<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if logged in and is employee
require_login();
if ($_SESSION['role_id'] != 4) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$username = $_SESSION['username'];

// Handle clock in/out
$clock_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $today = date('Y-m-d');
        
        // Check if already clocked in today
        $check = $conn->query("SELECT attendance_id, clock_out FROM attendance WHERE employee_id = $employee_id AND attendance_date = '$today'");
        
        if ($action === 'clock_in') {
            if ($check->num_rows === 0) {
                $clock_in = date('Y-m-d H:i:s');
                $conn->query("INSERT INTO attendance (employee_id, clock_in, attendance_date, status) VALUES ($employee_id, '$clock_in', '$today', 'present')");
                $clock_message = '<div class="alert alert-success">✓ Clocked in successfully at ' . date('h:i A') . '</div>';
            } else {
                $clock_message = '<div class="alert alert-danger">✗ Already clocked in today</div>';
            }
        } else if ($action === 'clock_out') {
            $record = $check->fetch_assoc();
            if ($record && $record['clock_out'] === null) {
                $clock_out = date('Y-m-d H:i:s');
                $conn->query("UPDATE attendance SET clock_out = '$clock_out' WHERE attendance_id = " . $record['attendance_id']);
                $clock_message = '<div class="alert alert-success">✓ Clocked out successfully at ' . date('h:i A') . '</div>';
            } else {
                $clock_message = '<div class="alert alert-danger">✗ Cannot clock out or already clocked out</div>';
            }
        }
    }
}

// Get today's attendance
$today = date('Y-m-d');
$today_attendance = $conn->query("SELECT * FROM attendance WHERE employee_id = $employee_id AND attendance_date = '$today'")->fetch_assoc();

// Get recent attendance records
$recent_attendance = $conn->query("SELECT * FROM attendance WHERE employee_id = $employee_id ORDER BY attendance_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Get pending leave requests
$pending_leaves = $conn->query("SELECT lr.*, lt.leave_type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.employee_id = $employee_id AND lr.status = 'pending' ORDER BY lr.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get leave balances
$leave_types = $conn->query("SELECT * FROM leave_types")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
        <h2>HRGetafe - Employee Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Welcome Section -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3>👋 Welcome Back!</h3>
            <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_code']); ?></p>
            <p><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
            <p><strong>Department:</strong> <?php echo get_department_name($conn, $employee['dept_id']); ?></p>
        </div>

        <!-- Clock In/Out Section -->
        <?php if ($clock_message) echo $clock_message; ?>
        
        <div class="card" style="margin-bottom: 2rem;">
            <h3>⏰ Clock In/Out</h3>
            <?php if ($today_attendance): ?>
                <p><strong>Clock In:</strong> <?php echo format_datetime($today_attendance['clock_in']); ?></p>
                <?php if ($today_attendance['clock_out']): ?>
                    <p><strong>Clock Out:</strong> <?php echo format_datetime($today_attendance['clock_out']); ?></p>
                    <p><span class="badge badge-success">Clocked Out</span></p>
                <?php else: ?>
                    <p><span class="badge badge-pending">Currently Clocked In</span></p>
                    <form method="POST" style="margin-top: 1rem;">
                        <button type="submit" name="action" value="clock_out" class="btn btn-danger">Clock Out</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p style="color: #666; margin-bottom: 1rem;">You haven't clocked in yet today</p>
                <form method="POST">
                    <button type="submit" name="action" value="clock_in" class="btn btn-success">Clock In</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Leave Balances -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <?php foreach ($leave_types as $leave): ?>
                <?php $balance = calculate_leave_balance($conn, $employee_id, $leave['leave_type_id']); ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($leave['leave_type_name']); ?></h3>
                    <p style="color: #666; font-size: 14px;">Balance Available</p>
                    <div class="stat-number"><?php echo $balance; ?> days</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3>📋 Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <a href="records.php" class="btn btn-primary">View Attendance Records</a>
                <a href="apply_leave.php" class="btn btn-primary">Apply for Leave</a>
            </div>
        </div>

        <!-- Pending Leave Requests -->
        <?php if (!empty($pending_leaves)): ?>
        <div class="table-container">
            <h3>📮 Pending Leave Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Days</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_leaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo format_date($leave['start_date']); ?></td>
                        <td><?php echo format_date($leave['end_date']); ?></td>
                        <td><?php echo $leave['number_of_days']; ?></td>
                        <td><span class="badge badge-pending">Pending</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Recent Attendance -->
        <div class="table-container">
            <h3>📅 Recent Attendance Records</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_attendance)): ?>
                        <?php foreach ($recent_attendance as $record): ?>
                        <tr>
                            <td><?php echo format_date($record['attendance_date']); ?></td>
                            <td><?php echo $record['clock_in'] ? format_datetime($record['clock_in']) : '-'; ?></td>
                            <td><?php echo $record['clock_out'] ? format_datetime($record['clock_out']) : '-'; ?></td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="badge badge-success">Present</span>
                                <?php elseif ($record['status'] === 'late'): ?>
                                    <span class="badge badge-warning">Late</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><?php echo ucfirst($record['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No attendance records yet</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>