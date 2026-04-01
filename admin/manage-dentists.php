<?php
session_start();
include("../config/database.php");

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff']);

if($_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Dentists</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="logo">🦷 Admin Panel</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage-dentists.php" class="active">Dentists</a>
        <a href="manage-appointments.php">Appointments</a>
        <a href="manage-users.php">Users</a>
        <a href="services.php">Services</a>
    </div>
</div>

<div class="container-main">

<h1>Manage Dentists</h1>
<a href="add_dentist.php" class="add-btn">+ Add Dentist</a>

<div class="services-grid">

<?php
$result = mysqli_query($conn, "SELECT * FROM dentist_profiles");

while($row = mysqli_fetch_assoc($result)){
?>

<div class="service-card">
    <h3><?php echo $row['name']; ?></h3>
    <p><?php echo $row['specialization']; ?></p>

    <div class="admin-actions">
        <a href="edit_dentist.php?id=<?php echo $row['dentist_id']; ?>" class="edit-btn">Edit</a>
        <a href="delete_dentist.php?id=<?php echo $row['dentist_id']; ?>" class="delete-btn">Delete</a>
    </div>
</div>

<?php } ?>

</div>
</div>

</body>
</html>