<?php
session_start();
include("../config/database.php");

if($_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Appointments</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="navbar">
    <div class="logo">🦷 Admin Panel</div>
</div>

<div class="container-main">

<h1>Appointments</h1>

<div class="services-grid">

<?php
$query = "
SELECT a.*, s.service_name, p.name AS patient_name
FROM appointments a
JOIN services s ON a.service_id = s.service_id
JOIN patient_profiles p ON a.patient_id = p.patient_id
";

$result = mysqli_query($conn, $query);

while($row = mysqli_fetch_assoc($result)){
?>

<div class="service-card">
    <h3><?php echo $row['patient_name']; ?></h3>
    <p><?php echo $row['service_name']; ?></p>
    <p><?php echo $row['appointment_date']; ?></p>
    <strong>Status: <?php echo $row['status']; ?></strong>

    <div class="admin-actions">
        <a href="update_status.php?id=<?php echo $row['appointment_id']; ?>&status=Approved" class="edit-btn">Approve</a>
        <a href="update_status.php?id=<?php echo $row['appointment_id']; ?>&status=Rejected" class="delete-btn">Reject</a>
    </div>
</div>

<?php } ?>

</div>
</div>

</body>
</html>