<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><i class="icon-receivable"></i> Customer Receivables</h1>
    <div class="flex gap-2">
        <a href="/due-collection?action=vendors" class="btn btn-outline">Vendor Payables</a>
        <a href="/due-collection?action=aging" class="btn btn-outline">Aging Analysis</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Total Due</th>
                    <th>Current (0-30)</th>
                    <th>31-60 Days</th>
                    <th>61-90 Days</th>
                    <th>90+ Days</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No outstanding receivables</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                        <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                        <td class="text-danger"><strong><?= Helpers::formatCurrency($c['total_due']) ?></strong></td>
                        <td><?= Helpers::formatCurrency($c['current_30']) ?></td>
                        <td><?= Helpers::formatCurrency($c['days_31_60']) ?></td>
                        <td><?= Helpers::formatCurrency($c['days_61_90']) ?></td>
                        <td class="text-danger"><?= Helpers::formatCurrency($c['days_90_plus']) ?></td>
                        <td>
                            <a href="/customers?action=ledger&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Ledger</a>
                            <a href="/payments?action=form&type=received&party_type=customer&party_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Receive Payment</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <th colspan="2">Total</th>
                    <th class="text-danger"><?= Helpers::formatCurrency(array_sum(array_column($customers, 'total_due'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($customers, 'current_30'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($customers, 'days_31_60'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($customers, 'days_61_90'))) ?></th>
                    <th class="text-danger"><?= Helpers::formatCurrency(array_sum(array_column($customers, 'days_90_plus'))) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
