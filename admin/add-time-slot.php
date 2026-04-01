<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

function deriveDayOfWeekFromDate(?string $date): string {
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '';
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }

    return date('l', $ts);
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$prefilled_day = deriveDayOfWeekFromDate($selected_date);

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

        if (($admin_row['role'] ?? '') === 'system_admin') {
            $admin_role = 'Administrator';
        } elseif (($admin_row['role'] ?? '') === 'staff') {
            $admin_role = 'Staff';
        } else {
            $admin_role = ucfirst($admin_row['role'] ?? 'Administrator');
        }
    }

    mysqli_stmt_close($admin_stmt);
}

$dentists = [];
$dentists_sql = "
    SELECT
        dp.dentist_id,
        dp.first_name,
        dp.middle_name,
        dp.last_name,
        COALESCE(dp.specialization, 'General Dentistry') AS specialization,
        dp.is_active,
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
        $row['is_available_for_setup'] = ((int)($row['is_active'] ?? 1) === 1) && (($row['account_status'] ?? 'active') === 'active');
        $dentists[] = $row;
    }
}

$message = '';
$message_type = 'error';

$dentist_id = '';
$day_of_week = $prefilled_day;
$start_time = '';
$end_time = '';
$slot_limit = '1';
$is_active = '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dentist_id = trim($_POST['dentist_id'] ?? '');
    $day_of_week = trim($_POST['day_of_week'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $slot_limit = trim($_POST['slot_limit'] ?? '1');
    $is_active = isset($_POST['is_active']) ? '1' : '0';

    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    if ($dentist_id === '' || !ctype_digit($dentist_id) || (int)$dentist_id <= 0) {
        $message = 'Please select a dentist.';
    } elseif (!in_array($day_of_week, $valid_days, true)) {
        $message = 'Please select a valid day of week.';
    } elseif ($start_time === '' || $end_time === '') {
        $message = 'Please provide both start and end time.';
    } elseif (strtotime($start_time) === false || strtotime($end_time) === false) {
        $message = 'Invalid time format.';
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $message = 'End time must be later than start time.';
    } elseif (!ctype_digit($slot_limit) || (int)$slot_limit < 1) {
        $message = 'Slot limit must be at least 1.';
    } else {
        $dentist_id_int = (int)$dentist_id;
        $slot_limit_int = (int)$slot_limit;
        $is_active_int = (int)$is_active;

        $overlap_sql = "
            SELECT availability_id
            FROM dentist_availability
            WHERE dentist_id = ?
              AND day_of_week = ?
              AND is_active = 1
              AND (
                    (? < end_time) AND (? > start_time)
                  )
            LIMIT 1
        ";
        $overlap_stmt = mysqli_prepare($conn, $overlap_sql);

        if (!$overlap_stmt) {
            $message = 'Something went wrong while checking existing slots.';
        } else {
            mysqli_stmt_bind_param(
                $overlap_stmt,
                "isss",
                $dentist_id_int,
                $day_of_week,
                $start_time,
                $end_time
            );
            mysqli_stmt_execute($overlap_stmt);
            $overlap_result = mysqli_stmt_get_result($overlap_stmt);

            if ($overlap_result && mysqli_num_rows($overlap_result) > 0) {
                $message = 'This time slot overlaps with an existing active slot for the selected dentist.';
            } else {
                $insert_sql = "
                    INSERT INTO dentist_availability
                    (dentist_id, day_of_week, start_time, end_time, slot_limit, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);

                if (!$insert_stmt) {
                    $message = 'Something went wrong while saving the time slot.';
                } else {
                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "isssiii",
                        $dentist_id_int,
                        $day_of_week,
                        $start_time,
                        $end_time,
                        $slot_limit_int,
                        $is_active_int,
                        $user_id
                    );

                    if (mysqli_stmt_execute($insert_stmt)) {
                        header("Location: manage-schedules.php?date=" . urlencode($selected_date));
                        exit();
                    } else {
                        $message = 'Failed to save the time slot.';
                    }

                    mysqli_stmt_close($insert_stmt);
                }
            }

            mysqli_stmt_close($overlap_stmt);
        }
    }
}

$page_title = "Add Time Slot | Floss & Gloss Dental";
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
        padding: 24px;
        max-width: 760px;
    }

    .message-box {
        max-width: 760px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-size: 14px;
        font-weight: 700;
        color: #111827;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        background: #f8fafc;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .helper {
        font-size: 12px;
        color: #64748b;
    }

    .checkbox-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 2px;
    }

    .checkbox-wrap input {
        width: auto;
        margin: 0;
    }

    .actions {
        margin-top: 22px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn-primary,
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        padding: 12px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-primary {
        border: none;
        background: #0ea5a0;
        color: #fff;
    }

    .btn-secondary {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
    }

    @media (max-width: 760px) {
        .form-grid {
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
            <h2>Add Time Slot</h2>
            <p>Create a dentist availability slot for booking and schedule display.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message-box">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="dentist_id">Dentist</label>
                        <select name="dentist_id" id="dentist_id" required>
                            <option value="">Select dentist</option>
                            <?php foreach ($dentists as $dentist): ?>
                                <?php if (!empty($dentist['is_available_for_setup'])): ?>
                                    <option
                                        value="<?php echo (int)$dentist['dentist_id']; ?>"
                                        <?php echo ((string)$dentist_id === (string)$dentist['dentist_id']) ? 'selected' : ''; ?>
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $dentist['full_name'] . ' — ' . ($dentist['specialization'] ?? 'General Dentistry')
                                        );
                                        ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="day_of_week">Day of Week</label>
                        <select name="day_of_week" id="day_of_week" required>
                            <option value="">Select day</option>
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day):
                            ?>
                                <option value="<?php echo $day; ?>" <?php echo ($day_of_week === $day) ? 'selected' : ''; ?>>
                                    <?php echo $day; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="helper">
                            Selected date: <?php echo htmlspecialchars(date('F j, Y', strtotime($selected_date))); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="slot_limit">Slot Limit</label>
                        <input
                            type="number"
                            name="slot_limit"
                            id="slot_limit"
                            min="1"
                            value="<?php echo htmlspecialchars($slot_limit); ?>"
                            required
                        >
                        <div class="helper">How many appointments can fit in this time range.</div>
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input
                            type="time"
                            name="start_time"
                            id="start_time"
                            value="<?php echo htmlspecialchars($start_time); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input
                            type="time"
                            name="end_time"
                            id="end_time"
                            value="<?php echo htmlspecialchars($end_time); ?>"
                            required
                        >
                    </div>

                    <div class="form-group full">
                        <label>Availability Status</label>
                        <div class="checkbox-wrap">
                            <input
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?php echo ($is_active === '1') ? 'checked' : ''; ?>
                            >
                            <label for="is_active" style="margin:0; font-weight:600;">Active time slot</label>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-primary">Save Time Slot</button>
                    <a href="manage-schedules.php?date=<?php echo urlencode($selected_date); ?>" class="btn-secondary">Back to Schedules</a>
                </div>
            </form>
        </section>
    </div>
</div>