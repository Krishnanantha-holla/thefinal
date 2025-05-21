<?php
require_once 'config/database.php';

// Check if admin user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@example.com']);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Admin User Check</h2>";

if ($admin) {
    echo "<p>Admin user found with ID: " . $admin['id'] . "</p>";
    echo "<p>Admin name: " . $admin['name'] . "</p>";
    echo "<p>Admin role: " . $admin['role'] . "</p>";
    echo "<p>Admin password hash: " . $admin['password'] . "</p>";
    
    // Check if the default password works
    $testPassword = 'admin123';
    $passwordVerified = password_verify($testPassword, $admin['password']);
    
    echo "<p>Testing password 'admin123': " . ($passwordVerified ? 'VERIFIED' : 'FAILED') . "</p>";
    
    // Generate a new hash for comparison
    $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
    echo "<p>New hash generated for 'admin123': " . $newHash . "</p>";
    
    // Show the expected hash from setup.sql
    $expectedHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    echo "<p>Expected hash from setup.sql: " . $expectedHash . "</p>";
    echo "<p>Does stored hash match expected hash? " . ($admin['password'] === $expectedHash ? 'YES' : 'NO') . "</p>";
} else {
    echo "<p>Admin user not found in database!</p>";
    
    // Check if any users exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Total users in database: " . $userCount['count'] . "</p>";
}

// Show database info
$testQuery = $pdo->query("SELECT DATABASE() as db");
$dbInfo = $testQuery->fetch(PDO::FETCH_ASSOC);
echo "<p>Connected to database: " . $dbInfo['db'] . "</p>";
?>
