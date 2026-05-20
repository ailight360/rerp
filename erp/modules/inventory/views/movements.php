<?php include __DIR__ . '/../../layout.php'; layoutHeader('Stock Movements'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-list"></i> Stock Movements Log</h1>
        <p class="text-muted">Complete audit trail of all stock changes</p>
    </div>
    <div class="flex gap-2">
        <a href="?module=inventory" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Summary
        </a>
        <a href="?module=inventory&action=adjustForm" class="btn btn-primary">
            <i class="icon-edit"></i> Adjust Stock
        </a>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card mb-4">
    <input type="hidden" name="module" value="inventory">
    <input type="hidden" name="action" value="movements">
    <div class="grid grid-4">
        <div>
            <label class="form-label">Product</label>
            <select name="product_id" class="form-control">
                <option value="">All Products</option>
                <?php foreach ($products as $prod): ?>
                    <option value="<?= $prod['id'] ?>" <?= $productId == $prod['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prod['code']) ?> - <?= htmlspecialchars($prod['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Movement Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="in" <?= $type == 'in' ? 'selected' : '' ?>>Stock In</option>
                <option value="out" <?= $type == 'out' ? 'selected' : '' ?>>Stock Out</option>
                <option value="adjustment" <?= $type == 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                <option value="return_in" <?= $type == 'return_in' ? 'selected' : '' ?>>Vendor Return</option>
                <option value="return_out" <?= $type == 'return_out' ? 'selected' : '' ?>>Customer Return</option>
                <option value="opening" <?= $type == 'opening' ? 'selected' : '' ?>>Opening Balance</option>
                <option value="pair_created" <?= $type == 'pair_created' ? 'selected' : '' ?>>Pair Created</option>
            </select>
        </div>
        <div>
            <label class="form-label">From Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div>
            <label class="form-label">To Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>
    </div>
    <div class="mt-3 flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="?module=inventory&action=movements" class="btn btn-secondary">Reset</a>
    </div>
</form>

<!-- Movements Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th class="text-right">Quantity</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No movements found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($m['product_code']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($m['product_name']) ?></small>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'secondary';
                                $typeLabel = ucfirst(str_replace('_', ' ', $m['type']));
                                switch($m['type']) {
                                    case 'in': $badgeClass = 'success'; break;
                                    case 'out': $badgeClass = 'primary'; break;
                                    case 'adjustment': $badgeClass = 'warning'; break;
                                    case 'return_in': $badgeClass = 'info'; break;
                                    case 'return_out': $badgeClass = 'info'; break;
                                    case 'opening': $badgeClass = 'secondary'; break;
                                    case 'pair_created': $badgeClass = 'purple'; break;
                                }
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>"><?= $typeLabel ?></span>
                            </td>
                            <td class="text-right">
                                <strong class="<?= $m['quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $m['quantity'] > 0 ? '+' : '' ?><?= number_format($m['quantity'], 3) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($m['reference_type'] && $m['reference_id']): ?>
                                    <?php
                                    $refUrl = '';
                                    switch($m['reference_type']) {
                                        case 'stock_in':
                                            $refUrl = "?module=stock_in&action=view&id={$m['reference_id']}";
                                            break;
                                        case 'stock_out':
                                            $refUrl = "?module=stock_out&action=view&id={$m['reference_id']}";
                                            break;
                                        case 'stock_in_return':
                                            $refUrl = "?module=stock_in&action=returnsList";
                                            break;
                                        case 'stock_out_return':
                                            $refUrl = "?module=stock_out&action=returnsList";
                                            break;
                                    }
                                    ?>
                                    <?php if ($refUrl): ?>
                                        <a href="<?= $refUrl ?>" class="text-primary">
                                            <?= ucfirst(str_replace('_', ' ', $m['reference_type'])) ?> #<?= $m['reference_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <?= ucfirst(str_replace('_', ' ', $m['reference_type'])) ?> #<?= $m['reference_id'] ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['notes'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($m['user_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted text-sm">
        Showing last <?= count($movements) ?> movements
    </div>
</div>

<style>
.badge-purple { background-color: #9333ea; color: white; }
</style>

<?php layoutFooter(); ?>
