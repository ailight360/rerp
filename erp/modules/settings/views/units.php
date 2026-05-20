<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Units of Measurement</h1>
    <button onclick="showUnitModal()" class="btn btn-primary">+ Add Unit</button>
</div>

<div class="card">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Name</th>
                <th>Short Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($units as $unit): ?>
                <tr>
                    <td><?= htmlspecialchars($unit['name']) ?></td>
                    <td><code><?= htmlspecialchars($unit['short_name']) ?></code></td>
                    <td>
                        <button onclick='editUnit(<?= json_encode($unit) ?>)' class="btn btn-sm btn-outline">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this unit?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $unit['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="unitModal" class="modal" style="display:none;">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Unit</h3>
                <button onclick="closeUnitModal()" class="btn-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="unit_action" value="create">
                <input type="hidden" name="id" id="unit_id">
                
                <div class="form-group">
                    <label>Unit Name *</label>
                    <input type="text" name="name" id="unit_name" class="form-control" required placeholder="e.g., Kilogram">
                </div>
                
                <div class="form-group">
                    <label>Short Name *</label>
                    <input type="text" name="short_name" id="unit_short_name" class="form-control" required placeholder="e.g., kg" maxlength="20">
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeUnitModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showUnitModal() {
    document.getElementById('unitModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Add Unit';
    document.getElementById('unit_action').value = 'create';
    document.getElementById('unit_id').value = '';
    document.getElementById('unit_name').value = '';
    document.getElementById('unit_short_name').value = '';
}

function closeUnitModal() {
    document.getElementById('unitModal').style.display = 'none';
}

function editUnit(unit) {
    document.getElementById('unitModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Edit Unit';
    document.getElementById('unit_action').value = 'update';
    document.getElementById('unit_id').value = unit.id;
    document.getElementById('unit_name').value = unit.name;
    document.getElementById('unit_short_name').value = unit.short_name;
}

document.querySelector('.modal-backdrop')?.addEventListener('click', closeUnitModal);
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
