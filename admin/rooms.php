<?php
$pageTitle = 'Manage Rooms';
require '../db/connection.php';
require '../includes/functions.php';
require_admin();

$edit = null;
$error = '';

if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    
    if (isset($_POST['delete'])) {
        try {
            $st = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
            $st->execute([$id]);
            emit_event($pdo, 'room_updated', [
                'room_id' => $id,
                'action' => 'deleted'
            ], null, null, $id);
            flash('main', 'Room deleted.');
        } catch (PDOException $e) {
            flash('main', 'Cannot delete a room with booking history.', 'error');
        }
        redirect('rooms.php');
    }
    
    // Determine image source
    $imagePath = trim($_POST['existing_image'] ?? '');
    
    // Check if a direct file was uploaded
    if (isset($_FILES['room_image_file']) && $_FILES['room_image_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['room_image_file'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime, $allowedMimes, true)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!$ext) $ext = 'jpg';
            $uploadDir = __DIR__ . '/../images/rooms/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = 'room_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            $target = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $imagePath = 'images/rooms/' . $filename;
            }
        } else {
            $error = 'Uploaded file must be an image (JPEG, PNG, WEBP, or GIF).';
        }
    } elseif (!empty($_POST['image_url'])) {
        $imagePath = trim($_POST['image_url']);
    }
    
    if (!$imagePath) {
        $imagePath = 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=900&q=80';
    }
    
    // Process gallery URLs
    $galleryUrls = [];
    if (!empty($_POST['gallery_urls'])) {
        $lines = preg_split('/\r\n|\r|\n/', $_POST['gallery_urls']);
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '') $galleryUrls[] = $l;
        }
    }
    if (empty($galleryUrls) && !empty($_POST['existing_gallery'])) {
        $galleryUrls = json_decode($_POST['existing_gallery'], true) ?: [];
    }
    if (empty($galleryUrls)) {
        $galleryUrls = [$imagePath];
    }
    $galleryJson = json_encode(array_values(array_unique($galleryUrls)), JSON_UNESCAPED_SLASHES);

    $capacity = (int)$_POST['capacity'];
    $maxCapacity = max($capacity, (int)($_POST['max_capacity'] ?? $capacity));
    $extraMattressRate = (float)($_POST['extra_mattress_rate'] ?? 800.00);

    $data = [
        trim($_POST['room_number']),
        trim($_POST['type']),
        (float)$_POST['price'],
        $capacity,
        $maxCapacity,
        $extraMattressRate,
        trim($_POST['description']),
        $imagePath,
        $galleryJson,
        $_POST['status']
    ];
    
    if (!$error) {
        if ($id) {
            $st = $pdo->prepare('UPDATE rooms SET room_number = ?, type = ?, price = ?, capacity = ?, max_capacity = ?, extra_mattress_rate = ?, description = ?, image = ?, gallery = ?, status = ? WHERE id = ?');
            $st->execute([...$data, $id]);
            $targetId = $id;
            $action = 'updated';
        } else {
            $st = $pdo->prepare('INSERT INTO rooms(room_number, type, price, capacity, max_capacity, extra_mattress_rate, description, image, gallery, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $st->execute($data);
            $targetId = (int)$pdo->lastInsertId();
            $action = 'created';
        }
        
        emit_event($pdo, 'room_updated', [
            'room_id' => $targetId,
            'room_number' => $data[0],
            'type' => $data[1],
            'price' => $data[2],
            'capacity' => $data[3],
            'max_capacity' => $data[4],
            'extra_mattress_rate' => $data[5],
            'image' => $imagePath,
            'gallery' => $galleryUrls,
            'status' => $data[9],
            'action' => $action
        ], null, null, $targetId);
        
        flash('main', 'Room saved successfully.');
        redirect('rooms.php');
    }
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY room_number')->fetchAll();
include '../includes/header.php';
?>
<section class="section">
    <div class="container">
        <span class="eyebrow">Inventory</span>
        <h1>Manage rooms</h1>
        <?php show_flash(); ?>
        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="split">
            <div class="panel">
                <h2><?= $edit ? 'Edit room' : 'Add a room' ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
                    <input type="hidden" name="existing_image" value="<?= e($edit['image'] ?? '') ?>">
                    <input type="hidden" name="existing_gallery" value="<?= e($edit['gallery'] ?? '') ?>">
                    
                    <div class="form-grid">
                        <div class="field">
                            <label>Room number</label>
                            <input name="room_number" required value="<?= e($edit['room_number'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Type</label>
                            <input name="type" required value="<?= e($edit['type'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Price/night (₹)</label>
                            <input type="number" name="price" required value="<?= e($edit['price'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Base Capacity (Guests)</label>
                            <input type="number" name="capacity" min="1" required value="<?= e($edit['capacity'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Max Capacity (With Extra Mattress)</label>
                            <input type="number" name="max_capacity" min="1" required value="<?= e($edit['max_capacity'] ?? $edit['capacity'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Extra Mattress Rate (₹ / day)</label>
                            <input type="number" name="extra_mattress_rate" min="0" step="50" required value="<?= e($edit['extra_mattress_rate'] ?? '800') ?>">
                        </div>
                        
                        <div class="field full">
                            <label>Main Cover Image</label>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <input type="file" name="room_image_file" id="room_image_file" accept="image/*" style="display:none;" onchange="previewRoomImage(this)">
                                    <button type="button" class="button alt small" onclick="document.getElementById('room_image_file').click()">
                                        📁 Choose image file
                                    </button>
                                    <span id="chosen-file-text" class="muted" style="font-size:0.85rem;">No new file selected</span>
                                </div>
                                <div id="preview-wrapper" style="<?= empty($edit['image']) ? 'display:none;' : '' ?>">
                                    <span class="muted" style="font-size:0.75rem;display:block;margin-bottom:4px;">Image Preview:</span>
                                    <img id="image-preview" src="<?= e(room_img_src($edit['image'] ?? '')) ?>" alt="Preview" style="max-height: 140px; max-width: 100%; border-radius: 8px; object-fit: cover; border: 1px solid var(--line); display: block;">
                                </div>
                                <details style="font-size: 0.82rem; color: var(--muted); margin-top: 4px;">
                                    <summary style="cursor: pointer;">Or enter an image URL instead</summary>
                                    <input name="image_url" placeholder="https://..." value="<?= (isset($edit['image']) && preg_match('#^https?://#i', $edit['image'])) ? e($edit['image']) : '' ?>" style="margin-top: 6px;">
                                </details>
                            </div>
                        </div>

                        <div class="field full">
                            <label>Room Photo Gallery (Multiple Relatable Views)</label>
                            <div style="font-size:0.82rem; color:var(--muted); margin-bottom:6px;">
                                Enter photo URLs for this room (e.g. Master Bedroom, Luxury Bathroom, Balcony/Garden View, Lounge/Desk). Enter one URL per line:
                            </div>
                            <?php
                            $galleryList = [];
                            if (!empty($edit['gallery'])) {
                                $decoded = json_decode($edit['gallery'], true);
                                if (is_array($decoded)) {
                                    $galleryList = $decoded;
                                }
                            }
                            $galleryText = implode("\n", $galleryList);
                            ?>
                            <textarea name="gallery_urls" rows="4" placeholder="https://images.unsplash.com/...&#10;https://images.unsplash.com/...&#10;https://images.unsplash.com/..." style="font-size:0.82rem; font-family:monospace;"><?= e($galleryText) ?></textarea>
                            <?php if (!empty($galleryList)): ?>
                                <div style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap;">
                                    <?php foreach ($galleryList as $gUrl): ?>
                                        <div style="position:relative;">
                                            <img src="<?= e(room_img_src($gUrl)) ?>" alt="Thumb" style="width:65px; height:45px; object-fit:cover; border-radius:6px; border:1px solid var(--line); display:block;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="field full">
                            <label>Description</label>
                            <textarea name="description" required><?= e($edit['description'] ?? '') ?></textarea>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="status">
                                <option <?= ($edit['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                                <option <?= ($edit['status'] ?? '') === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                <option <?= ($edit['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button class="button" type="submit">Save room</button>
                    <?php if ($edit): ?>
                        <a class="button alt" href="rooms.php">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="panel table-wrap">
                <table class="table" id="admin-rooms-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Price &amp; Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                            <tr id="admin-room-row-<?= $r['id'] ?>">
                                <td>
                                    <img src="<?= e(room_img_src($r['image'])) ?>" alt="<?= e($r['type']) ?>" style="width: 50px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); display: block;">
                                </td>
                                <td>
                                    <strong><?= e($r['room_number']) ?></strong>
                                    <?php $gCount = count(room_gallery_list($r)); ?>
                                    <div style="font-size:0.75rem; color:var(--muted); margin-top:2px;">
                                        📷 <?= $gCount ?> photo<?= $gCount === 1 ? '' : 's' ?>
                                    </div>
                                </td>
                                <td><?= e($r['type']) ?></td>
                                <td>
                                    <strong>₹<?= number_format($r['price']) ?></strong> / night<br>
                                    <small class="muted">Base: <?= (int)$r['capacity'] ?> &bull; Max: <?= (int)($r['max_capacity'] ?? $r['capacity']) ?> Guests</small><br>
                                    <span class="badge" style="background:#fef3e2; color:#92400e; font-size:0.7rem; border:1px solid #fde68a; display:inline-block; margin-top:2px;">
                                        +₹<?= number_format($r['extra_mattress_rate'] ?? 800) ?> / day
                                    </span>
                                </td>
                                <td><span class="badge <?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span></td>
                                <td class="actions">
                                    <a class="button alt small" href="?edit=<?= $r['id'] ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete room <?= e($r['room_number']) ?>?');">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button class="button danger small" name="delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<script>
function previewRoomImage(input) {
    const file = input.files[0];
    const textSpan = document.getElementById('chosen-file-text');
    const previewWrapper = document.getElementById('preview-wrapper');
    const previewImg = document.getElementById('image-preview');
    
    if (file) {
        textSpan.textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewWrapper.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        textSpan.textContent = 'No new file selected';
    }
}
</script>
<?php include '../includes/footer.php'; ?>
