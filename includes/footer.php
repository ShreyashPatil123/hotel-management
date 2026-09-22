</main>
<footer>
    <div class="container footer-grid">
        <div>
            <a class="brand light" href="<?= e($rootRel ?? '') ?>index.php">
                <span class="brand-mark">H</span>
                <span>Haven Hotel</span>
            </a>
            <p>A calm, considered stay in the heart of the city. Thoughtful rooms, warm hospitality and an easier way to book.</p>
        </div>
        <div>
            <strong>Explore</strong>
            <a href="<?= e($rootRel ?? '') ?>rooms.php">Rooms & suites</a>
            <a href="<?= e($rootRel ?? '') ?>contact.php">Contact us</a>
            <a href="<?= e($rootRel ?? '') ?>login.php">Guest login</a>
        </div>
        <div>
            <strong>Reach us</strong>
            <span>Haven Hotel Front Desk</span>
            <span>24 Palm Avenue, New Delhi</span>
            <a href="<?= e($rootRel ?? '') ?>contact.php" style="color:var(--gold);text-decoration:underline;">Send online message &rarr;</a>
        </div>
    </div>
    <div class="container copyright">
        © <?= date('Y') ?> Haven Hotel <span aria-hidden="true">·</span> Real-time hospitality management
    </div>
</footer>
<div id="toast-container" class="toast-container" aria-live="polite"></div>
<script>document.querySelectorAll('.alert').forEach(a=>setTimeout(()=>a.remove(),5000));</script>
<script src="<?= e($rootRel ?? '') ?>js/mobile.js"></script>
<script src="<?= e($rootRel ?? '') ?>js/realtime.js"></script>
</body>
</html>
