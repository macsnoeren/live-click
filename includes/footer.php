
</div><!-- /page-content -->

<!-- Vendor-bestanden lokaal opgeslagen — app werkt ook zonder internet -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/js/clicktrack.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/clicktrack.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
<script>
(function() {
    function updateLsUsage() {
        var el = document.getElementById('ls-usage');
        if (!el) return;
        try {
            var bytes = 0;
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                bytes += (k.length + (localStorage.getItem(k) || '').length) * 2;
            }
            var kb  = bytes / 1024;
            var txt = kb < 1 ? '<1 KB' : Math.round(kb) + ' KB';
            // Browsers typically cap localStorage at 5–10 MB; warn at 3 MB, red at 4.5 MB
            el.textContent = txt;
            el.title = 'Lokale cache (localStorage): ' + txt;
            el.className = 'ls-usage' +
                (bytes > 4718592 ? ' ls-full' : bytes > 3145728 ? ' ls-warn' : '');
        } catch (e) {
            // Private browsing or quota exceeded — hide silently
            el.textContent = '';
        }
    }
    updateLsUsage();
    window.addEventListener('storage', updateLsUsage);
    // Also expose so app.js can call it after a cache write
    window.updateLsUsage = updateLsUsage;
})();
</script>
</body>
</html>
