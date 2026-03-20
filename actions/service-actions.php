<?php
include("../config/database.php");

if(isset($_POST['add'])){
    $name = $_POST['service_name'];
    $price = $_POST['price'];

    if(empty($name) || empty($price)){
        echo "All fields required!";
    } else {
        mysqli_query($conn, "INSERT INTO services (service_name, price) VALUES ('$name','$price')");
        header("Location: ../admin/manage-services.php");
    }
}
?>