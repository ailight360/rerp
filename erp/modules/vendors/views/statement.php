<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Statement - <?= htmlspecialchars($vendor->name) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company-info h2 { color: #1e293b; margin-bottom: 5px; }
        .company-info p { color: #64748b; font-size: 14px; }
        .statement-info { text-align: right; }
        .statement-info h1 { color: #3b82f6; font-size: 24px; margin-bottom: 10px; }
        .vendor-details { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #1e293b; color: white; padding: 12px; text-align: left; font-size: 14px; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:nth-child(even) { background: #f8fafc; }
        .text-right { text-align: right; }
        .summary { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary-box { background: #1e293b; color: white; padding: 15px 30px; border-radius: 8px; }
        .summary-box h3 { font-size: 14px; opacity: 0.8; margin-bottom: 5px; }
        .summary-box .amount { font-size: 24px; font-weight: bold; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="no-print" style="margin-bottom: 20px; padding: 10px 20px; cursor: pointer;">
        📄 Print Statement
    </button>
    
    <div class="header">
        <div class="company-info">
            <h2><?= htmlspecialchars(getenv('APP_NAME') ?: 'Your Company') ?></h2>
            <p><?= htmlspecialchars(getenv('COMPANY_ADDRESS') ?: '') ?></p>
            <p><?= htmlspecialchars(getenv('COMPANY_PHONE') ?: '') ?></p>
        </div>
        <div class="statement-info">
            <h1>VENDOR STATEMENT</h1>
            <p><strong>Period:</strong> <?= $startDate ?> to <?= $endDate ?></p>
            <p><strong>Date:</strong> <?= date('Y-m-d') ?></p>
        </div>
    </div>
    
    <div class="vendor-details">
        <h3 style="margin-bottom: 10px;">Vendor Details</h3>
        <p><strong>Name:</strong> <?= htmlspecialchars($vendor->name) ?></p>
        <?php if ($vendor->phone): ?>
        <p><strong>Phone:</strong> <?= htmlspecialchars($vendor->phone) ?></p>
        <?php endif; ?>
        <?php if ($vendor->email): ?>
        <p><strong>Email:</strong> <?= htmlspecialchars($vendor->email) ?></p>
        <?php endif; ?>
        <?php if ($vendor->address): ?>
        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($vendor->address)) ?></p>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $runningBalance = $vendor->opening_balance;
            if ($vendor->balance_type === 'credit') {
                $runningBalance = -$runningBalance;
            }
            
            // Opening balance row
            if (count($entries) > 0 || $vendor->opening_balance != 0):
            ?>
            <tr style="background: #fef3c7;">
                <td><?= date('Y-m-d', strtotime($vendor->created_at)) ?></td>
                <td><strong>Opening Balance</strong></td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right"><strong><?= number_format(abs($vendor->opening_balance), 2) ?></strong></td>
            </tr>
            <?php endif; ?>
            
            <?php foreach ($entries as $entry): 
                $runningBalance += ($entry->debit - $entry->credit);
            ?>
            <tr>
                <td><?= date('Y-m-d', strtotime($entry->date)) ?></td>
                <td><?= htmlspecialchars($entry->description) ?></td>
                <td class="text-right"><?= $entry->debit > 0 ? number_format($entry->debit, 2) : '-' ?></td>
                <td class="text-right"><?= $entry->credit > 0 ? number_format($entry->credit, 2) : '-' ?></td>
                <td class="text-right"><strong><?= number_format(abs($runningBalance), 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="summary">
        <div class="summary-box">
            <h3>CURRENT BALANCE</h3>
            <div class="amount"><?= number_format(abs($runningBalance), 2) ?></div>
        </div>
    </div>
    
    <div style="margin-top: 40px; text-align: center; color: #64748b; font-size: 12px;">
        <p>This is a computer-generated statement.</p>
        <p>Generated on <?= date('F d, Y \a\t h:i A') ?></p>
    </div>
</body>
</html>
