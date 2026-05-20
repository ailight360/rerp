<?php include __DIR__ . '/../../layout.php'; layoutHeader('Quotation Form'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-edit"></i> <?= $quotation ? 'Edit' : 'New' ?> Quotation</h1>
        <p class="text-muted">Create or edit customer quotation</p>
    </div>
    <a href="?module=quotations" class="btn btn-secondary">
        <i class="icon-arrow-left"></i> Cancel
    </a>
</div>

<form method="POST" action="?module=quotations&action=save" id="quotation-form" class="card">
    <?= Helpers::csrfField() ?>
    
    <?php if ($quotation): ?>
        <input type="hidden" name="id" value="<?= $quotation['id'] ?>">
        <div class="form-group">
            <label class="form-label">Quotation Number</label>
            <input type="text" name="quote_no" class="form-control" 
                   value="<?= htmlspecialchars($quotation['quote_no']) ?>" readonly>
        </div>
    <?php endif; ?>

    <div class="grid grid-3">
        <div class="form-group">
            <label class="form-label required">Customer</label>
            <select name="customer_id" class="form-control" required>
                <option value="">Select Customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" 
                            <?= ($quotation && $quotation['customer_id'] == $c['id']) || (!$quotation && false) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Date</label>
            <input type="date" name="date" class="form-control" 
                   value="<?= $quotation ? $quotation['date'] : date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Valid Until</label>
            <input type="date" name="valid_until" class="form-control" 
                   value="<?= $quotation ? $quotation['valid_until'] : date('Y-m-d', strtotime('+30 days')) ?>">
        </div>
    </div>

    <!-- Line Items -->
    <div class="form-group mt-4">
        <label class="form-label required">Items</label>
        <div id="items-container">
            <?php 
            $itemIndex = 0;
            if (!empty($items)):
                foreach ($items as $item): 
            ?>
                <div class="item-row card p-3 mb-2" data-index="<?= $itemIndex ?>">
                    <div class="grid grid-12 gap-2 align-center">
                        <div class="col-4">
                            <input type="hidden" name="items[<?= $itemIndex ?>][product_id]" 
                                   value="<?= $item['product_id'] ?>">
                            <input type="text" class="form-control product-search" 
                                   value="<?= htmlspecialchars($item['product_code'] . ' - ' . $item['product_name']) ?>"
                                   placeholder="Search product" required>
                        </div>
                        <div class="col-2">
                            <input type="number" name="items[<?= $itemIndex ?>][quantity]" 
                                   class="form-control qty-input" step="0.001" min="0"
                                   value="<?= number_format($item['quantity'], 3) ?>" required>
                        </div>
                        <div class="col-2">
                            <input type="number" name="items[<?= $itemIndex ?>][unit_price]" 
                                   class="form-control price-input" step="0.01" min="0"
                                   value="<?= number_format($item['unit_price'], 2) ?>" required>
                        </div>
                        <div class="col-2">
                            <select name="items[<?= $itemIndex ?>][tax_rate_id]" class="form-control tax-select">
                                <option value="">No Tax</option>
                                <?php foreach ($taxRates as $tax): ?>
                                    <option value="<?= $tax['id'] ?>" 
                                            <?= $item['tax_rate_id'] == $tax['id'] ? 'selected' : '' ?>>
                                        <?= $tax['name'] ?> (<?= $tax['rate'] ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-1 text-right">
                            <span class="line-total"><?= formatCurrency($item['total']) ?></span>
                        </div>
                        <div class="col-1">
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="icon-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                $itemIndex++;
                endforeach;
            else: 
            ?>
                <div class="item-row card p-3 mb-2" data-index="0">
                    <div class="grid grid-12 gap-2 align-center">
                        <div class="col-4">
                            <input type="hidden" name="items[0][product_id]" value="">
                            <input type="text" class="form-control product-search" 
                                   placeholder="Search product" required>
                        </div>
                        <div class="col-2">
                            <input type="number" name="items[0][quantity]" 
                                   class="form-control qty-input" step="0.001" min="0" value="1" required>
                        </div>
                        <div class="col-2">
                            <input type="number" name="items[0][unit_price]" 
                                   class="form-control price-input" step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-2">
                            <select name="items[0][tax_rate_id]" class="form-control tax-select">
                                <option value="">No Tax</option>
                                <?php foreach ($taxRates as $tax): ?>
                                    <option value="<?= $tax['id'] ?>"><?= $tax['name'] ?> (<?= $tax['rate'] ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-1 text-right">
                            <span class="line-total"><?= formatCurrency(0) ?></span>
                        </div>
                        <div class="col-1">
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="icon-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <button type="button" class="btn btn-secondary mt-2" id="add-item-btn">
            <i class="icon-plus"></i> Add Item
        </button>
    </div>

    <!-- Totals -->
    <div class="grid grid-2 mt-4">
        <div></div>
        <div class="card p-3 bg-light">
            <div class="flex justify-between mb-2">
                <span>Subtotal:</span>
                <strong id="subtotal-display"><?= formatCurrency($quotation ? $quotation['subtotal'] : 0) ?></strong>
            </div>
            <div class="grid grid-3 gap-2 mb-2">
                <div>
                    <label class="form-label text-xs">Discount</label>
                    <input type="number" name="discount" id="discount-input" 
                           class="form-control form-control-sm" step="0.01" min="0"
                           value="<?= $quotation ? $quotation['discount'] : 0 ?>">
                </div>
                <div>
                    <label class="form-label text-xs">Type</label>
                    <select name="discount_type" id="discount-type" class="form-control form-control-sm">
                        <option value="percent" <?= ($quotation && $quotation['discount_type'] == 'percent') ? 'selected' : '' ?>>%</option>
                        <option value="flat" <?= ($quotation && $quotation['discount_type'] == 'flat') ? 'selected' : '' ?>>Flat</option>
                    </select>
                </div>
                <div class="text-right">
                    <label class="form-label text-xs">Discount Amount</label>
                    <div id="discount-amount" class="form-control-plaintext text-right">
                        <?= formatCurrency($quotation ? $quotation['discount'] : 0) ?>
                    </div>
                </div>
            </div>
            <div class="flex justify-between mb-2">
                <span>Tax:</span>
                <strong id="tax-display"><?= formatCurrency($quotation ? $quotation['tax'] : 0) ?></strong>
            </div>
            <div class="flex justify-between pt-2 border-top">
                <span class="text-lg">Total:</span>
                <strong class="text-lg text-primary" id="total-display"><?= formatCurrency($quotation ? $quotation['total'] : 0) ?></strong>
            </div>
        </div>
    </div>

    <div class="grid grid-2 mt-4">
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($quotation['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Terms & Conditions</label>
            <textarea name="terms" class="form-control" rows="3"><?= htmlspecialchars($quotation['terms'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="icon-check"></i> Save Quotation
        </button>
        <a href="?module=quotations" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
</form>

<script>
let itemCounter = <?= $itemIndex ?>;

// Add item row
document.getElementById('add-item-btn').addEventListener('click', function() {
    const container = document.getElementById('items-container');
    const newRow = document.createElement('div');
    newRow.className = 'item-row card p-3 mb-2';
    newRow.dataset.index = itemCounter;
    newRow.innerHTML = `
        <div class="grid grid-12 gap-2 align-center">
            <div class="col-4">
                <input type="hidden" name="items[${itemCounter}][product_id]" value="">
                <input type="text" class="form-control product-search" placeholder="Search product" required>
            </div>
            <div class="col-2">
                <input type="number" name="items[${itemCounter}][quantity]" class="form-control qty-input" step="0.001" min="0" value="1" required>
            </div>
            <div class="col-2">
                <input type="number" name="items[${itemCounter}][unit_price]" class="form-control price-input" step="0.01" min="0" value="0" required>
            </div>
            <div class="col-2">
                <select name="items[${itemCounter}][tax_rate_id]" class="form-control tax-select">
                    <option value="">No Tax</option>
                    <?php foreach ($taxRates as $tax): ?>
                        <option value="<?= $tax['id'] ?>"><?= $tax['name'] ?> (<?= $tax['rate'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-1 text-right">
                <span class="line-total"><?= formatCurrency(0) ?></span>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-danger btn-sm remove-item"><i class="icon-trash"></i></button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    itemCounter++;
    initProductSearch();
});

// Remove item
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        e.target.closest('.item-row').remove();
        calculateTotals();
    }
});

// Initialize product search
function initProductSearch() {
    document.querySelectorAll('.product-search').forEach(input => {
        // Simple autocomplete - in production, use AJAX
        input.addEventListener('focus', function() {
            // Could add autocomplete dropdown here
        });
    });
}

initProductSearch();

// Calculate totals
function calculateTotals() {
    let subtotal = 0;
    let taxTotal = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const taxRateId = row.querySelector('.tax-select').value;
        
        const lineTotal = qty * price;
        row.querySelector('.line-total').textContent = formatCurrency(lineTotal);
        
        subtotal += lineTotal;
        
        if (taxRateId) {
            // Would need to fetch tax rate - simplified here
        }
    });
    
    // Apply discount
    const discount = parseFloat(document.getElementById('discount-input').value) || 0;
    const discountType = document.getElementById('discount-type').value;
    let discountAmount = discountType === 'percent' ? subtotal * (discount / 100) : discount;
    
    document.getElementById('discount-amount').textContent = formatCurrency(discountAmount);
    
    const total = subtotal - discountAmount + taxTotal;
    
    document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
    document.getElementById('tax-display').textContent = formatCurrency(taxTotal);
    document.getElementById('total-display').textContent = formatCurrency(total);
}

// Listen for changes
document.getElementById('quotation-form').addEventListener('input', calculateTotals);
document.getElementById('quotation-form').addEventListener('change', calculateTotals);

function formatCurrency(amount) {
    return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}
</script>

<style>
.text-xs { font-size: 0.75rem; }
.form-control-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
.form-control-plaintext { background: transparent; border: none; }
.bg-light { background-color: #f8fafc; }
.border-top { border-top: 1px solid #e2e8f0; }
.text-lg { font-size: 1.125rem; }
.col-1 { grid-column: span 1; }
.col-2 { grid-column: span 2; }
.col-4 { grid-column: span 4; }
</style>

<?php layoutFooter(); ?>
