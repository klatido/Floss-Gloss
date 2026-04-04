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
   GET PATIENT PROFILE
========================= */
$patient = null;
$patient_query = mysqli_query($conn, "
    SELECT pp.*, u.email, u.phone
    FROM patient_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE pp.user_id = '$user_id'
    LIMIT 1
");

if ($patient_query && mysqli_num_rows($patient_query) > 0) {
    $patient = mysqli_fetch_assoc($patient_query);
} else {
    die("Patient profile not found.");
}

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
    $patient_data = mysqli_fetch_assoc($p);
    $patient_id = $patient_data['patient_id'];

    $service_query = mysqli_query($conn, "SELECT duration_minutes FROM services WHERE service_id = '$service_id'");
    $service_data = mysqli_fetch_assoc($service_query);
    $duration = !empty($service_data['duration_minutes']) ? (int)$service_data['duration_minutes'] : 60;

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
    overflow: hidden;
}

.panel {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 18px;
    padding: 16px 18px;
    box-sizing: border-box;
    min-height: 0;
    overflow: hidden;
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
    <div class="booking-page" style="height: calc(100vh - 92px);">

        <div class="panel left-panel">
            <h3>Appointment Details</h3>

            <form method="POST" class="booking-form">
                <div>
                    <label>Select Service</label>
                    <select name="service_id" id="service" onchange="updateSummary(); loadTimeSlots();" required>
                        <option value="" data-duration="60">Choose service</option>
                        <?php while($s = mysqli_fetch_assoc($services)) { ?>
                            <option value="<?php echo $s['service_id']; ?>" data-duration="<?php echo $s['duration_minutes']; ?>">
                                <?php echo htmlspecialchars($s['service_name']); ?> - ₱<?php echo number_format($s['price']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label>Select Dentist</label>
                    <select name="dentist_id" id="dentist" onchange="updateSummary()" required>
                        <option value="">Choose dentist</option>
                        <?php while($d = mysqli_fetch_assoc($dentists)) { ?>
                            <option value="<?php echo $d['dentist_id']; ?>">
                                Dr. <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

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

                <button class="btn" name="book">Submit Appointment Request</button>
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
        </div>

    </div>
</div>

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

let current = new Date();

function renderCalendar() {
    let cal = document.getElementById("calendar");
    cal.innerHTML = "";

    let y = current.getFullYear();
    let m = current.getMonth();

    document.getElementById("monthYear").innerText = current.toLocaleString("default", { month:"long", year:"numeric" });

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
    let duration = 60;

    if (sSelect.selectedIndex > 0) {
        duration = parseInt(sSelect.options[sSelect.selectedIndex].getAttribute("data-duration")) || 60;
    }

    let endHour = (duration >= 120) ? 15 : 16;

    for (let h = 9; h <= endHour; h++) {
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
    current.setMonth(current.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    current.setMonth(current.getMonth() + 1);
    renderCalendar();
}

renderCalendar();
</script>