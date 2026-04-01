<?php
session_start();
include("../config/database.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

$user_id = $_SESSION['user_id'];

/* =========================
   GET USER NAME (NEW)
========================= */
$user_query = mysqli_query($conn, "
SELECT first_name, last_name 
FROM patient_profiles 
WHERE user_id = '$user_id'
");
$user = mysqli_fetch_assoc($user_query);

/* =========================
   GET SERVICES
========================= */
$services = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1");

/* =========================
   GET DENTISTS
========================= */
$dentists = mysqli_query($conn, "SELECT * FROM dentist_profiles WHERE is_active = 1");

/* =========================
   HANDLE BOOKING
========================= */
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

/* ================= NAVBAR ================= */
.navbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 30px;
    background:#fff;
    border-bottom:1px solid #dde3ea;
}

.nav-left {
    display:flex;
    align-items:center;
    gap:10px;
}

.logo-box {
    width:40px;
    height:40px;
    background:linear-gradient(135deg,#0ea5a0,#2563eb);
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
}

.brand span {
    font-size:12px;
    color:#64748b;
}

.nav-center a {
    margin:0 10px;
    text-decoration:none;
    color:#334155;
    padding:8px 14px;
    border-radius:10px;
}

.nav-center a:hover {
    background:#e2e8f0;
}

.nav-right {
    display:flex;
    align-items:center;
    gap:15px;
}

.user span {
    font-size:12px;
    color:#64748b;
}

.logout {
    border:1px solid #dde3ea;
    padding:6px 12px;
    border-radius:10px;
    text-decoration:none;
    color:#334155;
}

/* ================= LAYOUT ================= */
.container {
    display:flex;
    gap:20px;
    padding:24px;
}

.panel {
    background:#fff;
    border:1px solid #dde3ea;
    border-radius:18px;
    padding:22px;
}

.left { flex:2; }
.right { flex:1; }

/* ================= FORM ================= */
label {
    font-weight:600;
    font-size:13px;
    margin-top:12px;
    display:block;
}

select, input {
    width:100%;
    padding:12px;
    border-radius:12px;
    border:1px solid #dde3ea;
    background:#f8fafc;
    margin-top:6px;
}

/* ================= BUTTON ================= */
.btn {
    width:100%;
    padding:14px;
    background:#0ea5a0;
    color:#fff;
    border:none;
    border-radius:12px;
    margin-top:20px;
    font-weight:bold;
}

/* ================= CALENDAR ================= */
.calendar-box {
    background:#f8fafc;
    border:1px solid #dde3ea;
    border-radius:16px;
    padding:15px;
    margin-top:10px;
}

.calendar-header {
    display:flex;
    justify-content:space-between;
    margin-bottom:10px;
    font-weight:bold;
}

.calendar-grid {
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:10px;
}

.day {
    padding:12px;
    text-align:center;
    border-radius:10px;
    cursor:pointer;
}

.day:hover {
    background:#e2f7f6;
}

.day.selected {
    background:#0ea5a0;
    color:#fff;
}

/* ================= SUMMARY ================= */
.summary-item {
    margin-bottom:14px;
}

.summary-item span {
    font-size:13px;
    color:#64748b;
}

.summary-item strong {
    display:block;
}
</style>

<script>
function updateSummary() {
    let s = document.getElementById("service");
    let d = document.getElementById("dentist");

    document.getElementById("sum_service").innerText =
        s.options[s.selectedIndex]?.text || "-";

    document.getElementById("sum_dentist").innerText =
        d.options[d.selectedIndex]?.text || "-";

    document.getElementById("sum_date").innerText =
        document.getElementById("date").value || "-";

    document.getElementById("sum_time").innerText =
        document.getElementById("time").value || "-";
}
</script>

</head>

<body>

<!-- NAVBAR -->
<div class="navbar">

    <div class="nav-left">
        <div class="logo-box">🦷</div>
        <div class="brand">
            <strong>Floss & Gloss</strong><br>
            <span>Dental Care</span>
        </div>
    </div>

    <div class="nav-center">
        <a href="patient-dashboard.php">Dashboard</a>
        <a href="services.php">Services</a>
        <a href="profile.php">Profile</a>
        <a href="settings.php">Settings</a>
    </div>

    <div class="nav-right">
        <div class="user">
            <strong><?= $user['first_name'] . ' ' . $user['last_name'] ?></strong><br>
            <span>Patient</span>
        </div>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </div>

</div>

<div class="container">

<!-- LEFT -->
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

<input type="hidden" name="date" id="date">
<input type="hidden" name="time" id="time">

<label>Preferred Date</label>
<div class="calendar-box">
    <div class="calendar-header">
        <button type="button" onclick="prevMonth()">‹</button>
        <span id="monthYear"></span>
        <button type="button" onclick="nextMonth()">›</button>
    </div>
    <div class="calendar-grid" id="calendar"></div>
</div>

<label>Preferred Time</label>
<select id="time_select" onchange="selectTime()" required>
<option value="">Choose a time slot</option>
</select>

<button class="btn" name="book">Submit Appointment Request</button>

</form>

</div>

<!-- RIGHT -->
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

<script>
let current = new Date();

function renderCalendar() {
    let cal = document.getElementById("calendar");
    cal.innerHTML = "";

    let y = current.getFullYear();
    let m = current.getMonth();

    document.getElementById("monthYear").innerText =
        current.toLocaleString("default", { month:"long", year:"numeric" });

    let firstDay = new Date(y, m, 1).getDay();
    let totalDays = new Date(y, m+1, 0).getDate();

    const daysHeader = ["Su","Mo","Tu","We","Th","Fr","Sa"];
    daysHeader.forEach(day => {
        let div = document.createElement("div");
        div.innerHTML = "<strong>"+day+"</strong>";
        div.style.textAlign="center";
        div.style.fontSize="12px";
        div.style.color="#64748b";
        cal.appendChild(div);
    });

    for(let i=0;i<firstDay;i++){
        cal.appendChild(document.createElement("div"));
    }

    for(let d=1; d<=totalDays; d++) {
        let div = document.createElement("div");
        div.className="day";
        div.innerText=d;

        let date = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;

        div.onclick = () => {
            document.querySelectorAll(".day").forEach(x=>x.classList.remove("selected"));
            div.classList.add("selected");

            document.getElementById("date").value = date;

            loadTimeSlots();
            updateSummary();
        };

        cal.appendChild(div);
    }
}

function loadTimeSlots() {
    let select = document.getElementById("time_select");
    select.innerHTML = "<option>Select time</option>";

    let hours = [9,10,11,12,13,14,15,16,17];

    hours.forEach(h=>{
        let time = `${String(h).padStart(2,'0')}:00`;

        let opt = document.createElement("option");
        opt.value = time;
        opt.text = time;

        select.appendChild(opt);
    });
}

function selectTime() {
    document.getElementById("time").value =
        document.getElementById("time_select").value;

    updateSummary();
}

function prevMonth() {
    current.setMonth(current.getMonth()-1);
    renderCalendar();
}

function nextMonth() {
    current.setMonth(current.getMonth()+1);
    renderCalendar();
}

renderCalendar();
</script>

</body>
</html>