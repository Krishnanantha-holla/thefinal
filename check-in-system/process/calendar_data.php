<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get month and year from request
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date parameters']);
    exit();
}

try {
    // Get check-in counts for each day of the month
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    
    if ($is_admin) {
        // Admins see all check-ins
        $stmt = $pdo->prepare("
            SELECT DAY(timestamp) as day, COUNT(*) as count 
            FROM attendance 
            WHERE MONTH(timestamp) = ? AND YEAR(timestamp) = ? AND type = 'check_in'
            GROUP BY DAY(timestamp)
        ");
        $stmt->execute([$month, $year]);
    } else {
        // Regular users only see their own check-ins
        $stmt = $pdo->prepare("
            SELECT DAY(timestamp) as day, COUNT(*) as count 
            FROM attendance 
            WHERE MONTH(timestamp) = ? AND YEAR(timestamp) = ? AND user_id = ? AND type = 'check_in'
            GROUP BY DAY(timestamp)
        ");
        $stmt->execute([$month, $year, $_SESSION['user_id']]);
    }
    
    // Create an array with day => count mapping
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[(int)$row['day']] = (int)$row['count'];
    }
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
