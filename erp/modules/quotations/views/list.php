<?php include __DIR__ . '/../../layout.php'; layoutHeader('Quotations'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-file-text"></i> Quotations</h1>
        <p class="text-muted">Create and manage customer quotations</p>
    </div>
    <a href="?module=quotations&action=form" class="btn btn-primary">
        <i class="icon-plus"></i> New Quotation
    </a>
</div>

<!-- Filters -->
<form method="GET" class="card mb-4">
    <input type="hidden" name="module" value="quotations">
    <div class="grid grid-4">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" 
                   placeholder="Quote number or title" 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div>
            <label class="form-label">Customer</label>
            <select name="customer" class="form-control">
                <option value="">All Customers</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $customer == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="draft" <?= $status == 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="sent" <?= $status == 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="accepted" <?= $status == 'accepted' ? 'selected' : '' ?>>Accepted</option>
                <option value="rejected" <?= $status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="expired" <?= $status == 'expired' ? 'selected' : '' ?>>Expired</option>
            </select>
        </div>
        <div class="flex align-end gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="?module=quotations" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Quotations Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Quote No</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Valid Until</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No quotations found. <a href="?module=quotations&action=form">Create one</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($q['quote_no']) ?></strong></td>
                            <td><?= htmlspecialchars($q['customer_name'] ?? '-') ?></td>
                            <td><?= date('d M Y', strtotime($q['date'])) ?></td>
                            <td>
                                <?= $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '-' ?>
                                <?php 
                                if ($q['valid_until'] && strtotime($q['valid_until']) < time() && $q['status'] != 'accepted'): 
                                ?>
                                    <span class="badge badge-danger ml-1">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><strong><?= formatCurrency($q['total']) ?></strong></td>
                            <td>
                                <?php
                                $badgeClass = 'secondary';
                                switch($q['status']) {
                                    case 'draft': $badgeClass = 'secondary'; break;
                                    case 'sent': $badgeClass = 'info'; break;
                                    case 'accepted': $badgeClass = 'success'; break;
                                    case 'rejected': $badgeClass = 'danger'; break;
                                    case 'expired': $badgeClass = 'warning'; break;
                                }
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>"><?= ucfirst($q['status']) ?></span>
                                <?php if ($q['converted_to_sale']): ?>
                                    <br><small class="text-xs text-success">
                                        → Sale #<?= $q['converted_to_sale'] ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?module=quotations&action=view&id=<?= $q['id'] ?>" 
                                       class="btn btn-sm btn-secondary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    <a href="?module=quotations&action=form&id=<?= $q['id'] ?>" 
                                       class="btn btn-sm btn-primary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php if ($q['status'] != 'accepted' && !$q['converted_to_sale']): ?>
                                        <a href="?module=quotations&action=convertToSale&id=<?= $q['id'] ?>" 
                                           class="btn btn-sm btn-success" title="Convert to Sale"
                                           onclick="return confirm('Convert this quotation to a sale?')">
                                            <i class="icon-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.text-xs { font-size: 0.75rem; }
</style>

<?php layoutFooter(); ?>
