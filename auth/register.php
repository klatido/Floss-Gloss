<?php
include("../config/database.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $query = "INSERT INTO users (email, password_hash, role)
              VALUES ('$email', '$password', 'patient')";

    if (mysqli_query($conn, $query)) {
        echo "Registered successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>