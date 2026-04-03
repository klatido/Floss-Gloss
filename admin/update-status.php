<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff']);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['system_admin', 'staff', 'admin'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status_raw = trim($_GET['status'] ?? '');
$new_status = strtolower($new_status_raw);

$allowed_statuses = ['approved', 'rejected'];

if ($appointment_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
    header("Location: manage-appointments.php?message=invalid_status");
    exit();
}

/* get current appointment */
$check_sql = "
    SELECT appointment_id, status
    FROM appointments
    WHERE appointment_id = ?
    LIMIT 1
";
$check_stmt = mysqli_prepare($conn, $check_sql);

if (!$check_stmt) {
    header("Location: manage-appointments.php?message=error");
    exit();
}

mysqli_stmt_bind_param($check_stmt, "i", $appointment_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (!$check_result || mysqli_num_rows($check_result) === 0) {
    mysqli_stmt_close($check_stmt);
    header("Location: manage-appointments.php?message=not_found");
    exit();
}

$appointment = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

$old_status = strtolower(trim($appointment['status'] ?? 'pending'));

/* optional: only allow update when current status is pending */
if ($old_status !== 'pending') {
    header("Location: manage-appointments.php?message=already_updated");
    exit();
}

/* update appointment */
$update_sql = "
    UPDATE appointments
    SET status = ?, last_updated_by = ?
    WHERE appointment_id = ?
    LIMIT 1
";
$update_stmt = mysqli_prepare($conn, $update_sql);

if (!$update_stmt) {
    header("Location: manage-appointments.php?message=error");
    exit();
}

mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $user_id, $appointment_id);
$updated = mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

if (!$updated) {
    header("Location: manage-appointments.php?message=error");
    exit();
}

/* insert history if table exists and works */
$history_sql = "
    INSERT INTO appointment_status_history
    (appointment_id, old_status, new_status, action_by, action_notes)
    VALUES (?, ?, ?, ?, ?)
";
$history_stmt = mysqli_prepare($conn, $history_sql);

if ($history_stmt) {
    $action_notes = ($new_status === 'approved')
        ? 'Appointment approved by admin/staff'
        : 'Appointment rejected by admin/staff';

    mysqli_stmt_bind_param($history_stmt, "issis", $appointment_id, $old_status, $new_status, $user_id, $action_notes);
    mysqli_stmt_execute($history_stmt);
    mysqli_stmt_close($history_stmt);
}

if ($new_status === 'approved') {
    header("Location: manage-appointments.php?message=approved");
    exit();
}

header("Location: manage-appointments.php?message=rejected");
exit();