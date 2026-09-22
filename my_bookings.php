<?php
$pageTitle = 'My Bookings';
require 'db/connection.php';
require 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $cancelReason = trim($_POST['cancel_reason'] ?? 'Customer requested cancellation');
    if (!$cancelReason) $cancelReason = 'Change of plans';

    $st = $pdo->prepare("SELECT b.*, r.type AS room_type, r.room_number FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ? AND b.user_id = ? AND b.status IN ('Pending','Confirmed') AND b.check_in >= CURDATE()");
    $st->execute([$cancelId, $_SESSION['user']['id']]);
    $b = $st->fetch();
    
    if ($b) {
        $checkInDateTime = strtotime($b['check_in'] . ' 14:00:00');
        $hoursToCheckIn = ($checkInDateTime - time()) / 3600;
        $isPaid = ($b['payment_status'] === 'Paid');
        $total = (float)$b['total_amount'];

        if (!$isPaid) {
            $fee = 0.00;
            $refund = 0.00;
            $refundStatus = 'No payment was collected (Reservation voided)';
        } elseif ($hoursToCheckIn >= 48) {
            $fee = 0.00;
            $refund = $total;
            $refundStatus = '100% Refund Initiated (Reverting in 2-3 business days)';
        } else {
            $fee = round($total * 0.10, 2);
            $refund = round($total - $fee, 2);
            $refundStatus = '90% Refund Initiated (10% Late cancellation fee deducted)';
        }

        $st = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_by = 'Customer', cancellation_reason = ?, cancellation_fee = ?, refund_amount = ?, refund_status = ? WHERE id = ?");
        $st->execute([$cancelReason, $fee, $refund, $refundStatus, $cancelId]);

        emit_event(
            $pdo,
            'booking_cancelled',
            [
                'booking_id' => $cancelId,
                'booking_reference' => $b['booking_reference'] ?? '',
                'user_id' => (int)$_SESSION['user']['id'],
                'guest_name' => $_SESSION['user']['name'],
                'room_type' => $b['room_type'],
                'room_number' => $b['room_number'],
                'status' => 'Cancelled',
                'cancelled_by' => 'Customer',
                'cancellation_reason' => $cancelReason,
                'cancellation_fee' => $fee,
                'refund_amount' => $refund,
                'refund_status' => $refundStatus
            ],
            (int)$_SESSION['user']['id'],
            $cancelId,
            (int)$b['room_id']
        );
        flash('main', "Booking {$b['booking_reference']} cancelled. Charges reverted: ₹" . number_format($refund) . ($fee > 0 ? " (Fee: ₹" . number_format($fee) . ")" : ""));
    } else {
        flash('main', 'This booking can no longer be cancelled.', 'error');
    }
    redirect('my_bookings.php');
}

$st = $pdo->prepare('SELECT b.*, r.room_number, r.type FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.user_id = ? ORDER BY b.created_at DESC');
$st->execute([$_SESSION['user']['id']]);
$bookings = $st->fetchAll();

include 'includes/header.php';
?>
<section class="section">
    <div class="container">
        <span class="eyebrow">Your account</span>
        <h1>My bookings</h1>
        <?php show_flash(); ?>

        <!-- Cancellation Policy Banner for Guest Awareness -->
<style>
/* ==========================================================
   My Bookings Responsive Cards & Modal Optimization
   ========================================================== */
.bookings-desktop-table {
    display: block;
}
.bookings-mobile-cards {
    display: none;
}

.cancellation-policy-banner {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.cpb-left {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1 1 300px;
}
.cpb-icon {
    font-size: 1.4rem;
    line-height: 1;
    flex-shrink: 0;
}
.cpb-text {
    font-size: 0.86rem;
    color: #14532d;
    line-height: 1.45;
}

/* Mobile Booking Card Styling */
.mobile-booking-card {
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 18px 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.mb-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}
.mb-ref-badge {
    display: inline-block;
    font-family: monospace;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--teal);
    background: #eaf4f1;
    border: 1px solid #c2ded7;
    padding: 2px 8px;
    border-radius: 6px;
    margin-bottom: 4px;
}
.mb-room-title {
    margin: 0 0 2px;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--ink);
}
.mb-room-sub {
    font-size: 0.8rem;
    color: var(--muted);
}
.mb-dates-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.mb-date-box {
    background: #f8faf9;
    border: 1px solid #e2ece9;
    border-radius: 10px;
    padding: 9px 12px;
}
.mb-date-box.check-in {
    border-left: 3.5px solid var(--teal);
}
.mb-date-box.check-out {
    border-left: 3.5px solid var(--gold);
}
.mb-date-lbl {
    display: block;
    font-size: 0.68rem;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--muted);
    margin-bottom: 2px;
}
.mb-date-val {
    display: block;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ink);
}
.mb-time-val {
    font-size: 0.74rem;
    color: var(--muted);
}
.mb-chips-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.mb-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #334155;
}
.mb-chip.mattress {
    background: #fef3e2;
    color: #92400e;
    border: 1px solid #fde68a;
}
.mb-payment-strip {
    background: #fafcfb;
    border: 1px solid #e5edea;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.mb-pay-lbl {
    display: block;
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--muted);
}
.mb-price-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--teal-dark);
}
.mb-cancel-callout {
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 0.78rem;
    text-align: left;
    line-height: 1.45;
}
.mb-cancel-callout.hotel {
    background: #fff1f2;
    border: 1px solid #fecdd3;
}
.mb-cancel-callout.customer {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.mb-cancel-title {
    font-weight: 700;
    margin-bottom: 4px;
}
.mb-cancel-title.hotel {
    color: #be123c;
    display: flex;
    align-items: center;
    gap: 5px;
}
.mb-cancel-title.customer {
    color: #475569;
}
.mb-cancel-reverted {
    color: #15803d;
    font-weight: 700;
    margin-top: 3px;
    font-size: 0.82rem;
}
.mb-cancel-status {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 2px;
}
.mb-action-footer {
    margin-top: 4px;
}

/* Mobile Breakpoint Transitions */
@media (max-width: 768px) {
    .bookings-desktop-table {
        display: none !important;
    }
    .bookings-mobile-cards {
        display: flex !important;
        flex-direction: column;
        gap: 14px;
    }
}

@media (max-width: 540px) {
    .cancellation-policy-banner {
        padding: 12px 14px;
        gap: 10px;
    }
    .cpb-badge {
        align-self: flex-start;
        margin-left: 36px;
    }
    #customer-cancel-modal-overlay {
        padding: 0;
        align-items: flex-end;
    }
    #customer-cancel-modal-overlay .customer-cancel-modal {
        max-width: 100% !important;
        width: 100% !important;
        border-radius: 20px 20px 0 0 !important;
        padding: 20px 18px 30px !important;
        max-height: 92vh;
        overflow-y: auto;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    #customer-cancel-modal-overlay.active .customer-cancel-modal {
        transform: translateY(0);
    }
    .modal-btn-row {
        flex-direction: column-reverse !important;
        gap: 10px !important;
    }
    .modal-btn-row .button {
        width: 100% !important;
        min-height: 44px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}
</style>

<section class="section">
    <div class="container">
        <span class="eyebrow">Your account</span>
        <h1>My bookings</h1>
        <?php show_flash(); ?>

        <!-- Cancellation Policy Banner for Guest Awareness -->
        <div class="cancellation-policy-banner">
            <div class="cpb-left">
                <span class="cpb-icon">🛡️</span>
                <div class="cpb-text">
                    <strong>Haven Cancellation &amp; Refund Guarantee:</strong>
                    Free cancellation with 100% refund up to 48 hours before check-in. Within 48 hours: 10% fee (90% refunded). If cancelled by hotel: 100% full charges reverted immediately.
                </div>
            </div>
            <span class="badge cpb-badge" style="background:#dcfce7; color:#15803d; font-weight:700;">Hassle-Free Refunds</span>
        </div>

        <!-- 1. DESKTOP VIEW: High Density Data Table (Screens > 768px) -->
        <div class="panel table-wrap bookings-desktop-table">
            <table class="table" id="my-bookings-table">
                <thead>
                    <tr>
                        <th>Booking / Room</th>
                        <th>Dates &amp; Expected Timings</th>
                        <th>Guests &amp; Add-ons</th>
                        <th>Total &amp; Payment</th>
                        <th>Status &amp; Cancellation Info</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="my-bookings-tbody">
                    <?php if (!$bookings): ?>
                        <tr id="no-bookings-row">
                            <td colspan="6" class="empty">You have no bookings yet. <a href="rooms.php">Explore rooms →</a></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr id="booking-row-<?= $b['id'] ?>" data-booking-id="<?= $b['id'] ?>">
                            <td>
                                <?php if (!empty($b['booking_reference'])): ?>
                                    <span class="badge" style="background:#eaf4f1; color:var(--teal); font-family:monospace; font-size:0.76rem; font-weight:800; margin-bottom:4px; display:inline-block; border:1px solid #c2ded7;">
                                        <?= e($b['booking_reference']) ?>
                                    </span><br>
                                <?php endif; ?>
                                <strong><?= e($b['type']) ?></strong><br>
                                <small class="muted">Room <?= e($b['room_number']) ?></small>
                            </td>
                            <td>
                                <div><strong>In:</strong> <?= e($b['check_in']) ?> <small class="muted">(<?= e($b['check_in_time'] ?: '02:00 PM') ?>)</small></div>
                                <div><strong>Out:</strong> <?= e($b['check_out']) ?> <small class="muted">(<?= e($b['check_out_time'] ?: '11:00 AM') ?>)</small></div>
                            </td>
                            <td>
                                <strong><?= e($b['guests']) ?> Guest<?= $b['guests'] > 1 ? 's' : '' ?></strong>
                                <?php if (!empty($b['extra_mattress']) && (int)$b['extra_mattress'] > 0): ?>
                                    <div style="margin-top:4px;">
                                        <span class="badge" style="background:#fef3e2; color:#92400e; font-size:0.72rem; border:1px solid #fde68a; display:inline-block;">
                                            🛏️ +<?= (int)$b['extra_mattress'] ?> Extra Mattress (+₹<?= number_format($b['extra_mattress_cost']) ?>)
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>₹<?= number_format($b['total_amount']) ?></strong><br>
                                <small class="muted" style="display:block; margin-bottom:3px;">incl. 12% GST</small>
                                <span class="badge" style="font-size:0.7rem; <?= ($b['payment_status'] === 'Paid') ? 'background:#e6f5ee; color:#197357;' : 'background:#fff8e7; color:#8b642e;' ?>">
                                    <?= e($b['payment_method'] ?: 'Pay on Arrival') ?> &bull; <?= e($b['payment_status'] ?: 'Pending') ?>
                                </span>

                                <?php if ($b['status'] === 'Cancelled' && (float)$b['refund_amount'] > 0): ?>
                                    <div style="margin-top:6px; padding:6px 8px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; font-size:0.75rem;">
                                        <span style="color:#047857; font-weight:700;">Reverted: ₹<?= number_format($b['refund_amount']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= strtolower($b['status']) ?>" id="status-badge-<?= $b['id'] ?>">
                                    <?= e($b['status']) ?>
                                </span>

                                <div id="desktop-cancel-callout-<?= $b['id'] ?>">
                                <?php if ($b['status'] === 'Cancelled'): ?>
                                    <?php if ($b['cancelled_by'] === 'Customer'): ?>
                                        <div style="margin-top:8px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:0.78rem; text-align:left;">
                                            <div style="color:#475569; font-weight:700; margin-bottom:3px;">
                                                Cancelled by You
                                            </div>
                                            <?php if ($b['cancellation_reason']): ?>
                                                <div style="color:#334155; margin-bottom:3px;">
                                                    <strong>Reason:</strong> <?= e($b['cancellation_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ((float)$b['cancellation_fee'] > 0): ?>
                                                <div style="color:#b91c1c; font-size:0.74rem;">
                                                    Fee: ₹<?= number_format($b['cancellation_fee']) ?> (10% Late cancellation)
                                                </div>
                                            <?php endif; ?>
                                            <div style="color:#15803d; font-weight:700; margin-top:2px;">
                                                Charges Reverted: ₹<?= number_format($b['refund_amount']) ?>
                                            </div>
                                            <div style="font-size:0.72rem; color:#64748b; margin-top:2px;">
                                                <?= e($b['refund_status']) ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top:8px; padding:10px 12px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; font-size:0.78rem; text-align:left;">
                                            <div style="color:#be123c; font-weight:700; display:flex; align-items:center; gap:5px; margin-bottom:4px;">
                                                <span>⚠️ Cancelled by Hotel</span>
                                            </div>
                                            <div style="color:#4c0519; margin-bottom:4px;">
                                                <strong>Hotel Reason:</strong> <?= e($b['cancellation_reason'] ?: 'Operational Maintenance & Safety Requirements') ?>
                                            </div>
                                            <div style="color:#15803d; font-weight:700; margin-bottom:2px;">
                                                Charges Reverted: ₹<?= number_format($b['refund_amount'] ?: $b['total_amount']) ?> (100% Full Refund)
                                            </div>
                                            <div style="font-size:0.72rem; color:#64748b;">
                                                <?= e($b['refund_status'] ?: '100% Full Charges Reverted to Original Payment Source') ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td id="action-cell-<?= $b['id'] ?>">
                                <?php if (in_array($b['status'], ['Pending', 'Confirmed']) && $b['check_in'] >= date('Y-m-d')): ?>
                                    <button class="button danger small cancel-booking-btn" type="button"
                                        data-booking-id="<?= $b['id'] ?>"
                                        data-ref="<?= e($b['booking_reference']) ?>"
                                        data-room="<?= e($b['type']) ?> (Room <?= e($b['room_number']) ?>)"
                                        data-checkin="<?= e($b['check_in']) ?>"
                                        data-total="<?= (float)$b['total_amount'] ?>"
                                        data-paid="<?= ($b['payment_status'] === 'Paid') ? '1' : '0' ?>"
                                    >Cancel</button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. MOBILE VIEW: Touch-Friendly Responsive Cards (Screens <= 768px) -->
        <div class="bookings-mobile-cards">
            <?php if (!$bookings): ?>
                <div class="panel" style="text-align:center; padding:36px 20px;">
                    <p class="muted" style="margin-bottom:14px;">You have no bookings yet.</p>
                    <a href="rooms.php" class="button">Explore Rooms →</a>
                </div>
            <?php endif; ?>

            <?php foreach ($bookings as $b): ?>
                <div class="mobile-booking-card" id="mobile-card-<?= $b['id'] ?>">
                    <!-- Card Top Header -->
                    <div class="mb-card-header">
                        <div>
                            <?php if (!empty($b['booking_reference'])): ?>
                                <span class="mb-ref-badge"><?= e($b['booking_reference']) ?></span>
                            <?php endif; ?>
                            <h3 class="mb-room-title"><?= e($b['type']) ?></h3>
                            <span class="mb-room-sub">Room <?= e($b['room_number']) ?></span>
                        </div>
                        <div>
                            <span class="badge <?= strtolower($b['status']) ?>"><?= e($b['status']) ?></span>
                        </div>
                    </div>

                    <!-- Stay Dates & Expected Times -->
                    <div class="mb-dates-grid">
                        <div class="mb-date-box check-in">
                            <span class="mb-date-lbl">Check-In</span>
                            <span class="mb-date-val"><?= e($b['check_in']) ?></span>
                            <span class="mb-time-val"><?= e($b['check_in_time'] ?: '02:00 PM') ?></span>
                        </div>
                        <div class="mb-date-box check-out">
                            <span class="mb-date-lbl">Check-Out</span>
                            <span class="mb-date-val"><?= e($b['check_out']) ?></span>
                            <span class="mb-time-val"><?= e($b['check_out_time'] ?: '11:00 AM') ?></span>
                        </div>
                    </div>

                    <!-- Guests & Add-ons Badges -->
                    <div class="mb-chips-row">
                        <span class="mb-chip">👥 <?= e($b['guests']) ?> Guest<?= $b['guests'] > 1 ? 's' : '' ?></span>
                        <?php if (!empty($b['extra_mattress']) && (int)$b['extra_mattress'] > 0): ?>
                            <span class="mb-chip mattress">
                                🛏️ +<?= (int)$b['extra_mattress'] ?> Mattress (+₹<?= number_format($b['extra_mattress_cost']) ?>)
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Summary Strip -->
                    <div class="mb-payment-strip">
                        <div>
                            <span class="mb-pay-lbl">Total Payable</span>
                            <div class="mb-price-val">₹<?= number_format($b['total_amount']) ?> <small class="muted" style="font-size:0.75rem; font-weight:normal;">(incl. 12% GST)</small></div>
                        </div>
                        <div>
                            <span class="badge" style="font-size:0.72rem; <?= ($b['payment_status'] === 'Paid') ? 'background:#e6f5ee; color:#197357;' : 'background:#fff8e7; color:#8b642e;' ?>">
                                <?= e($b['payment_method'] ?: 'Pay on Arrival') ?> &bull; <?= e($b['payment_status'] ?: 'Pending') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Reverted Amount Chip if Cancelled -->
                    <?php if ($b['status'] === 'Cancelled' && (float)$b['refund_amount'] > 0): ?>
                        <div style="padding:7px 10px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; font-size:0.76rem; display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#047857; font-weight:700;">Charges Reverted:</span>
                            <strong style="color:#047857; font-size:0.86rem;">₹<?= number_format($b['refund_amount']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- Cancellation Reason Callout -->
                    <div id="mobile-cancel-callout-<?= $b['id'] ?>">
                    <?php if ($b['status'] === 'Cancelled'): ?>
                        <?php if ($b['cancelled_by'] === 'Customer'): ?>
                            <div class="mb-cancel-callout customer">
                                <div class="mb-cancel-title customer">
                                    Cancelled by You
                                </div>
                                <?php if ($b['cancellation_reason']): ?>
                                    <div style="color:#334155; margin-bottom:3px;">
                                        <strong>Reason:</strong> <?= e($b['cancellation_reason']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ((float)$b['cancellation_fee'] > 0): ?>
                                    <div style="color:#b91c1c; font-size:0.74rem;">
                                        Fee: ₹<?= number_format($b['cancellation_fee']) ?> (10% Late cancellation)
                                    </div>
                                <?php endif; ?>
                                <div class="mb-cancel-reverted">
                                    Charges Reverted: ₹<?= number_format($b['refund_amount']) ?>
                                </div>
                                <div class="mb-cancel-status">
                                    <?= e($b['refund_status']) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-cancel-callout hotel">
                                <div class="mb-cancel-title hotel">
                                    <span>⚠️ Cancelled by Hotel</span>
                                </div>
                                <div style="color:#4c0519; margin-bottom:3px;">
                                    <strong>Hotel Reason:</strong> <?= e($b['cancellation_reason'] ?: 'Operational Maintenance & Safety Requirements') ?>
                                </div>
                                <div class="mb-cancel-reverted">
                                    Charges Reverted: ₹<?= number_format($b['refund_amount'] ?: $b['total_amount']) ?> (100% Full Refund)
                                </div>
                                <div class="mb-cancel-status">
                                    <?= e($b['refund_status'] ?: '100% Full Charges Reverted to Original Payment Source') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>

                    <!-- Action Footer -->
                    <div id="mobile-action-<?= $b['id'] ?>">
                    <?php if (in_array($b['status'], ['Pending', 'Confirmed']) && $b['check_in'] >= date('Y-m-d')): ?>
                        <div class="mb-action-footer">
                            <button class="button danger small cancel-booking-btn" type="button"
                                data-booking-id="<?= $b['id'] ?>"
                                data-ref="<?= e($b['booking_reference']) ?>"
                                data-room="<?= e($b['type']) ?> (Room <?= e($b['room_number']) ?>)"
                                data-checkin="<?= e($b['check_in']) ?>"
                                data-total="<?= (float)$b['total_amount'] ?>"
                                data-paid="<?= ($b['payment_status'] === 'Paid') ? '1' : '0' ?>"
                                style="width:100%; min-height:42px; font-size:0.88rem;"
                            >Cancel Reservation</button>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- Interactive Customer Cancellation Modal -->
<div class="payment-modal-overlay" id="customer-cancel-modal-overlay">
    <div class="payment-modal customer-cancel-modal" style="max-width:480px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:1.25rem; color:#991b1b; display:flex; align-items:center; gap:8px;">
                <span>Cancel Reservation</span>
            </h3>
            <button type="button" class="payment-close-btn" id="close-cancel-modal" aria-label="Close modal">&times;</button>
        </div>

        <form method="post" id="customer-cancel-form">
            <input type="hidden" name="cancel_id" id="modal-cancel-id" value="">

            <div style="background:#fafafa; border:1px solid #e5e5e5; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:0.86rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span class="muted">Booking Reference:</span>
                    <strong id="modal-cancel-ref" style="font-family:monospace; color:var(--teal);"></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span class="muted">Room:</span>
                    <strong id="modal-cancel-room"></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span class="muted">Check-in Date:</span>
                    <strong id="modal-cancel-checkin"></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #ddd; padding-top:6px; margin-top:4px;">
                    <span class="muted">Booking Amount:</span>
                    <strong id="modal-cancel-total" style="color:var(--ink);"></strong>
                </div>
            </div>

            <!-- Policy & Charges Reverted Calculation Box -->
            <div id="modal-cancel-policy-box" style="padding:12px 14px; border-radius:10px; margin-bottom:16px; font-size:0.84rem; line-height:1.45;">
                <div id="modal-cancel-policy-text"></div>
            </div>

            <div class="field" style="margin-bottom:18px;">
                <label for="cancel_reason" style="font-weight:600; font-size:0.88rem; margin-bottom:6px; display:block;">Reason for Cancellation (Optional)</label>
                <select name="cancel_reason" id="cancel_reason" style="width:100%; margin-bottom:8px;">
                    <option value="Change of travel plans">Change of travel plans</option>
                    <option value="Found alternative accommodation">Found alternative accommodation</option>
                    <option value="Personal emergency / Health reasons">Personal emergency / Health reasons</option>
                    <option value="Transportation / Flight delay">Transportation / Flight delay</option>
                    <option value="Booked by mistake">Booked by mistake</option>
                    <option value="Other">Other</option>
                </select>
                <input type="text" id="cancel_reason_other" placeholder="Specify other reason..." style="display:none; width:100%; margin-top:6px;">
            </div>

            <div class="modal-btn-row" style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="button alt" id="dismiss-cancel-modal">Keep Reservation</button>
                <button type="submit" class="button danger" id="confirm-cancel-submit-btn">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modalOverlay = document.getElementById('customer-cancel-modal-overlay');
    const closeBtn = document.getElementById('close-cancel-modal');
    const dismissBtn = document.getElementById('dismiss-cancel-modal');
    const cancelForm = document.getElementById('customer-cancel-form');
    const cancelIdInput = document.getElementById('modal-cancel-id');
    const refEl = document.getElementById('modal-cancel-ref');
    const roomEl = document.getElementById('modal-cancel-room');
    const checkinEl = document.getElementById('modal-cancel-checkin');
    const totalEl = document.getElementById('modal-cancel-total');
    const policyBox = document.getElementById('modal-cancel-policy-box');
    const policyText = document.getElementById('modal-cancel-policy-text');
    const reasonSelect = document.getElementById('cancel_reason');
    const reasonOther = document.getElementById('cancel_reason_other');

    reasonSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
            reasonOther.style.display = 'block';
            reasonOther.focus();
        } else {
            reasonOther.style.display = 'none';
        }
    });

    cancelForm.addEventListener('submit', function(e) {
        if (reasonSelect.value === 'Other' && reasonOther.value.trim()) {
            const hiddenReason = document.createElement('input');
            hiddenReason.type = 'hidden';
            hiddenReason.name = 'cancel_reason';
            hiddenReason.value = reasonOther.value.trim();
            this.appendChild(hiddenReason);
        }
    });

    document.querySelectorAll('.cancel-booking-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const bId = this.dataset.bookingId;
            const ref = this.dataset.ref;
            const room = this.dataset.room;
            const checkin = this.dataset.checkin;
            const total = parseFloat(this.dataset.total) || 0;
            const isPaid = this.dataset.paid === '1';

            cancelIdInput.value = bId;
            refEl.textContent = ref;
            roomEl.textContent = room;
            checkinEl.textContent = checkin;
            totalEl.textContent = '₹' + total.toLocaleString('en-IN');

            // Calculate hours to check-in (assume 14:00 PM check-in)
            const checkInDate = new Date(checkin + 'T14:00:00');
            const now = new Date();
            const diffHours = (checkInDate - now) / (1000 * 60 * 60);

            if (!isPaid) {
                policyBox.style.background = '#f8fafc';
                policyBox.style.border = '1px solid #cbd5e1';
                policyText.innerHTML = `
                    <strong style="color:#334155; display:block; margin-bottom:4px;">No Payment Collected</strong>
                    Since this was a 'Pay on Arrival' booking, zero charges were collected. The reservation will be cancelled with no fees.
                `;
            } else if (diffHours >= 48) {
                policyBox.style.background = '#f0fdf4';
                policyBox.style.border = '1px solid #bbf7d0';
                policyText.innerHTML = `
                    <div style="display:flex; align-items:center; gap:6px; color:#15803d; font-weight:700; margin-bottom:4px;">
                        <span>✓ Free Cancellation Applied (&ge; 48 hours to check-in)</span>
                    </div>
                    <div style="color:#166534;">
                        Cancellation Fee: <strong>₹0 (0%)</strong><br>
                        <strong>Charges Reverted: ₹${total.toLocaleString('en-IN')} (100% Full Refund)</strong><br>
                        <small style="color:#64748b;">The entire payment will be reverted to your original payment method in 2-3 business days.</small>
                    </div>
                `;
            } else {
                const fee = Math.round(total * 0.10);
                const refund = total - fee;
                policyBox.style.background = '#fffbeb';
                policyBox.style.border = '1px solid #fde68a';
                policyText.innerHTML = `
                    <div style="display:flex; align-items:center; gap:6px; color:#b45309; font-weight:700; margin-bottom:4px;">
                        <span>⚠️ Late Cancellation Policy (&lt; 48 hours to check-in)</span>
                    </div>
                    <div style="color:#92400e;">
                        Cancellation Fee (10%): <strong>₹${fee.toLocaleString('en-IN')}</strong><br>
                        <strong>Charges Reverted (90%): ₹${refund.toLocaleString('en-IN')}</strong><br>
                        <small style="color:#64748b;">₹${refund.toLocaleString('en-IN')} will be reverted to your original payment method in 2-3 business days.</small>
                    </div>
                `;
            }

            modalOverlay.classList.add('active');
        });
    });

    function closeModal() {
        modalOverlay.classList.remove('active');
    }

    closeBtn.addEventListener('click', closeModal);
    dismissBtn.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) closeModal();
    });
})();
</script>

<?php include 'includes/footer.php'; ?>

