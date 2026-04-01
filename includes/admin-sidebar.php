<?php
require_once '../includes/auth.php';

$current_page = basename($_SERVER['PHP_SELF']);
$role = currentRole();
?>

<style>
    .admin-layout {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }

    .sidebar {
        width: 240px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: #ffffff;
        border-right: 1px solid #dbe2ea;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        z-index: 1000;
    }

    .sidebar-top {
        display: flex;
        flex-direction: column;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 72px;
        padding: 0 18px;
        border-bottom: 1px solid #dbe2ea;
    }

    .brand-logo {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 16px;
    }

    .brand-text h2 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
    }

    .brand-text p {
        margin: 2px 0 0;
        font-size: 11px;
        color: #64748b;
    }

    .sidebar-nav {
        padding: 10px 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .sidebar-nav a,
    .sidebar-bottom a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 13px;
        border-radius: 10px;
        color: #111827;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .sidebar-nav a:hover,
    .sidebar-bottom a:hover {
        background: #f1f5f9;
    }

    .sidebar-nav a.active {
        background: #eceff3;
    }

    .nav-icon {
        width: 20px;
        text-align: center;
        font-size: 15px;
    }

    .sidebar-bottom {
        padding: 8px;
        border-top: 1px solid #dbe2ea;
        background: #fff;
    }

    .main-area {
        flex: 1;
        min-width: 0;
        margin-left: 240px;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    @media (max-width: 820px) {
        .sidebar {
            width: 200px;
        }

        .main-area {
            margin-left: 200px;
        }
    }
</style>

<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-brand">
                <div class="brand-logo">✚</div>
                <div class="brand-text">
                    <h2>F&amp;G Admin</h2>
                    <p>Management</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <?php if ($role !== 'dentist'): ?>
                <a href="admin-dashboard.php" class="<?php echo ($current_page === 'admin-dashboard.php') ? 'active' : ''; ?>">
                    <span class="nav-icon">◫</span> Dashboard
                </a>
                <?php endif; ?>

                <?php if ($role === 'system_admin' || $role === 'staff'): ?>
                    <a href="manage-appointments.php" class="<?php echo ($current_page === 'manage-appointments.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🗓</span> Appointments
                    </a>

                    <a href="manage-services.php" class="<?php echo ($current_page === 'manage-services.php' || $current_page === 'edit-service.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">📋</span> Services
                    </a>

                    <a href="manage-schedules.php" class="<?php echo ($current_page === 'manage-schedules.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">◔</span> Schedules
                    </a>

                    <a href="manage-patients.php" class="<?php echo ($current_page === 'manage-patients.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">☺</span> Patients
                    </a>

                    <a href="medical-records.php" class="<?php echo ($current_page === 'medical-records.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🗎</span> Medical Records
                    </a>

                    <a href="manage-billing.php" class="<?php echo ($current_page === 'manage-billing.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">▭</span> Billing
                    </a>

                    <a href="manage-users.php" class="<?php echo ($current_page === 'manage-users.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">👤</span> Users
                    </a>
                <?php endif; ?>

                <?php if ($role === 'dentist'): ?>
                    <a href="manage-schedules.php" class="<?php echo ($current_page === 'manage-schedules.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">◔</span> Schedules
                    </a>

                    <a href="medical-records.php" class="<?php echo ($current_page === 'medical-records.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🗎</span> Medical Records
                    </a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <a href="../auth/admin-logout.php">
                <span class="nav-icon">↪</span> Logout
            </a>
        </div>
    </aside>