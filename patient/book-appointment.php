<?php
session_start();
include("../config/database.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

$user_id = $_SESSION['user_id'];

// GET SERVICES
$services = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1");

// GET DENTISTS
$dentists = mysqli_query($conn, "SELECT * FROM dentist_profiles WHERE is_active = 1");

// HANDLE BOOKING
if (isset($_POST['book'])) {

    $service_id = $_POST['service_id'];
    $dentist_id = $_POST['dentist_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $p = mysqli_query($conn, "SELECT patient_id FROM patient_profiles WHERE user_id='$user_id'");
    $patient = mysqli_fetch_assoc($p);

    $patient_id = $patient['patient_id'];

    $end_time = date('H:i:s', strtotime($time . ' +1 hour'));
    $code = "APP-" . time();

    mysqli_query($conn, "INSERT INTO appointments
    (appointment_code, patient_id, service_id, dentist_id,
    requested_date, requested_start_time, requested_end_time,
    status, created_by_patient)

    VALUES
    ('$code','$patient_id','$service_id','$dentist_id',
    '$date','$time','$end_time','pending','$user_id')");

    header("Location: patient-dashboard.php?success=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Book Appointment</title>

<style>
body {
    margin:0;
    font-family: Arial;
    background:#f5f7fb;
}

/* NAVBAR */
.navbar {
    background: #fff;
    border-bottom: 1px solid #dbe2ea;
    padding: 14px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-weight: 700;
    font-size: 16px;
    color: #0b2454;
}

.nav-links a {
    text-decoration: none;
    color: #334155;
    font-size: 14px;
    margin-left: 20px;
    font-weight: 500;
}

.nav-links a:hover {
    color: #0ea5a0;
}

/* LAYOUT */
.container {
    display:flex;
    gap:20px;
    padding:24px;
}

/* PANELS */
.panel {
    background:#fff;
    border:1px solid #dde3ea;
    border-radius:18px;
    padding:22px;
}

.left { flex:2; }
.right { flex:1; }

/* FORM */
label {
    font-size:13px;
    font-weight:700;
    display:block;
    margin-top:12px;
}

select, input {
    width:100%;
    padding:12px;
    border-radius:12px;
    border:1px solid #dbe2ea;
    margin-top:6px;
    background:#f8fafc;
}

/* BUTTON */
.btn {
    width:100%;
    padding:12px;
    background:#0ea5a0;
    color:#fff;
    border:none;
    border-radius:12px;
    margin-top:18px;
    font-weight:700;
    cursor:pointer;
}

/* SUMMARY */
.summary-item {
    margin-bottom:14px;
}

.summary-item span {
    display:block;
    font-size:13px;
    color:#64748b;
}

.summary-item strong {
    font-size:15px;
}
</style>

<script>
function updateSummary() {
    let s = document.getElementById("service");
    let d = document.getElementById("dentist");

    document.getElementById("sum_service").innerText =
        s.options[s.selectedIndex].text;

    document.getElementById("sum_dentist").innerText =
        d.options[d.selectedIndex].text;

    document.getElementById("sum_date").innerText =
        document.getElementById("date").value;

    document.getElementById("sum_time").innerText =
        document.getElementById("time").value;
}
</script>

</head>

<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="logo">Floss & Gloss</div>
    <div class="nav-links">
        <a href="patient-dashboard.php">Dashboard</a>
        <a href="services.php">Services</a>
        <a href="profile.php">Profile</a>
        <a href="settings.php">Settings</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</div>

<div class="container">

<!-- LEFT PANEL -->
<div class="panel left">

<h3>Appointment Details</h3>

<form method="POST">

<label>Select Service</label>
<select name="service_id" id="service" onchange="updateSummary()" required>
<option value="">Choose service</option>
<?php while($s = mysqli_fetch_assoc($services)) { ?>
<option value="<?= $s['service_id'] ?>">
<?= $s['service_name'] ?> - ₱<?= number_format($s['price']) ?>
</option>
<?php } ?>
</select>

<label>Select Dentist</label>
<select name="dentist_id" id="dentist" onchange="updateSummary()" required>
<option value="">Choose dentist</option>
<?php while($d = mysqli_fetch_assoc($dentists)) { ?>
<option value="<?= $d['dentist_id'] ?>">
Dr. <?= $d['first_name'] ?> <?= $d['last_name'] ?>
</option>
<?php } ?>
</select>

<label>Date</label>
<input type="date" name="date" id="date" onchange="updateSummary()" required>

<label>Time</label>
<input type="time" name="time" id="time" onchange="updateSummary()" required>

<button class="btn" name="book">Submit Appointment Request</button>

</form>

</div>

<!-- RIGHT PANEL -->
<div class="panel right">

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

</div>

</div>

</body>
</html>