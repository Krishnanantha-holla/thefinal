<?php
require_once 'config/database.php';

// First, check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@example.com']);
$admin = $stmt->fetch();

if ($admin) {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([
        password_hash('admin123', PASSWORD_DEFAULT),
        'admin@example.com'
    ]);
    echo "Admin password has been reset successfully!\n";
    echo "Email: admin@example.com\n";
    echo "Password: admin123\n";
} else {
    // Create new admin user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        'Admin',
        'admin@example.com',
        password_hash('admin123', PASSWORD_DEFAULT),
        'admin'
    ]);
    echo "Admin user created successfully!\n";
    echo "Email: admin@example.com\n";
    echo "Password: admin123\n";
}

// Verify the admin account
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@example.com']);
$admin = $stmt->fetch();

echo "\nVerifying admin account:\n";
echo "ID: " . $admin['id'] . "\n";
echo "Name: " . $admin['name'] . "\n";
echo "Email: " . $admin['email'] . "\n";
echo "Role: " . $admin['role'] . "\n";
?>
