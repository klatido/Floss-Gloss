<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['system_admin', 'staff', 'admin'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header("Location: ../admin/manage-patients.php?message=invalid");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get linked user account of this patient
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        pp.patient_id,
        pp.user_id,
        u.account_status
    FROM patient_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE pp.patient_id = ?
      AND u.role = 'patient'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    header("Location: ../admin/manage-patients.php?message=error");
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: ../admin/manage-patients.php?message=notfound");
    exit();
}

$row = mysqli_fetch_assoc($result);

$target_user_id = (int)$row['user_id'];
$current_status = strtolower(trim($row['account_status'] ?? 'inactive'));

/*
|--------------------------------------------------------------------------
| Toggle only between active and inactive
|--------------------------------------------------------------------------
*/
$new_status = ($current_status === 'active') ? 'inactive' : 'active';

$update_sql = "
    UPDATE users
    SET account_status = ?
    WHERE user_id = ?
      AND role = 'patient'
    LIMIT 1
";

$update_stmt = mysqli_prepare($conn, $update_sql);
if (!$update_stmt) {
    header("Location: ../admin/manage-patients.php?message=error");
    exit();
}

mysqli_stmt_bind_param($update_stmt, "si", $new_status, $target_user_id);

if (mysqli_stmt_execute($update_stmt)) {
    if ($new_status === 'inactive') {
        header("Location: ../admin/manage-patients.php?message=deactivated");
    } else {
        header("Location: ../admin/manage-patients.php?message=activated");
    }
    exit();
}

header("Location: ../admin/manage-patients.php?message=error");
exit();