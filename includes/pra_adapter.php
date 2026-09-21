<?php

require_once __DIR__ . '/helpers.php';

/**
 * Isolated PRA integration layer.
 * Swap the mock branch for a live HTTP client when official credentials are available.
 */
function pra_submit_invoice(PDO $pdo, array $invoice, array $lines): array
{
    $cfg = pra_config();
    $company = company_settings();

    $payload = [
        'seller' => [
            'name' => $company['name'] ?? '',
            'ntn' => $cfg['seller_ntn'] ?: ($company['ntn'] ?? ''),
            'strn' => $company['strn'] ?? '',
        ],
        'buyer' => [
            'name' => $invoice['customer_name'] ?? '',
            'ntn_cnic' => $invoice['ntn_cnic'] ?? '',
            'type' => $invoice['customer_type'] ?? '',
        ],
        'invoice' => [
            'doc_no' => $invoice['doc_no'] ?? '',
            'doc_date' => $invoice['doc_date'] ?? '',
            'payment_mode' => $invoice['payment_mode'] ?? '',
            'sub_total' => (float) ($invoice['sub_total'] ?? 0),
            'tax_amount' => (float) ($invoice['tax_amount'] ?? 0),
            'net_amount' => (float) ($invoice['net_amount'] ?? 0),
        ],
        'items' => array_map(static function (array $line): array {
            return [
                'name' => $line['item_name'] ?? '',
                'description' => $line['description'] ?? '',
                'qty' => (float) ($line['qty'] ?? 0),
                'rate' => (float) ($line['rate'] ?? 0),
                'amount' => (float) ($line['amount'] ?? 0),
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'tax_amount' => (float) ($line['tax_amount'] ?? 0),
                'net_amount' => (float) ($line['net_amount'] ?? 0),
            ];
        }, $lines),
    ];

    $result = pra_mock_call($payload, (int) ($cfg['sandbox_mode'] ?? 1) === 1);

    $stmt = $pdo->prepare(
        'INSERT INTO pra_invoice_log (invoice_id, request_data, response_data, pra_status, pra_invoice_no, error_message)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $invoice['id'],
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        $result['pra_status'],
        $result['pra_invoice_no'],
        $result['error_message'],
    ]);

    $status = $result['ok'] ? 'PRA_APPROVED' : ($result['rejected'] ? 'PRA_REJECTED' : 'PENDING');
    $upd = $pdo->prepare(
        'UPDATE invoice_m SET status = ?, pra_status = ?, pra_invoice_no = ? WHERE id = ?'
    );
    $upd->execute([
        $status,
        $result['pra_status'],
        $result['pra_invoice_no'],
        (int) $invoice['id'],
    ]);

    return [
        'ok' => $result['ok'],
        'status' => $status,
        'pra_status' => $result['pra_status'],
        'pra_invoice_no' => $result['pra_invoice_no'],
        'error_message' => $result['error_message'],
    ];
}

function pra_mock_call(array $payload, bool $sandbox): array
{
    $docNo = (string) ($payload['invoice']['doc_no'] ?? '');
    $buyerType = (string) ($payload['buyer']['type'] ?? '');
    $buyerId = trim((string) ($payload['buyer']['ntn_cnic'] ?? ''));

    if ($buyerType === 'Registered' && $buyerId === '') {
        return [
            'ok' => false,
            'rejected' => true,
            'pra_status' => 'REJECTED',
            'pra_invoice_no' => '',
            'error_message' => 'PRA rejected: NTN is required for registered buyers.',
            'response' => [
                'mode' => $sandbox ? 'sandbox-mock' : 'mock',
                'code' => 'BUYER_NTN_REQUIRED',
                'message' => 'NTN is required for registered buyers.',
            ],
        ];
    }

    if ((float) ($payload['invoice']['net_amount'] ?? 0) <= 0) {
        return [
            'ok' => false,
            'rejected' => true,
            'pra_status' => 'REJECTED',
            'pra_invoice_no' => '',
            'error_message' => 'PRA rejected: invoice net amount must be greater than zero.',
            'response' => [
                'mode' => $sandbox ? 'sandbox-mock' : 'mock',
                'code' => 'INVALID_AMOUNT',
                'message' => 'Net amount must be greater than zero.',
            ],
        ];
    }

    // About 10% random failure so retry can be demonstrated.
    $fail = random_int(1, 10) === 1;
    if ($fail) {
        return [
            'ok' => false,
            'rejected' => false,
            'pra_status' => 'ERROR',
            'pra_invoice_no' => '',
            'error_message' => 'PRA service temporarily unavailable. Invoice saved; retry submission.',
            'response' => [
                'mode' => $sandbox ? 'sandbox-mock' : 'mock',
                'code' => 'TIMEOUT',
                'message' => 'Gateway timeout while posting invoice ' . $docNo,
            ],
        ];
    }

    $praNo = 'PRA-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    return [
        'ok' => true,
        'rejected' => false,
        'pra_status' => 'APPROVED',
        'pra_invoice_no' => $praNo,
        'error_message' => '',
        'response' => [
            'mode' => $sandbox ? 'sandbox-mock' : 'mock',
            'code' => 'OK',
            'praInvoiceNo' => $praNo,
            'message' => 'Invoice accepted by PRA sandbox.',
            'submittedAt' => date('c'),
        ],
    ];
}
