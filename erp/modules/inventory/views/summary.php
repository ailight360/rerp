<?php include __DIR__ . '/../../layout.php'; layoutHeader('Inventory Summary'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-box"></i> Inventory Summary</h1>
        <p class="text-muted">Stock levels with pair product availability</p>
    </div>
    <div class="flex gap-2">
        <a href="?module=inventory&action=adjustForm" class="btn btn-primary">
            <i class="icon-edit"></i> Adjust Stock
        </a>
        <a href="?module=inventory&action=exportCSV" class="btn btn-secondary">
            <i class="icon-download"></i> Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card mb-4">
    <input type="hidden" name="module" value="inventory">
    <div class="grid grid-4">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" 
                   placeholder="Product code or name" 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div>
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex align-end">
            <label class="checkbox-label mb-0">
                <input type="checkbox" name="low_stock" value="1" <?= $lowStockOnly ? 'checked' : '' ?>>
                <span>Show Low Stock Only</span>
            </label>
        </div>
        <div class="flex align-end gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="?module=inventory" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Stock Summary Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product Name</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Min Stock</th>
                    <th class="text-right">Purchase Price</th>
                    <th class="text-right">Sale Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No products found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr class="<?= $p['is_low_stock'] ? 'bg-warning-light' : '' ?>">
                            <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if ($p['product_type'] == 'pair'): ?>
                                    <span class="badge badge-info ml-1">Pair</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['product_type'] == 'pair' ? 'info' : 'secondary' ?>">
                                    <?= ucfirst($p['product_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                            <td class="text-right">
                                <strong class="<?= $p['is_low_stock'] ? 'text-danger' : '' ?>">
                                    <?= number_format($p['current_stock'], 3) ?>
                                </strong>
                                <?php if ($p['product_type'] == 'pair' && isset($p['pair_available'])): ?>
                                    <div class="text-xs text-muted">
                                        Pair avail: <?= number_format($p['pair_available'], 3) ?>
                                    </div>
                                    <?php if (isset($p['components'])): ?>
                                        <div class="text-xs text-muted">
                                            <?= htmlspecialchars($p['components']['component_a_code']) ?>: 
                                            <?= number_format($p['components']['component_a_stock'], 3) ?> + 
                                            <?= htmlspecialchars($p['components']['component_b_code']) ?>: 
                                            <?= number_format($p['components']['component_b_stock'], 3) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= number_format($p['min_stock'], 3) ?></td>
                            <td class="text-right"><?= formatCurrency($p['purchase_price']) ?></td>
                            <td class="text-right"><?= formatCurrency($p['sale_price']) ?></td>
                            <td>
                                <?php if ($p['is_low_stock']): ?>
                                    <span class="badge badge-danger">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?module=products&action=form&id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <a href="?module=inventory&action=adjustForm&id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-primary" title="Adjust">
                                        <i class="icon-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Navigation -->
<div class="mt-4 flex gap-2">
    <a href="?module=inventory&action=movements" class="btn btn-outline-primary">
        <i class="icon-list"></i> View Stock Movements
    </a>
    <a href="?module=products&action=form&type=pair" class="btn btn-outline-info">
        <i class="icon-plus"></i> Create Pair Product
    </a>
</div>

<style>
.bg-warning-light { background-color: #fffbeb; }
.text-xs { font-size: 0.75rem; }
</style>

<?php layoutFooter(); ?>
