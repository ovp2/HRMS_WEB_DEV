        </main>
        
        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <p>&copy; <?= date('Y') ?> <?= APP_FULL_NAME ?>. All rights reserved.</p>
                <p class="footer-version">v<?= APP_VERSION ?></p>
            </div>
        </footer>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- App JS -->
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?php if (isset($pageScript)): ?>
    <script src="<?= APP_URL ?>/assets/js/<?= $pageScript ?>"></script>
    <?php endif; ?>
</body>
</html>
