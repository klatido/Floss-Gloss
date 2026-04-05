<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff', 'dentist']);

$canManageSchedules = hasRole(['staff']);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
$fixed_time_slots = getFixedTimeSlots();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canManageSchedules) {
    header("Location: ../auth/unauthorized.php");
    exit();
}

/* --------------------------------------------------
   HELPERS
-------------------------------------------------- */
function safeDateFormat(?string $date, string $format): string {
    if (!$date) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return '';
    return date($format, $timestamp);
}

function safeTimeFormat(?string $time, string $format = 'h:i A'): string {
    if (!$time) return '';
    $timestamp = strtotime($time);
    if ($timestamp === false) return '';
    return date($format, $timestamp);
}

function initialsFromName(string $name): string {
    $name = trim(str_replace('Dr. ', '', $name));
    if ($name === '') return 'DR';

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 3) break;
    }

    return $initials ?: 'DR';
}

function getDisplayDate(array $row): string {
    return !empty($row['final_date']) ? $row['final_date'] : ($row['requested_date'] ?? '');
}

function getDisplayStart(array $row): string {
    return !empty($row['final_start_time']) ? $row['final_start_time'] : ($row['requested_start_time'] ?? '');
}

function getDisplayEnd(array $row): string {
    return !empty($row['final_end_time']) ? $row['final_end_time'] : ($row['requested_end_time'] ?? '');
}

function getFixedTimeSlots(): array {
    return [
        '09:00:00' => '10:00:00',
        '10:00:00' => '11:00:00',
        '11:00:00' => '12:00:00',
        '13:00:00' => '14:00:00',
        '14:00:00' => '15:00:00',
        '15:00:00' => '16:00:00',
        '16:00:00' => '17:00:00',
    ];
}

function isValidClinicTimeRange(string $start_time, string $end_time): bool {
    $slots = getFixedTimeSlots();
    return isset($slots[$start_time]) && $slots[$start_time] === $end_time;
}

function addMinutesToTime(string $time, int $minutes): string {
    $base = strtotime('2000-01-01 ' . $time);
    if ($base === false) return '';
    return date('H:i:s', strtotime("+{$minutes} minutes", $base));
}

function isWithinClinicWindow(string $start_time, string $end_time): bool {
    if ($start_time >= $end_time) return false;

    $morning_ok = ($start_time >= '09:00:00' && $end_time <= '12:00:00');
    $afternoon_ok = ($start_time >= '13:00:00' && $end_time <= '17:00:00');

    return $morning_ok || $afternoon_ok;
}

function dentistHasApprovedAppointmentOnDate(mysqli $conn, int $dentist_id, string $date): bool {
    $sql = "
        SELECT appointment_id
        FROM appointments
        WHERE dentist_id = ?
          AND status = 'approved'
          AND COALESCE(final_date, requested_date) = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, "is", $dentist_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = ($result && mysqli_num_rows($result) > 0);
    mysqli_stmt_close($stmt);

    return $exists;
}

function insertAppointmentHistory(
    mysqli $conn,
    int $appointment_id,
    ?string $old_status,
    string $new_status,
    int $action_by,
    string $notes
): void {
    $sql = "
        INSERT INTO appointment_status_history
        (appointment_id, old_status, new_status, action_by, action_notes)
        VALUES (?, ?, ?, ?, ?)
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "issis", $appointment_id, $old_status, $new_status, $action_by, $notes);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function dentistIsBlockedWholeDay(mysqli $conn, int $dentist_id, string $date): bool {
    $sql = "
        SELECT block_id
        FROM dentist_schedule_blocks
        WHERE dentist_id = ?
          AND block_date = ?
          AND start_time IS NULL
          AND end_time IS NULL
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, "is", $dentist_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $is_blocked = ($result && mysqli_num_rows($result) > 0);
    mysqli_stmt_close($stmt);

    return $is_blocked;
}

function hasConflictingAppointment(
    mysqli $conn,
    int $dentist_id,
    string $date,
    string $start_time,
    string $end_time,
    int $exclude_appointment_id = 0
): bool {
    $sql = "
        SELECT appointment_id
        FROM appointments
        WHERE dentist_id = ?
          AND appointment_id <> ?
          AND status IN ('approved', 'reschedule_requested', 'rescheduled')
          AND COALESCE(final_date, requested_date) = ?
          AND (
                (? < COALESCE(final_end_time, requested_end_time))
            AND (? > COALESCE(final_start_time, requested_start_time))
          )
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return true;

    mysqli_stmt_bind_param($stmt, "iisss", $dentist_id, $exclude_appointment_id, $date, $start_time, $end_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_conflict = ($result && mysqli_num_rows($result) > 0);
    mysqli_stmt_close($stmt);

    return $has_conflict;
}

/* --------------------------------------------------
   ADMIN INFO
-------------------------------------------------- */
$admin_name = "Admin Staff";
$admin_role = "Administrator";
$current_dentist_id = 0;

$admin_sql = "
    SELECT 
        u.role,
        sp.first_name AS staff_first_name,
        sp.last_name AS staff_last_name,
        dp.first_name AS dentist_first_name,
        dp.last_name AS dentist_last_name,
        dp.dentist_id
    FROM users u
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    LEFT JOIN dentist_profiles dp ON u.user_id = dp.user_id
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

        if (($admin_row['role'] ?? '') === 'dentist') {
            $name = trim(($admin_row['dentist_first_name'] ?? '') . ' ' . ($admin_row['dentist_last_name'] ?? ''));
            if ($name !== '') {
                $admin_name = 'Dr. ' . $name;
            }
            $admin_role = 'Dentist';
            $current_dentist_id = (int)($admin_row['dentist_id'] ?? 0);
        } else {
            $name = trim(($admin_row['staff_first_name'] ?? '') . ' ' . ($admin_row['staff_last_name'] ?? ''));
            if ($name !== '') {
                $admin_name = $name;
            }

            if (($admin_row['role'] ?? '') === 'system_admin') {
                $admin_role = 'Administrator';
            } elseif (($admin_row['role'] ?? '') === 'staff') {
                $admin_role = 'Staff';
            } else {
                $admin_role = ucfirst($admin_row['role'] ?? 'Administrator');
            }
        }
    }

    mysqli_stmt_close($admin_stmt);
}

/* --------------------------------------------------
   SELECTED DATE / MONTH VIEW
-------------------------------------------------- */
$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$selected_ts = strtotime($selected_date);
$selected_month = (int)date('n', $selected_ts);
$selected_year = (int)date('Y', $selected_ts);

$view_month = isset($_GET['month']) ? (int)$_GET['month'] : $selected_month;
$view_year = isset($_GET['year']) ? (int)$_GET['year'] : $selected_year;

if ($view_month < 1 || $view_month > 12) $view_month = (int)date('n');
if ($view_year < 1970 || $view_year > 2100) $view_year = (int)date('Y');

$current_view_ts = strtotime($view_year . '-' . str_pad((string)$view_month, 2, '0', STR_PAD_LEFT) . '-01');
$prev_view_ts = strtotime('-1 month', $current_view_ts);
$next_view_ts = strtotime('+1 month', $current_view_ts);
$month_label = date('F Y', $current_view_ts);

$message = '';
$message_type = 'success';

/* --------------------------------------------------
   FETCH DENTISTS
-------------------------------------------------- */
$dentists = [];

$dentists_sql = "
    SELECT
        dp.dentist_id,
        dp.user_id,
        dp.first_name,
        dp.middle_name,
        dp.last_name,
        COALESCE(dp.specialization, 'General Dentistry') AS specialization,
        dp.is_active,
        COALESCE(u.phone, 'N/A') AS phone,
        u.account_status
    FROM dentist_profiles dp
    INNER JOIN users u ON dp.user_id = u.user_id
    WHERE u.role = 'dentist'
    ORDER BY dp.first_name ASC, dp.last_name ASC
";
$dentists_result = mysqli_query($conn, $dentists_sql);

if ($dentists_result) {
    while ($row = mysqli_fetch_assoc($dentists_result)) {
        $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($full_name === '') {
            $full_name = 'Dentist #' . ($row['dentist_id'] ?? 'N/A');
        }

        $row['full_name'] = 'Dr. ' . $full_name;
        $row['initials'] = initialsFromName($row['full_name']);
        $row['is_active_account'] = ((int)($row['is_active'] ?? 1) === 1) && (($row['account_status'] ?? 'active') === 'active');
        $row['is_available'] = $row['is_active_account'];
        $row['status_text'] = $row['is_active_account'] ? 'Available' : 'Inactive';

        $dentists[$row['dentist_id']] = $row;
    }
}

/* --------------------------------------------------
   BLOCK SELECTED DATE FOR ONE SELECTED DENTIST
-------------------------------------------------- */
if ($canManageSchedules && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_date'])) {
    $block_date = $_POST['block_date_value'] ?? $selected_date;
    $block_reason = trim($_POST['block_reason'] ?? 'Blocked by admin');
    $selected_block_dentist_id = (int)($_POST['selected_dentist_id'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $block_date)) {
        $message = 'Invalid date selected.';
        $message_type = 'error';
    } elseif ($selected_block_dentist_id <= 0 || !isset($dentists[$selected_block_dentist_id])) {
        $message = 'Please select a doctor.';
        $message_type = 'error';
    } elseif (dentistHasApprovedAppointmentOnDate($conn, $selected_block_dentist_id, $block_date)) {
        $message = 'This doctor already has an approved appointment on the selected date. Reschedule first before blocking.';
        $message_type = 'error';
    } else {
        $check_sql = "
            SELECT block_id
            FROM dentist_schedule_blocks
            WHERE dentist_id = ?
              AND block_date = ?
              AND start_time IS NULL
              AND end_time IS NULL
            LIMIT 1
        ";
        $check_stmt = mysqli_prepare($conn, $check_sql);

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "is", $selected_block_dentist_id, $block_date);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $message = 'Selected doctor is already blocked on that date.';
                $message_type = 'error';
            } else {
                $insert_sql = "
                    INSERT INTO dentist_schedule_blocks
                    (dentist_id, block_date, start_time, end_time, reason, created_by)
                    VALUES (?, ?, NULL, NULL, ?, ?)
                ";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);

                if ($insert_stmt) {
                    mysqli_stmt_bind_param($insert_stmt, "issi", $selected_block_dentist_id, $block_date, $block_reason, $user_id);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = 'Doctor blocked successfully for the selected date.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to block selected doctor.';
                        $message_type = 'error';
                    }

                    mysqli_stmt_close($insert_stmt);
                }
            }

            mysqli_stmt_close($check_stmt);
        }
    }
}

/* --------------------------------------------------
   UNBLOCK SELECTED DATE FOR ONE SELECTED DENTIST
-------------------------------------------------- */
if ($canManageSchedules && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_date'])) {
    $unblock_date = $_POST['unblock_date_value'] ?? $selected_date;
    $selected_block_dentist_id = (int)($_POST['selected_dentist_id'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $unblock_date)) {
        $message = 'Invalid date selected.';
        $message_type = 'error';
    } elseif ($selected_block_dentist_id <= 0) {
        $message = 'Please select a doctor.';
        $message_type = 'error';
    } else {
        $delete_sql = "
            DELETE FROM dentist_schedule_blocks
            WHERE dentist_id = ?
              AND block_date = ?
              AND start_time IS NULL
              AND end_time IS NULL
        ";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);

        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "is", $selected_block_dentist_id, $unblock_date);

            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
                $message = 'Doctor unblocked successfully.';
                $message_type = 'success';
            } else {
                $message = 'No block found for that doctor on the selected date.';
                $message_type = 'error';
            }

            mysqli_stmt_close($delete_stmt);
        }
    }
}

/* --------------------------------------------------
   RESCHEDULE / ACCEPT RESCHEDULE APPOINTMENT
-------------------------------------------------- */
if ($canManageSchedules && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $new_date = trim($_POST['new_date'] ?? '');
    $new_start_time = trim($_POST['new_start_time'] ?? '');
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($appointment_id <= 0) {
        $message = 'Invalid appointment selected.';
        $message_type = 'error';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        $message = 'Please select a valid date.';
        $message_type = 'error';
    } elseif ($new_start_time === '') {
        $message = 'Please provide a valid start time.';
        $message_type = 'error';
    } else {
        $appointment_sql = "
            SELECT
                a.appointment_id,
                a.dentist_id,
                a.status,
                COALESCE(s.duration_minutes, 60) AS duration_minutes
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.service_id
            WHERE a.appointment_id = ?
              AND a.status IN ('approved', 'reschedule_requested')
            LIMIT 1
        ";
        $appointment_stmt = mysqli_prepare($conn, $appointment_sql);

        if ($appointment_stmt) {
            mysqli_stmt_bind_param($appointment_stmt, "i", $appointment_id);
            mysqli_stmt_execute($appointment_stmt);
            $appointment_result = mysqli_stmt_get_result($appointment_stmt);
            $appointment_row = $appointment_result ? mysqli_fetch_assoc($appointment_result) : null;
            mysqli_stmt_close($appointment_stmt);

            if (!$appointment_row) {
                $message = 'Appointment not found or cannot be rescheduled.';
                $message_type = 'error';
            } else {
                $dentist_id = (int)$appointment_row['dentist_id'];
                $old_status = $appointment_row['status'];
                $duration_minutes = (int)($appointment_row['duration_minutes'] ?? 60);

                if ($duration_minutes <= 0) {
                    $duration_minutes = 60;
                }

                $new_end_time = addMinutesToTime($new_start_time, $duration_minutes);

                if ($new_end_time === '' || !isWithinClinicWindow($new_start_time, $new_end_time)) {
                    $message = 'Selected start time does not fit the service duration within clinic hours.';
                    $message_type = 'error';
                } elseif (dentistIsBlockedWholeDay($conn, $dentist_id, $new_date)) {
                    $message = 'Selected dentist is blocked for the chosen date.';
                    $message_type = 'error';
                } elseif (hasConflictingAppointment($conn, $dentist_id, $new_date, $new_start_time, $new_end_time, $appointment_id)) {
                    $message = 'This new schedule conflicts with another active appointment.';
                    $message_type = 'error';
                } else {
                    if ($old_status === 'reschedule_requested') {
                        $note = $admin_note !== '' ? $admin_note : 'Patient reschedule request approved by admin/staff';
                    } else {
                        $note = $admin_note !== '' ? $admin_note : 'Appointment rescheduled by admin/staff';
                    }

                    $update_sql = "
                        UPDATE appointments
                        SET final_date = ?,
                            final_start_time = ?,
                            final_end_time = ?,
                            status = 'approved',
                            approval_notes = ?,
                            last_updated_by = ?
                        WHERE appointment_id = ?
                        LIMIT 1
                    ";
                    $update_stmt = mysqli_prepare($conn, $update_sql);

                    if ($update_stmt) {
                        mysqli_stmt_bind_param(
                            $update_stmt,
                            "ssssii",
                            $new_date,
                            $new_start_time,
                            $new_end_time,
                            $note,
                            $user_id,
                            $appointment_id
                        );

                        if (mysqli_stmt_execute($update_stmt)) {
                            insertAppointmentHistory(
                                $conn,
                                $appointment_id,
                                $old_status,
                                'approved',
                                $user_id,
                                $note
                            );

                            if ($old_status === 'reschedule_requested') {
                                $message = 'Reschedule request accepted successfully.';
                            } else {
                                $message = 'Appointment schedule updated successfully.';
                            }

                            $message_type = 'success';
                        } else {
                            $message = 'Failed to reschedule appointment.';
                            $message_type = 'error';
                        }

                        mysqli_stmt_close($update_stmt);
                    } else {
                        $message = 'Could not prepare reschedule action.';
                        $message_type = 'error';
                    }
                }
            }
        } else {
            $message = 'Could not validate appointment.';
            $message_type = 'error';
        }
    }
}

/* --------------------------------------------------
   CHECK BLOCKED DENTISTS FOR SELECTED DATE
-------------------------------------------------- */
$is_selected_date_blocked = false;
$blocked_dentist_ids = [];

$blocked_sql = "
    SELECT dentist_id
    FROM dentist_schedule_blocks
    WHERE block_date = ?
      AND start_time IS NULL
      AND end_time IS NULL
";
$blocked_stmt = mysqli_prepare($conn, $blocked_sql);
if ($blocked_stmt) {
    mysqli_stmt_bind_param($blocked_stmt, "s", $selected_date);
    mysqli_stmt_execute($blocked_stmt);
    $blocked_result = mysqli_stmt_get_result($blocked_stmt);

    if ($blocked_result) {
        while ($blocked_row = mysqli_fetch_assoc($blocked_result)) {
            $blocked_dentist_ids[] = (int)$blocked_row['dentist_id'];
        }
    }

    mysqli_stmt_close($blocked_stmt);
}

if (count($blocked_dentist_ids) > 0) {
    $is_selected_date_blocked = true;
}

foreach ($dentists as $dentist_id => $dentist) {
    if (!$dentist['is_active_account']) {
        $dentists[$dentist_id]['is_available'] = false;
        $dentists[$dentist_id]['status_text'] = 'Inactive';
    } elseif (in_array((int)$dentist_id, $blocked_dentist_ids, true)) {
        $dentists[$dentist_id]['is_available'] = false;
        $dentists[$dentist_id]['status_text'] = 'On Leave / Blocked';
    } else {
        $dentists[$dentist_id]['is_available'] = true;
        $dentists[$dentist_id]['status_text'] = 'Available';
    }
}

/* --------------------------------------------------
   DAILY APPOINTMENTS
-------------------------------------------------- */
$daily_appointments = [];
$daily_stmt = null;
$daily_result = null;

if ($role === 'dentist') {
    $daily_sql = "
        SELECT
            a.appointment_id,
            a.patient_id,
            a.service_id,
            a.dentist_id,
            a.requested_date,
            a.requested_start_time,
            a.requested_end_time,
            a.final_date,
            a.final_start_time,
            a.final_end_time,
            a.status,
            a.payment_status,
            s.service_name,
            TRIM(CONCAT(COALESCE(pp.first_name, ''), ' ', COALESCE(pp.last_name, ''))) AS patient_name,
            TRIM(CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, ''))) AS dentist_name,
            COALESCE(dp.specialization, 'General Dentistry') AS specialization
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        LEFT JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
        WHERE COALESCE(a.final_date, a.requested_date) = ?
          AND dp.user_id = ?
        ORDER BY COALESCE(a.final_start_time, a.requested_start_time) ASC
    ";
    $daily_stmt = mysqli_prepare($conn, $daily_sql);

    if ($daily_stmt) {
        mysqli_stmt_bind_param($daily_stmt, "si", $selected_date, $user_id);
        mysqli_stmt_execute($daily_stmt);
        $daily_result = mysqli_stmt_get_result($daily_stmt);
    }
} else {
    $daily_sql = "
        SELECT
            a.appointment_id,
            a.patient_id,
            a.service_id,
            a.dentist_id,
            a.requested_date,
            a.requested_start_time,
            a.requested_end_time,
            a.final_date,
            a.final_start_time,
            a.final_end_time,
            a.status,
            a.payment_status,
            s.service_name,
            TRIM(CONCAT(COALESCE(pp.first_name, ''), ' ', COALESCE(pp.last_name, ''))) AS patient_name,
            TRIM(CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, ''))) AS dentist_name,
            COALESCE(dp.specialization, 'General Dentistry') AS specialization
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        LEFT JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
        WHERE COALESCE(a.final_date, a.requested_date) = ?
        ORDER BY COALESCE(a.final_start_time, a.requested_start_time) ASC
    ";
    $daily_stmt = mysqli_prepare($conn, $daily_sql);

    if ($daily_stmt) {
        mysqli_stmt_bind_param($daily_stmt, "s", $selected_date);
        mysqli_stmt_execute($daily_stmt);
        $daily_result = mysqli_stmt_get_result($daily_stmt);
    }
}

if ($daily_result) {
    while ($row = mysqli_fetch_assoc($daily_result)) {
        if (trim($row['patient_name'] ?? '') === '') {
            $row['patient_name'] = 'Patient #' . ($row['patient_id'] ?? 'N/A');
        }

        if (trim($row['dentist_name'] ?? '') === '') {
            $row['dentist_name'] = 'Dentist #' . ($row['dentist_id'] ?? 'N/A');
        } else {
            $row['dentist_name'] = 'Dr. ' . trim($row['dentist_name']);
        }

        if (empty($row['service_name'])) {
            $row['service_name'] = 'Unknown Service';
        }

        $display_start = getDisplayStart($row);
        $display_end = getDisplayEnd($row);
        $row['display_time'] = safeTimeFormat($display_start, 'h:i A') . ' - ' . safeTimeFormat($display_end, 'h:i A');

        $daily_appointments[] = $row;
    }
}

if ($daily_stmt) {
    mysqli_stmt_close($daily_stmt);
}

$daily_count = count($daily_appointments);

/* --------------------------------------------------
   LIMIT DENTIST VIEW TO OWN CARD ONLY
-------------------------------------------------- */
if ($role === 'dentist' && $current_dentist_id > 0) {
    $filtered_dentists = [];
    if (isset($dentists[$current_dentist_id])) {
        $filtered_dentists[$current_dentist_id] = $dentists[$current_dentist_id];
    }
    $dentists = $filtered_dentists;
}

/* --------------------------------------------------
   RESCHEDULE REQUESTS
-------------------------------------------------- */
$reschedule_requests = [];
$approved_appointments = [];

if ($canManageSchedules) {
    $requests_sql = "
    SELECT
            a.appointment_id,
            a.patient_id,
            a.service_id,
            a.dentist_id,
            a.requested_date,
            a.requested_start_time,
            a.requested_end_time,
            a.final_date,
            a.final_start_time,
            a.final_end_time,
            a.status,
            a.approval_notes,
            s.service_name,
            COALESCE(s.duration_minutes, 60) AS duration_minutes,
            TRIM(CONCAT(COALESCE(pp.first_name, ''), ' ', COALESCE(pp.last_name, ''))) AS patient_name,
            TRIM(CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, ''))) AS dentist_name
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        LEFT JOIN dentist_profiles dp ON a.dentist_id = dp.dentist_id
        WHERE a.status IN ('reschedule_requested', 'approved')
        ORDER BY COALESCE(a.final_date, a.requested_date) ASC, COALESCE(a.final_start_time, a.requested_start_time) ASC
    ";
    $requests_result = mysqli_query($conn, $requests_sql);

    if ($requests_result) {
        while ($row = mysqli_fetch_assoc($requests_result)) {
            $row['patient_name'] = trim($row['patient_name'] ?? '') !== '' ? $row['patient_name'] : 'Patient';
            $row['dentist_name'] = trim($row['dentist_name'] ?? '') !== '' ? 'Dr. ' . trim($row['dentist_name']) : 'Dentist';
            $row['current_date'] = getDisplayDate($row);
            $row['current_start'] = getDisplayStart($row);
            $row['current_end'] = getDisplayEnd($row);

            if (($row['status'] ?? '') === 'reschedule_requested' || ($row['status'] ?? '') === 'approved') {
                $approved_appointments[] = $row;
            }
        }
    }
}

/* --------------------------------------------------
   CALENDAR BUILD
-------------------------------------------------- */
$first_day_of_month = strtotime(date('Y-m-01', $current_view_ts));
$start_day_of_week = (int)date('w', $first_day_of_month);
$days_in_month = (int)date('t', $current_view_ts);

$prev_month_ts_for_days = strtotime('-1 month', $current_view_ts);
$days_in_prev_month = (int)date('t', $prev_month_ts_for_days);

$calendar_cells = [];

for ($i = $start_day_of_week; $i > 0; $i--) {
    $day = $days_in_prev_month - $i + 1;
    $cell_date = date('Y-m-d', strtotime(date('Y-m-', $prev_month_ts_for_days) . str_pad((string)$day, 2, '0', STR_PAD_LEFT)));
    $calendar_cells[] = [
        'day' => $day,
        'date' => $cell_date,
        'current_month' => false
    ];
}

for ($day = 1; $day <= $days_in_month; $day++) {
    $cell_date = date('Y-m-d', strtotime($view_year . '-' . str_pad((string)$view_month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT)));
    $calendar_cells[] = [
        'day' => $day,
        'date' => $cell_date,
        'current_month' => true
    ];
}

$remaining = 42 - count($calendar_cells);
$next_month_ts_for_days = strtotime('+1 month', $current_view_ts);

for ($day = 1; $day <= $remaining; $day++) {
    $cell_date = date('Y-m-d', strtotime(date('Y-m-', $next_month_ts_for_days) . str_pad((string)$day, 2, '0', STR_PAD_LEFT)));
    $calendar_cells[] = [
        'day' => $day,
        'date' => $cell_date,
        'current_month' => false
    ];
}

$page_title = "Schedules | Floss & Gloss Dental";
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
        gap: 22px;
    }

    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
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

    .message-box {
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        border: 1px solid;
    }

    .message-box.success {
        background: #ecfdf3;
        color: #166534;
        border-color: #bbf7d0;
    }

    .message-box.error {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .schedule-grid {
        display: grid;
        grid-template-columns: 345px 1fr;
        gap: 22px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
    }

    .panel h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .panel-subtitle {
        margin: 6px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .calendar-box {
        margin-top: 24px;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 14px;
    }

    .calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .calendar-nav {
        width: 36px;
        height: 36px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #111827;
        font-size: 20px;
    }

    .calendar-title {
        font-size: 16px;
        font-weight: 700;
    }

    .calendar-weekdays,
    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }

    .calendar-weekdays {
        margin-bottom: 10px;
    }

    .calendar-weekdays div {
        text-align: center;
        font-size: 12px;
        color: #64748b;
        padding: 6px 0;
    }

    .calendar-day {
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 12px;
        text-decoration: none;
        color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .calendar-day:hover {
        background: #f8fafc;
        border-color: #e5e7eb;
    }

    .calendar-day.other-month {
        color: #94a3b8;
    }

    .calendar-day.selected {
        background: #020617;
        color: #ffffff;
    }

    .calendar-day.today:not(.selected) {
        border-color: #dbe2ea;
        background: #f8fafc;
        font-weight: 700;
    }

    .calendar-divider {
        border: none;
        border-top: 1px solid #e5e7eb;
        margin: 20px 0;
    }

    .block-date-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .block-date-btn {
        width: 100%;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .small-note {
        font-size: 12px;
        color: #64748b;
    }

    .daily-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 24px;
    }

    .pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid #dbe2ea;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
    }

    .schedule-list,
    .action-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .schedule-item,
    .action-card {
        border: 1px solid #dbe2ea;
        border-radius: 16px;
        padding: 18px 20px;
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: flex-start;
    }

    .schedule-time {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .schedule-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .schedule-dentist {
        font-size: 14px;
        color: #334155;
    }

    .schedule-patient {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .schedule-service {
        font-size: 14px;
        color: #64748b;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        text-transform: lowercase;
        white-space: nowrap;
    }

    .status-pending,
    .status-rescheduled,
    .status-reschedule_requested,
    .status-cancelled,
    .status-no_show {
        background: #eef2f7;
        color: #111827;
    }

    .status-approved {
        background: #020617;
        color: #ffffff;
    }

    .status-completed {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .empty-daily {
        min-height: 220px;
        border: 1px dashed #dbe2ea;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #64748b;
        text-align: center;
        padding: 20px;
    }

    .empty-daily-icon {
        font-size: 44px;
        opacity: 0.35;
        margin-bottom: 14px;
    }

    .availability-grid {
        margin-top: 24px;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }

    .dentist-card {
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 20px;
        background: #fff;
    }

    .dentist-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .dentist-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #ccfbf1;
        color: #0f766e;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .availability-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .availability-badge.available {
        background: #020617;
        color: #fff;
    }

    .availability-badge.unavailable {
        background: #eef2f7;
        color: #111827;
    }

    .dentist-name {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .dentist-spec {
        font-size: 14px;
        color: #52637a;
        margin-bottom: 8px;
    }

    .dentist-phone {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 10px;
    }

    .dentist-hours {
        font-size: 12px;
        color: #64748b;
        line-height: 1.6;
    }

    .section-block {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .action-meta {
        font-size: 13px;
        color: #475569;
        line-height: 1.6;
    }

    .action-form {
        margin-top: 14px;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
    }

    .action-form .full {
        grid-column: 1 / -1;
    }

    .action-form input,
    .action-form textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        background: #f8fafc;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .action-form textarea {
        min-height: 90px;
        resize: vertical;
    }

    .action-buttons {
        grid-column: 1 / -1;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn-primary {
        border: none;
        background: #0ea5a0;
        color: #fff;
        padding: 12px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

        .simple-reschedule-wrap {
        border: 1px solid #dbe2ea;
        border-radius: 16px;
        padding: 20px;
        background: #fff;
    }

    .simple-reschedule-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .form-row {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-row.full {
        width: 100%;
    }

    .form-row label {
        font-size: 14px;
        font-weight: 700;
        color: #111827;
    }

    .form-row select,
    .form-row input,
    .form-row textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        background: #f8fafc;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .form-row textarea {
        min-height: 90px;
        resize: vertical;
    }

    .form-row-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 14px;
    }

    .selected-appointment-preview {
        border: 1px solid #dbe2ea;
        background: #f8fafc;
        border-radius: 14px;
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .selected-appointment-preview strong {
        font-size: 14px;
        color: #0f172a;
    }

    .selected-appointment-preview span {
        font-size: 13px;
        color: #64748b;
        line-height: 1.5;
    }

        .fixed-slot-grid {
        align-items: end;
    }

    .slot-helper-note {
        font-size: 12px;
        color: #64748b;
        margin-top: -4px;
    }

    .form-row input[readonly] {
        background: #eef2f7;
        color: #334155;
        cursor: not-allowed;
    }

    @media (max-width: 700px) {
        .form-row-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1200px) {
        .availability-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 980px) {
        .schedule-grid {
            grid-template-columns: 1fr;
        }

        .page-top {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 700px) {
        .availability-grid {
            grid-template-columns: 1fr;
        }

        .daily-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .schedule-item,
        .action-card {
            flex-direction: column;
        }

        .action-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Schedules</h1>

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
            <div>
                <h2><?php echo $canManageSchedules ? 'Schedule Management' : 'My Schedule'; ?></h2>
                <p>
                    <?php echo $canManageSchedules
                        ? 'Clinic hours are fixed at 9:00 AM–5:00 PM, excluding 12:00 PM–1:00 PM.'
                        : 'View your assigned schedule for the selected date.'; ?>
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message-box <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="schedule-grid">
            <section class="panel">
                <h3>Calendar</h3>
                <p class="panel-subtitle">Pick a date to view schedule and status</p>

                <div class="calendar-box">
                    <div class="calendar-head">
                        <a
                            class="calendar-nav"
                            href="?month=<?php echo date('n', $prev_view_ts); ?>&year=<?php echo date('Y', $prev_view_ts); ?>&date=<?php echo urlencode(date('Y-m-d', strtotime('-1 month', $selected_ts))); ?>"
                        >
                            ‹
                        </a>

                        <div class="calendar-title"><?php echo htmlspecialchars($month_label); ?></div>

                        <a
                            class="calendar-nav"
                            href="?month=<?php echo date('n', $next_view_ts); ?>&year=<?php echo date('Y', $next_view_ts); ?>&date=<?php echo urlencode(date('Y-m-d', strtotime('+1 month', $selected_ts))); ?>"
                        >
                            ›
                        </a>
                    </div>

                    <div class="calendar-weekdays">
                        <div>Su</div>
                        <div>Mo</div>
                        <div>Tu</div>
                        <div>We</div>
                        <div>Th</div>
                        <div>Fr</div>
                        <div>Sa</div>
                    </div>

                    <div class="calendar-days">
                        <?php foreach ($calendar_cells as $cell): ?>
                            <?php
                                $classes = ['calendar-day'];
                                if (!$cell['current_month']) $classes[] = 'other-month';
                                if ($cell['date'] === date('Y-m-d')) $classes[] = 'today';
                                if ($cell['date'] === $selected_date) $classes[] = 'selected';
                            ?>
                            <a
                                class="<?php echo implode(' ', $classes); ?>"
                                href="?month=<?php echo $view_month; ?>&year=<?php echo $view_year; ?>&date=<?php echo urlencode($cell['date']); ?>"
                            >
                                <?php echo (int)$cell['day']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="calendar-divider">

                <?php if ($canManageSchedules): ?>
                    <form method="POST" class="block-date-form">
                        <select name="selected_dentist_id" required style="width:100%; padding:12px 14px; border:1px solid #dbe2ea; border-radius:12px; background:#f8fafc; font-size:14px;">
                            <option value="">Select doctor</option>
                            <?php foreach ($dentists as $dentist): ?>
                                <option value="<?php echo (int)$dentist['dentist_id']; ?>">
                                    <?php echo htmlspecialchars($dentist['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($is_selected_date_blocked): ?>
                            <input type="hidden" name="unblock_date" value="1">
                            <input type="hidden" name="unblock_date_value" value="<?php echo htmlspecialchars($selected_date); ?>">
                            <button type="submit" class="block-date-btn">🔓 Unblock Whole Day</button>
                        <?php else: ?>
                            <input type="hidden" name="block_date" value="1">
                            <input type="hidden" name="block_date_value" value="<?php echo htmlspecialchars($selected_date); ?>">
                            <button type="submit" class="block-date-btn">🗑️ Block Whole Day</button>
                        <?php endif; ?>

                        <div class="small-note">
                            Selected date: <?php echo htmlspecialchars(date('F j, Y', $selected_ts)); ?>
                            <?php if ($is_selected_date_blocked): ?>
                                — at least one doctor blocked
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="small-note">
                        Selected date: <?php echo htmlspecialchars(date('F j, Y', $selected_ts)); ?>
                        <?php if ($is_selected_date_blocked): ?>
                            — blocked
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="daily-top">
                    <div>
                        <h3>Daily Schedule</h3>
                        <p class="panel-subtitle"><?php echo htmlspecialchars(date('l, F j, Y', $selected_ts)); ?></p>
                    </div>

                    <div class="pill-count"><?php echo $daily_count; ?> appointment<?php echo $daily_count !== 1 ? 's' : ''; ?></div>
                </div>

                <?php if ($daily_count > 0): ?>
                    <div class="schedule-list">
                        <?php foreach ($daily_appointments as $appointment): ?>
                            <?php
                                $status = strtolower(trim($appointment['status'] ?? 'pending'));
                                $status_class = 'status-pending';

                                if ($status === 'approved') $status_class = 'status-approved';
                                elseif ($status === 'completed') $status_class = 'status-completed';
                                elseif ($status === 'rejected') $status_class = 'status-rejected';
                                elseif ($status === 'rescheduled') $status_class = 'status-rescheduled';
                                elseif ($status === 'reschedule_requested') $status_class = 'status-reschedule_requested';
                                elseif ($status === 'cancelled') $status_class = 'status-cancelled';
                                elseif ($status === 'no_show') $status_class = 'status-no_show';
                            ?>
                            <div class="schedule-item">
                                <div>
                                    <div class="schedule-time">
                                        <?php echo htmlspecialchars($appointment['display_time']); ?>
                                    </div>

                                    <div class="schedule-meta">
                                        <div class="schedule-dentist"><?php echo htmlspecialchars($appointment['dentist_name']); ?></div>
                                        <div class="schedule-patient"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                        <div class="schedule-service"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                                    </div>
                                </div>

                                <div>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($appointment['status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-daily">
                        <div class="empty-daily-icon">📅</div>
                        <div style="font-size:16px;">No appointments scheduled for this date</div>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel">
            <h3><?php echo $role === 'dentist' ? 'My Status' : 'Doctor Status'; ?></h3>
            <p class="panel-subtitle">Shows who is available or on leave / blocked</p>

            <div class="availability-grid">
                <?php if (count($dentists) > 0): ?>
                    <?php foreach ($dentists as $dentist): ?>
                        <div class="dentist-card">
                            <div class="dentist-card-top">
                                <div class="dentist-avatar">
                                    <?php echo htmlspecialchars($dentist['initials'] ?? 'DR'); ?>
                                </div>

                                <span class="availability-badge <?php echo !empty($dentist['is_available']) ? 'available' : 'unavailable'; ?>">
                                    <?php echo htmlspecialchars($dentist['status_text']); ?>
                                </span>
                            </div>

                            <div class="dentist-name"><?php echo htmlspecialchars($dentist['full_name'] ?? 'Unknown Dentist'); ?></div>
                            <div class="dentist-spec"><?php echo htmlspecialchars($dentist['specialization'] ?? 'General Dentistry'); ?></div>
                            <div class="dentist-phone"><?php echo htmlspecialchars($dentist['phone'] ?? 'N/A'); ?></div>

                            <div class="dentist-hours">
                                Regular clinic hours:<br>
                                9:00 AM – 12:00 PM<br>
                                1:00 PM – 5:00 PM
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small-note">No dentist records found.</div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canManageSchedules): ?>
            <section class="panel section-block">
                <h3>Reschedule / Accept Request</h3>
                <p class="panel-subtitle">Select an approved appointment or patient reschedule request, then choose a new valid date and time for the same doctor.</p>

                <?php if (count($approved_appointments) > 0): ?>
                    <div class="simple-reschedule-wrap">
                        <form method="POST" class="simple-reschedule-form">
                            <input type="hidden" name="reschedule_appointment" value="1">

                            <div class="form-row full">
                                <label for="approved_appointment_select">Approved Appointment</label>
                                <select name="appointment_id" id="approved_appointment_select" required>
                                    <option value="">Select approved appointment</option>
                                    <?php foreach ($approved_appointments as $item): ?>
                                        <?php
                                            $option_label =
                                                $item['patient_name'] . ' — ' .
                                                $item['service_name'] . ' — ' .
                                                safeDateFormat($item['current_date'], 'F j, Y') . ' — ' .
                                                safeTimeFormat($item['current_start']) . ' - ' . safeTimeFormat($item['current_end']) . ' — ' .
                                                $item['dentist_name'];
                                        ?>
                                        <option
                                            value="<?php echo (int)$item['appointment_id']; ?>"
                                            data-patient="<?php echo htmlspecialchars($item['patient_name']); ?>"
                                            data-service="<?php echo htmlspecialchars($item['service_name'] ?? 'Unknown Service'); ?>"
                                            data-dentist="<?php echo htmlspecialchars($item['dentist_name']); ?>"
                                            data-dentist-id="<?php echo (int)$item['dentist_id']; ?>"
                                            data-duration="<?php echo (int)($item['duration_minutes'] ?? 60); ?>"
                                            data-date="<?php echo htmlspecialchars(safeDateFormat($item['current_date'], 'F j, Y')); ?>"
                                            data-time="<?php echo htmlspecialchars(safeTimeFormat($item['current_start']) . ' - ' . safeTimeFormat($item['current_end'])); ?>"
                                        >
                                            <?php echo htmlspecialchars($option_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="selected-appointment-preview" id="approvedAppointmentPreview">
                                <strong>No appointment selected yet.</strong>
                                <span>Choose one from the dropdown above.</span>
                            </div>

                            <div class="form-row-grid fixed-slot-grid">
                                <div class="form-row">
                                    <label for="approved_new_date">New Date</label>
                                    <input
                                        type="date"
                                        name="new_date"
                                        id="approved_new_date"
                                        required
                                        min="<?php echo htmlspecialchars(date('Y-m-d')); ?>"
                                    >
                                </div>

                                <div class="form-row">
                                    <label for="approved_new_start_time">Time Slot</label>
                                    <select name="new_start_time" id="approved_new_start_time" required>
                                        <option value="">Select time slot</option>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="approved_new_end_time_display">End Time</label>
                                    <input
                                        type="text"
                                        id="approved_new_end_time_display"
                                        value=""
                                        placeholder="Auto-filled"
                                        readonly
                                    >
                                    <input type="hidden" name="new_end_time" id="approved_new_end_time" required>
                                </div>
                            </div>

                            <div class="form-row full">
                                <label for="approved_admin_note">Admin Note</label>
                                <textarea
                                    name="admin_note"
                                    id="approved_admin_note"
                                    placeholder="Optional note"
                                ></textarea>
                            </div>

                            <div class="slot-helper-note" id="slotHelperNote">
                                Time slots adjust automatically based on the selected service duration.
                            </div>
                            <div class="action-buttons">
                                <button type="submit" class="btn-primary">Reschedule Appointment</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-daily">
                        <div class="empty-daily-icon">✅</div>
                        <div style="font-size:16px;">No approved appointments available for rescheduling</div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div style="flex: 1;"></div>

        <script>
document.addEventListener('DOMContentLoaded', function () {
    const appointmentSelect = document.getElementById('approved_appointment_select');
    const preview = document.getElementById('approvedAppointmentPreview');
    const dateInput = document.getElementById('approved_new_date');
    const startTimeSelect = document.getElementById('approved_new_start_time');
    const endTimeHidden = document.getElementById('approved_new_end_time');
    const endTimeDisplay = document.getElementById('approved_new_end_time_display');
    const helperNote = document.getElementById('slotHelperNote');

    function formatTimeTo12Hour(value) {
        if (!value) return '';
        const parts = value.split(':');
        let hour = parseInt(parts[0], 10);
        const minute = parts[1] || '00';
        const suffix = hour >= 12 ? 'PM' : 'AM';

        hour = hour % 12;
        if (hour === 0) hour = 12;

        return `${hour}:${minute} ${suffix}`;
    }

    function addMinutesToTime(time, minutes) {
        const [h, m, s] = time.split(':').map(Number);
        const base = new Date(2000, 0, 1, h, m || 0, s || 0);
        base.setMinutes(base.getMinutes() + minutes);

        const hh = String(base.getHours()).padStart(2, '0');
        const mm = String(base.getMinutes()).padStart(2, '0');
        const ss = String(base.getSeconds()).padStart(2, '0');

        return `${hh}:${mm}:${ss}`;
    }

    function updateApprovedPreview() {
        if (!appointmentSelect || !preview) return;

        const selectedOption = appointmentSelect.options[appointmentSelect.selectedIndex];

        if (!selectedOption || !selectedOption.value) {
            preview.innerHTML = `
                <strong>No appointment selected yet.</strong>
                <span>Choose one from the dropdown above.</span>
            `;
            return;
        }

        const patient = selectedOption.getAttribute('data-patient') || 'Patient';
        const service = selectedOption.getAttribute('data-service') || 'Service';
        const dentist = selectedOption.getAttribute('data-dentist') || 'Dentist';
        const date = selectedOption.getAttribute('data-date') || '';
        const time = selectedOption.getAttribute('data-time') || '';
        const duration = parseInt(selectedOption.getAttribute('data-duration') || '60', 10);

        preview.innerHTML = `
            <strong>${patient}</strong>
            <span>${dentist} • ${service}</span>
            <span>Current: ${date} • ${time}</span>
            <span>Service duration: ${duration} minute(s)</span>
        `;
    }

    function getSelectedDuration() {
        if (!appointmentSelect) return 60;
        const selectedOption = appointmentSelect.options[appointmentSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return 60;
        return parseInt(selectedOption.getAttribute('data-duration') || '60', 10);
    }

    function getSelectedDentistId() {
        if (!appointmentSelect) return '';
        const selectedOption = appointmentSelect.options[appointmentSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return '';
        return selectedOption.getAttribute('data-dentist-id') || '';
    }

    function updateEndTimeFromSelectedSlot() {
        if (!startTimeSelect || !endTimeHidden || !endTimeDisplay) return;

        const selectedStart = startTimeSelect.value;
        if (!selectedStart) {
            endTimeHidden.value = '';
            endTimeDisplay.value = '';
            return;
        }

        const duration = getSelectedDuration();
        const computedEnd = addMinutesToTime(selectedStart, duration);

        endTimeHidden.value = computedEnd;
        endTimeDisplay.value = formatTimeTo12Hour(computedEnd);
    }

    async function loadApprovedTimeSlots() {
        if (!appointmentSelect || !dateInput || !startTimeSelect) return;

        startTimeSelect.innerHTML = `<option value="">Select time slot</option>`;
        endTimeHidden.value = '';
        endTimeDisplay.value = '';

        const selectedOption = appointmentSelect.options[appointmentSelect.selectedIndex];
        const selectedDate = dateInput.value;
        const dentistId = getSelectedDentistId();
        const duration = getSelectedDuration();
        const appointmentId = selectedOption && selectedOption.value ? selectedOption.value : '';

        if (!selectedDate || !dentistId || !appointmentId) {
            if (helperNote) {
                helperNote.textContent = 'Time slots adjust automatically based on the selected service duration.';
            }
            return;
        }

        let unavailableData = { full_day_blocked: false, unavailable_slots: [] };

        try {
            let url = `../patient/book-appointment.php?action=get_unavailable_slots`
                + `&dentist_id=${encodeURIComponent(dentistId)}`
                + `&date=${encodeURIComponent(selectedDate)}`
                + `&duration=${encodeURIComponent(duration)}`
                + `&exclude_appointment_id=${encodeURIComponent(appointmentId)}`;

            const response = await fetch(url, { cache: 'no-store' });
            unavailableData = await response.json();
        } catch (error) {
            console.error('Failed to load unavailable slots:', error);
        }

        if (unavailableData.full_day_blocked) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.text = 'No available time slots';
            opt.disabled = true;
            opt.selected = true;
            startTimeSelect.appendChild(opt);

            if (helperNote) {
                helperNote.textContent = 'Selected doctor is blocked for the whole day.';
            }

            return;
        }

        const unavailableSlots = unavailableData.unavailable_slots || [];
        const fixedSlots = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];

        for (const slot of fixedSlots) {
            const slotStart = new Date(`2000-01-01T${slot}:00`);
            const slotEnd = new Date(slotStart.getTime() + (duration * 60000));

            const clinicClose = new Date('2000-01-01T17:00:00');
            if (slotEnd > clinicClose) {
                continue;
            }

            const lunchStart = new Date('2000-01-01T12:00:00');
            const lunchEnd = new Date('2000-01-01T13:00:00');
            if (slotStart < lunchEnd && slotEnd > lunchStart) {
                continue;
            }

            let overlapsUnavailable = false;

            for (const blocked of unavailableSlots) {
                const blockedStart = new Date(`2000-01-01T${blocked}:00`);
                const blockedEnd = new Date(blockedStart.getTime() + (60 * 60000));

                if (slotStart < blockedEnd && slotEnd > blockedStart) {
                    overlapsUnavailable = true;
                    break;
                }
            }

            if (overlapsUnavailable) {
                continue;
            }

            const rawStart = `${slot}:00`;
            const rawEnd = addMinutesToTime(rawStart, duration);

            const opt = document.createElement('option');
            opt.value = rawStart;
            opt.text = `${formatTimeTo12Hour(rawStart)} - ${formatTimeTo12Hour(rawEnd)}`;
            startTimeSelect.appendChild(opt);
        }

        if (startTimeSelect.options.length === 1) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.text = 'No available time slots';
            opt.disabled = true;
            opt.selected = true;
            startTimeSelect.appendChild(opt);

            if (helperNote) {
                helperNote.textContent = `No valid slots available for this ${duration}-minute service.`;
            }
            return;
        }

        if (helperNote) {
            helperNote.textContent = `Showing valid slots for a ${duration}-minute service.`;
        }
    }

    if (appointmentSelect) {
        appointmentSelect.addEventListener('change', async function () {
            updateApprovedPreview();
            await loadApprovedTimeSlots();
            updateEndTimeFromSelectedSlot();
        });

        updateApprovedPreview();
    }

    if (dateInput) {
        dateInput.addEventListener('change', async function () {
            await loadApprovedTimeSlots();
            updateEndTimeFromSelectedSlot();
        });
    }

    if (startTimeSelect) {
        startTimeSelect.addEventListener('change', updateEndTimeFromSelectedSlot);
    }

    if (appointmentSelect && dateInput && dateInput.value) {
        loadApprovedTimeSlots().then(updateEndTimeFromSelectedSlot);
    }
});
</script>


        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>