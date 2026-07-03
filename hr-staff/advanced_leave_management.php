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

// Handle cancel leave request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_leave') {
    $leave_id = $_POST['leave_id'];
    $conn->query("UPDATE leave_requests SET status = 'cancelled' WHERE leave_id = $leave_id");
    $message = '<div class="alert alert-success">✓ Leave request cancelled successfully!</div>';
}

// Get all leave requests with approval details
$current_month = date('m');
$current_year = date('Y');

$leave_requests = $conn->query("
    SELECT 
        lr.*,
        e.first_name,
        e.last_name,
        e.employee_code,
        e.position,
        d.dept_name,
        lt.leave_type_name,
        CONCAT(ap.first_name, ' ', ap.last_name) as approved_by_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    JOIN departments d ON e.dept_id = d.dept_id
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    LEFT JOIN employees ap ON lr.approved_by = ap.employee_id
    ORDER BY lr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Separate by status
$pending = array_filter($leave_requests, fn($r) => $r['status'] === 'pending');
$approved = array_filter($leave_requests, fn($r) => $r['status'] === 'approved');
$denied = array_filter($leave_requests, fn($r) => $r['status'] === 'denied');
$cancelled = array_filter($leave_requests, fn($r) => $r['status'] === 'cancelled');

// Statistics
$total_requests = count($leave_requests);
$pending_count = count($pending);
$approved_count = count($approved);
$denied_count = count($denied);
$cancelled_count = count($cancelled);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .status-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .filter-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .leave-card {
            background: white;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .leave-card.approved {
            border-left-color: #28a745;
        }
        .leave-card.denied {
            border-left-color: #dc3545;
        }
        .leave-card.cancelled {
            border-left-color: #6c757d;
        }
        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .leave-header h4 {
            margin: 0;
            color: #333;
        }
        .leave-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 14px;
        }
        .leave-detail {
            color: #666;
        }
        .leave-detail strong {
            color: #333;
        }
        .leave-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>HRGetafe - Advanced Leave Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>

        <!-- Statistics -->
        <div class="dashboard" style="margin-bottom: 2rem;">
            <div class="card">
                <h3>📋 Total Requests</h3>
                <div class="stat-number"><?php echo $total_requests; ?></div>
            </div>
            <div class="card">
                <h3>⏳ Pending</h3>
                <div class="stat-number" style="color: #ffc107;"><?php echo $pending_count; ?></div>
            </div>
            <div class="card">
                <h3>✅ Approved</h3>
                <div class="stat-number" style="color: #28a745;"><?php echo $approved_count; ?></div>
            </div>
            <div class="card">
                <h3>❌ Denied</h3>
                <div class="stat-number" style="color: #dc3545;"><?php echo $denied_count; ?></div>
            </div>
            <div class="card">
                <h3>🚫 Cancelled</h3>
                <div class="stat-number" style="color: #6c757d;"><?php echo $cancelled_count; ?></div>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="status-filter">
            <button class="filter-btn active" onclick="filterLeaves('all')">All Requests (<?php echo $total_requests; ?>)</button>
            <button class="filter-btn" onclick="filterLeaves('pending')">Pending (<?php echo $pending_count; ?>)</button>
            <button class="filter-btn" onclick="filterLeaves('approved')">Approved (<?php echo $approved_count; ?>)</button>
            <button class="filter-btn" onclick="filterLeaves('denied')">Denied (<?php echo $denied_count; ?>)</button>
            <button class="filter-btn" onclick="filterLeaves('cancelled')">Cancelled (<?php echo $cancelled_count; ?>)</button>
        </div>

        <!-- Leave Requests List -->
        <div class="table-container">
            <h3>📋 All Leave Requests with Approval Trail</h3>

            <!-- Pending Leaves -->
            <div id="pending-section">
                <h4 style="color: #ffc107; margin-top: 2rem; margin-bottom: 1rem;">⏳ Pending Approvals</h4>
                <?php if (!empty($pending)): ?>
                    <?php foreach ($pending as $leave): ?>
                    <div class="leave-card">
                        <div class="leave-header">
                            <div>
                                <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></h4>
                                <small><?php echo $leave['employee_code']; ?> • <?php echo htmlspecialchars($leave['position']); ?></small>
                            </div>
                            <span class="badge badge-pending">Pending</span>
                        </div>
                        <div class="leave-details">
                            <div class="leave-detail">
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Period:</strong> <?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Days:</strong> <?php echo $leave['number_of_days']; ?> day(s)
                            </div>
                            <div class="leave-detail">
                                <strong>Department:</strong> <?php echo htmlspecialchars($leave['dept_name']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Applied:</strong> <?php echo format_datetime($leave['created_at']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card" style="text-align: center; color: #666;">
                        ✓ No pending leave requests
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Leaves -->
            <div id="approved-section">
                <h4 style="color: #28a745; margin-top: 2rem; margin-bottom: 1rem;">✅ Approved Leaves</h4>
                <?php if (!empty($approved)): ?>
                    <?php foreach ($approved as $leave): ?>
                    <div class="leave-card approved">
                        <div class="leave-header">
                            <div>
                                <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></h4>
                                <small><?php echo $leave['employee_code']; ?> • <?php echo htmlspecialchars($leave['position']); ?></small>
                            </div>
                            <span class="badge badge-success">Approved</span>
                        </div>
                        <div class="leave-details">
                            <div class="leave-detail">
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Period:</strong> <?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Days:</strong> <?php echo $leave['number_of_days']; ?> day(s)
                            </div>
                            <div class="leave-detail">
                                <strong>Approved By:</strong> <?php echo $leave['approved_by_name'] ? htmlspecialchars($leave['approved_by_name']) : '-'; ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Approved On:</strong> <?php echo $leave['approved_date'] ? format_datetime($leave['approved_date']) : '-'; ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card" style="text-align: center; color: #666;">
                        No approved leave requests
                    </div>
                <?php endif; ?>
            </div>

            <!-- Denied Leaves -->
            <div id="denied-section">
                <h4 style="color: #dc3545; margin-top: 2rem; margin-bottom: 1rem;">❌ Denied Leaves</h4>
                <?php if (!empty($denied)): ?>
                    <?php foreach ($denied as $leave): ?>
                    <div class="leave-card denied">
                        <div class="leave-header">
                            <div>
                                <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></h4>
                                <small><?php echo $leave['employee_code']; ?> • <?php echo htmlspecialchars($leave['position']); ?></small>
                            </div>
                            <span class="badge badge-danger">Denied</span>
                        </div>
                        <div class="leave-details">
                            <div class="leave-detail">
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Period:</strong> <?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Days:</strong> <?php echo $leave['number_of_days']; ?> day(s)
                            </div>
                            <div class="leave-detail">
                                <strong>Denied By:</strong> <?php echo $leave['approved_by_name'] ? htmlspecialchars($leave['approved_by_name']) : '-'; ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Denied On:</strong> <?php echo $leave['approved_date'] ? format_datetime($leave['approved_date']) : '-'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card" style="text-align: center; color: #666;">
                        No denied leave requests
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cancelled Leaves -->
            <div id="cancelled-section">
                <h4 style="color: #6c757d; margin-top: 2rem; margin-bottom: 1rem;">🚫 Cancelled Leaves</h4>
                <?php if (!empty($cancelled)): ?>
                    <?php foreach ($cancelled as $leave): ?>
                    <div class="leave-card cancelled">
                        <div class="leave-header">
                            <div>
                                <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></h4>
                                <small><?php echo $leave['employee_code']; ?> • <?php echo htmlspecialchars($leave['position']); ?></small>
                            </div>
                            <span class="badge badge-pending">Cancelled</span>
                        </div>
                        <div class="leave-details">
                            <div class="leave-detail">
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Period:</strong> <?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?>
                            </div>
                            <div class="leave-detail">
                                <strong>Days:</strong> <?php echo $leave['number_of_days']; ?> day(s)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card" style="text-align: center; color: #666;">
                        No cancelled leave requests
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterLeaves(status) {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Show/hide sections
            document.querySelectorAll('[id$="-section"]').forEach(section => {
                section.style.display = status === 'all' || section.id.includes(status) ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
