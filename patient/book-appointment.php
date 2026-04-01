<?php
session_start();
include("../config/database.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['service_id'])) {
    die("No service selected.");
}

$service_id = $_GET['service_id'];
$patient_id = 1;

// Get service
$result = mysqli_query($conn, "SELECT * FROM services WHERE service_id = '$service_id'");
$service = mysqli_fetch_assoc($result);

if (!$service) {
    die("Invalid service.");
}

// Handle booking
if (isset($_POST['book'])) {
    $date = $_POST['appointment_date'];

    $insert = mysqli_query($conn, "INSERT INTO appointments 
        (patient_id, service_id, appointment_date, status)
        VALUES ('$patient_id', '$service_id', '$date', 'pending')");

    if ($insert) {
        header("Location: services.php?success=1");
        exit();
    } else {
        echo mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Appointment</title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: #f5f7fb;
        }

        /* TOPBAR (same style) */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #dbe2ea;
            padding: 15px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            font-size: 18px;
            margin: 0;
        }

        /* CONTENT */
        .container {
            padding: 30px;
        }

        .panel {
            max-width: 500px;
            margin: auto;
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 16px;
            padding: 25px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            color: #0b2454;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 13px;
            color: #52637a;
            margin-bottom: 20px;
        }

        .service-box {
            background: #f8fafc;
            border: 1px solid #dde3ea;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .service-name {
            font-weight: 700;
            font-size: 15px;
        }

        .service-meta {
            font-size: 13px;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #dbe2ea;
            margin-top: 6px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #0ea5a0;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #0c8f8a;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0ea5a0;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="topbar">
    <h1>Book Appointment</h1>
</div>

<div class="container">

    <div class="panel">

        <a href="services.php" class="back">← Back to Services</a>

        <div class="title">Confirm Appointment</div>
        <div class="subtitle">Fill in the details below</div>

        <div class="service-box">
            <div class="service-name"><?php echo $service['service_name']; ?></div>
            <div class="service-meta">
                ₱<?php echo number_format($service['price']); ?> • 
                <?php echo $service['duration_minutes']; ?> mins
            </div>
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Select Date & Time</label>
                <input type="datetime-local" name="appointment_date" required>
            </div>

            <button type="submit" name="book" class="btn">
                Confirm Booking
            </button>

        </form>

    </div>

</div>

</body>
</html>