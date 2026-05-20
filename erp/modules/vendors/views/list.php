<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="module-header">
    <div>
        <h1>Vendors</h1>
        <p class="text-muted">Manage your suppliers and track payables</p>
    </div>
    <a href="/vendors/create" class="btn btn-primary">+ Add Vendor</a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Vendor created successfully!</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <input type="text" id="search-input" class="form-control" placeholder="Search vendors..." style="max-width: 300px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="vendors-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Opening Balance</th>
                    <th>Current Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor): ?>
                <tr data-name="<?= strtolower(htmlspecialchars($vendor->name)) ?>">
                    <td>
                        <strong><?= htmlspecialchars($vendor->name) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($vendor->phone ?? '-') ?></td>
                    <td><?= htmlspecialchars($vendor->email ?? '-') ?></td>
                    <td>
                        <span class="badge <?= $vendor->opening_balance >= 0 ? 'badge-info' : 'badge-warning' ?>">
                            <?= Helpers::formatCurrency($vendor->opening_balance) ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $balanceClass = $vendor->current_balance > 0 ? 'badge-danger' : ($vendor->current_balance < 0 ? 'badge-success' : 'badge-secondary');
                        $balanceText = $vendor->current_balance > 0 ? 'Payable' : ($vendor->current_balance < 0 ? 'Advance' : 'Clear');
                        ?>
                        <span class="badge <?= $balanceClass ?>">
                            <?= Helpers::formatCurrency(abs($vendor->current_balance)) ?>
                            <small>(<?= $balanceText ?>)</small>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/vendors/ledger/<?= $vendor->id ?>" class="btn btn-outline-primary" title="Ledger">
                                📖
                            </a>
                            <a href="/vendors/statement/<?= $vendor->id ?>" class="btn btn-outline-info" title="Statement" target="_blank">
                                📄
                            </a>
                            <a href="/vendors/edit/<?= $vendor->id ?>" class="btn btn-outline-warning" title="Edit">
                                ✏️
                            </a>
                            <button onclick="deleteVendor(<?= $vendor->id ?>, '<?= htmlspecialchars($vendor->name) ?>')" 
                                    class="btn btn-outline-danger" title="Deactivate">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search functionality
document.getElementById('search-input').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('#vendors-table tbody tr').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(term) ? '' : 'none';
    });
});

function deleteVendor(id, name) {
    if (!confirm(`Deactivate vendor "${name}"? This cannot be undone.`)) return;
    
    fetch(`/vendors/delete/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Vendor deactivated', 'success');
            location.reload();
        } else {
            showToast(data.message, 'danger');
        }
    });
}
</script>

<style>
@media print {
    .module-header, .card-header, .btn-group { display: none !important; }
    .card { box-shadow: none !important; border: none !important; }
}
</style>
