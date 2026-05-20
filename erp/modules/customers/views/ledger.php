<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <div class="page-header">
        <h2>📋 Customer Ledger - <?= htmlspecialchars($customer['name']) ?></h2>
        <div class="page-actions">
            <a href="/customers" class="btn btn-outline">← Back to Customers</a>
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
        </div>
    </div>
    
    <!-- Customer Info Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="grid-3">
                <div>
                    <strong>Phone:</strong><br>
                    <?= htmlspecialchars($customer['phone'] ?: 'N/A') ?>
                </div>
                <div>
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($customer['email'] ?: 'N/A') ?>
                </div>
                <div>
                    <strong>Current Balance:</strong><br>
                    <span class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= Helpers::formatCurrency($balance) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ledger Entries Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($entries)): ?>
                <p class="text-muted text-center py-5">No ledger entries found.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $runningBalance = 0;
                        foreach ($entries as $entry): 
                            if ($entry['debit'] > 0) {
                                $runningBalance += floatval($entry['debit']);
                            } else {
                                $runningBalance -= floatval($entry['credit']);
                            }
                        ?>
                            <tr>
                                <td><?= Helpers::formatDate($entry['date']) ?></td>
                                <td><?= htmlspecialchars($entry['description']) ?></td>
                                <td>
                                    <?php if ($entry['reference_type']): ?>
                                        <small class="text-muted"><?= $entry['reference_type'] ?> #<?= $entry['reference_id'] ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td class="<?= $entry['debit'] > 0 ? 'text-danger' : '' ?>">
                                    <?= $entry['debit'] > 0 ? Helpers::formatCurrency($entry['debit']) : '-' ?>
                                </td>
                                <td class="<?= $entry['credit'] > 0 ? 'text-success' : '' ?>">
                                    <?= $entry['credit'] > 0 ? Helpers::formatCurrency($entry['credit']) : '-' ?>
                                </td>
                                <td class="font-weight-bold <?= $runningBalance > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= Helpers::formatCurrency($runningBalance) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <td colspan="5" class="text-right"><strong>Closing Balance:</strong></td>
                            <td class="font-weight-bold <?= $runningBalance > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= Helpers::formatCurrency($runningBalance) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .header, .page-actions, .mobile-bottom-nav { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
