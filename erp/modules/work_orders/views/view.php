<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Work Order: <?= htmlspecialchars($workOrder['wo_no']) ?></h1>
    <div class="flex gap-2">
        <a href="/work-orders?action=form&id=<?= $workOrder['id'] ?>" class="btn btn-primary">Edit</a>
        <a href="/work-orders" class="btn btn-outline">Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <h3>Details</h3>
            <table class="table table-borderless">
                <tr><th width="30%">Title:</th><td><?= htmlspecialchars($workOrder['title']) ?></td></tr>
                <?php if ($workOrder['description']): ?>
                <tr><th>Description:</th><td><?= nl2br(htmlspecialchars($workOrder['description'])) ?></td></tr>
                <?php endif; ?>
                <?php if ($workOrder['notes']): ?>
                <tr><th>Notes:</th><td><?= nl2br(htmlspecialchars($workOrder['notes'])) ?></td></tr>
                <?php endif; ?>
                <tr><th>Customer:</th><td><?= htmlspecialchars($workOrder['customer_name'] ?? 'N/A') ?></td></tr>
                <?php if ($workOrder['customer_phone']): ?>
                <tr><th>Customer Phone:</th><td><?= htmlspecialchars($workOrder['customer_phone']) ?></td></tr>
                <?php endif; ?>
                <tr><th>Priority:</th><td><span class="badge badge-<?= $workOrder['priority'] === 'urgent' ? 'danger' : 'secondary' ?>"><?= ucfirst($workOrder['priority']) ?></span></td></tr>
                <tr><th>Status:</th><td><span class="badge badge-primary"><?= str_replace('_', ' ', ucfirst($workOrder['status'])) ?></span></td></tr>
                <tr><th>Assigned To:</th><td><?= htmlspecialchars($workOrder['assigned_to_name'] ?? 'Unassigned') ?></td></tr>
                <tr><th>Start Date:</th><td><?= $workOrder['start_date'] ? date('M d, Y', strtotime($workOrder['start_date'])) : '-' ?></td></tr>
                <tr><th>Due Date:</th><td>
                    <?= $workOrder['due_date'] ? date('M d, Y', strtotime($workOrder['due_date'])) : 'No due date' ?>
                    <?php if ($workOrder['due_date'] && $workOrder['due_date'] < date('Y-m-d') && $workOrder['status'] !== 'completed'): ?>
                        <span class="text-danger">(Overdue)</span>
                    <?php endif; ?>
                </td></tr>
                <tr><th>Created:</th><td><?= date('M d, Y H:i', strtotime($workOrder['created_at'])) ?></td></tr>
            </table>
        </div>
        
        <!-- Tasks -->
        <?php if (!empty($tasks)): ?>
        <div class="card mb-4">
            <h3>Tasks (<?= count($tasks) ?>)</h3>
            <table class="table table-sm">
                <thead><tr><th>Task</th><th>Assigned To</th><th>Due Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?= htmlspecialchars($task['task_name']) ?></td>
                        <td><?= htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned') ?></td>
                        <td><?= $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '-' ?></td>
                        <td><span class="badge badge-<?= $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'primary' : 'secondary') ?>"><?= ucfirst($task['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Timeline -->
        <div class="card mb-4">
            <h3>Timeline</h3>
            <div class="timeline">
                <?php foreach ($timeline as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-date"><?= date('M d, H:i', strtotime($item['created_at'])) ?></div>
                    <div class="timeline-content">
                        <strong><?= htmlspecialchars($item['action']) ?></strong>
                        <?php if ($item['notes']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($item['notes']) ?></small>
                        <?php endif; ?>
                        <br><small>by <?= htmlspecialchars($item['user_name'] ?? 'System') ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="card">
            <h3>Attachments (<?= count($attachments) ?>)</h3>
            <ul class="list-unstyled">
                <?php foreach ($attachments as $att): ?>
                <li class="mb-2">
                    <a href="/uploads/attachments/<?= htmlspecialchars($att['filename']) ?>" target="_blank" class="d-flex align-items-center">
                        <i class="icon-file mr-2"></i>
                        <span><?= htmlspecialchars($att['original_name']) ?></span>
                    </a>
                    <small class="text-muted"><?= date('M d, Y', strtotime($att['created_at'])) ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
