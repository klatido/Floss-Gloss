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

$user_id = $_SESSION['user_id'];
$service_id = (int)($_GET['service_id'] ?? 0);

if ($service_id <= 0) {
    header("Location: manage-services.php");
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

$service_sql = "SELECT * FROM services WHERE service_id = ? LIMIT 1";
$service_stmt = mysqli_prepare($conn, $service_sql);
mysqli_stmt_bind_param($service_stmt, "i", $service_id);
mysqli_stmt_execute($service_stmt);
$service_result = mysqli_stmt_get_result($service_stmt);

if (!$service_result || mysqli_num_rows($service_result) === 0) {
    header("Location: manage-services.php");
    exit();
}

$service = mysqli_fetch_assoc($service_result);

$page_title = "Edit Service | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<style>
    .topbar {
        position: sticky;
        top: 0;
        z-index: 900;
        min-height: 72px;
        background: #ffffff;
        border-bottom: 1px solid #dbe2ea;
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .topbar h1 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .admin-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .admin-meta {
        text-align: right;
    }

    .admin-meta strong {
        display: block;
        font-size: 13px;
    }

    .admin-meta span {
        color: #64748b;
        font-size: 11px;
    }

    .admin-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #d1fae5;
        color: #059669;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .content {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 20px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 16px;
        padding: 20px;
    }

    .services-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 20px;
    }

    .services-top h2 {
        margin: 0;
        font-size: 22px;
        color: #0b2454;
    }

    .services-top p {
        margin: 6px 0 0;
        color: #52637a;
        font-size: 13px;
    }

    .service-form-wrap {
        display: block;
        background: #f8fafc;
        border: 1px solid #dde3ea;
        border-radius: 14px;
        padding: 18px;
    }

    .service-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 700;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        font-size: 13px;
        background: #fff;
        box-sizing: border-box;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 90px;
    }

    .form-actions {
        margin-top: 16px;
        display: flex;
        gap: 10px;
    }

    .btn-save,
    .btn-cancel {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 11px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-save {
        border: none;
        background: #0ea5a0;
        color: #fff;
    }

    .btn-cancel {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
    }

    @media (max-width: 900px) {
        .services-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .service-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Services</h1>

        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">👤</div>
        </div>
    </div>

    <div class="content">
        <div class="services-top">
            <div>
                <h2>Services Management</h2>
                <p>Manage dental services and procedures</p>
            </div>
        </div>

        <section class="panel">
            <div class="service-form-wrap">
                <form method="POST" action="../actions/service-actions.php" enctype="multipart/form-data">
                    <input type="hidden" name="update" value="1">
                    <input type="hidden" name="service_id" value="<?php echo (int)$service['service_id']; ?>">

                    <div class="service-form-grid">
                        <div class="form-group">
                            <label>Service Name</label>
                            <input
                                type="text"
                                name="service_name"
                                value="<?php echo htmlspecialchars($service['service_name']); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input
                                type="number"
                                name="price"
                                step="0.01"
                                min="0"
                                value="<?php echo htmlspecialchars($service['price']); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input
                                type="number"
                                name="duration_minutes"
                                min="1"
                                value="<?php echo (int)$service['duration_minutes']; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" required>
                                <option value="1" <?php echo ((int)$service['is_active'] === 1) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo ((int)$service['is_active'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group full">
                        <label>Current Image</label><br>

                        <?php if (!empty($service['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($service['image_path']); ?>" 
                                style="width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid #ddd;">
                        <?php else: ?>
                            <span style="color:#888;">No image</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full">
                        <label>Change Image</label>
                        <input type="file" name="image" accept="image/*">
                    </div>

                    <!-- IMPORTANT: keep old image -->
                    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($service['image_path']); ?>">
                            <textarea name="description" required><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">Update Service</button>
                        <a href="manage-services.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <div style="flex: 1;"></div>

        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>