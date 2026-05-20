<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><?= $workOrder ? 'Edit Work Order' : 'New Work Order' ?></h1>
    <a href="/work-orders" class="btn btn-outline">Back to List</a>
</div>

<form method="POST" action="/work-orders?action=save" class="card">
    <?= Helpers::csrfField() ?>
    <input type="hidden" name="id" value="<?= $workOrder['id'] ?? '' ?>">
    <input type="hidden" name="old_status" value="<?= $workOrder['status'] ?? '' ?>">
    
    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required 
                       value="<?= htmlspecialchars($workOrder['title'] ?? '') ?>" 
                       placeholder="Brief title for the work order">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" 
                          placeholder="Detailed description of the work"><?= htmlspecialchars($workOrder['description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" 
                          placeholder="Internal notes"><?= htmlspecialchars($workOrder['notes'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">WO Number</label>
                <input type="text" name="wo_no" class="form-control" 
                       value="<?= htmlspecialchars($workOrder['wo_no'] ?? '') ?>" 
                       placeholder="Auto-generated if empty">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Customer *</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($workOrder['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="low" <?= ($workOrder['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= ($workOrder['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= ($workOrder['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= ($workOrder['priority'] ?? 'medium') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="pending" <?= ($workOrder['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= ($workOrder['status'] ?? 'pending') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="on_hold" <?= ($workOrder['status'] ?? 'pending') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="completed" <?= ($workOrder['status'] ?? 'pending') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= ($workOrder['status'] ?? 'pending') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Assigned To</label>
                <select name="assigned_to" class="form-control">
                    <option value="0">Unassigned</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($workOrder['assigned_to'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $workOrder['start_date'] ?? date('Y-m-d') ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control" 
                       value="<?= $workOrder['due_date'] ?? '' ?>">
            </div>
        </div>
    </div>
    
    <div class="border-top pt-3 mt-3">
        <button type="submit" class="btn btn-primary">Save Work Order</button>
        <a href="/work-orders" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php if ($workOrder && !empty($tasks)): ?>
<div class="card mt-4">
    <div class="flex justify-between align-items-center mb-3">
        <h3>Tasks</h3>
        <button onclick="toggleTaskForm()" class="btn btn-sm btn-primary">Add Task</button>
    </div>
    
    <!-- Add Task Form -->
    <div id="task-form" class="mb-3" style="display:none;">
        <form method="POST" action="/work-orders?action=add-task" class="card card-body bg-light">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" name="task_name" class="form-control" placeholder="Task name" required>
                </div>
                <div class="col-md-3">
                    <select name="assigned_to" class="form-control">
                        <option value="0">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="due_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Add</button>
                </div>
            </div>
        </form>
    </div>
    
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Task</th>
                <th>Assigned To</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars($task['task_name']) ?></td>
                    <td><?= htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned') ?></td>
                    <td><?= $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '-' ?></td>
                    <td>
                        <select onchange="updateTaskStatus(<?= $task['id'] ?>, this.value)" class="form-control form-control-sm" style="width:auto;display:inline;">
                            <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </td>
                    <td>-</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
function toggleTaskForm() {
    document.getElementById('task-form').style.display = 
        document.getElementById('task-form').style.display === 'none' ? 'block' : 'none';
}

function updateTaskStatus(taskId, status) {
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    formData.append('task_id', taskId);
    formData.append('status', status);
    
    fetch('/work-orders?action=update-task', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Task status updated', 'success');
        } else {
            showToast('Failed to update task', 'error');
        }
    });
}
</script>
