<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You must be logged in as an administrator to perform this action.";
    header("Location: ../index.php");
    exit();
}

// Handle announcement submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['title']) || empty($_POST['content'])) {
        $_SESSION['error'] = "Title and content are required.";
        header("Location: ../dashboard.php");
        exit();
    }
    
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    try {
        // Create announcements table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                active TINYINT(1) DEFAULT 1
            )
        ");
        
        // Insert the announcement
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, created_by) 
            VALUES (?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $title,
            $content,
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            $_SESSION['success'] = "Announcement added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add announcement.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: ../dashboard.php");
    exit();
}

// Handle announcement deletion (if ID is provided in GET request)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $_SESSION['success'] = "Announcement deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete announcement.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: ../dashboard.php");
    exit();
}

// If no valid action was performed, redirect back to dashboard
header("Location: ../dashboard.php");
exit();
?>
