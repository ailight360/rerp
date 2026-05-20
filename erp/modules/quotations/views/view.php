<?php include __DIR__ . '/../../layout.php'; layoutHeader('View Quotation'); ?>

<div class="page-header">
    <div>
        <h1><i class="icon-file-text"></i> <?= htmlspecialchars($quotation['quote_no']) ?></h1>
        <p class="text-muted">Quotation details</p>
    </div>
    <div class="flex gap-2">
        <a href="?module=quotations&action=print&id=<?= $quotation['id'] ?>" target="_blank" class="btn btn-secondary">
            <i class="icon-print"></i> Print
        </a>
        <?php if ($quotation['status'] != 'accepted' && !$quotation['converted_to_sale']): ?>
            <a href="?module=quotations&action=convertToSale&id=<?= $quotation['id'] ?>" 
               class="btn btn-success"
               onclick="return confirm('Convert this quotation to a sale?')">
                <i class="icon-check"></i> Convert to Sale
            </a>
        <?php endif; ?>
        <a href="?module=quotations&action=form&id=<?= $quotation['id'] ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit
        </a>
        <a href="?module=quotations" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="grid grid-2 gap-4">
    <!-- Quotation Details -->
    <div class="card">
        <h3>Quotation Information</h3>
        <table class="table table-borderless">
            <tr>
                <td><strong>Quotation No:</strong></td>
                <td><?= htmlspecialchars($quotation['quote_no']) ?></td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td><?= date('d M Y', strtotime($quotation['date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Valid Until:</strong></td>
                <td>
                    <?= $quotation['valid_until'] ? date('d M Y', strtotime($quotation['valid_until'])) : 'No expiry' ?>
                    <?php if ($quotation['valid_until'] && strtotime($quotation['valid_until']) < time() && $quotation['status'] != 'accepted'): ?>
                        <span class="badge badge-danger ml-2">Expired</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <?php
                    $badgeClass = 'secondary';
                    switch($quotation['status']) {
                        case 'draft': $badgeClass = 'secondary'; break;
                        case 'sent': $badgeClass = 'info'; break;
                        case 'accepted': $badgeClass = 'success'; break;
                        case 'rejected': $badgeClass = 'danger'; break;
                        case 'expired': $badgeClass = 'warning'; break;
                    }
                    ?>
                    <span class="badge badge-<?= $badgeClass ?>"><?= ucfirst($quotation['status']) ?></span>
                    <?php if ($quotation['converted_to_sale']): ?>
                        <br><small class="text-success mt-1">
                            → Converted to Sale #<?= $quotation['converted_to_sale'] ?>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Created By:</strong></td>
                <td><?= htmlspecialchars($quotation['created_by_name'] ?? 'Unknown') ?></td>
            </tr>
        </table>
    </div>

    <!-- Customer Details -->
    <div class="card">
        <h3>Customer Information</h3>
        <table class="table table-borderless">
            <tr>
                <td><strong>Name:</strong></td>
                <td><?= htmlspecialchars($quotation['customer_name'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?= htmlspecialchars($quotation['customer_phone'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?= htmlspecialchars($quotation['customer_email'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Address:</strong></td>
                <td><?= nl2br(htmlspecialchars($quotation['customer_address'] ?? '-')) ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- Items Table -->
<div class="card mt-4">
    <h3>Quotation Items</h3>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product Code</th>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                foreach ($items as $item): 
                ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($item['product_code'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php if ($item['item_type'] == 'pair'): ?>
                                <span class="badge badge-info ml-1">Pair</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format($item['quantity'], 3) ?></td>
                        <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                        <td class="text-right">
                            <?= $item['tax_rate'] ? $item['tax_rate'] . '%' : '-' ?>
                        </td>
                        <td class="text-right"><strong><?= formatCurrency($item['total']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right"><strong>Subtotal:</strong></td>
                    <td class="text-right"><?= formatCurrency($quotation['subtotal']) ?></td>
                </tr>
                <?php if ($quotation['discount'] > 0): ?>
                    <tr>
                        <td colspan="6" class="text-right">
                            <strong>Discount (<?= $quotation['discount_type'] == 'percent' ? '%' : '' ?>):</strong>
                        </td>
                        <td class="text-right text-danger">
                            - <?= formatCurrency($quotation['discount']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if ($quotation['tax'] > 0): ?>
                    <tr>
                        <td colspan="6" class="text-right"><strong>Tax:</strong></td>
                        <td class="text-right"><?= formatCurrency($quotation['tax']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="bg-light">
                    <td colspan="6" class="text-right"><strong class="text-lg">TOTAL:</strong></td>
                    <td class="text-right"><strong class="text-lg text-primary"><?= formatCurrency($quotation['total']) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Notes & Terms -->
<?php if ($quotation['notes'] || $quotation['terms']): ?>
    <div class="grid grid-2 gap-4 mt-4">
        <?php if ($quotation['notes']): ?>
            <div class="card">
                <h3>Notes</h3>
                <p><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($quotation['terms']): ?>
            <div class="card">
                <h3>Terms & Conditions</h3>
                <p><?= nl2br(htmlspecialchars($quotation['terms'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
.table-borderless td, .table-borderless th {
    border: none;
    padding: 0.5rem 0;
}
.text-lg { font-size: 1.125rem; }
.bg-light { background-color: #f8fafc; }
</style>

<?php layoutFooter(); ?>
