<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle user actions (if any)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['user_id'])) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$_POST['user_id']]);
                }
                break;
            case 'update':
                if (isset($_POST['user_id'], $_POST['name'], $_POST['email'])) {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$_POST['name'], $_POST['email'], $_POST['user_id']]);
                }
                break;
        }
    }
}

// Get all users except current admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get theme setting
$theme = 'light'; // Default theme
try {
    $themeStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_theme' LIMIT 1");
    $themeStmt->execute();
    $themeResult = $themeStmt->fetchColumn();
    if ($themeResult !== false) {
        $theme = $themeResult;
    }
} catch (PDOException $e) {
    // If error, use default theme
    // No need to show error to user
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Check-In System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2><i class="fas fa-users"></i> Manage Users</h2>
            <div class="nav-actions">
                <a href="../dashboard.php" class="btn-admin"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </nav>

        <div class="dashboard-content">
            <div class="users-list">
                <div class="users-header">
                    <h3>System Users</h3>
                    <button class="btn-admin" onclick="showAddUserForm()">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>

                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td class="actions">
                                    <button class="btn-icon" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <button class="btn-icon delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- User Form Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <span class="close">&times;</span>
            </div>
            <form id="userForm" onsubmit="return submitUserForm(event)">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId">
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password">
                    <small class="password-hint">Leave empty to keep existing password when editing</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-admin">Save</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const modal = document.getElementById('userModal');
    const closeBtn = document.getElementsByClassName('close')[0];
    const userForm = document.getElementById('userForm');

    function showAddUserForm() {
        document.getElementById('modalTitle').textContent = 'Add New User';
        document.getElementById('formAction').value = 'add';
        document.getElementById('userId').value = '';
        userForm.reset();
        document.getElementById('password').required = true;
        modal.style.display = 'block';
    }

    function editUser(userId) {
        fetch('process_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get&id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = 'Edit User';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('userId').value = data.user.id;
                document.getElementById('name').value = data.user.name;
                document.getElementById('email').value = data.user.email;
                document.getElementById('password').required = false;
                modal.style.display = 'block';
            } else {
                alert(data.message);
            }
        })
        .catch(error => alert('Error loading user data'));
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            fetch('process_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => alert('Error deleting user'));
        }
    }

    function submitUserForm(event) {
        event.preventDefault();
        const formData = new FormData(userForm);

        fetch('process_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => alert('Error saving user data'));

        return false;
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    closeBtn.onclick = closeModal;
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>
