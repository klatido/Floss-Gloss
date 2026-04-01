<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

requireClinicAccess(['staff']);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['system_admin', 'staff', 'admin'])) {
    header("Location: ../auth/admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ADMIN INFO */
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

/*
|--------------------------------------------------------------------------
| APPOINTMENTS QUERY
|--------------------------------------------------------------------------
| Assumptions:
| - patient_profiles has first_name and last_name
| - services has service_name and price
| - dentist_id is stored in appointments, but dentist table was not provided
| - so dentist is temporarily shown as "Dentist #<id>"
*/
$query = "
    SELECT 
        a.*,
        s.service_name,
        s.price,
        TRIM(CONCAT(COALESCE(pp.first_name, ''), ' ', COALESCE(pp.last_name, ''))) AS patient_name
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
    ORDER BY a.requested_date ASC, a.requested_start_time ASC
";
$result = mysqli_query($conn, $query);

$appointments = [];
$pending_count = 0;
$approved_count = 0;
$completed_count = 0;
$total_count = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (trim($row['patient_name'] ?? '') === '') {
            $row['patient_name'] = 'Patient #' . ($row['patient_id'] ?? 'N/A');
        }

        $appointments[] = $row;
        $total_count++;

        $status_lower = strtolower(trim($row['status'] ?? ''));
        if ($status_lower === 'pending') {
            $pending_count++;
        } elseif ($status_lower === 'approved') {
            $approved_count++;
        } elseif ($status_lower === 'completed') {
            $completed_count++;
        }
    }
}

$page_title = "Appointments | Floss & Gloss Dental";
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

    .appointments-top {
        margin-bottom: 20px;
    }

    .appointments-top h2 {
        margin: 0;
        font-size: 22px;
        color: #0b2454;
    }

    .appointments-top p {
        margin: 6px 0 0;
        color: #52637a;
        font-size: 13px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
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

    .stat-card.approved .value {
        color: #0ea5a0;
    }

    .stat-card.completed .value {
        color: #16a34a;
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

    .filter-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter-wrap select {
        min-width: 180px;
        padding: 12px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        font-size: 14px;
        background: #f8fafc;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .appointments-table {
        width: 100%;
        border-collapse: collapse;
    }

    .appointments-table th {
        text-align: left;
        padding: 14px 10px;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .appointments-table td {
        padding: 18px 10px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
    }

    .appointments-table tr:last-child td {
        border-bottom: none;
    }

    .patient-name,
    .service-name {
        font-weight: 700;
        color: #0f172a;
    }

    .service-price,
    .muted {
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
    }

    .date-block {
        display: flex;
        flex-direction: column;
        gap: 4px;
        white-space: nowrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .badge-pending {
        background: #eef2f7;
        color: #111827;
    }

    .badge-approved {
        background: #020617;
        color: #ffffff;
    }

    .badge-completed {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
    }

    .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-verified {
        background: #020617;
        color: #ffffff;
    }

    .badge-paid-pending {
        background: #eef2f7;
        color: #111827;
    }

    .view-btn {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
        border-radius: 12px;
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
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
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }

    .modal-item-value.small {
        font-size: 15px;
        font-weight: 600;
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

    .btn-approve,
    .btn-reject,
    .btn-close {
        border-radius: 12px;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-approve {
        background: #0ea5a0;
        color: #fff;
        border: none;
    }

    .btn-reject {
        background: #e11d48;
        color: #fff;
        border: none;
    }

    .btn-close {
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        cursor: pointer;
    }

    @media (max-width: 1100px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 780px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .panel-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .modal-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Appointments</h1>

        <div class="admin-user">
            <div class="admin-meta">
                <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <span><?php echo htmlspecialchars($admin_role); ?></span>
            </div>
            <div class="admin-avatar">👤</div>
        </div>
    </div>

    <div class="content">
        <div class="appointments-top">
            <h2>Appointments Management</h2>
            <p>View and manage all patient appointments</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $pending_count; ?></div>
                <div class="label">Pending</div>
            </div>

            <div class="stat-card approved">
                <div class="value"><?php echo $approved_count; ?></div>
                <div class="label">Approved</div>
            </div>

            <div class="stat-card completed">
                <div class="value"><?php echo $completed_count; ?></div>
                <div class="label">Completed</div>
            </div>

            <div class="stat-card">
                <div class="value"><?php echo $total_count; ?></div>
                <div class="label">Total</div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <h3>All Appointments</h3>
                    <p><?php echo $total_count; ?> appointments</p>
                </div>

                <div class="filter-wrap">
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Dentist</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsTableBody">
                        <?php if (count($appointments) > 0): ?>
                            <?php foreach ($appointments as $row): ?>
                                <?php
                                    $status = strtolower(trim($row['status'] ?? 'pending'));
                                    $payment_status = strtolower(trim($row['payment_status'] ?? 'pending'));

                                    $status_badge_class = 'badge-pending';
                                    if ($status === 'approved') {
                                        $status_badge_class = 'badge-approved';
                                    } elseif ($status === 'completed') {
                                        $status_badge_class = 'badge-completed';
                                    } elseif ($status === 'rejected') {
                                        $status_badge_class = 'badge-rejected';
                                    }

                                    $payment_badge_class = ($payment_status === 'verified') ? 'badge-verified' : 'badge-paid-pending';

                                    $raw_date = $row['requested_date'] ?? '';
                                    $formatted_date = $raw_date ? date('n/j/Y', strtotime($raw_date)) : 'No date';

                                    $raw_time = $row['requested_start_time'] ?? '';
                                    $formatted_time = $raw_time ? date('h:i A', strtotime($raw_time)) : 'No time';

                                    $price = isset($row['price']) ? (float)$row['price'] : 0;
                                    $dentist_name = !empty($row['dentist_id']) ? 'Dentist #' . $row['dentist_id'] : 'Not assigned';
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($status); ?>">
                                    <td>
                                        <div class="patient-name"><?php echo htmlspecialchars($row['patient_name']); ?></div>
                                    </td>

                                    <td>
                                        <div class="service-name"><?php echo htmlspecialchars($row['service_name'] ?? 'Unknown Service'); ?></div>
                                        <div class="service-price">₱<?php echo number_format($price, 0); ?></div>
                                    </td>

                                    <td><?php echo htmlspecialchars($dentist_name); ?></td>

                                    <td>
                                        <div class="date-block">
                                            <span><?php echo htmlspecialchars($formatted_date); ?></span>
                                            <span class="muted"><?php echo htmlspecialchars($formatted_time); ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo $status_badge_class; ?>">
                                            <?php echo htmlspecialchars($row['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo $payment_badge_class; ?>">
                                            <?php echo htmlspecialchars($row['payment_status'] ?? 'pending'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="view-btn"
                                            data-id="<?php echo (int)$row['appointment_id']; ?>"
                                            data-patient="<?php echo htmlspecialchars($row['patient_name'], ENT_QUOTES); ?>"
                                            data-service="<?php echo htmlspecialchars($row['service_name'] ?? 'Unknown Service', ENT_QUOTES); ?>"
                                            data-dentist="<?php echo htmlspecialchars($dentist_name, ENT_QUOTES); ?>"
                                            data-date="<?php echo htmlspecialchars($formatted_date, ENT_QUOTES); ?>"
                                            data-time="<?php echo htmlspecialchars($formatted_time, ENT_QUOTES); ?>"
                                            data-status="<?php echo htmlspecialchars($row['status'] ?? 'Pending', ENT_QUOTES); ?>"
                                            data-payment="<?php echo htmlspecialchars($row['payment_status'] ?? 'pending', ENT_QUOTES); ?>"
                                            data-cost="₱<?php echo number_format($price, 0); ?>"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">No appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div style="flex: 1;"></div>
        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<div class="modal-backdrop" id="appointmentModal">
    <div class="modal-card">
        <button class="modal-close-x" type="button" onclick="closeAppointmentModal()">&times;</button>

        <h3 class="modal-title">Appointment Details</h3>
        <p class="modal-subtitle">Review and manage appointment</p>

        <div class="modal-grid">
            <div>
                <div class="modal-item-label">Patient</div>
                <div class="modal-item-value" id="modalPatient">-</div>
            </div>

            <div>
                <div class="modal-item-label">Service</div>
                <div class="modal-item-value" id="modalService">-</div>
            </div>

            <div>
                <div class="modal-item-label">Dentist</div>
                <div class="modal-item-value" id="modalDentist">-</div>
            </div>

            <div>
                <div class="modal-item-label">Date &amp; Time</div>
                <div class="modal-item-value" id="modalDateTime">-</div>
            </div>

            <div>
                <div class="modal-item-label">Status</div>
                <div class="modal-item-value small">
                    <span class="badge" id="modalStatusBadge">-</span>
                </div>
            </div>

            <div>
                <div class="modal-item-label">Payment Status</div>
                <div class="modal-item-value small">
                    <span class="badge" id="modalPaymentBadge">-</span>
                </div>
            </div>

            <div>
                <div class="modal-item-label">Cost</div>
                <div class="modal-item-value" id="modalCost">-</div>
            </div>
        </div>

        <hr class="modal-divider">

        <div class="modal-actions" id="modalActions">
            <button type="button" class="btn-close" onclick="closeAppointmentModal()">Close</button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('appointmentModal');
    const modalPatient = document.getElementById('modalPatient');
    const modalService = document.getElementById('modalService');
    const modalDentist = document.getElementById('modalDentist');
    const modalDateTime = document.getElementById('modalDateTime');
    const modalStatusBadge = document.getElementById('modalStatusBadge');
    const modalPaymentBadge = document.getElementById('modalPaymentBadge');
    const modalCost = document.getElementById('modalCost');
    const modalActions = document.getElementById('modalActions');
    const statusFilter = document.getElementById('statusFilter');

    function getStatusBadgeClass(status) {
        status = status.toLowerCase().trim();
        if (status === 'approved') return 'badge badge-approved';
        if (status === 'completed') return 'badge badge-completed';
        if (status === 'rejected') return 'badge badge-rejected';
        return 'badge badge-pending';
    }

    function getPaymentBadgeClass(status) {
        status = status.toLowerCase().trim();
        if (status === 'verified') return 'badge badge-verified';
        return 'badge badge-paid-pending';
    }

    function openAppointmentModal(button) {
        const id = button.dataset.id;
        const patient = button.dataset.patient;
        const service = button.dataset.service;
        const dentist = button.dataset.dentist;
        const date = button.dataset.date;
        const time = button.dataset.time;
        const status = button.dataset.status;
        const payment = button.dataset.payment;
        const cost = button.dataset.cost;

        modalPatient.textContent = patient;
        modalService.textContent = service;
        modalDentist.textContent = dentist;
        modalDateTime.textContent = `${date} at ${time}`;
        modalCost.textContent = cost;

        modalStatusBadge.className = getStatusBadgeClass(status);
        modalStatusBadge.textContent = status;

        modalPaymentBadge.className = getPaymentBadgeClass(payment);
        modalPaymentBadge.textContent = payment;

        if (status.toLowerCase().trim() === 'pending') {
            modalActions.innerHTML = `
                <a class="btn-approve" href="update_status.php?id=${id}&status=Approved">Approve Appointment</a>
                <a class="btn-reject" href="update_status.php?id=${id}&status=Rejected" onclick="return confirm('Reject this appointment?')">Reject</a>
                <button type="button" class="btn-close" onclick="closeAppointmentModal()">Close</button>
            `;
        } else {
            modalActions.innerHTML = `
                <button type="button" class="btn-close" onclick="closeAppointmentModal()">Close</button>
            `;
        }

        modal.classList.add('show');
    }

    function closeAppointmentModal() {
        modal.classList.remove('show');
    }

    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function () {
            openAppointmentModal(this);
        });
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeAppointmentModal();
        }
    });

    statusFilter.addEventListener('change', function () {
        const selected = this.value;
        const rows = document.querySelectorAll('#appointmentsTableBody tr[data-status]');

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            row.style.display = (selected === 'all' || rowStatus === selected) ? '' : 'none';
        });
    });
</script>