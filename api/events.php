<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/functions.php';

$isAdmin = !empty($_SESSION['admin']);
$userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : -1;

// If client connects for the first time without specifying a known last_id,
// initialize them with the latest event id so they don't get flooded with historical events.
if ($lastId < 0) {
    $latestId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM realtime_events')->fetchColumn();
    $response = [
        'success' => true,
        'last_id' => $latestId,
        'events' => [],
        'role' => $isAdmin ? 'admin' : ($userId ? 'customer' : 'guest')
    ];
    if ($isAdmin) {
        $response['stats'] = [
            'rooms' => (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
            'available' => (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn(),
            'bookings' => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
            'customers' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        ];
    }
    echo json_encode($response);
    exit;
}

// Fetch new events since $lastId
if ($isAdmin) {
    $st = $pdo->prepare('SELECT id, event_type, user_id, booking_id, room_id, payload, created_at FROM realtime_events WHERE id > ? ORDER BY id ASC LIMIT 50');
    $st->execute([$lastId]);
} elseif ($userId) {
    $st = $pdo->prepare('SELECT id, event_type, user_id, booking_id, room_id, payload, created_at FROM realtime_events WHERE id > ? AND (user_id = ? OR user_id IS NULL OR event_type IN ("room_updated")) ORDER BY id ASC LIMIT 50');
    $st->execute([$lastId, $userId]);
} else {
    $st = $pdo->prepare('SELECT id, event_type, user_id, booking_id, room_id, payload, created_at FROM realtime_events WHERE id > ? AND event_type IN ("room_updated") ORDER BY id ASC LIMIT 50');
    $st->execute([$lastId]);
}

$rawEvents = $st->fetchAll();
$events = [];
$newLastId = $lastId;

foreach ($rawEvents as $row) {
    $rowId = (int)$row['id'];
    if ($rowId > $newLastId) {
        $newLastId = $rowId;
    }
    $payload = json_decode($row['payload'], true) ?: [];
    $events[] = [
        'id' => $rowId,
        'event_type' => $row['event_type'],
        'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
        'booking_id' => $row['booking_id'] ? (int)$row['booking_id'] : null,
        'room_id' => $row['room_id'] ? (int)$row['room_id'] : null,
        'payload' => $payload,
        'created_at' => $row['created_at'],
    ];
}

$response = [
    'success' => true,
    'last_id' => $newLastId,
    'events' => $events,
    'role' => $isAdmin ? 'admin' : ($userId ? 'customer' : 'guest')
];

if ($isAdmin) {
    $response['stats'] = [
        'rooms' => (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
        'available' => (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn(),
        'bookings' => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'customers' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    ];
}

echo json_encode($response);
