<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Add service
|--------------------------------------------------------------------------
*/
if (isset($_POST['add'])) {
    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 1);
    $created_by = (int)($_SESSION['user_id'] ?? 0);

    if ($service_name === '' || $description === '' || $duration_minutes <= 0 || $price < 0) {
        header("Location: ../admin/manage-services.php?error=1");
        exit();
    }

    $insert_sql = "
        INSERT INTO services (service_name, description, duration_minutes, price, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "ssidii", $service_name, $description, $duration_minutes, $price, $is_active, $created_by);

    if (mysqli_stmt_execute($insert_stmt)) {
        header("Location: ../admin/manage-services.php?success=added");
    } else {
        header("Location: ../admin/manage-services.php?error=1");
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| Update service
|--------------------------------------------------------------------------
*/
if (isset($_POST['update'])) {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($service_id <= 0 || $service_name === '' || $description === '' || $duration_minutes <= 0 || $price < 0) {
        header("Location: ../admin/manage-services.php?error=1");
        exit();
    }

    $update_sql = "
        UPDATE services
        SET service_name = ?, description = ?, duration_minutes = ?, price = ?, is_active = ?
        WHERE service_id = ?
    ";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ssidii", $service_name, $description, $duration_minutes, $price, $is_active, $service_id);

    if (mysqli_stmt_execute($update_stmt)) {
        header("Location: ../admin/manage-services.php?success=updated");
    } else {
        header("Location: ../admin/manage-services.php?error=1");
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| Delete service
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $service_id = (int)($_GET['delete'] ?? 0);

    if ($service_id <= 0) {
        header("Location: ../admin/manage-services.php?error=1");
        exit();
    }

    $delete_sql = "DELETE FROM services WHERE service_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $service_id);

    if (mysqli_stmt_execute($delete_stmt)) {
        header("Location: ../admin/manage-services.php?success=deleted");
    } else {
        header("Location: ../admin/manage-services.php?error=1");
    }
    exit();
}

header("Location: ../admin/manage-services.php");
exit();