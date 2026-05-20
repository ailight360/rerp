<?php include __DIR__ . '/../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1>Stock Out</h1>
        <p class="text-muted">Manage sales and delivery invoices</p>
    </div>
    <div class="flex gap-2">
        <a href="/stock_out/returns" class="btn btn-outline">
            <svg class="icon"><use href="#icon-return"></use></svg>
            Returns
        </a>
        <a href="/stock_out/create" class="btn btn-primary">
            <svg class="icon"><use href="#icon-plus"></use></svg>
            New Stock Out
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="flex justify-between items-center">
            <h3>All Stock Out Records</h3>
            <input type="text" id="table-search" class="input" placeholder="Search invoice, customer..." style="max-width: 300px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="stock-out-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockOuts)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No stock out records found. <a href="/stock_out/create">Create your first one</a>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($stockOuts as $so): ?>
                            <tr>
                                <td><a href="/stock_out/<?= $so->id ?>" class="font-medium"><?= htmlspecialchars($so->invoice_no) ?></a></td>
                                <td><?= Helpers::formatDate($so->date) ?></td>
                                <td><?= $so->customer_name ? htmlspecialchars($so->customer_name) : '<span class="text-muted">-</span>' ?></td>
                                <td><span class="badge badge-<?= $so->type === 'sale' ? 'primary' : 'info' ?>"><?= ucfirst($so->type) ?></span></td>
                                <td><span class="badge badge-info"><?= $so->item_count ?> items</span></td>
                                <td><?php if ($so->total !== null): ?><?= Helpers::formatCurrency($so->total) ?><?php else: ?><span class="text-muted">Delivery only</span><?php endif; ?></td>
                                <td><span class="badge badge-<?= $so->status === 'confirmed' || $so->status === 'delivered' ? 'success' : 'warning' ?>"><?= ucfirst($so->status) ?></span></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <a href="/stock_out/<?= $so->id ?>" class="btn btn-sm btn-outline" title="View"><svg class="icon"><use href="#icon-eye"></use></svg></a>
                                        <?php if (!$so->is_locked && $so->status !== 'cancelled'): ?>
                                            <button onclick="deleteStockOut(<?= $so->id ?>)" class="btn btn-sm btn-outline text-danger" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
                                        <?php endif; ?>
                                        <a href="/stock_out/print/<?= $so->id ?>" target="_blank" class="btn btn-sm btn-outline" title="Print"><svg class="icon"><use href="#icon-printer"></use></svg></a>
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
initDataTable('stock-out-table', 'table-search');
function deleteStockOut(id) {
    if (!confirm('Are you sure you want to delete this stock out record?')) return;
    fetch(`/stock_out/delete/${id}`, { method: 'POST', headers: { 'X-CSRF-Token': getCsrfToken() } })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
        else showToast(data.message, 'error');
    });
}
</script>
