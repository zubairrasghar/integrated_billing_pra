<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$pdo = db();

$customers = $pdo->query('SELECT id, name, ntn_cnic, customer_type FROM customers WHERE is_active = 1 ORDER BY name')->fetchAll();
$items = $pdo->query('SELECT id, code, name, default_rate, default_tax_rate FROM items WHERE is_active = 1 ORDER BY code')->fetchAll();
$docNo = peek_doc_no();

layout_start('Create Invoice', 'create', $user);
?>
<form id="invoice-form" method="post" action="<?= e(base_url('invoice_save.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="sub_total" id="sub_total" value="0">
    <input type="hidden" name="tax_amount" id="tax_amount" value="0">
    <input type="hidden" name="net_amount" id="net_amount" value="0">

    <div class="card-soft form-section">
        <h2>Invoice header</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Invoice no.</label>
                <input class="form-control" value="<?= e($docNo) ?>" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">Invoice date</label>
                <input class="form-control" type="date" name="doc_date" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer</label>
                <div class="input-group">
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#customerModal">New</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Payment mode</label>
                <select class="form-select" name="payment_mode" required>
                    <option>Cash</option>
                    <option>Bank</option>
                    <option>Card</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer name</label>
                <input class="form-control" id="customer_name" name="customer_name" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">NTN / CNIC</label>
                <input class="form-control" id="ntn_cnic" name="ntn_cnic">
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer type</label>
                <select class="form-select" id="customer_type" name="customer_type" required>
                    <option>Unregistered</option>
                    <option>Registered</option>
                    <option>Individual</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card-soft form-section">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Invoice details</h2>
            <button class="btn btn-sm btn-outline-primary" type="button" id="add-row">Add line</button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle line-table mb-0">
                <thead>
                    <tr>
                        <th style="min-width:180px">Service / item</th>
                        <th style="min-width:140px">Name</th>
                        <th style="min-width:140px">Description</th>
                        <th style="width:90px">Qty</th>
                        <th style="width:110px">Rate</th>
                        <th class="text-end" style="width:100px">Amount</th>
                        <th style="width:90px">Tax %</th>
                        <th class="text-end" style="width:100px">Tax</th>
                        <th class="text-end" style="width:110px">Net</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="line-body"></tbody>
            </table>
        </div>
        <div class="totals-box mt-3">
            <div class="row-line"><span>Sub total</span><strong id="sub-total">0.00</strong></div>
            <div class="row-line"><span>PRA sales tax</span><strong id="tax-total">0.00</strong></div>
            <div class="row-line grand"><span>Grand total</span><span id="grand-total">0.00</span></div>
        </div>
        <div class="actions-bar">
            <a class="btn btn-outline-secondary" href="<?= e(base_url('billing.php')) ?>">Cancel</a>
            <button class="btn btn-ok" type="submit">OK</button>
        </div>
    </div>
</form>

<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input class="form-control" id="new_name">
                </div>
                <div class="mb-2">
                    <label class="form-label">NTN / CNIC</label>
                    <input class="form-control" id="new_ntn">
                </div>
                <div class="mb-2">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="new_type">
                        <option>Unregistered</option>
                        <option>Registered</option>
                        <option>Individual</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Phone</label>
                    <input class="form-control" id="new_phone">
                </div>
                <div class="mb-0">
                    <label class="form-label">Address</label>
                    <input class="form-control" id="new_address">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" type="button" id="save-customer">Save customer</button>
            </div>
        </div>
    </div>
</div>

<script>
window.INVOICE_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
window.INVOICE_CUSTOMERS = <?= json_encode($customers, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
layout_end('assets/js/invoice.js');
