<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../config/database.php");

// VERIFY PAYMENT
if(isset($_GET['verify'])){
    $id = $_GET['verify'];
    mysqli_query($conn, "UPDATE payments SET status='verified' WHERE payment_id=$id");
    header("Location: billing.php");
    exit();
}

// INSERT PAYMENT
if(isset($_POST['pay'])){
    $appointment_id = $_POST['appointment_id'];
    $amount = $_POST['amount'];

    if(empty($appointment_id) || empty($amount)){
        echo "<p style='color:red;'>All fields are required!</p>";
    } else {
        mysqli_query($conn, "INSERT INTO payments (appointment_id, amount, status) VALUES ('$appointment_id','$amount','pending')");
        header("Location: billing.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Billing</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<h2>Billing Management</h2>

<!-- FORM -->
<form method="POST" action="">
    <label>Appointment ID</label>
    <input type="number" name="appointment_id" placeholder="Enter Appointment ID" required>

    <label>Amount (₱)</label>
    <input type="number" name="amount" placeholder="Enter Amount" required>

    <button type="submit" name="pay">Add Payment</button>
</form>

<h3>Payment List</h3>

<table>
<tr>
    <th>ID</th>
    <th>Appointment ID</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php
$result = mysqli_query($conn, "SELECT * FROM payments");

while($row = mysqli_fetch_assoc($result)){
?>
<tr>
    <td><?php echo $row['payment_id']; ?></td>
    <td><?php echo $row['appointment_id']; ?></td>
    <td><?php echo "₱" . number_format($row['amount'], 2); ?></td>
    <td><?php echo $row['status']; ?></td>
    <td>
        <?php if($row['status'] == 'pending'){ ?>
            <a href="?verify=<?php echo $row['payment_id']; ?>">Verify</a>
        <?php } else { ?>
            ✔ Verified
        <?php } ?>
    </td>
</tr>
<?php } ?>

</table>

</body>
</html>