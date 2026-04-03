<?php
session_start();
include("../config/database.php");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "User not logged in.";
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
     $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Get current password from DB
    $query = "SELECT password_hash FROM users WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    // Check old password
    if (!password_verify($old_password, $user['password_hash'])) {
        $message = "Old password is incorrect!";
    }
    // Check if new passwords match
    elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match!";
    }
    else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $update = "UPDATE users SET password_hash='$hashed' WHERE user_id='$user_id'";
        if (mysqli_query($conn, $update)) {
            $message = "Password updated successfully!";
        } else {
            $message = "Error updating password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Settings</title>
    <style>
        body { font-family: Arial; background:#f5f7fa; }
        .container { width: 90%; margin:auto; }
        .card {
            background:white;
            padding:20px;
            border-radius:10px;
            margin-top:20px;
        }
        input {
            padding:10px;
            width:100%;
            margin-top:10px;
        }
        button {
            margin-top:10px;
            padding:10px;
            background:#0d9488;
            color:white;
            border:none;
            border-radius:6px;
        }
        a {
            text-align: center;
        }
        p, h2 {
            text-align: center;
        }

        h2, {
            text-align: center;
            color: #000000;
            font-family: arial;
        }
        .btn-back {
            display: inline-block;
            padding: 10px 15px;
            background: #0d9488;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .btn-back:hover {
            background: #0f766e;
            align: center;
        }
    </style>
</head>

<body>
<div class="container">
    <?php
        $page_title = "Patient Dashboard | Floss & Gloss Dental";
        include("../includes/patient-header.php");
        include("../includes/patient-navbar.php");
    ?>
    <h2>Settings</h2>
    <p>Manage your settings and preferences</p>

    <div class="card">
        <h3>Change Password</h3>

        <?php if ($message) echo "<p>$message</p>"; ?>

        <form method="POST">
            <input type="password" name="old_password" placeholder="Old Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit">Update Password</button>
        </form>
    </div>
</div>
<a href = "patient-dashboard.php" class = "btn-back">Go Back To Dashboard</a>
</body>
</html>