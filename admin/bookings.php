<?php
$pageTitle = 'Manage Bookings';
require '../db/connection.php';
require '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = trim($_POST['status']);
    
    $st = $pdo->prepare('SELECT b.*, r.type AS room_type, r.room_number FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?');
    $st->execute([$id]);
    $booking = $st->fetch();
    
    if ($booking) {
        $prevStatus = $booking['status'];
        
        if ($status === 'Cancelled') {
            $reason = trim($_POST['cancellation_reason'] ?? 'Hotel operational cancellation');
            if (!$reason) $reason = 'Operational Maintenance & Safety Requirements';
            $refund = (float)$booking['total_amount'];
            $refundStatus = ($booking['payment_status'] === 'Paid') ? '100% Full Charges Reverted to Original Payment Source' : 'No Payment Collected';
            
            $st = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_by = 'Hotel', cancellation_reason = ?, cancellation_fee = 0.00, refund_amount = ?, refund_status = ? WHERE id = ?");
            $st->execute([$reason, $refund, $refundStatus, $id]);
            
            $cancelledBy = 'Hotel';
            $fee = 0.00;
        } else {
            $st = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
            $st->execute([$status, $id]);
            $reason = $booking['cancellation_reason'];
            $cancelledBy = $booking['cancelled_by'];
            $fee = (float)$booking['cancellation_fee'];
            $refund = (float)$booking['refund_amount'];
            $refundStatus = $booking['refund_status'];
        }
        
        emit_event(
            $pdo,
            'booking_status_updated',
            [
                'booking_id' => $id,
                'booking_reference' => $booking['booking_reference'] ?? '',
                'user_id' => (int)$booking['user_id'],
                'room_id' => (int)$booking['room_id'],
                'guest_name' => $booking['guest_name'],
                'room_type' => $booking['room_type'],
                'room_number' => $booking['room_number'],
                'check_in' => $booking['check_in'],
                'check_out' => $booking['check_out'],
                'total_amount' => (float)$booking['total_amount'],
                'previous_status' => $prevStatus,
                'status' => $status,
                'cancelled_by' => $cancelledBy ?? null,
                'cancellation_reason' => $reason ?? null,
                'cancellation_fee' => $fee ?? 0.00,
                'refund_amount' => $refund ?? 0.00,
                'refund_status' => $refundStatus ?? 'None'
            ],
            (int)$booking['user_id'],
            $id,
            (int)$booking['room_id']
        );
        flash('main', $status === 'Cancelled' ? "Booking #$id cancelled by Hotel. Charges reverted: ₹" . number_format($refund) : "Booking status updated to $status.");
    }
    redirect('bookings.php');
}

$bookings = $pdo->query('SELECT b.*, r.type, r.room_number, r.capacity, r.max_capacity, u.name AS account_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id ORDER BY b.created_at DESC')->fetchAll();
include '../includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Reservations</span>
                <h1>Manage bookings</h1>
            </div>
            <div>
                <span class="muted" id="booking-count-label"><?= count($bookings) ?> total reservations</span>
            </div>
        </div>
        <?php show_flash(); ?>
        <div class="panel table-wrap">
            <table class="table" id="admin-bookings-table">
                <thead>
                    <tr>
                        <th>Booking / Guest</th>
                        <th>Room</th>
                        <th>Stay, Guests &amp; Add-ons</th>
                        <th>Contact</th>
                        <th>Amount &amp; Payment</th>
                        <th>Status &amp; Cancellation</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="admin-bookings-tbody">
                    <?php if (!$bookings): ?>
                        <tr id="no-admin-bookings-row">
                            <td colspan="7" class="empty">No bookings found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr id="admin-booking-row-<?= $b['id'] ?>" data-booking-id="<?= $b['id'] ?>">
                            <td>
                                <?php if (!empty($b['booking_reference'])): ?>
                                    <span class="badge" style="background:#eaf4f1; color:var(--teal); font-family:monospace; font-size:0.75rem; font-weight:800; margin-bottom:4px; display:inline-block; border:1px solid #c2ded7;">
                                        <?= e($b['booking_reference']) ?>
                                    </span><br>
                                <?php endif; ?>
                                <strong><?= e($b['guest_name']) ?></strong>
                                <?php if ($b['account_name'] && $b['account_name'] !== $b['guest_name']): ?>
                                    <br><small class="muted">Acc: <?= e($b['account_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($b['type']) ?></strong><br>
                                <small class="muted">Room <?= e($b['room_number']) ?></small>
                            </td>
                            <td>
                                <div><strong>In:</strong> <?= e($b['check_in']) ?> <small class="muted">(<?= e($b['check_in_time'] ?: '02:00 PM') ?>)</small></div>
                                <div><strong>Out:</strong> <?= e($b['check_out']) ?> <small class="muted">(<?= e($b['check_out_time'] ?: '11:00 AM') ?>)</small></div>
                                <div style="margin-top:4px;">
                                    <strong>👥 <?= e($b['guests']) ?> Guest<?= $b['guests'] > 1 ? 's' : '' ?></strong>
                                    <?php if (!empty($b['extra_mattress']) && (int)$b['extra_mattress'] > 0): ?>
                                        <br><span class="badge" style="background:#fef3e2; color:#92400e; font-size:0.72rem; border:1px solid #fde68a; display:inline-block; margin-top:2px;">
                                            🛏️ +<?= (int)$b['extra_mattress'] ?> Mattress (+₹<?= number_format($b['extra_mattress_cost']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <small><?= e($b['guest_email']) ?></small><br>
                                <small><?= e($b['guest_phone']) ?></small>
                            </td>
                            <td>
                                <strong>₹<?= number_format($b['total_amount']) ?></strong><br>
                                <small class="muted" style="display:block; margin-bottom:3px;">incl. 12% GST</small>
                                <span class="badge" style="font-size:0.7rem; <?= ($b['payment_status'] === 'Paid') ? 'background:#e6f5ee; color:#197357;' : 'background:#fff8e7; color:#8b642e;' ?>">
                                    <?= e($b['payment_method'] ?: 'Pay on Arrival') ?> &bull; <?= e($b['payment_status'] ?: 'Pending') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= strtolower($b['status']) ?>" id="admin-badge-<?= $b['id'] ?>">
                                    <?= e($b['status']) ?>
                                </span>

                                <?php if ($b['status'] === 'Cancelled'): ?>
                                    <?php if ($b['cancelled_by'] === 'Customer'): ?>
                                        <div style="margin-top:6px; padding:6px 8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; font-size:0.74rem; text-align:left;">
                                            <strong style="color:#475569;">By Customer</strong><br>
                                            <?php if ($b['cancellation_reason']): ?><span><?= e($b['cancellation_reason']) ?></span><br><?php endif; ?>
                                            <?php if ((float)$b['cancellation_fee'] > 0): ?><span style="color:#b91c1c;">Fee: ₹<?= number_format($b['cancellation_fee']) ?> (10%)</span><br><?php endif; ?>
                                            <span style="color:#15803d; font-weight:700;">Reverted: ₹<?= number_format($b['refund_amount']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top:6px; padding:6px 8px; background:#fff1f2; border:1px solid #fecdd3; border-radius:6px; font-size:0.74rem; text-align:left;">
                                            <strong style="color:#be123c;">⚠️ By Hotel:</strong> <?= e($b['cancellation_reason'] ?: 'Operational Maintenance & Safety Requirements') ?><br>
                                            <span style="color:#15803d; font-weight:700;">Reverted: ₹<?= number_format($b['refund_amount'] ?: $b['total_amount']) ?> (100%)</span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="status-update-form" id="form-booking-<?= $b['id'] ?>" data-booking-id="<?= $b['id'] ?>">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="cancellation_reason" id="reason-input-<?= $b['id'] ?>" value="">
                                    <select name="status" class="booking-status-select" 
                                        data-booking-id="<?= $b['id'] ?>"
                                        data-ref="<?= e($b['booking_reference']) ?>"
                                        data-guest="<?= e($b['guest_name']) ?>"
                                        data-room="<?= e($b['type']) ?> (Room <?= e($b['room_number']) ?>)"
                                        data-total="<?= (float)$b['total_amount'] ?>"
                                        data-prev="<?= e($b['status']) ?>"
                                    >
                                        <option value="Pending" <?= $b['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Confirmed" <?= $b['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed (Approve)</option>
                                        <option value="Cancelled" <?= $b['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        <option value="Completed" <?= $b['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <noscript><button type="submit" class="button small">Save</button></noscript>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Hotel Cancellation Modal for Admin -->
<div class="payment-modal-overlay" id="hotel-cancel-modal-overlay">
    <div class="payment-modal" style="max-width:500px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:1.25rem; color:#be123c; display:flex; align-items:center; gap:8px;">
                <span>⚠️ Hotel Cancellation Notice</span>
            </h3>
            <button type="button" class="payment-close-btn" id="close-hotel-cancel-modal" aria-label="Close modal">&times;</button>
        </div>

        <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:0.85rem; color:#881337; line-height:1.45;">
            <strong>Mandatory Hotel Policy:</strong> When the Hotel cancels a customer reservation, <strong>100% full charges are reverted</strong> with zero cancellation fee. A clear reason must be documented for the guest.
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:0.85rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span class="muted">Booking Reference:</span>
                <strong id="admin-modal-ref" style="font-family:monospace; color:var(--teal);"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span class="muted">Guest Name:</span>
                <strong id="admin-modal-guest"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span class="muted">Room:</span>
                <strong id="admin-modal-room"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; margin-top:4px;">
                <span class="muted">Charges Reverted:</span>
                <strong id="admin-modal-reverted" style="color:#15803d; font-size:0.95rem;"></strong>
            </div>
        </div>

        <div class="field" style="margin-bottom:16px;">
            <label for="hotel_reason_preset" style="font-weight:600; font-size:0.88rem; margin-bottom:6px; display:block;">Select Hotel Reason <span style="color:var(--danger)">*</span></label>
            <select id="hotel_reason_preset" style="width:100%; margin-bottom:8px;">
                <option value="Emergency Room Maintenance & Plumbing Repairs">Emergency Room Maintenance &amp; Plumbing Repairs</option>
                <option value="Unexpected Power / Infrastructure Outage">Unexpected Power / Infrastructure Outage</option>
                <option value="Severe Weather / Natural Event (Force Majeure)">Severe Weather / Natural Event (Force Majeure)</option>
                <option value="Deep Sanitization & Health Safety Protocol">Deep Sanitization &amp; Health Safety Protocol</option>
                <option value="Room Out of Service (Inventory Realignment)">Room Out of Service (Inventory Realignment)</option>
                <option value="Other">Other (Custom Reason)</option>
            </select>
            <textarea id="hotel_reason_custom" rows="2" placeholder="Describe the specific hotel reason clearly..." style="display:none; width:100%; font-size:0.86rem;"></textarea>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="button alt" id="dismiss-hotel-cancel-modal">Abort</button>
            <button type="button" class="button danger" id="confirm-hotel-cancel-btn">Confirm Hotel Cancellation</button>
        </div>
    </div>
</div>

<script>
(function() {
    const modalOverlay = document.getElementById('hotel-cancel-modal-overlay');
    const closeBtn = document.getElementById('close-hotel-cancel-modal');
    const dismissBtn = document.getElementById('dismiss-hotel-cancel-modal');
    const confirmBtn = document.getElementById('confirm-hotel-cancel-btn');
    const refEl = document.getElementById('admin-modal-ref');
    const guestEl = document.getElementById('admin-modal-guest');
    const roomEl = document.getElementById('admin-modal-room');
    const revertedEl = document.getElementById('admin-modal-reverted');
    const reasonPreset = document.getElementById('hotel_reason_preset');
    const reasonCustom = document.getElementById('hotel_reason_custom');

    let activeSelect = null;
    let activeBookingId = null;

    reasonPreset.addEventListener('change', function() {
        if (this.value === 'Other') {
            reasonCustom.style.display = 'block';
            reasonCustom.focus();
        } else {
            reasonCustom.style.display = 'none';
        }
    });

    document.querySelectorAll('.booking-status-select').forEach(sel => {
        sel.addEventListener('change', function() {
            const newStatus = this.value;
            const prevStatus = this.dataset.prev;
            const bId = this.dataset.bookingId;

            if (newStatus === 'Cancelled') {
                activeSelect = this;
                activeBookingId = bId;

                refEl.textContent = this.dataset.ref;
                guestEl.textContent = this.dataset.guest;
                roomEl.textContent = this.dataset.room;
                const total = parseFloat(this.dataset.total) || 0;
                revertedEl.textContent = '₹' + total.toLocaleString('en-IN') + ' (100% Full Refund)';

                reasonPreset.value = 'Emergency Room Maintenance & Plumbing Repairs';
                reasonCustom.value = '';
                reasonCustom.style.display = 'none';

                modalOverlay.classList.add('active');
            }
        });
    });

    function abortCancel() {
        if (activeSelect) {
            activeSelect.value = activeSelect.dataset.prev;
        }
        modalOverlay.classList.remove('active');
        activeSelect = null;
        activeBookingId = null;
    }

    closeBtn.addEventListener('click', abortCancel);
    dismissBtn.addEventListener('click', abortCancel);
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) abortCancel();
    });

    confirmBtn.addEventListener('click', async function() {
        if (!activeBookingId) return;

        let finalReason = reasonPreset.value;
        if (finalReason === 'Other') {
            finalReason = reasonCustom.value.trim() || 'Operational Maintenance & Safety Requirements';
        }

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';

        try {
            const formData = new FormData();
            formData.append('id', activeBookingId);
            formData.append('status', 'Cancelled');
            formData.append('cancelled_by', 'Hotel');
            formData.append('cancellation_reason', finalReason);

            const res = await fetch('../api/update_booking_status.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                window.location.reload();
                return;
            }
        } catch (err) {
            console.warn('AJAX cancellation failed, falling back to form submit', err);
        }

        const hiddenInput = document.getElementById('reason-input-' + activeBookingId);
        if (hiddenInput) {
            hiddenInput.value = finalReason;
        }

        const form = document.getElementById('form-booking-' + activeBookingId);
        if (form) {
            form.submit();
        }
    });
})();
</script>

<?php include '../includes/footer.php'; ?>

