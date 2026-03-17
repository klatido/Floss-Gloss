<?php
session_start();
include("../config/database.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$email' AND account_status='active'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            $message = "Login successful!";

            // Temporary redirect targets
            if ($user['role'] === 'patient') {
                header("Location: ../patient/");
                exit();
            } elseif ($user['role'] === 'staff' || $user['role'] === 'system_admin') {
                header("Location: ../admin/");
                exit();
            } elseif ($user['role'] === 'dentist') {
                header("Location: ../dentist/");
                exit();
            }
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "No active account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Floss & Gloss Dental</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e6f7ff, #f5fcff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 36px 32px;
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand h1 {
            font-size: 30px;
            color: #0f3d56;
            margin-bottom: 8px;
        }

        .brand p {
            color: #5f6b76;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #284657;
            font-size: 14px;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cfdce5;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #1fa3c9;
            box-shadow: 0 0 0 3px rgba(31, 163, 201, 0.12);
        }

        .btn-login {
            width: 100%;
            border: none;
            background: #1496b8;
            color: #fff;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-login:hover {
            background: #0f84a3;
        }

        .helper-links {
            margin-top: 18px;
            text-align: center;
            font-size: 14px;
        }

        .helper-links a {
            color: #1496b8;
            text-decoration: none;
            font-weight: 600;
        }

        .helper-links a:hover {
            text-decoration: underline;
        }

        .message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            background: #fff4e5;
            color: #8a5a00;
            border: 1px solid #ffd9a3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand">
            <h1>Floss &amp; Gloss</h1>
            <p>Dental Appointment and Clinic Management System</p>
        </div>

        <?php if (!empty($message)) : ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>

            <div class="helper-links">
                <p><a href="#">Forgot Password?</a></p>
                <p style="margin-top: 8px;">No account yet? <a href="register.php">Register here</a></p>
            </div>
        </form>
    </div>
</body>
</html>