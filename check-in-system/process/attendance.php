<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['action'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'];
$current_time = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, type, timestamp) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $current_time]);
    
    $_SESSION['success'] = ucfirst($action) . " successful!";
} catch(PDOException $e) {
    $_SESSION['error'] = "Error processing your request. Please try again.";
}

header("Location: ../dashboard.php");
exit();
?>
