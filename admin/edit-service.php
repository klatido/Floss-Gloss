<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$service_id = (int)($_GET['service_id'] ?? 0);

if ($service_id <= 0) {
    header("Location: manage-services.php?error=1");
    exit();
}

$admin_name = "Admin Staff";
$admin_role = "Administrator";

$admin_sql = "
    SELECT u.role, sp.first_name, sp.last_name
    FROM users u
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
    LIMIT 1
";
$admin_stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
mysqli_stmt_execute($admin_stmt);
$admin_result = mysqli_stmt_get_result($admin_stmt);

if ($admin_result && mysqli_num_rows($admin_result) > 0) {
    $admin_row = mysqli_fetch_assoc($admin_result);
    $name = trim(($admin_row['first_name'] ?? '') . ' ' . ($admin_row['last_name'] ?? ''));
    if ($name !== '') {
        $admin_name = $name;
    }

    if (($admin_row['role'] ?? '') === 'system_admin') {
        $admin_role = 'Administrator';
    } elseif (($admin_row['role'] ?? '') === 'staff') {
        $admin_role = 'Staff';
    }
}

$service_sql = "
    SELECT service_id, service_name, description, duration_minutes, price, is_active
    FROM services
    WHERE service_id = ?
    LIMIT 1
";
$service_stmt = mysqli_prepare($conn, $service_sql);
mysqli_stmt_bind_param($service_stmt, "i", $service_id);
mysqli_stmt_execute($service_stmt);
$service_result = mysqli_stmt_get_result($service_stmt);

if (!$service_result || mysqli_num_rows($service_result) === 0) {
    header("Location: manage-services.php?error=1");
    exit();
}

$service = mysqli_fetch_assoc($service_result);

$page_title = "Edit Service | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<style>
    .edit-wrap {
        max-width: 820px;
    }

    .edit-wrap h2 {
        margin: 0 0 8px;
        font-size: 28px;
        color: #0b2454;
    }

    .edit-wrap p {
        margin: 0 0 24px;
        color: #52637a;
        font-size: 16px;
    }

    .edit-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 700;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        font-size: 15px;
        background: #fff;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 110px;
    }

    .form-actions {
        margin-top: 22px;
        display: flex;
        gap: 12px;
    }

    .btn-save {
        border: none;
        background: #0ea5a0;
        color: white;
        padding: 14px 18px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-back {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
        padding: 14px 18px;
        border-radius: 12px;
        font-weight: 700;
    }
</style>

<div class="main">
    <div class="topbar">
        <h1>Edit Service</h1>
        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">⚇</div>
        </div>
    </div>

    <div class="content">
        <section class="panel edit-wrap">
            <h2>Edit Service</h2>
            <p>Update the service details below</p>

            <form method="POST" action="../actions/service-actions.php">
                <input type="hidden" name="update" value="1">
                <input type="hidden" name="service_id" value="<?php echo (int)$service['service_id']; ?>">

                <div class="edit-grid">
                    <div class="form-group full">
                        <label>Service Name</label>
                        <input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" required><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Price (₱)</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($service['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" min="1" value="<?php echo (int)$service['duration_minutes']; ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>Status</label>
                        <select name="is_active" required>
                            <option value="1" <?php echo ((int)$service['is_active'] === 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo ((int)$service['is_active'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Update Service</button>
                    <a href="manage-services.php" class="btn-back">Cancel</a>
                </div>
            </form>
        </section>

        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>