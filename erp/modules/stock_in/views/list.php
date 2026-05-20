<?php include __DIR__ . '/../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1>Stock In</h1>
        <p class="text-muted">Manage purchase receipts and delivery notes</p>
    </div>
    <div class="flex gap-2">
        <a href="/stock_in/returns" class="btn btn-outline">
            <svg class="icon"><use href="#icon-return"></use></svg>
            Returns
        </a>
        <a href="/stock_in/create" class="btn btn-primary">
            <svg class="icon"><use href="#icon-plus"></use></svg>
            New Stock In
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="flex justify-between items-center">
            <h3>All Stock In Records</h3>
            <input type="text" id="table-search" class="input" placeholder="Search invoice, vendor..." 
                   style="max-width: 300px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="stock-in-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Locked</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockIns)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                No stock in records found. <a href="/stock_in/create">Create your first one</a>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stockIns as $si): ?>
                            <tr>
                                <td>
                                    <a href="/stock_in/<?= $si->id ?>" class="font-medium">
                                        <?= htmlspecialchars($si->invoice_no) ?>
                                    </a>
                                </td>
                                <td><?= Helpers::formatDate($si->date) ?></td>
                                <td><?= $si->vendor_name ? htmlspecialchars($si->vendor_name) : '<span class="text-muted">-</span>' ?></td>
                                <td><span class="badge badge-info"><?= $si->item_count ?> items</span></td>
                                <td>
                                    <?php if ($si->total !== null): ?>
                                        <?= Helpers::formatCurrency($si->total) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Delivery only</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'draft' => 'badge-warning',
                                        'confirmed' => 'badge-success',
                                        'cancelled' => 'badge-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $statusColors[$si->status] ?? 'badge-secondary' ?>">
                                        <?= ucfirst($si->status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($si->is_locked): ?>
                                        <span class="badge badge-danger">Locked</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <a href="/stock_in/<?= $si->id ?>" class="btn btn-sm btn-outline" title="View">
                                            <svg class="icon"><use href="#icon-eye"></use></svg>
                                        </a>
                                        <?php if (!$si->is_locked && $si->status !== 'cancelled'): ?>
                                            <a href="/stock_in/edit/<?= $si->id ?>" class="btn btn-sm btn-outline" title="Edit">
                                                <svg class="icon"><use href="#icon-edit"></use></svg>
                                            </a>
                                            <button onclick="deleteStockIn(<?= $si->id ?>)" 
                                                    class="btn btn-sm btn-outline text-danger" title="Delete">
                                                <svg class="icon"><use href="#icon-trash"></use></svg>
                                            </button>
                                        <?php endif; ?>
                                        <a href="/stock_in/print/<?= $si->id ?>" target="_blank" 
                                           class="btn btn-sm btn-outline" title="Print">
                                            <svg class="icon"><use href="#icon-printer"></use></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/assets/js/datatable.js"></script>
<script>
// Initialize datatable
initDataTable('stock-in-table', 'table-search');

function deleteStockIn(id) {
    if (!confirm('Are you sure you want to delete this stock in record? This will reverse the stock movements.')) {
        return;
    }
    
    fetch(`/stock_in/delete/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Error deleting record', 'error');
        console.error(err);
    });
}
</script>
