$(function () {
    const modal = $('#transaction-item-modal');
    $('.select2').each(function () {
        const parent = $(this).closest('.modal');
        $(this).select2({theme: 'bootstrap4', width: '100%', dropdownParent: parent.length ? parent : $(document.body)});
    });
    const type = String($('#transaction-type').val());
    $('[data-transaction-types]').each(function () {
        const active = String($(this).data('transaction-types')).split(',').includes(type);
        $(this).toggle(active).find('select').prop('disabled', !active || $(this).data('locked') === 1).prop('required', active);
    });
    $('#transaction-notes').prop('required', type === '3');
    const read = (id, fallback) => document.getElementById(id) ? JSON.parse(document.getElementById(id).textContent) : fallback;
    const stocks = read('transaction-stock-data', []);
    const messages = read('transaction-form-messages', {});
    const create = $('#transaction-create-form');
    const item = $('#line-item');
    const unit = $('#line-unit');
    const lot = $('#line-lot');
    const unitOptions = unit.find('option').clone();
    let pending = read('transaction-pending-data', []);
    if (!Array.isArray(pending)) pending = Object.values(pending);
    const number = value => Number(value).toLocaleString('id-ID', {maximumFractionDigits: 6});
    function lineTotal(qty, price) {
        qty = String(qty ?? '');
        price = String(price ?? '');
        if (!/^\d{1,18}(\.\d{1,6})?$/.test(qty) || !/^\d{1,18}(\.\d{1,2})?$/.test(price)) return '-';
        const scaled = (value, scale) => {
            const [whole, fraction = ''] = value.split('.');
            return BigInt(whole + fraction.padEnd(scale, '0'));
        };
        const cents = (scaled(qty, 6) * scaled(price, 2) + 500000n) / 1000000n;
        return 'Rp ' + (cents / 100n).toLocaleString('id-ID') + ',' + String(cents % 100n).padStart(2, '0');
    }
    function updateTotal() {
        $('#line-total').text(lineTotal($('#line-qty').val(), $('#line-price').val()));
    }
    $('#line-qty, #line-price').on('input change', updateTotal);
    function available(id) {
        const origin = $('#transaction-from').val();
        const date = $('#transaction-date').val();
        const today = new Date();
        const localToday = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
        const cutoff = date > localToday ? date : localToday;
        return stocks.filter(row => row.item_id === id && row.organization_id === origin && row.location === 'AVAILABLE' && Number(row.qty) > 0 && (type !== '2' || !row.expiry_date || row.expiry_date >= cutoff));
    }
    function stockText(id) {
        const rows = available(id);
        const selected = unit.find(':selected');
        const factor = Number(selected.data('factor'));
        const total = rows.reduce((sum, row) => sum + Number(row.qty), 0);
        if (unit.val() && Number.isFinite(factor) && factor > 0) {
            return number(total / factor) + ' ' + selected.data('unit-name');
        }
        return number(total) + ' ' + (rows[0]?.unit || '');
    }
    function updateStock() {
        $('#line-stock-value').text($('#transaction-from').val() ? stockText(String(item.val())) : messages.chooseOrganization);
    }
    unit.on('change', updateStock);
    function updateLine() {
        updateTotal();
        const id = String(item.val());
        const selectedUnit = unit.val();
        unit.empty().append(unitOptions.filter(function () { return !this.value || $(this).data('item') === id; }).clone());
        unit.val(unit.find('option').filter(function () { return this.value === selectedUnit; }).length ? selectedUnit : '').trigger('change');
        $('#line-expiry').prop('required', item.find(':selected').data('expirable') === 1);
        if (type !== '1') {
            const selectedLot = lot.val();
            const emptyOption = lot.find('option[value=""]').first().clone();
            lot.empty().append(emptyOption);
            available(id).forEach(row => lot.append(new Option(row.batch_number + ' — ' + number(row.qty) + ' ' + row.unit + ' — ' + (row.expiry_date || '-'), row.stock_lot_id)));
            lot.val(lot.find('option').filter(function () { return this.value === selectedLot; }).length ? selectedLot : '').trigger('change');
            $('#line-stock').prop('hidden', !item.val());
            updateStock();
            $('#transaction-line-form button[type="submit"]').prop('disabled', !available(id).length);
        } else $('#line-stock').prop('hidden', true);
    }
    function sufficientStock(lines) {
        if (type === '1') return true;
        const balances = new Map();
        const ordered = [...lines].sort((a, b) => Number(Boolean(b.stock_lot_id)) - Number(Boolean(a.stock_lot_id)));
        for (const line of ordered) {
            if (!balances.has(line.item_id)) {
                balances.set(line.item_id, available(line.item_id).map(row => ({...row, remaining: Number(row.qty)})).sort((a, b) => (a.expiry_date || '9999').localeCompare(b.expiry_date || '9999') || a.stock_lot_id.localeCompare(b.stock_lot_id)));
            }
            const option = unitOptions.filter(function () { return this.value === line.unit_id && $(this).data('item') === line.item_id; });
            let needed = line.base_qty != null ? Number(line.base_qty) : Number(line.qty) * Number(option.data('factor'));
            if (!Number.isFinite(needed) || needed <= 0) return false;
            for (const row of balances.get(line.item_id)) {
                if (line.stock_lot_id && line.stock_lot_id !== row.stock_lot_id) continue;
                const amount = Math.min(needed, row.remaining);
                row.remaining -= amount;
                needed -= amount;
            }
            if (needed > 0.00000001) return false;
        }
        return true;
    }
    $('#line-qty').on('input', function () { this.setCustomValidity(''); });
    item.add(unit).add(lot).on('change', function () { document.getElementById('line-qty')?.setCustomValidity(''); });
    $('#transaction-line-form').on('submit', function (event) {
        const line = {};
        $(this).serializeArray().forEach(field => { if (field.name !== '_csrf_token') line[field.name] = field.value; });
        const existing = create.length ? pending : read('transaction-existing-lines', []);
        if (!sufficientStock([...existing, line])) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const qty = document.getElementById('line-qty');
            qty.setCustomValidity(messages.insufficientStock);
            qty.reportValidity();
        }
    });
    create.on('submit', function (event) {
        if (!sufficientStock(pending)) {
            event.preventDefault();
            $('#transaction-stock-error').text(messages.insufficientStock).prop('hidden', false);
        }
    });
    function renderPending() {
        const tbody = $('#transaction-pending-items').empty();
        pending.forEach((line, index) => {
            const option = item.find('option').filter(function () { return this.value === line.item_id; });
            const unitOption = unitOptions.filter(function () { return this.value === line.unit_id && $(this).data('item') === line.item_id; });
            const selectedLot = stocks.find(row => row.stock_lot_id === line.stock_lot_id);
            const tr = $('<tr>');
            [option.data('code') || '-', option.data('name') || line.item_id, line.expiry_date || selectedLot?.expiry_date || '-', line.batch_number || selectedLot?.batch_number || messages.automatic, line.qty, unitOption.text()].forEach(text => $('<td>').text(text).appendTo(tr));
            if (type === '1') {
                $('<td class="text-right text-nowrap">').text(lineTotal('1', line.price)).appendTo(tr);
                $('<td class="text-right text-nowrap">').text(lineTotal(line.qty, line.price)).appendTo(tr);
            }
            const action = $('<td>').appendTo(tr);
            $('<button type="button" class="btn btn-sm btn-outline-danger">').text(messages.remove).on('click', function () { pending.splice(index, 1); renderPending(); }).appendTo(action);
            Object.entries(line).forEach(([key, value]) => {
                if (['line_id', 'item_id', 'unit_id', 'qty', 'price', 'batch_number', 'expiry_date', 'stock_lot_id'].includes(key)) $('<input type="hidden">').attr('name', 'items[' + index + '][' + key + ']').val(value).appendTo(action);
            });
            tbody.append(tr);
        });
        $('#transaction-empty-items').toggle(!pending.length);
    }
    modal.on('hidden.bs.modal', function () {
        const form = $('#transaction-line-form');
        form[0]?.reset();
        form.find('input:not([type="hidden"]), textarea').val('').each(function () { this.setCustomValidity(''); });
        form.find('select').val('').trigger('change');
        form.removeClass('was-validated').find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        $('#line-stock-value').empty();
        $('#line-stock').prop('hidden', true);
        updateLine();
    });
    modal.on('shown.bs.modal', updateLine);
    item.on('change', updateLine);
    $('#transaction-from, #transaction-date').on('change', function () { updateLine(); renderPending(); });
    if (create.length) {
        $('#transaction-line-form').on('submit', function (event) {
            event.preventDefault();
            const line = {};
            $(this).serializeArray().forEach(field => { if (field.name !== '_csrf_token') line[field.name] = field.value; });
            pending.push(line);
            renderPending();
            modal.modal('hide');
        });
        renderPending();
    }
    updateLine();
    if (messages.lineError) modal.modal('show');
    $('.transaction-action').on('submit', function () { $(this).find('button[type="submit"]').prop('disabled', true); });
});
