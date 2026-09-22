<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$validStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

if (!$id || !in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Fetch booking info before update
$st = $pdo->prepare('SELECT b.*, r.type AS room_type, r.room_number FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?');
$st->execute([$id]);
$booking = $st->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

$prevStatus = $booking['status'];

if ($status === 'Cancelled') {
    $cancelledBy = trim($_POST['cancelled_by'] ?? 'Hotel');
    if (!in_array($cancelledBy, ['Customer', 'Hotel'], true)) {
        $cancelledBy = 'Hotel';
    }
    $reason = trim($_POST['cancellation_reason'] ?? '');
    if (!$reason) {
        $reason = ($cancelledBy === 'Hotel') ? 'Operational Maintenance & Safety Requirements' : 'Customer cancellation request';
    }

    if ($cancelledBy === 'Hotel') {
        $refund = (float)$booking['total_amount'];
        $fee = 0.00;
        $refundStatus = ($booking['payment_status'] === 'Paid') ? '100% Full Charges Reverted to Original Payment Source' : 'No Payment Collected';
    } else {
        $checkInTime = strtotime($booking['check_in'] . ' 14:00:00');
        $now = time();
        $hoursUntilCheckIn = ($checkInTime - $now) / 3600;
        $totalAmount = (float)$booking['total_amount'];
        $isPaid = ($booking['payment_status'] === 'Paid');

        if ($hoursUntilCheckIn < 48) {
            $fee = round($totalAmount * 0.10, 2);
            $refund = max(0.00, $totalAmount - $fee);
            $refundStatus = $isPaid ? '90% Refund Initiated (10% Late cancellation fee deducted)' : 'Cancelled (Late cancellation fee may apply)';
        } else {
            $fee = 0.00;
            $refund = $totalAmount;
            $refundStatus = $isPaid ? '100% Refund Initiated (Reverting in 2-3 business days)' : 'No Charges (Free cancellation window)';
        }
    }

    $st = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_by = ?, cancellation_reason = ?, cancellation_fee = ?, refund_amount = ?, refund_status = ? WHERE id = ?");
    $st->execute([$cancelledBy, $reason, $fee, $refund, $refundStatus, $id]);
} else {
    $st = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    $st->execute([$status, $id]);
    $cancelledBy = $booking['cancelled_by'];
    $reason = $booking['cancellation_reason'];
    $fee = (float)$booking['cancellation_fee'];
    $refund = (float)$booking['refund_amount'];
    $refundStatus = $booking['refund_status'];
}

// Emit event for real-time live synchronization
$eventId = emit_event(
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
        'cancelled_by' => $cancelledBy,
        'cancellation_reason' => $reason,
        'cancellation_fee' => $fee,
        'refund_amount' => $refund,
        'refund_status' => $refundStatus
    ],
    (int)$booking['user_id'],
    $id,
    (int)$booking['room_id']
);

echo json_encode([
    'success' => true,
    'event_id' => $eventId,
    'booking_id' => $id,
    'previous_status' => $prevStatus,
    'status' => $status,
    'cancelled_by' => $cancelledBy,
    'cancellation_reason' => $reason,
    'refund_amount' => $refund,
    'refund_status' => $refundStatus
]);
