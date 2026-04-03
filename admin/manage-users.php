<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireRole(['system_admin']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* --------------------------------------------------
   TOPBAR INFO
-------------------------------------------------- */
$admin_name = "System Administrator";
$admin_role = "Administrator";

$admin_sql = "
    SELECT u.role, sp.first_name, sp.last_name
    FROM users u
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
    LIMIT 1
";
$admin_stmt = mysqli_prepare($conn, $admin_sql);

if ($admin_stmt) {
    mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);

    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_result);
        $name = trim(($admin_row['first_name'] ?? '') . ' ' . ($admin_row['last_name'] ?? ''));
        if ($name !== '') {
            $admin_name = $name;
        }
    }

    mysqli_stmt_close($admin_stmt);
}

/* --------------------------------------------------
   DELETE USER
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);

    if ($target_user_id > 0) {
        $delete_sql = "
            DELETE FROM users
            WHERE user_id = ?
              AND role IN ('staff', 'dentist')
            LIMIT 1
        ";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);

        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "i", $target_user_id);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
    }

    header("Location: manage-users.php?message=deleted");
    exit();
}

/* --------------------------------------------------
   DEACTIVATE / ACTIVATE USER
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    $allowed_statuses = ['active', 'deactivated'];

    if ($target_user_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        $status_sql = "
            UPDATE users
            SET account_status = ?
            WHERE user_id = ?
              AND role IN ('staff', 'dentist')
            LIMIT 1
        ";
        $status_stmt = mysqli_prepare($conn, $status_sql);

        if ($status_stmt) {
            mysqli_stmt_bind_param($status_stmt, "si", $new_status, $target_user_id);
            mysqli_stmt_execute($status_stmt);
            mysqli_stmt_close($status_stmt);
        }
    }

    header("Location: manage-users.php?message=status_updated");
    exit();
}

/* --------------------------------------------------
   UPDATE PASSWORD
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($target_user_id <= 0) {
        header("Location: manage-users.php?message=invalid_user");
        exit();
    }

    if ($new_password === '' || $confirm_password === '') {
        header("Location: manage-users.php?message=password_empty");
        exit();
    }

    if (strlen($new_password) < 8) {
        header("Location: manage-users.php?message=password_short");
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: manage-users.php?message=password_mismatch");
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $password_sql = "
        UPDATE users
        SET password_hash = ?
        WHERE user_id = ?
          AND role IN ('staff', 'dentist')
        LIMIT 1
    ";
    $password_stmt = mysqli_prepare($conn, $password_sql);

    if ($password_stmt) {
        mysqli_stmt_bind_param($password_stmt, "si", $hashed_password, $target_user_id);
        mysqli_stmt_execute($password_stmt);
        mysqli_stmt_close($password_stmt);
    }

    header("Location: manage-users.php?message=password_updated");
    exit();
}

/* --------------------------------------------------
   FETCH USERS
-------------------------------------------------- */
$users = [];

$users_sql = "
    SELECT
        u.user_id,
        u.role,
        u.email,
        u.phone,
        u.account_status,
        u.created_at,

        dp.first_name AS dentist_first_name,
        dp.middle_name AS dentist_middle_name,
        dp.last_name AS dentist_last_name,
        dp.specialization,

        sp.first_name AS staff_first_name,
        sp.middle_name AS staff_middle_name,
        sp.last_name AS staff_last_name,
        sp.position

    FROM users u
    LEFT JOIN dentist_profiles dp ON u.user_id = dp.user_id
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    WHERE u.role IN ('staff', 'dentist')
    ORDER BY u.role ASC, u.created_at DESC, u.user_id DESC
";

$users_result = mysqli_query($conn, $users_sql);

if ($users_result) {
    while ($row = mysqli_fetch_assoc($users_result)) {
        if (($row['role'] ?? '') === 'dentist') {
            $full_name = trim(
                ($row['dentist_first_name'] ?? '') . ' ' .
                ($row['dentist_middle_name'] ?? '') . ' ' .
                ($row['dentist_last_name'] ?? '')
            );
            $display_name = $full_name !== '' ? 'Dr. ' . preg_replace('/\s+/', ' ', trim($full_name)) : 'Unknown Dentist';
            $extra_info = $row['specialization'] ?: 'General Dentistry';
        } else {
            $full_name = trim(
                ($row['staff_first_name'] ?? '') . ' ' .
                ($row['staff_middle_name'] ?? '') . ' ' .
                ($row['staff_last_name'] ?? '')
            );
            $display_name = $full_name !== '' ? preg_replace('/\s+/', ' ', trim($full_name)) : 'Unknown Staff';
            $extra_info = $row['position'] ?: 'Clinic Staff';
        }

        $row['display_name'] = $display_name;
        $row['extra_info'] = $extra_info;
        $users[] = $row;
    }
}

$page_title = "Users | Floss & Gloss Dental";
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
        gap: 20px;
    }

    .page-top h2 {
        margin: 0;
        font-size: 22px;
        color: #0b2454;
    }

    .page-top p {
        margin: 6px 0 0;
        color: #52637a;
        font-size: 13px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
    }

    .users-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .user-card {
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 20px;
        background: #fff;
    }

    .user-head {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .user-name {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .user-meta {
        color: #64748b;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .status-pill,
    .role-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .status-pill.active {
        background: #dcfce7;
        color: #166534;
    }

    .status-pill.inactive {
        background: #eef2f7;
        color: #334155;
    }

    .status-pill.deactivated {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-pill.suspended {
        background: #fef3c7;
        color: #92400e;
    }

    .role-pill.staff {
        background: #e0f2fe;
        color: #075985;
    }

    .role-pill.dentist {
        background: #ede9fe;
        color: #5b21b6;
    }

    .user-info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }

    .info-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px;
    }

    .info-box .label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 6px;
    }

    .info-box .value {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        word-break: break-word;
    }

    .user-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .user-actions form {
        margin: 0;
    }

    .btn-danger,
    .btn-warning,
    .btn-primary,
    .btn-secondary {
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        background: #fff;
    }

    .btn-primary {
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
    }

    .btn-warning {
        border: 1px solid #fde68a;
        color: #b45309;
    }

    .btn-secondary {
        border: 1px solid #cbd5e1;
        color: #334155;
    }

    .btn-danger {
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    .password-form {
        margin-top: 16px;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #f8fafc;
        display: none;
    }

    .password-form.active {
        display: block;
    }

    .password-form h4 {
        margin: 0 0 12px;
        font-size: 15px;
        color: #0f172a;
    }

    .password-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }

    .form-group input {
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        padding: 11px 12px;
        font-size: 14px;
        outline: none;
        background: #fff;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .empty-state {
        text-align: center;
        color: #64748b;
        padding: 32px 20px;
    }

    .toast {
        position: fixed;
        right: 20px;
        bottom: 20px;
        background: #ffffff;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        z-index: 5000;
        min-width: 270px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }

    .toast.success .toast-icon {
        background: #16a34a;
    }

    .toast.error .toast-icon {
        background: #dc2626;
    }

    .toast-icon {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    @media (max-width: 1100px) {
        .user-info-grid,
        .password-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 900px) {
        .user-head {
            flex-direction: column;
        }
    }

    @media (max-width: 640px) {
        .user-info-grid,
        .password-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Users</h1>

        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">👤</div>
        </div>
    </div>

    <div class="content">
        <div class="page-top">
            <h2>User Account Management</h2>
            <p>Manage staff and dentist accounts only.</p>
        </div>

        <section class="panel">
            <div class="users-list">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $row): ?>
                        <?php
                            $is_active = ($row['account_status'] === 'active');
                            $toggle_to = $is_active ? 'deactivated' : 'active';
                            $toggle_label = $is_active ? 'Deactivate' : 'Activate';
                            $toggle_confirm = $is_active
                                ? 'Deactivate this user account?'
                                : 'Activate this user account?';
                        ?>
                        <div class="user-card">
                            <div class="user-head">
                                <div>
                                    <div class="user-name"><?php echo htmlspecialchars($row['display_name']); ?></div>
                                    <div class="user-meta">
                                        <span><?php echo htmlspecialchars($row['email']); ?></span>
                                        <span><?php echo htmlspecialchars($row['extra_info']); ?></span>
                                    </div>
                                </div>

                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <span class="role-pill <?php echo htmlspecialchars($row['role']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($row['role'])); ?>
                                    </span>
                                    <span class="status-pill <?php echo htmlspecialchars($row['account_status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($row['account_status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="user-info-grid">
                                <div class="info-box">
                                    <div class="label">Email</div>
                                    <div class="value"><?php echo htmlspecialchars($row['email'] ?: 'N/A'); ?></div>
                                </div>

                                <div class="info-box">
                                    <div class="label">Phone</div>
                                    <div class="value"><?php echo htmlspecialchars($row['phone'] ?: 'N/A'); ?></div>
                                </div>

                                <div class="info-box">
                                    <div class="label">Created</div>
                                    <div class="value">
                                        <?php
                                        echo !empty($row['created_at'])
                                            ? htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at'])))
                                            : 'N/A';
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="user-actions">
                                <button
                                    type="button"
                                    class="btn-primary"
                                    onclick="togglePasswordForm(<?php echo (int)$row['user_id']; ?>)"
                                >
                                    Edit Password
                                </button>

                                <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars($toggle_confirm, ENT_QUOTES); ?>');">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$row['user_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo htmlspecialchars($toggle_to); ?>">
                                    <button type="submit" class="btn-warning"><?php echo htmlspecialchars($toggle_label); ?></button>
                                </form>

                                <form method="POST" onsubmit="return confirm('Delete this user account? This will also remove the linked profile.');">
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$row['user_id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </div>

                            <div class="password-form" id="password-form-<?php echo (int)$row['user_id']; ?>">
                                <h4>Change Password</h4>

                                <form method="POST">
                                    <input type="hidden" name="update_password" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$row['user_id']; ?>">

                                    <div class="password-grid">
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <input type="password" name="new_password" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Confirm Password</label>
                                            <input type="password" name="confirm_password" required>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn-primary">Save Password</button>
                                        <button
                                            type="button"
                                            class="btn-secondary"
                                            onclick="togglePasswordForm(<?php echo (int)$row['user_id']; ?>)"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No user accounts found.</div>
                <?php endif; ?>
            </div>
        </section>

        <div style="flex: 1;"></div>
        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<?php
$toast_message = '';
$toast_class = 'success';

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'deleted':
            $toast_message = 'User deleted successfully';
            $toast_class = 'success';
            break;

        case 'status_updated':
            $toast_message = 'User status updated successfully';
            $toast_class = 'success';
            break;

        case 'password_updated':
            $toast_message = 'Password updated successfully';
            $toast_class = 'success';
            break;

        case 'password_mismatch':
            $toast_message = 'Passwords do not match';
            $toast_class = 'error';
            break;

        case 'password_short':
            $toast_message = 'Password must be at least 8 characters';
            $toast_class = 'error';
            break;

        case 'password_empty':
            $toast_message = 'Please fill in both password fields';
            $toast_class = 'error';
            break;

        case 'invalid_user':
            $toast_message = 'Invalid user selected';
            $toast_class = 'error';
            break;
    }
}
?>

<?php if ($toast_message !== ''): ?>
    <div class="toast <?php echo htmlspecialchars($toast_class); ?>" id="toastMessage">
        <span class="toast-icon"><?php echo $toast_class === 'success' ? '✓' : '!'; ?></span>
        <span><?php echo htmlspecialchars($toast_message); ?></span>
    </div>
<?php endif; ?>

<script>
    function togglePasswordForm(userId) {
        const form = document.getElementById('password-form-' + userId);
        if (form) {
            form.classList.toggle('active');
        }
    }

    const toastMessage = document.getElementById('toastMessage');

    if (toastMessage) {
        setTimeout(() => {
            toastMessage.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toastMessage.style.opacity = '0';
            toastMessage.style.transform = 'translateY(10px)';
        }, 2500);

        setTimeout(() => {
            toastMessage.remove();
        }, 3000);
    }
</script>