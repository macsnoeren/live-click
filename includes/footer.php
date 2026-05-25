
</div><!-- /page-content -->

<!-- Vendor-bestanden lokaal opgeslagen — app werkt ook zonder internet -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/js/clicktrack.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/clicktrack.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
<script>
(function() {
    var LS_MAX = 5 * 1024 * 1024; // 5 MB

    function updateLsUsage() {
        var el = document.getElementById('ls-usage');
        if (!el) return;
        try {
            var bytes = 0;
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                bytes += (k.length + (localStorage.getItem(k) || '').length) * 2;
            }
            var kb   = bytes / 1024;
            var pct  = Math.min(100, Math.round(bytes / LS_MAX * 100));
            var txt  = kb < 1 ? '<1 KB' : Math.round(kb) + ' KB';
            var tip  = 'Lokale cache: ' + txt + ' van 5 MB (' + pct + '%)';

            // Lazy-build inner structure once
            var fill = el.querySelector('.ls-usage-fill');
            var lbl  = el.querySelector('.ls-usage-label');
            if (!fill) {
                el.innerHTML =
                    '<span class="ls-usage-bar"><span class="ls-usage-fill"></span></span>' +
                    '<span class="ls-usage-label"></span>';
                fill = el.querySelector('.ls-usage-fill');
                lbl  = el.querySelector('.ls-usage-label');
            }

            fill.style.width = pct + '%';
            lbl.textContent  = txt;
            el.title         = tip;
            el.className     = 'ls-usage' +
                (pct >= 90 ? ' ls-full' : pct >= 60 ? ' ls-warn' : '');
        } catch (e) {
            el.innerHTML = ''; // private browsing of quota error
        }
    }
    updateLsUsage();
    window.addEventListener('storage', updateLsUsage);
    window.updateLsUsage = updateLsUsage;
})();
</script>
</body>
</html>
