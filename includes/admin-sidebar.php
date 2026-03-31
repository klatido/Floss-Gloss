<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="brand-logo">🩺</div>
            <div class="brand-text">
                <h2>F&amp;G Admin</h2>
                <p>Management</p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="admin-dashboard.php" class="<?php echo ($current_page === 'admin-dashboard.php') ? 'active' : ''; ?>">
                <span>◫</span> Dashboard
            </a>
            <a href="manage-appointments.php" class="<?php echo ($current_page === 'manage-appointments.php') ? 'active' : ''; ?>">
                <span>🗓</span> Appointments
            </a>
            <a href="manage-services.php" class="<?php echo ($current_page === 'manage-services.php' || $current_page === 'edit-service.php') ? 'active' : ''; ?>">
                <span>📋</span> Services
            </a>
            <a href="manage-schedules.php" class="<?php echo ($current_page === 'manage-schedules.php') ? 'active' : ''; ?>">
                <span>◔</span> Schedules
            </a>
            <a href="manage-patients.php" class="<?php echo ($current_page === 'manage-patients.php') ? 'active' : ''; ?>">
                <span>☺</span> Patients
            </a>
            <a href="medical-records.php" class="<?php echo ($current_page === 'medical-records.php') ? 'active' : ''; ?>">
                <span>🗎</span> Medical Records
            </a>
            <a href="billing.php" class="<?php echo ($current_page === 'billing.php') ? 'active' : ''; ?>">
                <span>▭</span> Billing
            </a>
        </nav>
    </div>

    <div class="sidebar-bottom">
        <a href="#"><span>☰</span> Collapse</a>
        <a href="../auth/logout.php"><span>↪</span> Logout</a>
    </div>
</aside>