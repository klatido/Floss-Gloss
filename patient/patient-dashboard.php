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

/* Total appointments */
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

/* Pending approvals */
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

/* Upcoming appointments */
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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
$full_name = htmlspecialchars(($patient['first_name'] ?? 'Patient') . ' ' . ($patient['last_name'] ?? ''));

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Floss & Gloss Dental</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #f4f6f8;
            color: #0f172a;
        }

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #dbe2ea;
            padding: 14px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0ea5a0, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            font-weight: bold;
        }

        .brand-text h2 {
            margin: 0;
            font-size: 18px;
        }

        .brand-text p {
            margin: 2px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #111827;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 600;
        }

        .nav-links a.active {
            background: #eef2f7;
        }

        .user-box {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-meta {
            text-align: right;
        }

        .user-meta strong {
            display: block;
            font-size: 15px;
        }

        .user-meta span {
            color: #64748b;
            font-size: 14px;
        }

        .logout-btn {
            text-decoration: none;
            color: #111827;
            border: 1px solid #d1d5db;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            background: #fff;
        }

        .page {
            padding: 40px;
        }

        .welcome h1 {
            margin: 0 0 10px;
            font-size: 36px;
            color: #0b2454;
        }

        .welcome p {
            margin: 0;
            color: #52637a;
            font-size: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 34px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 20px;
            padding: 28px 30px;
            min-height: 150px;
        }

        .stat-card h3 {
            margin: 0 0 38px;
            color: #40597a;
            font-size: 16px;
            font-weight: 700;
        }

        .stat-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #0b2454;
        }

        .section {
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 22px;
            padding: 30px;
            margin-top: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .section-header p {
            margin: 6px 0 0;
            color: #667085;
            font-size: 16px;
        }

        .btn-primary {
            text-decoration: none;
            background: #0ea5a0;
            color: white;
            padding: 14px 22px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px 40px;
            color: #7b8ba1;
        }

        .empty-state .icon {
            font-size: 52px;
            margin-bottom: 12px;
        }

        .appointment-card {
            border: 1px solid #e1e7ef;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            background: #fff;
        }

        .appointment-card:last-child {
            margin-bottom: 0;
        }

        .appointment-info h4 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .appointment-info .dentist {
            color: #344054;
            margin-bottom: 4px;
        }

        .appointment-info .date {
            color: #667085;
            font-size: 15px;
        }

        .badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.success {
            background: #ecfdf3;
            color: #027a48;
        }

        .badge.pending {
            background: #fff7ed;
            color: #b54708;
        }

        .badge.danger {
            background: #fef3f2;
            color: #b42318;
        }

        .badge.neutral {
            background: #f2f4f7;
            color: #344054;
        }

        .payment-box {
            background: #edf9f8;
            border: 1px solid #9ddfdc;
            border-radius: 22px;
            padding: 28px 30px;
        }

        .payment-box h2 {
            margin-top: 0;
            color: #0b4f6c;
        }

        .payment-box p {
            color: #0a4b61;
            line-height: 1.7;
            margin: 8px 0;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-box {
                width: 100%;
                justify-content: space-between;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 700px) {
            .page {
                padding: 20px;
            }

            .appointment-card {
                flex-direction: column;
            }

            .welcome h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand">
            <div class="brand-logo">🩺</div>
            <div class="brand-text">
                <h2>Floss &amp; Gloss</h2>
                <p>Dental Care</p>
            </div>
        </div>

        <div class="nav-links">
            <a href="patient-dashboard.php" class="active">Dashboard</a>
            <a href="services.php">Services</a>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
        </div>

        <div class="user-box">
            <div class="user-meta">
                <strong><?php echo $full_name; ?></strong>
                <span>Patient</span>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="page">
        <div class="welcome">
            <h1>Welcome back, <?php echo $full_name; ?>!</h1>
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
</body>
</html>