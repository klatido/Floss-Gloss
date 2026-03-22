<?php
include("../config/database.php");
?>

<h2>Medical Records</h2>

<form method="POST">
    <input type="number" name="appointment_id" placeholder="Appointment ID" required>
    <textarea name="notes" placeholder="Medical Notes"></textarea>
    <button name="save">Save</button>
</form>

<?php
if(isset($_POST['save'])){
    $id = $_POST['appointment_id'];
    $notes = $_POST['notes'];

    mysqli_query($conn, "INSERT INTO medical_records (appointment_id, notes) VALUES ('$id','$notes')");
}

// DISPLAY
$result = mysqli_query($conn, "SELECT * FROM medical_records");

while($row = mysqli_fetch_assoc($result)){
    echo "Appointment: " . $row['appointment_id'] . "<br>";
    echo "Notes: " . $row['notes'] . "<br><br>";
}
?>