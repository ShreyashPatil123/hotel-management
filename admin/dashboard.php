<?php
$pageTitle = 'Admin Dashboard';
require '../db/connection.php';
require '../includes/functions.php';
require_admin();

$stats = [];
$stats['rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$stats['available'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
$stats['bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$stats['customers'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$recent = $pdo->query("SELECT b.*, r.type, u.name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

include '../includes/header.php';
?>
<section class="section">
    <div class="container">
        <span class="eyebrow">Management overview</span>
        <h1>Good day, <?= e($_SESSION['admin']['name']) ?></h1>
        <p class="lead">A quick view of your property’s rooms, guests and latest reservations.</p>
        <div class="actions" style="margin:26px 0 34px">
            <a class="button" href="rooms.php">Manage rooms <span aria-hidden="true">→</span></a>
            <a class="button alt" href="bookings.php">Review bookings</a>
        </div>
        <div class="stats">
            <div class="stat">
                <strong id="stat-rooms"><?= $stats['rooms'] ?></strong>
                <span>Total rooms</span>
            </div>
            <div class="stat">
                <strong id="stat-available"><?= $stats['available'] ?></strong>
                <span>Available now</span>
            </div>
            <div class="stat">
                <strong id="stat-bookings"><?= $stats['bookings'] ?></strong>
                <span>Total bookings</span>
            </div>
            <div class="stat">
                <strong id="stat-customers"><?= $stats['customers'] ?></strong>
                <span>Customers</span>
            </div>
        </div>
        <div class="section-head" style="margin-top:56px">
            <div>
                <span class="eyebrow">Latest activity</span>
                <h2>Recent bookings</h2>
            </div>
            <a href="bookings.php">Manage all <span aria-hidden="true">→</span></a>
        </div>
        <div class="panel table-wrap">
            <table class="table" id="recent-bookings-table">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Dates</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="recent-bookings-tbody">
                    <?php if (!$recent): ?>
                        <tr id="no-recent-bookings-row">
                            <td colspan="4" class="empty">No bookings yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $b): ?>
                        <tr id="recent-booking-row-<?= $b['id'] ?>" data-booking-id="<?= $b['id'] ?>">
                            <td><strong><?= e($b['name']) ?></strong></td>
                            <td><?= e($b['type']) ?></td>
                            <td><?= e($b['check_in']) ?> → <?= e($b['check_out']) ?></td>
                            <td>
                                <span class="badge <?= strtolower($b['status']) ?>" id="recent-badge-<?= $b['id'] ?>">
                                    <?= e($b['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include '../includes/footer.php'; ?>
