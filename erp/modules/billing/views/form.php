<?php
/**
 * Bill Form View (Create/Edit)
 */
$isEdit = isset($bill);
$formTitle = $isEdit ? 'Edit Bill' : 'New Bill';
$customerJson = json_encode($customers);
$taxRatesJson = json_encode($taxRates);
?>
<div class="page-header">
    <h1><?= $formTitle ?></h1>
    <a href="?module=billing" class="btn btn-secondary">Back to List</a>
</div>

<form id="billForm" method="POST" action="?module=billing&action=<?= $isEdit ? 'update' : 'store' ?>">
    <input type="hidden" name="csrf_token" value="<?= $helpers->generateCsrfToken() ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= $bill['id'] ?>">
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Bill Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer *</label>
                            <select name="customer_id" id="customer_id" class="form-control" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" 
                                            <?= ($isEdit && $bill['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (!$isEdit): ?>
                        <div class="col-md-6">
                            <label class="form-label">Load from Stock Out</label>
                            <select id="stockOutSelect" class="form-control">
                                <option value="">-- Select Stock Out --</option>
                            </select>
                            <small class="text-muted">Auto-fills products & quantities</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" class="form-control" 
                                   value="<?= $isEdit ? $bill['date'] : date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date *</label>
                            <input type="date" name="due_date" class="form-control" 
                                   value="<?= $isEdit ? $bill['due_date'] : date('Y-m-d', strtotime('+15 days')) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $isEdit ? htmlspecialchars($bill['notes']) : '' ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Items</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addLineItem()">+ Add Item</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="40%">Product</th>
                                    <th width="15%">Quantity</th>
                                    <th width="20%">Unit Price *</th>
                                    <th width="15%">Tax Rate</th>
                                    <th width="10%">Total</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php 
                                $itemsData = $isEdit ? $items : [];
                                if (empty($itemsData)) {
                                    $itemsData = [['product_id' => '', 'quantity' => 1, 'unit_price' => '', 'tax_rate_id' => '', 'description' => '']];
                                }
                                ?>
                                <?php foreach ($itemsData as $index => $item): ?>
                                    <tr class="item-row">
                                        <td>
                                            <input type="text" class="form-control product-search" 
                                                   placeholder="Search product..." 
                                                   value="<?= $item['product_id'] ? getProductCode($item['product_id'], $db) : '' ?>"
                                                   data-index="<?= $index ?>">
                                            <input type="hidden" name="items[<?= $index ?>][product_id]" 
                                                   class="product_id" value="<?= $item['product_id'] ?>">
                                            <input type="hidden" name="items[<?= $index ?>][item_type]" 
                                                   class="item_type" value="<?= $item['item_type'] ?? 'single' ?>">
                                            <input type="hidden" name="items[<?= $index ?>][description]" 
                                                   class="description" value="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="items[<?= $index ?>][quantity]" 
                                                   class="form-control quantity" value="<?= $item['quantity'] ?>" required min="0.001">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="items[<?= $index ?>][unit_price]" 
                                                   class="form-control unit_price" value="<?= $item['unit_price'] ?>" required min="0">
                                        </td>
                                        <td>
                                            <select name="items[<?= $index ?>][tax_rate_id]" class="form-control tax_rate_id">
                                                <option value="">No Tax</option>
                                                <?php foreach ($taxRates as $tr): ?>
                                                    <option value="<?= $tr['id'] ?>" 
                                                            <?= ($item['tax_rate_id'] ?? '') == $tr['id'] ? 'selected' : '' ?>>
                                                        <?= $tr['name'] ?> (<?= $tr['rate'] ?>%)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <span class="line-total"><?= $helpers->formatCurrency($item['quantity'] * $item['unit_price']) ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="removeLine(this)">×</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="text-muted">
                                        <small>* Unit price is REQUIRED for all items</small>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar - Totals -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Totals</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Discount</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="discount" id="discount" 
                                   class="form-control" value="<?= $isEdit ? $bill['discount'] : 0 ?>" min="0">
                            <select name="discount_type" id="discount_type" class="form-select" style="max-width: 100px;">
                                <option value="percent" <?= ($isEdit && $bill['discount_type'] === 'percent') ? 'selected' : '' ?>>%</option>
                                <option value="flat" <?= ($isEdit && $bill['discount_type'] === 'flat') ? 'selected' : '' ?>>Flat</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="subtotal"><?= $helpers->formatCurrency(0) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount:</span>
                        <strong id="discountAmount" class="text-success">- <?= $helpers->formatCurrency(0) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax:</span>
                        <strong id="taxTotal"><?= $helpers->formatCurrency(0) ?></strong>
                    </div>
                    
                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="h5">Total:</span>
                        <strong class="h5 text-primary" id="grandTotal"><?= $helpers->formatCurrency(0) ?></strong>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        <?= $isEdit ? 'Update Bill' : 'Create Bill' ?>
                    </button>
                </div>
            </div>

            <!-- Recurring Billing -->
            <div class="card">
                <div class="card-header">
                    <h5>Recurring Billing</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Repeat Interval</label>
                        <select name="repeat_interval" id="repeat_interval" class="form-control">
                            <option value="none" <?= ($isEdit && $bill['repeat_interval'] === 'none') ? 'selected' : '' ?>>None</option>
                            <option value="weekly" <?= ($isEdit && $bill['repeat_interval'] === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= ($isEdit && $bill['repeat_interval'] === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="quarterly" <?= ($isEdit && $bill['repeat_interval'] === 'quarterly') ? 'selected' : '' ?>>Quarterly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Next Date</label>
                        <input type="date" name="repeat_next_date" id="repeat_next_date" class="form-control"
                               value="<?= $isEdit && $bill['repeat_next_date'] ? $bill['repeat_next_date'] : '' ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const customers = <?= $customerJson ?>;
const taxRates = <?= $taxRatesJson ?>;
let itemIndex = <?= count($itemsData) ?>;

// Auto-complete product search
document.querySelectorAll('.product-search').forEach(input => {
    input.addEventListener('input', function() {
        const query = this.value;
        const index = this.dataset.index;
        
        if (query.length < 2) return;
        
        fetch(`../../api/products.php?search=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(products => {
                if (products.length > 0) {
                    // Auto-select first match
                    selectProduct(index, products[0]);
                }
            });
    });
});

function selectProduct(index, product) {
    const row = document.querySelector(`.item-row:nth-child(${index + 1})`);
    row.querySelector('.product_id').value = product.id;
    row.querySelector('.product-search').value = `${product.code} - ${product.name}`;
    row.querySelector('.item_type').value = product.product_type || 'single';
    row.querySelector('.description').value = product.name;
    
    // Set default price if available
    if (!row.querySelector('.unit_price').value && product.sale_price) {
        row.querySelector('.unit_price').value = product.sale_price;
        calculateTotals();
    }
}

function addLineItem() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td>
            <input type="text" class="form-control product-search" placeholder="Search product..." data-index="${itemIndex}">
            <input type="hidden" name="items[${itemIndex}][product_id]" class="product_id" value="">
            <input type="hidden" name="items[${itemIndex}][item_type]" class="item_type" value="single">
            <input type="hidden" name="items[${itemIndex}][description]" class="description" value="">
        </td>
        <td>
            <input type="number" step="0.001" name="items[${itemIndex}][quantity]" class="form-control quantity" value="1" required min="0.001">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" class="form-control unit_price" value="" required min="0">
        </td>
        <td>
            <select name="items[${itemIndex}][tax_rate_id]" class="form-control tax_rate_id">
                <option value="">No Tax</option>
                ${taxRates.map(tr => `<option value="${tr.id}">${tr.name} (${tr.rate}%)</option>`).join('')}
            </select>
        </td>
        <td><span class="line-total"><?= $helpers->formatCurrency(0) ?></span></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeLine(this)">×</button></td>
    `;
    tbody.appendChild(row);
    
    // Add event listener to new product search
    row.querySelector('.product-search').addEventListener('input', function() {
        const query = this.value;
        if (query.length < 2) return;
        
        fetch(`../../api/products.php?search=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(products => {
                if (products.length > 0) {
                    selectProduct(itemIndex, products[0]);
                }
            });
    });
    
    itemIndex++;
}

function removeLine(btn) {
    btn.closest('tr').remove();
    calculateTotals();
}

// Calculate totals
function calculateTotals() {
    let subtotal = 0;
    let taxTotal = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.quantity').value) || 0;
        const price = parseFloat(row.querySelector('.unit_price').value) || 0;
        const taxRateId = row.querySelector('.tax_rate_id').value;
        
        const lineTotal = qty * price;
        subtotal += lineTotal;
        
        if (taxRateId) {
            const taxRate = taxRates.find(t => t.id == taxRateId);
            if (taxRate) {
                taxTotal += lineTotal * (taxRate.rate / 100);
            }
        }
        
        row.querySelector('.line-total').textContent = formatCurrency(lineTotal);
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const discountType = document.getElementById('discount_type').value;
    const discountAmount = discountType === 'percent' ? (subtotal * discount / 100) : discount;
    
    const total = subtotal - discountAmount + taxTotal;
    
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('discountAmount').textContent = '- ' + formatCurrency(discountAmount);
    document.getElementById('taxTotal').textContent = formatCurrency(taxTotal);
    document.getElementById('grandTotal').textContent = formatCurrency(total);
}

function formatCurrency(amount) {
    return '৳' + amount.toFixed(2);
}

// Event listeners
document.getElementById('discount').addEventListener('input', calculateTotals);
document.getElementById('discount_type').addEventListener('change', calculateTotals);

// Add event listeners to existing inputs
document.querySelectorAll('.quantity, .unit_price, .tax_rate_id').forEach(input => {
    input.addEventListener('input', calculateTotals);
});

// Load from Stock Out
<?php if (!$isEdit): ?>
document.getElementById('stockOutSelect').addEventListener('change', function() {
    const stockOutId = this.value;
    if (!stockOutId) return;
    
    fetch(`?module=billing&action=createFromStockOut&stock_out_id=${stockOutId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Populate customer
                document.getElementById('customer_id').value = data.stock_out.customer_id;
                
                // Clear existing items
                document.getElementById('itemsBody').innerHTML = '';
                itemIndex = 0;
                
                // Add items from stock out
                data.items.forEach(item => {
                    addLineItem();
                    const row = document.querySelector(`.item-row:last-child`);
                    row.querySelector('.product_id').value = item.product_id;
                    row.querySelector('.product-search').value = `${item.code} - ${item.name}`;
                    row.querySelector('.item_type').value = item.item_type;
                    row.querySelector('.description').value = item.name;
                    row.querySelector('.quantity').value = item.quantity;
                    // Note: unit_price left empty for manual entry
                });
                
                calculateTotals();
            }
        });
});

// Load recent stock outs
fetch(`../../api/stock-out-recent.php`)
    .then(r => r.json())
    .then(stockOuts => {
        const select = document.getElementById('stockOutSelect');
        stockOuts.forEach(so => {
            const option = document.createElement('option');
            option.value = so.id;
            option.textContent = `${so.invoice_no} - ${so.customer_name}`;
            select.appendChild(option);
        });
    });
<?php endif; ?>

// Initial calculation
calculateTotals();
</script>

<?php
function getProductCode($productId, $db) {
    $stmt = $db->prepare("SELECT code FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    return $product ? $product['code'] : '';
}
?>
