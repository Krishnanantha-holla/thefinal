<?php
// Suppress deprecation warnings about type conversion
error_reporting(E_ALL & ~E_DEPRECATED);

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Get user's latest check-in/out status
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$latest_record = $stmt->fetch();

$is_checked_in = $latest_record && $latest_record['type'] == 'check_in';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Check-In System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/dark-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Add jQuery and Chart.js with secure HTTPS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js" integrity="sha256-ErZ09KkZnzjpqcane4SCyyHsKAXMvID9/xwbl/Aq1pc=" crossorigin="anonymous"></script>
    <?php
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
    <style>
        /* Dark Theme Styles */
        body.theme-dark {
            background-color: #0a0a0a;
            color: #ffffff;
        }

        /* Sidebar styles */
        .dashboard-layout {
            display: flex;
            width: 100%;
        }
        
        .sidebar {
            width: 250px;
            background-color: #161b22;
            border-right: 1px solid #30363d;
            padding: 20px;
            min-height: calc(100vh - 70px);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-section h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #c9d1d9;
            border-bottom: 1px solid #30363d;
            padding-bottom: 8px;
        }
        
        .quick-actions a {
            display: block;
            padding: 8px 10px;
            margin-bottom: 8px;
            background-color: #1f2937;
            border-radius: 4px;
            color: #c9d1d9;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #30363d;
        }
        
        .quick-actions a:hover {
            background-color: #30363d;
            transform: translateX(5px);
        }
        
        .quick-actions a i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .calendar {
            background-color: #1f2937;
            border-radius: 4px;
            padding: 10px;
            border: 1px solid #30363d;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calendar-nav-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #c9d1d9;
            padding: 5px;
            border-radius: 4px;
        }
        
        .calendar-nav-btn:hover {
            background-color: #30363d;
            color: #ffffff;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day {
            text-align: center;
            padding: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #c9d1d9;
        }
        
        .calendar-date {
            text-align: center;
            padding: 5px 2px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            min-height: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 1px solid transparent;
            background-color: #1f2937;
            color: #c9d1d9;
        }
        
        .calendar-date:hover {
            background-color: #30363d;
            border-color: #404854;
        }
        
        .calendar-date.current-day {
            background-color: #238636;
            color: white;
            font-weight: bold;
            border-color: #238636;
            box-shadow: 0 0 5px rgba(35, 134, 54, 0.5);
        }
        
        .day-number {
            font-size: 12px;
            line-height: 1;
        }
        
        .check-in-count {
            font-size: 9px;
            background-color: #238636;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 2px;
            right: 2px;
        }

        /* Add theme class to body */
        <script>document.body.classList.add('theme-<?php echo htmlspecialchars($theme); ?>');</script>
    <style>
        /* Sidebar styles */
        .dashboard-layout {
            display: flex;
            width: 100%;
        }
        
        .sidebar {
            width: 250px;
            background-color: #f8f9fa;
            border-right: 1px solid #e9ecef;
            padding: 20px;
            min-height: calc(100vh - 70px);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-section h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .quick-actions a {
            display: block;
            padding: 8px 10px;
            margin-bottom: 8px;
            background-color: #fff;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }
        
        .quick-actions a:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .quick-actions a i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .calendar {
            background-color: #fff;
            border-radius: 4px;
            padding: 10px;
            border: 1px solid #e9ecef;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calendar-nav-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            padding: 5px;
            border-radius: 4px;
        }
        
        .calendar-nav-btn:hover {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day {
            text-align: center;
            padding: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #495057;
        }
        
        .calendar-date {
            text-align: center;
            padding: 5px 2px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            min-height: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 1px solid transparent;
        }
        
        .calendar-date:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        
        .calendar-date.has-event .event-indicator {
            background-color: #0d6efd;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: block;
            margin: 2px auto 0;
        }
        
        .calendar-date.current-day {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
            border-color: #0d6efd;
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
        }
        
        .day-number {
            font-size: 12px;
            line-height: 1;
        }
        
        .check-in-count {
            font-size: 9px;
            background-color: #28a745;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 2px;
            right: 2px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 0;
            border: 1px solid #888;
            width: 400px;
            max-width: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover,
        .close-modal:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-cancel {
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-confirm {
            padding: 8px 16px;
            background-color: #238636;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-cancel:hover {
            background-color: #bd2130;
            transform: translateY(-2px);
        }
        
        .btn-confirm:hover {
            background-color: #1f8636;
            transform: translateY(-2px);
        }
        
        /* Dark theme support */
        .theme-dark .modal-content {
            background-color: #343a40;
            color: #f8f9fa;
            border-color: #495057;
        }
        
        .theme-dark .modal-header,
        .theme-dark .modal-footer {
            border-color: #495057;
        }
        
        .theme-dark .close-modal {
            color: #f8f9fa;
        }
        
        .theme-dark .close-modal:hover,
        .theme-dark .close-modal:focus {
            color: #fff;
        }
        
        /* Date Details Modal */
        .date-details-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .date-details-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
            position: relative;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 15px;
        }
        
        .close-modal:hover {
            color: black;
        }
        
        #date-details-title {
            margin-top: 0;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .attendance-record {
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .attendance-record:nth-child(odd) {
            background-color: #f8f9fa;
        }
        
        .attendance-record.check-in {
            border-left: 3px solid #28a745;
        }
        
        .attendance-record.check-out {
            border-left: 3px solid #dc3545;
            background-color: #2c2c2c;
        }

        .attendance-record.check-out:hover {
            background-color: #3c3c3c;
        }
        
        .announcement {
            background-color: #fff;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        
        .announcement h4 {
            margin-top: 0;
            font-size: 14px;
            color: #495057;
        }
        
        .announcement p {
            margin-bottom: 0;
            font-size: 13px;
            color: #6c757d;
        }
        
        .weekly-summary {
            background-color: #fff;
            border-radius: 4px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-label {
            color: #6c757d;
            font-size: 13px;
        }
        
        .summary-value {
            font-weight: bold;
            color: #495057;
        }
        
        .no-announcements {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 15px 0;
        }
        
        #announcements-container {
            max-height: 400px; /* Set a maximum height for the container */
            overflow-y: auto; /* Enable vertical scrolling */
            padding: 5px;
            margin-bottom: 10px;
        }
        
        .announcement {
            position: relative;
            padding: 12px 25px 12px 15px; /* Space for delete button and left border */
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 3px solid #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .announcement:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }
        
        .announcement h4 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .announcement-content {
            max-height: 120px; /* Set maximum height for content */
            overflow-y: auto; /* Enable vertical scrolling */
            margin-bottom: 8px;
            padding-right: 5px;
            position: relative;
        }
        
        /* Custom scrollbar for webkit browsers */
        .announcement-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .announcement-content::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 3px;
        }
        
        .announcement-content::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        
        .announcement-content::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }
        
        .announcement small {
            display: block;
            color: #6c757d;
            font-size: 12px;
            text-align: right;
        }
        
        .announcement-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
        }
        
        .delete-announcement {
            color: #dc3545;
            font-size: 14px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .delete-announcement:hover {
            opacity: 1;
            color: #dc3545;
        }
        
        .admin-actions {
            margin-top: 10px;
            text-align: right;
        }
        
        .activity-list {
            margin-top: 15px;
        }
        
        /* Time spent styling for light theme */
        .time-spent {
            display: block;
            width: 100%;
            margin-top: 5px;
            margin-left: 25px;
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
            text-align: right;
        }
        
        /* Time spent styling for dark theme */
        body.theme-dark .time-spent {
            color: #a0a0a0;
        }
        
        body.theme-dark .activity-item {
            background-color: #1f2937;
            border-left: 3px solid #30363d;
        }
        
        body.theme-dark .activity-item.check-in {
            border-left-color: #238636;
        }
        
        body.theme-dark .activity-item.check-out {
            border-left-color: #f85149;
        }
        
        /* Admin dashboard table styling */
        .activity-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .activity-table th, 
        .activity-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        /* Time spent column styling */
        .activity-table td:last-child {
            font-family: monospace;
            text-align: center;
        }
        
        /* Overtime styling */
        .overtime {
            color: #dc3545 !important;
            font-weight: bold;
        }
        
        body.theme-dark .overtime {
            color: #f85149 !important;
        }
        
        /* Dark theme styling for admin table */
        body.theme-dark .activity-table th, 
        body.theme-dark .activity-table td {
            border-bottom: 1px solid #30363d;
            color: #c9d1d9;
        }
        
        body.theme-dark .activity-table table {
            background-color: #1f2937;
        }
        
        .btn-sm {
            display: inline-block;
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .btn-sm:hover {
            background-color: #0069d9;
        }
        
        .announcement small {
            display: block;
            color: #6c757d;
            font-size: 11px;
            margin-top: 5px;
            text-align: right;
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2><i class="fas fa-clock"></i> Check-In System</h2>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                <?php if ($is_admin): ?>
                    <small class="admin-badge">Admin</small>
                <?php endif; ?>
                </span>
                <div class="nav-actions">
                    <?php if ($is_admin): ?>
                        <a href="admin/users.php" class="btn-admin"><i class="fas fa-users"></i> Manage Users</a>
                        <a href="admin/reports.php" class="btn-admin"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php endif; ?>
                    <a href="auth/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </nav>

        <div class="dashboard-layout">
            <!-- New Sidebar -->
            <div class="sidebar">
                <!-- Quick Actions Section -->
                <div class="sidebar-section quick-actions">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <?php if ($is_admin): ?>
                        <a href="admin/users.php"><i class="fas fa-users"></i> Manage Users</a>
                        <a href="admin/reports.php"><i class="fas fa-file-export"></i> Generate Report</a>
                        <a href="admin/settings.php"><i class="fas fa-cogs"></i> System Settings</a>
                    <?php else: ?>
                        <a href="profile/settings.php"><i class="fas fa-user-cog"></i> Profile Settings</a>
                    <?php endif; ?>
                </div>
                
                <!-- Calendar Section -->
                <div class="sidebar-section">
                    <h3><i class="fas fa-calendar-alt"></i> Calendar</h3>
                    <div class="calendar">
                        <div class="calendar-header">
                            <button id="prev-month" class="calendar-nav-btn"><i class="fas fa-chevron-left"></i></button>
                            <span id="current-month"><?php echo date('F Y'); ?></span>
                            <button id="next-month" class="calendar-nav-btn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-grid" id="calendar-grid">
                            <div class="calendar-day">Su</div>
                            <div class="calendar-day">Mo</div>
                            <div class="calendar-day">Tu</div>
                            <div class="calendar-day">We</div>
                            <div class="calendar-day">Th</div>
                            <div class="calendar-day">Fr</div>
                            <div class="calendar-day">Sa</div>
                            <?php
                            $currentMonth = date('m');
                            $currentYear = date('Y');
                            $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
                            $daysInMonth = date('t', $firstDay);
                            $dayOfWeek = date('w', $firstDay);
                            
                            // Get check-in counts for each day
                            $dailyCheckIns = [];
                            if ($is_admin) {
                                $stmt = $pdo->prepare("SELECT DAY(timestamp) as day, COUNT(*) as count 
                                    FROM attendance 
                                    WHERE MONTH(timestamp) = ? AND YEAR(timestamp) = ? AND type = 'check_in'
                                    GROUP BY DAY(timestamp)");
                                $stmt->execute([$currentMonth, $currentYear]);
                                while ($row = $stmt->fetch()) {
                                    $dailyCheckIns[$row['day']] = $row['count'];
                                }
                            } else {
                                $stmt = $pdo->prepare("SELECT DAY(timestamp) as day, COUNT(*) as count 
                                    FROM attendance 
                                    WHERE MONTH(timestamp) = ? AND YEAR(timestamp) = ? AND user_id = ? AND type = 'check_in'
                                    GROUP BY DAY(timestamp)");
                                $stmt->execute([$currentMonth, $currentYear, $_SESSION['user_id']]);
                                while ($row = $stmt->fetch()) {
                                    $dailyCheckIns[$row['day']] = $row['count'];
                                }
                            }
                            
                            // Add empty cells for days before the first day of the month
                            for ($i = 0; $i < $dayOfWeek; $i++) {
                                echo '<div class="calendar-date"></div>';
                            }
                            
                            // Current date for highlighting - ensure we use server time
                            $currentDay = (int)date('j');
                            $isCurrentMonth = ((int)date('m') == (int)$currentMonth && (int)date('Y') == (int)$currentYear);
                            error_log("Current day: $currentDay, Current month: " . date('m') . ", Calendar month: $currentMonth");
                            
                            // Add cells for each day of the month
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $class = 'calendar-date';
                                $dataAttr = '';
                                
                                // Highlight current date with blue
                                if ($isCurrentMonth && $day == $currentDay) {
                                    $class .= ' current-day';
                                }
                                
                                // Add check-in count if available
                                $checkInCount = isset($dailyCheckIns[$day]) ? $dailyCheckIns[$day] : 0;
                                
                                // Add data attributes for the date details
                                $fullDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                $dataAttr = ' data-date="' . $fullDate . '" data-count="' . $checkInCount . '"';
                                
                                echo '<div class="' . $class . '"' . $dataAttr . ' onclick="showDateDetails(this)">';
                                echo '<span class="day-number">' . $day . '</span>';
                                if ($checkInCount > 0) {
                                    echo '<span class="check-in-count">' . $checkInCount . '</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Date Details Modal -->
                    <div id="date-details-modal" class="date-details-modal">
                        <div class="date-details-content">
                            <span class="close-modal">&times;</span>
                            <h4 id="date-details-title">Attendance for Date</h4>
                            <div id="date-details-body"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Announcements Section -->
                <div class="sidebar-section">
                    <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
                    <div id="announcements-container">
                        <?php
                        // Get announcements from database
                        try {
                            $stmt = $pdo->query("SELECT * FROM announcements WHERE active = 1 ORDER BY created_at DESC LIMIT 3");
                            $announcements = $stmt->fetchAll();
                            
                            if (count($announcements) > 0) {
                                foreach ($announcements as $announcement) {
                                    echo '<div class="announcement">';
                                    // Add a delete button for admins
                                    if ($is_admin) {
                                        echo '<div class="announcement-actions">';
                                        echo '<a href="process/announcement.php?delete=' . $announcement['id'] . '" class="delete-announcement" title="Delete Announcement"><i class="fas fa-times"></i></a>';
                                        echo '</div>';
                                    }
                                    echo '<h4>' . htmlspecialchars($announcement['title']) . '</h4>';
                                    echo '<div class="announcement-content">' . htmlspecialchars($announcement['content']) . '</div>';
                                    echo '<small>' . date('M d, Y', strtotime($announcement['created_at'])) . '</small>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="no-announcements">No announcements at this time.</p>';
                            }
                        } catch (PDOException $e) {
                            // Table might not exist yet
                            echo '<p class="no-announcements">No announcements at this time.</p>';
                        }
                        ?>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="admin-actions">
                        <a href="#" onclick="showAnnouncementForm()" class="btn-sm"><i class="fas fa-plus"></i> Add Announcement</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Weekly Summary Section (More Practical than Chart) -->
                <div class="sidebar-section">
                    <h3><i class="fas fa-chart-line"></i> Weekly Summary</h3>
                    <div class="weekly-summary">
                        <?php
                        // Get weekly attendance summary
                        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                        
                        try {
                            // Total check-ins this week
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance 
                                WHERE type = 'check_in' AND DATE(timestamp) BETWEEN ? AND ?");
                            $stmt->execute([$startOfWeek, $endOfWeek]);
                            $weeklyCheckins = $stmt->fetch()['count'];
                            
                            // Average check-in time
                            $stmt = $pdo->prepare("SELECT AVG(HOUR(timestamp) * 60 + MINUTE(timestamp)) as avg_time 
                                FROM attendance WHERE type = 'check_in' AND DATE(timestamp) BETWEEN ? AND ?");
                            $stmt->execute([$startOfWeek, $endOfWeek]);
                            $result = $stmt->fetch();
                            $avgTimeMinutes = $result ? $result['avg_time'] : 0;
                            
                            // Handle NULL or empty values
                            if ($avgTimeMinutes === null || $avgTimeMinutes === '') {
                                $avgTimeMinutes = 0;
                            }
                            
                            // Explicitly cast to float first to avoid precision loss warnings
                            $avgTimeMinutes = (float)$avgTimeMinutes;
                            // Use intval to ensure integer conversion
                            $avgHour = intval(floor($avgTimeMinutes / 60));
                            $avgMinute = intval($avgTimeMinutes % 60);
                            // Use string concatenation instead of sprintf to avoid type issues
                            $avgTimeFormatted = str_pad($avgHour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($avgMinute, 2, '0', STR_PAD_LEFT);
                            
                            // Most active day
                            $stmt = $pdo->prepare("SELECT DAYNAME(timestamp) as day, COUNT(*) as count 
                                FROM attendance WHERE DATE(timestamp) BETWEEN ? AND ? 
                                GROUP BY DAYNAME(timestamp) ORDER BY count DESC LIMIT 1");
                            $stmt->execute([$startOfWeek, $endOfWeek]);
                            $mostActiveDay = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            echo '<div class="summary-item">';
                            echo '<span class="summary-label">Week Check-ins:</span>';
                            echo '<span class="summary-value">' . $weeklyCheckins . '</span>';
                            echo '</div>';
                            
                            echo '<div class="summary-item">';
                            echo '<span class="summary-label">Avg. Check-in Time:</span>';
                            echo '<span class="summary-value">' . $avgTimeFormatted . '</span>';
                            echo '</div>';
                            
                            if ($mostActiveDay) {
                                echo '<div class="summary-item">';
                                echo '<span class="summary-label">Most Active Day:</span>';
                                echo '<span class="summary-value">' . $mostActiveDay['day'] . '</span>';
                                echo '</div>';
                            }
                            
                        } catch (PDOException $e) {
                            echo '<p>No data available for this week.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
            <?php if ($is_admin): ?>
            <!-- Admin Dashboard -->
            <div class="admin-dashboard">
                <div class="admin-stats">
                    <?php
                    // Get total users count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
                    $stmt->execute();
                    $total_users = $stmt->fetch()['total'];

                    // Get today's check-ins count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE DATE(timestamp) = CURDATE() AND type = 'check_in'");
                    $stmt->execute();
                    $todays_checkins = $stmt->fetch()['total'];

                    // Get currently checked in users
                    $stmt = $pdo->prepare("
                        SELECT u.id
                        FROM users u
                        LEFT JOIN attendance a1 ON u.id = a1.user_id
                        LEFT JOIN attendance a2 ON u.id = a2.user_id AND a2.timestamp > a1.timestamp
                        WHERE a2.id IS NULL AND a1.type = 'check_in' AND u.role != 'admin'
                    ");
                    $stmt->execute();
                    $currently_checked_in = $stmt->rowCount();
                    ?>

                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $total_users; ?></span>
                            <span class="stat-label">Total Users</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-sign-in-alt"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $todays_checkins; ?></span>
                            <span class="stat-label">Today's Check-ins</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo $currently_checked_in; ?></span>
                            <span class="stat-label">Currently Present</span>
                        </div>
                    </div>
                </div>

                <div class="recent-activity">
                    <div class="section-header">
                        <h3>Recent Activity</h3>
                        <a href="admin/reports.php" class="btn-admin"><i class="fas fa-chart-bar"></i> View All</a>
                    </div>
                    <div class="activity-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                    <th>Time Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT u.name, a.user_id, a.type, a.timestamp 
                                    FROM attendance a 
                                    JOIN users u ON a.user_id = u.id 
                                    ORDER BY a.timestamp DESC 
                                    LIMIT 15
                                ");
                                $stmt->execute();
                                $records = $stmt->fetchAll();
                                
                                if (count($records) > 0) {
                                    foreach ($records as $record) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($record['name']) . '</td>';
                                        echo '<td><span class="status-badge ' . $record['type'] . '">' . 
                                             ($record['type'] === 'check_in' ? 'Checked In' : 'Checked Out') . '</span></td>';
                                        echo '<td>' . date('M d, H:i', strtotime($record['timestamp'])) . '</td>';
                                        
                                        // Calculate time spent for check-out records
                                        echo '<td>';
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
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center">No activity records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Regular User Dashboard -->
            <div class="status-card">
                <h3>Current Status</h3>
                <p class="status-text">
                    <?php echo $is_checked_in ? 'Checked In' : 'Checked Out'; ?>
                </p>
                <div class="button-container">
                    <form id="attendance-form" action="process/attendance.php" method="POST" style="width: 100%;">
                        <input type="hidden" name="action" value="<?php echo $is_checked_in ? 'check_out' : 'check_in'; ?>">
                        <?php if (!$is_admin): ?>
                        <button type="button" onclick="confirmAttendance()" class="btn-action <?php echo $is_checked_in ? 'btn-checkout' : 'btn-checkin'; ?>">
                            <i class="fas <?php echo $is_checked_in ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?>"></i>
                            <?php echo $is_checked_in ? 'Check Out' : 'Check In'; ?>
                        </button>
                        <?php else: ?>
                        <button type="submit" class="btn-action <?php echo $is_checked_in ? 'btn-checkout' : 'btn-checkin'; ?>">
                            <i class="fas <?php echo $is_checked_in ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?>"></i>
                            <?php echo $is_checked_in ? 'Check Out' : 'Check In'; ?>
                        </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="history-section">
                <h3>Recent Activity</h3>
                <div class="activity-list">
                    <?php
                    // Get all records with timestamps
                    $stmt = $pdo->prepare("
                        SELECT * FROM attendance 
                        WHERE user_id = ? 
                        ORDER BY timestamp DESC 
                        LIMIT 15
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $records = $stmt->fetchAll();
                    
                    if (count($records) > 0) {
                        foreach ($records as $index => $record) {
                            $itemClass = $record['type'] == 'check_in' ? 'check-in' : 'check-out';
                            echo '<div class="activity-item ' . $itemClass . '">';
                            echo '<i class="fas fa-' . ($record['type'] == 'check_in' ? 'sign-in-alt' : 'sign-out-alt') . '"></i>';
                            echo '<span>' . ($record['type'] == 'check_in' ? 'Check In' : 'Check Out') . '</span>';
                            echo '<span class="time">' . date('M d, Y H:i', strtotime($record['timestamp'])) . '</span>';
                            
                            // Calculate time spent for check-out records
                            if ($record['type'] == 'check_out') {
                                // Look for the most recent check-in before this check-out
                                $checkInStmt = $pdo->prepare("
                                    SELECT timestamp FROM attendance 
                                    WHERE user_id = ? AND type = 'check_in' AND timestamp < ? 
                                    ORDER BY timestamp DESC LIMIT 1
                                ");
                                $checkInStmt->execute([$_SESSION['user_id'], $record['timestamp']]);
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
                                        echo '<span class="time-spent overtime">Time spent: ' . $timeSpentFormatted . '</span>';
                                    } else {
                                        echo '<span class="time-spent">Time spent: ' . $timeSpentFormatted . '</span>';
                                    }
                                }
                            }
                            
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="no-activity">No recent activity found</div>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    // No longer needed as we have a dedicated settings page
    
    // No longer needed as we have a dedicated profile settings page
    
    // Calendar functionality
    let currentCalendarDate = new Date();
    
    // Initialize the calendar
    function initCalendar() {
        document.getElementById('prev-month').addEventListener('click', navigateMonth.bind(null, -1));
        document.getElementById('next-month').addEventListener('click', navigateMonth.bind(null, 1));
    }
    
    // Navigate to previous/next month
    function navigateMonth(direction) {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + direction);
        updateCalendar();
    }
    
    // Update the calendar with new month/year
    function updateCalendar() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth() + 1; // JavaScript months are 0-indexed
        
        // Update the header
        document.getElementById('current-month').textContent = 
            currentCalendarDate.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        // Fetch the new calendar data using full URL path to avoid mixed content issues
        const baseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        const calendarUrl = new URL('process/calendar_data.php', baseUrl).href;
        
        fetch(`${calendarUrl}?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(data => {
                renderCalendar(data, month, year);
            })
            .catch(error => {
                console.error('Error fetching calendar data:', error);
            });
    }
    
    // Render the calendar with the provided data
    function renderCalendar(data, month, year) {
        const calendarGrid = document.getElementById('calendar-grid');
        
        // Keep the day headers
        const dayHeaders = Array.from(calendarGrid.querySelectorAll('.calendar-day'));
        calendarGrid.innerHTML = '';
        
        // Add back the day headers
        dayHeaders.forEach(header => calendarGrid.appendChild(header));
        
        // Calculate first day of month and days in month
        const firstDay = new Date(year, month - 1, 1).getDay();
        const daysInMonth = new Date(year, month, 0).getDate();
        
        // Add empty cells for days before the first day of the month
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-date';
            calendarGrid.appendChild(emptyCell);
        }
        
        // Get current date for highlighting
        const today = new Date();
        const currentYear = today.getFullYear();
        const currentMonth = today.getMonth() + 1; // JavaScript months are 0-indexed
        const currentDay = today.getDate();
        
        // Check if we're viewing the current month
        const isCurrentMonth = (currentMonth === month && currentYear === year);
        
        console.log('Current date:', currentDay, currentMonth, currentYear);
        console.log('Calendar month/year:', month, year);
        console.log('Is current month:', isCurrentMonth);
        
        // Add cells for each day of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateCell = document.createElement('div');
            dateCell.className = 'calendar-date';
            
            // Highlight current date
            if (isCurrentMonth && day === currentDay) {
                dateCell.classList.add('current-day');
            }
            
            // Add date info
            const fullDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dateCell.setAttribute('data-date', fullDate);
            
            // Add check-in count if available
            const checkInCount = data[day] || 0;
            dateCell.setAttribute('data-count', checkInCount);
            
            // Add day number
            const dayNumber = document.createElement('span');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dateCell.appendChild(dayNumber);
            
            // Add check-in count badge if there are check-ins
            if (checkInCount > 0) {
                const countBadge = document.createElement('span');
                countBadge.className = 'check-in-count';
                countBadge.textContent = checkInCount;
                dateCell.appendChild(countBadge);
            }
            
            // Add click event
            dateCell.addEventListener('click', function() {
                showDateDetails(this);
            });
            
            calendarGrid.appendChild(dateCell);
        }
    }
    
    // Show attendance details for a specific date
    function showDateDetails(dateElement) {
        const date = dateElement.getAttribute('data-date');
        const count = dateElement.getAttribute('data-count');
        
        if (count > 0) {
            // Fetch attendance records for this date using full URL path
            const baseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
            const detailsUrl = new URL('process/attendance_details.php', baseUrl).href;
            
            fetch(`${detailsUrl}?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    displayDateDetails(date, data);
                })
                .catch(error => {
                    console.error('Error fetching attendance details:', error);
                });
        } else {
            // Show empty message if no check-ins
            displayDateDetails(date, []);
        }
    }
    
    // Display the date details in the modal
    function displayDateDetails(date, records) {
        const modal = document.getElementById('date-details-modal');
        const title = document.getElementById('date-details-title');
        const body = document.getElementById('date-details-body');
        
        // Format the date for display
        const displayDate = new Date(date).toLocaleDateString('default', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        title.textContent = `Attendance for ${displayDate}`;
        
        // Clear previous content
        body.innerHTML = '';
        
        if (records.length === 0) {
            body.innerHTML = '<p>No attendance records for this date.</p>';
        } else {
            records.forEach(record => {
                const recordDiv = document.createElement('div');
                recordDiv.className = `attendance-record ${record.type}`;
                
                const userInfo = document.createElement('div');
                userInfo.className = 'user-info';
                userInfo.textContent = record.name;
                
                const timeInfo = document.createElement('div');
                timeInfo.className = 'time-info';
                
                // Basic time info
                let timeText = `${record.type === 'check_in' ? 'In' : 'Out'}: ${record.time}`;
                
                // Add time spent if available (for check-out records)
                if (record.type === 'check_out' && record.time_spent) {
                    timeText += ` (Time spent: ${record.time_spent})`;
                }
                
                timeInfo.textContent = timeText;
                
                recordDiv.appendChild(userInfo);
                recordDiv.appendChild(timeInfo);
                body.appendChild(recordDiv);
            });
        }
        
        // Show the modal
        modal.style.display = 'block';
        
        // Close modal when clicking the X
        document.querySelector('.close-modal').onclick = function() {
            modal.style.display = 'none';
        };
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    // Initialize calendar when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Set the current date to ensure we're showing the correct month
        currentCalendarDate = new Date();
        initCalendar();
        
        // Force highlight of current day if we're on the current month
        const today = new Date();
        const currentDay = today.getDate();
        const currentMonth = today.getMonth() + 1;
        const currentYear = today.getFullYear();
        
        // Check if we're viewing the current month
        if (currentMonth === currentCalendarDate.getMonth() + 1 && 
            currentYear === currentCalendarDate.getFullYear()) {
            
            // Find the current day cell and highlight it using jQuery
            const dayElements = $('.calendar-date .day-number');
            dayElements.each(function() {
                if ($(this).text().trim() == currentDay) {
                    $(this).parent().addClass('current-day');
                }
            });
        }
    });
    
    // Helper function to find elements by text content
    $.expr[':'].contains = function(a, i, m) {
        return $(a).text().trim() === m[3];
    };
    
    // Function to show announcement form
    function showAnnouncementForm() {
        // Create modal for adding announcements
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.style.position = 'fixed';
        modal.style.zIndex = '1000';
        modal.style.left = '0';
        modal.style.top = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.overflow = 'auto';
        modal.style.backgroundColor = 'rgba(0,0,0,0.4)';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        modalContent.style.backgroundColor = '#fefefe';
        modalContent.style.margin = '15% auto';
        modalContent.style.padding = '20px';
        modalContent.style.border = '1px solid #888';
        modalContent.style.width = '50%';
        modalContent.style.borderRadius = '5px';
        
        const closeBtn = document.createElement('span');
        closeBtn.className = 'close';
        closeBtn.innerHTML = '&times;';
        closeBtn.style.color = '#aaa';
        closeBtn.style.float = 'right';
        closeBtn.style.fontSize = '28px';
        closeBtn.style.fontWeight = 'bold';
        closeBtn.style.cursor = 'pointer';
        closeBtn.onclick = function() {
            document.body.removeChild(modal);
        };
        
        const heading = document.createElement('h3');
        heading.textContent = 'Add New Announcement';
        
        const form = document.createElement('form');
        form.action = 'process/announcement.php';
        form.method = 'POST';
        
        const titleGroup = document.createElement('div');
        titleGroup.className = 'form-group';
        titleGroup.style.marginBottom = '15px';
        
        const titleLabel = document.createElement('label');
        titleLabel.textContent = 'Title:';
        titleLabel.style.display = 'block';
        titleLabel.style.marginBottom = '5px';
        
        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.name = 'title';
        titleInput.required = true;
        titleInput.style.width = '100%';
        titleInput.style.padding = '8px';
        titleInput.style.borderRadius = '4px';
        titleInput.style.border = '1px solid #ddd';
        
        titleGroup.appendChild(titleLabel);
        titleGroup.appendChild(titleInput);
        
        const contentGroup = document.createElement('div');
        contentGroup.className = 'form-group';
        contentGroup.style.marginBottom = '15px';
        
        const contentLabel = document.createElement('label');
        contentLabel.textContent = 'Content:';
        contentLabel.style.display = 'block';
        contentLabel.style.marginBottom = '5px';
        
        const contentTextarea = document.createElement('textarea');
        contentTextarea.name = 'content';
        contentTextarea.required = true;
        contentTextarea.rows = '4';
        contentTextarea.style.width = '100%';
        contentTextarea.style.padding = '8px';
        contentTextarea.style.borderRadius = '4px';
        contentTextarea.style.border = '1px solid #ddd';
        
        contentGroup.appendChild(contentLabel);
        contentGroup.appendChild(contentTextarea);
        
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'btn-admin';
        submitBtn.textContent = 'Save Announcement';
        submitBtn.style.padding = '8px 16px';
        submitBtn.style.backgroundColor = '#4CAF50';
        submitBtn.style.color = 'white';
        submitBtn.style.border = 'none';
        submitBtn.style.borderRadius = '4px';
        submitBtn.style.cursor = 'pointer';
        
        form.appendChild(titleGroup);
        form.appendChild(contentGroup);
        form.appendChild(submitBtn);
        
        modalContent.appendChild(closeBtn);
        modalContent.appendChild(heading);
        modalContent.appendChild(form);
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
    }
    </script>
    <?php if (!$is_admin): ?>
    <!-- Confirmation Modal (only for regular users) -->
    <div id="confirmation-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Action</h3>
                <span class="close-modal" onclick="closeConfirmationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmation-message"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeConfirmationModal()">Cancel</button>
                <button class="btn-confirm" onclick="submitAttendanceForm()">Confirm</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Confirmation modal functionality
    function confirmAttendance() {
        const isCheckedIn = <?php echo $is_checked_in ? 'true' : 'false'; ?>;
        const action = isCheckedIn ? 'check out' : 'check in';
        const currentTime = new Date().toLocaleTimeString();
        
        document.getElementById('confirmation-message').innerHTML = 
            `Are you sure you want to <strong>${action}</strong>?<br>Current time: ${currentTime}`;
        
        document.getElementById('confirmation-modal').style.display = 'block';
    }
    
    function closeConfirmationModal() {
        document.getElementById('confirmation-modal').style.display = 'none';
    }
    
    function submitAttendanceForm() {
        document.getElementById('attendance-form').submit();
    }
    
    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('confirmation-modal');
        if (event.target == modal) {
            closeConfirmationModal();
        }
    }
    </script>
</body>
</html>
