<?php
$pageTitle = 'Book your stay';
require 'db/connection.php';
require 'includes/functions.php';
require_login();

$roomId = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'Available'");
$st->execute([$roomId]);
$room = $st->fetch();
if (!$room) {
    redirect('rooms.php');
}
$roomGallery = room_gallery_list($room);

$user = $_SESSION['user'];
$error = '';
$gstPercent = 12; // 12% GST lodging tax

// Fallback direct POST handler if JavaScript is disabled
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $in = trim($_POST['check_in'] ?? '');
    $out = trim($_POST['check_out'] ?? '');
    $checkInTime = trim($_POST['check_in_time'] ?? '02:00 PM (Standard)');
    $checkOutTime = trim($_POST['check_out_time'] ?? '11:00 AM (Standard)');
    $guests = (int)($_POST['guests'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'Pay on Arrival');
    $digits = preg_replace('/\D/', '', $phone);
    $today = date('Y-m-d');

    $baseCapacity = (int)$room['capacity'];
    $maxCapacity = (int)($room['max_capacity'] ?? $baseCapacity);
    $mattressRate = (float)($room['extra_mattress_rate'] ?? 800.00);

    if (!$guestName) {
        $error = 'Please enter the guest full name.';
    } elseif (!$guestEmail || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid contact email address.';
    } elseif (strlen($digits) < 10 || strlen($digits) > 15) {
        $error = 'Please enter a valid real phone number (10 to 15 digits).';
    } elseif (!$in || !$out || $in < $today || $out <= $in || $guests < 1 || $guests > $maxCapacity) {
        $error = "Please enter valid dates and a guest count within maximum room capacity ($maxCapacity guests).";
    } elseif (overlap_exists($pdo, $roomId, $in, $out)) {
        $error = 'This room is already booked for some or all of those dates. Please choose another range.';
    } else {
        // Calculate nights, base amount, extra mattress, and GST
        $nights = (new DateTime($in))->diff(new DateTime($out))->days;
        $roomBaseAmount = $nights * (float)$room['price'];
        $extraMattress = max(0, $guests - $baseCapacity);
        $extraMattressCost = $extraMattress * $mattressRate * $nights;
        $subtotal = $roomBaseAmount + $extraMattressCost;
        $gstAmount = round($subtotal * ($gstPercent / 100), 2);
        $totalAmount = $subtotal + $gstAmount;
        $paymentStatus = ($paymentMethod === 'Pay on Arrival') ? 'Pending' : 'Paid';
        $bookingStatus = ($paymentStatus === 'Paid') ? 'Confirmed' : 'Pending';

        $bookingRef = 'HVN-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Insert booking record with extra mattress columns
        $st = $pdo->prepare('INSERT INTO bookings(booking_reference, user_id, room_id, guest_name, guest_email, guest_phone, check_in, check_in_time, check_out, check_out_time, guests, extra_mattress, extra_mattress_cost, total_amount, payment_method, payment_status, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $bookingRef,
            $user['id'],
            $roomId,
            $guestName,
            $guestEmail,
            $phone,
            $in,
            $checkInTime,
            $out,
            $checkOutTime,
            $guests,
            $extraMattress,
            $extraMattressCost,
            $totalAmount,
            $paymentMethod,
            $paymentStatus,
            $bookingStatus
        ]);
        $bookingId = (int)$pdo->lastInsertId();

        // Emit real-time live event
        emit_event($pdo, 'booking_created', [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingRef,
            'user_id' => (int)$user['id'],
            'room_id' => $roomId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $phone,
            'room_type' => $room['type'],
            'room_number' => $room['room_number'],
            'check_in' => $in,
            'check_in_time' => $checkInTime,
            'check_out' => $out,
            'check_out_time' => $checkOutTime,
            'nights' => $nights,
            'guests' => $guests,
            'extra_mattress' => $extraMattress,
            'extra_mattress_cost' => $extraMattressCost,
            'extra_mattress_rate' => $mattressRate,
            'room_base_amount' => $roomBaseAmount,
            'subtotal' => $subtotal,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total_amount' => (float)$totalAmount,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => $bookingStatus
        ], (int)$user['id'], $bookingId, $roomId);

        flash('main', "Booking request submitted successfully! Your Booking ID is $bookingRef");
        redirect('my_bookings.php');
    }
}

include 'includes/header.php';
?>
<div class="form-page wide">
    <div class="split">
        <div class="panel">
            <span class="eyebrow">Reserve your room</span>
            <h1><?= e($room['type']) ?></h1>
            <p class="muted"><?= e($room['description']) ?></p>
            <div class="meta" style="flex-wrap:wrap; gap:8px;">
                <span>Room <?= e($room['room_number']) ?></span>
                <span>👥 Base: <?= e($room['capacity']) ?> · Max: <?= e($room['max_capacity'] ?? $room['capacity']) ?> Guests</span>
                <span class="price">₹<?= number_format($room['price']) ?> / night <small style="font-weight:normal;color:var(--muted);">(+<?= $gstPercent ?>% GST)</small></span>
            </div>
            <!-- Interactive Multi-Photo Showcase for Room -->
            <div style="position:relative; border-radius:14px; overflow:hidden; margin:16px 0 10px; box-shadow:var(--shadow-sm);">
                <img id="booking-main-photo" class="room-img" src="<?= e($roomGallery[0] ?? room_img_src($room['image'])) ?>" alt="<?= e($room['type']) ?>" style="height:260px; transition:opacity 0.25s ease;">
                <span style="position:absolute; top:12px; right:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.75rem; font-weight:700; padding:5px 12px; border-radius:20px; backdrop-filter:blur(6px); display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    <?= count($roomGallery) ?> Room Photos
                </span>
            </div>

            <?php if (count($roomGallery) > 1): ?>
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; margin-bottom:18px;">
                    <?php 
                    $photoLabels = ['Master Bed', 'Bathroom', 'Balcony / View', 'Living / Lounge'];
                    foreach ($roomGallery as $idx => $gImg): 
                    $label = $photoLabels[$idx] ?? ('Photo ' . ($idx + 1));
                    ?>
                        <div style="cursor:pointer; text-align:center;" onclick="changeBookingPhoto('<?= e($gImg) ?>', this)">
                            <img src="<?= e($gImg) ?>" alt="<?= e($label) ?>" class="booking-thumb-img <?= $idx === 0 ? 'active' : '' ?>" style="width:100%; height:52px; object-fit:cover; border-radius:8px; border:2.5px solid <?= $idx === 0 ? 'var(--teal)' : 'transparent' ?>; opacity:<?= $idx === 0 ? '1' : '0.65' ?>; transition:all 0.2s;">
                            <span style="font-size:0.7rem; color:var(--muted); font-weight:600; display:block; margin-top:3px;"><?= e($label) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Room Capacity & Extra Mattress Policy Card -->
            <div style="margin-top: 18px; padding: 14px 16px; background: #fdfaf4; border-radius: 12px; border: 1px solid #fae6cb;">
                <h4 style="margin: 0 0 8px; font-size: 0.92rem; color: #92400e; display: flex; align-items: center; justify-content: space-between;">
                    <span style="display:flex; align-items:center; gap:6px;">🛏️ Capacity &amp; Extra Bedding</span>
                    <span class="badge" style="background:#fef3c7; color:#b45309; font-size:0.72rem; font-weight:700;">₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?> / day</span>
                </h4>
                <div style="font-size:0.83rem; color:#78350f; line-height:1.45;">
                    <div>&bull; Standard room tariff covers <strong><?= (int)$room['capacity'] ?> guests</strong>.</div>
                    <?php if ((int)($room['max_capacity'] ?? $room['capacity']) > (int)$room['capacity']): ?>
                        <div>&bull; Additional guests (up to <strong><?= (int)$room['max_capacity'] ?> max</strong>) include premium extra mattress &amp; fresh linens at <strong>₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?>/day (+12% GST)</strong>.</div>
                    <?php else: ?>
                        <div>&bull; Maximum room capacity is <strong><?= (int)$room['capacity'] ?> guests</strong>.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cancellation & Refund Policy Card -->
            <div style="margin-top: 14px; padding: 14px 16px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                <h4 style="margin: 0 0 8px; font-size: 0.92rem; color: #166534; display: flex; align-items: center; justify-content: space-between;">
                    <span style="display:flex; align-items:center; gap:6px;">🛡️ Cancellation &amp; Refund Policy</span>
                    <span class="badge" style="background:#dcfce7; color:#15803d; font-size:0.72rem; font-weight:700;">Free Cancellation &ge; 48h</span>
                </h4>
                <div style="font-size:0.82rem; color:#14532d; line-height:1.45;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>&ge; 48 hours prior to check-in:</span>
                        <strong style="color:#15803d;">100% Refund (₹0 Fee)</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>&lt; 48 hours prior to check-in:</span>
                        <strong style="color:#b45309;">90% Refund (10% Fee)</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed #86efac; padding-top:4px; margin-top:4px;">
                        <span>Cancelled by Hotel:</span>
                        <strong style="color:#15803d;">100% Full Charges Reverted</strong>
                    </div>
                    <small style="color:#166534; display:block; margin-top:5px; font-size:0.74rem;">Reverted charges are credited to original payment source within 2-3 business days.</small>
                </div>
            </div>

            <div style="margin-top: 14px; padding: 14px 16px; background: #f6faf9; border-radius: 12px; border: 1px solid #d5e5e1;">
                <h4 style="margin: 0 0 10px; font-size: 0.92rem; color: var(--teal-dark); display: flex; align-items: center; gap: 8px;">
                    <span>🏨 Hotel Timing Policy</span>
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.85rem;">
                    <div style="background:#fff; padding:10px 12px; border-radius:8px; border:1px solid #e2ece9;">
                        <span class="muted" style="display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Standard Check-in</span>
                        <strong style="color:var(--teal);">02:00 PM</strong> onwards
                    </div>
                    <div style="background:#fff; padding:10px 12px; border-radius:8px; border:1px solid #e2ece9;">
                        <span class="muted" style="display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Standard Check-out</span>
                        <strong style="color:var(--teal);">11:00 AM</strong> sharp
                    </div>
                </div>
                <small class="muted" style="display:block; margin-top:8px; font-size:0.76rem;">Early check-in &amp; late check-out are subject to room availability.</small>
            </div>
        </div>

        <div class="panel">
            <h2>Booking details</h2>
            <?php if ($error): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>
            <div id="booking-validation-error" class="alert error" style="display:none;"></div>

            <form method="post" id="booking-form" novalidate>
                <input type="hidden" name="room_id" id="room_id" value="<?= $roomId ?>">
                
                <div class="field">
                    <label for="guest_name">Guest full name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="guest_name" name="guest_name" value="<?= e($_POST['guest_name'] ?? '') ?>" required placeholder="Enter guest full name" autocomplete="name">
                </div>
                
                <div class="field">
                    <label for="guest_email">Email address <span style="color:var(--danger)">*</span></label>
                    <input type="email" id="guest_email" name="guest_email" value="<?= e($_POST['guest_email'] ?? '') ?>" required placeholder="Enter contact email address" autocomplete="email">
                </div>
                
                <div class="field">
                    <label for="phone">Phone number <span style="color:var(--danger)">*</span></label>
                    <input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" required placeholder="Enter 10-digit mobile number" pattern="^(\+?[0-9\s-]{10,15})$" maxlength="15" title="Please enter a valid real phone number (10 to 15 digits)" autocomplete="tel">
                    <small class="muted" style="font-size:0.78rem;">Enter your 10 to 15 digit mobile number for booking confirmation SMS/WhatsApp</small>
                </div>
                
                <div class="form-grid">
                    <div class="field">
                        <label for="check_in">Check-in date <span style="color:var(--danger)">*</span></label>
                        <input type="date" id="check_in" name="check_in" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['check_in'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="check_out">Check-out date <span style="color:var(--danger)">*</span></label>
                        <input type="date" id="check_out" name="check_out" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($_POST['check_out'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Expected Check-in & Check-out Times -->
                <div class="form-grid">
                    <div class="field">
                        <label for="check_in_time">Expected Check-in Time</label>
                        <select id="check_in_time" name="check_in_time">
                            <option value="02:00 PM (Standard)" selected>02:00 PM (Standard Check-in)</option>
                            <option value="12:00 PM - 02:00 PM (Early)">12:00 PM - 02:00 PM (Early Check-in)</option>
                            <option value="02:00 PM - 04:00 PM">02:00 PM - 04:00 PM (Afternoon)</option>
                            <option value="04:00 PM - 06:00 PM">04:00 PM - 06:00 PM (Late Afternoon)</option>
                            <option value="06:00 PM - 08:00 PM">06:00 PM - 08:00 PM (Evening)</option>
                            <option value="After 08:00 PM (Late Arrival)">After 08:00 PM (Late Arrival)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="check_out_time">Expected Check-out Time</label>
                        <select id="check_out_time" name="check_out_time">
                            <option value="11:00 AM (Standard)" selected>11:00 AM (Standard Check-out)</option>
                            <option value="Before 09:00 AM (Early)">Before 09:00 AM (Early Check-out)</option>
                            <option value="09:00 AM - 11:00 AM">09:00 AM - 11:00 AM (Morning)</option>
                            <option value="12:00 PM (Express)">12:00 PM (Express Check-out)</option>
                            <option value="01:00 PM (Late on Request)">01:00 PM (Late Check-out on Request)</option>
                        </select>
                    </div>
                </div>
                
                <div class="field">
                    <label for="guests">Number of guests <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="guests" name="guests" min="1" max="<?= (int)($room['max_capacity'] ?? $room['capacity']) ?>" value="<?= e($_POST['guests'] ?? '') ?>" placeholder="e.g. 1" required>
                    <div style="margin-top:6px; font-size:0.8rem; color:var(--muted); line-height:1.4;">
                        <span>👥 Base Capacity: <strong><?= (int)$room['capacity'] ?> Guests</strong> &bull; Max: <strong><?= (int)($room['max_capacity'] ?? $room['capacity']) ?> Guests</strong></span>
                        <?php if ((int)($room['max_capacity'] ?? $room['capacity']) > (int)$room['capacity']): ?>
                            <span style="display:block; color:#92400e; font-weight:600; margin-top:2px;">
                                Additional guests above <?= (int)$room['capacity'] ?> include extra mattress @ ₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?> / guest / night (+12% GST).
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Real Amount & GST Breakdown Card -->
                <div class="price-summary-box" style="margin: 20px 0; padding: 18px; background: var(--teal-pale); border-radius: 12px; border: 1px solid #c9ded9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <strong style="color: var(--teal-dark); font-size: 0.98rem;">Price Breakdown</strong>
                        <span class="badge" style="background:#def0ea; color:var(--teal); font-size:0.75rem; font-weight:700;">+<?= $gstPercent ?>% GST</span>
                    </div>
                    <div id="price-breakdown-details">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem;">
                            <span>Room (₹<?= number_format($room['price']) ?> &times; <strong id="calc-nights">1</strong> night<span id="plural-s"></span>)</span>
                            <span id="calc-base" style="font-weight:600;">₹<?= number_format($room['price']) ?></span>
                        </div>
                        <!-- Dynamic Extra Mattress Row -->
                        <div id="calc-mattress-row" style="display:none; justify-content:space-between; margin-bottom:6px; font-size:0.88rem; background:#fef3e2; color:#92400e; padding:6px 10px; border-radius:6px; border:1px solid #fde68a;">
                            <span>🛏️ Extra Mattress (<span id="calc-mattress-count">1</span> &times; ₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?> &times; <span id="calc-mattress-nights">1</span>n)</span>
                            <span id="calc-mattress-total" style="font-weight:700;">+ ₹0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: var(--ink-soft);">
                            <span>Taxes &amp; Fees (+<?= $gstPercent ?>% GST)</span>
                            <span id="calc-gst" style="font-weight:600;">+ ₹<?= number_format($room['price'] * ($gstPercent / 100)) ?></span>
                        </div>
                        <hr style="border: 0; border-top: 1px dashed #b5d1cb; margin: 10px 0;">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 1.15rem; font-weight: 800; color: var(--teal-dark);">
                            <span>Total Payable:</span>
                            <span id="calc-total" style="color:var(--teal);">₹<?= number_format($room['price'] * (1 + $gstPercent / 100)) ?></span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--muted); margin-top: 5px;">
                            *Calculated for <span id="summary-dates-text">1 night</span> with <?= $gstPercent ?>% GST included.
                        </div>
                    </div>
                </div>

                <button class="button" id="open-payment-btn" type="button" style="width:100%; display:flex; justify-content:center; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span id="open-payment-btn-text">Proceed to Payment · ₹<?= number_format($room['price'] * (1 + $gstPercent / 100)) ?></span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- INTERACTIVE PAYMENT MODAL & SUCCESS SCREEN -->
<!-- ========================================== -->
<canvas id="confetti-canvas"></canvas>

<div class="payment-modal-overlay" id="payment-modal-overlay">
    <div class="payment-modal" id="payment-modal">
        
        <!-- STEP 1: PAYMENT SELECTION VIEW -->
        <div id="payment-selection-view">
            <div class="payment-modal-header">
                <div>
                    <h3>Select Payment Method</h3>
                    <div style="font-size: 0.82rem; color: var(--muted); margin-top: 2px;">
                        <?= e($room['type']) ?> &bull; Room <?= e($room['room_number']) ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="badge" style="background:#e3f5ef; color:var(--teal); font-size:0.85rem; font-weight:800; padding:6px 12px;" id="modal-total-badge">
                        ₹<?= number_format($room['price'] * (1 + $gstPercent / 100)) ?>
                    </span>
                    <button type="button" class="payment-close-btn" id="close-payment-modal" aria-label="Close modal">&times;</button>
                </div>
            </div>

            <!-- TABS -->
            <div class="payment-tabs">
                <button type="button" class="pay-tab-btn active" data-tab="upi">
                    <span style="font-size:1.1rem;">📱</span>
                    <span>UPI / QR</span>
                </button>
                <button type="button" class="pay-tab-btn" data-tab="card">
                    <span style="font-size:1.1rem;">💳</span>
                    <span>Cards</span>
                </button>
                <button type="button" class="pay-tab-btn" data-tab="arrival">
                    <span style="font-size:1.1rem;">🏨</span>
                    <span>Pay at Desk</span>
                </button>
                <button type="button" class="pay-tab-btn" data-tab="netbanking">
                    <span style="font-size:1.1rem;">🏦</span>
                    <span>Net Banking</span>
                </button>
            </div>

            <div class="payment-body">
                
                <!-- TAB 1: UPI / QR CODE -->
                <div class="pay-panel active" id="tab-upi">
                    <div style="text-align:center;">
                        <span class="eyebrow" style="font-size:0.68rem; margin-bottom:6px;">Instant &amp; Zero Fee</span>
                        <h4 style="margin:0 0 10px; font-size:1.05rem; color:var(--teal-dark);">Scan QR with any UPI App</h4>
                        <p class="muted" style="font-size:0.82rem; margin:0 0 16px;">GPay, PhonePe, Paytm, BHIM, Cred, Amazon Pay</p>
                        
                        <!-- Animated QR Box -->
                        <div class="upi-qr-box">
                            <svg viewBox="0 0 100 100" width="100%" height="100%" style="display:block;">
                                <rect width="100" height="100" fill="#ffffff"/>
                                <rect x="8" y="8" width="24" height="24" rx="3" fill="#123d3a"/>
                                <rect x="12" y="12" width="16" height="16" rx="2" fill="#ffffff"/>
                                <rect x="15" y="15" width="10" height="10" fill="#123d3a"/>
                                <rect x="68" y="8" width="24" height="24" rx="3" fill="#123d3a"/>
                                <rect x="72" y="12" width="16" height="16" rx="2" fill="#ffffff"/>
                                <rect x="75" y="15" width="10" height="10" fill="#123d3a"/>
                                <rect x="8" y="68" width="24" height="24" rx="3" fill="#123d3a"/>
                                <rect x="12" y="72" width="16" height="16" rx="2" fill="#ffffff"/>
                                <rect x="15" y="75" width="10" height="10" fill="#123d3a"/>
                                <rect x="36" y="10" width="4" height="4" fill="#123d3a"/>
                                <rect x="44" y="10" width="4" height="4" fill="#123d3a"/>
                                <rect x="52" y="14" width="4" height="4" fill="#123d3a"/>
                                <rect x="56" y="22" width="4" height="4" fill="#123d3a"/>
                                <rect x="40" y="26" width="4" height="4" fill="#123d3a"/>
                                <rect x="48" y="30" width="4" height="4" fill="#123d3a"/>
                                <rect x="12" y="38" width="4" height="4" fill="#123d3a"/>
                                <rect x="20" y="42" width="4" height="4" fill="#123d3a"/>
                                <rect x="28" y="38" width="4" height="4" fill="#123d3a"/>
                                <rect x="36" y="44" width="6" height="6" fill="#1e625d"/>
                                <rect x="52" y="44" width="6" height="6" fill="#1e625d"/>
                                <rect x="44" y="52" width="8" height="8" rx="2" fill="#bd8747"/>
                                <rect x="64" y="38" width="4" height="4" fill="#123d3a"/>
                                <rect x="74" y="42" width="4" height="4" fill="#123d3a"/>
                                <rect x="82" y="38" width="4" height="4" fill="#123d3a"/>
                                <rect x="38" y="68" width="4" height="4" fill="#123d3a"/>
                                <rect x="46" y="74" width="4" height="4" fill="#123d3a"/>
                                <rect x="56" y="70" width="4" height="4" fill="#123d3a"/>
                                <rect x="68" y="68" width="4" height="4" fill="#123d3a"/>
                                <rect x="76" y="72" width="4" height="4" fill="#123d3a"/>
                                <rect x="84" y="68" width="4" height="4" fill="#123d3a"/>
                                <rect x="72" y="80" width="4" height="4" fill="#123d3a"/>
                                <rect x="80" y="84" width="4" height="4" fill="#123d3a"/>
                            </svg>
                            <div class="upi-scan-line"></div>
                        </div>

                        <div style="display:inline-flex; align-items:center; gap:8px; background:#f0f7f5; border:1px solid #cce4de; padding:6px 14px; border-radius:20px; font-size:0.85rem; margin-bottom:16px;">
                            <span>UPI ID: <strong style="color:var(--teal);">havenhotel@upi</strong></span>
                            <button type="button" class="copy-ref-btn" id="copy-upi-btn" title="Copy UPI ID">Copy</button>
                        </div>

                        <div style="max-width:360px; margin:0 auto 16px;">
                            <label for="upi_id_input" style="display:block; text-align:left; font-size:0.8rem; margin-bottom:6px;">Or enter your UPI ID (VPA):</label>
                            <input type="text" id="upi_id_input" placeholder="e.g. mobile@okhdfcbank" style="padding:10px 12px; font-size:0.9rem;">
                        </div>

                        <button type="button" class="button" id="pay-upi-btn" style="width:100%; max-width:360px;">
                            <span>Pay Now via UPI</span>
                        </button>
                    </div>
                </div>

                <!-- TAB 2: CREDIT / DEBIT CARD -->
                <div class="pay-panel" id="tab-card">
                    <!-- Interactive 3D Card Visualizer with 3D Flip -->
                    <div class="card-scene" id="card-scene" title="Click to flip card">
                        <div class="card-flip-wrap" id="card-flip-wrap">
                            <!-- FRONT FACE -->
                            <div class="card-face card-front">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div class="card-chip"></div>
                                    <span style="font-family:'Playfair Display', serif; font-size:1.15rem; font-weight:700; letter-spacing:0.5px;">HAVEN</span>
                                </div>
                                <div class="card-number-display" id="card-preview-number">•••• •••• •••• ••••</div>
                                <div class="card-meta-row">
                                    <div>
                                        <span style="font-size:0.65rem;">Cardholder</span>
                                        <strong id="card-preview-name">CARDHOLDER NAME</strong>
                                    </div>
                                    <div>
                                        <span style="font-size:0.65rem;">Expires</span>
                                        <strong id="card-preview-exp">MM/YY</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- BACK FACE -->
                            <div class="card-face card-back">
                                <div class="card-magnetic-strip"></div>
                                <div class="card-back-body">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:4px;">
                                        <span style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.8px; color:#b8d5cf;">Authorized Signature</span>
                                        <span style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.8px; color:#e0c28d; font-weight:700;">CVV / CVC</span>
                                    </div>
                                    <div class="card-signature-panel">
                                        <span class="card-cvv-display" id="card-preview-cvv">•••</span>
                                    </div>
                                </div>
                                <div class="card-back-meta">
                                    <span>HAVEN LUXURY RESORT &bull; 24/7 ENCRYPTED</span>
                                    <span style="font-family:'Playfair Display', serif; font-weight:700;">HAVEN</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Input Form -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div class="field" style="margin:0;">
                            <label for="card_num">Card Number</label>
                            <input type="text" id="card_num" placeholder="4532 8901 2345 6789" maxlength="19" autocomplete="cc-number">
                        </div>
                        <div class="field" style="margin:0;">
                            <label for="card_holder">Cardholder Name</label>
                            <input type="text" id="card_holder" value="" placeholder="Full name as printed on card" autocomplete="cc-name">
                        </div>
                        <div class="form-grid" style="gap:0 12px;">
                            <div class="field" style="margin:0;">
                                <label for="card_exp">Expiry Date</label>
                                <input type="text" id="card_exp" placeholder="MM/YY" maxlength="5" autocomplete="cc-exp">
                            </div>
                            <div class="field" style="margin:0;">
                                <label for="card_cvv">CVV / CVC</label>
                                <input type="password" id="card_cvv" placeholder="•••" maxlength="4" autocomplete="cc-csc">
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--muted); margin-top:4px;">
                            <span>🔒 256-Bit Bank-grade encryption. Test sandbox active.</span>
                        </div>
                        <button type="button" class="button" id="pay-card-btn" style="width:100%; margin-top:8px;">
                            <span id="pay-card-btn-text">Pay Securely</span>
                        </button>
                    </div>
                </div>

                <!-- TAB 3: PAY ON ARRIVAL -->
                <div class="pay-panel" id="tab-arrival">
                    <div style="text-align:center; padding:12px 6px;">
                        <div style="width:64px; height:64px; border-radius:50%; background:var(--teal-pale); display:grid; place-items:center; font-size:1.8rem; margin:0 auto 16px;">
                            🛎️
                        </div>
                        <h4 style="margin:0 0 8px; font-size:1.15rem; color:var(--teal-dark);">Pay upon Check-in at Hotel Desk</h4>
                        <p class="muted" style="font-size:0.88rem; max-width:440px; margin:0 auto 20px; line-height:1.5;">
                            Zero upfront payment required today. You can pay seamlessly using <strong>Cash, UPI, Credit Card, or Debit Card</strong> when arriving at Haven Hotel &amp; Suites.
                        </p>

                        <div style="background:#fafcfb; border:1px solid var(--line); border-radius:12px; padding:16px; max-width:420px; margin:0 auto 20px; text-align:left; font-size:0.85rem;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <span class="muted">Check-in expected:</span>
                                <strong id="arrival-checkin-time-text">02:00 PM (Standard)</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <span class="muted">Check-out expected:</span>
                                <strong id="arrival-checkout-time-text">11:00 AM (Standard)</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; border-top:1px dashed #cad9d5; padding-top:8px;">
                                <span class="muted">Total Due at Desk:</span>
                                <strong id="arrival-total-text" style="color:var(--teal); font-size:1rem;">₹0</strong>
                            </div>
                        </div>

                        <button type="button" class="button" id="pay-arrival-btn" style="width:100%; max-width:380px;">
                            <span>Confirm &amp; Pay on Arrival</span>
                        </button>
                    </div>
                </div>

                <!-- TAB 4: NET BANKING -->
                <div class="pay-panel" id="tab-netbanking">
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <div class="bank-options-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(90px, 1fr)); gap:10px;">
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="HDFC Bank" checked style="width:auto; margin:0;">
                                <span>HDFC Bank</span>
                            </label>
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="State Bank of India" style="width:auto; margin:0;">
                                <span>SBI</span>
                            </label>
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="ICICI Bank" style="width:auto; margin:0;">
                                <span>ICICI Bank</span>
                            </label>
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="Axis Bank" style="width:auto; margin:0;">
                                <span>Axis Bank</span>
                            </label>
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="Kotak Mahindra" style="width:auto; margin:0;">
                                <span>Kotak Bank</span>
                            </label>
                            <label style="border:1px solid #cad9d5; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; font-size:0.82rem; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <input type="radio" name="bank_option" value="Punjab National Bank" style="width:auto; margin:0;">
                                <span>PNB</span>
                            </label>
                        </div>

                        <div class="field" style="margin:6px 0 0;">
                            <label for="other_banks">Or select another bank</label>
                            <select id="other_banks">
                                <option value="">-- Choose Other Indian Bank --</option>
                                <option value="Bank of Baroda">Bank of Baroda</option>
                                <option value="Canara Bank">Canara Bank</option>
                                <option value="Union Bank of India">Union Bank of India</option>
                                <option value="IndusInd Bank">IndusInd Bank</option>
                                <option value="IDBI Bank">IDBI Bank</option>
                                <option value="Yes Bank">Yes Bank</option>
                                <option value="Federal Bank">Federal Bank</option>
                            </select>
                        </div>

                        <button type="button" class="button" id="pay-netbanking-btn" style="width:100%; margin-top:8px;">
                            <span>Proceed to Net Banking</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Cancellation & Refund Guarantee in Payment Modal -->
            <div style="margin-top:16px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; font-size:0.8rem; color:#166534; display:flex; align-items:center; gap:10px;">
                <span style="font-size:1.2rem; flex-shrink:0;">🛡️</span>
                <div style="line-height:1.4;">
                    <strong>Cancellation &amp; Refund Guarantee:</strong> Free cancellation &ge;48h before check-in (100% refund). Within 48h: 10% fee (90% refund). If cancelled by hotel: 100% full charges reverted immediately.
                </div>
            </div>
        </div>

        <!-- STEP 2: ANIMATED PROCESSING SCREEN -->
        <div class="payment-processing-container" id="payment-processing-container">
            <div class="processing-spinner"></div>
            <h3 style="margin:0 0 6px; font-size:1.35rem; color:var(--teal-dark);">Processing Payment...</h3>
            <p class="muted" style="margin:0 0 24px; font-size:0.86rem;">Please do not close or refresh this page.</p>

            <div class="processing-steps">
                <div class="step-item active" id="proc-step-1">
                    <span class="step-dot">1</span>
                    <span>Connecting to banking gateway...</span>
                </div>
                <div class="step-item" id="proc-step-2">
                    <span class="step-dot">2</span>
                    <span>Verifying transaction &amp; authorization...</span>
                </div>
                <div class="step-item" id="proc-step-3">
                    <span class="step-dot">3</span>
                    <span>Confirming room &amp; generating booking ID...</span>
                </div>
            </div>
        </div>

        <!-- STEP 3: BOOKING SUCCESSFUL SCREEN -->
        <div class="booking-success-view" id="booking-success-view">
            <!-- Animated SVG Checkmark -->
            <svg class="success-checkmark-svg" viewBox="0 0 52 52">
                <circle cx="26" cy="26" r="25" fill="none"/>
                <path fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>

            <span class="eyebrow" style="color:var(--success); font-size:0.75rem;">Payment &amp; Reservation Confirmed</span>
            <h2 style="margin:4px 0 10px; font-size:1.6rem; color:var(--teal-dark);">Booking Successful! 🎉</h2>
            <p class="muted" style="margin:0 0 14px; font-size:0.9rem;">Your stay at Haven Hotel &amp; Suites has been successfully booked.</p>

            <div>
                <span style="font-size:0.76rem; text-transform:uppercase; letter-spacing:1px; color:var(--muted); display:block; font-weight:700;">Unique Booking ID</span>
                <div class="booking-ref-badge">
                    <span id="confirmed-booking-ref">HVN-2026-XXXXXX</span>
                    <button type="button" class="copy-ref-btn" id="copy-confirmed-ref-btn">Copy ID</button>
                </div>
            </div>

            <!-- Itemized Receipt -->
            <div id="printable-receipt-area">
                <table class="receipt-table">
                    <tr>
                        <td class="receipt-label">Room Reserved</td>
                        <td><strong><span id="rec-room-type"><?= e($room['type']) ?></span></strong> (Room <span id="rec-room-number"><?= e($room['room_number']) ?></span>)</td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Guest Details</td>
                        <td><span id="rec-guest-name"></span> &bull; <span id="rec-guest-phone"></span></td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Check-in Expected</td>
                        <td><span id="rec-checkin-date"></span> &bull; <strong id="rec-checkin-time">02:00 PM</strong></td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Check-out Expected</td>
                        <td><span id="rec-checkout-date"></span> &bull; <strong id="rec-checkout-time">11:00 AM</strong></td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Stay &amp; Guests</td>
                        <td><span id="rec-nights-count">1 Night</span> &bull; <span id="rec-guests-count">1 Guest</span></td>
                    </tr>
                    <tr id="rec-mattress-row" style="display:none;">
                        <td class="receipt-label">Extra Bedding</td>
                        <td><span id="rec-mattress-info" style="color:#b45309; font-weight:700;"></span></td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Payment Method</td>
                        <td><span id="rec-payment-method">UPI</span> (<span id="rec-payment-status" style="font-weight:700; color:var(--success);">Paid</span>)</td>
                    </tr>
                    <tr>
                        <td class="receipt-label">Total Amount Paid</td>
                        <td><span id="rec-total-amount">₹0</span> <span style="font-size:0.75rem; font-weight:normal; color:var(--muted);">(incl. 12% GST)</span></td>
                    </tr>
                </table>
            </div>

            <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:20px;" class="no-print">
                <button type="button" class="button alt" id="print-receipt-btn" style="min-height:40px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print Receipt
                </button>
                <a href="my_bookings.php" class="button" style="min-height:40px;">
                    View in My Bookings &rarr;
                </a>
            </div>
        </div>

    </div>
</div>

<script>
(function() {
    const pricePerNight = <?= (float)$room['price'] ?>;
    const gstRate = <?= (float)($gstPercent / 100) ?>;
    const form = document.getElementById('booking-form');
    const guestNameInput = document.getElementById('guest_name');
    const guestEmailInput = document.getElementById('guest_email');
    const phoneInput = document.getElementById('phone');
    const inInput = document.getElementById('check_in');
    const outInput = document.getElementById('check_out');
    const inTimeInput = document.getElementById('check_in_time');
    const outTimeInput = document.getElementById('check_out_time');
    const guestsInput = document.getElementById('guests');
    const nightsEl = document.getElementById('calc-nights');
    const pluralEl = document.getElementById('plural-s');
    const baseEl = document.getElementById('calc-base');
    const gstEl = document.getElementById('calc-gst');
    const totalEl = document.getElementById('calc-total');
    const datesTextEl = document.getElementById('summary-dates-text');
    const openPaymentBtn = document.getElementById('open-payment-btn');
    const openPaymentBtnText = document.getElementById('open-payment-btn-text');
    const errorBox = document.getElementById('booking-validation-error');

    // Modal elements
    const overlay = document.getElementById('payment-modal-overlay');
    const modalCloseBtn = document.getElementById('close-payment-modal');
    const selectionView = document.getElementById('payment-selection-view');
    const processingView = document.getElementById('payment-processing-container');
    const successView = document.getElementById('booking-success-view');
    const modalTotalBadge = document.getElementById('modal-total-badge');

    // Tab buttons & panels
    const tabButtons = document.querySelectorAll('.pay-tab-btn');
    const tabPanels = document.querySelectorAll('.pay-panel');

    // Current booking state
    let calculatedTotal = Math.round(pricePerNight * (1 + gstRate));
    let calculatedNights = 1;

    // Photo gallery switcher
    window.changeBookingPhoto = function(src, el) {
        const mainImg = document.getElementById('booking-main-photo');
        if (!mainImg) return;
        mainImg.style.opacity = '0.35';
        setTimeout(() => {
            mainImg.src = src;
            mainImg.style.opacity = '1';
        }, 140);

        document.querySelectorAll('.booking-thumb-img').forEach(t => {
            t.style.borderColor = 'transparent';
            t.style.opacity = '0.65';
        });
        const activeImg = el.querySelector('img');
        if (activeImg) {
            activeImg.style.borderColor = 'var(--teal)';
            activeImg.style.opacity = '1';
        }
    };

    // Extra mattress configuration
    const baseCapacity = <?= (int)$room['capacity'] ?>;
    const maxCapacity = <?= (int)($room['max_capacity'] ?? $room['capacity']) ?>;
    const mattressRate = <?= (float)($room['extra_mattress_rate'] ?? 800.00) ?>;
    const mattressRow = document.getElementById('calc-mattress-row');
    const mattressCountEl = document.getElementById('calc-mattress-count');
    const mattressNightsEl = document.getElementById('calc-mattress-nights');
    const mattressTotalEl = document.getElementById('calc-mattress-total');

    // Phone restriction: allow digits, space, +, -
    phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+\s-]/g, '');
    });

    // Update pricing on date or guest count change
    function updatePricing() {
        const inVal = inInput.value;
        const outVal = outInput.value;

        if (inVal) {
            const inDate = new Date(inVal);
            const nextDay = new Date(inDate);
            nextDay.setDate(nextDay.getDate() + 1);
            const nextDayStr = nextDay.toISOString().split('T')[0];
            outInput.min = nextDayStr;
            if (outVal && outVal <= inVal) {
                outInput.value = nextDayStr;
            }
        }

        const effectiveIn = inInput.value;
        const effectiveOut = outInput.value;
        const guestsVal = parseInt(guestsInput.value, 10) || 1;

        if (effectiveIn && effectiveOut && effectiveOut > effectiveIn) {
            const d1 = new Date(effectiveIn);
            const d2 = new Date(effectiveOut);
            const diffTime = d2.getTime() - d1.getTime();
            calculatedNights = Math.max(1, Math.round(diffTime / (1000 * 60 * 60 * 24)));
        } else {
            calculatedNights = 1;
        }

        const roomBase = calculatedNights * pricePerNight;
        const extraMattressCount = Math.max(0, Math.min(guestsVal, maxCapacity) - baseCapacity);
        const extraMattressCost = extraMattressCount * mattressRate * calculatedNights;
        const subtotal = roomBase + extraMattressCost;
        const gst = Math.round(subtotal * gstRate);
        calculatedTotal = subtotal + gst;

        nightsEl.textContent = calculatedNights;
        pluralEl.textContent = calculatedNights > 1 ? 's' : '';
        baseEl.textContent = '₹' + roomBase.toLocaleString('en-IN');

        if (extraMattressCount > 0) {
            mattressRow.style.display = 'flex';
            mattressCountEl.textContent = extraMattressCount;
            mattressNightsEl.textContent = calculatedNights;
            mattressTotalEl.textContent = '+ ₹' + extraMattressCost.toLocaleString('en-IN');
        } else {
            mattressRow.style.display = 'none';
        }

        gstEl.textContent = '+ ₹' + gst.toLocaleString('en-IN');
        totalEl.textContent = '₹' + calculatedTotal.toLocaleString('en-IN');
        datesTextEl.textContent = calculatedNights + ' night' + (calculatedNights > 1 ? 's' : '');
        openPaymentBtnText.textContent = 'Proceed to Payment · ₹' + calculatedTotal.toLocaleString('en-IN');
        modalTotalBadge.textContent = '₹' + calculatedTotal.toLocaleString('en-IN');

        // Also update Pay on Arrival summary
        document.getElementById('arrival-checkin-time-text').textContent = inTimeInput.value;
        document.getElementById('arrival-checkout-time-text').textContent = outTimeInput.value;
        document.getElementById('arrival-total-text').textContent = '₹' + calculatedTotal.toLocaleString('en-IN');
    }

    inInput.addEventListener('change', updatePricing);
    outInput.addEventListener('change', updatePricing);
    inTimeInput.addEventListener('change', updatePricing);
    outTimeInput.addEventListener('change', updatePricing);
    guestsInput.addEventListener('input', updatePricing);
    guestsInput.addEventListener('change', updatePricing);
    updatePricing();

    // Tab switching
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            tabButtons.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const targetId = 'tab-' + this.getAttribute('data-tab');
            document.getElementById(targetId)?.classList.add('active');
        });
    });

    // Validate form before opening payment modal
    function validateBookingForm() {
        errorBox.style.display = 'none';
        errorBox.textContent = '';

        const nameVal = guestNameInput.value.trim();
        const emailVal = guestEmailInput.value.trim();
        const phoneVal = phoneInput.value.trim();
        const digits = phoneVal.replace(/\D/g, '');
        const inVal = inInput.value;
        const outVal = outInput.value;
        const guestsVal = parseInt(guestsInput.value, 10);
        const today = new Date().toISOString().split('T')[0];

        if (!nameVal) {
            showError('Please enter the guest full name.');
            guestNameInput.focus();
            return false;
        }

        if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
            showError('Please enter a valid contact email address.');
            guestEmailInput.focus();
            return false;
        }

        if (!phoneVal || digits.length < 10 || digits.length > 15) {
            showError('Please enter a valid real phone number (10 to 15 digits).');
            phoneInput.focus();
            return false;
        }

        if (!inVal || inVal < today) {
            showError('Please select a valid check-in date from today onwards.');
            inInput.focus();
            return false;
        }

        if (!outVal || outVal <= inVal) {
            showError('Check-out date must be at least one day after check-in.');
            outInput.focus();
            return false;
        }

        if (isNaN(guestsVal) || guestsVal < 1 || guestsVal > maxCapacity) {
            showError('Please enter the number of guests (between 1 and ' + maxCapacity + ').');
            guestsInput.focus();
            return false;
        }

        return true;
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.style.display = 'block';
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Open Payment Modal
    openPaymentBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!validateBookingForm()) return;

        updatePricing();

        // If cardholder name is blank, default it to guest name
        if (cardHolderInput && !cardHolderInput.value.trim()) {
            cardHolderInput.value = guestNameInput.value.trim();
            cardPreviewName.textContent = guestNameInput.value.trim().toUpperCase() || 'CARDHOLDER NAME';
        }

        selectionView.style.display = 'block';
        processingView.style.display = 'none';
        successView.style.display = 'none';
        overlay.classList.add('active');
    });

    // Close Payment Modal
    modalCloseBtn.addEventListener('click', function() {
        overlay.classList.remove('active');
    });

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay && selectionView.style.display !== 'none') {
            overlay.classList.remove('active');
        }
    });

    // Interactive 3D Card Preview & Flip Logic
    const cardScene = document.getElementById('card-scene');
    const cardFlipWrap = document.getElementById('card-flip-wrap');
    const cardNumInput = document.getElementById('card_num');
    const cardHolderInput = document.getElementById('card_holder');
    const cardExpInput = document.getElementById('card_exp');
    const cardCvvInput = document.getElementById('card_cvv');
    const cardPreviewNumber = document.getElementById('card-preview-number');
    const cardPreviewName = document.getElementById('card-preview-name');
    const cardPreviewExp = document.getElementById('card-preview-exp');
    const cardPreviewCvv = document.getElementById('card-preview-cvv');

    // 1. Sync guest name to cardholder name if cardholder not customized
    guestNameInput.addEventListener('input', function() {
        if (!cardHolderInput.value || cardHolderInput.dataset.touched !== 'true') {
            cardPreviewName.textContent = this.value.trim().toUpperCase() || 'CARDHOLDER NAME';
        }
    });

    // 2. Card Number Input Formatting (groups of 4, max 16 digits)
    if (cardNumInput) {
        cardNumInput.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '').substring(0, 16);
            let formatted = val.match(/.{1,4}/g)?.join(' ') || '';
            this.value = formatted;
            cardPreviewNumber.textContent = formatted || '•••• •••• •••• ••••';
        });
    }

    // 3. Cardholder Name Input
    if (cardHolderInput) {
        cardHolderInput.addEventListener('input', function() {
            this.dataset.touched = 'true';
            cardPreviewName.textContent = this.value.trim().toUpperCase() || 'CARDHOLDER NAME';
        });
    }

    // 4. Strict Locked Expiry Date (Month 01-12 & Valid Future Year)
    let isExpDeleting = false;
    if (cardExpInput) {
        cardExpInput.addEventListener('keydown', function(e) {
            isExpDeleting = (e.key === 'Backspace' || e.key === 'Delete');
        });

        cardExpInput.addEventListener('input', function() {
            let val = this.value;
            // Handle backspace when ending with slash
            if (isExpDeleting && val.endsWith('/')) {
                this.value = val.slice(0, -1);
                cardPreviewExp.textContent = this.value || 'MM/YY';
                return;
            }

            let clean = val.replace(/\D/g, '').substring(0, 4);
            if (!clean) {
                this.value = '';
                cardPreviewExp.textContent = 'MM/YY';
                return;
            }

            const currYear = parseInt(new Date().getFullYear().toString().slice(-2), 10);
            const currDecade = Math.floor(currYear / 10);
            let formatted = '';

            // Handle Month (1st and 2nd digits)
            if (clean.length === 1) {
                if (clean[0] > '1') {
                    // Digits 2-9 cannot start a month > 12, so lock with leading 0
                    formatted = '0' + clean[0] + '/';
                } else {
                    formatted = clean;
                }
            } else {
                let m = parseInt(clean.substring(0, 2), 10);
                if (m === 0) m = 1;
                if (m > 12) m = 12; // Strictly locked to 1-12
                let monthStr = m.toString().padStart(2, '0');

                if (clean.length === 2) {
                    formatted = monthStr + '/';
                } else if (clean.length === 3) {
                    let y1 = parseInt(clean[2], 10);
                    // Year decade check (e.g. 2 for 202x, 3 for 203x)
                    if (y1 < currDecade) y1 = currDecade;
                    if (y1 > currDecade + 2) y1 = currDecade + 2;
                    formatted = monthStr + '/' + y1;
                } else {
                    let y = parseInt(clean.substring(2, 4), 10);
                    if (y < currYear) y = currYear; // Lock to current year or future
                    if (y > currYear + 20) y = currYear + 20; // Lock to max +20 years
                    let yearStr = y.toString().padStart(2, '0');
                    formatted = monthStr + '/' + yearStr;
                }
            }

            this.value = formatted;
            cardPreviewExp.textContent = formatted || 'MM/YY';
        });

        cardExpInput.addEventListener('blur', function() {
            let clean = this.value.replace(/\D/g, '');
            if (clean.length >= 2 && clean.length < 4) {
                const currYear = parseInt(new Date().getFullYear().toString().slice(-2), 10);
                let m = parseInt(clean.substring(0, 2), 10);
                if (m === 0) m = 1;
                if (m > 12) m = 12;
                this.value = m.toString().padStart(2, '0') + '/' + currYear;
                cardPreviewExp.textContent = this.value;
            }
        });
    }

    // 5. Card Flip when entering CVV & showing CVV on the back
    if (cardCvvInput && cardFlipWrap) {
        // Flip to back when focusing CVV
        cardCvvInput.addEventListener('focus', function() {
            cardFlipWrap.classList.add('flipped');
        });

        // Flip back to front when blurring CVV
        cardCvvInput.addEventListener('blur', function() {
            cardFlipWrap.classList.remove('flipped');
        });

        // Update CVV on the back of the card
        cardCvvInput.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '').substring(0, 4);
            this.value = val;
            cardPreviewCvv.textContent = val || '•••';
        });

        // Ensure other inputs flip card back to front
        [cardNumInput, cardHolderInput, cardExpInput].forEach(inp => {
            if (inp) {
                inp.addEventListener('focus', function() {
                    cardFlipWrap.classList.remove('flipped');
                });
            }
        });

        // Click card scene to toggle flip manually
        cardScene?.addEventListener('click', function() {
            cardFlipWrap.classList.toggle('flipped');
        });
    }

    // Copy UPI ID button
    const copyUpiBtn = document.getElementById('copy-upi-btn');
    if (copyUpiBtn) {
        copyUpiBtn.addEventListener('click', function() {
            navigator.clipboard.writeText('havenhotel@upi').then(() => {
                copyUpiBtn.textContent = 'Copied!';
                setTimeout(() => copyUpiBtn.textContent = 'Copy', 2000);
            });
        });
    }

    // Payment Trigger Handlers
    document.getElementById('pay-upi-btn')?.addEventListener('click', () => triggerPaymentFlow('UPI'));
    document.getElementById('pay-card-btn')?.addEventListener('click', function() {
        const numVal = cardNumInput ? cardNumInput.value.replace(/\D/g, '') : '';
        const holderVal = cardHolderInput ? cardHolderInput.value.trim() : '';
        const expVal = cardExpInput ? cardExpInput.value.trim() : '';
        const cvvVal = cardCvvInput ? cardCvvInput.value.replace(/\D/g, '') : '';

        if (numVal.length < 15) {
            alert('Please enter a valid 16-digit card number.');
            cardNumInput?.focus();
            return;
        }

        if (!holderVal) {
            alert('Please enter the cardholder name.');
            cardHolderInput?.focus();
            return;
        }

        if (expVal.length < 5 || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(expVal)) {
            alert('Please enter a valid expiry date (MM/YY) with month from 01 to 12.');
            cardExpInput?.focus();
            return;
        }

        if (cvvVal.length < 3) {
            alert('Please enter a valid 3 or 4-digit CVV code on the back of your card.');
            cardCvvInput?.focus();
            return;
        }

        triggerPaymentFlow('Credit/Debit Card');
    });
    document.getElementById('pay-arrival-btn')?.addEventListener('click', () => triggerPaymentFlow('Pay on Arrival'));
    document.getElementById('pay-netbanking-btn')?.addEventListener('click', () => {
        const otherBank = document.getElementById('other_banks').value;
        const selectedRadio = document.querySelector('input[name="bank_option"]:checked')?.value;
        const bankName = otherBank || selectedRadio || 'Net Banking';
        triggerPaymentFlow('Net Banking (' + bankName + ')');
    });

    // Execute Payment Simulation & AJAX Submission
    async function triggerPaymentFlow(paymentMethod) {
        // Switch to Processing View
        selectionView.style.display = 'none';
        processingView.style.display = 'block';

        const step1 = document.getElementById('proc-step-1');
        const step2 = document.getElementById('proc-step-2');
        const step3 = document.getElementById('proc-step-3');

        step1.className = 'step-item active';
        step2.className = 'step-item';
        step3.className = 'step-item';

        // Prepare POST data
        const formData = new FormData();
        formData.append('room_id', <?= $roomId ?>);
        formData.append('guest_name', guestNameInput.value.trim());
        formData.append('guest_email', guestEmailInput.value.trim());
        formData.append('phone', phoneInput.value.trim());
        formData.append('check_in', inInput.value);
        formData.append('check_out', outInput.value);
        formData.append('check_in_time', inTimeInput.value);
        formData.append('check_out_time', outTimeInput.value);
        formData.append('guests', guestsInput.value);
        formData.append('payment_method', paymentMethod);

        // Step 1: Connecting (400ms)
        await new Promise(r => setTimeout(r, 450));
        step1.className = 'step-item done';
        step1.querySelector('.step-dot').innerHTML = '&#10003;';
        step2.className = 'step-item active';

        // Step 2: Gateway authorization & call API (700ms)
        let responseData = null;
        let fetchError = null;

        try {
            const res = await fetch('api/create_booking.php', {
                method: 'POST',
                body: formData
            });
            responseData = await res.json();
            if (!res.ok || !responseData.success) {
                fetchError = responseData?.error || 'Failed to complete booking.';
            }
        } catch (err) {
            fetchError = 'Network error: could not connect to booking server.';
        }

        await new Promise(r => setTimeout(r, 550));

        if (fetchError) {
            // Handle error
            processingView.style.display = 'none';
            selectionView.style.display = 'block';
            alert('Booking could not be completed: ' + fetchError);
            return;
        }

        // Step 3: Finalizing reservation (500ms)
        step2.className = 'step-item done';
        step2.querySelector('.step-dot').innerHTML = '&#10003;';
        step3.className = 'step-item active';

        await new Promise(r => setTimeout(r, 500));
        step3.className = 'step-item done';
        step3.querySelector('.step-dot').innerHTML = '&#10003;';

        // Display Success Screen!
        showSuccessView(responseData);
    }

    // Render Success View & Trigger Confetti Animation
    function showSuccessView(data) {
        processingView.style.display = 'none';
        successView.style.display = 'block';

        // Populate receipt details
        document.getElementById('confirmed-booking-ref').textContent = data.booking_reference;
        document.getElementById('rec-room-type').textContent = data.room_type;
        document.getElementById('rec-room-number').textContent = data.room_number;
        document.getElementById('rec-guest-name').textContent = data.guest_name;
        document.getElementById('rec-guest-phone').textContent = data.guest_phone;
        document.getElementById('rec-checkin-date').textContent = data.check_in;
        document.getElementById('rec-checkin-time').textContent = data.check_in_time;
        document.getElementById('rec-checkout-date').textContent = data.check_out;
        document.getElementById('rec-checkout-time').textContent = data.check_out_time;
        document.getElementById('rec-nights-count').textContent = data.nights + ' Night' + (data.nights > 1 ? 's' : '');
        document.getElementById('rec-guests-count').textContent = data.guests + ' Guest' + (data.guests > 1 ? 's' : '');

        const recMattressRow = document.getElementById('rec-mattress-row');
        const recMattressInfo = document.getElementById('rec-mattress-info');
        if (recMattressRow && recMattressInfo) {
            if (data.extra_mattress && Number(data.extra_mattress) > 0) {
                recMattressRow.style.display = 'table-row';
                recMattressInfo.textContent = data.extra_mattress + ' Extra Mattress (+₹' + Number(data.extra_mattress_cost).toLocaleString('en-IN') + ')';
            } else {
                recMattressRow.style.display = 'none';
            }
        }

        document.getElementById('rec-payment-method').textContent = data.payment_method;
        
        const statusEl = document.getElementById('rec-payment-status');
        statusEl.textContent = data.payment_status;
        statusEl.style.color = (data.payment_status === 'Paid') ? 'var(--success)' : 'var(--gold)';

        document.getElementById('rec-total-amount').textContent = '₹' + Number(data.total_amount).toLocaleString('en-IN');

        // Copy Booking ID handler
        const copyRefBtn = document.getElementById('copy-confirmed-ref-btn');
        copyRefBtn.onclick = function() {
            navigator.clipboard.writeText(data.booking_reference).then(() => {
                copyRefBtn.textContent = 'Copied!';
                setTimeout(() => copyRefBtn.textContent = 'Copy ID', 2000);
            });
        };

        // Print Receipt handler
        document.getElementById('print-receipt-btn').onclick = function() {
            window.print();
        };

        // Launch celebratory confetti!
        startConfetti();
    }

    // Lightweight Celebratory Confetti Engine
    function startConfetti() {
        const canvas = document.getElementById('confetti-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const colors = ['#1e625d', '#bd8747', '#2ecc71', '#3498db', '#e74c3c', '#f1c40f', '#9b59b6'];
        const pieces = [];
        const count = 90;

        for (let i = 0; i < count; i++) {
            pieces.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height * -0.6,
                w: Math.random() * 10 + 6,
                h: Math.random() * 6 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                vx: (Math.random() - 0.5) * 3,
                vy: Math.random() * 3 + 2.5,
                rot: Math.random() * 360,
                vRot: (Math.random() - 0.5) * 6
            });
        }

        let animationFrame = null;
        const startTime = Date.now();

        function render() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let active = false;

            pieces.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vRot;

                if (p.y < canvas.height + 20) active = true;

                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate((p.rot * Math.PI) / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });

            if (active && Date.now() - startTime < 4500) {
                animationFrame = requestAnimationFrame(render);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                cancelAnimationFrame(animationFrame);
            }
        }

        render();
    }

})();
</script>

<?php include 'includes/footer.php'; ?>
