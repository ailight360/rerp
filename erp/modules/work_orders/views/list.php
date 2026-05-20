<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1><i class="icon-work"></i> Work Orders</h1>
        <p class="text-muted">Manage work orders with Kanban board and task tracking</p>
    </div>
    <div class="flex gap-2">
        <a href="/work-orders?action=kanban" class="btn btn-outline">
            <i class="icon-board"></i> Kanban Board
        </a>
        <a href="/work-orders?action=form" class="btn btn-primary">
            <i class="icon-plus"></i> New Work Order
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <form method="GET" class="row gap-3 align-items-end">
        <input type="hidden" name="action" value="index">
        
        <div class="col">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="pending" <?= ($status ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= ($status ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="on_hold" <?= ($status ?? '') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="completed" <?= ($status ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= ($status ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <div class="col">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <option value="">All Priorities</option>
                <option value="urgent" <?= ($priority ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                <option value="high" <?= ($priority ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= ($priority ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= ($priority ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
        </div>
        
        <div class="col">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-control">
                <option value="">All Customers</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($customer_id ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="/work-orders" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<!-- Work Orders List -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>WO #</th>
                    <th>Title</th>
                    <th>Customer</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Due Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workOrders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No work orders found. <a href="/work-orders?action=form">Create one?</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($workOrders as $wo): ?>
                        <tr>
                            <td>
                                <a href="/work-orders?action=view&id=<?= $wo['id'] ?>">
                                    <?= htmlspecialchars($wo['wo_no']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($wo['title']) ?></strong>
                                <?php if ($wo['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(substr($wo['description'], 0, 60)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($wo['customer_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= $wo['priority'] === 'urgent' ? 'danger' : ($wo['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($wo['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'pending' => 'secondary',
                                    'in_progress' => 'primary',
                                    'on_hold' => 'warning',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?= $statusBadges[$wo['status']] ?? 'secondary' ?>">
                                    <?= str_replace('_', ' ', ucfirst($wo['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($wo['assigned_to_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <?php if ($wo['due_date']): ?>
                                    <?= date('M d, Y', strtotime($wo['due_date'])) ?>
                                    <?php if ($wo['due_date'] < date('Y-m-d') && $wo['status'] !== 'completed'): ?>
                                        <span class="text-danger ml-1">(Overdue)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No due date</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/work-orders?action=view&id=<?= $wo['id'] ?>" class="btn btn-outline" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    <a href="/work-orders?action=form&id=<?= $wo['id'] ?>" class="btn btn-outline" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <button onclick="deleteWorkOrder(<?= $wo['id'] ?>, '<?= htmlspecialchars($wo['wo_no']) ?>')" 
                                            class="btn btn-outline text-danger" title="Delete">
                                        <i class="icon-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total > 20): ?>
        <div class="pagination mt-3">
            <?php
            $currentPage = $page;
            $totalPages = ceil($total / 20);
            
            if ($currentPage > 1):
            ?>
                <a href="?action=index&page=<?= $currentPage - 1 ?><?= $status ? '&status=' . $status : '' ?><?= $priority ? '&priority=' . $priority : '' ?><?= $customer_id ? '&customer_id=' . $customer_id : '' ?>" 
                   class="btn btn-outline btn-sm">Previous</a>
            <?php endif; ?>
            
            <span class="mx-2">Page <?= $currentPage ?> of <?= $totalPages ?></span>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?action=index&page=<?= $currentPage + 1 ?><?= $status ? '&status=' . $status : '' ?><?= $priority ? '&priority=' . $priority : '' ?><?= $customer_id ? '&customer_id=' . $customer_id : '' ?>" 
                   class="btn btn-outline btn-sm">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteWorkOrder(id, woNo) {
    if (!confirm(`Are you sure you want to delete work order ${woNo}? This will also delete all tasks and timeline entries.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    fetch(`/work-orders?action=delete&id=${id}`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Work order deleted', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to delete', 'error');
        }
    })
    .catch(err => {
        showToast('Failed to delete work order', 'error');
    });
}
</script>
