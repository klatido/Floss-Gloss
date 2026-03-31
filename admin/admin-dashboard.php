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

$todays_appointments = 0;
$pending_requests = 0;
$total_patients = 0;
$verified_revenue = 0.00;

$todays_sql = "
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE COALESCE(final_date, requested_date) = CURDATE()
      AND status IN ('approved', 'rescheduled', 'completed')
";
$todays_result = mysqli_query($conn, $todays_sql);
if ($todays_result) {
    $todays_appointments = mysqli_fetch_assoc($todays_result)['total'] ?? 0;
}

$pending_sql = "
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE status IN ('pending', 'reschedule_requested')
";
$pending_result = mysqli_query($conn, $pending_sql);
if ($pending_result) {
    $pending_requests = mysqli_fetch_assoc($pending_result)['total'] ?? 0;
}

$patients_sql = "
    SELECT COUNT(*) AS total
    FROM patient_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE u.account_status = 'active'
";
$patients_result = mysqli_query($conn, $patients_sql);
if ($patients_result) {
    $total_patients = mysqli_fetch_assoc($patients_result)['total'] ?? 0;
}

$revenue_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE verification_status = 'verified'
      AND MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at) = YEAR(CURDATE())
";
$revenue_result = mysqli_query($conn, $revenue_sql);
if ($revenue_result) {
    $verified_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;
}

$pending_list = [];
$pending_list_sql = "
    SELECT
        a.status,
        s.service_name,
        CONCAT(pp.first_name, ' ', pp.last_name) AS patient_name,
        CONCAT('Dr. ', dp.first_name, ' ', dp.last_name) AS dentist_name,
        COALESCE(a.final_date, a.requested_date) AS appointment_date,
        COALESCE(a.final_start_time, a.requested_start_time) AS appointment_time
    FROM appointments a
    INNER JOIN patient_profiles pp ON a.patient_id = pp.patient_id
    INNER JOIN services s ON a.service_id = s.service_id
    INNER JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
    WHERE a.status IN ('pending', 'reschedule_requested')
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 5
";
$pending_list_result = mysqli_query($conn, $pending_list_sql);
if ($pending_list_result) {
    while ($row = mysqli_fetch_assoc($pending_list_result)) {
        $pending_list[] = $row;
    }
}

$today_list = [];
$today_list_sql = "
    SELECT
        a.status,
        s.service_name,
        CONCAT(pp.first_name, ' ', pp.last_name) AS patient_name,
        CONCAT('Dr. ', dp.first_name, ' ', dp.last_name) AS dentist_name,
        COALESCE(a.final_date, a.requested_date) AS appointment_date,
        COALESCE(a.final_start_time, a.requested_start_time) AS appointment_time
    FROM appointments a
    INNER JOIN patient_profiles pp ON a.patient_id = pp.patient_id
    INNER JOIN services s ON a.service_id = s.service_id
    INNER JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
    WHERE COALESCE(a.final_date, a.requested_date) = CURDATE()
      AND a.status IN ('approved', 'rescheduled', 'completed')
    ORDER BY appointment_time ASC
    LIMIT 5
";
$today_list_result = mysqli_query($conn, $today_list_sql);
if ($today_list_result) {
    while ($row = mysqli_fetch_assoc($today_list_result)) {
        $today_list[] = $row;
    }
}

function formatDateTimeAdmin($date, $time) {
    if (!$date) return 'Date not available';
    $date_text = date("n/j/Y", strtotime($date));
    if (!empty($time)) {
        $time_text = date("h:i A", strtotime($time));
        return $date_text . " at " . $time_text;
    }
    return $date_text;
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "Admin Dashboard | Floss & Gloss Dental";
include("../includes/admin-header.php");
?>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: #f3f4f6;
        font-family: Arial, sans-serif;
        color: #0f172a;
        font-size: 14px;
    }

    .admin-layout {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }

    .sidebar {
        width: 250px;
        min-height: 100vh;
        background: #ffffff;
        border-right: 1px solid #d9dee7;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: width 0.25s ease;
        overflow: hidden;
        flex-shrink: 0;
        position: sticky;
        top: 0;
    }

    .sidebar.collapsed {
        width: 72px;
    }

    .sidebar-top {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        min-height: 72px;
        border-bottom: 1px solid #d9dee7;
    }

    .brand-logo {
        width: 38px;
        height: 38px;
        min-width: 38px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .brand-text {
        transition: opacity 0.2s ease, width 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
    }

    .brand-text h2 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
    }

    .brand-text p {
        margin: 2px 0 0;
        font-size: 11px;
        color: #64748b;
    }

    .sidebar.collapsed .brand-text {
        opacity: 0;
        width: 0;
        pointer-events: none;
    }

    .sidebar.collapsed .sidebar-brand {
        justify-content: center;
        padding: 12px 8px;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 10px 8px;
    }

    .sidebar-nav a,
    .sidebar-bottom a,
    .collapse-btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 13px;
        border-radius: 12px;
        text-decoration: none;
        border: none;
        background: none;
        color: #111827;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-align: left;
        transition: background 0.2s ease;
        white-space: nowrap;
    }

    .sidebar-nav a:hover,
    .sidebar-bottom a:hover,
    .collapse-btn:hover {
        background: #f1f5f9;
    }

    .sidebar-nav a.active {
        background: #eceff3;
    }

    .nav-icon {
        width: 20px;
        min-width: 20px;
        text-align: center;
        font-size: 18px;
        line-height: 1;
    }

    .nav-text {
        transition: opacity 0.2s ease, width 0.2s ease;
        overflow: hidden;
        white-space: nowrap;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        width: 0;
        pointer-events: none;
    }

    .sidebar.collapsed .sidebar-nav a,
    .sidebar.collapsed .sidebar-bottom a,
    .sidebar.collapsed .collapse-btn {
        justify-content: center;
        padding: 11px 8px;
        gap: 0;
    }

    .sidebar-bottom {
        border-top: 1px solid #d9dee7;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .main-area {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .topbar {
        min-height: 72px;
        background: #ffffff;
        border-bottom: 1px solid #d9dee7;
        padding: 12px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .topbar h1 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0b1739;
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
        color: #0b1739;
    }

    .admin-meta span {
        display: block;
        margin-top: 2px;
        color: #64748b;
        font-size: 11px;
    }

    .admin-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #d9f6ef;
        color: #0f9f8f;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .content {
        padding: 20px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 20px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
        min-height: 130px;
    }

    .stat-card h3 {
        margin: 0 0 20px;
        font-size: 13px;
        color: #3f5b7a;
        font-weight: 700;
        line-height: 1.4;
    }

    .stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #0b2454;
        margin-bottom: 6px;
    }

    .stat-note {
        color: #64748b;
        font-size: 12px;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }

    .panel-header h2 {
        margin: 0;
        font-size: 15px;
        color: #111827;
    }

    .panel-header p {
        margin: 4px 0 0;
        color: #667085;
        font-size: 12px;
    }

    .small-btn {
        display: inline-block;
        padding: 9px 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-weight: 700;
        font-size: 12px;
        color: #111827;
        background: #fff;
        text-decoration: none;
    }

    .request-card {
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
    }

    .request-card:last-child {
        margin-bottom: 0;
    }

    .request-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 8px;
    }

    .request-top strong {
        font-size: 14px;
        color: #111827;
    }

    .request-service {
        color: #344054;
        margin: 3px 0;
        font-size: 13px;
    }

    .request-meta {
        color: #60708a;
        font-size: 12px;
        line-height: 1.5;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        background: #f3f4f6;
        color: #111827;
    }

    .empty-state {
        text-align: center;
        color: #7b8ba1;
        padding: 50px 16px;
        font-size: 14px;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .action-card {
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        padding: 20px 14px;
        text-align: center;
        color: #111827;
        background: #fff;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        display: block;
    }

    .action-card .icon {
        display: block;
        font-size: 22px;
        margin-bottom: 8px;
    }

    .notification-box {
        border: 1px solid #f7d66a;
        background: #fffbea;
        border-radius: 12px;
        padding: 14px;
        color: #a15c07;
    }

    .notification-box strong {
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
    }

    .notification-box span {
        font-size: 12px;
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .grid-2 {
            grid-template-columns: 1fr;
        }

        .quick-actions {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 820px) {
        .sidebar {
            width: 72px;
        }

        .brand-text,
        .nav-text {
            display: none;
        }

        .sidebar-brand,
        .sidebar-nav a,
        .sidebar-bottom a,
        .collapse-btn {
            justify-content: center;
        }

        .stats-grid,
        .quick-actions {
            grid-template-columns: 1fr;
        }

        .topbar {
            flex-direction: column;
            align-items: flex-start;
            padding: 12px 16px;
        }

        .content {
            padding: 16px;
        }

        .admin-user {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<div class="admin-layout">
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-top">
            <div class="sidebar-brand">
                <div class="brand-logo">🩺</div>
                <div class="brand-text">
                    <h2>F&amp;G Admin</h2>
                    <p>Management</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="admin-dashboard.php" class="<?php echo ($current_page === 'admin-dashboard.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">◫</span>
                    <span class="nav-text">Dashboard</span>
                </a>

                <a href="manage-appointments.php" class="<?php echo ($current_page === 'manage-appointments.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">🗓</span>
                    <span class="nav-text">Appointments</span>
                </a>

                <a href="manage-services.php" class="<?php echo ($current_page === 'manage-services.php' || $current_page === 'edit-service.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Services</span>
                </a>

                <a href="manage-schedules.php" class="<?php echo ($current_page === 'manage-schedules.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">◔</span>
                    <span class="nav-text">Schedules</span>
                </a>

                <a href="manage-patients.php" class="<?php echo ($current_page === 'manage-patients.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">☺</span>
                    <span class="nav-text">Patients</span>
                </a>

                <a href="medical-records.php" class="<?php echo ($current_page === 'medical-records.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">🗎</span>
                    <span class="nav-text">Medical Records</span>
                </a>

                <a href="billing.php" class="<?php echo ($current_page === 'billing.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">▭</span>
                    <span class="nav-text">Billing</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <button type="button" class="collapse-btn" id="toggleSidebar">
                <span class="nav-icon">☰</span>
                <span class="nav-text">Collapse</span>
            </button>

            <a href="../auth/admin-logout.php">
                <span class="nav-icon">↪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-area">
        <div class="topbar">
            <h1>Dashboard</h1>

            <div class="admin-user">
                <div class="admin-meta">
                    <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                    <span><?php echo htmlspecialchars($admin_role); ?></span>
                </div>
                <div class="admin-avatar">⚇</div>
            </div>
        </div>

        <div class="content">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Today's<br>Appointments</h3>
                    <div class="stat-value"><?php echo $todays_appointments; ?></div>
                    <div class="stat-note">Scheduled for today</div>
                </div>

                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="stat-value"><?php echo $pending_requests; ?></div>
                    <div class="stat-note">Awaiting approval</div>
                </div>

                <div class="stat-card">
                    <h3>Total Patients</h3>
                    <div class="stat-value"><?php echo $total_patients; ?></div>
                    <div class="stat-note">Active patients</div>
                </div>

                <div class="stat-card">
                    <h3>Revenue (Verified)</h3>
                    <div class="stat-value">₱<?php echo number_format((float)$verified_revenue, 0); ?></div>
                    <div class="stat-note">This month</div>
                </div>
            </div>

            <div class="grid-2">
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Pending Requests</h2>
                            <p>Appointments awaiting approval</p>
                        </div>
                        <a href="manage-appointments.php" class="small-btn">View All</a>
                    </div>

                    <?php if (count($pending_list) > 0): ?>
                        <?php foreach ($pending_list as $row): ?>
                            <div class="request-card">
                                <div class="request-top">
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['patient_name']); ?></strong>
                                        <div class="request-service"><?php echo htmlspecialchars($row['service_name']); ?></div>
                                    </div>
                                    <span class="status-badge">Pending</span>
                                </div>
                                <div class="request-meta">
                                    <?php echo htmlspecialchars(formatDateTimeAdmin($row['appointment_date'], $row['appointment_time'])); ?>
                                    &nbsp;&nbsp;&nbsp;
                                    <?php echo htmlspecialchars($row['dentist_name']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No pending requests</div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Today's Schedule</h2>
                            <p>Appointments scheduled for today</p>
                        </div>
                        <a href="manage-schedules.php" class="small-btn">Calendar</a>
                    </div>

                    <?php if (count($today_list) > 0): ?>
                        <?php foreach ($today_list as $row): ?>
                            <div class="request-card">
                                <div class="request-top">
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['patient_name']); ?></strong>
                                        <div class="request-service"><?php echo htmlspecialchars($row['service_name']); ?></div>
                                    </div>
                                </div>
                                <div class="request-meta">
                                    <?php echo htmlspecialchars(formatDateTimeAdmin($row['appointment_date'], $row['appointment_time'])); ?>
                                    &nbsp;&nbsp;&nbsp;
                                    <?php echo htmlspecialchars($row['dentist_name']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No appointments today</div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="panel" style="margin-bottom: 30px;">
                <div class="panel-header">
                    <div>
                        <h2>Quick Actions</h2>
                        <p>Common administrative tasks</p>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="manage-appointments.php" class="action-card"><span class="icon">🗓</span>Manage Appointments</a>
                    <a href="manage-services.php" class="action-card"><span class="icon">☑</span>Services</a>
                    <a href="manage-patients.php" class="action-card"><span class="icon">☺</span>Patients</a>
                    <a href="billing.php" class="action-card"><span class="icon">$</span>Billing</a>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>System Notifications</h2>
                    </div>
                </div>

                <div class="notification-box">
                    <strong><?php echo $pending_requests; ?> pending appointments require approval</strong>
                    <span>Review and approve patient appointment requests</span>
                </div>
            </section>

            <?php include("../includes/admin-footer.php"); ?>
        </div>
    </div>
</div>

<script>
    (function () {
        const sidebar = document.getElementById('adminSidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        const storageKey = 'fg_admin_sidebar_collapsed';

        if (localStorage.getItem(storageKey) === 'true') {
            sidebar.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem(storageKey, sidebar.classList.contains('collapsed'));
        });
    })();
</script>