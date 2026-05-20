<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Profit & Loss (Gross Margin)</h1>
    <button onclick="window.print()" class="btn btn-secondary">Print</button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <form method="GET" class="filter-form">
        <input type="hidden" name="action" value="profit_loss">
        <div class="row">
            <div class="col-md-4">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
            </div>
            <div class="col-md-4">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
            </div>
        </div>
    </form>
</div>

<!-- P&L Summary -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <h3 class="card-title">Revenue</h3>
            <div class="pl-summary">
                <div class="pl-row">
                    <span>Total Sales Revenue</span>
                    <span class="pl-value"><?= Helpers::formatCurrency($revenue['total_revenue'] ?? 0) ?></span>
                </div>
                <div class="pl-row">
                    <span>Number of Sales</span>
                    <span class="pl-value"><?= $revenue['sale_count'] ?? 0 ?></span>
                </div>
                <div class="pl-row total">
                    <span><strong>Total Revenue</strong></span>
                    <span class="pl-value"><strong><?= Helpers::formatCurrency($revenue['total_revenue'] ?? 0) ?></strong></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <h3 class="card-title">Cost of Goods Sold (COGS)</h3>
            <div class="pl-summary">
                <div class="pl-row">
                    <span>Purchase Cost of Sold Items</span>
                    <span class="pl-value"><?= Helpers::formatCurrency($cogs['total_cogs'] ?? 0) ?></span>
                </div>
                <div class="pl-row total">
                    <span><strong>Total COGS</strong></span>
                    <span class="pl-value"><strong><?= Helpers::formatCurrency($cogs['total_cogs'] ?? 0) ?></strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gross Margin -->
<div class="card mt-4 <?= $gross_profit >= 0 ? 'success-border' : 'danger-border' ?>">
    <h3 class="card-title">Gross Profit</h3>
    <div class="pl-summary large">
        <div class="pl-row">
            <span>Total Revenue</span>
            <span class="pl-value"><?= Helpers::formatCurrency($revenue['total_revenue'] ?? 0) ?></span>
        </div>
        <div class="pl-row">
            <span>Less: COGS</span>
            <span class="pl-value text-danger">-<?= Helpers::formatCurrency($cogs['total_cogs'] ?? 0) ?></span>
        </div>
        <div class="pl-row grand-total">
            <span><strong>GROSS PROFIT</strong></span>
            <span class="pl-value <?= $gross_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                <strong><?= Helpers::formatCurrency($gross_profit) ?></strong>
            </span>
        </div>
        <div class="pl-row margin-percent">
            <span>Gross Margin %</span>
            <span class="pl-value badge badge-<?= $margin_percent >= 20 ? 'success' : ($margin_percent >= 10 ? 'warning' : 'danger') ?> p-2">
                <strong><?= number_format($margin_percent, 2) ?>%</strong>
            </span>
        </div>
    </div>
</div>

<!-- Info Box -->
<div class="alert alert-info mt-4">
    <strong>Note:</strong> This report shows Gross Margin only (Revenue - COGS). 
    Operating expenses, taxes, and other costs are not included in this lightweight version.
    For a full P&L, integrate with an expense tracking module.
</div>

<style>
.pl-summary {
    padding: 1rem;
}
.pl-summary.large {
    padding: 2rem;
    background: #f8fafc;
    border-radius: 8px;
}
.pl-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e2e8f0;
}
.pl-row:last-child {
    border-bottom: none;
}
.pl-row.total {
    background: #f1f5f9;
    padding: 1rem;
    margin-top: 0.5rem;
    border-radius: 4px;
    font-size: 1.1rem;
}
.pl-row.grand-total {
    font-size: 1.5rem;
    padding: 1.5rem 0;
    border-bottom: 2px solid #3b82f6;
}
.pl-row.margin-percent {
    justify-content: center;
    padding-top: 1.5rem;
}
.pl-value {
    font-weight: 600;
}
.success-border {
    border-left: 4px solid #10b981;
}
.danger-border {
    border-left: 4px solid #ef4444;
}
@media print {
    .filter-form, .btn, .alert { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
