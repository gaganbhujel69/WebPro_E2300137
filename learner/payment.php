<?php
// ============================================================
//  EMS — Learner Payment & Receipt
//  learner/payment.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/enrolment_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('learner');

$learnerId   = current_user_id();
$enrolmentId = (int)($_GET['enrolment_id'] ?? 0);

// Fetch enrolment
$enrolment = get_enrolment_detail($conn, $enrolmentId, $learnerId);
if (!$enrolment) {
    http_response_code(404);
    die('Enrolment not found or does not belong to you.');
}

// Check if already paid
$existingPayment = get_existing_payment($conn, $enrolmentId);

$errors  = [];

// ── Handle Payment POST ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingPayment) {
    verify_csrf();

    $formData = [
        'payment_method'  => $_POST['payment_method']  ?? '',
        'cardholder_name' => $_POST['cardholder_name'] ?? ''
    ];

    $errors = validate_payment($formData['payment_method'], $formData['cardholder_name']);

    if (empty($errors)) {
        $amount = (float)$enrolment['fee'];
        $result = process_payment($conn, $enrolmentId, $amount, $formData['payment_method'], $formData['cardholder_name']);

        if ($result['success']) {
            // Reload for display
            $existingPayment = get_existing_payment($conn, $enrolmentId);
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Payment & Receipt';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-credit-card me-2 text-ems-primary"></i>Payment</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/learner/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Payment</li>
        </ol>
    </nav>
</div>

<?php if ($existingPayment && $existingPayment['payment_status'] === 'success'): ?>
    <!-- ── RECEIPT VIEW ────────────────────────────────── -->
    <div class="receipt-box mb-4 d-print-block" id="receiptBox">
        <div class="text-center mb-4">
            <div class="auth-logo mb-1"><i class="bi bi-mortarboard-fill"></i> EMS</div>
            <h4 class="fw-700">Official Enrolment Receipt</h4>
            <p class="text-muted small">Receipt No: <strong><?= htmlspecialchars($existingPayment['receipt_no']) ?></strong></p>
        </div>

        <table class="table table-borderless mb-0" style="font-size:.9rem;">
            <tbody>
                <tr><th style="width:40%;">Learner</th><td><?= htmlspecialchars($_SESSION['full_name']) ?></td></tr>
                <tr><th>Email</th><td><?= htmlspecialchars($_SESSION['email']) ?></td></tr>
                <tr><th>Course</th><td><?= htmlspecialchars($enrolment['title']) ?></td></tr>
                <tr><th>Provider</th><td><?= htmlspecialchars($enrolment['organisation_name']) ?></td></tr>
                <tr><th>Start Date</th><td><?= date('d F Y', strtotime($enrolment['start_date'])) ?></td></tr>
                <tr><th>End Date</th><td><?= date('d F Y', strtotime($enrolment['end_date'])) ?></td></tr>
                <tr><th>Mode</th><td><?= ucfirst(str_replace('_',' ',$enrolment['mode'])) ?></td></tr>
                <tr><th>Location</th><td><?= htmlspecialchars($enrolment['location']) ?></td></tr>
                <tr><td colspan="2"><hr class="my-2"></td></tr>
                <tr><th>Transaction Ref</th><td><?= htmlspecialchars($existingPayment['transaction_ref']) ?></td></tr>
                <tr><th>Payment Method</th><td><?= ucfirst(str_replace('_',' ',$existingPayment['payment_method'])) ?></td></tr>
                <tr><th>Payment Date</th><td><?= date('d F Y, H:i', strtotime($existingPayment['paid_at'])) ?></td></tr>
                <tr><td colspan="2"><hr class="my-2"></td></tr>
                <tr>
                    <th class="fs-5">Amount Paid</th>
                    <td class="fs-5 fw-700 text-ems-primary">
                        RM <?= number_format($existingPayment['amount'], 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="text-center mt-4">
            <span class="badge bg-success px-3 py-2 fs-6">
                <i class="bi bi-check-circle-fill me-1"></i>Payment Successful
            </span>
        </div>
    </div>

    <div class="text-center d-print-none">
        <button class="btn btn-outline-primary me-2" id="btnPrintReceipt">
            <i class="bi bi-printer me-2"></i>Print Receipt
        </button>
        <a href="<?= BASE_URL ?>/learner/dashboard.php" class="btn btn-primary">
            <i class="bi bi-house me-2"></i>Back to Dashboard
        </a>
    </div>

<?php else: ?>
    <!-- ── PAYMENT FORM ──────────────────────────────── -->
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Course Summary -->
        <div class="col-md-5 col-lg-4">
            <div class="ems-card p-4">
                <h5 class="mb-3"><i class="bi bi-journal-check me-2"></i>Order Summary</h5>
                <p class="fw-600 mb-1"><?= htmlspecialchars($enrolment['title']) ?></p>
                <p class="text-muted small mb-1"><?= htmlspecialchars($enrolment['organisation_name']) ?></p>
                <p class="text-muted small mb-1">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= date('d M Y', strtotime($enrolment['start_date'])) ?> –
                    <?= date('d M Y', strtotime($enrolment['end_date'])) ?>
                </p>
                <hr>
                <div class="d-flex justify-content-between fw-700 fs-5">
                    <span>Total</span>
                    <span class="text-ems-primary">
                        <?= $enrolment['fee'] > 0 ? 'RM ' . number_format($enrolment['fee'], 2) : 'Free' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="col-md-7 col-lg-8">
            <div class="ems-card p-4">
                <h5 class="mb-4"><i class="bi bi-credit-card me-2"></i>Payment Details</h5>
                <form method="POST" action="<?= BASE_URL ?>/learner/payment.php?enrolment_id=<?= $enrolmentId ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach ([
                                'credit_card'     => ['bi-credit-card',       'Credit Card'],
                                'debit_card'      => ['bi-credit-card-2-back','Debit Card'],
                                'online_transfer' => ['bi-bank',              'Online Transfer'],
                                'others'          => ['bi-wallet2',           'Others'],
                            ] as $val => [$icon, $label]): ?>
                            <div class="col-6 col-sm-3">
                                <input type="radio" class="btn-check" name="payment_method"
                                       id="pm<?= $val ?>" value="<?= $val ?>">
                                <label class="btn btn-outline-secondary w-100 py-3" for="pm<?= $val ?>">
                                    <i class="bi <?= $icon ?> d-block fs-4 mb-1"></i>
                                    <small><?= $label ?></small>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cardholderName" class="form-label">
                            Cardholder / Account Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="cardholderName"
                               name="cardholder_name"
                               value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>"
                               required>
                    </div>

                    <div class="alert alert-info mb-4" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Simulated Payment:</strong> No real payment is processed. Clicking Pay will issue an official receipt immediately.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="btnConfirmPayment">
                        <i class="bi bi-lock-fill me-2"></i>
                        Confirm Payment
                        <?php if ($enrolment['fee'] > 0): ?>
                            — RM <?= number_format($enrolment['fee'], 2) ?>
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
