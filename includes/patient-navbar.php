<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: patient-login.php");
    exit();
}

$nav_full_name = trim(($patient['first_name'] ?? 'Patient') . ' ' . ($patient['last_name'] ?? ''));
if ($nav_full_name === '') {
    $nav_full_name = 'Patient';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="navbar">
    <div class="brand">
        <div class="brand-logo">🩺</div>
        <div class="brand-text">
            <h2>Floss &amp; Gloss</h2>
            <p>Dental Care</p>
        </div>
    </div>

    <div class="nav-links">
        <a href="patient-dashboard.php" class="<?php echo ($current_page === 'patient-dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
        <a href="services.php" class="<?php echo ($current_page === 'services.php') ? 'active' : ''; ?>">Services</a>
        <a href="profile.php" class="<?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">Profile</a>
        <a href="settings.php" class="<?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>">Settings</a>
    </div>

    <div class="user-box">
        <div class="user-meta">
            <strong><?php echo htmlspecialchars($nav_full_name); ?></strong>
            <span>Patient</span>
        </div>
        <a href="../auth/patient-logout.php" class="logout-btn">Logout</a>
    </div>
</div>