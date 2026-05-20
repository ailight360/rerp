<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-content">
    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary">👥</div>
            <div class="kpi-content">
                <h3><?= number_format($stats['total_customers']) ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon bg-success">🏭</div>
            <div class="kpi-content">
                <h3><?= number_format($stats['total_vendors']) ?></h3>
                <p>Total Vendors</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon bg-info">📦</div>
            <div class="kpi-content">
                <h3><?= number_format($stats['total_products']) ?></h3>
                <p>Total Products</p>
            </div>
        </div>
        
        <div class="kpi-card <?= $stats['low_stock'] > 0 ? 'border-danger' : '' ?>">
            <div class="kpi-icon bg-warning">⚠️</div>
            <div class="kpi-content">
                <h3 class="<?= $stats['low_stock'] > 0 ? 'text-danger' : '' ?>"><?= number_format($stats['low_stock']) ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon bg-purple">💰</div>
            <div class="kpi-content">
                <h3><?= Helpers::formatCurrency($stats['monthly_sales']) ?></h3>
                <p>Monthly Sales</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon bg-cyan">🛒</div>
            <div class="kpi-content">
                <h3><?= Helpers::formatCurrency($stats['monthly_purchases']) ?></h3>
                <p>Monthly Purchases</p>
            </div>
        </div>
        
        <div class="kpi-card <?= $stats['overdue_bills'] > 0 ? 'border-danger' : '' ?>">
            <div class="kpi-icon bg-danger">📋</div>
            <div class="kpi-content">
                <h3 class="<?= $stats['overdue_bills'] > 0 ? 'text-danger' : '' ?>"><?= number_format($stats['overdue_bills']) ?></h3>
                <p>Overdue Bills</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon bg-orange">💳</div>
            <div class="kpi-content">
                <h3><?= Helpers::formatCurrency($stats['total_receivables']) ?></h3>
                <p>Total Receivables</p>
            </div>
        </div>
    </div>
    
    <!-- Charts & Tables Row -->
    <div class="grid-2">
        <!-- Low Stock Alert -->
        <div class="card">
            <div class="card-header">
                <h3>⚠️ Low Stock Alert</h3>
            </div>
            <div class="card-body">
                <?php if (empty($lowStockProducts)): ?>
                    <p class="text-muted text-center py-4">All products are well stocked!</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                                    <td><span class="badge badge-danger"><?= $product['current_stock'] ?></span></td>
                                    <td><?= $product['min_stock'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h3>📤 Recent Sales</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recentSales)): ?>
                    <p class="text-muted text-center py-4">No recent sales</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sale['invoice_no']) ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name'] ?? 'N/A') ?></td>
                                    <td><?= Helpers::formatDate($sale['date']) ?></td>
                                    <td><?= Helpers::formatCurrency($sale['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chart Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>📊 Sales Overview (Last 6 Months)</h3>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="80"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Fetch sales data and render chart
fetch('/api/dashboard.php?chart=sales')
    .then(r => r.json())
    .then(data => {
        const ctx = document.getElementById('salesChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Sales',
                    data: data.sales,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }, {
                    label: 'Purchases',
                    data: data.purchases,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
