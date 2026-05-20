<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Stock Report</h1>
    <button onclick="window.print()" class="btn btn-secondary">Print</button>
    <a href="?action=stock&export=csv&<?= http_build_query($_GET) ?>" class="btn btn-success">Export CSV</a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <form method="GET" class="filter-form">
        <input type="hidden" name="action" value="stock">
        <div class="row">
            <div class="col-md-4">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label>&nbsp;</label>
                <div class="checkbox-wrapper mt-2">
                    <label>
                        <input type="checkbox" name="show_low_stock" value="1" <?= $show_low_stock ? 'checked' : '' ?>>
                        Show Low Stock Only
                    </label>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Total Products</div>
            <div class="stat-value"><?= count($products) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success">
            <div class="stat-label">Total Stock Value</div>
            <div class="stat-value"><?= Helpers::formatCurrency(array_sum(array_column($products, 'stock_value'))) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['is_low_stock'])) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Pair Products</div>
            <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['product_type'] === 'pair')) ?></div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Unit</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Pair Available</th>
                    <th class="text-right">Purchase Price</th>
                    <th class="text-right">Sale Price</th>
                    <th class="text-right">Stock Value</th>
                    <th class="text-right">Min Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No products found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="<?= $product['is_low_stock'] ? 'table-warning' : '' ?>">
                            <td><code><?= htmlspecialchars($product['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                <?php if ($product['product_type'] === 'pair'): ?>
                                    <br>
                                    <small class="text-muted">
                                        1× <?= htmlspecialchars($product['component_a_name'] ?? 'N/A') ?> + 
                                        1× <?= htmlspecialchars($product['component_b_name'] ?? 'N/A') ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= $product['product_type'] === 'pair' ? 'info' : 'secondary' ?>">
                                    <?= ucfirst($product['product_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($product['unit_name'] ?? 'N/A') ?></td>
                            <td class="text-right">
                                <strong><?= number_format($product['current_stock'], 2) ?></strong>
                                <?php if ($product['is_low_stock']): ?>
                                    <span class="badge badge-danger ml-1">Low</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($product['product_type'] === 'pair'): ?>
                                    <strong><?= number_format($product['pair_available'] ?? 0, 2) ?></strong>
                                    <span class="badge badge-info">pairs</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= Helpers::formatCurrency($product['purchase_price']) ?></td>
                            <td class="text-right"><?= Helpers::formatCurrency($product['sale_price']) ?></td>
                            <td class="text-right"><?= Helpers::formatCurrency($product['stock_value']) ?></td>
                            <td class="text-right"><?= number_format($product['min_stock'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .filter-form, .btn { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
