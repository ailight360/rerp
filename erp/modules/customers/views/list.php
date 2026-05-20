<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <h2>👥 Customers</h2>
        <div class="page-actions">
            <a href="?action=create" class="btn btn-primary">+ New Customer</a>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by name, phone, or email..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="/customers" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Customers Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($customers)): ?>
                <p class="text-muted text-center py-5">
                    No customers found. <a href="?action=create">Create your first customer</a>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Balance</th>
                                <th>Due Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td>#<?= $c['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($c['phone']) ?></td>
                                    <td><?= htmlspecialchars($c['email']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $c['balance_type'] === 'debit' ? 'info' : 'warning' ?>">
                                            <?= ucfirst($c['balance_type']) ?>
                                        </span>
                                        <?= Helpers::formatCurrency($c['opening_balance']) ?>
                                    </td>
                                    <td>
                                        <?php if ($c['total_due'] > 0): ?>
                                            <span class="text-danger font-weight-bold">
                                                <?= Helpers::formatCurrency($c['total_due']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-success">✓ Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=ledger&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Ledger">
                                                📋
                                            </a>
                                            <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                                ✏️
                                            </a>
                                            <a href="#" class="btn btn-sm btn-outline btn-delete" 
                                               data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" title="Delete">
                                                🗑️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination mt-3">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                               class="btn btn-sm btn-outline">Previous</a>
                        <?php endif; ?>
                        
                        <span class="px-3">Page <?= $page ?> of <?= $totalPages ?></span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                               class="btn btn-sm btn-outline">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="btn-icon modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete customer <strong id="deleteCustomerName"></strong>?</p>
            <p class="text-muted text-sm">This will set the status to inactive. Cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline modal-close">Cancel</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                <input type="hidden" name="id" id="deleteCustomerId">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
// Delete confirmation
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        
        document.getElementById('deleteCustomerId').value = id;
        document.getElementById('deleteCustomerName').textContent = name;
        document.getElementById('deleteModal').classList.add('show');
    });
});

// Modal close handlers
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
    });
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });
});
</script>
