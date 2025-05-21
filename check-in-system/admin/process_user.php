<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Validate input
                if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password'])) {
                    throw new Exception('All fields are required');
                }

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists');
                }

                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT)
                ]);

                echo json_encode(['success' => true, 'message' => 'User added successfully']);
                break;

            case 'edit':
                // Validate input
                if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['email'])) {
                    throw new Exception('Required fields are missing');
                }

                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $_POST['id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists');
                }

                // Update user
                $sql = "UPDATE users SET name = ?, email = ?";
                $params = [$_POST['name'], $_POST['email']];

                // Only update password if provided
                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id = ? AND role != 'admin'";
                $params[] = $_POST['id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                break;

            case 'delete':
                if (empty($_POST['id'])) {
                    throw new Exception('User ID is required');
                }

                // Delete user and their attendance records
                $pdo->beginTransaction();
                
                // Delete attendance records
                $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$_POST['id']]);
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                break;

            case 'get':
                if (empty($_POST['id'])) {
                    throw new Exception('User ID is required');
                }

                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$_POST['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throw new Exception('User not found');
                }

                echo json_encode(['success' => true, 'user' => $user]);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
