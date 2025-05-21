<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: ../dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: ../dashboard.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_profile':
                // Validate input
                $name = trim($_POST['name'] ?? '');
                $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                
                if (empty($name) || empty($email)) {
                    throw new Exception("Name and email are required.");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Please enter a valid email address.");
                }
                
                // Check if email exists for other users
                if ($email !== $user['email']) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Email already exists for another user.");
                    }
                }
                
                // Update profile
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $_SESSION['user_id']]);
                
                // Update session data
                $_SESSION['user_name'] = $name;
                
                $_SESSION['success'] = "Profile updated successfully.";
                break;
                
            case 'change_password':
                // Validate input
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("All password fields are required.");
                }
                
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception("Current password is incorrect.");
                }
                
                // Validate new password
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long.");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }
                
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $_SESSION['user_id']]);
                
                $_SESSION['success'] = "Password changed successfully.";
                break;
                
            case 'update_preferences':
                // Get preferences
                $notification_enabled = isset($_POST['notification_enabled']) ? 1 : 0;
                $theme = $_POST['theme'] ?? 'light';
                
                // Create user_preferences table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS user_preferences (
                        user_id INT PRIMARY KEY,
                        notification_enabled TINYINT(1) DEFAULT 1,
                        theme VARCHAR(20) DEFAULT 'light',
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                
                // Update or insert preferences
                $stmt = $pdo->prepare("
                    INSERT INTO user_preferences (user_id, notification_enabled, theme) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE notification_enabled = ?, theme = ?
                ");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $notification_enabled, 
                    $theme, 
                    $notification_enabled, 
                    $theme
                ]);
                
                $_SESSION['success'] = "Preferences updated successfully.";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect to refresh the page and avoid form resubmission
    header("Location: settings.php");
    exit();
}

// Get user preferences
$preferences = [
    'notification_enabled' => 1,
    'theme' => 'light'
];

try {
    // Check if preferences table exists
    $tableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    if ($tableExists) {
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userPrefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userPrefs) {
            $preferences['notification_enabled'] = $userPrefs['notification_enabled'];
            $preferences['theme'] = $userPrefs['theme'];
        }
    }
} catch (PDOException $e) {
    // Silently fail and use defaults
}

// Get user statistics
try {
    // Total check-ins
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE user_id = ? AND type = 'check_in'");
    $stmt->execute([$_SESSION['user_id']]);
    $totalCheckIns = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // First check-in date
    $stmt = $pdo->prepare("SELECT MIN(timestamp) as first_date FROM attendance WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $firstDate = $stmt->fetch(PDO::FETCH_ASSOC)['first_date'];
    
    // Last check-in date
    $stmt = $pdo->prepare("SELECT MAX(timestamp) as last_date FROM attendance WHERE user_id = ? AND type = 'check_in'");
    $stmt->execute([$_SESSION['user_id']]);
    $lastCheckIn = $stmt->fetch(PDO::FETCH_ASSOC)['last_date'];
    
    // Average check-in time
    $stmt = $pdo->prepare("
        SELECT AVG(HOUR(timestamp) * 60 + MINUTE(timestamp)) as avg_time 
        FROM attendance 
        WHERE user_id = ? AND type = 'check_in'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $avgTimeMinutes = $stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0;
    
    $avgHour = floor($avgTimeMinutes / 60);
    $avgMinute = $avgTimeMinutes % 60;
    $avgTimeFormatted = sprintf("%02d:%02d", $avgHour, $avgMinute);
} catch (PDOException $e) {
    // Silently fail
    $totalCheckIns = 0;
    $firstDate = null;
    $lastCheckIn = null;
    $avgTimeFormatted = "00:00";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Check-In System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <style>
        :root {
            --bg-primary: #fff;
            --bg-secondary: #f8f9fa;
            --bg-input: #fff;
            --text-primary: #495057;
            --text-secondary: #6c757d;
            --primary-color: #007bff;
            --border-color: #dee2e6;
        }
        
        /* Theme toggle switch styles */
        .theme-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            margin-left: 15px;
        }
        
        .theme-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            z-index: 2;
        }
        
        input:checked + .toggle-slider {
            background-color: #2196F3;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .toggle-icon {
            color: white;
            font-size: 14px;
            z-index: 1;
        }
        
        .theme-dark {
            --bg-primary: #2f343a;
            --bg-secondary: #3b4148;
            --bg-input: #3b4148;
            --text-primary: #fff;
            --text-secondary: #adb5bd;
            --primary-color: #66d9ef;
            --border-color: #4f5458;
        }
        
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-card {
            background-color: var(--bg-primary);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--bg-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--text-secondary);
            margin-right: 20px;
        }
        
        .profile-info h2 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .profile-info p {
            margin: 5px 0 0;
            color: var(--text-secondary);
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .profile-tab {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: var(--text-secondary);
        }
        
        .profile-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--bg-input);
            color: var(--text-primary);
        }
        
        .form-actions {
            text-align: right;
            padding-top: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background-color: var(--bg-secondary);
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group label {
            margin-left: 10px;
            margin-bottom: 0;
            color: var(--text-primary);
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($preferences['theme']); ?>">
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2><i class="fas fa-user-cog"></i> Profile Settings</h2>
            <div class="nav-actions">
                <a href="../dashboard.php" class="btn-admin"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="../auth/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <p><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></p>
                    </div>
                    <div class="theme-toggle-container" style="margin-left: auto;">
                        <span style="margin-right: 10px; vertical-align: middle;">Theme</span>
                        <label class="theme-toggle">
                            <input type="checkbox" id="theme-toggle-switch" <?php echo $preferences['theme'] === 'dark' ? 'checked' : ''; ?>>
                            <span class="toggle-slider">
                                <i class="fas fa-sun toggle-icon" style="margin-left: 4px;"></i>
                                <i class="fas fa-moon toggle-icon" style="margin-right: 4px;"></i>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="profile">
                        <i class="fas fa-user"></i> Profile
                    </div>
                    <div class="profile-tab" data-tab="password">
                        <i class="fas fa-lock"></i> Password
                    </div>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <div class="profile-tab" data-tab="preferences">
                        <i class="fas fa-sliders-h"></i> Preferences
                    </div>
                    <?php endif; ?>
                    <div class="profile-tab" data-tab="statistics">
                        <i class="fas fa-chart-line"></i> Statistics
                    </div>
                </div>
                
                <!-- Profile Tab -->
                <div class="tab-content active" id="profile-tab">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-admin">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Password Tab -->
                <div class="tab-content" id="password-tab">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small>Password must be at least 8 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-admin">Change Password</button>
                        </div>
                    </form>
                </div>
                
                <!-- Preferences Tab -->
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <div class="tab-content" id="preferences-tab">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="notification_enabled" name="notification_enabled" <?php echo $preferences['notification_enabled'] ? 'checked' : ''; ?>>
                            <label for="notification_enabled">Enable Notifications</label>
                        </div>
                        
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme">
                                <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="blue" <?php echo $preferences['theme'] === 'blue' ? 'selected' : ''; ?>>Blue</option>
                                <option value="green" <?php echo $preferences['theme'] === 'green' ? 'selected' : ''; ?>>Green</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-admin">Save Preferences</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Tab -->
                <div class="tab-content" id="statistics-tab">
                    <h3>Your Check-In Statistics</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalCheckIns; ?></div>
                            <div class="stat-label">Total Check-ins</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $firstDate ? date('M d, Y', strtotime($firstDate)) : 'N/A'; ?></div>
                            <div class="stat-label">First Activity</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $lastCheckIn ? date('M d, Y', strtotime($lastCheckIn)) : 'N/A'; ?></div>
                            <div class="stat-label">Last Check-in</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $avgTimeFormatted; ?></div>
                            <div class="stat-label">Average Check-in Time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.profile-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });
        
        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle-switch');
        if (themeToggle) {
            themeToggle.addEventListener('change', function() {
                const theme = this.checked ? 'dark' : 'light';
                
                // Apply theme immediately
                document.body.classList.remove('theme-light', 'theme-dark', 'theme-blue', 'theme-green');
                document.body.classList.add('theme-' + theme);
                
                // Save theme preference via AJAX
                fetch('save_theme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'theme=' + theme
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Theme preference saved successfully');
                    } else {
                        console.error('Error saving theme preference:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
            
            // Apply current theme on page load
            const currentTheme = themeToggle.checked ? 'dark' : 'light';
            document.body.classList.add('theme-' + currentTheme);
        }
    });
    </script>
</body>
</html>
