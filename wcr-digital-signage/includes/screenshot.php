<?php
/**
 * Aktiviert Self-Screenshot-Mode wenn ?wcr_screenshot=1 in der URL steht.
 * Wird am Ende von functions.php per require eingebunden.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (empty($_GET['wcr_screenshot'])) return;
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    window.addEventListener('load', function () {
        // kurz warten bis Fonts/Animationen fertig
        setTimeout(function () {
            html2canvas(document.documentElement, {
                backgroundColor : null,
                scale           : 1,
                width           : 1080,
                height          : 1920,
                windowWidth     : 1080,
                windowHeight    : 1920,
                useCORS         : true,
                allowTaint      : false,
            }).then(function (canvas) {
                var jpg = canvas.toDataURL('image/jpeg', 0.93);
                fetch('<?= esc_url(rest_url('wakecamp/v1/screenshot')) ?>', {
                    method  : 'POST',
                    headers : { 'Content-Type': 'application/json' },
                    body    : JSON.stringify({
                        image      : jpg,
                        wcr_secret : 'WCR_DS_2026',
                    }),
                })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        // Signal ans BE-Fenster schicken
                        if (window.opener) {
                            window.opener.postMessage({ wcr_screenshot_done: true, url: d.url }, '*');
                        }
                        window.close();
                    } else {
                        alert('Fehler: ' + (d.error || 'unbekannt'));
                    }
                })
                .catch(function (e) { alert('Fetch-Fehler: ' + e); });
            });
        }, 2500);
    });
    </script>
    <?php
}, 99);
