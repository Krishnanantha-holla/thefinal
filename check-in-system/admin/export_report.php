<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

// Create file pointer connected to PHP output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers to CSV
fputcsv($output, array('User Name', 'Email', 'Action', 'Date', 'Time', 'Time Spent (HH:MM)', 'Overtime'));

// Get all attendance records with user details
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        u.email,
        u.id as user_id,
        a.type,
        a.timestamp
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.timestamp DESC
");

$stmt->execute();

// Add data to CSV
while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Initialize time spent and overtime values
    $timeSpent = '-';
    $overtime = 'No';
    
    // Calculate time spent for check-out records
    if ($record['type'] === 'check_out') {
        // Look for the most recent check-in before this check-out
        $checkInStmt = $pdo->prepare("
            SELECT timestamp FROM attendance 
            WHERE user_id = ? AND type = 'check_in' AND timestamp < ? 
            ORDER BY timestamp DESC LIMIT 1
        ");
        $checkInStmt->execute([$record['user_id'], $record['timestamp']]);
        $checkInRecord = $checkInStmt->fetch();
        
        if ($checkInRecord) {
            $checkInTime = strtotime($checkInRecord['timestamp']);
            $checkOutTime = strtotime($record['timestamp']);
            $timeSpentSeconds = $checkOutTime - $checkInTime;
            
            // Format time spent in hours and minutes
            $hours = floor($timeSpentSeconds / 3600);
            $minutes = floor(($timeSpentSeconds % 3600) / 60);
            $timeSpent = sprintf('%02d:%02d', $hours, $minutes);
            
            // Check if time spent is more than 9 hours (overtime)
            if ($hours > 9 || ($hours == 9 && $minutes > 0)) {
                $overtime = 'Yes';
            }
        }
    }
    
    $row = array(
        $record['name'],
        $record['email'],
        $record['type'] === 'check_in' ? 'Checked In' : 'Checked Out',
        date('Y-m-d', strtotime($record['timestamp'])),
        date('H:i:s', strtotime($record['timestamp'])),
        $timeSpent,
        $overtime
    );
    fputcsv($output, $row);
}

// Close file pointer
fclose($output);
exit();
?>
