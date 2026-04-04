<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Helper: Clean input
|--------------------------------------------------------------------------
*/
function clean($value) {
    return trim($value ?? '');
}

/*
|--------------------------------------------------------------------------
| ADD SERVICE
|--------------------------------------------------------------------------
*/
if (isset($_POST['add'])) {

    $service_name = clean($_POST['service_name']);
    $description = clean($_POST['description']);

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

        $upload_dir = "../assets/services/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES['image']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }

    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 1);
    $created_by = (int)$_SESSION['user_id'];

    if (
        $service_name === '' ||
        $description === '' ||
        $duration_minutes <= 0 ||
        $price < 0
    ) {
        header("Location: ../admin/manage-services.php?error=invalid_input");
        exit();
    }

    $insert_sql = "
        INSERT INTO services
        (service_name, description, image_path, duration_minutes, price, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $insert_sql);

    if (!$stmt) {
        header("Location: ../admin/manage-services.php?error=prepare_failed");
        exit();
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssiddi",
        $service_name,
        $description,
        $image_path,
        $duration_minutes,
        $price,
        $is_active,
        $created_by
    );

    if (mysqli_stmt_execute($stmt)) {
        header("Location: ../admin/manage-services.php?success=added");
    } else {
        header("Location: ../admin/manage-services.php?error=insert_failed");
    }

    mysqli_stmt_close($stmt);
    exit();
}

/*
|--------------------------------------------------------------------------
| UPDATE SERVICE
|--------------------------------------------------------------------------
*/
if (isset($_POST['update'])) {

    $service_id = (int)($_POST['service_id'] ?? 0);
    $service_name = clean($_POST['service_name']);
    $description = clean($_POST['description']);

    $image_path = clean($_POST['existing_image'] ?? '');
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

        $upload_dir = "../assets/services/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES['image']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }

    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 1);

    if (
        $service_id <= 0 ||
        $service_name === '' ||
        $description === '' ||
        $duration_minutes <= 0 ||
        $price < 0
    ) {
        header("Location: ../admin/manage-services.php?error=invalid_input");
        exit();
    }

    $update_sql = "
        UPDATE services
        SET
            service_name = ?,
            description = ?,
            image_path = ?,
            duration_minutes = ?,
            price = ?,
            is_active = ?
        WHERE service_id = ?
    ";

    $stmt = mysqli_prepare($conn, $update_sql);

    if (!$stmt) {
        header("Location: ../admin/manage-services.php?error=prepare_failed");
        exit();
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssiddi",
        $service_name,
        $description,
        $image_path,
        $duration_minutes,
        $price,
        $is_active,
        $service_id
    );

    if (mysqli_stmt_execute($stmt)) {
        header("Location: ../admin/manage-services.php?success=updated");
    } else {
        header("Location: ../admin/manage-services.php?error=update_failed");
    }

    mysqli_stmt_close($stmt);
    exit();
}

/*
|--------------------------------------------------------------------------
| DELETE SERVICE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {

    $service_id = (int)($_GET['delete'] ?? 0);

    if ($service_id <= 0) {
        header("Location: ../admin/manage-services.php?error=invalid_id");
        exit();
    }

    /*
    |----------------------------------------------------------------------
    | CHECK IF SERVICE IS USED IN APPOINTMENTS
    |----------------------------------------------------------------------
    */
    $check_sql = "SELECT COUNT(*) AS total FROM appointments WHERE service_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);

    if (!$check_stmt) {
        header("Location: ../admin/manage-services.php?error=delete_failed");
        exit();
    }

    mysqli_stmt_bind_param($check_stmt, "i", $service_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = $check_result ? mysqli_fetch_assoc($check_result) : null;
    mysqli_stmt_close($check_stmt);

    $used_count = (int)($check_row['total'] ?? 0);

    /*
    |----------------------------------------------------------------------
    | IF USED IN APPOINTMENTS → DEACTIVATE INSTEAD
    |----------------------------------------------------------------------
    */
    if ($used_count > 0) {
        $deactivate_sql = "
            UPDATE services
            SET is_active = 0
            WHERE service_id = ?
        ";
        $stmt = mysqli_prepare($conn, $deactivate_sql);

        if (!$stmt) {
            header("Location: ../admin/manage-services.php?error=delete_failed");
            exit();
        }

        mysqli_stmt_bind_param($stmt, "i", $service_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: ../admin/manage-services.php?success=deactivated");
        } else {
            header("Location: ../admin/manage-services.php?error=delete_failed");
        }

        mysqli_stmt_close($stmt);
        exit();
    }

    /*
    |----------------------------------------------------------------------
    | IF NOT USED → SAFE DELETE
    |----------------------------------------------------------------------
    */
    $image_path = '';
    $image_sql = "SELECT image_path FROM services WHERE service_id = ? LIMIT 1";
    $image_stmt = mysqli_prepare($conn, $image_sql);

    if ($image_stmt) {
        mysqli_stmt_bind_param($image_stmt, "i", $service_id);
        mysqli_stmt_execute($image_stmt);
        $image_result = mysqli_stmt_get_result($image_stmt);
        $image_row = $image_result ? mysqli_fetch_assoc($image_result) : null;
        $image_path = $image_row['image_path'] ?? '';
        mysqli_stmt_close($image_stmt);
    }

    $delete_sql = "DELETE FROM services WHERE service_id = ?";
    $stmt = mysqli_prepare($conn, $delete_sql);

    if (!$stmt) {
        header("Location: ../admin/manage-services.php?error=prepare_failed");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "i", $service_id);

    if (mysqli_stmt_execute($stmt)) {
        if (!empty($image_path) && file_exists($image_path) && is_file($image_path)) {
            @unlink($image_path);
        }

        header("Location: ../admin/manage-services.php?success=deleted");
    } else {
        header("Location: ../admin/manage-services.php?error=delete_failed");
    }

    mysqli_stmt_close($stmt);
    exit();
}

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/
header("Location: ../admin/manage-services.php");
exit();
?>