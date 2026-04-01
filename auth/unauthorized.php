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
        body{
            margin:0;
            font-family:Arial, sans-serif;
            background:#f8fafc;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
        }
        .box{
            background:#fff;
            border:1px solid #dbe2ea;
            border-radius:16px;
            padding:32px;
            max-width:480px;
            width:100%;
            text-align:center;
            box-shadow:0 8px 24px rgba(0,0,0,0.06);
        }
        .box h1{
            margin:0 0 10px;
            font-size:28px;
            color:#0f172a;
        }
        .box p{
            margin:0 0 20px;
            color:#64748b;
            line-height:1.6;
        }
        .btn{
            display:inline-block;
            padding:12px 18px;
            border-radius:10px;
            background:#0ea5a0;
            color:#fff;
            text-decoration:none;
            font-weight:700;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page.</p>
        <a href="../auth/admin-login.php" class="btn">Back to Login</a>
    </div>
</body>
</html>