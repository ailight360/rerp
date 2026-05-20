<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="module-header">
    <div>
        <h1>Vendor Ledger</h1>
        <p class="text-muted"><?= htmlspecialchars($vendor->name) ?></p>
    </div>
    <a href="/vendors" class="btn btn-secondary">← Back</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <label class="mr-2">Filter:</label>
            <select name="filter" class="form-control mr-2" onchange="this.form.submit()">
                <option value="all" <?= ($filter ?? 'all') === 'all' ? 'selected' : '' ?>>All Transactions</option>
                <option value="dated" <?= ($filter ?? 'all') === 'dated' ? 'selected' : '' ?>>Date Range</option>
            </select>
            
            <?php if (($filter ?? 'all') === 'dated'): ?>
            <input type="date" name="start_date" class="form-control mr-2" value="<?= htmlspecialchars($startDate) ?>">
            <input type="date" name="end_date" class="form-control mr-2" value="<?= htmlspecialchars($endDate) ?>">
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <label>Opening Balance</label>
                <div class="stat-value"><?= Helpers::formatCurrency(abs($vendor->opening_balance)) ?></div>
                <small class="text-muted">(<?= $vendor->balance_type ?>)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <label>Total Debit</label>
                <?php
                $totalDebit = array_sum(array_column($entries, 'debit'));
                ?>
                <div class="stat-value text-info"><?= Helpers::formatCurrency($totalDebit) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <label>Total Credit</label>
                <?php
                $totalCredit = array_sum(array_column($entries, 'credit'));
                ?>
                <div class="stat-value text-success"><?= Helpers::formatCurrency($totalCredit) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <label>Current Balance</label>
                <?php
                $currentBalance = $vendor->opening_balance + ($totalDebit - $totalCredit);
                if ($vendor->balance_type === 'credit') {
                    $currentBalance = -$currentBalance;
                }
                ?>
                <div class="stat-value <?= $currentBalance > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= Helpers::formatCurrency(abs($currentBalance)) ?>
                </div>
                <small class="text-muted"><?= $currentBalance > 0 ? 'Payable' : 'Advance' ?></small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4>Transaction History</h4>
        <button onclick="window.print()" class="btn btn-sm btn-outline-primary">📄 Print</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Show opening balance first
                if (count($entries) > 0 || $vendor->opening_balance != 0):
                ?>
                <tr class="table-light">
                    <td><?= date('Y-m-d', strtotime($vendor->created_at)) ?></td>
                    <td><strong>Opening Balance</strong></td>
                    <td>-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">
                        <strong><?= Helpers::formatCurrency(abs($vendor->opening_balance)) ?></strong>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= date('Y-m-d', strtotime($entry->date)) ?></td>
                    <td><?= htmlspecialchars($entry->description) ?></td>
                    <td>
                        <?php if ($entry->reference_type && $entry->reference_id): ?>
                            <a href="/<?= str_replace('_', '/', $entry->reference_type) ?>/view/<?= $entry->reference_id ?>" target="_blank">
                                #<?= $entry->reference_id ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= $entry->debit > 0 ? Helpers::formatCurrency($entry->debit) : '-' ?></td>
                    <td class="text-right"><?= $entry->credit > 0 ? Helpers::formatCurrency($entry->credit) : '-' ?></td>
                    <td class="text-right">
                        <strong><?= Helpers::formatCurrency(abs($entry->running_balance)) ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (count($entries) === 0 && $vendor->opening_balance == 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No transactions found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .module-header, .card-header button, .form-inline { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .stat-card { break-inside: avoid; }
}
</style>
