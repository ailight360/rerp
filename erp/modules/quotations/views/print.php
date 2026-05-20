<?php
// Print layout for quotation - optimized for printing
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quotation['quote_no']) ?> - Quotation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .company-info h1 { color: #3b82f6; font-size: 24px; margin-bottom: 8px; }
        .company-info p { color: #64748b; font-size: 14px; }
        .quote-info { text-align: right; }
        .quote-info h2 { font-size: 20px; color: #0f172a; margin-bottom: 8px; }
        .quote-info p { font-size: 14px; color: #64748b; }
        .customer-section { margin-bottom: 30px; }
        .customer-section h3 { font-size: 16px; color: #0f172a; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; padding: 12px 8px; text-align: left; font-weight: 600; font-size: 13px; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px 8px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .text-right { text-align: right; }
        .totals { margin-left: auto; width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
        .totals-row.total { background: #f8fafc; font-weight: bold; font-size: 16px; border-bottom: 2px solid #0f172a; }
        .notes-section { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .notes-box { background: #f8fafc; padding: 15px; border-radius: 6px; }
        .notes-box h4 { font-size: 14px; color: #0f172a; margin-bottom: 8px; }
        .notes-box p { font-size: 13px; color: #64748b; white-space: pre-wrap; }
        .footer { margin-top: 60px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
            .container { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1><?= htmlspecialchars(getSetting('company_name') ?: 'Your Company') ?></h1>
                <p><?= nl2br(htmlspecialchars(getSetting('company_address') ?: '')) ?></p>
                <p><?= htmlspecialchars(getSetting('company_phone') ?: '') ?> | <?= htmlspecialchars(getSetting('company_email') ?: '') ?></p>
            </div>
            <div class="quote-info">
                <h2>QUOTATION</h2>
                <p><strong><?= htmlspecialchars($quotation['quote_no']) ?></strong></p>
                <p>Date: <?= date('d M Y', strtotime($quotation['date'])) ?></p>
                <p>Valid Until: <?= $quotation['valid_until'] ? date('d M Y', strtotime($quotation['valid_until'])) : 'No expiry' ?></p>
                <p>Status: <span class="badge badge-info"><?= ucfirst($quotation['status']) ?></span></p>
            </div>
        </div>

        <!-- Customer -->
        <div class="customer-section">
            <h3>Quotation For:</h3>
            <p><strong><?= htmlspecialchars($quotation['customer_name'] ?? '-') ?></strong></p>
            <p><?= nl2br(htmlspecialchars($quotation['customer_address'] ?? '-')) ?></p>
            <p>Phone: <?= htmlspecialchars($quotation['customer_phone'] ?? '-') ?></p>
            <p>Email: <?= htmlspecialchars($quotation['customer_email'] ?? '-') ?></p>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Product Code</th>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
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
                                <span class="badge badge-info" style="margin-left: 6px;">Pair</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format($item['quantity'], 3) ?></td>
                        <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                        <td class="text-right"><strong><?= formatCurrency($item['total']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span><?= formatCurrency($quotation['subtotal']) ?></span>
            </div>
            <?php if ($quotation['discount'] > 0): ?>
                <div class="totals-row">
                    <span>Discount:</span>
                    <span style="color: #ef4444;">- <?= formatCurrency($quotation['discount']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($quotation['tax'] > 0): ?>
                <div class="totals-row">
                    <span>Tax:</span>
                    <span><?= formatCurrency($quotation['tax']) ?></span>
                </div>
            <?php endif; ?>
            <div class="totals-row total">
                <span>TOTAL:</span>
                <span style="color: #3b82f6;"><?= formatCurrency($quotation['total']) ?></span>
            </div>
        </div>

        <!-- Notes & Terms -->
        <?php if ($quotation['notes'] || $quotation['terms']): ?>
            <div class="notes-section">
                <?php if ($quotation['notes']): ?>
                    <div class="notes-box">
                        <h4>Notes:</h4>
                        <p><?= htmlspecialchars($quotation['notes']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($quotation['terms']): ?>
                    <div class="notes-box">
                        <h4>Terms & Conditions:</h4>
                        <p><?= htmlspecialchars($quotation['terms']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>This quotation is valid until the date specified above.</p>
            <p>Thank you for your business!</p>
            <button onclick="window.print()" class="no-print" style="margin-top: 20px; padding: 10px 30px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Print</button>
        </div>
    </div>
</body>
</html>
