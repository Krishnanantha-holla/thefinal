<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if theme is provided
if (!isset($_POST['theme'])) {
    echo json_encode(['success' => false, 'message' => 'Theme not provided']);
    exit();
}

// Validate theme
$theme = $_POST['theme'];
$validThemes = ['light', 'dark', 'blue', 'green'];
if (!in_array($theme, $validThemes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    exit();
}

try {
    // Create user_preferences table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            user_id INT PRIMARY KEY,
            theme VARCHAR(20) NOT NULL DEFAULT 'light',
            notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Check if user already has preferences
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $exists = $stmt->fetchColumn() > 0;
    
    if ($exists) {
        // Update existing preferences
        $stmt = $pdo->prepare("UPDATE user_preferences SET theme = ? WHERE user_id = ?");
        $stmt->execute([$theme, $_SESSION['user_id']]);
    } else {
        // Insert new preferences
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $theme]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
