<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/patient-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');

    $update_user = "UPDATE users SET phone = ? WHERE user_id = ?";
    $stmt_user = $conn->prepare($update_user);
    $stmt_user->bind_param("si", $phone, $user_id);
    
    $update_profile = "UPDATE patient_profiles SET first_name = ?, last_name = ?, address = ?, notes = ? WHERE user_id = ?";
    $stmt_profile = $conn->prepare($update_profile);
    $stmt_profile->bind_param("ssssi", $first_name, $last_name, $address, $allergies, $user_id);

    if ($stmt_user->execute() && $stmt_profile->execute()) {
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Error updating profile. Please try again.";
    }
}

$query = "
    SELECT 
        u.email, 
        u.phone, 
        u.created_at,
        p.patient_id,
        p.first_name, 
        p.middle_name, 
        p.last_name, 
        p.birth_date, 
        p.address,
        p.notes AS allergies
    FROM users u
    JOIN patient_profiles p ON u.user_id = p.user_id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

$full_name = trim($patient['first_name'] . ' ' . $patient['last_name']);
$member_since = date('n/j/Y', strtotime($patient['created_at']));
$formatted_dob = $patient['birth_date'] ? date('m/d/Y', strtotime($patient['birth_date'])) : '';

$med_query = "
    SELECT 
        mn.note_type,
        mn.chief_complaint,
        mn.diagnosis,
        mn.treatment_plan,
        mn.prescription,
        mn.note_text,
        mn.created_at,
        u.role AS encoder_role,
        sp.first_name AS staff_first_name,
        sp.last_name AS staff_last_name,
        dp.first_name AS dentist_first_name,
        dp.last_name AS dentist_last_name
    FROM medical_notes mn
    LEFT JOIN users u ON mn.encoded_by = u.user_id
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    LEFT JOIN dentist_profiles dp ON u.user_id = dp.user_id
    WHERE mn.patient_id = ?
      AND mn.note_type IN ('medical_history', 'treatment_note', 'general_note', 'appointment_note')
    ORDER BY mn.created_at DESC
    LIMIT 1
";
$med_stmt = $conn->prepare($med_query);
$med_stmt->bind_param("i", $patient['patient_id']);
$med_stmt->execute();
$med_result = $med_stmt->get_result();
$medical_record = $med_result->fetch_assoc();

$record_date = '';
$record_added_by = '';
$record_procedure = '';
$record_notes = '';

if ($medical_record) {
    $record_date = date('n/j/Y', strtotime($medical_record['created_at']));

    if (($medical_record['encoder_role'] ?? '') === 'dentist') {
        $dentist_name = trim(($medical_record['dentist_first_name'] ?? '') . ' ' . ($medical_record['dentist_last_name'] ?? ''));
        $record_added_by = $dentist_name !== '' ? 'Dr. ' . $dentist_name : 'Dentist';
    } elseif (in_array(($medical_record['encoder_role'] ?? ''), ['staff', 'system_admin', 'admin'])) {
        $staff_name = trim(($medical_record['staff_first_name'] ?? '') . ' ' . ($medical_record['staff_last_name'] ?? ''));
        $record_added_by = $staff_name !== '' ? $staff_name : 'System Administrator';
    } else {
        $record_added_by = 'Clinic Staff';
    }

    $record_procedure = trim($medical_record['treatment_plan'] ?? '');
    if ($record_procedure === '') {
        $record_procedure = trim($medical_record['diagnosis'] ?? '');
    }
    if ($record_procedure === '') {
        $record_procedure = trim($medical_record['chief_complaint'] ?? '');
    }
    if ($record_procedure === '') {
        $record_procedure = 'No procedure specified.';
    }

    $record_notes = trim($medical_record['note_text'] ?? '');
    if ($record_notes === '') {
        $record_notes = 'No treatment notes available.';
    }
}
$allergies_text = $patient['allergies'] ? $patient['allergies'] : '';

$page_title = "Profile Management | Floss & Gloss Dental";
include("../includes/patient-header.php");
include("../includes/patient-navbar.php");
?>

<style>

    .medical-record-card {
    background: #ffffff;
    border: 1px solid #dde3ea;
    border-radius: 18px;
    padding: 24px 26px;
    }

    .medical-record-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
    }

    .medical-record-meta-left {
        font-size: 14px;
        color: #64748b;
    }

    .medical-record-meta-right {
        font-size: 14px;
        color: #64748b;
        text-align: right;
        white-space: nowrap;
    }

    .medical-record-label {
        font-size: 14px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
    }

    .medical-record-value {
        font-size: 15px;
        color: #0f172a;
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .medical-record-empty {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px 20px;
        font-size: 15px;
        color: #6b7280;
    }

    @media (max-width: 768px) {
        .medical-record-head {
            flex-direction: column;
        }

        .medical-record-meta-right {
            text-align: left;
            white-space: normal;
        }
    }

    .profile-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }

    .profile-inner {
        max-width: 900px;
        margin: 0 auto;
    }
    .profile-header { margin-bottom: 30px; }
    .profile-header h1 { font-size: 28px; color: #0f172a; margin-bottom: 6px; }
    .profile-header p { color: #64748b; font-size: 15px; }

    .profile-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    }

    .profile-hero { display: flex; align-items: center; gap: 24px; }
    .profile-avatar {
        width: 80px; height: 80px; border-radius: 50%;
        background: linear-gradient(135deg, #00b7c6, #1769ff);
        display: flex; align-items: center; justify-content: center; color: white;
    }
    .profile-avatar svg { width: 36px; height: 36px; }
    .profile-info h2 { font-size: 22px; color: #0f172a; margin-bottom: 4px; }
    .profile-info p { color: #64748b; font-size: 14px; margin-bottom: 2px; }

    .profile-section-top {
        display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;
    }
    .profile-section-title h3 { font-size: 17px; color: #0f172a; margin-bottom: 4px; }
    .profile-section-title p { font-size: 14px; color: #64748b; }

    .btn-edit-profile {
        background: #ffffff; border: 1px solid #cbd5e1; padding: 8px 16px;
        border-radius: 8px; font-size: 14px; font-weight: 600; color: #0f172a;
        cursor: pointer; transition: 0.2s;
    }
    .btn-edit-profile:hover { background: #f1f5f9; }
    
    .btn-save-profile {
        background: #0ea5a0; border: none; padding: 8px 16px;
        border-radius: 8px; font-size: 14px; font-weight: 600; color: white;
        cursor: pointer; transition: 0.2s; display: none;
    }
    .btn-save-profile:hover { background: #0b8f8a; }

    .btn-cancel-profile {
        background: #fee2e2; border: none; padding: 8px 16px; margin-right: 10px;
        border-radius: 8px; font-size: 14px; font-weight: 600; color: #991b1b;
        cursor: pointer; transition: 0.2s; display: none;
    }
    .btn-cancel-profile:hover { background: #fca5a5; }

    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .profile-full-width { grid-column: 1 / -1; }

    .profile-input-group label {
        display: block; font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 8px;
    }

    .profile-input-wrapper { position: relative; display: flex; align-items: center; }
    .profile-input-wrapper svg { position: absolute; left: 14px; width: 18px; height: 18px; color: #9ca3af; }

    .profile-input-wrapper input {
        width: 100%; padding: 14px 14px 14px 42px;
        background-color: #f9fafb; border: 1px solid #f9fafb;
        border-radius: 10px; font-size: 15px; color: #6b7280; 
        outline: none; pointer-events: none; transition: 0.3s;
    }
    .profile-input-wrapper input.no-icon { padding-left: 16px; }

    /* The magic CSS class that turns on editing mode */
    .profile-input-wrapper input.editable-mode {
        background-color: #ffffff; border: 1px solid #cbd5e1;
        color: #111827; pointer-events: auto;
    }
    .profile-input-wrapper input.editable-mode:focus { border-color: #0ea5a0; box-shadow: 0 0 0 3px rgba(14,165,160,0.1); }

    .profile-warning {
        background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
        padding: 16px; margin-top: 25px; font-size: 14px; color: #b45309;
    }
    
    .profile-warning strong { font-weight: 700; color: #92400e; }
    
    .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
    .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }

    @media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }
</style>

<div class="page profile-wrapper">
    
    <div class="profile-header" style="width:100%; max-width:1200px; margin:0 auto 30px;">
        <h1 style="font-size:36px; color:#0b2454; margin-bottom:10px;">
            Profile Management
        </h1>
        <p style="color:#52637a; font-size:18px;">
            Manage your personal and medical information
        </p>
    </div>

    <div class="profile-inner">

    <?php if ($success_message): ?>
        <div class="alert-success">✓ <?= $success_message ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-error">⚠ <?= $error_message ?></div>
    <?php endif; ?>

    <div class="profile-card profile-hero">
        <div class="profile-avatar">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($full_name) ?></h2>
            <p>Patient ID: <?= htmlspecialchars($patient['patient_id']) ?></p>
            <p>Member since <?= htmlspecialchars($member_since) ?></p>
        </div>
    </div>

    <form method="POST" action="profile.php" id="profileForm">
        <input type="hidden" name="action" value="update_profile">

        <div class="profile-card">
            <div class="profile-section-top">
                <div class="profile-section-title">
                    <h3>Personal Information</h3>
                    <p>Update your personal details</p>
                </div>
                <div>
                    <button type="button" class="btn-cancel-profile" id="cancelBtn">Cancel</button>
                    <button type="button" class="btn-edit-profile" id="editBtn">Edit Profile</button>
                    <button type="submit" class="btn-save-profile" id="saveBtn">Save Changes</button>
                </div>
            </div>

            <div class="profile-grid">
                <div class="profile-input-group">
                    <label>First Name</label>
                    <div class="profile-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <input type="text" name="first_name" class="editable-field" value="<?= htmlspecialchars($patient['first_name']) ?>" readonly>
                    </div>
                </div>

                <div class="profile-input-group">
                    <label>Last Name</label>
                    <div class="profile-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <input type="text" name="last_name" class="editable-field" value="<?= htmlspecialchars($patient['last_name']) ?>" readonly>
                    </div>
                </div>

                <div class="profile-input-group">
                    <label>Email Address (Cannot be changed)</label>
                    <div class="profile-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <input type="email" value="<?= htmlspecialchars($patient['email']) ?>" readonly style="background-color: #f3f4f6;">
                    </div>
                </div>

                <div class="profile-input-group">
                    <label>Phone Number</label>
                    <div class="profile-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <input type="text" name="phone" class="editable-field" value="<?= htmlspecialchars($patient['phone']) ?>" readonly>
                    </div>
                </div>

                <div class="profile-input-group profile-full-width">
                    <label>Address</label>
                    <div class="profile-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <input type="text" name="address" class="editable-field" value="<?= htmlspecialchars($patient['address']) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-section-top" style="margin-bottom: 15px;">
                <div class="profile-section-title">
                    <h3>Medical Information</h3>
                    <p>Important health details for your dental care</p>
                </div>
            </div>

            <?php if ($medical_record): ?>
                <div class="medical-record-card">
                    <div class="medical-record-head">
                        <div class="medical-record-meta-left">
                            <?= htmlspecialchars($record_date) ?> • <?= htmlspecialchars($record_added_by) ?>
                        </div>
                        <div class="medical-record-meta-right">
                            Added by <?= htmlspecialchars($record_added_by) ?>
                        </div>
                    </div>

                    <div class="medical-record-label">Procedure</div>
                    <div class="medical-record-value">
                        <?= htmlspecialchars($record_procedure) ?>
                    </div>

                    <div class="medical-record-label">Treatment Notes</div>
                    <div class="medical-record-value">
                        <?= nl2br(htmlspecialchars($record_notes)) ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="medical-record-empty">
                    No major health issues recorded.
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const editableFields = document.querySelectorAll('.editable-field');

    // Store original values in case they cancel
    const originalValues = {};

    editBtn.addEventListener('click', function() {
        // Hide Edit button, show Save and Cancel
        editBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
        cancelBtn.style.display = 'inline-block';

        // Unlock fields and add CSS styling
        editableFields.forEach(field => {
            originalValues[field.name] = field.value; // save original
            field.removeAttribute('readonly');
            field.classList.add('editable-mode');
        });
        
        // Auto focus the first field
        editableFields[0].focus();
    });

    cancelBtn.addEventListener('click', function() {
        // Hide Save and Cancel, show Edit
        editBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';

        // Re-lock fields, remove CSS styling, and restore original values
        editableFields.forEach(field => {
            field.value = originalValues[field.name]; // restore original
            field.setAttribute('readonly', true);
            field.classList.remove('editable-mode');
        });
    });
</script>

</div>

<?php include("../includes/patient-footer.php"); ?>