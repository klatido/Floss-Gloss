<?php
include("../config/database.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Services</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<h2>Dental Services</h2>

<!-- ADD SERVICE -->
<form method="POST" action="../actions/service-actions.php">
    <label>Service Name</label>
    <input type="text" name="service_name" placeholder="Enter Service Name" required>

    <label>Price (₱)</label>
    <input type="number" name="price" placeholder="Enter Price" required>

    <button type="submit" name="add">Add Service</button>
</form>

<hr>

<!-- SERVICE CARDS -->
<div class="container">

<?php
$result = mysqli_query($conn, "SELECT * FROM services");

while($row = mysqli_fetch_assoc($result)){
?>

<div class="card">
    <h3><?php echo $row['service_name']; ?></h3>

    <p class="price">
        ₱<?php echo number_format($row['price'], 2); ?>
    </p>

    <a href="../actions/service-actions.php?delete=<?php echo $row['service_id']; ?>" class="delete-btn">
        Delete
    </a>
</div>

<?php } ?>

</div>

</body>
</html>

