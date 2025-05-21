<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Test Page</h1>";
echo "<pre>";
echo "PHP is working!\n";
echo "PHP Version: " . phpversion() . "\n";

// Test database connection
try {
    require_once 'config/database.php';
    echo "\nDatabase connection test completed above.\n";
    
    // Test session
    session_start();
    echo "\nSession test:\n";
    $_SESSION['test'] = 'test_value';
    echo "Session ID: " . session_id() . "\n";
    echo "Session test value: " . $_SESSION['test'] . "\n";
    
} catch (Exception $e) {
    echo "\nError occurred:\n";
    echo $e->getMessage() . "\n";
}
?>
