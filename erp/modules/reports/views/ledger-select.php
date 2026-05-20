<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Ledger Report</h1>
    <button onclick="window.print()" class="btn btn-secondary">Print</button>
</div>

<!-- Party Selection -->
<div class="card mb-4">
    <form method="GET" class="filter-form">
        <input type="hidden" name="action" value="ledger">
        <div class="row">
            <div class="col-md-3">
                <label>Party Type</label>
                <select name="party_type" id="party_type" class="form-control" onchange="updatePartyList()">
                    <option value="customer" <?= $party_type === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor" <?= $party_type === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Select Party</label>
                <select name="party_id" id="party_id" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= $party['id'] ?>" <?= $party_id == $party['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($party['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">View Ledger</button>
            </div>
        </div>
    </form>
</div>

<script>
function updatePartyList() {
    // Reload page with selected party type to update party list
    const partyType = document.getElementById('party_type').value;
    window.location.href = `?action=ledger&party_type=${partyType}`;
}
</script>

<style>
@media print {
    .filter-form, .btn { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
