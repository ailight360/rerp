<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><?= $edit_mode ? 'Edit Payment' : 'New Payment' ?></h1>
    <a href="?" class="btn btn-light">Back to List</a>
</div>

<div class="card">
    <form method="POST" id="payment-form">
        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
        <input type="hidden" name="id" value="<?= $payment['id'] ?? '' ?>">
        
        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Payment Type *</label>
                <select name="type" class="form-control" required>
                    <option value="received" <?= ($payment['type'] ?? '') === 'received' ? 'selected' : '' ?>>Received (from Customer)</option>
                    <option value="paid" <?= ($payment['type'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid (to Vendor)</option>
                </select>
            </div>
            
            <div class="form-group col-md-3">
                <label>Party Type *</label>
                <select name="party_type" id="party_type" class="form-control" required onchange="loadParties()">
                    <option value="">Select...</option>
                    <option value="customer" <?= ($payment['party_type'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor" <?= ($payment['party_type'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                </select>
            </div>
            
            <div class="form-group col-md-3">
                <label>Select Party *</label>
                <select name="party_id" id="party_id" class="form-control" required onchange="loadInvoices()">
                    <option value="">Select <?= ($payment['party_type'] ?? '') === 'vendor' ? 'Vendor' : 'Customer' ?></option>
                    <?php 
                    $parties = ($payment['party_type'] ?? '') === 'vendor' ? $vendors : $customers;
                    foreach ($parties as $party): 
                    ?>
                    <option value="<?= $party['id'] ?>" <?= ($payment['party_id'] ?? 0) == $party['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($party['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group col-md-3">
                <label>Payment Method *</label>
                <select name="method" class="form-control" required>
                    <option value="cash" <?= ($payment['method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank" <?= ($payment['method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="mobile" <?= ($payment['method'] ?? '') === 'mobile' ? 'selected' : '' ?>>Mobile Banking</option>
                    <option value="check" <?= ($payment['method'] ?? '') === 'check' ? 'selected' : '' ?>>Check</option>
                    <option value="other" <?= ($payment['method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Amount *</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $payment['amount'] ?? '' ?>" required min="0.01">
            </div>
            
            <div class="form-group col-md-3">
                <label>Date *</label>
                <input type="date" name="date" class="form-control" value="<?= $payment['date'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group col-md-6">
                <label>Link to Invoice (Optional)</label>
                <select name="reference_type" id="reference_type" class="form-control" disabled>
                    <option value="manual">Manual Payment (No Invoice)</option>
                    <option value="stock_out" <?= ($payment['reference_type'] ?? '') === 'stock_out' ? 'selected' : '' ?>>Sale Invoice</option>
                    <option value="bill" <?= ($payment['reference_type'] ?? '') === 'bill' ? 'selected' : '' ?>>Tax Invoice (Bill)</option>
                    <option value="stock_in" <?= ($payment['reference_type'] ?? '') === 'stock_in' ? 'selected' : '' ?>>Purchase Invoice</option>
                </select>
                <select name="reference_id" id="reference_id" class="form-control mt-2" disabled>
                    <option value="">Select Invoice...</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($payment['notes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $edit_mode ? 'Update Payment' : 'Record Payment' ?></button>
            <a href="?" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>

<script>
const partyTypeSelect = document.getElementById('party_type');
const partyIdSelect = document.getElementById('party_id');
const referenceTypeSelect = document.getElementById('reference_type');
const referenceIdSelect = document.getElementById('reference_id');

function loadInvoices() {
    const partyType = partyTypeSelect.value;
    const partyId = partyIdSelect.value;
    
    if (!partyType || !partyId) {
        referenceTypeSelect.disabled = true;
        referenceIdSelect.disabled = true;
        return;
    }
    
    referenceTypeSelect.disabled = false;
    
    fetch(`/api/payments.php?action=outstanding&party_type=${partyType}&party_id=${partyId}`)
        .then(r => r.json())
        .then(data => {
            referenceIdSelect.innerHTML = '<option value="">Select Invoice...</option>';
            if (data.invoices) {
                data.invoices.forEach(inv => {
                    const opt = document.createElement('option');
                    opt.value = inv.id;
                    opt.textContent = `${inv.ref_no} - ${inv.description || ''} (Due: ${inv.due})`;
                    opt.dataset.total = inv.total;
                    referenceIdSelect.appendChild(opt);
                });
            }
            referenceIdSelect.disabled = false;
        });
}

// Auto-set reference type based on party type
partyTypeSelect.addEventListener('change', function() {
    const partyType = this.value;
    referenceTypeSelect.innerHTML = '<option value="manual">Manual Payment (No Invoice)</option>';
    
    if (partyType === 'customer') {
        referenceTypeSelect.innerHTML += '<option value="stock_out">Sale Invoice</option><option value="bill">Tax Invoice (Bill)</option>';
    } else if (partyType === 'vendor') {
        referenceTypeSelect.innerHTML += '<option value="stock_in">Purchase Invoice</option>';
    }
});
</script>
