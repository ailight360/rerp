<?php include __DIR__ . '/../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1><?= isset($stockIn->id) ? 'Edit' : 'New' ?> Stock In</h1>
        <p class="text-muted">Record incoming stock (delivery receipt or purchase with pricing)</p>
    </div>
    <a href="/stock_in" class="btn btn-outline">Cancel</a>
</div>

<form id="stockInForm" method="POST" action="<?= isset($stockIn->id) ? "/stock_in/update/{$stockIn->id}" : '/stock_in/store' ?>">
    <?= csrf_field() ?>
    
    <div class="grid grid-2 gap-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Basic Information</h4>
                
                <div class="form-group">
                    <label for="invoice_no">Invoice/Delivery No *</label>
                    <input type="text" id="invoice_no" name="invoice_no" class="input" 
                           value="<?= $stockIn->invoice_no ?? '' ?>" 
                           placeholder="Auto-generated if empty">
                </div>
                
                <div class="form-group">
                    <label for="vendor_id">Vendor</label>
                    <select id="vendor_id" name="vendor_id" class="input">
                        <option value="">-- Select Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v->id ?>" <?= (isset($stockIn->vendor_id) && $stockIn->vendor_id == $v->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" class="input" 
                           value="<?= $stockIn->date ?? date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="input">
                        <option value="draft" <?= (isset($stockIn->status) && $stockIn->status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="confirmed" <?= (!isset($stockIn->status) || $stockIn->status === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                        <option value="cancelled" <?= (isset($stockIn->status) && $stockIn->status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Pricing (Optional)</h4>
                <p class="text-muted text-sm mb-3">
                    Leave prices empty for delivery receipts. Enter prices for purchase invoices.
                </p>
                
                <div class="form-group">
                    <label for="discount">Discount</label>
                    <div class="flex gap-2">
                        <input type="number" id="discount" name="discount" class="input" step="0.01" 
                               value="<?= $stockIn->discount ?? 0 ?>" style="flex:1;">
                        <select name="discount_type" class="input" style="width: 120px;">
                            <option value="percent" <?= (isset($stockIn->discount_type) && $stockIn->discount_type === 'percent') ? 'selected' : '' ?>>%</option>
                            <option value="flat" <?= (isset($stockIn->discount_type) && $stockIn->discount_type === 'flat') ? 'selected' : '' ?>>Flat</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="input" rows="3"><?= $stockIn->notes ?? '' ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="flex justify-between items-center">
                <h3>Items</h3>
                <button type="button" onclick="addLineItem()" class="btn btn-primary btn-sm">
                    <svg class="icon"><use href="#icon-plus"></use></svg>
                    Add Item
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0" id="items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Product</th>
                        <th>Type</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 15%;">Unit Price</th>
                        <th style="width: 12%;">Tax Rate</th>
                        <th style="width: 13%;">Total</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <!-- Items will be added here -->
                </tbody>
                <tfoot id="items-footer" class="bg-light" style="display: none;">
                    <tr>
                        <td colspan="4" class="text-right font-bold">Subtotal:</td>
                        <td colspan="3" id="subtotal-display">0.00</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-right font-bold">Tax:</td>
                        <td colspan="3" id="tax-display">0.00</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-right font-bold">Total:</td>
                        <td colspan="3" id="total-display">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
            <svg class="icon"><use href="#icon-save"></use></svg>
            Save Stock In
        </button>
        <button type="button" onclick="window.history.back()" class="btn btn-outline btn-lg">Cancel</button>
    </div>
</form>

<!-- Product search modal -->
<div id="product-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Select Product</h3>
            <button onclick="closeModal('product-modal')" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="text" id="product-search" class="input mb-3" 
                   placeholder="Search by name, code, or scan barcode..." autofocus>
            <div id="product-results" class="list-group"></div>
        </div>
    </div>
</div>

<script>
let currentLineIndex = 0;
let lineItems = <?= isset($stockIn->items) ? json_encode($stockIn->items) : '[]' ?>;

// Initialize with existing items
document.addEventListener('DOMContentLoaded', function() {
    lineItems.forEach((item, index) => {
        addLineItemRow(item);
    });
    updateTotals();
});

function addLineItem() {
    openModal('product-modal');
    document.getElementById('product-search').value = '';
    document.getElementById('product-results').innerHTML = '';
    document.getElementById('product-search').focus();
}

// Product search
document.getElementById('product-search').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    if (query.length < 2) return;
    
    fetch(`/api/products.php?search=${encodeURIComponent(query)}`)
        .then(r => r.json())
        .then(products => {
            const resultsDiv = document.getElementById('product-results');
            if (products.length === 0) {
                resultsDiv.innerHTML = '<div class="text-muted py-3">No products found</div>';
                return;
            }
            
            resultsDiv.innerHTML = products.map(p => `
                <div class="list-group-item" onclick="selectProduct(${p.id}, '${escapeHtml(p.name)}', '${p.product_type}', ${p.current_stock || 0})">
                    <div class="flex justify-between">
                        <div>
                            <strong>${escapeHtml(p.name)}</strong>
                            <span class="badge badge-info ml-2">${p.product_type === 'pair' ? 'Pair' : 'Single'}</span>
                            ${p.code ? `<span class="text-muted text-sm">(${escapeHtml(p.code)})</span>` : ''}
                        </div>
                        <div class="text-muted">Stock: ${p.current_stock || 0}</div>
                    </div>
                    ${p.product_type === 'pair' && p.pair_components ? 
                        `<div class="text-muted text-sm mt-1">${escapeHtml(p.pair_components)}</div>` : ''}
                </div>
            `).join('');
        });
});

function selectProduct(id, name, type, stock) {
    const item = {
        product_id: id,
        product_name: name,
        item_type: type,
        quantity: 1,
        unit_price: null,
        tax_rate_id: null,
        total: 0
    };
    
    lineItems.push(item);
    addLineItemRow(item, lineItems.length - 1);
    closeModal('product-modal');
    updateTotals();
}

function addLineItemRow(item, index = null) {
    if (index === null) {
        index = currentLineIndex++;
    }
    
    const tbody = document.getElementById('items-body');
    const row = document.createElement('tr');
    row.dataset.index = index;
    
    row.innerHTML = `
        <td>
            <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}" class="product-id">
            <input type="text" value="${escapeHtml(item.product_name)}" class="input input-sm" readonly 
                   onclick="editLineItem(${index})" style="cursor: pointer;">
        </td>
        <td>
            <span class="badge ${item.item_type === 'pair' ? 'badge-info' : 'badge-secondary'}">
                ${item.item_type === 'pair' ? 'Pair' : 'Single'}
            </span>
            <input type="hidden" name="items[${index}][item_type]" value="${item.item_type}">
        </td>
        <td>
            <input type="number" name="items[${index}][quantity]" value="${item.quantity}" 
                   class="input input-sm quantity" min="0.001" step="0.001" required
                   onchange="updateLineTotal(${index})">
        </td>
        <td>
            <input type="number" name="items[${index}][unit_price]" value="${item.unit_price || ''}" 
                   class="input input-sm unit-price" min="0" step="0.01" placeholder="Optional"
                   onchange="updateLineTotal(${index})">
        </td>
        <td>
            <select name="items[${index}][tax_rate_id]" class="input input-sm tax-rate" onchange="updateLineTotal(${index})">
                <option value="">-- None --</option>
                <?php foreach ($taxRates as $tr): ?>
                    <option value="<?= $tr->id ?>" <?= (isset($item->tax_rate_id) && $item->tax_rate_id == $tr->id) ? 'selected' : '' ?>>
                        <?= $tr->name ?> (<?= $tr->rate ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <span class="line-total font-medium"><?= isset($item->total) ? Helpers::formatCurrency($item->total) : '0.00' ?></span>
        </td>
        <td>
            <button type="button" onclick="removeLineItem(${index})" class="btn btn-sm btn-outline text-danger">
                <svg class="icon"><use href="#icon-trash"></use></svg>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    document.getElementById('items-footer').style.display = 'table-row-group';
}

function editLineItem(index) {
    // Could open modal to change product - for now just alert
    showToast('Remove and re-add to change product', 'info');
}

function removeLineItem(index) {
    lineItems.splice(index, 1);
    renderAllItems();
    updateTotals();
}

function renderAllItems() {
    const tbody = document.getElementById('items-body');
    tbody.innerHTML = '';
    currentLineIndex = 0;
    
    lineItems.forEach((item, index) => {
        addLineItemRow(item, index);
    });
}

function updateLineTotal(index) {
    const row = document.querySelector(`tr[data-index="${index}"]`);
    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
    const taxRateId = row.querySelector('.tax-rate').value;
    
    const lineTotal = quantity * unitPrice;
    row.querySelector('.line-total').textContent = formatCurrency(lineTotal);
    
    lineItems[index].quantity = quantity;
    lineItems[index].unit_price = unitPrice || null;
    lineItems[index].tax_rate_id = taxRateId || null;
    lineItems[index].total = lineTotal;
    
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    let tax = 0;
    
    lineItems.forEach(item => {
        if (item.unit_price && item.quantity) {
            const lineTotal = item.unit_price * item.quantity;
            subtotal += lineTotal;
            
            if (item.tax_rate_id) {
                // Would need to fetch tax rate - simplified here
                // tax += lineTotal * (rate / 100);
            }
        }
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const discountType = document.querySelector('[name="discount_type"]').value;
    
    if (discount > 0) {
        if (discountType === 'percent') {
            subtotal -= subtotal * (discount / 100);
        } else {
            subtotal -= discount;
        }
    }
    
    const total = subtotal + tax;
    
    document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
    document.getElementById('tax-display').textContent = formatCurrency(tax);
    document.getElementById('total-display').textContent = formatCurrency(total);
}

// Form submission
document.getElementById('stockInForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (lineItems.length === 0) {
        showToast('Please add at least one item', 'error');
        return;
    }
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.href = '/stock_in', 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Error saving stock in', 'error');
        console.error(err);
    });
});

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
}
</script>
