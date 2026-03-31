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

$services = [];
$services_sql = "
    SELECT service_id, service_name, description, duration_minutes, price, is_active, created_by, created_at, updated_at
    FROM services
    ORDER BY service_name ASC
";
$services_result = mysqli_query($conn, $services_sql);
if ($services_result) {
    while ($row = mysqli_fetch_assoc($services_result)) {
        $services[] = $row;
    }
}

$page_title = "Services | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<style>
    .services-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 24px;
    }

    .services-top h2 {
        margin: 0;
        font-size: 28px;
        color: #0b2454;
    }

    .services-top p {
        margin: 8px 0 0;
        color: #52637a;
        font-size: 16px;
    }

    .add-btn {
        border: none;
        background: #0ea5a0;
        color: #fff;
        padding: 14px 20px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
    }

    .service-form-wrap {
        display: none;
        background: #f8fafc;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 26px;
    }

    .service-form-wrap.show {
        display: block;
    }

    .service-form-grid {
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
        min-height: 100px;
    }

    .form-actions {
        margin-top: 18px;
        display: flex;
        gap: 12px;
    }

    .btn-save, .btn-cancel {
        padding: 13px 18px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
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

    .services-table {
        width: 100%;
        border-collapse: collapse;
    }

    .services-table th {
        text-align: left;
        padding: 14px 10px;
        border-bottom: 1px solid #dbe2ea;
    }

    .services-table td {
        padding: 16px 10px;
        vertical-align: top;
        border-bottom: 1px solid #eef2f7;
    }

    .service-name {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .service-desc {
        color: #667085;
        font-size: 14px;
        line-height: 1.5;
    }

    .pill {
        display: inline-block;
        padding: 7px 12px;
        border: 1px solid #d1d5db;
        border-radius: 999px;
        font-size: 13px;
        background: #fafafa;
    }

    .status-pill {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }

    .status-pill.active {
        background: #ecfdf3;
        color: #027a48;
    }

    .status-pill.inactive {
        background: #fef3f2;
        color: #b42318;
    }

    .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        color: #111827;
        font-size: 18px;
        margin-right: 8px;
    }

    .icon-btn.delete {
        color: #dc2626;
    }

    .toast {
        position: fixed;
        right: 20px;
        bottom: 20px;
        background: #fff;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.10);
        z-index: 2000;
    }
</style>

<div class="main">
    <div class="topbar">
        <h1>Services</h1>
        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">⚇</div>
        </div>
    </div>

    <div class="content">
        <div class="services-top">
            <div>
                <h2>Services Management</h2>
                <p>Manage dental services and procedures</p>
            </div>
            <button type="button" class="add-btn" onclick="toggleServiceForm()">＋ Add Service</button>
        </div>

        <section class="panel">
            <div id="serviceFormWrap" class="service-form-wrap">
                <form method="POST" action="../actions/service-actions.php">
                    <div class="service-form-grid">
                        <div class="form-group">
                            <label>Service Name</label>
                            <input type="text" name="service_name" required>
                        </div>

                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" name="price" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration_minutes" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="description" required></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="add" class="btn-save">Save Service</button>
                        <button type="button" class="btn-cancel" onclick="toggleServiceForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <h3>All Services</h3>
            <p><?php echo count($services); ?> services available</p>

            <table class="services-table">
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($services) > 0): ?>
                        <?php foreach ($services as $row): ?>
                            <tr>
                                <td>
                                    <div class="service-name"><?php echo htmlspecialchars($row['service_name']); ?></div>
                                    <div class="service-desc"><?php echo htmlspecialchars($row['description'] ?? 'No description available.'); ?></div>
                                </td>
                                <td><span class="pill">Dental Service</span></td>
                                <td>₱<?php echo number_format((float)$row['price'], 0); ?></td>
                                <td><?php echo (int)$row['duration_minutes']; ?> mins</td>
                                <td>
                                    <span class="status-pill <?php echo ((int)$row['is_active'] === 1) ? 'active' : 'inactive'; ?>">
                                        <?php echo ((int)$row['is_active'] === 1) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit-service.php?service_id=<?php echo (int)$row['service_id']; ?>" class="icon-btn" title="Edit">✎</a>
                                    <a href="../actions/service-actions.php?delete=<?php echo (int)$row['service_id']; ?>" class="icon-btn delete" onclick="return confirm('Delete this service?');" title="Delete">🗑</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No services found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php if (isset($_GET['success'])): ?>
            <div class="toast">
                <?php
                    if ($_GET['success'] === 'added') echo 'Service added successfully!';
                    elseif ($_GET['success'] === 'deleted') echo 'Service deleted successfully!';
                    elseif ($_GET['success'] === 'updated') echo 'Service updated successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<script>
function toggleServiceForm() {
    document.getElementById('serviceFormWrap').classList.toggle('show');
}
</script>