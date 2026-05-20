<?php
/**
 * Database Setup Script
 * Run this once to create tables and seed initial data
 */

require_once __DIR__ . '/config/db.php';

echo "🚀 ERP System Database Setup\n";
echo "============================\n\n";

try {
    $pdo = Database::getInstance();
    
    echo "✓ Database connection successful\n\n";
    
    // Read and execute SQL schema
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Split by semicolons (simple approach, works for our schema)
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "Executing schema...\n";
    $count = 0;
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        $pdo->exec($statement);
        $count++;
    }
    echo "✓ Executed $count SQL statements\n\n";
    
    // Seed initial data
    echo "Seeding initial data...\n";
    
    // Default admin user (password: admin123)
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) 
                          VALUES ('Admin User', 'admin@example.com', ?, 'admin', 1)");
    $stmt->execute([$passwordHash]);
    echo "✓ Created admin user (admin@example.com / admin123)\n";
    
    // Default tax rate
    $stmt = $pdo->query("INSERT INTO tax_rates (name, rate, is_default, status) VALUES ('VAT 15%', 15.00, 1, 1)");
    echo "✓ Created default tax rate (VAT 15%)\n";
    
    // Default units
    $stmt = $pdo->query("INSERT INTO units (name, short_name) VALUES ('Piece', 'pcs'), ('Pair', 'pair'), ('Box', 'box'), ('Kg', 'kg')");
    echo "✓ Created default units\n";
    
    // Default categories
    $stmt = $pdo->query("INSERT INTO categories (name, type) VALUES ('Electronics', 'product'), ('Accessories', 'product'), ('Services', 'service')");
    echo "✓ Created default categories\n";
    
    // Default settings
    $stmt = $pdo->query("INSERT INTO settings (`key_name`, `key_value`) VALUES 
                        ('company_name', 'My Company'),
                        ('currency', 'USD'),
                        ('invoice_prefix', 'INV-'),
                        ('quote_prefix', 'QT-'),
                        ('timezone', 'UTC')");
    echo "✓ Created default settings\n";
    
    echo "\n✅ Setup complete!\n\n";
    echo "You can now login at: /login.php\n";
    echo "Default credentials: admin@example.com / admin123\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
