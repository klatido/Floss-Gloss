<?php
include("../config/database.php");

// TEMP patient_id (change later)
$patient_id = 1;

$service_id = $_GET['service_id'];

// default date (need to improve)
$date = date("Y-m-d H:i:s");

// insert appointment
mysqli_query($conn, "INSERT INTO appointments (patient_id, service_id, appointment_date, status)
VALUES ('$patient_id', '$service_id', '$date', 'pending')");

// redirect back
header("Location: services.php?success=1");
exit();
?>