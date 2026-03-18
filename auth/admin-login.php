<?php
session_start();
include("../config/database.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $query = "SELECT * FROM users 
              WHERE email = '$email' 
              AND role IN ('staff', 'system_admin') 
              AND account_status = 'active'";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["email"] = $user["email"];

            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "Admin account not found or inactive.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Floss & Gloss Dental</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #05264d, #0b6b67, #1b4fa3);
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
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
            font-size: 36px;
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

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 30px 0 20px;
        }

        .back-link-wrap {
            text-align: center;
        }

        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-box">🛡️</div>
        <h1>Admin Portal</h1>
        <div class="subtitle">Floss &amp; Gloss Dental Management</div>

        <?php if (!empty($message)) : ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Admin Email</label>
            <input type="email" name="email" id="email" placeholder="admin@flossgloss.com" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>

            <button type="submit" class="btn">Sign In as Admin</button>
        </form>

        <hr>

        <div class="back-link-wrap">
            <a href="patient-login.php" class="back-link">← Back to Patient Login</a>
        </div>
    </div>
</body>
</html>