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
    $patient_id = (int)$patient['patient_id'];
} else {
    die("Patient profile not found.");
}

/*
|--------------------------------------------------------------------------
| 2. Cancel appointment handler
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);

    if ($appointment_id > 0) {
        $check_sql = "SELECT appointment_id, status
                      FROM appointments
                      WHERE appointment_id = ?
                        AND patient_id = ?
                      LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $appointment_id, $patient_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $appointment = ($check_result && mysqli_num_rows($check_result) > 0)
            ? mysqli_fetch_assoc($check_result)
            : null;
        mysqli_stmt_close($check_stmt);

        if ($appointment) {
            $old_status = $appointment['status'];

            if (in_array($old_status, ['pending', 'approved', 'rescheduled', 'reschedule_requested'])) {
                $update_sql = "UPDATE appointments
               SET status = 'cancelled',
                                payment_status = CASE
                                    WHEN payment_status = 'verified' THEN 'verified'
                                    ELSE 'cancelled'
                                END,
                                last_updated_by = ?
                            WHERE appointment_id = ?
                                AND patient_id = ?
                            LIMIT 1";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "iii", $user_id, $appointment_id, $patient_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    mysqli_stmt_close($update_stmt);

                    $history_sql = "INSERT INTO appointment_status_history
                                    (appointment_id, old_status, new_status, action_by, action_notes)
                                    VALUES (?, ?, 'cancelled', ?, ?)";
                    $history_stmt = mysqli_prepare($conn, $history_sql);
                    $note = "Appointment cancelled by patient";
                    mysqli_stmt_bind_param($history_stmt, "isis", $appointment_id, $old_status, $user_id, $note);
                    mysqli_stmt_execute($history_stmt);
                    mysqli_stmt_close($history_stmt);

                    header("Location: patient-dashboard.php?cancelled=1");
                    exit();
                } else {
                    mysqli_stmt_close($update_stmt);
                    header("Location: patient-dashboard.php?cancel_error=1");
                    exit();
                }
            } else {
                header("Location: patient-dashboard.php?cancel_invalid=1");
                exit();
            }
        } else {
            header("Location: patient-dashboard.php?cancel_invalid=1");
            exit();
        }
    } else {
        header("Location: patient-dashboard.php?cancel_invalid=1");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| 3. Dashboard counts
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
| 4. Active appointments list
|--------------------------------------------------------------------------
*/
$active_list = [];

$active_list_sql = "
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
      AND a.status IN ('pending', 'approved', 'rescheduled', 'reschedule_requested')
      AND COALESCE(a.final_date, a.requested_date) >= CURDATE()
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 10
";
$active_list_stmt = mysqli_prepare($conn, $active_list_sql);
mysqli_stmt_bind_param($active_list_stmt, "i", $patient_id);
mysqli_stmt_execute($active_list_stmt);
$active_list_result = mysqli_stmt_get_result($active_list_stmt);

if ($active_list_result) {
    while ($row = mysqli_fetch_assoc($active_list_result)) {
        $active_list[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| 5. Appointment history
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

<style>

.action-btn {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

/* RESCHEDULE */
.btn-reschedule {
    background: #eff6ff;
    color: #1d4ed8;
}

.btn-reschedule:hover {
    background: #dbeafe;
}

/* CANCEL */
.btn-cancel {
    background: #fee2e2;
    color: #b91c1c;
}

.btn-cancel:hover {
    background: #fecaca;
}

/* spacing */
.action-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.toast-message {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    min-width: 280px;
    max-width: 380px;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    animation: slideUpToast 0.3s ease;
}

.toast-success {
    background: #ecfdf5;
    color: #065f46;
    border-left: 6px solid #10b981;
}

.toast-error {
    background: #fef2f2;
    color: #7f1d1d;
    border-left: 6px solid #ef4444;
}

@keyframes slideUpToast {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php if (isset($_GET['success'])): ?>
    <div class="toast-message toast-success" id="toastMessage">
        Appointment request submitted successfully.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cancelled'])): ?>
    <div class="toast-message toast-success" id="toastMessage">
        Appointment cancelled successfully.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cancel_error'])): ?>
    <div class="toast-message toast-error" id="toastMessage">
        Failed to cancel the appointment.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cancel_invalid'])): ?>
    <div class="toast-message toast-error" id="toastMessage">
        This appointment can no longer be cancelled.
    </div>
<?php endif; ?>

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
                <h2>Active Appointments</h2>
                <p>Your pending and upcoming dental visits</p>
            </div>
            <a href="book-appointment.php" class="btn-primary">Book New Appointment</a>
        </div>

        <?php if (count($active_list) > 0): ?>
            <?php foreach ($active_list as $row): ?>
                <div class="appointment-card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
                    <div class="appointment-info">
                        <h4><?php echo htmlspecialchars($row['service_name']); ?></h4>
                        <div class="dentist">with Dr. <?php echo htmlspecialchars($row['dentist_name']); ?></div>
                        <div class="date"><?php echo htmlspecialchars(formatAppointmentDateTime($row['appointment_date'], $row['appointment_time'])); ?></div>
                    </div>

                    <div class="action-group">
                        <span class="<?php echo statusBadgeClass($row['status']); ?>">
                            <?php echo htmlspecialchars(statusLabel($row['status'])); ?>
                        </span>

                        <?php if (in_array($row['status'], ['approved', 'rescheduled'])): ?>
                            <a href="book-appointment.php?reschedule_id=<?php echo $row['appointment_id']; ?>" class="action-btn btn-reschedule">
                                Reschedule
                            </a>
                        <?php endif; ?>

                        <?php if (in_array($row['status'], ['pending', 'approved', 'rescheduled', 'reschedule_requested'])): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="margin:0;">
                                <input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>">
                                <button type="submit" name="cancel_appointment" class="action-btn btn-cancel">
                                    Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🗓️</div>
                <div>No active appointments</div>
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    const toast = document.getElementById("toastMessage");
    if (toast) {
        setTimeout(() => {
            toast.style.transition = "opacity 0.3s ease, transform 0.3s ease";
            toast.style.opacity = "0";
            toast.style.transform = "translateY(-10px)";
            setTimeout(() => {
                if (toast) toast.remove();
            }, 300);
        }, 2500);
    }
});
</script>

<?php include("../includes/patient-footer.php"); ?>