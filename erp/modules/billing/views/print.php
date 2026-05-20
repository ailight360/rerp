<?php
/**
 * Bill Print Layout - Tax Invoice
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print <?= htmlspecialchars($bill['bill_no']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; }
        .invoice { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company-info h1 { color: #3b82f6; margin-bottom: 10px; }
        .invoice-details { text-align: right; }
        .invoice-details h2 { color: #1e293b; margin-bottom: 10px; }
        .customer-section { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #1e293b; color: white; font-weight: 600; }
        .totals { margin-left: auto; width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 12px; }
        .totals-row.total { background: #3b82f6; color: white; font-size: 1.2em; font-weight: bold; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
        .sig-line { border-top: 1px solid #1e293b; width: 200px; padding-top: 5px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">🖨️ Print Invoice</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; margin-left: 10px;">✕ Close</button>
    </div>

    <div class="invoice">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1><?= htmlspecialchars($helpers->getSetting('company_name') ?: 'Company Name') ?></h1>
                <p><?= nl2br(htmlspecialchars($helpers->getSetting('company_address') ?: '')) ?></p>
                <p>📞 <?= htmlspecialchars($helpers->getSetting('company_phone') ?: '') ?></p>
            </div>
            <div class="invoice-details">
                <h2>TAX INVOICE</h2>
                <p><strong>Bill #:</strong> <?= htmlspecialchars($bill['bill_no']) ?></p>
                <p><strong>Date:</strong> <?= date('M d, Y', strtotime($bill['date'])) ?></p>
                <p><strong>Due Date:</strong> <?= date('M d, Y', strtotime($bill['due_date'])) ?></p>
            </div>
        </div>

        <!-- Customer -->
        <div class="customer-section">
            <strong>Bill To:</strong><br>
            <?= htmlspecialchars($bill['customer_name']) ?><br>
            <?php if ($bill['address']): ?>
                <?= nl2br(htmlspecialchars($bill['address'])) ?><br>
            <?php endif; ?>
            <?php if ($bill['phone']): ?>
                📞 <?= htmlspecialchars($bill['phone']) ?><br>
            <?php endif; ?>
            <?php if ($bill['email']): ?>
                ✉️ <?= htmlspecialchars($bill['email']) ?>
            <?php endif; ?>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="40%">Description</th>
                    <th width="10%">Type</th>
                    <th width="10%" style="text-align: right;">Qty</th>
                    <th width="15%" style="text-align: right;">Rate</th>
                    <th width="20%" style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <?= htmlspecialchars($item['product_name'] ?: $item['description']) ?>
                            <?php if ($item['code']): ?>
                                <small style="color: #64748b;">(<?= htmlspecialchars($item['code']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= ucfirst($item['item_type']) ?></td>
                        <td style="text-align: right;"><?= number_format($item['quantity'], 3) ?></td>
                        <td style="text-align: right;"><?= $helpers->formatCurrency($item['unit_price']) ?></td>
                        <td style="text-align: right;"><?= $helpers->formatCurrency($item['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span><?= $helpers->formatCurrency($bill['subtotal']) ?></span>
            </div>
            <?php if ($bill['discount'] > 0): ?>
                <div class="totals-row">
                    <span>Discount (<?= $bill['discount_type'] === 'percent' ? round($bill['discount'], 1) . '%' : 'Flat' ?>):</span>
                    <span>-<?= $helpers->formatCurrency($bill['discount_type'] === 'percent' ? ($bill['subtotal'] * $bill['discount'] / 100) : $bill['discount']) ?></span>
                </div>
            <?php endif; ?>
            <div class="totals-row">
                <span>Tax:</span>
                <span><?= $helpers->formatCurrency($bill['tax']) ?></span>
            </div>
            <div class="totals-row total">
                <span>TOTAL:</span>
                <span><?= $helpers->formatCurrency($bill['total']) ?></span>
            </div>
            <div class="totals-row">
                <span>Paid:</span>
                <span><?= $helpers->formatCurrency($bill['paid']) ?></span>
            </div>
            <div class="totals-row" style="font-size: 1.1em; font-weight: bold; color: <?= $bill['due'] > 0 ? '#ef4444' : '#10b981' ?>;">
                <span>DUE:</span>
                <span><?= $helpers->formatCurrency($bill['due']) ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?php if ($bill['notes']): ?>
                <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($bill['notes'])) ?></p>
            <?php endif; ?>
            <p style="margin-top: 10px;"><strong>Terms:</strong> Payment due within specified days. Thank you for your business!</p>
        </div>

        <!-- Signature -->
        <div class="signature">
            <div>
                <div class="sig-line">Customer Signature</div>
            </div>
            <div>
                <div class="sig-line">Authorized Signature</div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
