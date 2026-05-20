<?php
/**
 * Bill View - Single Bill Display with WhatsApp Share
 */
?>
<div class="page-header">
    <h1>Bill #<?= htmlspecialchars($bill['bill_no']) ?></h1>
    <div class="btn-group">
        <?php if (!$bill['is_locked']): ?>
            <a href="?module=billing&action=edit&id=<?= $bill['id'] ?>" class="btn btn-secondary">Edit</a>
        <?php endif; ?>
        <a href="?module=billing&action=print&id=<?= $bill['id'] ?>" target="_blank" class="btn btn-dark">
            <i class="icon-print"></i> Print
        </a>
        <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-success">
            <i class="icon-whatsapp"></i> Share via WhatsApp
        </a>
        <a href="?module=billing" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<div class="row">
    <!-- Bill Details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5>Bill Information</h5>
                <span class="badge badge-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'overdue' ? 'danger' : 'warning') ?>">
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Customer:</strong><br>
                        <?= htmlspecialchars($bill['customer_name']) ?><br>
                        <?php if ($bill['phone']): ?>
                            <small class="text-muted">📱 <?= htmlspecialchars($bill['phone']) ?></small><br>
                        <?php endif; ?>
                        <?php if ($bill['email']): ?>
                            <small class="text-muted">✉️ <?= htmlspecialchars($bill['email']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-end">
                        <strong>Bill Date:</strong><br><?= date('M d, Y', strtotime($bill['date'])) ?><br>
                        <strong>Due Date:</strong><br><?= date('M d, Y', strtotime($bill['due_date'])) ?><br>
                        <?php if ($bill['stock_out_no']): ?>
                            <strong>From Delivery:</strong><br><?= htmlspecialchars($bill['stock_out_no']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($bill['notes']): ?>
                    <div class="alert alert-light">
                        <strong>Notes:</strong> <?= nl2br(htmlspecialchars($bill['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Items</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <?= htmlspecialchars($item['product_name'] ?: $item['description']) ?>
                                    <?php if ($item['code']): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($item['code']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info"><?= ucfirst($item['item_type']) ?></span></td>
                                <td><?= number_format($item['quantity'], 3) ?></td>
                                <td><?= $helpers->formatCurrency($item['unit_price']) ?></td>
                                <td><?= $item['tax_name'] ? $item['tax_name'] . ' (' . $item['tax_rate'] . '%)' : '-' ?></td>
                                <td><?= $helpers->formatCurrency($item['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end"><strong>Subtotal:</strong></td>
                            <td><?= $helpers->formatCurrency($bill['subtotal']) ?></td>
                        </tr>
                        <?php if ($bill['discount'] > 0): ?>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Discount (<?= $bill['discount_type'] === 'percent' ? '%' : 'Flat' ?>):</strong></td>
                                <td class="text-success">- <?= $helpers->formatCurrency($bill['discount_type'] === 'percent' ? ($bill['subtotal'] * $bill['discount'] / 100) : $bill['discount']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="6" class="text-end"><strong>Tax:</strong></td>
                            <td><?= $helpers->formatCurrency($bill['tax']) ?></td>
                        </tr>
                        <tr class="table-primary">
                            <td colspan="6" class="text-end"><strong>TOTAL:</strong></td>
                            <td><strong><?= $helpers->formatCurrency($bill['total']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Payments -->
        <?php if (!empty($payments)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payments Received</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Payment #</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($p['date'])) ?></td>
                                    <td><?= htmlspecialchars($p['payment_no']) ?></td>
                                    <td><span class="badge badge-secondary"><?= ucfirst($p['method']) ?></span></td>
                                    <td class="text-success"><?= $helpers->formatCurrency($p['amount']) ?></td>
                                    <td><?= htmlspecialchars($p['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Summary Sidebar -->
    <div class="col-lg-4">
        <div class="card mb-4 <?= $bill['due'] > 0 && strtotime($bill['due_date']) < time() ? 'border-danger' : '' ?>">
            <div class="card-header bg-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'overdue' ? 'danger' : 'primary') ?> text-white">
                <h5>Payment Summary</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Amount:</span>
                        <strong><?= $helpers->formatCurrency($bill['total']) ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Paid:</span>
                        <strong class="text-success"><?= $helpers->formatCurrency($bill['paid']) ?></strong>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="h5">Due:</span>
                        <strong class="h5 <?= $bill['due'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $helpers->formatCurrency($bill['due']) ?>
                        </strong>
                    </div>
                </div>

                <?php if ($bill['due'] > 0): ?>
                    <a href="?module=payments&action=create&type=received&bill_id=<?= $bill['id'] ?>" 
                       class="btn btn-primary w-100">Record Payment</a>
                <?php else: ?>
                    <div class="alert alert-success text-center">
                        ✓ Fully Paid
                    </div>
                <?php endif; ?>

                <?php if ($bill['is_locked']): ?>
                    <div class="alert alert-warning mt-3">
                        <small>🔒 This bill is locked due to payment recording.</small>
                    </div>
                <?php endif; ?>

                <?php if ($bill['repeat_interval'] !== 'none'): ?>
                    <hr>
                    <div class="mt-3">
                        <strong>Recurring Billing:</strong><br>
                        <span class="badge badge-info"><?= ucfirst($bill['repeat_interval']) ?></span><br>
                        <small>Next: <?= $bill['repeat_next_date'] ? date('M d, Y', strtotime($bill['repeat_next_date'])) : 'Not set' ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <h5>Timeline</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <small class="text-muted"><?= date('M d, Y H:i', strtotime($bill['created_at'])) ?></small><br>
                        <strong>Bill Created</strong><br>
                        <small>by <?= htmlspecialchars($bill['created_by_name']) ?></small>
                    </div>
                    <?php if ($bill['stock_out_id']): ?>
                        <div class="timeline-item">
                            <small class="text-muted">From Stock Out</small><br>
                            <a href="?module=stock_out&action=view&id=<?= $bill['stock_out_id'] ?>">
                                <?= htmlspecialchars($bill['stock_out_no']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                        <div class="timeline-item">
                            <small class="text-muted"><?= date('M d, Y', strtotime($p['date'])) ?></small><br>
                            <strong>Payment: <?= $helpers->formatCurrency($p['amount']) ?></strong><br>
                            <small><?= ucfirst($p['method']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline { padding-left: 1rem; border-left: 2px solid var(--border); }
.timeline-item { margin-bottom: 1rem; padding-left: 1rem; position: relative; }
.timeline-item::before {
    content: ''; position: absolute; left: -1.35rem; top: 0.3rem;
    width: 0.7rem; height: 0.7rem; border-radius: 50%;
    background: var(--primary);
}
.border-danger { border: 2px solid var(--danger) !important; }
</style>
