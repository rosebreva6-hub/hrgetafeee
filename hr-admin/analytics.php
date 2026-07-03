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

// Get attendance analytics for current month
$current_month = date('m');
$current_year = date('Y');

// Attendance trends - last 6 months
$attendance_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE MONTH(attendance_date) = $month AND YEAR(attendance_date) = $year
    ")->fetch_assoc();
    
    $attendance_trend[] = [
        'month' => date('M Y', strtotime("-$i months")),
        'present' => $result['present'] ?? 0,
        'absent' => $result['absent'] ?? 0,
        'late' => $result['late'] ?? 0,
        'total' => $result['total'] ?? 0
    ];
}

// Current month attendance breakdown
$this_month = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM attendance
    WHERE MONTH(attendance_date) = $current_month AND YEAR(attendance_date) = $current_year
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// Leave statistics
$leave_stats = $conn->query("
    SELECT 
        lt.leave_type_name,
        COUNT(lr.leave_id) as total_requests,
        SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN lr.status = 'denied' THEN 1 ELSE 0 END) as denied,
        SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.leave_type_id = lr.leave_type_id
    GROUP BY lt.leave_type_id, lt.leave_type_name
")->fetch_all(MYSQLI_ASSOC);

// Department-wise attendance
$dept_stats = $conn->query("
    SELECT 
        d.dept_name,
        COUNT(DISTINCT e.employee_id) as total_employees,
        SUM(CASE WHEN a.attendance_date = CURDATE() AND a.status = 'present' THEN 1 ELSE 0 END) as present_today,
        SUM(CASE WHEN a.attendance_date = CURDATE() AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_today
    FROM departments d
    LEFT JOIN employees e ON d.dept_id = e.dept_id
    LEFT JOIN attendance a ON e.employee_id = a.employee_id
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Top employees by attendance
$top_employees = $conn->query("
    SELECT 
        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) as name,
        COUNT(a.attendance_id) as attendance_count,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
        d.dept_name
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND MONTH(a.attendance_date) = $current_month
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.status = 'active'
    GROUP BY e.employee_id
    ORDER BY present_days DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
            margin-bottom: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box h4 {
            font-size: 12px;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Analytics & Reports</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <!-- Current Month Stats -->
        <div class="stats-grid">
            <?php foreach ($this_month as $stat): ?>
                <div class="stat-box">
                    <h4><?php echo ucfirst($stat['status']); ?></h4>
                    <div class="number"><?php echo $stat['count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Attendance Trend Chart -->
        <div class="table-container">
            <h3>📈 Attendance Trend (Last 6 Months)</h3>
            <div class="chart-container">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>

        <!-- Leave Statistics -->
        <div class="table-container">
            <h3>📋 Leave Statistics by Type</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Total Requests</th>
                        <th>Approved</th>
                        <th>Denied</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_stats as $stat): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($stat['leave_type_name']); ?></strong></td>
                        <td><?php echo $stat['total_requests']; ?></td>
                        <td><span class="badge badge-success"><?php echo $stat['approved']; ?></span></td>
                        <td><span class="badge badge-danger"><?php echo $stat['denied']; ?></span></td>
                        <td><span class="badge badge-pending"><?php echo $stat['pending']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Department Performance -->
        <div class="table-container">
            <h3>🏢 Department Performance (Today)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total Employees</th>
                        <th>Present Today</th>
                        <th>Absent Today</th>
                        <th>Attendance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_stats as $dept): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong></td>
                        <td><?php echo $dept['total_employees']; ?></td>
                        <td><span class="badge badge-success"><?php echo $dept['present_today']; ?></span></td>
                        <td><span class="badge badge-danger"><?php echo $dept['absent_today']; ?></span></td>
                        <td>
                            <?php 
                                if ($dept['total_employees'] > 0) {
                                    $rate = ($dept['present_today'] / $dept['total_employees']) * 100;
                                    echo round($rate, 1) . '%';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Employees This Month -->
        <div class="table-container">
            <h3>⭐ Top Employees - <?php echo date('F Y'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Present Days</th>
                        <th>Late Days</th>
                        <th>Total Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_employees as $index => $emp): ?>
                    <tr>
                        <td><strong><?php echo $index + 1; ?></strong></td>
                        <td><?php echo htmlspecialchars($emp['name']); ?><br><small><?php echo $emp['employee_code']; ?></small></td>
                        <td><?php echo htmlspecialchars($emp['dept_name']); ?></td>
                        <td><span class="badge badge-success"><?php echo $emp['present_days']; ?></span></td>
                        <td><span class="badge badge-warning"><?php echo $emp['late_days']; ?></span></td>
                        <td><?php echo $emp['attendance_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Attendance Trend Chart
        const trendData = <?php echo json_encode($attendance_trend); ?>;
        const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.map(d => d.month),
                datasets: [
                    {
                        label: 'Present',
                        data: trendData.map(d => d.present),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Late',
                        data: trendData.map(d => d.late),
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Absent',
                        data: trendData.map(d => d.absent),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
