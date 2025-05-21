<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get date from request
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

try {
    // Get attendance records for the specified date
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    
    if ($is_admin) {
        // Admins see all attendance records
        $stmt = $pdo->prepare("
            SELECT a.id, a.user_id, u.name, a.type, a.timestamp, TIME_FORMAT(a.timestamp, '%H:%i') as time
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE DATE(a.timestamp) = ?
            ORDER BY a.timestamp ASC
        ");
        $stmt->execute([$date]);
    } else {
        // Regular users only see their own attendance records
        $stmt = $pdo->prepare("
            SELECT a.id, a.user_id, u.name, a.type, a.timestamp, TIME_FORMAT(a.timestamp, '%H:%i') as time
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE DATE(a.timestamp) = ? AND a.user_id = ?
            ORDER BY a.timestamp ASC
        ");
        $stmt->execute([$date, $_SESSION['user_id']]);
    }
    
    // Fetch all records
    $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process records to calculate time spent
    $records = [];
    $userCheckIns = [];
    
    foreach ($rawRecords as $record) {
        // Add the record to the output array
        $records[] = $record;
        
        $userId = $record['user_id'];
        $recordType = $record['type'];
        $timestamp = strtotime($record['timestamp']);
        
        // If this is a check-out and we have a previous check-in for this user
        if ($recordType === 'check_out' && isset($userCheckIns[$userId])) {
            $checkInTime = $userCheckIns[$userId];
            $timeSpent = $timestamp - $checkInTime;
            
            // Format time spent in hours and minutes
            $hours = floor($timeSpent / 3600);
            $minutes = floor(($timeSpent % 3600) / 60);
            
            // Add time spent to the current record
            $records[count($records) - 1]['time_spent'] = sprintf('%02d:%02d', $hours, $minutes);
            
            // Clear the check-in record as it's been processed
            unset($userCheckIns[$userId]);
        } 
        // If this is a check-in, store it for later processing
        elseif ($recordType === 'check_in') {
            $userCheckIns[$userId] = $timestamp;
        }
    }
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode($records);
    
} catch (PDOException $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
