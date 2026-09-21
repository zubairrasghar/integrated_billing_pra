(function () {
    const tbody = document.getElementById('line-body');
    const items = window.INVOICE_ITEMS || [];
    const customers = window.INVOICE_CUSTOMERS || [];

    function money(n) {
        return (Math.round((Number(n) || 0) * 100) / 100);
    }

    function fmt(n) {
        return money(n).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function itemOptions(selected) {
        let html = '<option value="">Free text / select</option>';
        items.forEach(function (it) {
            const sel = String(it.id) === String(selected) ? ' selected' : '';
            html += '<option value="' + esc(it.id) + '"' + sel + '>' + esc(it.code) + ' — ' + esc(it.name) + '</option>';
        });
        return html;
    }

    function addRow(data) {
        data = data || {};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select name="item_id[]" class="form-select form-select-sm item-id">' + itemOptions(data.item_id || '') + '</select></td>' +
            '<td><input name="item_name[]" class="form-control form-control-sm item-name" required value="' + esc(data.item_name || '') + '"></td>' +
            '<td><input name="description[]" class="form-control form-control-sm" value="' + esc(data.description || '') + '"></td>' +
            '<td><input name="qty[]" type="number" step="0.001" min="0" class="form-control form-control-sm qty" value="' + (data.qty || '1') + '"></td>' +
            '<td><input name="rate[]" type="number" step="0.01" min="0" class="form-control form-control-sm rate" value="' + (data.rate || '0') + '"></td>' +
            '<td class="text-end amount">0.00</td>' +
            '<td><input name="tax_rate[]" type="number" step="0.01" min="0" class="form-control form-control-sm tax-rate" value="' + (data.tax_rate || '16') + '"></td>' +
            '<td class="text-end tax-amt">0.00</td>' +
            '<td class="text-end net-amt">0.00</td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>';
        tbody.appendChild(tr);
        recalc();
    }

    function recalc() {
        let sub = 0, tax = 0, net = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            const qty = money(tr.querySelector('.qty').value);
            const rate = money(tr.querySelector('.rate').value);
            const taxRate = money(tr.querySelector('.tax-rate').value);
            const amount = money(qty * rate);
            const taxAmt = money(amount * taxRate / 100);
            const lineNet = money(amount + taxAmt);
            tr.querySelector('.amount').textContent = fmt(amount);
            tr.querySelector('.tax-amt').textContent = fmt(taxAmt);
            tr.querySelector('.net-amt').textContent = fmt(lineNet);
            sub += amount;
            tax += taxAmt;
            net += lineNet;
        });
        document.getElementById('sub-total').textContent = fmt(sub);
        document.getElementById('tax-total').textContent = fmt(tax);
        document.getElementById('grand-total').textContent = fmt(net);
        document.getElementById('sub_total').value = money(sub);
        document.getElementById('tax_amount').value = money(tax);
        document.getElementById('net_amount').value = money(net);
    }

    tbody.addEventListener('input', recalc);
    tbody.addEventListener('change', function (e) {
        const sel = e.target.closest('.item-id');
        if (!sel) {
            recalc();
            return;
        }
        const item = items.find(function (it) { return String(it.id) === String(sel.value); });
        const tr = sel.closest('tr');
        if (item) {
            tr.querySelector('.item-name').value = item.name;
            tr.querySelector('.rate').value = item.default_rate;
            tr.querySelector('.tax-rate').value = item.default_tax_rate;
        }
        recalc();
    });

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        const rows = tbody.querySelectorAll('tr');
        if (rows.length <= 1) return;
        btn.closest('tr').remove();
        recalc();
    });

    document.getElementById('add-row').addEventListener('click', function () {
        addRow();
    });

    const customerSel = document.getElementById('customer_id');
    customerSel.addEventListener('change', function () {
        const c = customers.find(function (x) { return String(x.id) === String(customerSel.value); });
        if (!c) return;
        document.getElementById('customer_name').value = c.name;
        document.getElementById('ntn_cnic').value = c.ntn_cnic || '';
        document.getElementById('customer_type').value = c.customer_type || 'Unregistered';
    });

    const form = document.getElementById('invoice-form');
    form.addEventListener('submit', function (e) {
        if (!tbody.querySelector('tr')) {
            e.preventDefault();
            alert('Add at least one invoice line.');
            return;
        }
        const names = [...tbody.querySelectorAll('.item-name')].map(function (i) { return i.value.trim(); });
        if (names.every(function (n) { return n === ''; })) {
            e.preventDefault();
            alert('Enter a service or item on at least one line.');
        }
    });

    const saveBtn = document.getElementById('save-customer');
    if (saveBtn) {
        saveBtn.addEventListener('click', async function () {
            const payload = {
                csrf_token: document.querySelector('[name="csrf_token"]').value,
                name: document.getElementById('new_name').value.trim(),
                ntn_cnic: document.getElementById('new_ntn').value.trim(),
                customer_type: document.getElementById('new_type').value,
                phone: document.getElementById('new_phone').value.trim(),
                address: document.getElementById('new_address').value.trim()
            };
            if (!payload.name) {
                alert('Customer name is required.');
                return;
            }
            const res = await fetch('customer_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload.csrf_token },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'Could not save customer.');
                return;
            }
            customers.push(data.customer);
            const opt = document.createElement('option');
            opt.value = data.customer.id;
            opt.textContent = data.customer.name;
            opt.selected = true;
            customerSel.appendChild(opt);
            customerSel.dispatchEvent(new Event('change'));
            bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
            document.getElementById('new_name').value = '';
            document.getElementById('new_ntn').value = '';
            document.getElementById('new_phone').value = '';
            document.getElementById('new_address').value = '';
        });
    }

    addRow();
})();
