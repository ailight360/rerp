<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Ledger: <?= htmlspecialchars($party['name']) ?></h1>
    <button onclick="window.print()" class="btn btn-secondary">Print</button>
    <a href="?action=ledger" class="btn btn-outline">← Back to Selection</a>
</div>

<!-- Party Info -->
<div class="card mb-4">
    <div class="row">
        <div class="col-md-6">
            <h3><?= htmlspecialchars($party['name']) ?></h3>
            <p class="text-muted">
                <?php if ($party_type === 'customer'): ?>
                    Customer since <?= Helpers::formatDate($party['created_at']) ?>
                <?php else: ?>
                    Vendor since <?= Helpers::formatDate($party['created_at']) ?>
                <?php endif; ?>
            </p>
            <?php if ($party['phone']): ?>
                <p><strong>Phone:</strong> <?= htmlspecialchars($party['phone']) ?></p>
            <?php endif; ?>
            <?php if ($party['email']): ?>
                <p><strong>Email:</strong> <?= htmlspecialchars($party['email']) ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-right">
            <div class="stat-card inline">
                <div class="stat-label">Opening Balance</div>
                <div class="stat-value <?= $party['balance_type'] === 'debit' ? 'text-success' : 'text-danger' ?>">
                    <?= $party['balance_type'] === 'debit' ? 'Dr.' : 'Cr.' ?> 
                    <?= Helpers::formatCurrency(abs($party['opening_balance'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <form method="GET" class="filter-form">
        <input type="hidden" name="action" value="ledger">
        <input type="hidden" name="party_type" value="<?= $party_type ?>">
        <input type="hidden" name="party_id" value="<?= $party_id ?>">
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
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>
</div>

<!-- Ledger Entries -->
<div class="card">
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
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No ledger entries for this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= Helpers::formatDate($entry['date']) ?></td>
                            <td><?= htmlspecialchars($entry['description']) ?></td>
                            <td>
                                <?php if ($entry['reference_type'] && $entry['reference_id']): ?>
                                    <small class="text-muted">
                                        <?= ucfirst(str_replace('_', ' ', $entry['reference_type'])) ?> 
                                        #<?= $entry['reference_id'] ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right <?= $entry['debit'] > 0 ? 'text-success' : '' ?>">
                                <?= $entry['debit'] > 0 ? Helpers::formatCurrency($entry['debit']) : '-' ?>
                            </td>
                            <td class="text-right <?= $entry['credit'] > 0 ? 'text-danger' : '' ?>">
                                <?= $entry['credit'] > 0 ? Helpers::formatCurrency($entry['credit']) : '-' ?>
                            </td>
                            <td class="text-right">
                                <strong class="<?= $entry['running_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $entry['running_balance'] >= 0 ? 'Dr.' : 'Cr.' ?> 
                                    <?= Helpers::formatCurrency(abs($entry['running_balance'])) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($entries)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-right"><strong>Closing Balance:</strong></td>
                        <td class="text-right">
                            <strong class="<?= end($entries)['running_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= end($entries)['running_balance'] >= 0 ? 'Dr.' : 'Cr.' ?> 
                                <?= Helpers::formatCurrency(abs(end($entries)['running_balance'])) ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<style>
.stat-card.inline {
    display: inline-block;
    text-align: right;
}
@media print {
    .filter-form, .btn { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
