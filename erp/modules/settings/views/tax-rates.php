<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Tax Rates</h1>
    <button onclick="showTaxModal()" class="btn btn-primary">+ Add Tax Rate</button>
</div>

<div class="card">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Name</th>
                <th>Rate (%)</th>
                <th>Default</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tax_rates as $rate): ?>
                <tr>
                    <td><?= htmlspecialchars($rate['name']) ?></td>
                    <td><?= number_format($rate['rate'], 2) ?>%</td>
                    <td>
                        <?php if ($rate['is_default']): ?>
                            <span class="badge badge-success">Yes</span>
                        <?php else: ?>
                            <span class="text-muted">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $rate['status'] ? 'success' : 'secondary' ?>">
                            <?= $rate['status'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <button onclick='editTax(<?= json_encode($rate) ?>)' class="btn btn-sm btn-outline">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tax rate?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $rate['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="taxModal" class="modal" style="display:none;">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Tax Rate</h3>
                <button onclick="closeTaxModal()" class="btn-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="tax_action" value="create">
                <input type="hidden" name="id" id="tax_id">
                
                <div class="form-group">
                    <label>Tax Name *</label>
                    <input type="text" name="name" id="tax_name" class="form-control" required placeholder="e.g., VAT 15%">
                </div>
                
                <div class="form-group">
                    <label>Rate (%) *</label>
                    <input type="number" name="rate" id="tax_rate" step="0.01" class="form-control" required placeholder="15.00">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_default" id="tax_is_default" value="1">
                        Set as default tax rate
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeTaxModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTaxModal() {
    document.getElementById('taxModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Add Tax Rate';
    document.getElementById('tax_action').value = 'create';
    document.getElementById('tax_id').value = '';
    document.getElementById('tax_name').value = '';
    document.getElementById('tax_rate').value = '';
    document.getElementById('tax_is_default').checked = false;
}

function closeTaxModal() {
    document.getElementById('taxModal').style.display = 'none';
}

function editTax(tax) {
    document.getElementById('taxModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Edit Tax Rate';
    document.getElementById('tax_action').value = 'update';
    document.getElementById('tax_id').value = tax.id;
    document.getElementById('tax_name').value = tax.name;
    document.getElementById('tax_rate').value = tax.rate;
    document.getElementById('tax_is_default').checked = tax.is_default == 1;
}

// Close modal on backdrop click
document.querySelector('.modal-backdrop')?.addEventListener('click', closeTaxModal);
</script>

<style>
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; }
.modal-backdrop { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-dialog { position: relative; top: 50px; margin: 20px auto; max-width: 500px; background: white; border-radius: 8px; }
.modal-content { padding: 1.5rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }
</style>
