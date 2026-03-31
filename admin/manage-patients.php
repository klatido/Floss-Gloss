<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['system_admin', 'staff', 'admin'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* --------------------------------------------------
   HELPERS
-------------------------------------------------- */
function safeDate(?string $date, string $format = 'n/j/Y'): string {
    if (!$date) return 'N/A';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : 'N/A';
}

/* --------------------------------------------------
   ADMIN INFO
-------------------------------------------------- */
$admin_name = "Admin Staff";
$admin_role = "Administrator";

$admin_sql = "
    SELECT u.role, sp.first_name, sp.last_name
    FROM users u
    LEFT JOIN staff_profiles sp ON u.user_id = sp.user_id
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
        $name = trim(($admin_row['first_name'] ?? '') . ' ' . ($admin_row['last_name'] ?? ''));
        if ($name !== '') {
            $admin_name = $name;
        }

        if (($admin_row['role'] ?? '') === 'system_admin') {
            $admin_role = 'Administrator';
        } elseif (($admin_row['role'] ?? '') === 'staff') {
            $admin_role = 'Staff';
        } else {
            $admin_role = ucfirst($admin_row['role'] ?? 'Administrator');
        }
    }
}

/* --------------------------------------------------
   PATIENTS QUERY
-------------------------------------------------- */
$patients = [];
$active_count = 0;
$inactive_count = 0;
$total_count = 0;

$patients_sql = "
    SELECT
        pp.patient_id,
        pp.user_id,
        pp.first_name,
        pp.middle_name,
        pp.last_name,
        pp.birth_date,
        pp.created_at AS patient_created_at,
        u.email,
        u.phone,
        u.account_status,
        u.created_at AS user_created_at
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

        $status = strtolower(trim($row['account_status'] ?? 'inactive'));
        $row['display_status'] = ($status === 'active') ? 'Active' : 'Inactive';

        if ($status === 'active') {
            $active_count++;
        } else {
            $inactive_count++;
        }

        $patients[] = $row;
        $total_count++;
    }
}

$page_title = "Patients | Floss & Gloss Dental";
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
    }

    .patients-top {
        margin-bottom: 20px;
    }

    .patients-top h2 {
        margin: 0;
        font-size: 22px;
        color: #0b2454;
    }

    .patients-top p {
        margin: 6px 0 0;
        color: #52637a;
        font-size: 13px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 24px 22px;
    }

    .stat-card .value {
        font-size: 34px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 10px;
        color: #0b2454;
    }

    .stat-card .label {
        font-size: 14px;
        color: #52637a;
    }

    .stat-card.total .value {
        color: #0ea5a0;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px;
    }

    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 18px;
    }

    .panel-title h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .panel-title p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .search-wrap {
        position: relative;
        min-width: 320px;
    }

    .search-wrap input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        font-size: 14px;
        background: #f8fafc;
        outline: none;
        box-sizing: border-box;
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .patients-table {
        width: 100%;
        border-collapse: collapse;
    }

    .patients-table th {
        text-align: left;
        padding: 14px 10px;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .patients-table td {
        padding: 18px 10px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
    }

    .patients-table tr:last-child td {
        border-bottom: none;
    }

    .patient-name {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .patient-id {
        font-size: 12px;
        color: #64748b;
    }

    .contact-line {
        color: #334155;
        margin-bottom: 6px;
    }

    .contact-line:last-child {
        margin-bottom: 0;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .status-pill.active {
        background: #020617;
        color: #fff;
    }

    .status-pill.inactive {
        background: #eef2f7;
        color: #111827;
    }

    .actions-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .icon-btn {
        width: 44px;
        height: 38px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 16px;
        cursor: pointer;
    }

    .icon-btn.view {
        color: #111827;
    }

    .icon-btn.deactivate {
        color: #dc2626;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 3000;
        padding: 20px;
    }

    .modal-backdrop.show {
        display: flex;
    }

    .modal-card {
        width: 100%;
        max-width: 680px;
        background: #fff;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 25px 50px rgba(15, 23, 42, 0.18);
        padding: 28px;
        position: relative;
    }

    .modal-close-x {
        position: absolute;
        top: 18px;
        right: 20px;
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

    .modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px 34px;
    }

    .modal-item-label {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 6px;
    }

    .modal-item-value {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        word-break: break-word;
    }

    .modal-divider {
        margin: 26px 0 20px;
        border: none;
        border-top: 1px solid #e5e7eb;
    }

    .modal-actions {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }

    .btn-close {
        border-radius: 12px;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 700;
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        cursor: pointer;
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

    @media (max-width: 980px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .panel-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .search-wrap {
            min-width: 100%;
        }
    }

    @media (max-width: 760px) {
        .modal-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Patients</h1>

        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">👤</div>
        </div>
    </div>

    <div class="content">
        <div class="patients-top">
            <h2>Patient Management</h2>
            <p>View and manage patient accounts</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $active_count; ?></div>
                <div class="label">Active Patients</div>
            </div>

            <div class="stat-card">
                <div class="value"><?php echo $inactive_count; ?></div>
                <div class="label">Inactive Patients</div>
            </div>

            <div class="stat-card total">
                <div class="value"><?php echo $total_count; ?></div>
                <div class="label">Total Patients</div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <h3>All Patients</h3>
                    <p><?php echo $total_count; ?> patients</p>
                </div>

                <div class="search-wrap">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="patientSearch" placeholder="Search patients...">
                </div>
            </div>

            <div class="table-wrap">
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Contact Information</th>
                            <th>Date of Birth</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="patientsTableBody">
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $patient): ?>
                                <?php
                                    $status_class = strtolower($patient['display_status']) === 'active' ? 'active' : 'inactive';
                                    $email = $patient['email'] ?? 'N/A';
                                    $phone = $patient['phone'] ?? 'N/A';
                                    $birth_date = safeDate($patient['birth_date'] ?? null, 'n/j/Y');
                                    $registered_date = safeDate($patient['user_created_at'] ?? $patient['patient_created_at'] ?? null, 'n/j/Y');
                                ?>
                                <tr
                                    data-search="<?php echo htmlspecialchars(strtolower(
                                        ($patient['full_name'] ?? '') . ' ' .
                                        ($patient['email'] ?? '') . ' ' .
                                        ($patient['phone'] ?? '') . ' ' .
                                        ($patient['patient_id'] ?? '')
                                    )); ?>"
                                >
                                    <td>
                                        <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                        <div class="patient-id">ID: <?php echo (int)$patient['patient_id']; ?></div>
                                    </td>

                                    <td>
                                        <div class="contact-line"><?php echo htmlspecialchars($email); ?></div>
                                        <div class="contact-line"><?php echo htmlspecialchars($phone ?: 'N/A'); ?></div>
                                    </td>

                                    <td><?php echo htmlspecialchars($birth_date); ?></td>
                                    <td><?php echo htmlspecialchars($registered_date); ?></td>

                                    <td>
                                        <span class="status-pill <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($patient['display_status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="actions-wrap">
                                            <button
                                                type="button"
                                                class="icon-btn view"
                                                title="View"
                                                data-id="<?php echo (int)$patient['patient_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($patient['full_name'], ENT_QUOTES); ?>"
                                                data-email="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>"
                                                data-phone="<?php echo htmlspecialchars($phone ?: 'N/A', ENT_QUOTES); ?>"
                                                data-birth="<?php echo htmlspecialchars($birth_date, ENT_QUOTES); ?>"
                                                data-registered="<?php echo htmlspecialchars($registered_date, ENT_QUOTES); ?>"
                                                data-status="<?php echo htmlspecialchars($patient['display_status'], ENT_QUOTES); ?>"
                                            >👁</button>

                                            <?php $is_active = strtolower($patient['display_status']) === 'active'; ?>

                                            <a
                                                href="../actions/toggle-patient-status.php?id=<?php echo (int)$patient['patient_id']; ?>"
                                                class="icon-btn <?php echo $is_active ? 'deactivate' : 'activate'; ?>"
                                                title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>"
                                                onclick="return confirm('Change this patient account status?');"
                                                style="<?php echo $is_active 
                                                    ? '' 
                                                    : 'background:#020617; color:#22c55e; border-color:#020617;'; ?>"
                                            >
                                                <?php echo $is_active ? '👤×' : '👤✓'; ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">No patients found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div style="flex: 1;"></div>

        <?php if (isset($_GET['message'])): ?>
            <?php
                $toast_message = '';

                if ($_GET['message'] === 'deactivated') {
                    $toast_message = 'Patient account deactivated';
                } elseif ($_GET['message'] === 'activated') {
                    $toast_message = 'Patient account activated';
                } elseif ($_GET['message'] === 'notfound') {
                    $toast_message = 'Patient not found';
                } elseif ($_GET['message'] === 'invalid') {
                    $toast_message = 'Invalid patient selected';
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
        
        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<div class="modal-backdrop" id="patientModal">
    <div class="modal-card">
        <button class="modal-close-x" type="button" onclick="closePatientModal()">&times;</button>

        <h3 class="modal-title">Patient Details</h3>
        <p class="modal-subtitle">View patient account information</p>

        <div class="modal-grid">
            <div>
                <div class="modal-item-label">Patient Name</div>
                <div class="modal-item-value" id="modalPatientName">-</div>
            </div>

            <div>
                <div class="modal-item-label">Patient ID</div>
                <div class="modal-item-value" id="modalPatientId">-</div>
            </div>

            <div>
                <div class="modal-item-label">Email</div>
                <div class="modal-item-value" id="modalPatientEmail">-</div>
            </div>

            <div>
                <div class="modal-item-label">Phone</div>
                <div class="modal-item-value" id="modalPatientPhone">-</div>
            </div>

            <div>
                <div class="modal-item-label">Date of Birth</div>
                <div class="modal-item-value" id="modalPatientBirth">-</div>
            </div>

            <div>
                <div class="modal-item-label">Registered</div>
                <div class="modal-item-value" id="modalPatientRegistered">-</div>
            </div>

            <div>
                <div class="modal-item-label">Status</div>
                <div class="modal-item-value" id="modalPatientStatus">-</div>
            </div>
        </div>

        <hr class="modal-divider">

        <div class="modal-actions">
            <button type="button" class="btn-close" onclick="closePatientModal()">Close</button>
        </div>
    </div>
</div>

<script>
    const patientSearch = document.getElementById('patientSearch');
    const patientRows = document.querySelectorAll('#patientsTableBody tr[data-search]');
    const patientModal = document.getElementById('patientModal');

    const modalPatientId = document.getElementById('modalPatientId');
    const modalPatientName = document.getElementById('modalPatientName');
    const modalPatientEmail = document.getElementById('modalPatientEmail');
    const modalPatientPhone = document.getElementById('modalPatientPhone');
    const modalPatientBirth = document.getElementById('modalPatientBirth');
    const modalPatientRegistered = document.getElementById('modalPatientRegistered');
    const modalPatientStatus = document.getElementById('modalPatientStatus');

    patientSearch.addEventListener('input', function () {
        const value = this.value.toLowerCase().trim();

        patientRows.forEach(row => {
            const haystack = row.getAttribute('data-search') || '';
            row.style.display = haystack.includes(value) ? '' : 'none';
        });
    });

    document.querySelectorAll('.icon-btn.view').forEach(button => {
        button.addEventListener('click', function () {
            modalPatientId.textContent = this.dataset.id || '-';
            modalPatientName.textContent = this.dataset.name || '-';
            modalPatientEmail.textContent = this.dataset.email || '-';
            modalPatientPhone.textContent = this.dataset.phone || '-';
            modalPatientBirth.textContent = this.dataset.birth || '-';
            modalPatientRegistered.textContent = this.dataset.registered || '-';
            modalPatientStatus.textContent = this.dataset.status || '-';

            patientModal.classList.add('show');
        });
    });

    function closePatientModal() {
        patientModal.classList.remove('show');
    }

    patientModal.addEventListener('click', function (e) {
        if (e.target === patientModal) {
            closePatientModal();
        }
    });
</script>

<script>
    const toastMessage = document.getElementById('toastMessage');
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