</main>
<footer class="site-footer">
  <div class="footer-main">
    <div class="footer-brand"><a class="brand" href="index.php"><span class="brand-mark">AG</span><span>Amazing Grace<small>BEAUTY SALON</small></span></a><p>Rooted in care. Made for your crown.</p></div>
    <div><h3>Explore</h3><a href="index.php?page=about">Our story</a><a href="index.php?page=styles">Natural styles</a><a href="index.php?page=services">Salon services</a><a href="index.php?page=shop">Shop hair care</a></div>
    <div><h3>Visit us</h3><p><?= h(setting('salon_address', 'Accra, Ghana')) ?></p><p><?= h(setting('salon_hours', 'Tue–Sat, 9:00 AM–6:00 PM')) ?></p><a href="tel:<?= h(preg_replace('/[^+0-9]/', '', setting('salon_phone', '+233 20 000 0000'))) ?>"><?= h(setting('salon_phone', '+233 20 000 0000')) ?></a></div>
    <div><h3>Stay in touch</h3><p>Have a question? We would love to hear from you.</p><a href="index.php?page=contact">Send us a message ↗</a><div class="social-links"><a href="https://instagram.com" aria-label="Instagram">Instagram</a><a href="https://facebook.com" aria-label="Facebook">Facebook</a></div></div>
  </div>
  <div class="footer-bottom"><span>© <?= date('Y') ?> Amazing Grace Beauty Salon</span><a href="index.php?page=admin-login">Salon team</a><span>Made with care in Ghana</span></div>
</footer>
<script src="assets/js/app.js" defer></script>
</body></html>
