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
function safeDateFormat(?string $date, string $format = 'd/m/y'): string {
    if (!$date) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

function moneyFormat($amount): string {
    return '₱' . number_format((float)$amount, 0);
}

function monthLabel(int $month): string {
    return date('M', mktime(0, 0, 0, $month, 1));
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
   FILTERS
-------------------------------------------------- */
$selected_date = trim($_GET['selected_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$selected_year = isset($_GET['selected_year']) ? (int)$_GET['selected_year'] : (int)date('Y');
if ($selected_year < 2020 || $selected_year > 2100) {
    $selected_year = (int)date('Y');
}

/* --------------------------------------------------
   FETCH BILLING DATA
-------------------------------------------------- */
$transactions = [];

$billing_sql = "
    SELECT
        a.appointment_id,
        a.appointment_code,
        a.status AS appointment_status,
        a.payment_status,
        a.requested_date,
        a.final_date,
        a.requested_start_time,
        a.final_start_time,

        p.payment_id,
        p.amount AS payment_amount,
        p.payment_date,
        p.verification_status,
        p.created_at AS payment_created_at,
        p.verified_by,
        p.verification_notes,

        s.service_name,
        s.price AS service_price,

        pp.patient_id,
        pp.first_name AS patient_first_name,
        pp.last_name AS patient_last_name

    FROM appointments a
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN patient_profiles pp ON a.patient_id = pp.patient_id
    WHERE a.status IN ('approved', 'completed', 'rejected')
    ORDER BY COALESCE(a.final_date, a.requested_date) DESC, a.appointment_id DESC
";
$billing_result = mysqli_query($conn, $billing_sql);

$total_transactions = 0;
$daily_verified_amount = 0;
$daily_pending_count = 0;
$daily_pending_amount = 0;
$daily_verified_count = 0;
$recent_verified = [];
$monthly_summary = [];

for ($m = 1; $m <= 12; $m++) {
    $monthly_summary[$m] = [
        'label' => monthLabel($m),
        'revenue' => 0,
        'payments' => 0,
        'pending' => 0,
        'rejected' => 0
    ];
}

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

        $appointment_status = strtolower(trim($row['appointment_status'] ?? 'pending'));
        $payment_status = strtolower(trim($row['payment_status'] ?? 'pending'));

        /* AMOUNT MUST FOLLOW SERVICE PRICE IF PAYMENT AMOUNT IS NULL/0 */
        $service_price = isset($row['service_price']) ? (float)$row['service_price'] : 0.00;
        $payment_amount = isset($row['payment_amount']) ? (float)$row['payment_amount'] : 0.00;
        $amount = ($payment_amount > 0) ? $payment_amount : $service_price;

        /* BILLING DATE MUST FOLLOW APPOINTMENT DATE */
        $billing_date = $row['final_date'] ?: $row['requested_date'];
        $billing_time_raw = $row['final_start_time'] ?: $row['requested_start_time'];
        $billing_time = $billing_time_raw ? date('h:i A', strtotime($billing_time_raw)) : 'No time';

        /* DISPLAY STATUS SHOULD FOLLOW APPOINTMENT PAYMENT STATUS */
        $display_payment_status = $payment_status;
        if ($appointment_status === 'rejected') {
            $display_payment_status = 'rejected';
        }

        $row['patient_name'] = $patient_name;
        $row['service_name'] = $service_name;
        $row['display_date'] = $billing_date;
        $row['display_time'] = $billing_time;
        $row['display_status'] = ucfirst($display_payment_status);
        $row['amount_formatted'] = moneyFormat($amount);
        $row['computed_amount'] = $amount;
        $row['computed_payment_status'] = $display_payment_status;
        $row['appointment_label'] = !empty($row['appointment_code'])
            ? $row['appointment_code']
            : '#' . str_pad((string)$row['appointment_id'], 6, '0', STR_PAD_LEFT);

        /* DAILY TABLE FILTER */
        if ($billing_date === $selected_date) {
            $transactions[] = $row;
            $total_transactions++;

            if ($display_payment_status === 'verified') {
                $daily_verified_count++;
                $daily_verified_amount += $amount;
                $recent_verified[] = $row;
            } elseif ($display_payment_status === 'pending') {
                $daily_pending_count++;
                $daily_pending_amount += $amount;
            }
        }

        /* MONTHLY SUMMARY */
        if (!empty($billing_date)) {
            $year_of_row = (int)date('Y', strtotime($billing_date));
            $month_of_row = (int)date('n', strtotime($billing_date));

            if ($year_of_row === $selected_year) {
                if ($display_payment_status === 'verified') {
                    $monthly_summary[$month_of_row]['revenue'] += $amount;
                    $monthly_summary[$month_of_row]['payments']++;
                } elseif ($display_payment_status === 'pending') {
                    $monthly_summary[$month_of_row]['pending']++;
                } elseif ($display_payment_status === 'rejected') {
                    $monthly_summary[$month_of_row]['rejected']++;
                }
            }
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

    .filter-bar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: end;
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 18px;
        padding: 18px 20px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-group label {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }

    .filter-group input,
    .filter-group select {
        min-width: 180px;
        padding: 11px 12px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        font-size: 14px;
        background: #f8fafc;
        outline: none;
    }

    .btn-filter {
        border: none;
        background: #0ea5a0;
        color: #fff;
        padding: 12px 18px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
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

    .billing-table,
    .history-table {
        width: 100%;
        border-collapse: collapse;
    }

    .billing-table th,
    .history-table th {
        text-align: left;
        padding: 14px 10px;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .billing-table td,
    .history-table td {
        padding: 18px 10px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
    }

    .billing-table tr:last-child td,
    .history-table tr:last-child td {
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
            <p>Daily billing dashboard with yearly monthly summary</p>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Selected Day</label>
                <input type="date" name="selected_date" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>

            <div class="filter-group">
                <label>Selected Year</label>
                <select name="selected_year">
                    <?php for ($year = (int)date('Y') - 2; $year <= (int)date('Y') + 2; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selected_year === $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit" class="btn-filter">Apply Filters</button>
        </form>

        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-title">Revenue for <?php echo htmlspecialchars(safeDateFormat($selected_date)); ?></div>
                <div class="stat-icon">₱</div>
                <div class="stat-value"><?php echo moneyFormat($daily_verified_amount); ?></div>
                <div class="stat-subtext">Verified payments of the day</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-title">Pending Payments for <?php echo htmlspecialchars(safeDateFormat($selected_date)); ?></div>
                <div class="stat-icon">◔</div>
                <div class="stat-value"><?php echo $daily_pending_count; ?></div>
                <div class="stat-subtext"><?php echo moneyFormat($daily_pending_amount); ?></div>
            </div>

            <div class="stat-card verified">
                <div class="stat-title">Verified Payments for <?php echo htmlspecialchars(safeDateFormat($selected_date)); ?></div>
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $daily_verified_count; ?></div>
                <div class="stat-subtext">Transactions</div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <h3>Transactions for <?php echo htmlspecialchars(safeDateFormat($selected_date)); ?></h3>
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
                            <th>Date &amp; Time</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>

                    <tbody id="billingTableBody">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <?php $status = strtolower($transaction['computed_payment_status'] ?? 'pending'); ?>
                                <tr data-status="<?php echo htmlspecialchars($status); ?>">
                                    <td><?php echo htmlspecialchars($transaction['appointment_label']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['service_name']); ?></td>                               
                                    <td>
                                    <?php echo htmlspecialchars(safeDateFormat($transaction['display_date'], 'd/m/y')); ?><br>
                                    <span class="muted"><?php echo htmlspecialchars($transaction['display_time'] ?? 'No time'); ?></span>
                                    </td>
                                    <td><span class="amount-text"><?php echo htmlspecialchars($transaction['amount_formatted']); ?></span></td>
                                    <td>
                                        <span class="status-pill <?php echo htmlspecialchars($status); ?>">
                                            <?php echo $status === 'verified' ? '✓' : ($status === 'pending' ? '◔' : '•'); ?>
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">No transactions found for this day.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="process-box">
            <h3>Payment Verification Process</h3>
            <ol>
                <li><strong>Appointment Approved:</strong> Once an appointment is approved, it appears as a pending payment for its billing day.</li>
                <li><strong>Check Payment:</strong> Review submitted payment reference and proof details.</li>
                <li><strong>Verify Payment:</strong> Confirm payment received in your bank account, cash log, or GCash.</li>
                <li><strong>Update Status:</strong> Click "Verify Payment" to confirm the transaction.</li>
            </ol>
        </section>

        <section class="panel">
            <div class="panel-title">
                <h3>Recent Verified Payments for <?php echo htmlspecialchars(safeDateFormat($selected_date)); ?></h3>
                <p>Latest confirmed transactions of the selected day</p>
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
                                <div class="recent-date"><?php echo htmlspecialchars(safeDateFormat($verified['display_date'], 'd/m/y')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No verified payments yet for this day.</div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-title">
                <h3>Monthly Billing History for <?php echo htmlspecialchars((string)$selected_year); ?></h3>
                <p>January to December yearly summary</p>
            </div>

            <div class="table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Revenue</th>
                            <th>Verified Payments</th>
                            <th>Pending Payments</th>
                            <th>Rejected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_summary as $month => $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['label']); ?></td>
                                <td><span class="amount-text"><?php echo moneyFormat($data['revenue']); ?></span></td>
                                <td><?php echo (int)$data['payments']; ?></td>
                                <td><?php echo (int)$data['pending']; ?></td>
                                <td><?php echo (int)$data['rejected']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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