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

/*
|--------------------------------------------------------------------------
| Get logged-in admin/staff info
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Dashboard stats
|--------------------------------------------------------------------------
*/
$todays_appointments = 0;
$pending_requests = 0;
$total_patients = 0;
$verified_revenue = 0.00;

/* Today's appointments */
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

/* Pending requests */
$pending_sql = "
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE status IN ('pending', 'reschedule_requested')
";
$pending_result = mysqli_query($conn, $pending_sql);
if ($pending_result) {
    $pending_requests = mysqli_fetch_assoc($pending_result)['total'] ?? 0;
}

/* Total active patients */
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

/* Verified revenue this month */
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

/*
|--------------------------------------------------------------------------
| Pending request cards
|--------------------------------------------------------------------------
*/
$pending_list = [];

$pending_list_sql = "
    SELECT
        a.appointment_id,
        a.appointment_code,
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

/*
|--------------------------------------------------------------------------
| Today's schedule
|--------------------------------------------------------------------------
*/
$today_list = [];

$today_list_sql = "
    SELECT
        a.appointment_id,
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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function formatDateTimeAdmin($date, $time) {
    if (!$date) return 'Date not available';

    $date_text = date("n/j/Y", strtotime($date));

    if (!empty($time)) {
        $time_text = date("h:i A", strtotime($time));
        return $date_text . " at " . $time_text;
    }

    return $date_text;
}

function badgeClassAdmin($status) {
    switch ($status) {
        case 'approved':
        case 'completed':
        case 'rescheduled':
            return 'status-badge success';
        case 'pending':
        case 'reschedule_requested':
            return 'status-badge pending';
        case 'rejected':
        case 'cancelled':
        case 'no_show':
            return 'status-badge danger';
        default:
            return 'status-badge neutral';
    }
}

function badgeLabelAdmin($status) {
    return ucwords(str_replace('_', ' ', $status));
}

$page_title = "Admin Dashboard | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<main class="main">
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
                                <span class="<?php echo badgeClassAdmin($row['status']); ?>">
                                    <?php echo htmlspecialchars(badgeLabelAdmin($row['status'])); ?>
                                </span>
                            </div>
                            <div class="request-meta">
                                <?php echo htmlspecialchars(formatDateTimeAdmin($row['appointment_date'], $row['appointment_time'])); ?>
                                &nbsp;&nbsp;&nbsp;
                                <?php echo htmlspecialchars($row['dentist_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">🗂</div>
                        <div>No pending requests</div>
                    </div>
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
                                <span class="<?php echo badgeClassAdmin($row['status']); ?>">
                                    <?php echo htmlspecialchars(badgeLabelAdmin($row['status'])); ?>
                                </span>
                            </div>
                            <div class="request-meta">
                                <?php echo htmlspecialchars(formatDateTimeAdmin($row['appointment_date'], $row['appointment_time'])); ?>
                                &nbsp;&nbsp;&nbsp;
                                <?php echo htmlspecialchars($row['dentist_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">🗓</div>
                        <div>No appointments today</div>
                    </div>
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
                <a href="manage-appointments.php" class="action-card">
                    <span class="icon">🗓</span>
                    Manage Appointments
                </a>
                <a href="manage-services.php" class="action-card">
                    <span class="icon">☑</span>
                    Services
                </a>
                <a href="manage-patients.php" class="action-card">
                    <span class="icon">☺</span>
                    Patients
                </a>
                <a href="billing.php" class="action-card">
                    <span class="icon">$</span>
                    Billing
                </a>
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