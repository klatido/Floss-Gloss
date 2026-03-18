<?php
include("../config/database.php");

$message = "";
$is_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $check_query = "SELECT * FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $message = "Email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_query = "INSERT INTO users (role, email, password_hash)
                             VALUES ('patient', '$email', '$hashed_password')";

            if (mysqli_query($conn, $insert_query)) {
                $message = "Registration successful. You can now log in.";
                $is_success = true;
            } else {
                $message = "Error: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Floss & Gloss Dental</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(135deg, #05264d, #0b6b67, #1b4fa3);
        }

        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.15);
        }

        .logo-box {
            width: 70px;
            height: 70px;
            margin: 0 auto 12px;
            border-radius: 18px;
            background: linear-gradient(135deg, #00b7c6, #1769ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }

        h1 {
            text-align: center;
            margin: 0 0 6px;
            font-size: 24px;
            color: #111;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .message {
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 13px;
            background: #ffe8e8;
            color: #b00020;
        }

        .message.success {
            background: #e7f8ec;
            color: #18794e;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: #111;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border: none;
            border-radius: 10px;
            background: #f2f4f7;
            font-size: 14px;
            margin-bottom: 14px;
            outline: none;
        }

        input:focus {
            box-shadow: 0 0 0 2px #18a8b5;
        }

        .btn {
            width: 100%;
            border: none;
            background: #0ea5a0;
            color: white;
            padding: 13px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b8f8a;
        }

        .bottom-text {
            text-align: center;
            margin-top: 14px;
            color: #374151;
            font-size: 13px;
        }

        .bottom-text a {
            color: #0891b2;
            font-weight: 600;
            text-decoration: none;
        }

        .bottom-text a:hover {
            text-decoration: underline;
        }

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 14px 0;
        }

        .back-link-wrap {
            text-align: center;
        }

        .back-link {
            font-size: 13px;
            color: #0891b2;
            font-weight: 600;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
</style>
</head>
<body>
    <div class="card">
        <div class="logo-box">🩺</div>
        <h1>Create Your Account</h1>
        <div class="subtitle">Register for your patient account</div>

        <?php if (!empty($message)) : ?>
            <div class="message <?php echo $is_success ? 'success' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="john.smith@email.com" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Create a password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>

            <button type="submit" class="btn">Register</button>
        </form>

        <div class="bottom-text">
            Already have an account? <a href="patient-login.php">Login here</a>
        </div>

        <hr>

        <div class="back-link-wrap">
            <a href="patient-login.php" class="back-link">← Back to Patient Login</a>
        </div>
    </div>
</body>
</html>