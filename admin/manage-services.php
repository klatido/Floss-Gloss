<form method="POST" action="../actions/service-actions.php">
    <input type="text" name="service_name" placeholder="Service Name" required>
    <input type="number" name="price" placeholder="Price" required>
    <button type="submit" name="add">Add Service</button>
</form>

<?php
include("../config/database.php");

$result = mysqli_query($conn, "SELECT * FROM services");
?>

<table border="1">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Price</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($result)){ ?>
<tr>
    <td><?php echo $row['service_id']; ?></td>
    <td><?php echo $row['service_name']; ?></td>
    <td><?php echo $row['price']; ?></td>
    <td>
        <a href="../actions/service-actions.php?delete=<?php echo $row['service_id']; ?>">Delete</a>
    </td>
</tr>
<?php } ?>
</table>