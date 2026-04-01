<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unauthorized</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .unauthorized-box {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .unauthorized-box h1 {
            margin: 0 0 12px;
            color: #0f172a;
        }

        .unauthorized-box p {
            margin: 0 0 22px;
            color: #64748b;
            line-height: 1.6;
        }

        .back-btn {
            display: inline-block;
            text-decoration: none;
            background: #0ea5a0;
            color: #fff;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="unauthorized-box">
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page.</p>
        <a href="../auth/admin-login.php" class="back-btn">Back to Login</a>
    </div>
</body>
</html>