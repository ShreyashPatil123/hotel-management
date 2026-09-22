<?php
$pageTitle = 'Rooms & Suites';
require 'db/connection.php';
require 'includes/functions.php';

$type = trim($_GET['type'] ?? '');
$max = (float)($_GET['max_price'] ?? 0);
$sql = "SELECT * FROM rooms WHERE status='Available'";
$params = [];

if ($type !== '') {
    $sql .= ' AND type LIKE ?';
    $params[] = '%' . $type . '%';
}
if ($max > 0) {
    $sql .= ' AND price <= ?';
    $params[] = $max;
}
$sql .= ' ORDER BY price';
$st = $pdo->prepare($sql);
$st->execute($params);
$rooms = $st->fetchAll();

include 'includes/header.php';
?>
<section class="section">
    <div class="container">
        <span class="eyebrow">Sleep well</span>
        <h1>Rooms &amp; suites</h1>
        <p class="lead">Choose a room that fits your plans. Explore high-resolution photos of our rooms and suites below.</p>
        <form class="filter panel" method="get" aria-label="Filter available rooms">
            <div class="field">
                <label for="type">Room type</label>
                <input id="type" name="type" value="<?= e($type) ?>" placeholder="e.g. Suite">
            </div>
            <div class="field">
                <label for="max_price">Maximum price</label>
                <input id="max_price" type="number" name="max_price" value="<?= e($max ?: '') ?>" min="0" placeholder="₹ per night">
            </div>
            <button class="button" type="submit">Apply filters <span aria-hidden="true">→</span></button>
            <?php if ($type !== '' || $max > 0): ?>
                <a class="button alt" href="rooms.php">Clear</a>
            <?php endif; ?>
        </form>
        <div class="section-head" style="margin-top:32px">
            <div>
                <span class="muted" id="rooms-count-label"><?= count($rooms) ?> room<?= count($rooms) === 1 ? '' : 's' ?> available</span>
            </div>
        </div>
        <div class="cards" id="rooms-container">
            <?php if (!$rooms): ?>
                <div class="panel empty" id="no-rooms-message" style="grid-column:1/-1">No rooms match those filters. Try widening your search.</div>
            <?php endif; ?>
            <?php foreach ($rooms as $room): 
                $gallery = room_gallery_list($room);
            ?>
                <article class="card room-card" id="room-card-<?= $room['id'] ?>" data-room-id="<?= $room['id'] ?>">
                    <div style="position:relative; overflow:hidden;">
                        <img class="room-img" id="main-photo-<?= $room['id'] ?>" loading="lazy" src="<?= e($gallery[0] ?? room_img_src($room['image'])) ?>" alt="<?= e($room['type']) ?>" style="transition:opacity 0.25s ease;">
                        <span style="position:absolute; top:12px; right:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:20px; backdrop-filter:blur(6px); display:flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            <?= count($gallery) ?> Photos
                        </span>
                    </div>

                    <?php if (count($gallery) > 1): ?>
                        <div class="room-gallery-thumbs" id="thumbs-<?= $room['id'] ?>" style="display:flex; gap:6px; padding:10px 16px 4px; overflow-x:auto; -webkit-overflow-scrolling:touch; touch-action:pan-x;">
                            <?php foreach ($gallery as $idx => $gImg): ?>
                                <img src="<?= e($gImg) ?>" alt="<?= e($room['type']) ?> photo <?= $idx + 1 ?>" 
                                     class="room-thumb-btn <?= $idx === 0 ? 'active' : '' ?>" 
                                     style="width:50px; height:36px; object-fit:cover; border-radius:6px; cursor:pointer; border:2px solid <?= $idx === 0 ? 'var(--teal)' : 'transparent' ?>; opacity:<?= $idx === 0 ? '1' : '0.65' ?>; transition:all 0.2s;"
                                     onclick="changeRoomMainPhoto(<?= $room['id'] ?>, '<?= e($gImg) ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h3><?= e($room['type']) ?></h3>
                        <p class="muted"><?= e($room['description']) ?></p>
                        <div class="meta">
                            <span>Room <?= e($room['room_number']) ?></span>
                            <span>👥 Base: <strong><?= e($room['capacity']) ?></strong></span>
                            <span>Max: <strong style="color:var(--teal);"><?= e($room['max_capacity'] ?? ($room['capacity'] + 1)) ?> Guests</strong></span>
                        </div>
                        <div style="font-size:0.78rem; background:#f4f9f7; padding:6px 10px; border-radius:6px; margin-bottom:12px; border:1px solid #dbeae6; color:var(--teal-dark); display:flex; align-items:center; gap:6px;">
                            <span>🛏️ Extra mattress @ <strong>₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?>/day</strong> for extra guests</span>
                        </div>
                        <div style="font-size:0.74rem; color:var(--muted); margin-bottom:14px; display:flex; align-items:center; gap:5px;">
                            <span>🛡️ Free cancellation up to 48h before check-in</span>
                        </div>
                        <div class="actions">
                            <span class="price">₹<?= number_format($room['price']) ?> <small>/ night (+12% GST)</small></span>
                            <a class="button" href="booking.php?room_id=<?= $room['id'] ?>">Book now <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
function changeRoomMainPhoto(roomId, imgUrl, thumbEl) {
    const mainImg = document.getElementById('main-photo-' + roomId);
    if (!mainImg) return;
    mainImg.style.opacity = '0.4';
    setTimeout(() => {
        mainImg.src = imgUrl;
        mainImg.style.opacity = '1';
    }, 150);

    const container = document.getElementById('thumbs-' + roomId);
    if (container) {
        container.querySelectorAll('.room-thumb-btn').forEach(btn => {
            btn.style.borderColor = 'transparent';
            btn.style.opacity = '0.65';
        });
        thumbEl.style.borderColor = 'var(--teal)';
        thumbEl.style.opacity = '1';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
