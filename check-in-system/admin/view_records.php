<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get all records with user information
$stmt = $pdo->query("
    SELECT 
        a.id,
        u.name,
        u.email,
        a.type,
        a.timestamp
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.timestamp DESC
");
$records = $stmt->fetchAll();

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Check-In System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: var(--primary-color); color: white; }
        .data-table tr:hover { background: #f5f5f5; }
        .section { margin-bottom: 40px; }
        .section h2 { color: var(--secondary-color); margin-bottom: 20px; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.9em; }
        .check-in { background: #2ecc71; color: white; }
        .check-out { background: #e74c3c; color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1><i class="fas fa-user-shield"></i> Admin Panel</h1>
        <a href="../dashboard.php" class="btn-login" style="display: inline-block; margin: 10px 0;">Back to Dashboard</a>
        
        <div class="section">
            <h2>Users</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Attendance Records</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= htmlspecialchars($record['id']) ?></td>
                        <td><?= htmlspecialchars($record['name']) ?></td>
                        <td><?= htmlspecialchars($record['email']) ?></td>
                        <td>
                            <span class="status-badge <?= $record['type'] == 'check_in' ? 'check-in' : 'check-out' ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $record['type']))) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($record['timestamp']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
