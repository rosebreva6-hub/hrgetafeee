<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
if ($_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$dept_id = $employee['dept_id'];

// Get pending leave requests for this department
$pending_leaves = $conn->query("
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    WHERE e.dept_id = $dept_id AND lr.status = 'pending'
    ORDER BY lr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get team members
$team_members = $conn->query("
    SELECT * FROM employees WHERE dept_id = $dept_id AND employee_id != $employee_id
    ORDER BY first_name
")->fetch_all(MYSQLI_ASSOC);

// Get today's attendance for team
$today = date('Y-m-d');
$team_attendance = $conn->query("
    SELECT a.*, e.first_name, e.last_name, e.employee_code
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE e.dept_id = $dept_id AND a.attendance_date = '$today'
    ORDER BY a.clock_in DESC
")->fetch_all(MYSQLI_ASSOC);

// Count statistics
$present_count = $conn->query("SELECT COUNT(*) as count FROM attendance a JOIN employees e ON a.employee_id = e.employee_id WHERE e.dept_id = $dept_id AND a.attendance_date = '$today' AND a.status = 'present'")->fetch_assoc()['count'];
$absent_count = $conn->query("SELECT COUNT(*) as count FROM attendance a JOIN employees e ON a.employee_id = e.employee_id WHERE e.dept_id = $dept_id AND a.attendance_date = '$today' AND a.status = 'absent'")->fetch_assoc()['count'];
$pending_count = count($pending_leaves);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Head Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Department Head Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="dashboard">
            <div class="card">
                <h3>👥 Team Size</h3>
                <div class="stat-number"><?php echo count($team_members); ?></div>
                <p>Total team members</p>
            </div>
            <div class="card">
                <h3>✅ Present Today</h3>
                <div class="stat-number"><?php echo $present_count; ?></div>
                <p>Clocked in today</p>
            </div>
            <div class="card">
                <h3>❌ Absent Today</h3>
                <div class="stat-number"><?php echo $absent_count; ?></div>
                <p>Not clocked in</p>
            </div>
            <div class="card">
                <h3>⏳ Pending Approvals</h3>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <p>Leave requests awaiting approval</p>
            </div>
        </div>

        <!-- Pending Leave Requests -->
        <?php if (!empty($pending_leaves)): ?>
        <div class="table-container">
            <h3>📮 Pending Leave Requests for Approval</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_leaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?><br><small><?php echo $leave['employee_code']; ?></small></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo format_date($leave['start_date']); ?></td>
                        <td><?php echo format_date($leave['end_date']); ?></td>
                        <td><?php echo $leave['number_of_days']; ?></td>
                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 30)) . (strlen($leave['reason']) > 30 ? '...' : ''); ?></td>
                        <td>
                            <form method="POST" action="approve_leave.php" style="display: inline;">
                                <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">Approve</button>
                            </form>
                            <form method="POST" action="approve_leave.php" style="display: inline;">
                                <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                <button type="submit" name="action" value="deny" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Deny</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card" style="text-align: center;">
            <h3>✓ No Pending Leave Requests</h3>
            <p>All leave requests have been processed!</p>
        </div>
        <?php endif; ?>

        <!-- Team Attendance Today -->
        <div class="table-container">
            <h3>📅 Team Attendance Today (<?php echo format_date($today); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employee ID</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($team_attendance)): ?>
                        <?php foreach ($team_attendance as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                            <td><?php echo $record['employee_code']; ?></td>
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
                        <td colspan="5" class="text-center">No attendance records for today</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Team Members List -->
        <div class="table-container">
            <h3>👥 Team Members</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Employee ID</th>
                        <th>Position</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($team_members)): ?>
                        <?php foreach ($team_members as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><?php echo $member['employee_code']; ?></td>
                            <td><?php echo htmlspecialchars($member['position']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <?php if ($member['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No team members</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
