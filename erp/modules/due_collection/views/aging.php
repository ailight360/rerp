<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><i class="icon-chart"></i> Aging Analysis</h1>
    <div class="flex gap-2">
        <a href="/due-collection?action=customers" class="btn btn-outline">Receivables</a>
        <a href="/due-collection?action=vendors" class="btn btn-outline">Payables</a>
    </div>
</div>

<div class="mb-3">
    <a href="?action=aging&type=customer" class="btn <?= $type === 'customer' ? 'btn-primary' : 'btn-outline' ?>">Customers</a>
    <a href="?action=aging&type=vendor" class="btn <?= $type === 'vendor' ? 'btn-primary' : 'btn-outline' ?>">Vendors</a>
</div>

<div class="card">
    <table class="table table-hover">
        <thead>
            <tr>
                <th><?= ucfirst($type) ?></th>
                <th>Current (0-30)</th>
                <th>31-60 Days</th>
                <th>61-90 Days</th>
                <th>91-120 Days</th>
                <th>120+ Days</th>
                <th>Total Due</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totals = ['bucket_current' => 0, 'bucket_30' => 0, 'bucket_60' => 0, 'bucket_90' => 0, 'bucket_120_plus' => 0, 'total_due' => 0];
            foreach ($aging as $row): 
                $totals['bucket_current'] += $row['bucket_current'];
                $totals['bucket_30'] += $row['bucket_30'];
                $totals['bucket_60'] += $row['bucket_60'];
                $totals['bucket_90'] += $row['bucket_90'];
                $totals['bucket_120_plus'] += $row['bucket_120_plus'];
                $totals['total_due'] += $row['total_due'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= Helpers::formatCurrency($row['bucket_current']) ?></td>
                <td><?= Helpers::formatCurrency($row['bucket_30']) ?></td>
                <td><?= Helpers::formatCurrency($row['bucket_60']) ?></td>
                <td><?= Helpers::formatCurrency($row['bucket_90']) ?></td>
                <td class="text-danger"><?= Helpers::formatCurrency($row['bucket_120_plus']) ?></td>
                <td><strong><?= Helpers::formatCurrency($row['total_due']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light">
            <tr>
                <th>Totals</th>
                <th><?= Helpers::formatCurrency($totals['bucket_current']) ?></th>
                <th><?= Helpers::formatCurrency($totals['bucket_30']) ?></th>
                <th><?= Helpers::formatCurrency($totals['bucket_60']) ?></th>
                <th><?= Helpers::formatCurrency($totals['bucket_90']) ?></th>
                <th class="text-danger"><?= Helpers::formatCurrency($totals['bucket_120_plus']) ?></th>
                <th class="text-danger"><?= Helpers::formatCurrency($totals['total_due']) ?></th>
            </tr>
        </tfoot>
    </table>
</div>
