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
$message = '';

// Handle add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $middle_name = $_POST['middle_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $position = $_POST['position'];
    $dept_id = $_POST['dept_id'];
    $date_hired = $_POST['date_hired'];
    $salary = $_POST['salary'];
    
    // Generate employee code
    $year = date('Y');
    $count = $conn->query("SELECT COUNT(*) as count FROM employees WHERE YEAR(created_at) = $year")->fetch_assoc()['count'] + 1;
    $employee_code = 'GETAFE-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    $conn->query("INSERT INTO employees (employee_code, first_name, last_name, middle_name, email, phone, position, dept_id, date_hired, salary, status) VALUES ('$employee_code', '$first_name', '$last_name', '$middle_name', '$email', '$phone', '$position', $dept_id, '$date_hired', $salary, 'active')");
    
    $message = '<div class="alert alert-success">✓ Employee added successfully! Employee Code: ' . $employee_code . '</div>';
}

// Get all employees
$employees = $conn->query("SELECT e.*, d.dept_name FROM employees e JOIN departments d ON e.dept_id = d.dept_id ORDER BY e.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get departments
$departments = $conn->query("SELECT * FROM departments")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_employees = count($employees);
$active_employees = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count'];
$inactive_employees = $total_employees - $active_employees;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Staff Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - HR Staff Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="dashboard">
            <div class="card">
                <h3>👥 Total Employees</h3>
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <p>All registered employees</p>
            </div>
            <div class="card">
                <h3>✅ Active</h3>
                <div class="stat-number"><?php echo $active_employees; ?></div>
                <p>Currently working</p>
            </div>
            <div class="card">
                <h3>❌ Inactive</h3>
                <div class="stat-number"><?php echo $inactive_employees; ?></div>
                <p>Not active</p>
            </div>
            <div class="card">
                <h3>🏢 Departments</h3>
                <div class="stat-number"><?php echo count($departments); ?></div>
                <p>Total departments</p>
            </div>
        </div>

        <!-- Add Employee Form -->
        <div class="table-container">
            <h3>➕ Add New Employee</h3>
            
            <?php if ($message) echo $message; ?>
            
            <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <input type="hidden" name="action" value="add_employee">
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>

                <div class="form-group">
                    <label for="position">Position *</label>
                    <input type="text" id="position" name="position" required>
                </div>

                <div class="form-group">
                    <label for="dept_id">Department *</label>
                    <select id="dept_id" name="dept_id" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_hired">Date Hired *</label>
                    <input type="date" id="date_hired" name="date_hired" required>
                </div>

                <div class="form-group">
                    <label for="salary">Salary *</label>
                    <input type="number" id="salary" name="salary" step="0.01" required>
                </div>

                <button type="submit" class="btn btn-success" style="grid-column: 1 / -1;">Add Employee</button>
            </form>
        </div>

        <!-- Employee List -->
        <div class="table-container">
            <h3>📋 Employee List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Hire Date</th>
                        <th>Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo $emp['employee_code']; ?></td>
                            <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['position']); ?></td>
                            <td><?php echo htmlspecialchars($emp['dept_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                            <td><?php echo format_date($emp['date_hired']); ?></td>
                            <td>₱<?php echo number_format($emp['salary'], 2); ?></td>
                            <td>
                                <?php if ($emp['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No employees found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Links -->
        <div class="card" style="text-align: center; margin-top: 2rem;">
            <h3>📌 Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <a href="payroll.php" class="btn btn-primary">View Payroll</a>
                <a href="generate_reports.php" class="btn btn-primary">Generate Reports</a>
            </div>
        </div>
    </div>
</body>
</html>
