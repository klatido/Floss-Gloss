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
| Get logged-in patient profile
|--------------------------------------------------------------------------
*/
$patient = null;

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
} else {
    die("Patient profile not found.");
}

/*
|--------------------------------------------------------------------------
| Fetch services (NO category column)
|--------------------------------------------------------------------------
*/
$services = [];

$services_sql = "SELECT service_id, service_name, description, duration_minutes, price
                 FROM services
                 WHERE is_active = 1
                 ORDER BY service_name ASC";

$services_stmt = mysqli_prepare($conn, $services_sql);
mysqli_stmt_execute($services_stmt);
$services_result = mysqli_stmt_get_result($services_stmt);

if ($services_result) {
    while ($row = mysqli_fetch_assoc($services_result)) {
        $services[] = $row;
    }
}

$page_title = "Dental Services | Floss & Gloss Dental";
include("../includes/patient-header.php");
include("../includes/patient-navbar.php");
?>

<style>
.services-title h1 {
    margin: 0 0 10px;
    font-size: 36px;
    color: #0b2454;
}

.services-title p {
    margin: 0;
    color: #52637a;
    font-size: 18px;
}

.services-grid {
    margin-top: 30px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
}

.service-card {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 22px;
    padding: 28px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.card-header h3 {
    margin: 0;
    font-size: 20px;
}

.badge {
    border: 1px solid #d1d5db;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    background: #f9fafb;
}

.desc {
    color: #667085;
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.book-btn {
    display: block;
    text-align: center;
    background: #0ea5a0;
    color: #fff;
    padding: 14px;
    border-radius: 12px;
    font-weight: bold;
    margin-top: 15px;
}

.book-btn:hover {
    background: #0b8f8a;
}

@media (max-width: 1000px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page">

    <div class="services-title">
        <h1>Dental Services</h1>
        <p>Browse our comprehensive dental care services</p>
    </div>

    <div class="services-grid">

        <?php if (count($services) > 0): ?>
            <?php foreach ($services as $row): ?>

                <div class="service-card">

                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($row['service_name']); ?></h3>
                        <span class="badge">Dental Service</span>
                    </div>

                    <div class="desc">
                        <?php echo htmlspecialchars($row['description'] ?? 'No description available.'); ?>
                    </div>

                    <div class="detail-row">
                        <span>Estimated Price</span>
                        <strong>₱<?php echo number_format($row['price'], 0); ?></strong>
                    </div>

                    <div class="detail-row">
                        <span>Duration</span>
                        <strong><?php echo $row['duration_minutes']; ?> mins</strong>
                    </div>

                    <a href="book-appointment.php?service_id=<?php echo $row['service_id']; ?>" class="book-btn">
                        Book Appointment
                    </a>

                </div>

            <?php endforeach; ?>
        <?php else: ?>
            <p>No services available.</p>
        <?php endif; ?>

    </div>

</div>

<?php include("../includes/patient-footer.php"); ?>