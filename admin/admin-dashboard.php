<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/database.php';

$query = "
    SELECT 
        a.appointment_id, 
        a.appointment_code, 
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        s.service_name,
        CONCAT('Dr. ', d.last_name) AS dentist_name,
        a.appointment_date,
        a.start_time,
        a.status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN services s ON a.service_id = s.service_id
    JOIN dentists d ON a.dentist_id = d.dentist_id
    WHERE a.status = 'pending'
    ORDER BY a.appointment_date ASC, a.start_time ASC
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Floss & Gloss Dental</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-dashboard">

    <div class="sidebar">
        <h2>Floss & Gloss</h2>
        <ul>
            <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
            <li><a href="manage-appointments.php">All Appointments</a></li>
            <li><a href="manage-dentists.php">Dentist Schedules</a></li>
            <li><a href="../auth/logout.php" class="logout">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>Admin Dashboard</h1>
            <p>Welcome back! Here is the overview for today.</p>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                Action successful: Appointment has been <?= htmlspecialchars($_GET['success']) ?>.
            </div>
        <?php endif; ?>

        <section class="dashboard-card">
            <h2>Pending Appointment Requests</h2>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Dentist</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['appointment_code']) ?></td>
                                <td><strong><?= htmlspecialchars($row['patient_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['service_name']) ?></td>
                                <td><?= htmlspecialchars($row['dentist_name']) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($row['appointment_date'])) ?><br>
                                    <small style="color: #6b7280;"><?= date('h:i A', strtotime($row['start_time'])) ?></small>
                                </td>
                                <td><span class="badge badge-pending">Pending</span></td>
                                <td class="action-buttons">
                                    <form action="../actions/appointment-actions.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_appointment"> 
                                        <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                    
                                    <form action="../actions/appointment-actions.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_appointment">
                                        <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                        <button type="submit" class="btn-reject" onclick="return confirm('Are you sure you want to reject this appointment?');">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #6b7280;">No pending appointments at the moment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

</body>
</html>
