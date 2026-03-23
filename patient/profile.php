<?php
session_start();
include("../config/database.php");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die("User not logged in.");
}

$query = "SELECT * FROM patients WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$patient = mysqli_fetch_assoc($result);

if (!$patient) {
    $patient = [];
}

$patient_id = $patient['patient_id'] ?? 1;
?>

<?php
$totalQuery = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = '$patient_id'";
$totalResult = mysqli_query($conn, $totalQuery);
$total = $totalResult ? mysqli_fetch_assoc($totalResult)['total'] : 0;

$pendingQuery = "SELECT COUNT(*) as pending FROM appointments WHERE patient_id = '$patient_id' AND status='pending'";
$pendingResult = mysqli_query($conn, $pendingQuery);
$pending = $pendingResult ? mysqli_fetch_assoc($pendingResult)['pending'] : 0;

$upcomingQuery = "SELECT COUNT(*) as upcoming FROM appointments WHERE patient_id = '$patient_id' AND appointment_date >= CURDATE()";
$upcomingResult = mysqli_query($conn, $upcomingQuery);
$upcoming = $upcomingResult ? mysqli_fetch_assoc($upcomingResult)['upcoming'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard</title>
    <style>
        body { 
            font-family: Arial; 
            background: #f5f7fa; 
        }
        .container { 
            width: 90%; 
            margin: auto; 
        }
        .cards { 
            display: flex; 
            gap: 20px; 
        }
        .card {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section {
            margin-top: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        td, tr {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome back, <?php echo ($patient['first_name'] ?? 'John') . ' ' . ($patient['last_name'] ?? 'Smith'); ?>!</h2>

    <div style="text-align:center;">
        <a href="patient-dashboard.php">Dashboard</a> |
        <a href="profile.php">Profile</a> |
        <a href="appointments.php">View Appointments</a> |
        <a href="settings.php">Settings</a> |
        <a href="services.php">Services</a> 
    </div>

    <div class="cards">
        <div class="card">
            <h3>Upcoming Appointments</h3>
            <p><?php echo $upcoming; ?></p>
        </div>

        <div class="card">
            <h3>Pending Approvals</h3>
            <p><?php echo $pending; ?></p>
        </div>

        <div class="card">
            <h3>Total Appointments</h3>
            <p><?php echo $total; ?></p>
        </div>
    </div>

    <div class="section">
    <h3>Payment Instructions</h3>
    <p>Please make payment before your appointment date to confirm your booking.</p>
    <p><b>Bank Transfer:</b> Floss & Gloss Dental Clinic</p>
    <p><b>Account Number:</b> 1234-5678-9012</p>
    <p><b>GCash:</b> 0917-123-4567</p>
    <p style="font-size: 12px; color: gray;">
        Please email your proof of payment with your appointment ID.
    </p>
</div>

<div class="section">
    <h3>Appointment History</h3>
    <p>Your past dental visits</p>

    <?php
    $historyQuery = "SELECT * FROM appointments WHERE patient_id = '$patient_id' ORDER BY appointment_date DESC LIMIT 5";
    $historyResult = mysqli_query($conn, $historyQuery);

    if ($historyResult && mysqli_num_rows($historyResult) > 0) {
        while ($row = mysqli_fetch_assoc($historyResult)) {
    ?>

    <div style="padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:10px;">
        <b><?php echo $row['service'] ?? 'Service'; ?></b><br>
            <small><?php echo $row['appointment_date']; ?></small><br>
            <span style="float:right;">
                <?php echo ucfirst($row['status']); ?>
            </span>
        </div>
        <?php
            }
        } else {
            echo "<p>No appointment history</p>";
        }
        ?>
    </div>
</div>
</body>
</html>
