<?php 
$pageTitle = 'Welcome to Haven Hotel'; 
require 'db/connection.php'; 
require_once 'includes/functions.php';
$rooms = $pdo->query("SELECT * FROM rooms WHERE status='Available' ORDER BY price LIMIT 3")->fetchAll(); 
include 'includes/header.php'; 
?>
<section class="hero">
    <div class="container hero-content">
        <span class="eyebrow">Stay a little longer</span>
        <h1>Thoughtful comfort in the heart of the city.</h1>
        <p>Relax in beautifully designed rooms, enjoy warm hospitality and make your next stay feel effortless.</p>
        <div class="hero-actions">
            <a class="button" href="rooms.php">Explore rooms <span aria-hidden="true">→</span></a>
            <a class="button alt" href="contact.php">Plan your stay</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Our rooms</span>
                <h2>Find your kind of comfort</h2>
            </div>
            <a href="rooms.php">View all rooms <span aria-hidden="true">→</span></a>
        </div>
        <div class="cards">
            <?php foreach($rooms as $room): 
                $gallery = room_gallery_list($room);
            ?>
            <article class="card">
                <div style="position:relative; overflow:hidden; border-radius:12px 12px 0 0;">
                    <img class="room-img" loading="lazy" src="<?= e(room_img_src($room['image'])) ?>" alt="<?= e($room['type']) ?>">
                    <span style="position:absolute; bottom:10px; right:10px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.75rem; padding:3px 8px; border-radius:20px; font-weight:600; backdrop-filter:blur(4px); box-shadow:0 2px 6px rgba(0,0,0,0.2);">
                        📷 <?= count($gallery) ?> Photos
                    </span>
                </div>
                <div class="card-body">
                    <h3><?= e($room['type']) ?></h3>
                    <div class="meta">
                        <span>Room <?= e($room['room_number']) ?></span>
                        <span>👥 Base: <strong><?= e($room['capacity']) ?></strong></span>
                        <span>Max: <strong style="color:var(--teal);"><?= e($room['max_capacity'] ?? ($room['capacity'] + 1)) ?> Guests</strong></span>
                    </div>
                    <div style="font-size:0.76rem; background:#f4f9f7; padding:5px 9px; border-radius:6px; margin-bottom:10px; border:1px solid #dbeae6; color:var(--teal-dark);">
                        🛏️ Extra mattress @ <strong>₹<?= number_format($room['extra_mattress_rate'] ?? 800) ?>/day</strong>
                    </div>
                    <div class="actions">
                        <div class="price">₹<?= number_format($room['price']) ?> <small>/ night (+12% GST)</small></div>
                        <a class="button small" href="booking.php?room_id=<?= $room['id'] ?>">View room</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background:var(--sand, #fdfbf7); border-top:1px solid var(--line, #e2dcd5); border-bottom:1px solid var(--line, #e2dcd5);">
    <div class="container">
        <div class="section-head" style="margin-bottom:28px;">
            <div>
                <span class="eyebrow">Immersive Experience</span>
                <h2>Life at Haven Hotel</h2>
                <p style="margin:6px 0 0; color:var(--muted); max-width:600px;">
                    From sun-drenched terrace mornings to tranquil wellness retreats, experience thoughtful amenities crafted for your comfort.
                </p>
            </div>
            <a href="rooms.php" class="button alt small">Explore all rooms <span aria-hidden="true">→</span></a>
        </div>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px;">
            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80" alt="Grand Lobby & Lounge" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Arrival</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">The Grand Lobby & Lounge</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">24/7 concierge, double-height ceilings, and quiet conversation alcoves with fresh botanicals.</p>
                </div>
            </div>

            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=800&q=80" alt="Rooftop Infinity Pool" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Relaxation</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">Skyline Infinity Pool</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">Temperature-controlled heated pool with panoramic skyline views, sun loungers and cabana service.</p>
                </div>
            </div>

            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80" alt="The Verandah Restaurant" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Dining</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">The Verandah Restaurant</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">Artisanal breakfast buffet, farm-to-table lunch specialties and intimate candlelit dinners.</p>
                </div>
            </div>

            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80" alt="Lotus Wellness Spa" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Wellness</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">Lotus Wellness & Spa</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">Holistic Ayurvedic massages, aroma therapy suites, herbal steam room and yoga studio.</p>
                </div>
            </div>

            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=800&q=80" alt="Artisan Cafe & Bakery" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Café</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">Artisan Coffee & Patisserie</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">Single-origin roasted espresso, flaky morning croissants and high-speed Wi-Fi work zones.</p>
                </div>
            </div>

            <div style="background:var(--surface, #fff); border-radius:14px; overflow:hidden; border:1px solid var(--line); box-shadow:0 4px 14px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
                <div style="height:190px; overflow:hidden; position:relative;">
                    <img src="https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=800&q=80" alt="Courtyard Gardens" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position:absolute; top:12px; left:12px; background:rgba(18,61,58,0.85); color:#fff; font-size:0.72rem; padding:3px 9px; border-radius:12px; font-weight:600;">Outdoors</span>
                </div>
                <div style="padding:16px;">
                    <h3 style="margin:0 0 6px; font-size:1.05rem;">Courtyard & Sunset Terrace</h3>
                    <p style="margin:0; font-size:0.86rem; color:var(--muted); line-height:1.45;">Lush tropical garden walkways, evening fire pits and gentle fountain water features.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background:var(--teal-pale)">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">The Haven promise</span>
                <h2>Everything you need, nothing you don't.</h2>
            </div>
        </div>
        <div class="feature">
            <div>
                <strong>Easy, honest booking</strong>
                <span class="muted">Clear pricing and real-time date availability, right from the start.</span>
            </div>
            <div>
                <strong>Warm local hospitality</strong>
                <span class="muted">A friendly team ready to make your stay comfortable and personal.</span>
            </div>
            <div>
                <strong>Central and convenient</strong>
                <span class="muted">Close to business districts, cafés and the city’s best local spots.</span>
            </div>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
