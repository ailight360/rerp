<?php include __DIR__ . '/../../../layout.php'; ?>

<div class="page-header">
    <h1>Company Settings</h1>
    <?php if (isset($_GET['success'])): ?>
        <span class="badge badge-success">Settings saved successfully!</span>
    <?php endif; ?>
</div>

<form method="POST" enctype="multipart/form-data" class="card">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Company Name *</label>
                <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" class="form-control" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Tax Number / VAT ID</label>
                <input type="text" name="tax_number" value="<?= htmlspecialchars($settings['tax_number'] ?? '') ?>" class="form-control">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Address</label>
        <textarea name="company_address" rows="3" class="form-control"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="company_phone" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>" class="form-control">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>" class="form-control">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" value="<?= htmlspecialchars($settings['currency'] ?? 'USD') ?>" class="form-control" placeholder="$">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Timezone</label>
                <select name="timezone" class="form-control">
                    <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                    <option value="Asia/Dubai" <?= ($settings['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' ?>>Gulf Time (GST)</option>
                    <option value="Asia/Kolkata" <?= ($settings['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : '' ?>>India (IST)</option>
                    <option value="Europe/London" <?= ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London (GMT)</option>
                    <option value="America/New_York" <?= ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>New York (EST)</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Company Logo</label>
        <?php if (!empty($settings['company_logo'])): ?>
            <div class="mb-2">
                <img src="<?= $settings['company_logo'] ?>" alt="Logo" style="max-height: 80px;">
            </div>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/*" class="form-control">
        <small class="text-muted">PNG, JPG, or GIF. Max 2MB.</small>
    </div>

    <div class="form-actions mt-4">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="?module=settings&action=invoicing" class="btn btn-outline">Invoice Prefixes →</a>
    </div>
</form>
