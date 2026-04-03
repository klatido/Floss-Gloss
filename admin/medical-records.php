<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff', 'dentist']);

$canManageRecords = hasRole(['staff']);
$user_id = (int)($_SESSION['user_id'] ?? 0);

/* dentists are view-only */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canManageRecords) {
    header("Location: ../auth/unauthorized.php");
    exit();
}

/* --------------------------------------------------
   HELPERS
-------------------------------------------------- */
function safeDateFormat(?string $date, string $format = 'n/j/Y'): string {
    if (!$date) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/* --------------------------------------------------
   ADMIN / TOPBAR INFO
-------------------------------------------------- */
$admin_name = "Admin Staff";
$admin_role = "Administrator";

$admin_sql = "
    SELECT 
        u.role,
        sp.first_name AS staff_first_name,
        sp.last_name AS staff_last_name,
        dp.first_name AS dentist_first_name,
        dp.last_name AS dentist_last_name
    FROM users u
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
    LEFT JOIN dentist_profiles dp ON u.user_id = dp.user_id
    WHERE u.user_id = ?
    LIMIT 1
";
$admin_stmt = mysqli_prepare($conn, $admin_sql);
if ($admin_stmt) {
    mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);

    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_result);

        $name = trim(
            (($admin_row['staff_first_name'] ?? '') ?: ($admin_row['dentist_first_name'] ?? '')) . ' ' .
            (($admin_row['staff_last_name'] ?? '') ?: ($admin_row['dentist_last_name'] ?? ''))
        );

        if ($name !== '') {
        if (($admin_row['role'] ?? '') === 'dentist') {
            $admin_name = 'Dr. ' . $name;
        } else {
            $admin_name = $name;
        }
    }

        if (($admin_row['role'] ?? '') === 'system_admin') {
            $admin_role = 'Administrator';
        } elseif (($admin_row['role'] ?? '') === 'staff') {
            $admin_role = 'Staff';
        } elseif (($admin_row['role'] ?? '') === 'dentist') {
            $admin_role = 'Dentist';
        } else {
            $admin_role = ucfirst($admin_row['role'] ?? 'Administrator');
        }
    }
}

/* --------------------------------------------------
   ADD MEDICAL RECORD
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record']) && $canManageRecords) {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $procedure = trim($_POST['procedure'] ?? '');
    $treatment_notes = trim($_POST['treatment_notes'] ?? '');

    if ($patient_id <= 0 || $procedure === '' || $treatment_notes === '') {
        header("Location: " . basename(__FILE__) . "?message=invalid");
        exit();
    }

    $check_patient_sql = "
        SELECT patient_id
        FROM patient_profiles
        WHERE patient_id = ?
        LIMIT 1
    ";
    $check_patient_stmt = mysqli_prepare($conn, $check_patient_sql);

    if (!$check_patient_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($check_patient_stmt, "i", $patient_id);
    mysqli_stmt_execute($check_patient_stmt);
    $check_patient_result = mysqli_stmt_get_result($check_patient_stmt);

    if (!$check_patient_result || mysqli_num_rows($check_patient_result) === 0) {
        header("Location: " . basename(__FILE__) . "?message=patient_not_found");
        exit();
    }

    $insert_sql = "
        INSERT INTO medical_notes
        (
            patient_id,
            appointment_id,
            encoded_by,
            note_type,
            chief_complaint,
            diagnosis,
            treatment_plan,
            prescription,
            note_text,
            is_confidential
        )
        VALUES (?, NULL, ?, 'treatment_note', NULL, NULL, ?, NULL, ?, 0)
    ";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);

    if (!$insert_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($insert_stmt, "iiss", $patient_id, $user_id, $procedure, $treatment_notes);

    if (mysqli_stmt_execute($insert_stmt)) {
        header("Location: " . basename(__FILE__) . "?message=added");
        exit();
    }

    header("Location: " . basename(__FILE__) . "?message=error");
    exit();
}

/* --------------------------------------------------
   EDIT MEDICAL RECORD
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_record']) && $canManageRecords) {
    $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $procedure = trim($_POST['procedure'] ?? '');
    $treatment_notes = trim($_POST['treatment_notes'] ?? '');

    if ($note_id <= 0 || $patient_id <= 0 || $procedure === '' || $treatment_notes === '') {
        header("Location: " . basename(__FILE__) . "?message=invalid_edit");
        exit();
    }

    $check_note_sql = "
        SELECT note_id
        FROM medical_notes
        WHERE note_id = ?
        LIMIT 1
    ";
    $check_note_stmt = mysqli_prepare($conn, $check_note_sql);

    if (!$check_note_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($check_note_stmt, "i", $note_id);
    mysqli_stmt_execute($check_note_stmt);
    $check_note_result = mysqli_stmt_get_result($check_note_stmt);

    if (!$check_note_result || mysqli_num_rows($check_note_result) === 0) {
        header("Location: " . basename(__FILE__) . "?message=notfound");
        exit();
    }

    $update_sql = "
        UPDATE medical_notes
        SET
            patient_id = ?,
            treatment_plan = ?,
            note_text = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE note_id = ?
        LIMIT 1
    ";
    $update_stmt = mysqli_prepare($conn, $update_sql);

    if (!$update_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($update_stmt, "issi", $patient_id, $procedure, $treatment_notes, $note_id);

    if (mysqli_stmt_execute($update_stmt)) {
        header("Location: " . basename(__FILE__) . "?message=updated");
        exit();
    }

    header("Location: " . basename(__FILE__) . "?message=error");
    exit();
}

/* --------------------------------------------------
   DELETE MEDICAL RECORD
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record']) && $canManageRecords) {
    $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

    if ($note_id <= 0) {
        header("Location: " . basename(__FILE__) . "?message=invalid_delete");
        exit();
    }

    $delete_sql = "
        DELETE FROM medical_notes
        WHERE note_id = ?
        LIMIT 1
    ";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);

    if (!$delete_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($delete_stmt, "i", $note_id);

    if (mysqli_stmt_execute($delete_stmt)) {
        header("Location: " . basename(__FILE__) . "?message=deleted");
        exit();
    }

    header("Location: " . basename(__FILE__) . "?message=error");
    exit();
}

/* --------------------------------------------------
   PATIENTS FOR DROPDOWN
-------------------------------------------------- */
$patients = [];

$patients_sql = "
    SELECT
        pp.patient_id,
        pp.first_name,
        pp.last_name
    FROM patient_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE u.role = 'patient'
    ORDER BY pp.first_name ASC, pp.last_name ASC
";
$patients_result = mysqli_query($conn, $patients_sql);

if ($patients_result) {
    while ($row = mysqli_fetch_assoc($patients_result)) {
        $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($full_name === '') {
            $full_name = 'Patient #' . ($row['patient_id'] ?? 'N/A');
        }

        $row['full_name'] = $full_name;
        $patients[] = $row;
    }
}

/* --------------------------------------------------
   MEDICAL RECORDS LIST
-------------------------------------------------- */
$records = [];

$records_sql = "
    SELECT
        mn.note_id,
        mn.patient_id,
        mn.encoded_by,
        mn.note_type,
        mn.treatment_plan,
        mn.note_text,
        mn.created_at,

        pp.first_name AS patient_first_name,
        pp.last_name AS patient_last_name,

        u_encoder.role AS encoder_role,
        dsp.first_name AS dentist_first_name,
        dsp.last_name AS dentist_last_name,
        ssp.first_name AS staff_first_name,
        ssp.last_name AS staff_last_name

    FROM medical_notes mn
    INNER JOIN patient_profiles pp ON mn.patient_id = pp.patient_id
    LEFT JOIN users u_encoder ON mn.encoded_by = u_encoder.user_id
    LEFT JOIN dentist_profiles dsp ON u_encoder.user_id = dsp.user_id
    LEFT JOIN staff_profiles ssp ON u_encoder.user_id = ssp.user_id
    ORDER BY mn.created_at DESC, mn.note_id DESC
";
$records_result = mysqli_query($conn, $records_sql);

if ($records_result) {
    while ($row = mysqli_fetch_assoc($records_result)) {
        $patient_name = trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? ''));
        if ($patient_name === '') {
            $patient_name = 'Patient #' . ($row['patient_id'] ?? 'N/A');
        }

        $added_by = 'System';
        if (($row['encoder_role'] ?? '') === 'dentist') {
            $dentist_name = trim(($row['dentist_first_name'] ?? '') . ' ' . ($row['dentist_last_name'] ?? ''));
            $added_by = $dentist_name !== '' ? 'Dr. ' . $dentist_name : 'Dentist';
        } elseif (in_array(($row['encoder_role'] ?? ''), ['staff', 'system_admin', 'admin'])) {
            $staff_name = trim(($row['staff_first_name'] ?? '') . ' ' . ($row['staff_last_name'] ?? ''));
            $added_by = $staff_name !== '' ? $staff_name : 'Admin Staff';
        }

        $procedure = trim($row['treatment_plan'] ?? '');
        if ($procedure === '') {
            $procedure = 'No procedure specified';
        }

        $notes = trim($row['note_text'] ?? '');
        if ($notes === '') {
            $notes = 'No treatment notes available.';
        }

        $row['patient_name'] = $patient_name;
        $row['added_by'] = $added_by;
        $row['procedure'] = $procedure;
        $row['notes'] = $notes;

        $records[] = $row;
    }
}

$page_title = "Medical Records | Floss & Gloss Dental";
include("../includes/admin-header.php");
include("../includes/admin-sidebar.php");
?>

<style>
    .topbar {
        position: sticky;
        top: 0;
        z-index: 900;
        min-height: 72px;
        background: #ffffff;
        border-bottom: 1px solid #dbe2ea;
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .topbar h1 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .admin-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .admin-meta {
        text-align: right;
    }

    .admin-meta strong {
        display: block;
        font-size: 13px;
    }

    .admin-meta span {
        color: #64748b;
        font-size: 11px;
    }

    .admin-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #d1fae5;
        color: #059669;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .content {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 20px;
        gap: 18px;
    }

    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
    }

    .page-top h2 {
        margin: 0;
        font-size: 22px;
        color: #0b2454;
    }

    .page-top p {
        margin: 6px 0 0;
        color: #52637a;
        font-size: 13px;
    }

    .add-btn {
        border: none;
        background: #0ea5a0;
        color: #fff;
        padding: 12px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
    }

    .search-wrap {
        position: relative;
    }

    .search-wrap input {
        width: 100%;
        padding: 14px 16px 14px 48px;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        background: #f8fafc;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
    }

    .records-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .record-card {
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 24px 30px;
    }

    .record-head {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        margin-bottom: 26px;
    }

    .record-patient {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .record-meta {
        font-size: 14px;
        color: #64748b;
    }

    .record-added-by {
        font-size: 14px;
        color: #64748b;
        text-align: right;
        white-space: nowrap;
    }

    .record-label {
        font-size: 14px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
    }

    .record-value {
        font-size: 14px;
        color: #0f172a;
        line-height: 1.6;
        margin-bottom: 18px;
    }

    .record-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
    }

    .action-btn {
        min-width: 110px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        background: #fff;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .action-btn.edit {
        color: #111827;
    }

    .action-btn.delete {
        color: #dc2626;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    .toast {
        position: fixed;
        right: 20px;
        bottom: 20px;
        background: #ffffff;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        z-index: 5000;
        min-width: 260px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }

    .toast-icon {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #111827;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 4000;
        padding: 20px;
    }

    .modal-backdrop.show {
        display: flex;
    }

    .modal-card {
        width: 100%;
        max-width: 640px;
        background: #fff;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 25px 50px rgba(15, 23, 42, 0.18);
        padding: 28px 30px;
        position: relative;
    }

    .modal-close-x {
        position: absolute;
        top: 14px;
        right: 16px;
        border: none;
        background: transparent;
        font-size: 34px;
        line-height: 1;
        cursor: pointer;
        color: #666;
    }

    .modal-title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }

    .modal-subtitle {
        margin: 6px 0 24px;
        font-size: 14px;
        color: #6b7280;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #111827;
    }

    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        background: #f8fafc;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .form-group textarea {
        min-height: 110px;
        resize: vertical;
    }

    .modal-actions {
        margin-top: 8px;
        display: flex;
        justify-content: flex-end;
        gap: 14px;
    }

    .btn-cancel,
    .btn-save {
        border-radius: 12px;
        padding: 12px 18px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-cancel {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
    }

    .btn-save {
        border: none;
        background: #0ea5a0;
        color: #fff;
    }

    @media (max-width: 900px) {
        .page-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .record-head {
            flex-direction: column;
        }

        .record-added-by {
            text-align: left;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Medical Records</h1>

        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">👤</div>
        </div>
    </div>

    <div class="content">
        <div class="page-top">
            <div>
                <h2>Medical Records</h2>
                <p><?php echo $canManageRecords ? 'View and manage patient medical history' : 'View patient medical history'; ?></p>
            </div>

            <?php if ($canManageRecords): ?>
                <button type="button" class="add-btn" onclick="openAddRecordModal()">
                    <span style="font-size:20px; line-height:1;">+</span>
                    Add Record
                </button>
            <?php endif; ?>
        </div>

        <section class="panel">
            <div class="search-wrap">
                <span class="search-icon">⌕</span>
                <input type="text" id="recordSearch" placeholder="Search by patient name or procedure...">
            </div>
        </section>

        <div class="records-list" id="recordsList">
            <?php if (count($records) > 0): ?>
                <?php foreach ($records as $record): ?>
                    <div
                        class="record-card"
                        data-search="<?php echo htmlspecialchars(strtolower($record['patient_name'] . ' ' . $record['procedure'] . ' ' . $record['notes'])); ?>"
                    >
                        <div class="record-head">
                            <div>
                                <div class="record-patient"><?php echo htmlspecialchars($record['patient_name']); ?></div>
                                <div class="record-meta">
                                    <?php echo htmlspecialchars(safeDateFormat($record['created_at'], 'n/j/Y')); ?>
                                    •
                                    <?php echo htmlspecialchars($record['added_by']); ?>
                                </div>
                            </div>

                            <div class="record-added-by">
                                Added by <?php echo htmlspecialchars($record['added_by']); ?>
                            </div>
                        </div>

                        <div class="record-label">Procedure</div>
                        <div class="record-value"><?php echo htmlspecialchars($record['procedure']); ?></div>

                        <div class="record-label">Treatment Notes</div>
                        <div class="record-value"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>

                        <?php if ($canManageRecords): ?>
                            <div class="record-actions">
                                <button
                                    type="button"
                                    class="action-btn edit"
                                    data-note-id="<?php echo (int)$record['note_id']; ?>"
                                    data-patient-id="<?php echo (int)$record['patient_id']; ?>"
                                    data-procedure="<?php echo htmlspecialchars($record['procedure'], ENT_QUOTES); ?>"
                                    data-notes="<?php echo htmlspecialchars($record['notes'], ENT_QUOTES); ?>"
                                    onclick="openEditRecordModal(this)"
                                >
                                    Edit
                                </button>

                                <form method="POST" onsubmit="return confirm('Delete this medical record?');" style="display:inline;">
                                    <input type="hidden" name="delete_record" value="1">
                                    <input type="hidden" name="note_id" value="<?php echo (int)$record['note_id']; ?>">
                                    <button type="submit" class="action-btn delete">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <section class="panel">
                    <div class="empty-state">No medical records found.</div>
                </section>
            <?php endif; ?>
        </div>

        <div style="flex: 1;"></div>
        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<?php if ($canManageRecords): ?>
<div class="modal-backdrop" id="addRecordModal">
    <div class="modal-card">
        <button class="modal-close-x" type="button" onclick="closeAddRecordModal()">&times;</button>

        <h3 class="modal-title">Add Medical Record</h3>
        <p class="modal-subtitle">Add treatment notes and medical history for a patient</p>

        <form method="POST">
            <input type="hidden" name="add_record" value="1">

            <div class="form-group">
                <label>Select Patient</label>
                <select name="patient_id" required>
                    <option value="">Choose a patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo (int)$patient['patient_id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Procedure/Treatment</label>
                <input
                    type="text"
                    name="procedure"
                    placeholder="e.g., Teeth Cleaning, Root Canal"
                    required
                >
            </div>

            <div class="form-group">
                <label>Treatment Notes</label>
                <textarea
                    name="treatment_notes"
                    placeholder="Enter detailed treatment notes, observations, and recommendations..."
                    required
                ></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddRecordModal()">Cancel</button>
                <button type="submit" class="btn-save">Add Record</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editRecordModal">
    <div class="modal-card">
        <button class="modal-close-x" type="button" onclick="closeEditRecordModal()">&times;</button>

        <h3 class="modal-title">Edit Medical Record</h3>
        <p class="modal-subtitle">Update treatment notes and patient record details</p>

        <form method="POST">
            <input type="hidden" name="edit_record" value="1">
            <input type="hidden" name="note_id" id="edit_note_id">

            <div class="form-group">
                <label>Select Patient</label>
                <select name="patient_id" id="edit_patient_id" required>
                    <option value="">Choose a patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo (int)$patient['patient_id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Procedure/Treatment</label>
                <input
                    type="text"
                    name="procedure"
                    id="edit_procedure"
                    required
                >
            </div>

            <div class="form-group">
                <label>Treatment Notes</label>
                <textarea
                    name="treatment_notes"
                    id="edit_treatment_notes"
                    required
                ></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditRecordModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['message'])): ?>
    <?php
        $toast_message = '';
        if ($_GET['message'] === 'added') {
            $toast_message = 'Medical record added successfully';
        } elseif ($_GET['message'] === 'updated') {
            $toast_message = 'Medical record updated successfully';
        } elseif ($_GET['message'] === 'deleted') {
            $toast_message = 'Medical record deleted successfully';
        } elseif ($_GET['message'] === 'invalid') {
            $toast_message = 'Please complete all required fields';
        } elseif ($_GET['message'] === 'invalid_edit') {
            $toast_message = 'Please complete all edit fields';
        } elseif ($_GET['message'] === 'invalid_delete') {
            $toast_message = 'Invalid record selected';
        } elseif ($_GET['message'] === 'patient_not_found') {
            $toast_message = 'Selected patient not found';
        } elseif ($_GET['message'] === 'notfound') {
            $toast_message = 'Medical record not found';
        } elseif ($_GET['message'] === 'error') {
            $toast_message = 'Something went wrong';
        }
    ?>

    <?php if ($toast_message !== ''): ?>
        <div class="toast" id="toastMessage">
            <span class="toast-icon">✓</span>
            <span><?php echo htmlspecialchars($toast_message); ?></span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
    const recordSearch = document.getElementById('recordSearch');
    const recordCards = document.querySelectorAll('.record-card');
    const addRecordModal = document.getElementById('addRecordModal');
    const editRecordModal = document.getElementById('editRecordModal');
    const toastMessage = document.getElementById('toastMessage');

    function openAddRecordModal() {
        if (addRecordModal) {
            addRecordModal.classList.add('show');
        }
    }

    function closeAddRecordModal() {
        if (addRecordModal) {
            addRecordModal.classList.remove('show');
        }
    }

    function openEditRecordModal(button) {
        const noteId = document.getElementById('edit_note_id');
        const patientId = document.getElementById('edit_patient_id');
        const procedure = document.getElementById('edit_procedure');
        const notes = document.getElementById('edit_treatment_notes');

        if (noteId) noteId.value = button.dataset.noteId || '';
        if (patientId) patientId.value = button.dataset.patientId || '';
        if (procedure) procedure.value = button.dataset.procedure || '';
        if (notes) notes.value = button.dataset.notes || '';

        if (editRecordModal) {
            editRecordModal.classList.add('show');
        }
    }

    function closeEditRecordModal() {
        if (editRecordModal) {
            editRecordModal.classList.remove('show');
        }
    }

    if (recordSearch) {
        recordSearch.addEventListener('input', function () {
            const value = this.value.toLowerCase().trim();

            recordCards.forEach(card => {
                const haystack = card.getAttribute('data-search') || '';
                card.style.display = haystack.includes(value) ? '' : 'none';
            });
        });
    }

    if (addRecordModal) {
        addRecordModal.addEventListener('click', function (e) {
            if (e.target === addRecordModal) {
                closeAddRecordModal();
            }
        });
    }

    if (editRecordModal) {
        editRecordModal.addEventListener('click', function (e) {
            if (e.target === editRecordModal) {
                closeEditRecordModal();
            }
        });
    }

    if (toastMessage) {
        setTimeout(() => {
            toastMessage.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toastMessage.style.opacity = '0';
            toastMessage.style.transform = 'translateY(10px)';
        }, 2500);

        setTimeout(() => {
            toastMessage.remove();
        }, 3000);
    }
</script>