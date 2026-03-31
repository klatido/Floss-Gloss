<?php
if (!isset($page_title)) {
    $page_title = "Patient Page | Floss & Gloss Dental";
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
            background: #f4f6f8;
            color: #0f172a;
        }

        a {
            text-decoration: none;
        }

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #dbe2ea;
            padding: 14px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0ea5a0, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            font-weight: bold;
        }

        .brand-text h2 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }

        .brand-text p {
            margin: 2px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #111827;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            background: #f3f4f6;
        }

        .nav-links a.active {
            background: #eef2f7;
        }

        .user-box {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-meta {
            text-align: right;
        }

        .user-meta strong {
            display: block;
            font-size: 15px;
            color: #111827;
        }

        .user-meta span {
            color: #64748b;
            font-size: 14px;
        }

        .logout-btn {
            color: #111827;
            border: 1px solid #d1d5db;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            background: #fff;
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            background: #f9fafb;
        }

        .page {
            padding: 40px;
            min-height: calc(100vh - 140px);
        }

        .welcome h1 {
            margin: 0 0 10px;
            font-size: 36px;
            color: #0b2454;
        }

        .welcome p {
            margin: 0;
            color: #52637a;
            font-size: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 34px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 20px;
            padding: 28px 30px;
            min-height: 150px;
        }

        .stat-card h3 {
            margin: 0 0 38px;
            color: #40597a;
            font-size: 16px;
            font-weight: 700;
        }

        .stat-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #0b2454;
        }

        .section {
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 22px;
            padding: 30px;
            margin-top: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .section-header p {
            margin: 6px 0 0;
            color: #667085;
            font-size: 16px;
        }

        .btn-primary {
            background: #0ea5a0;
            color: white;
            padding: 14px 22px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #0b8f8a;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px 40px;
            color: #7b8ba1;
        }

        .empty-state .icon {
            font-size: 52px;
            margin-bottom: 12px;
        }

        .appointment-card {
            border: 1px solid #e1e7ef;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            background: #fff;
        }

        .appointment-card:last-child {
            margin-bottom: 0;
        }

        .appointment-info h4 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .appointment-info .dentist {
            color: #344054;
            margin-bottom: 4px;
        }

        .appointment-info .date {
            color: #667085;
            font-size: 15px;
        }

        .badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.success {
            background: #ecfdf3;
            color: #027a48;
        }

        .badge.pending {
            background: #fff7ed;
            color: #b54708;
        }

        .badge.danger {
            background: #fef3f2;
            color: #b42318;
        }

        .badge.neutral {
            background: #f2f4f7;
            color: #344054;
        }

        .payment-box {
            background: #edf9f8;
            border: 1px solid #9ddfdc;
            border-radius: 22px;
            padding: 28px 30px;
        }

        .payment-box h2 {
            margin-top: 0;
            color: #0b4f6c;
        }

        .payment-box p {
            color: #0a4b61;
            line-height: 1.7;
            margin: 8px 0;
        }

        .site-footer {
            background: #ffffff;
            border-top: 1px solid #dbe2ea;
            padding: 18px 40px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-box {
                width: 100%;
                justify-content: space-between;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 700px) {
            .page {
                padding: 20px;
            }

            .appointment-card {
                flex-direction: column;
            }

            .welcome h1 {
                font-size: 28px;
            }

            .navbar {
                padding: 16px 20px;
            }

            .site-footer {
                padding: 18px 20px;
            }
        }
    </style>
</head>
<body>