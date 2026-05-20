<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="module-header">
    <div>
        <h1><?= isset($vendor) ? 'Edit Vendor' : 'Add New Vendor' ?></h1>
        <p class="text-muted"><?= isset($vendor) ? 'Update vendor information' : 'Create a new supplier record' ?></p>
    </div>
    <a href="/vendors" class="btn btn-secondary">← Back to List</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Vendor Information</h4>
            </div>
            <div class="card-body">
                <form id="vendor-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::getCsrfToken() ?>">
                    
                    <div class="form-group">
                        <label for="name">Vendor Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= htmlspecialchars($vendor->name ?? '') ?>" required autofocus>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($vendor->phone ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($vendor->email ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($vendor->address ?? '') ?></textarea>
                    </div>
                    
                    <?php if (!isset($vendor)): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="opening_balance">Opening Balance</label>
                                <input type="number" step="0.01" id="opening_balance" name="opening_balance" 
                                       class="form-control" value="0">
                                <small class="text-muted">Initial amount owed to/from this vendor</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="balance_type">Balance Type</label>
                                <select id="balance_type" name="balance_type" class="form-control">
                                    <option value="debit">Debit (We owe them)</option>
                                    <option value="credit">Credit (They owe us)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="1" <?= ($vendor->status ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($vendor->status ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= isset($vendor) ? 'Update Vendor' : 'Create Vendor' ?>
                        </button>
                        <a href="/vendors" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if (isset($vendor)): ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Quick Stats</h4>
            </div>
            <div class="card-body">
                <div class="stat-item">
                    <label>Current Balance</label>
                    <?php 
                    $stmt = $GLOBALS['db']->prepare("
                        SELECT COALESCE(SUM(debit), 0) as total_debit, 
                               COALESCE(SUM(credit), 0) as total_credit 
                        FROM ledger_entries 
                        WHERE party_type = 'vendor' AND party_id = ?
                    ");
                    $stmt->execute([$vendor->id]);
                    $ledger = $stmt->fetch(PDO::FETCH_OBJ);
                    $currentBalance = $vendor->opening_balance + ($ledger->total_debit - $ledger->total_credit);
                    if ($vendor->balance_type === 'credit') {
                        $currentBalance = -$currentBalance;
                    }
                    ?>
                    <div class="stat-value <?= $currentBalance > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= Helpers::formatCurrency(abs($currentBalance)) ?>
                    </div>
                    <small class="text-muted"><?= $currentBalance > 0 ? 'Amount Payable' : 'Advance Paid' ?></small>
                </div>
                
                <hr>
                
                <a href="/vendors/ledger/<?= $vendor->id ?>" class="btn btn-outline-primary btn-block">
                    📖 View Ledger
                </a>
                <a href="/vendors/statement/<?= $vendor->id ?>" class="btn btn-outline-info btn-block" target="_blank">
                    📄 Print Statement
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('vendor-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.href = '/vendors', 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(err => {
        showToast('An error occurred', 'danger');
    });
});
</script>
