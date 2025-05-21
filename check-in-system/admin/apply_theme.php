<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if theme was provided
if (!isset($_POST['theme'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No theme specified']);
    exit();
}

// Validate theme
$theme = $_POST['theme'];
if (!in_array($theme, ['light', 'dark'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    exit();
}

try {
    // Create settings table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Update theme setting
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by) 
        VALUES ('system_theme', ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?
    ");
    $stmt->execute([$theme, $_SESSION['user_id'], $theme, $_SESSION['user_id']]);
    
    // Return success
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Theme updated successfully']);
    
} catch (PDOException $e) {
    // Return error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
