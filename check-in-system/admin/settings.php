<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
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
        
        // Get all settings from the form
        $settings = [
            'system_theme' => $_POST['system_theme'] ?? 'light',
            'records_per_page' => $_POST['records_per_page'] ?? '15',
            'show_user_stats' => isset($_POST['show_user_stats']) ? 1 : 0
        ];
        
        // Update each setting in the database
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?
            ");
            $stmt->execute([$key, $value, $_SESSION['user_id'], $value, $_SESSION['user_id']]);
        }
        
        $_SESSION['success'] = "System settings updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("Location: settings.php");
    exit();
}

// Get current settings
try {
    // Check if settings table exists
    $tableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    $settings = [
        'system_theme' => 'light',
        'records_per_page' => '15',
        'show_user_stats' => 1
    ];
    
    if ($tableExists) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Merge default settings with database settings
        foreach ($dbSettings as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = $value;
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving settings: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Check-In System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .settings-header {
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .settings-header h2 {
            margin: 0;
            color: #495057;
        }
        
        .settings-group {
            margin-bottom: 25px;
        }
        
        .settings-group h3 {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="time"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group select {
            height: 38px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group label {
            margin-left: 10px;
            margin-bottom: 0;
        }
        
        .form-actions {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            margin-top: 20px;
        }
        
        .theme-preview {
            display: inline-block;
            width: 20px;
        }
        
        .theme-preview.theme-light {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .theme-preview.theme-dark {
            background-color: #121212;
            border: 1px solid #2c2c2c;
        }
        
        .preview-header {
            height: 20px;
            border-bottom: 1px solid;
        }
        
        .theme-light .preview-header {
            background-color: #ffffff;
            border-color: #dee2e6;
        }
        
        .theme-dark .preview-header {
            background-color: #1a1a1a;
            border-color: #2c2c2c;
        }
        
        .preview-content {
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .preview-item {
            height: 10px;
            border-radius: 3px;
        }
        
        .theme-light .preview-item:first-child {
            background-color: #007bff;
            width: 80%;
        }
        
        .theme-light .preview-item:last-child {
            background-color: #28a745;
            width: 60%;
        }
        
        .theme-dark .preview-item:first-child {
            background-color: #4dabf7;
            width: 80%;
        }
        
        .theme-dark .preview-item:last-child {
            background-color: #40c057;
            width: 60%;
        }
        
        .theme-option input[type="radio"] {
            display: none;
        }
        
        .theme-label {
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .theme-preview {
            width: 100px;
            height: 60px;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .theme-option input[type="radio"]:checked + .theme-label .theme-preview {
            box-shadow: 0 0 0 3px #007bff;
            transform: scale(1.05);
        }
        
        /* Button states and animations */
        .btn-pulse {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
            }
        }
        
        .btn-success {
            background-color: #28a745 !important;
        }
        
        .btn-danger {
            background-color: #dc3545 !important;
        }
        
        /* Dark theme adjustments for theme preview */
        body.theme-dark .theme-preview.theme-light {
            border: 1px solid #495057;
        }
        
        body.theme-dark .theme-preview.theme-dark {
            border: 1px solid #58a6ff;
            box-shadow: 0 0 5px rgba(88, 166, 255, 0.5);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2><i class="fas fa-cogs"></i> System Settings</h2>
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

        <div class="settings-container">
            <div class="settings-card">
                <div class="settings-header">
                    <h2>System Settings</h2>
                </div>
                
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="settings-group">
                        <h3><i class="fas fa-palette"></i> Appearance</h3>
                        
                        <div class="form-group">
                            <label>System Theme:</label>
                            <div class="theme-selector">
                                <div class="theme-option">
                                    <input type="radio" id="theme-light" name="system_theme" value="light" <?php echo $settings['system_theme'] === 'light' ? 'checked' : ''; ?>>
                                    <label for="theme-light" class="theme-label">
                                        <div class="theme-preview theme-light">
                                            <div class="preview-header"></div>
                                            <div class="preview-content">
                                                <div class="preview-item"></div>
                                                <div class="preview-item"></div>
                                            </div>
                                        </div>
                                        <span>Light</span>
                                    </label>
                                </div>
                                
                                <div class="theme-option">
                                    <input type="radio" id="theme-dark" name="system_theme" value="dark" <?php echo $settings['system_theme'] === 'dark' ? 'checked' : ''; ?>>
                                    <label for="theme-dark" class="theme-label">
                                        <div class="theme-preview theme-dark">
                                            <div class="preview-header"></div>
                                            <div class="preview-content">
                                                <div class="preview-item"></div>
                                                <div class="preview-item"></div>
                                            </div>
                                        </div>
                                        <span>Dark</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="button" id="apply-theme" class="btn-admin"><i class="fas fa-check"></i> Apply Theme</button>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <h3><i class="fas fa-tachometer-alt"></i> Dashboard Settings</h3>
                        
                        <div class="form-group">
                            <label for="records_per_page">Records Per Page</label>
                            <input type="number" id="records_per_page" name="records_per_page" min="5" max="100" value="<?php echo htmlspecialchars($settings['records_per_page']); ?>">
                            <small>Number of records to display in tables and reports</small>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="show_user_stats" name="show_user_stats" <?php echo $settings['show_user_stats'] ? 'checked' : ''; ?>>
                            <label for="show_user_stats">Show User Statistics on Dashboard</label>
                        </div>
                    </div>
                    
                    <!-- Additional features section removed as these features were non-functional -->
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Save Settings</button>
                    </div>
                </form>
            </div>
            
            <div class="settings-card">
                <div class="settings-header">
                    <h2>System Maintenance</h2>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-database"></i> Database Operations</h3>
                    
                    <p>These operations affect your system data. Use with caution.</p>
                    
                    <div class="form-actions">
                        <a href="maintenance.php?action=clear_logs" class="btn-admin" style="background-color: #ffc107;" onclick="return confirm('Are you sure you want to clear all system logs? This cannot be undone.')">
                            <i class="fas fa-broom"></i> Clear System Logs
                        </a>
                        
                        <a href="maintenance.php?action=reset" class="btn-admin" style="background-color: #dc3545; margin-left: 15px;" onclick="return confirm('WARNING: This will reset all attendance data. This action cannot be undone. Are you absolutely sure?')">
                            <i class="fas fa-trash"></i> Reset Attendance Data
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme apply functionality
        const applyButton = document.getElementById('apply-theme');
        const themeRadios = document.querySelectorAll('input[name="system_theme"]');
        
        // Add live preview when selecting a theme
        themeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Preview theme immediately
                document.body.classList.remove('theme-light', 'theme-dark');
                document.body.classList.add('theme-' + this.value);
                
                // Add a visual indicator that changes need to be applied
                applyButton.classList.add('btn-pulse');
                applyButton.innerHTML = '<i class="fas fa-check"></i> Apply Theme Changes';
            });
        });
        
        applyButton.addEventListener('click', function() {
            // Get selected theme
            let selectedTheme = 'light';
            themeRadios.forEach(radio => {
                if (radio.checked) {
                    selectedTheme = radio.value;
                }
            });
            
            // Show loading state
            applyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
            applyButton.disabled = true;
            
            // Save theme to database via AJAX
            fetch('apply_theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'theme=' + selectedTheme
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    applyButton.innerHTML = '<i class="fas fa-check"></i> Theme Applied';
                    applyButton.classList.remove('btn-pulse');
                    applyButton.classList.add('btn-success');
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        applyButton.innerHTML = '<i class="fas fa-check"></i> Apply Theme';
                        applyButton.classList.remove('btn-success');
                        applyButton.disabled = false;
                    }, 2000);
                } else {
                    // Show error state
                    applyButton.innerHTML = '<i class="fas fa-times"></i> Error';
                    applyButton.classList.add('btn-danger');
                    applyButton.disabled = false;
                    alert('Error applying theme: ' + data.message);
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        applyButton.innerHTML = '<i class="fas fa-check"></i> Apply Theme';
                        applyButton.classList.remove('btn-danger');
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                applyButton.innerHTML = '<i class="fas fa-times"></i> Error';
                applyButton.classList.add('btn-danger');
                applyButton.disabled = false;
                alert('Error applying theme. Please try again.');
                
                // Reset button after 2 seconds
                setTimeout(() => {
                    applyButton.innerHTML = '<i class="fas fa-check"></i> Apply Theme';
                    applyButton.classList.remove('btn-danger');
                }, 2000);
            });
        });
    });
    </script>
</body>
</html>
