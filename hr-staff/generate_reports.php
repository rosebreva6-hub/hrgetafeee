<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
if ($_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get current month/year
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

// Get all attendance for current month
$attendance = $conn->query("
    SELECT a.*, e.employee_code, e.first_name, e.last_name, e.position, d.dept_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    JOIN departments d ON e.dept_id = d.dept_id
    WHERE MONTH(a.attendance_date) = $current_month AND YEAR(a.attendance_date) = $current_year
    ORDER BY a.attendance_date DESC, e.first_name
")->fetch_all(MYSQLI_ASSOC);

// Get monthly summary
$summary = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave
    FROM attendance
    WHERE MONTH(attendance_date) = $current_month AND YEAR(attendance_date) = $current_year
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Report Generation</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <!-- Report Filter -->
        <div class="table-container">
            <h3>📊 Select Report Period</h3>
            <form method="GET" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="month">Month</label>
                    <select id="month" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year">Year</label>
                    <select id="year" name="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="align-self: flex-end;">Generate Report</button>
            </form>
        </div>

        <!-- Report Summary -->
        <div class="dashboard" style="margin-bottom: 2rem;">
            <div class="card">
                <h3>📋 Total Records</h3>
                <div class="stat-number"><?php echo $summary['total']; ?></div>
                <p>Attendance entries</p>
            </div>
            <div class="card">
                <h3>✅ Present</h3>
                <div class="stat-number"><?php echo $summary['present']; ?></div>
                <p>Days present</p>
            </div>
            <div class="card">
                <h3>❌ Absent</h3>
                <div class="stat-number"><?php echo $summary['absent']; ?></div>
                <p>Days absent</p>
            </div>
            <div class="card">
                <h3>⏰ Late</h3>
                <div class="stat-number"><?php echo $summary['late']; ?></div>
                <p>Late arrivals</p>
            </div>
            <div class="card">
                <h3>📅 On Leave</h3>
                <div class="stat-number"><?php echo $summary['on_leave']; ?></div>
                <p>Days on leave</p>
            </div>
        </div>

        <!-- DTR Report -->
        <div class="table-container">
            <h3>📑 Daily Time Record - <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendance)): ?>
                        <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td><?php echo format_date($record['attendance_date']); ?></td>
                            <td><?php echo $record['employee_code']; ?></td>
                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['dept_name']); ?></td>
                            <td><?php echo $record['clock_in'] ? format_datetime($record['clock_in']) : '-'; ?></td>
                            <td><?php echo $record['clock_out'] ? format_datetime($record['clock_out']) : '-'; ?></td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="badge badge-success">Present</span>
                                <?php elseif ($record['status'] === 'late'): ?>
                                    <span class="badge badge-warning">Late</span>
                                <?php elseif ($record['status'] === 'absent'): ?>
                                    <span class="badge badge-danger">Absent</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><?php echo ucfirst($record['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No attendance records for this period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="margin-top: 1rem;">
                <button class="btn btn-success" onclick="window.print()">🖨️ Print Report</button>
                <button class="btn btn-primary" disabled>📥 Export to Excel</button>
            </div>
        </div>
    </div>
</body>
</html>