<?php
include("../config/database.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // Basic validation
    if ($password !== $confirm) {
        $message = "Passwords do not match!";
    } else {
        // Check if email already exists
        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        
        if (mysqli_num_rows($check) > 0) {
            $message = "Email already exists!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $query = "INSERT INTO users (email, password_hash, role)
                      VALUES ('$email', '$hashed', 'patient')";

            if (mysqli_query($conn, $query)) {
                $message = "Registered successfully! You can now login.";
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
        }

        .register-container {
            width: 100%;
            max-width: 420px;
            background: #fff;
            padding: 36px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand h1 {
            color: #0f3d56;
        }

        .brand p {
            font-size: 14px;
            color: #666;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
        }

        input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            outline: none;
        }

        input:focus {
            border-color: #1496b8;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #1496b8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn:hover {
            background: #0f84a3;
        }

        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            background: #ffe9e9;
            color: #b30000;
            font-size: 14px;
        }

        .success {
            background: #e6ffed;
            color: #0a7d2c;
        }

        .link {
            margin-top: 12px;
            text-align: center;
            font-size: 14px;
        }

        .link a {
            color: #1496b8;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="brand">
        <h1>Floss & Gloss</h1>
        <p>Create your account</p>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message <?php echo (strpos($message, 'success') !== false) ? 'success' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn">Register</button>

        <div class="link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </form>
</div>

</body>
</html>