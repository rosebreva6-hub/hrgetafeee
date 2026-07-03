<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
if ($_SESSION['role_id'] != 4) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    
    // Check leave balance
    $balance = calculate_leave_balance($conn, $employee_id, $leave_type_id);
    
    if ($days > $balance) {
        $message = '<div class="alert alert-danger">✗ Insufficient leave balance. You have ' . $balance . ' days available.</div>';
    } else {
        $conn->query("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status) VALUES ($employee_id, $leave_type_id, '$start_date', '$end_date', $days, '$reason', 'pending')");
        $message = '<div class="alert alert-success">✓ Leave request submitted successfully. Pending approval.</div>';
    }
}

$leave_types = $conn->query("SELECT * FROM leave_types")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Apply for Leave</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div style="max-width: 600px; margin: 0 auto;">
            <div class="card">
                <h3>📝 Leave Application Form</h3>
                
                <?php if ($message) echo $message; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="leave_type_id">Leave Type *</label>
                        <select id="leave_type_id" name="leave_type_id" required>
                            <option value="">-- Select Leave Type --</option>
                            <?php foreach ($leave_types as $type): ?>
                                <?php $balance = calculate_leave_balance($conn, $employee_id, $type['leave_type_id']); ?>
                                <option value="<?php echo $type['leave_type_id']; ?>">
                                    <?php echo htmlspecialchars($type['leave_type_name']); ?> (<?php echo $balance; ?> days available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Leave *</label>
                        <textarea id="reason" name="reason" required placeholder="Enter your reason for leave"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Submit Leave Request</button>
                    <a href="dashboard.php" class="btn btn-secondary btn-block" style="text-align: center;">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
