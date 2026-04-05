<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/patient-login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$success_message = "";
$error_message = "";

/*
|--------------------------------------------------------------------------
| Get logged-in patient info
|--------------------------------------------------------------------------
*/
$query = "
    SELECT 
        u.email,
        u.password_hash,
        p.first_name,
        p.last_name
    FROM users u
    INNER JOIN patient_profiles p ON u.user_id = p.user_id
    WHERE u.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    die("Patient profile not found.");
}

/*
|--------------------------------------------------------------------------
| Handle password update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = trim($_POST['old_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($old_password === '' || $new_password === '' || $confirm_password === '') {
        $error_message = "Please complete all password fields.";
    } elseif (!password_verify($old_password, $patient['password_hash'])) {
        $error_message = "Old password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } elseif ($new_password === $old_password) {
        $error_message = "New password must be different from your old password.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update_sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $user_id);

        if ($update_stmt->execute()) {
            $success_message = "Password updated successfully.";
            $patient['password_hash'] = $hashed_password;
        } else {
            $error_message = "Error updating password. Please try again.";
        }
    }
}

$page_title = "Settings | Floss & Gloss Dental";
include("../includes/patient-header.php");
include("../includes/patient-navbar.php");
?>

<style>
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden;
    }

    body {
        display: flex;
        flex-direction: column;
    }

    .settings-wrapper {
        flex: 1;
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        padding: 20px 20px 10px;
        box-sizing: border-box;
        overflow: hidden;
    }

    .settings-inner {
    max-width: 900px;
    margin: 0 auto;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    }

    .settings-header {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto 30px;
    }

    .settings-header h1 {
        font-size: 32px;
        color: #0b2454;
        margin: 0 0 6px;
    }

    .settings-header p {
        color: #52637a;
        font-size: 17px;
        margin: 0;
    }

    .settings-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        padding: 24px 30px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        max-width: 900px;
    }

    .settings-section-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .settings-section-title h3 {
        font-size: 17px;
        color: #0f172a;
        margin: 0 0 4px;
    }

    .settings-section-title p {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        font-weight: 600;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        font-weight: 600;
        border: 1px solid #fecaca;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .settings-input-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 6px;
    }

    .settings-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .settings-input-wrapper svg {
        position: absolute;
        left: 14px;
        width: 18px;
        height: 18px;
        color: #9ca3af;
    }

    .settings-input-wrapper input {
        width: 100%;
        padding: 13px 14px 13px 42px;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font-size: 15px;
        color: #111827;
        outline: none;
        transition: 0.2s;
        box-sizing: border-box;
    }

    .settings-input-wrapper input:focus {
        border-color: #0ea5a0;
        box-shadow: 0 0 0 3px rgba(14,165,160,0.10);
    }

    .settings-actions {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
    }

    .btn-save-settings {
        background: #0ea5a0;
        border: none;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: white;
        cursor: pointer;
        transition: 0.2s;
    }

    .btn-save-settings:hover {
        background: #0b8f8a;
    }

    @media (max-width: 768px) {
        html, body {
            overflow: auto;
        }

        .settings-wrapper {
            padding: 24px 16px;
            overflow: visible;
        }

        .settings-inner {
            height: auto;
            justify-content: flex-start;
        }

        .settings-header h1 {
            font-size: 28px;
        }

        .settings-header p {
            font-size: 15px;
        }

        .settings-card {
            padding: 22px;
        }
    }
</style>

<div class="page settings-wrapper">
    <div class="settings-header">
        <h1>Settings</h1>
        <p>Manage your account settings and preferences</p>
    </div>

    <div class="settings-inner">
        <?php if ($success_message): ?>
            <div class="alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert-error">⚠ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <div class="settings-section-top">
                <div class="settings-section-title">
                    <h3>Change Password</h3>
                    <p>Update your account password to keep your account secure</p>
                </div>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="change_password">

                <div class="settings-grid">
                    <div class="settings-input-group">
                        <label>Old Password</label>
                        <div class="settings-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V9a5 5 0 00-10 0v2H6a2 2 0 00-2 2v6a2 2 0 002 2zm3-10V9a3 3 0 116 0v2H9z"></path>
                            </svg>
                            <input type="password" name="old_password" placeholder="Enter your current password" required>
                        </div>
                    </div>

                    <div class="settings-input-group">
                        <label>New Password</label>
                        <div class="settings-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V9a5 5 0 00-10 0v2H6a2 2 0 00-2 2v6a2 2 0 002 2zm3-10V9a3 3 0 116 0v2H9z"></path>
                            </svg>
                            <input type="password" name="new_password" placeholder="Enter your new password" required>
                        </div>
                    </div>

                    <div class="settings-input-group">
                        <label>Confirm New Password</label>
                        <div class="settings-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V9a5 5 0 00-10 0v2H6a2 2 0 00-2 2v6a2 2 0 002 2zm3-10V9a3 3 0 116 0v2H9z"></path>
                            </svg>
                            <input type="password" name="confirm_password" placeholder="Re-enter your new password" required>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn-save-settings">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../includes/patient-footer.php"); ?>