<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <div class="page-header">
        <h2><?= $action === 'create' ? '➕ New Customer' : '✏️ Edit Customer' ?></h2>
        <div class="page-actions">
            <a href="/customers" class="btn btn-outline">← Back to List</a>
        </div>
    </div>
    
    <form method="POST" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <?php if ($action === 'edit' && $customer): ?>
                <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                <input type="hidden" name="action" value="update">
            <?php else: ?>
                <input type="hidden" name="action" value="create">
            <?php endif; ?>
            
            <div class="grid-2">
                <div class="form-group">
                    <label for="name">Customer Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required 
                           value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
            </div>
            
            <?php if ($action === 'create'): ?>
            <div class="grid-2">
                <div class="form-group">
                    <label for="opening_balance">Opening Balance</label>
                    <input type="number" id="opening_balance" name="opening_balance" class="form-control" 
                           step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="balance_type">Balance Type</label>
                    <select id="balance_type" name="balance_type" class="form-control">
                        <option value="debit" <?= ($customer['balance_type'] ?? 'debit') === 'debit' ? 'selected' : '' ?>>Debit (Receivable)</option>
                        <option value="credit" <?= ($customer['balance_type'] ?? 'debit') === 'credit' ? 'selected' : '' ?>>Credit (Payable)</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <?= $action === 'create' ? 'Create Customer' : 'Update Customer' ?>
            </button>
            <a href="/customers" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
