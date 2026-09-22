<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: '.$url); exit; }
function flash($key, $message, $type='success') { $_SESSION['flash'][$key] = ['message'=>$message, 'type'=>$type]; }
function show_flash($key='main') { if (!empty($_SESSION['flash'][$key])) { $f=$_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); echo '<div class="alert '.e($f['type']).'">'.e($f['message']).'</div>'; } }
function require_login() { if (empty($_SESSION['user'])) { flash('main','Please log in to continue.','error'); redirect('login.php'); } }
function require_admin() { if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; } }
function overlap_exists(PDO $pdo, $roomId, $checkIn, $checkOut, $excludeId=0) { $sql="SELECT COUNT(*) FROM bookings WHERE room_id=? AND id<>? AND status IN ('Pending','Confirmed') AND check_in < ? AND check_out > ?"; $st=$pdo->prepare($sql); $st->execute([$roomId,$excludeId,$checkOut,$checkIn]); return (int)$st->fetchColumn() > 0; }
function room_available(PDO $pdo, $roomId, $checkIn, $checkOut, $excludeId=0) { $st=$pdo->prepare("SELECT status FROM rooms WHERE id=?"); $st->execute([$roomId]); $room=$st->fetch(); return $room && $room['status']==='Available' && !overlap_exists($pdo,$roomId,$checkIn,$checkOut,$excludeId); }
function emit_event(PDO $pdo, string $eventType, array $payload = [], ?int $userId = null, ?int $bookingId = null, ?int $roomId = null) {
    try {
        $st = $pdo->prepare('INSERT INTO realtime_events (event_type, user_id, booking_id, room_id, payload) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$eventType, $userId, $bookingId, $roomId, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}
function room_img_src(?string $img): string {
    if (!$img) {
        return 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=900&q=80';
    }
    if (preg_match('#^(https?://|data:)#i', $img)) {
        return $img;
    }
    $inAdmin = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false);
    return ($inAdmin ? '../' : '') . ltrim($img, '/');
}
function room_gallery_list(array $room): array {
    $list = [];
    if (!empty($room['image'])) {
        $list[] = room_img_src($room['image']);
    }
    if (!empty($room['gallery'])) {
        $decoded = is_string($room['gallery']) ? json_decode($room['gallery'], true) : $room['gallery'];
        if (is_array($decoded)) {
            foreach ($decoded as $img) {
                $src = room_img_src($img);
                if (!in_array($src, $list, true)) {
                    $list[] = $src;
                }
            }
        }
    }
    if (empty($list)) {
        $list[] = 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80';
    }
    return $list;
}
?>
