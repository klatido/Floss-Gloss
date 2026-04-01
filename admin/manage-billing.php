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

$user_id = (int)$_SESSION['user_id'];

/* --------------------------------------------------
   HELPERS
-------------------------------------------------- */
function safeDateFormat(?string $date, string $format = 'n/j/Y'): string {
    if (!$date) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

function moneyFormat($amount): string {
    return '₱' . number_format((float)$amount, 0);
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
   PAYMENT ACTIONS
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action'])) {
    $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
    $action = trim($_POST['payment_action'] ?? '');

    if ($payment_id <= 0 || !in_array($action, ['verify', 'mark_pending'])) {
        header("Location: " . basename(__FILE__) . "?message=invalid");
        exit();
    }

    $get_payment_sql = "
        SELECT payment_id, appointment_id, verification_status
        FROM payments
        WHERE payment_id = ?
        LIMIT 1
    ";
    $get_payment_stmt = mysqli_prepare($conn, $get_payment_sql);

    if (!$get_payment_stmt) {
        header("Location: " . basename(__FILE__) . "?message=error");
        exit();
    }

    mysqli_stmt_bind_param($get_payment_stmt, "i", $payment_id);
    mysqli_stmt_execute($get_payment_stmt);
    $get_payment_result = mysqli_stmt_get_result($get_payment_stmt);

    if (!$get_payment_result || mysqli_num_rows($get_payment_result) === 0) {
        header("Location: " . basename(__FILE__) . "?message=notfound");
        exit();
    }

    $payment_row = mysqli_fetch_assoc($get_payment_result);
    $appointment_id = (int)$payment_row['appointment_id'];

    if ($action === 'verify') {
        $new_verification_status = 'verified';
        $new_payment_status = 'verified';

        $update_payment_sql = "
            UPDATE payments
            SET verification_status = ?, verified_by = ?
            WHERE payment_id = ?
            LIMIT 1
        ";
        $update_payment_stmt = mysqli_prepare($conn, $update_payment_sql);

        if (!$update_payment_stmt) {
            header("Location: " . basename(__FILE__) . "?message=error");
            exit();
        }

        mysqli_stmt_bind_param($update_payment_stmt, "sii", $new_verification_status, $user_id, $payment_id);
        $payment_ok = mysqli_stmt_execute($update_payment_stmt);

        $update_appointment_sql = "
            UPDATE appointments
            SET payment_status = ?, last_updated_by = ?
            WHERE appointment_id = ?
            LIMIT 1
        ";
        $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);

        if (!$update_appointment_stmt) {
            header("Location: " . basename(__FILE__) . "?message=error");
            exit();
        }

        mysqli_stmt_bind_param($update_appointment_stmt, "sii", $new_payment_status, $user_id, $appointment_id);
        $appointment_ok = mysqli_stmt_execute($update_appointment_stmt);

        if ($payment_ok && $appointment_ok) {
            header("Location: " . basename(__FILE__) . "?message=verified");
            exit();
        }
    }

    if ($action === 'mark_pending') {
        $new_verification_status = 'pending';
        $new_payment_status = 'pending';
        $null_verified_by = null;

        $update_payment_sql = "
            UPDATE payments
            SET verification_status = ?, verified_by = ?
            WHERE payment_id = ?
            LIMIT 1
        ";
        $update_payment_stmt = mysqli_prepare($conn, $update_payment_sql);

        if (!$update_payment_stmt) {
            header("Location: " . basename(__FILE__) . "?message=error");
            exit();
        }

        mysqli_stmt_bind_param($update_payment_stmt, "sii", $new_verification_status, $null_verified_by, $payment_id);
        $payment_ok = mysqli_stmt_execute($update_payment_stmt);

        $update_appointment_sql = "
            UPDATE appointments
            SET payment_status = ?, last_updated_by = ?
            WHERE appointment_id = ?
            LIMIT 1
        ";
        $update_appointment_stmt = mysqli_prepare($conn, $update_appointment_sql);

        if (!$update_appointment_stmt) {
            header("Location: " . basename(__FILE__) . "?message=error");
            exit();
        }

        mysqli_stmt_bind_param($update_appointment_stmt, "sii", $new_payment_status, $user_id, $appointment_id);
        $appointment_ok = mysqli_stmt_execute($update_appointment_stmt);

        if ($payment_ok && $appointment_ok) {
            header("Location: " . basename(__FILE__) . "?message=pending");
            exit();
        }
    }

    header("Location: " . basename(__FILE__) . "?message=error");
    exit();
}

/* --------------------------------------------------
   FETCH BILLING DATA
-------------------------------------------------- */
$transactions = [];

$billing_sql = "
    SELECT
        p.payment_id,
        p.appointment_id,
        p.amount,
        p.payment_method,
        p.reference_number,
        p.payment_date,
        p.verification_status,
        p.created_at AS payment_created_at,

        a.payment_status,
        a.requested_date,
        a.final_date,
        a.appointment_code,

        s.service_name,

        pp.patient_id,
        pp.first_name AS patient_first_name,
        pp.last_name AS patient_last_name

    FROM payments p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
    ORDER BY p.created_at DESC, p.payment_id DESC
";
$billing_result = mysqli_query($conn, $billing_sql);

$total_transactions = 0;
$total_verified_amount = 0;
$pending_count = 0;
$pending_amount = 0;
$verified_count = 0;
$recent_verified = [];

if ($billing_result) {
    while ($row = mysqli_fetch_assoc($billing_result)) {
        $patient_name = trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? ''));
        if ($patient_name === '') {
            $patient_name = 'Patient #' . ($row['patient_id'] ?? 'N/A');
        }

        $service_name = trim($row['service_name'] ?? '');
        if ($service_name === '') {
            $service_name = 'Unknown Service';
        }

        $status = strtolower(trim($row['verification_status'] ?? 'pending'));
        $amount = (float)($row['amount'] ?? 0);

        $display_date = $row['payment_date'] ?: ($row['final_date'] ?: $row['requested_date'] ?: $row['payment_created_at']);

        $row['patient_name'] = $patient_name;
        $row['service_name'] = $service_name;
        $row['display_date'] = $display_date;
        $row['display_status'] = ucfirst($status);
        $row['amount_formatted'] = moneyFormat($amount);
        $row['appointment_label'] = !empty($row['appointment_code'])
            ? $row['appointment_code']
            : '#' . str_pad((string)$row['appointment_id'], 6, '0', STR_PAD_LEFT);

        $transactions[] = $row;
        $total_transactions++;

        if ($status === 'verified') {
            $verified_count++;
            $total_verified_amount += $amount;
            $recent_verified[] = $row;
        } elseif ($status === 'pending') {
            $pending_count++;
            $pending_amount += $amount;
        }
    }
}

$recent_verified = array_slice($recent_verified, 0, 5);

$page_title = "Billing | Floss & Gloss Dental";
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 26px 30px;
        position: relative;
    }

    .stat-icon {
        position: absolute;
        top: 24px;
        right: 26px;
        font-size: 24px;
        font-weight: 700;
    }

    .stat-title {
        font-size: 14px;
        font-weight: 700;
        color: #3b5b89;
        margin-bottom: 36px;
    }

    .stat-value {
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 6px;
        color: #0f172a;
    }

    .stat-subtext {
        font-size: 13px;
        color: #52637a;
    }

    .stat-card.revenue .stat-value,
    .stat-card.revenue .stat-icon {
        color: #16a34a;
    }

    .stat-card.pending .stat-value,
    .stat-card.pending .stat-icon {
        color: #ea580c;
    }

    .stat-card.verified .stat-icon {
        color: #16a34a;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 22px 30px;
    }

    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
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
        gap: 12px;
    }

    .filter-icon {
        font-size: 20px;
        color: #94a3b8;
    }

    .filter-wrap select {
        min-width: 200px;
        padding: 12px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        font-size: 14px;
        background: #f8fafc;
        outline: none;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .billing-table {
        width: 100%;
        border-collapse: collapse;
    }

    .billing-table th {
        text-align: left;
        padding: 14px 10px;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .billing-table td {
        padding: 18px 10px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
    }

    .billing-table tr:last-child td {
        border-bottom: none;
    }

    .amount-text {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .status-pill.verified {
        background: #020617;
        color: #fff;
    }

    .status-pill.pending {
        background: #eef2f7;
        color: #111827;
    }

    .status-pill.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .action-form {
        display: inline-block;
    }

    .btn-verify,
    .btn-pending {
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-verify {
        border: none;
        background: #16a34a;
        color: #fff;
    }

    .btn-pending {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
    }

    .process-box {
        background: #ecfeff;
        border: 1px solid #99f6e4;
        border-radius: 18px;
        padding: 28px 30px;
    }

    .process-box h3 {
        margin: 0 0 22px;
        font-size: 18px;
        color: #0f766e;
    }

    .process-box ol {
        margin: 0;
        padding-left: 24px;
    }

    .process-box li {
        margin-bottom: 12px;
        color: #115e59;
        line-height: 1.55;
        font-size: 14px;
    }

    .recent-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin-top: 22px;
    }

    .recent-card {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 16px;
        padding: 18px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
    }

    .recent-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .recent-check {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #16a34a;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .recent-name {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .recent-service {
        font-size: 14px;
        color: #52637a;
    }

    .recent-right {
        text-align: right;
    }

    .recent-amount {
        font-size: 16px;
        font-weight: 700;
        color: #16a34a;
        margin-bottom: 4px;
    }

    .recent-date {
        font-size: 13px;
        color: #52637a;
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

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    @media (max-width: 1100px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .panel-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .recent-card {
            flex-direction: column;
            align-items: flex-start;
        }

        .recent-right {
            text-align: left;
        }
    }
</style>

<div class="main-area">
    <div class="topbar">
        <h1>Billing</h1>

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
            <h2>Billing & Payment Management</h2>
            <p>Manage payments and verify transactions</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-title">Total Revenue</div>
                <div class="stat-icon">₱</div>
                <div class="stat-value"><?php echo moneyFormat($total_verified_amount); ?></div>
                <div class="stat-subtext">Verified payments</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-title">Pending Payments</div>
                <div class="stat-icon">◔</div>
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <div class="stat-subtext"><?php echo moneyFormat($pending_amount); ?></div>
            </div>

            <div class="stat-card verified">
                <div class="stat-title">Verified Payments</div>
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $verified_count; ?></div>
                <div class="stat-subtext">Transactions</div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <h3>All Transactions</h3>
                    <p><?php echo $total_transactions; ?> transactions</p>
                </div>

                <div class="filter-wrap">
                    <span class="filter-icon">⎚</span>
                    <select id="paymentFilter">
                        <option value="all">All Payments</option>
                        <option value="verified">Verified</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table class="billing-table">
                    <thead>
                        <tr>
                            <th>Appointment ID</th>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="billingTableBody">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <?php
                                    $status = strtolower($transaction['verification_status'] ?? 'pending');
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($status); ?>">
                                    <td><?php echo htmlspecialchars($transaction['appointment_label']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars(safeDateFormat($transaction['display_date'], 'n/j/Y')); ?></td>
                                    <td><span class="amount-text"><?php echo htmlspecialchars($transaction['amount_formatted']); ?></span></td>
                                    <td>
                                        <span class="status-pill <?php echo htmlspecialchars($status); ?>">
                                            <?php echo $status === 'verified' ? '✓' : ($status === 'pending' ? '◔' : '•'); ?>
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($status === 'verified'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$transaction['payment_id']; ?>">
                                                <input type="hidden" name="payment_action" value="mark_pending">
                                                <button type="submit" class="btn-pending">Mark Pending</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$transaction['payment_id']; ?>">
                                                <input type="hidden" name="payment_action" value="verify">
                                                <button type="submit" class="btn-verify">Verify Payment</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">No transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="process-box">
            <h3>Payment Verification Process</h3>
            <ol>
                <li><strong>Check Payment:</strong> Review submitted payment reference and proof details.</li>
                <li><strong>Verify Payment:</strong> Confirm payment received in your bank account, cash log, or GCash.</li>
                <li><strong>Update Status:</strong> Click "Verify Payment" to confirm the transaction.</li>
                <li><strong>Notify Patient:</strong> System can use the updated payment status for downstream notices.</li>
            </ol>
        </section>

        <section class="panel">
            <div class="panel-title">
                <h3>Recent Verified Payments</h3>
                <p>Latest confirmed transactions</p>
            </div>

            <?php if (count($recent_verified) > 0): ?>
                <div class="recent-list">
                    <?php foreach ($recent_verified as $verified): ?>
                        <div class="recent-card">
                            <div class="recent-left">
                                <div class="recent-check">✓</div>

                                <div>
                                    <div class="recent-name"><?php echo htmlspecialchars($verified['patient_name']); ?></div>
                                    <div class="recent-service"><?php echo htmlspecialchars($verified['service_name']); ?></div>
                                </div>
                            </div>

                            <div class="recent-right">
                                <div class="recent-amount"><?php echo htmlspecialchars($verified['amount_formatted']); ?></div>
                                <div class="recent-date"><?php echo htmlspecialchars(safeDateFormat($verified['display_date'], 'n/j/Y')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No verified payments yet.</div>
            <?php endif; ?>
        </section>

        <div style="flex: 1;"></div>
        <?php include("../includes/admin-footer.php"); ?>
    </div>
</div>

<?php if (isset($_GET['message'])): ?>
    <?php
        $toast_message = '';

        if ($_GET['message'] === 'verified') {
            $toast_message = 'Payment verified successfully';
        } elseif ($_GET['message'] === 'pending') {
            $toast_message = 'Payment marked as pending';
        } elseif ($_GET['message'] === 'invalid') {
            $toast_message = 'Invalid payment action';
        } elseif ($_GET['message'] === 'notfound') {
            $toast_message = 'Payment record not found';
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
    const paymentFilter = document.getElementById('paymentFilter');
    const rows = document.querySelectorAll('#billingTableBody tr[data-status]');
    const toastMessage = document.getElementById('toastMessage');

    if (paymentFilter) {
        paymentFilter.addEventListener('change', function () {
            const selected = this.value;

            rows.forEach(row => {
                const status = row.getAttribute('data-status') || '';
                row.style.display = (selected === 'all' || selected === status) ? '' : 'none';
            });
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