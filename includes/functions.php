<?php
// Common Functions for HRGetafe

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Check user role
function check_role($required_role) {
    if ($_SESSION['role_id'] !== $required_role) {
        die("Access Denied: Insufficient permissions");
    }
}

// Format date
function format_date($date) {
    return date('M d, Y', strtotime($date));
}

// Format datetime
function format_datetime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

// Get role name
function get_role_name($role_id) {
    $roles = [
        1 => 'HR Administrator',
        2 => 'HR Staff',
        3 => 'Department Head',
        4 => 'Employee'
    ];
    return $roles[$role_id] ?? 'Unknown';
}

// Get employee name
function get_employee_name($conn, $employee_id) {
    $result = $conn->query("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE employee_id = $employee_id");
    $row = $result->fetch_assoc();
    return $row['full_name'] ?? 'Unknown';
}

// Get employee info
function get_employee_info($conn, $employee_id) {
    $result = $conn->query("SELECT * FROM employees WHERE employee_id = $employee_id");
    return $result->fetch_assoc();
}

// Calculate leave balance
function calculate_leave_balance($conn, $employee_id, $leave_type_id) {
    $current_year = date('Y');
    
    // Get max days for leave type
    $max_result = $conn->query("SELECT max_days_per_year FROM leave_types WHERE leave_type_id = $leave_type_id");
    $max_days = $max_result->fetch_assoc()['max_days_per_year'];
    
    // Count approved leaves this year
    $used_result = $conn->query("SELECT COALESCE(SUM(number_of_days), 0) as used_days FROM leave_requests WHERE employee_id = $employee_id AND leave_type_id = $leave_type_id AND status = 'approved' AND YEAR(start_date) = $current_year");
    $used_days = $used_result->fetch_assoc()['used_days'];
    
    return $max_days - $used_days;
}

// Get department name
function get_department_name($conn, $dept_id) {
    $result = $conn->query("SELECT dept_name FROM departments WHERE dept_id = $dept_id");
    $row = $result->fetch_assoc();
    return $row['dept_name'] ?? 'Unknown';
}

// Check if employee clocked in today
function is_clocked_in_today($conn, $employee_id) {
    $today = date('Y-m-d');
    $result = $conn->query("SELECT attendance_id FROM attendance WHERE employee_id = $employee_id AND attendance_date = '$today' AND clock_in IS NOT NULL");
    return $result->num_rows > 0;
}

// Get today's attendance record
function get_today_attendance($conn, $employee_id) {
    $today = date('Y-m-d');
    $result = $conn->query("SELECT * FROM attendance WHERE employee_id = $employee_id AND attendance_date = '$today' LIMIT 1");
    return $result->fetch_assoc();
}

// Count pending leaves for department head
function count_pending_leaves($conn, $dept_id) {
    $result = $conn->query("
        SELECT COUNT(*) as count FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        WHERE e.dept_id = $dept_id AND lr.status = 'pending'
    ");
    return $result->fetch_assoc()['count'];
}
?>