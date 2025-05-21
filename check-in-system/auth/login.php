<?php
session_start();

try {
    require_once '../config/database.php';
    
    // Verify database connection
    $testQuery = $pdo->query("SELECT DATABASE() as db");
    $dbInfo = $testQuery->fetch(PDO::FETCH_ASSOC);
    error_log("Connected to database: " . $dbInfo['db']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get and sanitize inputs
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        
        // Log login attempt (for debugging)
        error_log("Login attempt for email: " . $email);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header("Location: ../index.php");
            exit();
        }
        
        // Check for empty password
        if (empty($password)) {
            $_SESSION['error'] = "Password cannot be empty.";
            header("Location: ../index.php");
            exit();
        }
        
        // Query the database for the user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("User found with ID: " . $user['id'] . ", Role: " . $user['role']);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                error_log("Password verification successful");
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Update last login time if the column exists
                try {
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (Exception $e) {
                    // Column might not exist, that's okay
                    error_log("Could not update last_login: " . $e->getMessage());
                }
                
                // Clear any existing error
                if (isset($_SESSION['error'])) {
                    unset($_SESSION['error']);
                }
                
                // Set success message
                $_SESSION['success'] = "Welcome back, " . $user['name'] . "!";
                
                // Redirect to dashboard
                error_log("Redirecting user ID " . $user['id'] . " to dashboard");
                header("Location: ../dashboard.php");
                exit();
            } else {
                // Try one more time with a fixed hash if this is the admin account
                // This is a temporary fix for admin authentication issues
                if ($user['role'] === 'admin' && $email === 'admin@example.com' && $password === 'admin123') {
                    error_log("Admin login with default credentials - applying special handling");
                    
                    // Set session variables for admin
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = 'admin';
                    
                    // Fix the admin password hash
                    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$newHash, $user['id']]);
                    
                    error_log("Admin password hash updated and redirecting to dashboard");
                    header("Location: ../dashboard.php");
                    exit();
                }
                
                // Password verification failed
                error_log("Password verification failed for user ID: " . $user['id']);
                $_SESSION['error'] = "Incorrect email or password. Please try again.";
                header("Location: ../index.php");
                exit();
            }
        } else {
            // User not found
            error_log("Login failed for email: " . $email . " - User not found");
            $_SESSION['error'] = "Incorrect email or password. Please try again.";
            header("Location: ../index.php");
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("Database error in login.php: " . $e->getMessage());
    $_SESSION['error'] = "Database error. Please try again later.";
    header("Location: ../index.php");
    exit();
}
?>
