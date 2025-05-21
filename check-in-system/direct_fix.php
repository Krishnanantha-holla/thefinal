<?php
require_once 'config/database.php';

echo "<h2>Direct Admin Password Fix</h2>";

try {
    // Generate a proper password hash
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update admin password directly
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $result = $updateStmt->execute([$hash, 'admin@example.com']);
    
    if ($result) {
        echo "<p>Admin password directly updated!</p>";
        echo "<p>New password hash: " . $hash . "</p>";
        echo "<p>Hash length: " . strlen($hash) . " characters</p>";
        
        // Verify the update worked
        $verifyStmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $verifyStmt->execute(['admin@example.com']);
        $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<p>Admin user found with ID: " . $admin['id'] . "</p>";
            echo "<p>Stored hash length: " . strlen($admin['password']) . " characters</p>";
            echo "<p>Stored hash: " . $admin['password'] . "</p>";
            
            // Test verification
            $passwordVerified = password_verify($password, $admin['password']);
            echo "<p>Password verification test: " . ($passwordVerified ? 'SUCCESS' : 'FAILED') . "</p>";
        } else {
            echo "<p>Could not find admin user after update!</p>";
        }
    } else {
        echo "<p>Failed to update admin password.</p>";
    }
    
    echo "<p><a href='index.php'>Return to login page</a></p>";
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>
