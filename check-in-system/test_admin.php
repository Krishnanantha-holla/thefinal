<?php
require_once 'config/database.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@example.com']);
$user = $stmt->fetch();

if ($user) {
    echo "Admin user exists:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
} else {
    echo "Admin user does not exist. Creating admin user...\n";
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        'Admin',
        'admin@example.com',
        password_hash('admin123', PASSWORD_DEFAULT),
        'admin'
    ]);
    echo "Admin user created successfully!\n";
}
?>
