<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1><i class="icon-board"></i> Work Orders Kanban</h1>
    <a href="/work-orders" class="btn btn-outline">Back to List</a>
</div>

<div class="kanban-board">
    <?php 
    $statusLabels = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress', 
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    $statusColors = [
        'pending' => '#6c757d',
        'in_progress' => '#3b82f6',
        'on_hold' => '#f59e0b',
        'completed' => '#10b981',
        'cancelled' => '#ef4444'
    ];
    ?>
    
    <?php foreach ($columns as $status => $orders): ?>
    <div class="kanban-column" data-status="<?= $status ?>">
        <div class="kanban-column-header" style="border-top-color: <?= $statusColors[$status] ?>">
            <h3><?= $statusLabels[$status] ?></h3>
            <span class="badge"><?= count($orders) ?></span>
        </div>
        
        <div class="kanban-cards">
            <?php foreach ($orders as $wo): ?>
            <div class="kanban-card" draggable="true" data-id="<?= $wo['id'] ?>">
                <div class="kanban-card-header">
                    <strong><?= htmlspecialchars($wo['wo_no']) ?></strong>
                    <span class="badge badge-<?= $wo['priority'] === 'urgent' ? 'danger' : ($wo['priority'] === 'high' ? 'warning' : 'secondary') ?> badge-sm">
                        <?= ucfirst($wo['priority']) ?>
                    </span>
                </div>
                <div class="kanban-card-body">
                    <p class="mb-1"><strong><?= htmlspecialchars($wo['title']) ?></strong></p>
                    <?php if ($wo['customer_name']): ?>
                    <small class="text-muted"><?= htmlspecialchars($wo['customer_name']) ?></small>
                    <?php endif; ?>
                    <?php if ($wo['due_date']): ?>
                    <br><small class="<?= $wo['due_date'] < date('Y-m-d') && $wo['status'] !== 'completed' ? 'text-danger' : 'text-muted' ?>">
                        Due: <?= date('M d', strtotime($wo['due_date'])) ?>
                    </small>
                    <?php endif; ?>
                </div>
                <div class="kanban-card-footer">
                    <a href="/work-orders?action=view&id=<?= $wo['id'] ?>" class="btn btn-sm btn-outline">View</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
.kanban-board {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding: 1rem 0;
}
.kanban-column {
    min-width: 300px;
    background: #f1f5f9;
    border-radius: 8px;
    padding: 0.75rem;
}
.kanban-column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-top: 3px solid;
}
.kanban-column-header h3 {
    font-size: 0.9rem;
    margin: 0;
    color: #475569;
}
.kanban-cards {
    min-height: 400px;
}
.kanban-card {
    background: white;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: grab;
}
.kanban-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.kanban-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.kanban-card-body {
    font-size: 0.875rem;
    color: #475569;
}
.kanban-card-footer {
    margin-top: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e2e8f0;
}
</style>

<script src="/assets/js/kanban.js"></script>
