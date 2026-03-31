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

<div class="container-main">

    <h1>Dental Services</h1>
    <p class="subtitle">Browse our comprehensive dental care services</p>

    <input type="text" placeholder=" Search services..." class="search-bar">

    <div class="services-grid">

        <?php
        $result = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1");

        if(mysqli_num_rows($result) > 0){
            while($row = mysqli_fetch_assoc($result)){
        ?>
            <div class="service-card">

                <div class="card-header">
                    <h3><?php echo $row['service_name']; ?></h3>
                    <span class="badge">Dental</span>
                </div>

                <p class="desc">
                    <?php echo $row['description']; ?>
                </p>

                <div class="price-row">
                    <span>Estimated Price</span>
                    <strong>₱<?php echo number_format($row['price'],2); ?></strong>
                </div>

                <div class="duration">
                    ⏱ Duration: <?php echo $row['duration_minutes']; ?> mins
                </div>

                <a href="book.php?service_id=<?php echo $row['service_id']; ?>" class="book-btn">
                    Book Appointment
                </a>

            </div>
        <?php 
            }
        } else {
            echo "<p>No services available yet.</p>";
        }
        ?>

    </div>

</div>

</body>
</html>