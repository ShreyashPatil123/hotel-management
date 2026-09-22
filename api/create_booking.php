<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to complete your booking.']);
    exit;
}

$user = $_SESSION['user'];
$roomId = (int)($_POST['room_id'] ?? 0);
$guestName = trim($_POST['guest_name'] ?? '');
$guestEmail = trim($_POST['guest_email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$in = trim($_POST['check_in'] ?? '');
$out = trim($_POST['check_out'] ?? '');
$checkInTime = trim($_POST['check_in_time'] ?? '02:00 PM');
$checkOutTime = trim($_POST['check_out_time'] ?? '11:00 AM');
$guests = (int)($_POST['guests'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'Pay on Arrival');
$today = date('Y-m-d');
$gstPercent = 12;

$digits = preg_replace('/\D/', '', $phone);

// Validate room
$st = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'Available'");
$st->execute([$roomId]);
$room = $st->fetch();

if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'The selected room is no longer available.']);
    exit;
}

$baseCapacity = (int)$room['capacity'];
$maxCapacity = (int)($room['max_capacity'] ?? $baseCapacity);
$mattressRate = (float)($room['extra_mattress_rate'] ?? 800.00);

// Validate inputs
if (!$guestName) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter the guest full name.']);
    exit;
}

if (!$guestEmail || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($digits) < 10 || strlen($digits) > 15) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid real phone number (10 to 15 digits).']);
    exit;
}

if (!$in || !$out || $in < $today || $out <= $in || $guests < 1 || $guests > $maxCapacity) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Please select valid stay dates and guest count within maximum room capacity ($maxCapacity guests)."]);
    exit;
}

if (overlap_exists($pdo, $roomId, $in, $out)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This room is already booked for the selected dates. Please choose another date range.']);
    exit;
}

// Calculate amounts
$nights = (new DateTime($in))->diff(new DateTime($out))->days;
$roomBaseAmount = $nights * (float)$room['price'];
$extraMattress = max(0, $guests - $baseCapacity);
$extraMattressCost = $extraMattress * $mattressRate * $nights;
$subtotal = $roomBaseAmount + $extraMattressCost;
$gstAmount = round($subtotal * ($gstPercent / 100), 2);
$totalAmount = $subtotal + $gstAmount;

// Determine payment status
$paymentStatus = ($paymentMethod === 'Pay on Arrival') ? 'Pending' : 'Paid';
$bookingStatus = ($paymentStatus === 'Paid') ? 'Confirmed' : 'Pending';

// Generate unique booking reference: e.g. HVN-2026-784920
$bookingRef = 'HVN-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

// Ensure uniqueness
$st = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_reference = ?');
$st->execute([$bookingRef]);
if ((int)$st->fetchColumn() > 0) {
    $bookingRef = 'HVN-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

// Insert booking with the user-provided guest name, email, phone and extra mattress
$st = $pdo->prepare('INSERT INTO bookings (booking_reference, user_id, room_id, guest_name, guest_email, guest_phone, check_in, check_in_time, check_out, check_out_time, guests, extra_mattress, extra_mattress_cost, total_amount, payment_method, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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

// Emit real-time live event for instant admin synchronization
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

echo json_encode([
    'success' => true,
    'booking_id' => $bookingId,
    'booking_reference' => $bookingRef,
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
    'price_per_night' => (float)$room['price'],
    'room_base_amount' => $roomBaseAmount,
    'subtotal' => $subtotal,
    'gst_percent' => $gstPercent,
    'gst_amount' => $gstAmount,
    'total_amount' => (float)$totalAmount,
    'payment_method' => $paymentMethod,
    'payment_status' => $paymentStatus,
    'status' => $bookingStatus
]);

