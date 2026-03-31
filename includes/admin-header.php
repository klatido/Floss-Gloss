<?php
if (!isset($page_title)) {
    $page_title = "Admin Panel | Floss & Gloss Dental";
}

if (!isset($admin_name)) {
    $admin_name = "Admin Staff";
}

if (!isset($admin_role)) {
    $admin_role = "Administrator";
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

        body {
            margin: 0;
            background: #f5f7fb;
            color: #0f172a;
        }

        a {
            text-decoration: none;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 320px;
            background: #ffffff;
            border-right: 1px solid #dbe2ea;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #dde3ea;
            border-radius: 22px;
            padding: 30px;
            min-height: 170px;
        }

        .stat-card h3 {
            margin: 0 0 28px;
            font-size: 16px;
            color: #3f5b7a;
            font-weight: 700;
            line-height: 1.4;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
            color: #0b2454;
            margin-bottom: 8px;
        }

        .stat-note {
            color: #64748b;
            font-size: 14px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 30px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #dde3ea;
            border-radius: 22px;
            padding: 28px 30px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .panel-header p {
            margin: 6px 0 0;
            color: #667085;
            font-size: 16px;
        }

        .small-btn {
            display: inline-block;
            padding: 12px 18px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-weight: 700;
            color: #111827;
            background: #fff;
        }

        .request-card {
            border: 1px solid #dbe2ea;
            border-radius: 16px;
            padding: 18px 16px;
            margin-bottom: 14px;
        }

        .request-card:last-child {
            margin-bottom: 0;
        }

        .request-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 10px;
        }

        .request-top strong {
            font-size: 16px;
        }

        .request-service {
            color: #344054;
            margin: 4px 0;
            font-size: 15px;
        }

        .request-meta {
            color: #60708a;
            font-size: 14px;
            line-height: 1.6;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge.pending {
            background: #f3f4f6;
            color: #111827;
        }

        .status-badge.success {
            background: #ecfdf3;
            color: #027a48;
        }

        .status-badge.danger {
            background: #fef3f2;
            color: #b42318;
        }

        .status-badge.neutral {
            background: #f2f4f7;
            color: #344054;
        }

        .empty-state {
            text-align: center;
            color: #7b8ba1;
            padding: 60px 20px;
        }

        .empty-state .icon {
            font-size: 52px;
            margin-bottom: 8px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .action-card {
            border: 1px solid #dbe2ea;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            color: #111827;
            background: #fff;
            font-weight: 700;
            font-size: 16px;
        }

        .action-card .icon {
            display: block;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .notification-box {
            border: 1px solid #f7d66a;
            background: #fffbea;
            border-radius: 16px;
            padding: 18px;
            color: #a15c07;
        }

        .notification-box strong {
            display: block;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .notification-box span {
            color: #b26b13;
            font-size: 15px;
        }

        .site-footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 820px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .stats-grid,
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }
        }
    </style>
</head>
<body>
<div class="layout">