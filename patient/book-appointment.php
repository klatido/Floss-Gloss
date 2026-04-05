<?php
session_start();
include("../config/database.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

$user_id = (int)$_SESSION['user_id'];

$reschedule_id = isset($_GET['reschedule_id']) ? (int)$_GET['reschedule_id'] : 0;
$prefilled_service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

$existing = null;
$is_reschedule_mode = false;
$locked_service_id = 0;
$locked_dentist_id = 0;
$locked_service_name = '';
$locked_service_price = 0;
$locked_service_duration = 60;
$locked_dentist_name = '';

/* =========================
   GET PATIENT PROFILE
========================= */
$patient = null;
$patient_id = 0;

$patient_sql = "
    SELECT pp.*, u.email, u.phone
    FROM patient_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE pp.user_id = ?
    LIMIT 1
";
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

mysqli_stmt_close($patient_stmt);

/* =========================
   AJAX: GET UNAVAILABLE SLOTS
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_unavailable_slots') {
    header('Content-Type: application/json');

    $dentist_id = (int)($_GET['dentist_id'] ?? 0);
    $date = trim($_GET['date'] ?? '');
    $duration = (int)($_GET['duration'] ?? 60);
    $exclude_appointment_id = (int)($_GET['exclude_appointment_id'] ?? 0);

    $response = [
        'full_day_blocked' => false,
        'unavailable_slots' => []
    ];

    if ($dentist_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode($response);
        exit();
    }

    if ($duration <= 0) {
        $duration = 60;
    }

    $fixed_slots = [
        '09:00:00',
        '10:00:00',
        '11:00:00',
        '13:00:00',
        '14:00:00',
        '15:00:00',
        '16:00:00'
    ];

    // Whole day block
    $full_block_sql = "
        SELECT block_id
        FROM dentist_schedule_blocks
        WHERE dentist_id = ?
          AND block_date = ?
          AND start_time IS NULL
          AND end_time IS NULL
        LIMIT 1
    ";
    $full_block_stmt = mysqli_prepare($conn, $full_block_sql);
    mysqli_stmt_bind_param($full_block_stmt, "is", $dentist_id, $date);
    mysqli_stmt_execute($full_block_stmt);
    $full_block_result = mysqli_stmt_get_result($full_block_stmt);

    if ($full_block_result && mysqli_num_rows($full_block_result) > 0) {
        $response['full_day_blocked'] = true;
        mysqli_stmt_close($full_block_stmt);
        echo json_encode($response);
        exit();
    }

    mysqli_stmt_close($full_block_stmt);

    $unavailable = [];

    // Partial blocks
    $block_sql = "
        SELECT start_time, end_time
        FROM dentist_schedule_blocks
        WHERE dentist_id = ?
          AND block_date = ?
          AND start_time IS NOT NULL
          AND end_time IS NOT NULL
    ";
    $block_stmt = mysqli_prepare($conn, $block_sql);
    mysqli_stmt_bind_param($block_stmt, "is", $dentist_id, $date);
    mysqli_stmt_execute($block_stmt);
    $block_result = mysqli_stmt_get_result($block_stmt);

    if ($block_result) {
        while ($row = mysqli_fetch_assoc($block_result)) {
            foreach ($fixed_slots as $slot) {
                $slot_end = date('H:i:s', strtotime($slot . " +{$duration} minutes"));

                if (
                    strtotime($slot) < strtotime($row['end_time']) &&
                    strtotime($slot_end) > strtotime($row['start_time'])
                ) {
                    $unavailable[] = substr($slot, 0, 5);
                }
            }
        }
    }

    mysqli_stmt_close($block_stmt);

    // Existing appointments, INCLUDING pending
    if ($exclude_appointment_id > 0) {
        $appointment_sql = "
            SELECT
                COALESCE(final_start_time, requested_start_time) AS start_time,
                COALESCE(final_end_time, requested_end_time) AS end_time
            FROM appointments
            WHERE dentist_id = ?
              AND appointment_id <> ?
              AND status IN ('pending', 'approved', 'reschedule_requested', 'rescheduled')
              AND COALESCE(final_date, requested_date) = ?
        ";
        $appointment_stmt = mysqli_prepare($conn, $appointment_sql);
        mysqli_stmt_bind_param($appointment_stmt, "iis", $dentist_id, $exclude_appointment_id, $date);
    } else {
        $appointment_sql = "
            SELECT
                COALESCE(final_start_time, requested_start_time) AS start_time,
                COALESCE(final_end_time, requested_end_time) AS end_time
            FROM appointments
            WHERE dentist_id = ?
              AND status IN ('pending', 'approved', 'reschedule_requested', 'rescheduled')
              AND COALESCE(final_date, requested_date) = ?
        ";
        $appointment_stmt = mysqli_prepare($conn, $appointment_sql);
        mysqli_stmt_bind_param($appointment_stmt, "is", $dentist_id, $date);
    }

    mysqli_stmt_execute($appointment_stmt);
    $appointment_result = mysqli_stmt_get_result($appointment_stmt);

    if ($appointment_result) {
        while ($row = mysqli_fetch_assoc($appointment_result)) {
            foreach ($fixed_slots as $slot) {
                $slot_end = date('H:i:s', strtotime($slot . " +{$duration} minutes"));

                if (
                    strtotime($slot) < strtotime($row['end_time']) &&
                    strtotime($slot_end) > strtotime($row['start_time'])
                ) {
                    $unavailable[] = substr($slot, 0, 5);
                }
            }
        }
    }

    mysqli_stmt_close($appointment_stmt);

    $response['unavailable_slots'] = array_values(array_unique($unavailable));
    echo json_encode($response);
    exit();
}

/* =========================
   RESCHEDULE FETCH
========================= */
if ($reschedule_id > 0) {
    $existing_sql = "
        SELECT a.*, s.service_name, s.price, s.duration_minutes,
               dp.first_name, dp.last_name
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.service_id
        INNER JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
        WHERE a.appointment_id = ?
          AND a.patient_id = ?
          AND a.status IN ('approved', 'rescheduled')
        LIMIT 1
    ";
    $existing_stmt = mysqli_prepare($conn, $existing_sql);
    mysqli_stmt_bind_param($existing_stmt, "ii", $reschedule_id, $patient_id);
    mysqli_stmt_execute($existing_stmt);
    $existing_result = mysqli_stmt_get_result($existing_stmt);

    if ($existing_result && mysqli_num_rows($existing_result) > 0) {
        $existing = mysqli_fetch_assoc($existing_result);
        $is_reschedule_mode = true;

        $locked_service_id = (int)$existing['service_id'];
        $locked_dentist_id = (int)$existing['dentist_id'];
        $locked_service_name = $existing['service_name'];
        $locked_service_price = (float)$existing['price'];
        $locked_service_duration = !empty($existing['duration_minutes']) ? (int)$existing['duration_minutes'] : 60;
        $locked_dentist_name = trim(($existing['first_name'] ?? '') . ' ' . ($existing['last_name'] ?? ''));
    } else {
        die("Invalid reschedule request.");
    }

    mysqli_stmt_close($existing_stmt);
}

/* =========================
   GET SERVICES / DENTISTS FOR NORMAL BOOKING
========================= */
$services = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1 ORDER BY service_name ASC");
$dentists = mysqli_query($conn, "SELECT * FROM dentist_profiles WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");

/* =========================
   HANDLE BOOKING / RESCHEDULE
========================= */
if (isset($_POST['book'])) {
    if ($is_reschedule_mode) {
        $service_id = $locked_service_id;
        $dentist_id = $locked_dentist_id;
        $duration = $locked_service_duration;
    } else {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $dentist_id = (int)($_POST['dentist_id'] ?? 0);

        $service_stmt = mysqli_prepare($conn, "SELECT duration_minutes FROM services WHERE service_id = ? LIMIT 1");
        mysqli_stmt_bind_param($service_stmt, "i", $service_id);
        mysqli_stmt_execute($service_stmt);
        $service_result = mysqli_stmt_get_result($service_stmt);
        $service_data = $service_result ? mysqli_fetch_assoc($service_result) : null;
        mysqli_stmt_close($service_stmt);

        $duration = !empty($service_data['duration_minutes']) ? (int)$service_data['duration_minutes'] : 60;
    }

    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');

    if ($service_id <= 0 || $dentist_id <= 0 || $date === '' || $time === '') {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Please complete all required fields.<br><br><a href='book-appointment.php' style='color:#1e40af;'>Go back</a></h3></div>");
    }

    $end_time = date('H:i:s', strtotime($time . " +$duration minutes"));

    if (strtotime($end_time) > strtotime('17:00:00')) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Error: This appointment extends past our 5:00 PM closing time.<br><br><a href='javascript:history.back()' style='color:#1e40af;'>Go back and select an earlier time</a></h3></div>");
    }

    $start_ts = strtotime($time);
    $end_ts = strtotime($end_time);
    $lunch_start = strtotime('12:00:00');
    $lunch_end = strtotime('13:00:00');

    if ($start_ts < $lunch_end && $end_ts > $lunch_start) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Error: This appointment overlaps with the clinic's 12:00 PM - 1:00 PM lunch break.<br><br><a href='javascript:history.back()' style='color:#1e40af;'>Go back and select another time</a></h3></div>");
    }

    $today_date = date('Y-m-d');
    if ($date < $today_date) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Security Error: You cannot book an appointment in the past.<br><br><a href='javascript:history.back()' style='color:#1e40af;'>Go back to the booking form</a></h3></div>");
    }

    $block_stmt = mysqli_prepare($conn, "
        SELECT block_id
        FROM dentist_schedule_blocks
        WHERE dentist_id = ?
          AND block_date = ?
          AND (
                (start_time IS NULL AND end_time IS NULL)
                OR
                (? < COALESCE(end_time, '23:59:59') AND ? > COALESCE(start_time, '00:00:00'))
              )
        LIMIT 1
    ");
    mysqli_stmt_bind_param($block_stmt, "isss", $dentist_id, $date, $time, $end_time);
    mysqli_stmt_execute($block_stmt);
    $block_result = mysqli_stmt_get_result($block_stmt);
    $is_blocked = $block_result && mysqli_num_rows($block_result) > 0;
    mysqli_stmt_close($block_stmt);

    if ($is_blocked) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>The selected dentist is unavailable on this schedule.<br><br><a href='javascript:history.back()' style='color:#1e40af;'>Go back and select another date/time</a></h3></div>");
    }

    if ($is_reschedule_mode) {
        $conflict_sql = "
            SELECT appointment_id
            FROM appointments
            WHERE dentist_id = ?
              AND appointment_id <> ?
              AND status IN ('pending', 'approved', 'reschedule_requested', 'rescheduled')
              AND COALESCE(final_date, requested_date) = ?
              AND (? < COALESCE(final_end_time, requested_end_time))
              AND (? > COALESCE(final_start_time, requested_start_time))
            LIMIT 1
        ";
        $conflict_stmt = mysqli_prepare($conn, $conflict_sql);
        mysqli_stmt_bind_param($conflict_stmt, "iisss", $dentist_id, $reschedule_id, $date, $time, $end_time);
    } else {
        $conflict_sql = "
            SELECT appointment_id
            FROM appointments
            WHERE dentist_id = ?
              AND status IN ('pending', 'approved', 'reschedule_requested', 'rescheduled')
              AND COALESCE(final_date, requested_date) = ?
              AND (? < COALESCE(final_end_time, requested_end_time))
              AND (? > COALESCE(final_start_time, requested_start_time))
            LIMIT 1
        ";
        $conflict_stmt = mysqli_prepare($conn, $conflict_sql);
        mysqli_stmt_bind_param($conflict_stmt, "isss", $dentist_id, $date, $time, $end_time);
    }

    mysqli_stmt_execute($conflict_stmt);
    $conflict_result = mysqli_stmt_get_result($conflict_stmt);
    $has_conflict = $conflict_result && mysqli_num_rows($conflict_result) > 0;
    mysqli_stmt_close($conflict_stmt);

    if ($has_conflict) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>This schedule conflicts with another appointment.<br><br><a href='javascript:history.back()' style='color:#1e40af;'>Go back and select another time</a></h3></div>");
    }

    if ($is_reschedule_mode) {
        $old_status = $existing['status'];

        $update_sql = "
            UPDATE appointments
            SET requested_date = ?,
                requested_start_time = ?,
                requested_end_time = ?,
                status = 'reschedule_requested',
                last_updated_by = ?
            WHERE appointment_id = ?
              AND patient_id = ?
            LIMIT 1
        ";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sssiii", $date, $time, $end_time, $user_id, $reschedule_id, $patient_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        $history_sql = "INSERT INTO appointment_status_history
                        (appointment_id, old_status, new_status, action_by, action_notes)
                        VALUES (?, ?, 'reschedule_requested', ?, ?)";
        $history_stmt = mysqli_prepare($conn, $history_sql);
        $note = "Reschedule requested by patient";
        mysqli_stmt_bind_param($history_stmt, "isis", $reschedule_id, $old_status, $user_id, $note);
        mysqli_stmt_execute($history_stmt);
        mysqli_stmt_close($history_stmt);
    } else {
        $code = "APP-" . time();

        $insert_sql = "INSERT INTO appointments
            (appointment_code, patient_id, service_id, dentist_id,
            requested_date, requested_start_time, requested_end_time,
            status, created_by_patient)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "siiisssi", $code, $patient_id, $service_id, $dentist_id, $date, $time, $end_time, $user_id);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
    }

    header("Location: patient-dashboard.php?success=1");
    exit();
}

$page_title = "Book Appointment | Floss & Gloss Dental";
include("../includes/patient-header.php");
include("../includes/patient-navbar.php");
?>

<style>
html, body {
    height: 100%;
    margin: 0;
    overflow: hidden;
    font-family: Arial, sans-serif;
    background: #f5f7fb;
}
body {
    display: flex;
    flex-direction: column;
}
.page-shell {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.booking-page {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 2.1fr 1fr;
    gap: 16px;
    padding: 12px 18px 18px;
    box-sizing: border-box;
    overflow: visible;
}
.panel {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 18px;
    padding: 16px 18px;
    box-sizing: border-box;
    min-height: 0;
    overflow: visible;
}
.left-panel {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.right-panel {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    min-height: 0;
}
.left-panel h3,
.right-panel h3 {
    margin: 0 0 12px;
    font-size: 18px;
    color: #0b2454;
}
.booking-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
}
label {
    font-weight: 700;
    font-size: 13px;
    color: #0f172a;
    display: block;
    margin-bottom: 4px;
}
select,
input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #dde3ea;
    background: #f8fafc;
    font-size: 14px;
    box-sizing: border-box;
}
.readonly-box {
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #dde3ea;
    background: #eef2f7;
    font-size: 14px;
    box-sizing: border-box;
    color: #0f172a;
}
.btn {
    width: 100%;
    padding: 12px;
    background: #0ea5a0;
    color: #fff;
    border: none;
    border-radius: 10px;
    margin-top: 4px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
}
.calendar-box {
    background: #f8fafc;
    border: 1px solid #dde3ea;
    border-radius: 14px;
    padding: 10px 12px;
}
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 14px;
}
.calendar-header button {
    border: 1px solid #cbd5e1;
    background: #fff;
    border-radius: 8px;
    width: 28px;
    height: 28px;
    cursor: pointer;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}
.day {
    padding: 8px 4px;
    text-align: center;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
}
.day:hover {
    background: #e2f7f6;
}
.day.selected {
    background: #0ea5a0;
    color: #fff;
}
.summary-item {
    margin-bottom: 14px;
}
.summary-item span {
    font-size: 12px;
    color: #64748b;
    display: block;
    margin-bottom: 4px;
}
.summary-item strong {
    display: block;
    font-size: 18px;
    color: #0f172a;
    line-height: 1.25;
}
.right-panel {
    display: flex;
    flex-direction: column;
}
.right-panel .btn {
    margin-top: auto;
}
@media (max-width: 1200px) {
    html, body {
        overflow: auto;
    }
    .booking-page {
        grid-template-columns: 1fr;
        height: auto;
        overflow: visible;
    }
    .panel {
        overflow: visible;
    }
}
</style>

<div class="page-shell">
    <div class="booking-page">
        <div class="panel left-panel">
            <h3>Appointment Details</h3>

            <form method="POST" class="booking-form" id="bookingForm">
                <?php if ($is_reschedule_mode): ?>
                    <div>
                        <label>Selected Service</label>
                        <div class="readonly-box">
                            <?php echo htmlspecialchars($locked_service_name); ?> - ₱<?php echo number_format($locked_service_price); ?>
                        </div>
                    </div>

                    <div>
                        <label>Selected Dentist</label>
                        <div class="readonly-box">
                            Dr. <?php echo htmlspecialchars($locked_dentist_name); ?>
                        </div>
                    </div>

                    <input type="hidden" name="service_id" id="service" value="<?php echo (int)$locked_service_id; ?>">
                    <input type="hidden" name="dentist_id" id="dentist" value="<?php echo (int)$locked_dentist_id; ?>">
                <?php else: ?>
                    <div>
                        <label>Select Service</label>
                        <select name="service_id" id="service" onchange="updateSummary(); loadTimeSlots();" required>
                            <option value="" data-duration="60">Choose service</option>
                            <?php while($s = mysqli_fetch_assoc($services)) { ?>
                                <option
                                    value="<?php echo $s['service_id']; ?>"
                                    data-duration="<?php echo $s['duration_minutes']; ?>"
                                    <?php echo ($prefilled_service_id === (int)$s['service_id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($s['service_name']); ?> - ₱<?php echo number_format($s['price']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Select Dentist</label>
                        <select name="dentist_id" id="dentist" onchange="updateSummary(); loadTimeSlots();" required>
                            <option value="">Choose dentist</option>
                            <?php while($d = mysqli_fetch_assoc($dentists)) { ?>
                                <option value="<?php echo $d['dentist_id']; ?>">
                                    Dr. <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="date" id="date">
                <input type="hidden" name="time" id="time">

                <div>
                    <label>Preferred Date</label>
                    <div class="calendar-box">
                        <div class="calendar-header">
                            <button type="button" onclick="prevMonth()">‹</button>
                            <span id="monthYear"></span>
                            <button type="button" onclick="nextMonth()">›</button>
                        </div>
                        <div class="calendar-grid" id="calendar"></div>
                    </div>
                </div>

                <div>
                    <label>Preferred Time</label>
                    <select id="time_select" onchange="selectTime()" required>
                        <option value="">Choose a time slot</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="panel right-panel">
            <h3>Appointment Summary</h3>

            <div class="summary-item">
                <span>Service</span>
                <strong id="sum_service">-</strong>
            </div>

            <div class="summary-item">
                <span>Dentist</span>
                <strong id="sum_dentist">-</strong>
            </div>

            <div class="summary-item">
                <span>Date</span>
                <strong id="sum_date">-</strong>
            </div>

            <div class="summary-item">
                <span>Time</span>
                <strong id="sum_time">-</strong>
            </div>

            <button class="btn" name="book" form="bookingForm">
                <?php echo $is_reschedule_mode ? "Submit Reschedule Request" : "Submit Appointment Request"; ?>
            </button>
        </div>
    </div>
</div>

<script>
const isRescheduleMode = <?php echo $is_reschedule_mode ? 'true' : 'false'; ?>;
const lockedServiceText = <?php echo json_encode($is_reschedule_mode ? ($locked_service_name . ' - ₱' . number_format($locked_service_price)) : ''); ?>;
const lockedDentistText = <?php echo json_encode($is_reschedule_mode ? ('Dr. ' . $locked_dentist_name) : ''); ?>;
const lockedDuration = <?php echo (int)$locked_service_duration; ?>;
const excludeAppointmentId = <?php echo (int)$reschedule_id; ?>;

function getSelectedDentistId() {
    const dentistEl = document.getElementById("dentist");
    return dentistEl ? dentistEl.value : "";
}

function getCurrentDuration() {
    if (isRescheduleMode) {
        return lockedDuration || 60;
    }

    const serviceEl = document.getElementById("service");
    if (serviceEl && serviceEl.selectedIndex > 0) {
        return parseInt(serviceEl.options[serviceEl.selectedIndex].getAttribute("data-duration")) || 60;
    }

    return 60;
}

function formatTimeDisplay(rawTime) {
    if (!rawTime) return "-";
    let parts = rawTime.split(":");
    let hour = parseInt(parts[0], 10);
    let mins = parts[1] || "00";
    let suffix = hour >= 12 ? "PM" : "AM";
    let displayHour = hour % 12;
    if (displayHour === 0) displayHour = 12;
    return `${displayHour}:${mins} ${suffix}`;
}

function updateSummary() {
    let serviceEl = document.getElementById("service");
    let dentistEl = document.getElementById("dentist");

    if (isRescheduleMode) {
        document.getElementById("sum_service").innerText = lockedServiceText || "-";
        document.getElementById("sum_dentist").innerText = lockedDentistText || "-";
    } else {
        document.getElementById("sum_service").innerText =
            serviceEl && serviceEl.selectedIndex >= 0
                ? (serviceEl.options[serviceEl.selectedIndex]?.text || "-")
                : "-";

        document.getElementById("sum_dentist").innerText =
            dentistEl && dentistEl.selectedIndex >= 0
                ? (dentistEl.options[dentistEl.selectedIndex]?.text || "-")
                : "-";
    }

    document.getElementById("sum_date").innerText =
        document.getElementById("date").value || "-";

    document.getElementById("sum_time").innerText =
        formatTimeDisplay(document.getElementById("time").value || "");
}

let current = new Date();

function renderCalendar() {
    let cal = document.getElementById("calendar");
    cal.innerHTML = "";

    let y = current.getFullYear();
    let m = current.getMonth();

    document.getElementById("monthYear").innerText =
        current.toLocaleString("default", { month:"long", year:"numeric" });

    let firstDay = new Date(y, m, 1).getDay();
    let totalDays = new Date(y, m + 1, 0).getDate();

    const daysHeader = ["Su","Mo","Tu","We","Th","Fr","Sa"];
    daysHeader.forEach(day => {
        let div = document.createElement("div");
        div.innerHTML = "<strong>" + day + "</strong>";
        div.style.textAlign = "center";
        div.style.fontSize = "11px";
        div.style.color = "#64748b";
        cal.appendChild(div);
    });

    for (let i = 0; i < firstDay; i++) {
        cal.appendChild(document.createElement("div"));
    }

    let today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let d = 1; d <= totalDays; d++) {
        let div = document.createElement("div");
        div.className = "day";
        div.innerText = d;
        div.style.minHeight = "28px";

        let dateString = `${y}-${String(m + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        let calendarDay = new Date(y, m, d);

        if (calendarDay < today) {
            div.style.color = "#94a3b8";
            div.style.background = "#f1f5f9";
            div.style.cursor = "not-allowed";
            div.style.opacity = "0.4";
            div.style.pointerEvents = "none";
        } else {
            div.onclick = async () => {
                document.querySelectorAll(".day").forEach(x => x.classList.remove("selected"));
                div.classList.add("selected");
                document.getElementById("date").value = dateString;
                document.getElementById("time").value = "";
                await loadTimeSlots();
                updateSummary();
            };
        }

        cal.appendChild(div);
    }
}

async function loadTimeSlots() {
    let select = document.getElementById("time_select");
    select.innerHTML = "<option value=''>Choose a time slot</option>";

    const selectedDate = document.getElementById("date").value;
    const dentistId = getSelectedDentistId();

    if (!selectedDate || !dentistId) {
        return;
    }

    const duration = getCurrentDuration();
    let unavailableData = { full_day_blocked: false, unavailable_slots: [] };

    try {
        let url = `book-appointment.php?action=get_unavailable_slots&dentist_id=${encodeURIComponent(dentistId)}&date=${encodeURIComponent(selectedDate)}&duration=${encodeURIComponent(duration)}`;

        if (excludeAppointmentId > 0) {
            url += `&exclude_appointment_id=${encodeURIComponent(excludeAppointmentId)}`;
        }

        const response = await fetch(url, { cache: "no-store" });
        unavailableData = await response.json();
    } catch (error) {
        console.error("Failed to load unavailable slots:", error);
    }

    if (unavailableData.full_day_blocked) {
        let opt = document.createElement("option");
        opt.value = "";
        opt.text = "No available time slots";
        opt.disabled = true;
        opt.selected = true;
        select.appendChild(opt);
        document.getElementById("time").value = "";
        updateSummary();
        return;
    }

    const unavailableSlots = unavailableData.unavailable_slots || [];
    const fixedSlots = ["09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00"];

    for (const slot of fixedSlots) {
        const slotStart = new Date(`2000-01-01T${slot}:00`);
        const slotEnd = new Date(slotStart.getTime() + (duration * 60000));

        // guard: closing time
        const clinicClose = new Date("2000-01-01T17:00:00");
        if (slotEnd > clinicClose) {
            continue;
        }

        // guard: lunch overlap
        const lunchStart = new Date("2000-01-01T12:00:00");
        const lunchEnd = new Date("2000-01-01T13:00:00");
        if (slotStart < lunchEnd && slotEnd > lunchStart) {
            continue;
        }

        let overlapsUnavailable = false;

        for (const blocked of unavailableSlots) {
            const blockedStart = new Date(`2000-01-01T${blocked}:00`);
            const blockedEnd = new Date(blockedStart.getTime() + 60000 * 60);

            if (slotStart < blockedEnd && slotEnd > blockedStart) {
                overlapsUnavailable = true;
                break;
            }
        }

        if (overlapsUnavailable) {
            continue;
        }

        let opt = document.createElement("option");
        opt.value = slot;
        opt.text = formatTimeDisplay(slot);
        select.appendChild(opt);
    }

    if (select.options.length === 1) {
        let opt = document.createElement("option");
        opt.value = "";
        opt.text = "No available time slots";
        opt.disabled = true;
        opt.selected = true;
        select.appendChild(opt);
        document.getElementById("time").value = "";
    }
}

function selectTime() {
    document.getElementById("time").value =
        document.getElementById("time_select").value;
    updateSummary();
}

function prevMonth() {
    current.setMonth(current.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    current.setMonth(current.getMonth() + 1);
    renderCalendar();
}

renderCalendar();
loadTimeSlots();
updateSummary();
</script>

<?php include("../includes/patient-footer.php"); ?>