<form method="POST" action="../actions/service-actions.php">
    <input type="text" name="service_name" placeholder="Service Name" required>
    <input type="number" name="price" placeholder="Price" required>
    <button type="submit" name="add">Add Service</button>
</form>

<?php
include("../config/database.php");

$result = mysqli_query($conn, "SELECT * FROM services");

while($row = mysqli_fetch_assoc($result)){
    echo $row['service_name'] . " - " . $row['price'] . "<br>";
}
?>

