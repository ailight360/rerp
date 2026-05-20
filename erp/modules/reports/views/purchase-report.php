<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Purchase Report</h1>
    <button onclick="window.print()" class="btn btn-secondary">Print</button>
    <a href="?action=purchases&export=csv&<?= http_build_query($_GET) ?>" class="btn btn-success">Export CSV</a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <form method="GET" class="filter-form">
        <input type="hidden" name="action" value="purchases">
        <div class="row">
            <div class="col-md-3">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label>Vendor</label>
                <select name="vendor_id" class="form-control">
                    <option value="">All Vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Total Purchases</div>
            <div class="stat-value"><?= Helpers::formatCurrency($grand_total) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success">
            <div class="stat-label">Total Paid</div>
            <div class="stat-value"><?= Helpers::formatCurrency($grand_paid) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning">
            <div class="stat-label">Total Due</div>
            <div class="stat-value"><?= Helpers::formatCurrency($grand_due) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Invoices</div>
            <div class="stat-value"><?= count($purchases) ?></div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice #</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Paid</th>
                    <th class="text-right">Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No purchases found for this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?= Helpers::formatDate($purchase['date']) ?></td>
                            <td><strong><?= htmlspecialchars($purchase['invoice_no']) ?></strong></td>
                            <td><?= htmlspecialchars($purchase['vendor_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= $purchase['status'] === 'confirmed' ? 'success' : 'primary' ?>">
                                    <?= ucfirst($purchase['status']) ?>
                                </span>
                            </td>
                            <td class="text-right"><?= Helpers::formatCurrency($purchase['subtotal']) ?></td>
                            <td class="text-right"><?= Helpers::formatCurrency($purchase['tax_amount']) ?></td>
                            <td class="text-right"><strong><?= Helpers::formatCurrency($purchase['total_amount']) ?></strong></td>
                            <td class="text-right text-success"><?= Helpers::formatCurrency($purchase['paid']) ?></td>
                            <td class="text-right text-warning"><?= Helpers::formatCurrency($purchase['due']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($purchases)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-right"><strong>Totals:</strong></td>
                        <td class="text-right"><strong><?= Helpers::formatCurrency($grand_total - array_sum(array_column($purchases, 'tax_amount'))) ?></strong></td>
                        <td class="text-right"><strong><?= Helpers::formatCurrency(array_sum(array_column($purchases, 'tax_amount'))) ?></strong></td>
                        <td class="text-right"><strong><?= Helpers::formatCurrency($grand_total) ?></strong></td>
                        <td class="text-right text-success"><strong><?= Helpers::formatCurrency($grand_paid) ?></strong></td>
                        <td class="text-right text-warning"><strong><?= Helpers::formatCurrency($grand_due) ?></strong></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<style>
@media print {
    .filter-form, .btn { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
