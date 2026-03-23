<?php
session_start();
include("../config/database.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dental Services</title>

    <style>
        body {
            font-family: Arial;
            background: #c7e0df;
        }

        h2, p, a {
            text-align: center;
        }

        .container {
            width: 90%;
            margin: auto;
        }

        .services {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .btn {
            margin-top: 15px;
            align: center;
            display: block;
            width: 100%;
            padding: 10px;
            background: #1de0d0;
            color: white;
            border: none;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
        }

        .btn:hover {
            background: #0f766e;
        }
    </style>
</head>

<body>

<div class="container">
    <h2>Dental Services</h2>
    <p>Browse our comprehensive dental care services</p>
    <div style="text-align:center;">
        <a href="patient-dashboard.php">Go Back to Dashboard</a> 
    </div>

    <div class="services">

        <div class="card">
            <h3>Teeth Cleaning</h3>
            <p>Professional dental cleaning to remove plaque and tartar buildup</p>
            <p><b>Estimated Price:</b> ₱3,500</p>
            <p><b>Duration:</b> 30 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>

        <div class="card">
            <h3>Tooth Extraction</h3>
            <p>Safe and painless tooth removal procedure</p>
            <p><b>Estimated Price:</b> ₱5,000</p>
            <p><b>Duration:</b> 45 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>

        <div class="card">
            <h3>Root Canal</h3>
            <p>Treatment to save infected or damaged tooth</p>
            <p><b>Estimated Price:</b> ₱12,000</p>
            <p><b>Duration:</b> 90 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>

        <div class="card">
            <h3>Braces Consultation</h3>
            <p>Initial consultation for orthodontic treatment</p>
            <p><b>Estimated Price:</b> ₱2,000</p>
            <p><b>Duration:</b> 30 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>

        <div class="card">
            <h3>Teeth Whitening</h3>
            <p>Professional teeth whitening for a brighter smile</p>
            <p><b>Estimated Price:</b> ₱8,000</p>
            <p><b>Duration:</b> 60 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>

        <div class="card">
            <h3>Dental Filling</h3>
            <p>Cavity treatment with composite resin filling</p>
            <p><b>Estimated Price:</b> ₱4,000</p>
            <p><b>Duration:</b> 45 mins</p>
            <a href="#" class="btn">Book Appointment</a>
        </div>
    </div>
</div>
</body>
</html>