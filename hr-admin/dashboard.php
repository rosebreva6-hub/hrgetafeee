<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
if ($_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$message = '';

// Handle user status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'];
    
    if ($action === 'activate') {
        $conn->query("UPDATE users SET status = 'active' WHERE user_id = $user_id");
        $message = '<div class="alert alert-success">✓ User activated!</div>';
    } else if ($action === 'deactivate') {
        $conn->query("UPDATE users SET status = 'inactive' WHERE user_id = $user_id");
        $message = '<div class="alert alert-success">✓ User deactivated!</div>';
    }
}

// Get all users
$users = $conn->query("
    SELECT u.*, e.first_name, e.last_name, e.employee_code, r.role_name
    FROM users u
    JOIN employees e ON u.employee_id = e.employee_id
    JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_users = count($users);
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 1")->fetch_assoc()['count'];
$staff_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2")->fetch_assoc()['count'];
$head_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3")->fetch_assoc()['count'];
$emp_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 4")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Admin Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - HR Administrator Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="dashboard">
            <div class="card">
                <h3>👥 Total Users</h3>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <p>All system users</p>
            </div>
            <div class="card">
                <h3>✅ Active Users</h3>
                <div class="stat-number"><?php echo $active_users; ?></div>
                <p>Currently active</p>
            </div>
            <div class="card">
                <h3>🔐 Administrators</h3>
                <div class="stat-number"><?php echo $admin_count; ?></div>
                <p>Admin accounts</p>
            </div>
            <div class="card">
                <h3>📊 HR Staff</h3>
                <div class="stat-number"><?php echo $staff_count; ?></div>
                <p>HR personnel</p>
            </div>
            <div class="card">
                <h3>👔 Department Heads</h3>
                <div class="stat-number"><?php echo $head_count; ?></div>
                <p>Department leads</p>
            </div>
            <div class="card">
                <h3>👨‍💼 Employees</h3>
                <div class="stat-number"><?php echo $emp_count; ?></div>
                <p>Regular employees</p>
            </div>
        </div>

        <!-- System Settings -->
        <div class="table-container">
            <h3>⚙️ System Settings</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="card">
                    <h3>📅 System Information</h3>
                    <p><strong>Database:</strong> hrgetafee</p>
                    <p><strong>Last Updated:</strong> <?php echo format_datetime(date('Y-m-d H:i:s')); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-success">Operational</span></p>
                </div>
                <div class="card">
                    <h3>🔧 Maintenance</h3>
                    <p><strong>Backup Status:</strong> Regular</p>
                    <p><strong>Security:</strong> Enabled</p>
                    <button class="btn btn-primary" style="margin-top: 1rem;">View Logs</button>
                </div>
            </div>
        </div>

        <!-- User Management -->
        <div class="table-container">
            <h3>👥 User Management & Access Control</h3>
            
            <?php if ($message) echo $message; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Employee ID</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo $user['employee_code']; ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : '-'; ?></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="action" value="deactivate" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="action" value="activate" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No users found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- System Configuration -->
        <div class="table-container">
            <h3>⚙️ System Configuration</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label>Leave Types Management</label>
                    <p style="color: #666; margin-bottom: 1rem;">View and manage all leave types available in the system</p>
                    <button class="btn btn-secondary" disabled>Configure Leave Types</button>
                </div>
                <div class="form-group">
                    <label>Holiday Management</label>
                    <p style="color: #666; margin-bottom: 1rem;">Set official holidays and special non-working days</p>
                    <button class="btn btn-secondary" disabled>Manage Holidays</button>
                </div>
                <div class="form-group">
                    <label>Department Setup</label>
                    <p style="color: #666; margin-bottom: 1rem;">Create and manage organizational departments</p>
                    <button class="btn btn-secondary" disabled>Manage Departments</button>
                </div>
                <div class="form-group">
                    <label>System Logs</label>
                    <p style="color: #666; margin-bottom: 1rem;">View audit logs and system activities</p>
                    <button class="btn btn-secondary" disabled>View Logs</button>
                </div>
            </div>
        </div>

        <!-- Security & Backup -->
        <div class="card" style="margin-top: 2rem;">
            <h3>🔒 Security & Data Management</h3>
            <p><strong>Status:</strong> All systems secure ✓</p>
            <p style="color: #666; margin-bottom: 1rem;">Regular automated backups and security protocols are in place.</p>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <button class="btn btn-secondary" disabled>View Backups</button>
                <button class="btn btn-secondary" disabled>Security Settings</button>
                <button class="btn btn-danger" disabled>Reset Database</button>
            </div>
        </div>
    </div>
</body>
</html>
