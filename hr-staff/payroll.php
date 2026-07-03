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

// Handle payroll generation
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Get all active employees
    $employees = $conn->query("SELECT * FROM employees WHERE status = 'active'")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($employees as $emp) {
        // Check if payroll already exists
        $check = $conn->query("SELECT payroll_id FROM payroll WHERE employee_id = {$emp['employee_id']} AND payroll_month = $month AND payroll_year = $year");
        
        if ($check->num_rows === 0) {
            $gross_salary = $emp['salary'];
            $deductions = $gross_salary * 0.1; // 10% deduction
            $net_salary = $gross_salary - $deductions;
            
            $conn->query("INSERT INTO payroll (employee_id, payroll_month, payroll_year, gross_salary, deductions, net_salary, status) VALUES ({$emp['employee_id']}, $month, $year, $gross_salary, $deductions, $net_salary, 'processed')");
        }
    }
    
    $message = '<div class="alert alert-success">✓ Payroll for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' generated successfully!</div>';
}

// Get current month/year payroll
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

$payroll_records = $conn->query("
    SELECT p.*, e.employee_code, e.first_name, e.last_name, e.position
    FROM payroll p
    JOIN employees e ON p.employee_id = e.employee_id
    WHERE p.payroll_month = $current_month AND p.payroll_year = $current_year
    ORDER BY e.first_name
")->fetch_all(MYSQLI_ASSOC);

// Get payroll summary
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(gross_salary) as total_gross,
        SUM(deductions) as total_deductions,
        SUM(net_salary) as total_net
    FROM payroll
    WHERE payroll_month = $current_month AND payroll_year = $current_year
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Processing - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Payroll Processing</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <!-- Generate Payroll Form -->
        <div class="table-container">
            <h3>📊 Generate Payroll</h3>
            
            <?php if ($message) echo $message; ?>
            
            <form method="POST" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
                <input type="hidden" name="action" value="generate_payroll">
                
                <div class="form-group">
                    <label for="month">Month *</label>
                    <select id="month" name="month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == date('m') ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year">Year *</label>
                    <select id="year" name="year" required>
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success" style="align-self: flex-end;">Generate Payroll</button>
            </form>
        </div>

        <!-- Payroll Summary -->
        <?php if ($summary['total_records'] > 0): ?>
        <div class="dashboard" style="margin-bottom: 2rem;">
            <div class="card">
                <h3>📋 Records</h3>
                <div class="stat-number"><?php echo $summary['total_records']; ?></div>
                <p>Employees processed</p>
            </div>
            <div class="card">
                <h3>💰 Total Gross</h3>
                <div class="stat-number" style="font-size: 20px;">₱<?php echo number_format($summary['total_gross'], 2); ?></div>
            </div>
            <div class="card">
                <h3>📉 Total Deductions</h3>
                <div class="stat-number" style="font-size: 20px;">₱<?php echo number_format($summary['total_deductions'], 2); ?></div>
            </div>
            <div class="card">
                <h3>✓ Total Net</h3>
                <div class="stat-number" style="font-size: 20px;">₱<?php echo number_format($summary['total_net'], 2); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payroll Records Table -->
        <div class="table-container">
            <h3>📑 Payroll Records for <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payroll_records)): ?>
                        <?php foreach ($payroll_records as $record): ?>
                        <tr>
                            <td><?php echo $record['employee_code']; ?></td>
                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['position']); ?></td>
                            <td>₱<?php echo number_format($record['gross_salary'], 2); ?></td>
                            <td>₱<?php echo number_format($record['deductions'], 2); ?></td>
                            <td><strong>₱<?php echo number_format($record['net_salary'], 2); ?></strong></td>
                            <td>
                                <?php if ($record['status'] === 'processed'): ?>
                                    <span class="badge badge-success">Processed</span>
                                <?php elseif ($record['status'] === 'paid'): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No payroll records for this month</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>