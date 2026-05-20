<?php
/**
 * Bill List View
 */
?>
<div class="page-header">
    <h1>Billing / Tax Invoices</h1>
    <a href="?module=billing&action=create" class="btn btn-primary">+ New Bill</a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="module" value="billing">
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-control">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Bills Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No bills found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>
                                    <a href="?module=billing&action=view&id=<?= $bill['id'] ?>">
                                        <?= htmlspecialchars($bill['bill_no']) ?>
                                    </a>
                                    <?php if ($bill['is_locked']): ?>
                                        <span class="badge badge-success ms-1">Locked</span>
                                    <?php endif; ?>
                                    <?php if ($bill['repeat_interval'] !== 'none'): ?>
                                        <span class="badge badge-info ms-1">Recurring</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bill['customer_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($bill['date'])) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($bill['due_date'])) ?>
                                    <?php 
                                    $isOverdue = $bill['status'] === 'overdue' || 
                                        ($bill['due'] > 0 && strtotime($bill['due_date']) < time());
                                    if ($isOverdue): 
                                    ?>
                                        <span class="text-danger fw-bold">!</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $helpers->formatCurrency($bill['total']) ?></td>
                                <td><?= $helpers->formatCurrency($bill['paid']) ?></td>
                                <td class="<?= $bill['due'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                    <?= $helpers->formatCurrency($bill['due']) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'unpaid' => 'badge-danger',
                                        'partial' => 'badge-warning',
                                        'paid' => 'badge-success',
                                        'overdue' => 'badge-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $statusClass[$bill['status']] ?? 'badge-secondary' ?>">
                                        <?= ucfirst($bill['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?module=billing&action=view&id=<?= $bill['id'] ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="icon-eye"></i>
                                        </a>
                                        <?php if (!$bill['is_locked']): ?>
                                            <a href="?module=billing&action=edit&id=<?= $bill['id'] ?>" 
                                               class="btn btn-outline-secondary" title="Edit">
                                                <i class="icon-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?module=billing&action=print&id=<?= $bill['id'] ?>" 
                                           target="_blank" class="btn btn-outline-dark" title="Print">
                                            <i class="icon-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?module=billing&page=<?= $i ?>&<?= http_build_query(array_filter($_GET)) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<style>
.badge-info { background-color: var(--primary); color: white; }
</style>
