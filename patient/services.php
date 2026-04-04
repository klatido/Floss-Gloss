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
| Fetch services
|--------------------------------------------------------------------------
*/
$services = [];

$services_sql = "SELECT service_id, service_name, description, image_path, duration_minutes, price
                 FROM services
                 WHERE is_active = 1
                 ORDER BY service_id ASC"; // Ordered by ID to match your insertion order

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
/* CENTER WHOLE PAGE */
.page {
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* TITLE */
.services-title {
    width: 100%;
    max-width: 1200px;
}

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

/* GRID FIX (MAIN CHANGE 🔥) */
.services-grid {
    margin-top: 30px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
    width: 100%;
    max-width: 1200px;
}

/* 🔥 MAGIC CSS TO CENTER THE 10th CARD ON THE BOTTOM ROW 🔥 */
.service-card:nth-child(3n + 1):last-child {
    grid-column: 2; /* Forces the orphaned item into the middle column */
}

/* CARD */
.service-card {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 22px;
    padding: 28px;
    transition: 0.2s;
    display: flex; /* Added flex to push button to the bottom if descriptions vary in length */
    flex-direction: column;
}

.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.05); /* Added slight shadow on hover for a premium feel */
}

/* HEADER */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.card-header h3 {
    margin: 0;
    font-size: 20px;
    color: #0b2454;
}

/* BADGE */
.badge {
    border: 1px solid #d1d5db;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    background: #f9fafb;
    white-space: nowrap;
}

/* DESCRIPTION */
.desc {
    color: #667085;
    margin-bottom: 20px;
    flex-grow: 1; /* Pushes the price/duration details down */
    line-height: 1.5;
}

/* DETAILS */
.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 15px;
}

.detail-row span { color: #64748b; }
.detail-row strong { color: #0f172a; }

/* BUTTON */
.book-btn {
    display: block;
    text-align: center;
    background: #0ea5a0;
    color: #fff;
    padding: 14px;
    border-radius: 12px;
    font-weight: bold;
    margin-top: 15px;
    text-decoration: none;
    transition: 0.2s;
}

.book-btn:hover {
    background: #0b8f8a;
}

.service-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 16px;
    margin-bottom: 18px;
    border: 1px solid #dde3ea;
    background: #f8fafc;
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    /* Reset the magic centering trick on mobile so it doesn't break the single column */
    .service-card:nth-child(3n + 1):last-child {
        grid-column: 1; 
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

                    <?php if (!empty($row['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Service Image" class="service-image">
                    <?php endif; ?>

                    <div class="desc">
                        <?php echo htmlspecialchars($row['description'] ?? 'No description available.'); ?>
                    </div>

                    <div class="detail-row">
                        <span>Estimated Price</span>
                        <strong>₱<?php echo number_format($row['price'], 0); ?></strong>
                    </div>

                    <div class="detail-row">
                        <span>Duration</span>
                        <?php 
                            // Quick PHP trick to display "X hours" instead of "180 mins" if it's a long surgery
                            $mins = $row['duration_minutes'];
                            if ($mins >= 60 && $mins % 60 == 0) {
                                $hrs = $mins / 60;
                                $duration_text = $hrs . ($hrs == 1 ? " hour" : " hours");
                            } else {
                                $duration_text = $mins . " mins";
                            }
                        ?>
                        <strong><?php echo $duration_text; ?></strong>
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
