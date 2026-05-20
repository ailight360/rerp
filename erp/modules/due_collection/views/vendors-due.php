<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><i class="icon-payable"></i> Vendor Payables</h1>
    <div class="flex gap-2">
        <a href="/due-collection?action=customers" class="btn btn-outline">Customer Receivables</a>
        <a href="/due-collection?action=aging&type=vendor" class="btn btn-outline">Aging Analysis</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Vendor</th>
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
                <?php if (empty($vendors)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No outstanding payables</td></tr>
                <?php else: ?>
                    <?php foreach ($vendors as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                        <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                        <td class="text-danger"><strong><?= Helpers::formatCurrency($v['total_due']) ?></strong></td>
                        <td><?= Helpers::formatCurrency($v['current_30']) ?></td>
                        <td><?= Helpers::formatCurrency($v['days_31_60']) ?></td>
                        <td><?= Helpers::formatCurrency($v['days_61_90']) ?></td>
                        <td class="text-danger"><?= Helpers::formatCurrency($v['days_90_plus']) ?></td>
                        <td>
                            <a href="/vendors?action=ledger&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline">Ledger</a>
                            <a href="/payments?action=form&type=paid&party_type=vendor&party_id=<?= $v['id'] ?>" class="btn btn-sm btn-primary">Make Payment</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <th colspan="2">Total</th>
                    <th class="text-danger"><?= Helpers::formatCurrency(array_sum(array_column($vendors, 'total_due'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($vendors, 'current_30'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($vendors, 'days_31_60'))) ?></th>
                    <th><?= Helpers::formatCurrency(array_sum(array_column($vendors, 'days_61_90'))) ?></th>
                    <th class="text-danger"><?= Helpers::formatCurrency(array_sum(array_column($vendors, 'days_90_plus'))) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
