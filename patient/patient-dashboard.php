<?php
session_start();
include("../config/database.php");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: patient-login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| 1. Get logged-in patient profile
|--------------------------------------------------------------------------
*/
$patient = null;
$patient_id = null;

$patient_sql = "SELECT pp.*, u.email, u.phone
                FROM patient_profiles pp
                INNER JOIN users u ON pp.user_id = u.user_id
                WHERE pp.user_id = ?
                LIMIT 1";

$patient_stmt = mysqli_prepare($conn, $patient_sql);
mysqli_stmt_bind_param($patient_stmt, "i", $user_id);
mysqli_stmt_execute($patient_stmt);
$patient_result = mysqli_stmt_get_result($patient_stmt);

if ($patient_result && mysqli_num_rows($patient_result) > 0) {
    $patient = mysqli_fetch_assoc($patient_result);
    $patient_id = $patient['patient_id'];
} else {
    die("Patient profile not found.");
}

/*
|--------------------------------------------------------------------------
| 2. Dashboard counts
|--------------------------------------------------------------------------
*/
$total = 0;
$pending = 0;
$upcoming = 0;

$total_sql = "SELECT COUNT(*) AS total
              FROM appointments
              WHERE patient_id = ?";
$total_stmt = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($total_stmt, "i", $patient_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
if ($total_result) {
    $total = mysqli_fetch_assoc($total_result)['total'] ?? 0;
}

$pending_sql = "SELECT COUNT(*) AS pending
                FROM appointments
                WHERE patient_id = ?
                AND status IN ('pending', 'reschedule_requested')";
$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "i", $patient_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
if ($pending_result) {
    $pending = mysqli_fetch_assoc($pending_result)['pending'] ?? 0;
}

$upcoming_sql = "SELECT COUNT(*) AS upcoming
                 FROM appointments
                 WHERE patient_id = ?
                 AND status IN ('approved', 'rescheduled')
                 AND (
                     (final_date IS NOT NULL AND final_date >= CURDATE())
                     OR
                     (final_date IS NULL AND requested_date >= CURDATE())
                 )";
$upcoming_stmt = mysqli_prepare($conn, $upcoming_sql);
mysqli_stmt_bind_param($upcoming_stmt, "i", $patient_id);
mysqli_stmt_execute($upcoming_stmt);
$upcoming_result = mysqli_stmt_get_result($upcoming_stmt);
if ($upcoming_result) {
    $upcoming = mysqli_fetch_assoc($upcoming_result)['upcoming'] ?? 0;
}

/*
|--------------------------------------------------------------------------
| 3. Upcoming appointments list
|--------------------------------------------------------------------------
*/
$upcoming_list = [];

$upcoming_list_sql = "
    SELECT 
        a.appointment_id,
        a.appointment_code,
        a.status,
        s.service_name,
        CONCAT(dp.first_name, ' ', dp.last_name) AS dentist_name,
        COALESCE(a.final_date, a.requested_date) AS appointment_date,
        COALESCE(a.final_start_time, a.requested_start_time) AS appointment_time
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    INNER JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
    WHERE a.patient_id = ?
      AND a.status IN ('approved', 'rescheduled')
      AND COALESCE(a.final_date, a.requested_date) >= CURDATE()
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 5
";
$upcoming_list_stmt = mysqli_prepare($conn, $upcoming_list_sql);
mysqli_stmt_bind_param($upcoming_list_stmt, "i", $patient_id);
mysqli_stmt_execute($upcoming_list_stmt);
$upcoming_list_result = mysqli_stmt_get_result($upcoming_list_stmt);

if ($upcoming_list_result) {
    while ($row = mysqli_fetch_assoc($upcoming_list_result)) {
        $upcoming_list[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| 4. Appointment history
|--------------------------------------------------------------------------
*/
$history_list = [];

$history_sql = "
    SELECT 
        a.appointment_id,
        a.appointment_code,
        a.status,
        s.service_name,
        CONCAT(dp.first_name, ' ', dp.last_name) AS dentist_name,
        COALESCE(a.final_date, a.requested_date) AS appointment_date,
        COALESCE(a.final_start_time, a.requested_start_time) AS appointment_time
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    INNER JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
    WHERE a.patient_id = ?
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT 5
";
$history_stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($history_stmt, "i", $patient_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history_list[] = $row;
    }
}

$full_name = (($patient['first_name'] ?? 'Patient') . ' ' . ($patient['last_name'] ?? ''));

function formatAppointmentDateTime($date, $time) {
    if (!$date) {
        return "Date not available";
    }

    $date_text = date("n/j/Y", strtotime($date));

    if (!empty($time)) {
        $time_text = date("h:i A", strtotime($time));
        return $date_text . " at " . $time_text;
    }

    return $date_text;
}

function statusBadgeClass($status) {
    switch ($status) {
        case 'approved':
        case 'completed':
        case 'rescheduled':
            return 'badge success';
        case 'pending':
        case 'reschedule_requested':
            return 'badge pending';
        case 'rejected':
        case 'cancelled':
        case 'no_show':
            return 'badge danger';
        default:
            return 'badge neutral';
    }
}

function statusLabel($status) {
    return ucwords(str_replace('_', ' ', $status));
}

$page_title = "Patient Dashboard | Floss & Gloss Dental";
include("../includes/patient-header.php");
include("../includes/patient-navbar.php");
?>

<div class="page">
    <div class="welcome">
        <h1>Welcome back, <?php echo htmlspecialchars(trim($full_name)); ?>!</h1>
        <p>Manage your dental appointments and health records</p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>Upcoming Appointments</h3>
            <div class="value"><?php echo $upcoming; ?></div>
        </div>

        <div class="stat-card">
            <h3>Pending Approvals</h3>
            <div class="value"><?php echo $pending; ?></div>
        </div>

        <div class="stat-card">
            <h3>Total Appointments</h3>
            <div class="value"><?php echo $total; ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div>
                <h2>Upcoming Appointments</h2>
                <p>Your scheduled dental visits</p>
            </div>
            <a href="book-appointment.php" class="btn-primary">Book New Appointment</a>
        </div>

        <?php if (count($upcoming_list) > 0): ?>
            <?php foreach ($upcoming_list as $row): ?>
                <div class="appointment-card">
                    <div class="appointment-info">
                        <h4><?php echo htmlspecialchars($row['service_name']); ?></h4>
                        <div class="dentist">with Dr. <?php echo htmlspecialchars($row['dentist_name']); ?></div>
                        <div class="date"><?php echo htmlspecialchars(formatAppointmentDateTime($row['appointment_date'], $row['appointment_time'])); ?></div>
                    </div>
                    <span class="<?php echo statusBadgeClass($row['status']); ?>">
                        <?php echo htmlspecialchars(statusLabel($row['status'])); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🗓️</div>
                <div>No upcoming appointments</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="section payment-box">
        <h2>Payment Instructions</h2>
        <p>Please make payment before your appointment date to confirm your booking.</p>
        <p><strong>Bank Transfer:</strong> Floss &amp; Gloss Dental Clinic</p>
        <p><strong>Account Number:</strong> 1234-5678-9012</p>
        <p><strong>GCash:</strong> 0917-123-4567</p>
        <p>Please email your proof of payment to billing@flossgloss.com with your appointment ID.</p>
    </div>

    <div class="section">
        <div class="section-header">
            <div>
                <h2>Appointment History</h2>
                <p>Your past dental visits</p>
            </div>
        </div>

        <?php if (count($history_list) > 0): ?>
            <?php foreach ($history_list as $row): ?>
                <div class="appointment-card">
                    <div class="appointment-info">
                        <h4><?php echo htmlspecialchars($row['service_name']); ?></h4>
                        <div class="dentist">with Dr. <?php echo htmlspecialchars($row['dentist_name']); ?></div>
                        <div class="date"><?php echo htmlspecialchars(formatAppointmentDateTime($row['appointment_date'], $row['appointment_time'])); ?></div>
                    </div>
                    <span class="<?php echo statusBadgeClass($row['status']); ?>">
                        <?php echo htmlspecialchars(statusLabel($row['status'])); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📄</div>
                <div>No appointment history</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include("../includes/patient-footer.php"); ?>