<?php
if (!isset($page_title)) {
    $page_title = "Admin Panel | Floss & Gloss Dental";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #f5f7fb;
            color: #0f172a;
        }

        a {
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 320px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #dbe2ea;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-top {
            padding: 20px 14px 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 2px 8px 18px;
            border-bottom: 1px solid #dbe2ea;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0ea5a0, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
        }

        .brand-text h2 {
            margin: 0;
            font-size: 18px;
        }

        .brand-text p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .sidebar-nav {
            padding: 14px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 6px 0;
            padding: 14px 16px;
            color: #111827;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 600;
        }

        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background: #f1f5f9;
        }

        .sidebar-bottom {
            padding: 14px;
            border-top: 1px solid #dbe2ea;
            background: #fff;
        }

        .sidebar-bottom a {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 6px 0;
            padding: 14px 16px;
            color: #111827;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 600;
        }

        .sidebar-bottom a:hover {
            background: #f1f5f9;
        }

        .main {
            margin-left: 320px;
            min-height: 100vh;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 900;
            background: #ffffff;
            border-bottom: 1px solid #dbe2ea;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            margin: 0;
            font-size: 24px;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-meta {
            text-align: right;
        }

        .admin-meta strong {
            display: block;
            font-size: 16px;
        }

        .admin-meta span {
            color: #64748b;
            font-size: 14px;
        }

        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #d1fae5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .content {
            padding: 24px 30px 40px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #dde3ea;
            border-radius: 22px;
            padding: 28px 30px;
        }

        .site-footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                position: static;
            }
        }
    </style>
</head>
<body>