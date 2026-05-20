<?php include __DIR__ . '/../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1>Stock In Returns</h1>
        <p class="text-muted">Vendor returns and purchase reversals</p>
    </div>
    <div class="flex gap-2">
        <a href="/stock_in/create-return" class="btn btn-primary">
            <svg class="icon"><use href="#icon-plus"></use></svg>
            New Return
        </a>
        <a href="/stock_in" class="btn btn-outline">Back to Stock In</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Return No</th>
                    <th>Date</th>
                    <th>Original Invoice</th>
                    <th>Vendor</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No returns found</td></tr>
                <?php else: ?>
                    <?php foreach ($returns as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r->return_no) ?></td>
                            <td><?= Helpers::formatDate($r->date) ?></td>
                            <td><?= htmlspecialchars($r->original_invoice) ?></td>
                            <td><?= $r->vendor_name ? htmlspecialchars($r->vendor_name) : '-' ?></td>
                            <td><a href="/stock_in/return-view/<?= $r->id ?>" class="btn btn-sm btn-outline">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
