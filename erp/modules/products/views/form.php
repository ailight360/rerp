<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <div class="page-header">
        <h2><?= $action === 'create' ? '➕ New Product' : '✏️ Edit Product' ?></h2>
        <div class="page-actions">
            <a href="/products" class="btn btn-outline">← Back to List</a>
        </div>
    </div>
    
    <form method="POST" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <?php if ($action === 'edit' && $product): ?>
                <input type="hidden" name="id" value="<?= $product['id'] ?>">
                <input type="hidden" name="action" value="update">
            <?php else: ?>
                <input type="hidden" name="action" value="create">
            <?php endif; ?>
            
            <div class="grid-2">
                <div class="form-group">
                    <label for="code">Product Code *</label>
                    <input type="text" id="code" name="code" class="form-control" required 
                           value="<?= htmlspecialchars($product['code'] ?? '') ?>" 
                           placeholder="e.g., PROD-001">
                </div>
                
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required 
                           value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label for="product_type">Product Type</label>
                    <select id="product_type" name="product_type" class="form-control" onchange="togglePairFields()">
                        <option value="single" <?= ($product['product_type'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single Item</option>
                        <option value="pair" <?= ($product['product_type'] ?? 'single') === 'pair' ? 'selected' : '' ?>>Pair (2 Components)</option>
                    </select>
                    <small class="text-muted">Pair products are made of 2 single items in 1:1 ratio</small>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label for="unit_id">Unit</label>
                    <select id="unit_id" name="unit_id" class="form-control">
                        <option value="">Select Unit</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit['id'] ?>" <?= ($product['unit_id'] ?? 0) == $unit['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['short_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="tax_rate_id">Tax Rate</label>
                    <select id="tax_rate_id" name="tax_rate_id" class="form-control">
                        <option value="">No Tax</option>
                        <?php foreach ($taxRates as $tax): ?>
                            <option value="<?= $tax['id'] ?>" <?= ($product['tax_rate_id'] ?? 0) == $tax['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tax['name']) ?> (<?= $tax['rate'] ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Pair Components (shown only for pair products) -->
            <div id="pairComponents" style="display: none;" class="card bg-light mb-3">
                <div class="card-header">
                    <h4>🔗 Pair Components (1:1 Ratio)</h4>
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="component_a_id">Component A *</label>
                            <select id="component_a_id" name="component_a_id" class="form-control">
                                <option value="">Select First Component</option>
                                <?php foreach ($singleProducts as $sp): ?>
                                    <option value="<?= $sp['id'] ?>" 
                                            <?= ($product['pair']['component_a_id'] ?? 0) == $sp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sp['code']) ?> - <?= htmlspecialchars($sp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="component_b_id">Component B *</label>
                            <select id="component_b_id" name="component_b_id" class="form-control">
                                <option value="">Select Second Component</option>
                                <?php foreach ($singleProducts as $sp): ?>
                                    <option value="<?= $sp['id'] ?>" 
                                            <?= ($product['pair']['component_b_id'] ?? 0) == $sp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sp['code']) ?> - <?= htmlspecialchars($sp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted text-sm">
                        💡 1 pair = 1× Component A + 1× Component B<br>
                        Available pairs = MIN(Component A stock, Component B stock)
                    </p>
                </div>
            </div>
            
            <div class="grid-3">
                <div class="form-group">
                    <label for="purchase_price">Purchase Price</label>
                    <input type="number" id="purchase_price" name="purchase_price" class="form-control" 
                           step="0.01" min="0" value="<?= htmlspecialchars($product['purchase_price'] ?? 0) ?>">
                </div>
                
                <div class="form-group">
                    <label for="sale_price">Sale Price</label>
                    <input type="number" id="sale_price" name="sale_price" class="form-control" 
                           step="0.01" min="0" value="<?= htmlspecialchars($product['sale_price'] ?? 0) ?>">
                </div>
                
                <div class="form-group">
                    <label for="min_stock">Minimum Stock Level</label>
                    <input type="number" id="min_stock" name="min_stock" class="form-control" 
                           min="0" value="<?= htmlspecialchars($product['min_stock'] ?? 0) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <?= $action === 'create' ? 'Create Product' : 'Update Product' ?>
            </button>
            <a href="/products" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function togglePairFields() {
    const type = document.getElementById('product_type').value;
    const pairDiv = document.getElementById('pairComponents');
    pairDiv.style.display = type === 'pair' ? 'block' : 'none';
    
    // Make component fields required for pair products
    const compA = document.getElementById('component_a_id');
    const compB = document.getElementById('component_b_id');
    if (type === 'pair') {
        compA.required = true;
        compB.required = true;
    } else {
        compA.required = false;
        compB.required = false;
    }
}

// Initialize on page load
togglePairFields();
</script>
