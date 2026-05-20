<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Payments</h1>
    <a href="?action=form" class="btn btn-primary">+ New Payment</a>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div class="filter-row">
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="received" <?= $type === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="paid" <?= $type === 'paid' ? 'selected' : '' ?>>Paid</option>
            </select>
            
            <select name="party_type" class="form-control">
                <option value="">All Parties</option>
                <option value="customer" <?= $party_type === 'customer' ? 'selected' : '' ?>>Customers</option>
                <option value="vendor" <?= $party_type === 'vendor' ? 'selected' : '' ?>>Vendors</option>
            </select>
            
            <select name="method" class="form-control">
                <option value="">All Methods</option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="bank" <?= $method === 'bank' ? 'selected' : '' ?>>Bank</option>
                <option value="mobile" <?= $method === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                <option value="check" <?= $method === 'check' ? 'selected' : '' ?>>Check</option>
            </select>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control" placeholder="From Date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control" placeholder="To Date">
            
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="?" class="btn btn-light">Clear</a>
        </div>
    </form>
    
    <?php if ($total > 0): ?>
    <div class="stats-bar">
        <span>Total Received: <strong><?= Helpers::formatCurrency($stats['total_received'] ?? 0) ?></strong></span>
        <span>Total Paid: <strong><?= Helpers::formatCurrency($stats['total_paid'] ?? 0) ?></strong></span>
    </div>
    <?php endif; ?>
    
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Party</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['payment_no']) ?></td>
                <td><?= date('M d, Y', strtotime($p['date'])) ?></td>
                <td><span class="badge badge-<?= $p['type'] === 'received' ? 'success' : 'danger' ?>"><?= ucfirst($p['type']) ?></span></td>
                <td><?= htmlspecialchars($p['party_name']) ?></td>
                <td><span class="badge badge-secondary"><?= ucfirst($p['method']) ?></span></td>
                <td><strong><?= Helpers::formatCurrency($p['amount']) ?></strong></td>
                <td>
                    <?php if ($p['reference_id']): ?>
                        <small><?= str_replace('_', ' ', $p['reference_type']) ?></small>
                    <?php else: ?>
                        <em>Manual</em>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?action=form&id=<?= $p['id'] ?>" class="btn btn-sm btn-light">Edit</a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payment?')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-item <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    
</div>
