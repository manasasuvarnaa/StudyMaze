<?php
// includes/footer.php - Global Footer & Script Attachments
declare(strict_types=1);
?>

  <!-- Toast Notification Container -->
  <div id="toastContainer" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none;"></div>

  <!-- Shared JavaScript (with cache busting) -->
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>

  <script>
    // Initialize Lucide icons
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>
