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
$logged_in_employee = get_employee_info($conn, $employee_id);

// Get employee list
$employees = $conn->query("
    SELECT e.*, d.dept_name
    FROM employees e
    JOIN departments d ON e.dept_id = d.dept_id
    ORDER BY e.first_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Directory - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .employee-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        .employee-card .name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .employee-card .position {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 0.5rem;
        }
        .employee-card .info {
            color: #666;
            font-size: 13px;
            margin-bottom: 0.3rem;
        }
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Employee Directory</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($logged_in_employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <div class="card" style="margin-bottom: 2rem;">
            <h3>👥 All Employees (<?php echo count($employees); ?>)</h3>
            <p>Click on any employee to view detailed profile</p>
        </div>

        <div class="employee-grid">
            <?php foreach ($employees as $emp): ?>
            <div class="employee-card" onclick="location.href='employee_profile.php?id=<?php echo $emp['employee_id']; ?>';">
                <div class="name"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                <div class="position"><?php echo htmlspecialchars($emp['position']); ?></div>
                <div class="info"><strong>ID:</strong> <?php echo $emp['employee_code']; ?></div>
                <div class="info"><strong>Department:</strong> <?php echo htmlspecialchars($emp['dept_name']); ?></div>
                <div class="info"><strong>Email:</strong> <?php echo htmlspecialchars($emp['email']); ?></div>
                <div class="info"><strong>Phone:</strong> <?php echo htmlspecialchars($emp['phone']); ?></div>
                <div class="info" style="margin-top: 0.8rem;">
                    <?php if ($emp['status'] === 'active'): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
