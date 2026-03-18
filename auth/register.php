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
            max-width: 480px;
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .logo-box {
            width: 84px;
            height: 84px;
            margin: 0 auto 20px;
            border-radius: 20px;
            background: linear-gradient(135deg, #00b7c6, #1769ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 38px;
        }

        h1 {
            text-align: center;
            margin: 0 0 10px;
            font-size: 28px;
            color: #111;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 28px;
        }

        .message {
            background: #ffe8e8;
            color: #b00020;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 15px;
            color: #111;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: #f2f4f7;
            font-size: 16px;
            margin-bottom: 22px;
            outline: none;
        }

        input:focus {
            box-shadow: 0 0 0 2px #18a8b5;
        }

        .forgot {
            display: inline-block;
            margin-bottom: 20px;
            color: #0891b2;
            text-decoration: none;
            font-size: 15px;
        }

        .btn {
            width: 100%;
            border: none;
            background: #0ea5a0;
            color: white;
            padding: 15px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b8f8a;
        }

        .bottom-text {
            text-align: center;
            margin-top: 26px;
            color: #374151;
            font-size: 15px;
        }

        .bottom-text a,
        .admin-link {
            color: #0891b2;
            font-weight: 700;
            text-decoration: none;
        }

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 22px 0;
        }

        .admin-link-wrap {
            text-align: center;
        }

        .admin-link {
            font-size: 15px;
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