<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get attendance reports
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        a.type,
        a.timestamp,
        u.id as user_id
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.timestamp DESC
    LIMIT 50
");
$stmt->execute();
$records = $stmt->fetchAll();

// Get theme setting
$theme = 'light'; // Default to light theme
try {
    // First check user preferences
    $themeStmt = $pdo->prepare("SELECT theme FROM user_preferences WHERE user_id = ? LIMIT 1");
    $themeStmt->execute([$_SESSION['user_id']]);
    $userTheme = $themeStmt->fetchColumn();
    
    if ($userTheme) {
        $theme = $userTheme;
    } else {
        // Fall back to system settings
        $themeStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_theme' LIMIT 1");
        $themeStmt->execute();
        $theme = $themeStmt->fetchColumn() ?: 'light';
    }
} catch (PDOException $e) {
    // If error, use light theme as default
    $theme = 'light';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Check-In System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Common styles for both themes */
        .dashboard-container {
            min-height: 100vh;
        }

        /* Navigation */
        .dashboard-nav {
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-nav h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-admin {
            background-color: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-admin:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
        }

        .btn-admin i {
            font-size: 1.1rem;
        }

        /* Reports Section */
        .reports-section {
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Light Theme Styles (default) */
        body {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .dashboard-nav {
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
        }
        
        .dashboard-nav h2 {
            color: #212529;
        }
        
        /* Dark Theme Styles */
        body.theme-dark {
            background-color: #0a0a0a;
            color: #ffffff;
        }

        body.theme-dark .dashboard-container {
            background-color: #161b22;
        }

        body.theme-dark .dashboard-nav {
            background-color: #161b22;
            border-bottom: 1px solid #30363d;
        }

        body.theme-dark .dashboard-nav h2 {
            color: #c9d1d9;
        }

        body.theme-dark .btn-admin {
            background-color: #238636;
        }

        body.theme-dark .btn-admin:hover {
            background-color: #2ea043;
        }

        body.theme-dark .reports-section {
            background-color: #1f2937;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .reports-header h3 {
            margin: 0;
            color: #212529;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
        }

        /* Reports Table */
        .reports-table {
            width: 100%;
            overflow-x: auto;
        }

        .reports-table table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
        }

        .reports-table th,
        .reports-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .reports-table th {
            color: #495057;
            font-weight: 600;
        }

        .reports-table td {
            color: #212529;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }

        .status-badge.check_in {
            background-color: #28a745;
            color: white;
        }

        .status-badge.check_out {
            background-color: #dc3545;
            color: white;
        }

        .status-badge i {
            font-size: 1rem;
        }
        
        /* Dark Theme Overrides for Reports */
        body.theme-dark .reports-header h3 {
            color: #c9d1d9;
        }
        
        body.theme-dark .reports-table table {
            background-color: #1f2937;
        }
        
        body.theme-dark .reports-table th,
        body.theme-dark .reports-table td {
            border-bottom: 1px solid #30363d;
        }
        
        body.theme-dark .reports-table th {
            color: #c9d1d9;
        }
        
        body.theme-dark .reports-table td {
            color: #c9d1d9;
        }
        
        body.theme-dark .status-badge.check_in {
            background-color: #238636;
        }
        
        body.theme-dark .status-badge.check_out {
            background-color: #da3633;
        }
        
        /* Overtime styling */
        .overtime {
            color: #da3633;
            font-weight: bold;
            font-family: monospace;
        }
        
        /* Time spent column styling */
        .reports-table td:last-child {
            font-family: monospace;
            text-align: center;
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2><i class="fas fa-chart-bar"></i> Attendance Reports</h2>
            <div class="nav-actions">
                <a href="../dashboard.php" class="btn-admin"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </nav>

        <div class="dashboard-content">
            <div class="reports-section">
                <div class="reports-header">
                    <h3>Recent Activity</h3>
                    <div class="filter-actions">
                        <a href="export_report.php" class="btn-admin">
                            <i class="fas fa-download"></i> Export Report
                        </a>
                    </div>
                </div>

                <div class="reports-table">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Timestamp</th>
                                <th>Time Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <td>
                                    <?php echo '<span class="status-badge ' . $record['type'] . '">' . 
                                         '<i class="fas fa-' . ($record['type'] === 'check_in' ? 'sign-in-alt' : 'sign-out-alt') . '"></i>' .
                                         ($record['type'] === 'check_in' ? 'Checked In' : 'Checked Out') . '</span>'; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($record['timestamp'])); ?></td>
                                <td>
                                    <?php
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
                                            $timeSpent = $checkOutTime - $checkInTime;
                                            
                                            // Format time spent in hours and minutes
                                            $hours = floor($timeSpent / 3600);
                                            $minutes = floor(($timeSpent % 3600) / 60);
                                            $timeSpentFormatted = sprintf('%02d:%02d', $hours, $minutes);
                                            
                                            // Check if time spent is more than 9 hours (overtime)
                                            $isOvertime = ($hours > 9 || ($hours == 9 && $minutes > 0));
                                            
                                            if ($isOvertime) {
                                                echo '<span class="overtime">' . $timeSpentFormatted . '</span>';
                                            } else {
                                                echo $timeSpentFormatted;
                                            }
                                        } else {
                                            echo '-';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
