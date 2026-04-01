<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['dentist']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* --------------------------------------------------
   TOPBAR INFO
-------------------------------------------------- */
$admin_name = "Dentist";
$admin_role = "Dentist";

$admin_sql = "
    SELECT u.role, dp.first_name, dp.last_name, dp.dentist_id
    FROM users u
    LEFT JOIN dentist_profiles dp ON u.user_id = dp.user_id
    WHERE u.user_id = ?
    LIMIT 1
";
$admin_stmt = mysqli_prepare($conn, $admin_sql);
$dentist_id = 0;

if ($admin_stmt) {
    mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);

    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_result);

        $name = trim(($admin_row['first_name'] ?? '') . ' ' . ($admin_row['last_name'] ?? ''));
        if ($name !== '') {
            $admin_name = 'Dr. ' . $name;
        }

        $admin_role = 'Dentist';
        $dentist_id = (int)($admin_row['dentist_id'] ?? 0);
    }
}

/* --------------------------------------------------
   FETCH CONFIRMED / ACTIVE SCHEDULES
-------------------------------------------------- */
$schedules = [];

if ($dentist_id > 0) {
    $sql = "
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.status,
            a.patient_concern,
            COALESCE(a.final_date, a.requested_date) AS appointment_date,
            COALESCE(a.final_start_time, a.requested_start_time) AS start_time,
            COALESCE(a.final_end_time, a.requested_end_time) AS end_time,
            s.service_name,
            TRIM(CONCAT(COALESCE(pp.first_name,''), ' ', COALESCE(pp.last_name,''))) AS patient_name
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        WHERE a.dentist_id = ?
          AND a.status IN ('approved', 'rescheduled', 'completed')
        ORDER BY appointment_date ASC, start_time ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $dentist_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $schedules[] = $row;
            }
        }
    }
}

$page_title = "My Schedule | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<style>
    .topbar{
        min-height:72px;
        background:#ffffff;
        border-bottom:1px solid #dbe2ea;
        padding:12px 24px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
    }
    .topbar h1{
        margin:0;
        font-size:18px;
        font-weight:700;
    }
    .admin-user{
        display:flex;
        align-items:center;
        gap:10px;
    }
    .admin-meta{
        text-align:right;
    }
    .admin-meta strong{
        display:block;
        font-size:13px;
    }
    .admin-meta span{
        color:#64748b;
        font-size:11px;
    }
    .admin-avatar{
        width:38px;
        height:38px;
        border-radius:50%;
        background:#d1fae5;
        color:#059669;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:16px;
    }
    .content{
        flex:1;
        display:flex;
        flex-direction:column;
        padding:20px;
    }
    .panel{
        background:#fff;
        border:1px solid #dde3ea;
        border-radius:18px;
        padding:22px;
    }
    .panel h2{
        margin:0 0 8px;
        font-size:22px;
        color:#0b2454;
    }
    .panel p{
        margin:0 0 20px;
        color:#52637a;
        font-size:13px;
    }
    .table-wrap{
        width:100%;
        overflow-x:auto;
    }
    table{
        width:100%;
        border-collapse:collapse;
    }
    th, td{
        padding:14px 10px;
        border-bottom:1px solid #eef2f7;
        text-align:left;
        font-size:14px;
    }
    th{
        font-weight:700;
        color:#0f172a;
    }
    .empty{
        text-align:center;
        color:#64748b;
        padding:30px 0;
    }
</style>

<div class="topbar">
    <h1>Dentist Schedule</h1>

    <div class="admin-user">
        <div class="admin-meta">
            <strong><?php echo htmlspecialchars($admin_name); ?></strong>
            <span><?php echo htmlspecialchars($admin_role); ?></span>
        </div>
        <div class="admin-avatar">🦷</div>
    </div>
</div>

<div class="content">
    <div class="panel">
        <h2>My Confirmed Appointments</h2>
        <p>View-only schedule for approved, rescheduled, and completed appointments.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Concern</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($schedules)): ?>
                        <?php foreach ($schedules as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['appointment_code'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['patient_name'] ?: 'Unknown Patient'); ?></td>
                                <td><?php echo htmlspecialchars($row['service_name'] ?? 'Unknown Service'); ?></td>
                                <td><?php echo !empty($row['appointment_date']) ? date('M d, Y', strtotime($row['appointment_date'])) : 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $start = !empty($row['start_time']) ? date('h:i A', strtotime($row['start_time'])) : 'N/A';
                                    $end   = !empty($row['end_time']) ? date('h:i A', strtotime($row['end_time'])) : '';
                                    echo htmlspecialchars(trim($start . ($end ? ' - ' . $end : '')));
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars($row['patient_concern'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty">No confirmed schedule yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>