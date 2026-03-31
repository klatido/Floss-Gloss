<?php
include("../config/database.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dental Services</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="logo">🦷 Floss & Gloss</div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="services.php" class="active">Services</a>
        <a href="#">Profile</a>
        <a href="#">Settings</a>
    </div>

    <div class="user">
        John Smith | <a href="../auth/logout.php">Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="container-main">

    <h1>Dental Services</h1>
    <p class="subtitle">Browse our comprehensive dental care services</p>

    <!-- SEARCH (optional) -->
    <input type="text" placeholder="🔍 Search services..." class="search-bar">

    <!-- GRID -->
    <div class="services-grid">

        <?php
        $result = mysqli_query($conn, "SELECT * FROM services");

        while($row = mysqli_fetch_assoc($result)){
        ?>
            <div class="service-card">

                <div class="card-header">
                    <h3><?php echo $row['service_name']; ?></h3>
                    <span class="badge">Dental</span>
                </div>

                <p class="desc">Professional dental care service</p>

                <div class="price-row">
                    <span>Estimated Price</span>
                    <strong>₱<?php echo number_format($row['price'],2); ?></strong>
                </div>

                <div class="duration">
                    ⏱ Duration: 30 mins
                </div>

                <a href="#" class="book-btn">Book Appointment</a>

            </div>
        <?php } ?>

    </div>

</div>

</body>
</html>