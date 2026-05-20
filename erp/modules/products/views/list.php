<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <div class="page-header">
        <h2>📦 Products</h2>
        <div class="page-actions">
            <a href="?action=create" class="btn btn-primary">+ New Product</a>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by name or code..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="/products" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($products)): ?>
                <p class="text-muted text-center py-5">
                    No products found. <a href="?action=create">Create your first product</a>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Sale Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>#<?= $p['id'] ?></td>
                                    <td><code><?= htmlspecialchars($p['code']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                        <?php if ($p['product_type'] === 'pair'): ?>
                                            <span class="badge badge-info">Pair</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $p['product_type'] === 'pair' ? 'info' : 'secondary' ?>">
                                            <?= ucfirst($p['product_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php 
                                        $stock = floatval($p['available_stock'] ?? 0);
                                        $minStock = intval($p['min_stock'] ?? 0);
                                        $class = $stock <= $minStock ? 'badge-danger' : 'badge-success';
                                        ?>
                                        <span class="badge <?= $class ?>"><?= $stock ?> <?= htmlspecialchars($p['unit_name'] ?? '') ?></span>
                                        <?php if ($stock <= $minStock && $stock > 0): ?>
                                            <small class="text-warning">⚠️ Low</small>
                                        <?php elseif ($stock == 0): ?>
                                            <small class="text-danger">Out of stock</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::formatCurrency($p['sale_price']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                                ✏️
                                            </a>
                                            <a href="#" class="btn btn-sm btn-outline btn-delete" 
                                               data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" title="Delete">
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
            <p>Are you sure you want to delete product <strong id="deleteProductName"></strong>?</p>
            <p class="text-muted text-sm">This will set the status to inactive. Cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline modal-close">Cancel</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                <input type="hidden" name="id" id="deleteProductId">
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
        
        document.getElementById('deleteProductId').value = id;
        document.getElementById('deleteProductName').textContent = name;
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
