<?php include __DIR__ . '/../../layout.php'; ?>

<div class="page-header">
    <div>
        <h1>Stock In #<?= htmlspecialchars($stockIn->invoice_no) ?></h1>
        <p class="text-muted"><?= Helpers::formatDate($stockIn->date) ?></p>
    </div>
    <div class="flex gap-2">
        <?php if (!$stockIn->is_locked): ?>
            <a href="/stock_in/edit/<?= $stockIn->id ?>" class="btn btn-primary">
                <svg class="icon"><use href="#icon-edit"></use></svg>
                Edit
            </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline">
            <svg class="icon"><use href="#icon-printer"></use></svg>
            Print
        </button>
        <a href="/stock_in" class="btn btn-outline">Back</a>
    </div>
</div>

<div class="grid grid-2 gap-4">
    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">Vendor Information</h4>
            <?php if ($stockIn->vendor_name): ?>
                <p class="mb-1"><strong><?= htmlspecialchars($stockIn->vendor_name) ?></strong></p>
                <?php if ($stockIn->vendor_phone): ?>
                    <p class="text-muted mb-1">📞 <?= htmlspecialchars($stockIn->vendor_phone) ?></p>
                <?php endif; ?>
                <?php if ($stockIn->vendor_address): ?>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($stockIn->vendor_address)) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">No vendor specified</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">Invoice Details</h4>
            <table class="table table-sm">
                <tr>
                    <td class="text-muted">Invoice No:</td>
                    <td><strong><?= htmlspecialchars($stockIn->invoice_no) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Date:</td>
                    <td><?= Helpers::formatDate($stockIn->date) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Status:</td>
                    <td>
                        <span class="badge badge-<?= $stockIn->status === 'confirmed' ? 'success' : ($stockIn->status === 'draft' ? 'warning' : 'danger') ?>">
                            <?= ucfirst($stockIn->status) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Locked:</td>
                    <td>
                        <?php if ($stockIn->is_locked): ?>
                            <span class="badge badge-danger">Yes</span>
                        <?php else: ?>
                            <span class="text-muted">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Created By:</td>
                    <td><?= $stockIn->created_by_name ? htmlspecialchars($stockIn->created_by_name) : '-' ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Items</h3>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 0;
                foreach ($stockIn->items as $item): 
                    $i++;
                ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item->product_name) ?></strong>
                            <?php if ($item->product_code): ?>
                                <span class="text-muted text-sm">(<?= htmlspecialchars($item->product_code) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $item->item_type === 'pair' ? 'info' : 'secondary' ?>">
                                <?= ucfirst($item->item_type) ?>
                            </span>
                        </td>
                        <td class="text-right"><?= number_format($item->quantity, 3) ?></td>
                        <td class="text-right">
                            <?php if ($item->unit_price !== null): ?>
                                <?= Helpers::formatCurrency($item->unit_price) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($item->tax_rate): ?>
                                <?= $item->tax_name ?> (<?= $item->tax_rate ?>%)
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($item->total !== null): ?>
                                <?= Helpers::formatCurrency($item->total) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($stockIn->subtotal !== null): ?>
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="6" class="text-right font-bold">Subtotal:</td>
                        <td class="text-right font-bold"><?= Helpers::formatCurrency($stockIn->subtotal) ?></td>
                    </tr>
                    <?php if ($stockIn->discount > 0): ?>
                        <tr>
                            <td colspan="6" class="text-right">Discount (<?= $stockIn->discount_type === 'percent' ? $stockIn->discount . '%' : 'Flat' ?>):</td>
                            <td class="text-right text-danger">-<?= Helpers::formatCurrency($stockIn->discount) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($stockIn->tax !== null && $stockIn->tax > 0): ?>
                        <tr>
                            <td colspan="6" class="text-right font-bold">Tax:</td>
                            <td class="text-right font-bold"><?= Helpers::formatCurrency($stockIn->tax) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="6" class="text-right font-bold text-lg">Total:</td>
                        <td class="text-right font-bold text-lg text-primary"><?= Helpers::formatCurrency($stockIn->total) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($stockIn->notes): ?>
    <div class="card">
        <div class="card-body">
            <h4 class="mb-2">Notes</h4>
            <p class="text-muted"><?= nl2br(htmlspecialchars($stockIn->notes)) ?></p>
        </div>
    </div>
<?php endif; ?>

<style media="print">
@media print {
    .sidebar, .top-nav, .page-header > div:last-child, .no-print { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 20px !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
