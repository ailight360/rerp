<?php include __DIR__ . '/../../layout.php'; layoutHeader('Adjust Stock'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-edit"></i> Adjust Stock Level</h1>
        <p class="text-muted">Manually adjust product stock quantity</p>
    </div>
    <a href="?module=inventory" class="btn btn-secondary">
        <i class="icon-arrow-left"></i> Back to Summary
    </a>
</div>

<form method="POST" action="?module=inventory&action=adjustSave" class="card max-w-2xl">
    <?= Helpers::csrfField() ?>
    
    <div class="form-group">
        <label class="form-label required">Product</label>
        <?php if ($product): ?>
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <div class="form-control-plaintext">
                <strong><?= htmlspecialchars($product['code']) ?></strong> - 
                <?= htmlspecialchars($product['name']) ?>
                <br>
                <small class="text-muted">
                    Category: <?= htmlspecialchars($product['category_name'] ?? '-') ?> | 
                    Current Stock: <strong class="text-primary"><?= number_format($product['current_stock'], 3) ?> <?= htmlspecialchars($product['unit_name'] ?? '') ?></strong>
                </small>
            </div>
        <?php else: ?>
            <select name="product_id" id="product-select" class="form-control" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-stock="<?= $p['current_stock'] ?>">
                        <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['name']) ?> 
                        (Stock: <?= number_format($p['current_stock'], 3) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label required">New Stock Quantity</label>
        <input type="number" name="new_quantity" id="new-quantity" 
               class="form-control" step="0.001" min="0" 
               value="<?= $product ? number_format($product['current_stock'], 3) : '' ?>" 
               required>
        <small class="text-muted">Enter the actual physical count you want to set</small>
    </div>

    <div class="form-group">
        <label class="form-label">Current Stock (for reference)</label>
        <input type="text" id="current-stock-display" class="form-control" readonly 
               value="<?= $product ? number_format($product['current_stock'], 3) : '0.000' ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Difference</label>
        <input type="text" id="difference-display" class="form-control" readonly value="0.000">
        <small class="text-muted" id="difference-text">No change</small>
    </div>

    <div class="form-group">
        <label class="form-label required">Reason for Adjustment</label>
        <select name="reason" class="form-control" required>
            <option value="">Select Reason</option>
            <option value="Physical Count">Physical Count/Stock Take</option>
            <option value="Damaged Goods">Damaged Goods</option>
            <option value="Lost/Theft">Lost/Theft</option>
            <option value="Data Correction">Data Correction</option>
            <option value="Return Processing">Return Processing</option>
            <option value="Other">Other</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3" 
                  placeholder="Additional details about this adjustment..."></textarea>
    </div>

    <div class="alert alert-warning">
        <i class="icon-alert-triangle"></i>
        <strong>Warning:</strong> This will immediately update the stock level and create a movement log entry.
        This action cannot be undone.
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="icon-check"></i> Save Adjustment
        </button>
        <a href="?module=inventory" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
</form>

<script>
// Calculate difference in real-time
const newQuantityInput = document.getElementById('new-quantity');
const currentStockDisplay = document.getElementById('current-stock-display');
const differenceDisplay = document.getElementById('difference-display');
const differenceText = document.getElementById('difference-text');
const productSelect = document.getElementById('product-select');

function calculateDifference() {
    const current = parseFloat(currentStockDisplay.value) || 0;
    const newVal = parseFloat(newQuantityInput.value) || 0;
    const diff = newVal - current;
    
    differenceDisplay.value = diff.toFixed(3);
    
    if (diff > 0) {
        differenceDisplay.classList.add('text-success');
        differenceDisplay.classList.remove('text-danger');
        differenceText.textContent = `Adding ${diff.toFixed(3)} units to stock`;
        differenceText.className = 'text-success';
    } else if (diff < 0) {
        differenceDisplay.classList.add('text-danger');
        differenceDisplay.classList.remove('text-success');
        differenceText.textContent = `Removing ${Math.abs(diff).toFixed(3)} units from stock`;
        differenceText.className = 'text-danger';
    } else {
        differenceDisplay.classList.remove('text-success', 'text-danger');
        differenceText.textContent = 'No change';
        differenceText.className = 'text-muted';
    }
}

// Update current stock when product is selected
if (productSelect) {
    productSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const stock = option.getAttribute('data-stock') || '0';
        currentStockDisplay.value = parseFloat(stock).toFixed(3);
        newQuantityInput.value = parseFloat(stock).toFixed(3);
        calculateDifference();
    });
}

// Listen for changes
newQuantityInput.addEventListener('input', calculateDifference);

// Initial calculation
calculateDifference();
</script>

<style>
.max-w-2xl { max-width: 600px; }
.form-control-plaintext { 
    padding: 0.5rem 0; 
    background: transparent; 
    border: none;
}
.text-success { color: #10b981; }
.text-danger { color: #ef4444; }
</style>

<?php layoutFooter(); ?>
