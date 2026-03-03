<?php
/**
 * Self-Screenshot: wenn ?wcr_screenshot=1 in der URL steht,
 * macht html2canvas die Seite zu einem JPG und startet direkt den Download
 * — kein REST, kein postMessage, kein Server-Speicher nötig.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (empty($_GET['wcr_screenshot'])) return;
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function () {
    // Status-Overlay damit der User sieht was passiert
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.7);'
        + 'display:flex;flex-direction:column;align-items:center;justify-content:center;'
        + 'color:#fff;font-family:sans-serif;font-size:1.2rem;gap:12px;';
    overlay.innerHTML = '<div style="width:48px;height:48px;border:4px solid rgba(255,255,255,.2);'
        + 'border-top-color:#3b82f6;border-radius:50%;animation:spin .7s linear infinite"></div>'
        + '<div>Screenshot wird erstellt…</div>'
        + '<style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
    document.body.appendChild(overlay);

    window.addEventListener('load', function () {
        // Warten bis Fonts + Animationen fertig sind
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
                logging         : false,
            }).then(function (canvas) {
                overlay.innerHTML = '<div>✓ Fertig — Download startet…</div>';

                // Canvas → Blob → direkter Download in diesem Fenster
                canvas.toBlob(function (blob) {
                    var url  = URL.createObjectURL(blob);
                    var date = new Date().toISOString().slice(0, 10);
                    var a    = document.createElement('a');
                    a.href     = url;
                    a.download = 'oeffnungszeiten_' + date + '.jpg';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () {
                        URL.revokeObjectURL(url);
                        // Fenster schließen falls Popup, sonst Overlay wegräumen
                        if (window.opener) {
                            window.opener.postMessage({ wcr_screenshot_done: true }, '*');
                            setTimeout(function () { window.close(); }, 800);
                        } else {
                            overlay.remove();
                        }
                    }, 1000);
                }, 'image/jpeg', 0.93);

            }).catch(function (err) {
                overlay.innerHTML = '<div style="color:#f87171">✗ Fehler: ' + err + '</div>';
            });
        }, 2500);
    });
}());
</script>
<?php
}, 99);
