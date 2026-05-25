
</div><!-- /page-content -->

<!-- Vendor-bestanden lokaal opgeslagen — app werkt ook zonder internet -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/js/clicktrack.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/clicktrack.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
