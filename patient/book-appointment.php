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

    $service_query = mysqli_query($conn, "SELECT duration_minutes FROM services WHERE service_id = '$service_id'");
    $service_data = mysqli_fetch_assoc($service_query);
    $duration = $service_data['duration_minutes'] ? $service_data['duration_minutes'] : 60;

    $end_time = date('H:i:s', strtotime($time . " +$duration minutes"));

    if (strtotime($end_time) > strtotime('17:00:00')) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Error: This appointment extends past our 5:00 PM closing time.<br><br><a href='book-appointment.php' style='color:#1e40af;'>Go back and select an earlier time</a></h3></div>");
    }

    $today_date = date('Y-m-d');
    if ($date < $today_date) {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f5f7fb;'><h3 style='padding:40px; color:#991b1b; background:#fee2e2; border-radius:12px; font-family:sans-serif; text-align:center;'>Security Error: You cannot book an appointment in the past.<br><br><a href='book-appointment.php' style='color:#1e40af;'>Go back to the booking form</a></h3></div>");
    }

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

   <?php
        $page_title = "Patient Dashboard | Floss & Gloss Dental";
        include("../includes/patient-header.php");
        include("../includes/patient-navbar.php");
    ?>

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
<select name="service_id" id="service" onchange="updateSummary(); loadTimeSlots();" required>
    <option value="" data-duration="60">Choose service</option>
    <?php while($s = mysqli_fetch_assoc($services)) { ?>
        <option value="<?= $s['service_id'] ?>" data-duration="<?= $s['duration_minutes'] ?>">
            <?= htmlspecialchars($s['service_name']) ?> - ₱<?= number_format($s['price']) ?>
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

    document.getElementById("monthYear").innerText = current.toLocaleString("default", { month:"long", year:"numeric" });

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

    for(let i=0; i<firstDay; i++){
        cal.appendChild(document.createElement("div"));
    }

    let today = new Date();
    today.setHours(0, 0, 0, 0); 

    for(let d=1; d<=totalDays; d++) {
        let div = document.createElement("div");
        div.className = "day";
        div.innerText = d;

        let dateString = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        
        let calendarDay = new Date(y, m, d);

        if (calendarDay < today) {
            div.style.color = "#94a3b8"; 
            div.style.background = "#f1f5f9";
            div.style.cursor = "not-allowed";
            div.style.opacity = "0.4"; 
        
            div.style.pointerEvents = "none"; 
        } else {
            div.classList.add("selectable");
            div.onclick = () => {
                document.querySelectorAll(".day").forEach(x => x.classList.remove("selected"));
                div.classList.add("selected");

                document.getElementById("date").value = dateString;

                loadTimeSlots(); 
                updateSummary();
            };
        }

        cal.appendChild(div);
    }
}

function loadTimeSlots() {
    let select = document.getElementById("time_select");
    select.innerHTML = "<option value=''>Choose a time slot</option>";

    let sSelect = document.getElementById("service");
    let duration = 60; // Default to 1 hour
    if (sSelect.selectedIndex > 0) {
        duration = parseInt(sSelect.options[sSelect.selectedIndex].getAttribute("data-duration")) || 60;
    }

    let endHour = (duration >= 120) ? 15 : 16;

    for(let h = 9; h <= endHour; h++) {
        let timeStr = `${String(h).padStart(2,'0')}:00`;
        
        let displayTime = h < 12 ? `${h}:00 AM` : (h === 12 ? `12:00 PM` : `${h - 12}:00 PM`);

        let opt = document.createElement("option");
        opt.value = timeStr;
        opt.text = displayTime;
        select.appendChild(opt);
    }
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
