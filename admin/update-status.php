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
$action = strtolower(trim($_GET['status'] ?? ''));

$allowed_actions = ['approved', 'rejected', 'completed', 'verify_payment'];

if ($appointment_id <= 0 || !in_array($action, $allowed_actions, true)) {
    header("Location: manage-appointments.php?message=invalid_status");
    exit();
}

/* get appointment info */
$check_sql = "
    SELECT 
        a.appointment_id,
        a.status,
        a.payment_status,
        a.service_id,
        s.price
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE a.appointment_id = ?
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
$old_payment_status = strtolower(trim($appointment['payment_status'] ?? 'pending'));
$price = isset($appointment['price']) ? (float)$appointment['price'] : 0.00;

/* =========================================================
   VERIFY PAYMENT
========================================================= */
if ($action === 'verify_payment') {
    if ($old_status !== 'approved') {
        header("Location: manage-appointments.php?message=invalid_payment");
        exit();
    }

    if ($old_payment_status === 'verified') {
        header("Location: manage-appointments.php?message=already_verified");
        exit();
    }

    $appt_sql = "
        UPDATE appointments
        SET payment_status = 'verified',
            last_updated_by = ?
        WHERE appointment_id = ?
        LIMIT 1
    ";
    $appt_stmt = mysqli_prepare($conn, $appt_sql);

    if ($appt_stmt) {
        mysqli_stmt_bind_param($appt_stmt, "ii", $user_id, $appointment_id);
        mysqli_stmt_execute($appt_stmt);
        mysqli_stmt_close($appt_stmt);
    }

    $payment_sql = "
        UPDATE payments
        SET verification_status = 'verified',
            verified_by = ?,
            payment_date = CURDATE(),
            verification_notes = 'Payment verified by admin/staff'
        WHERE appointment_id = ?
    ";
    $payment_stmt = mysqli_prepare($conn, $payment_sql);

    if ($payment_stmt) {
        mysqli_stmt_bind_param($payment_stmt, "ii", $user_id, $appointment_id);
        mysqli_stmt_execute($payment_stmt);
        mysqli_stmt_close($payment_stmt);
    }

    header("Location: manage-appointments.php?message=payment_verified");
    exit();
}

/* =========================================================
   STATUS RULES
========================================================= */
if ($action === 'approved' && $old_status !== 'pending') {
    header("Location: manage-appointments.php?message=already_updated");
    exit();
}

if ($action === 'rejected' && $old_status !== 'pending') {
    header("Location: manage-appointments.php?message=already_updated");
    exit();
}

if ($action === 'completed' && $old_status !== 'approved') {
    header("Location: manage-appointments.php?message=complete_not_allowed");
    exit();
}

/* =========================================================
   UPDATE APPOINTMENT
========================================================= */
if ($action === 'approved') {
    $update_sql = "
        UPDATE appointments
        SET status = 'approved',
            payment_status = 'pending',
            last_updated_by = ?
        WHERE appointment_id = ?
        LIMIT 1
    ";
} elseif ($action === 'rejected') {
    $update_sql = "
        UPDATE appointments
        SET status = 'rejected',
            payment_status = 'rejected',
            last_updated_by = ?
        WHERE appointment_id = ?
        LIMIT 1
    ";
} else {
    $update_sql = "
        UPDATE appointments
        SET status = 'completed',
            last_updated_by = ?
        WHERE appointment_id = ?
        LIMIT 1
    ";
}

$update_stmt = mysqli_prepare($conn, $update_sql);

if (!$update_stmt) {
    header("Location: manage-appointments.php?message=error");
    exit();
}

mysqli_stmt_bind_param($update_stmt, "ii", $user_id, $appointment_id);
$updated = mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

if (!$updated) {
    header("Location: manage-appointments.php?message=error");
    exit();
}

/* =========================================================
   APPROVED -> CREATE PAYMENT ROW
========================================================= */
if ($action === 'approved') {
    $payment_check_sql = "
        SELECT payment_id
        FROM payments
        WHERE appointment_id = ?
        LIMIT 1
    ";
    $payment_check_stmt = mysqli_prepare($conn, $payment_check_sql);

    if ($payment_check_stmt) {
        mysqli_stmt_bind_param($payment_check_stmt, "i", $appointment_id);
        mysqli_stmt_execute($payment_check_stmt);
        $payment_check_result = mysqli_stmt_get_result($payment_check_stmt);

        $payment_exists = ($payment_check_result && mysqli_num_rows($payment_check_result) > 0);
        mysqli_stmt_close($payment_check_stmt);

        if (!$payment_exists) {
            $insert_payment_sql = "
                INSERT INTO payments
                (
                    appointment_id,
                    amount,
                    payment_method,
                    reference_number,
                    proof_image_path,
                    payment_date,
                    verification_status,
                    verified_by,
                    verification_notes
                )
                VALUES (?, ?, 'other', NULL, NULL, NULL, 'pending', NULL, NULL)
            ";
            $insert_payment_stmt = mysqli_prepare($conn, $insert_payment_sql);

            if ($insert_payment_stmt) {
                mysqli_stmt_bind_param($insert_payment_stmt, "id", $appointment_id, $price);
                mysqli_stmt_execute($insert_payment_stmt);
                mysqli_stmt_close($insert_payment_stmt);
            }
        }
    }
}

/* =========================================================
   REJECTED -> UPDATE PAYMENT IF EXISTS
========================================================= */
if ($action === 'rejected') {
    $reject_payment_sql = "
        UPDATE payments
        SET verification_status = 'rejected',
            verification_notes = 'Appointment rejected'
        WHERE appointment_id = ?
    ";
    $reject_payment_stmt = mysqli_prepare($conn, $reject_payment_sql);

    if ($reject_payment_stmt) {
        mysqli_stmt_bind_param($reject_payment_stmt, "i", $appointment_id);
        mysqli_stmt_execute($reject_payment_stmt);
        mysqli_stmt_close($reject_payment_stmt);
    }
}

/* =========================================================
   HISTORY LOG
========================================================= */
$history_sql = "
    INSERT INTO appointment_status_history
    (appointment_id, old_status, new_status, action_by, action_notes)
    VALUES (?, ?, ?, ?, ?)
";
$history_stmt = mysqli_prepare($conn, $history_sql);

if ($history_stmt) {
    if ($action === 'approved') {
        $new_status = 'approved';
        $action_notes = 'Appointment approved by admin/staff';
    } elseif ($action === 'rejected') {
        $new_status = 'rejected';
        $action_notes = 'Appointment rejected by admin/staff';
    } else {
        $new_status = 'completed';
        $action_notes = 'Appointment marked as completed by admin/staff';
    }

    mysqli_stmt_bind_param($history_stmt, "issis", $appointment_id, $old_status, $new_status, $user_id, $action_notes);
    mysqli_stmt_execute($history_stmt);
    mysqli_stmt_close($history_stmt);
}

if ($action === 'approved') {
    header("Location: manage-appointments.php?message=approved");
    exit();
}

if ($action === 'rejected') {
    header("Location: manage-appointments.php?message=rejected");
    exit();
}

header("Location: manage-appointments.php?message=completed");
exit();