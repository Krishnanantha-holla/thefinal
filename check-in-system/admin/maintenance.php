<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle maintenance actions
if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {
            case 'backup':
                // Create backup directory if it doesn't exist
                $backupDir = '../backups';
                if (!file_exists($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                // Generate backup filename with timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
                
                // Get all tables
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Start output buffering
                ob_start();
                
                // Add SQL header
                echo "-- Check-In System Database Backup\n";
                echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                echo "-- ------------------------------------------------------------\n\n";
                
                // Process each table
                foreach ($tables as $table) {
                    // Get create table statement
                    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                    $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo $createTable['Create Table'] . ";\n\n";
                    
                    // Get table data
                    $stmt = $pdo->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rows) > 0) {
                        $columns = array_keys($rows[0]);
                        
                        // Generate insert statements
                        echo "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES\n";
                        
                        $rowCount = count($rows);
                        foreach ($rows as $i => $row) {
                            $values = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $values[] = $pdo->quote($value);
                                }
                            }
                            
                            echo "(" . implode(", ", $values) . ")";
                            if ($i < $rowCount - 1) {
                                echo ",\n";
                            } else {
                                echo ";\n\n";
                            }
                        }
                    }
                }
                
                // Get the buffer contents
                $sql = ob_get_clean();
                
                // Save to file
                file_put_contents($backupFile, $sql);
                
                // Provide download
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . basename($backupFile));
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($backupFile));
                readfile($backupFile);
                exit;
                
            case 'clear_logs':
                // Create activity_logs table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id VARCHAR(50),
                        activity_type VARCHAR(50) NOT NULL,
                        message TEXT NOT NULL,
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                // Truncate the activity_logs table
                $pdo->exec("TRUNCATE TABLE activity_logs");
                
                $_SESSION['success'] = "System logs have been cleared successfully.";
                break;
                
            case 'reset':
                // Truncate the attendance table
                $pdo->exec("TRUNCATE TABLE attendance");
                
                $_SESSION['success'] = "Attendance data has been reset successfully.";
                break;
                
            default:
                $_SESSION['error'] = "Invalid maintenance action.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error performing maintenance: " . $e->getMessage();
    }
    
    // Redirect back to settings page (except for backup which handles its own output)
    if ($_GET['action'] !== 'backup') {
        header("Location: settings.php");
        exit();
    }
}

// If no action was specified, redirect to settings
header("Location: settings.php");
exit();
?>
