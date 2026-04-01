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
    <title>Admin - Users</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="navbar">
    <div class="logo">🦷 Admin Panel</div>
</div>

<div class="container-main">

<h1>Users</h1>

<div class="services-grid">

<?php
$result = mysqli_query($conn, "SELECT * FROM users");

while($row = mysqli_fetch_assoc($result)){
?>

<div class="service-card">
    <h3><?php echo $row['name']; ?></h3>
    <p><?php echo $row['email']; ?></p>
    <strong><?php echo $row['role']; ?></strong>

    <div class="admin-actions">
        <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" class="delete-btn">Delete</a>
    </div>
</div>

<?php } ?>

</div>
</div>

</body>
</html>