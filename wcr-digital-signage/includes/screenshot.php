<?php
/**
 * Self-Screenshot: wenn ?wcr_screenshot=1 in der URL steht,
 * macht html2canvas die Seite zu einem JPG und startet direkt den Download.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (empty($_GET['wcr_screenshot'])) return;
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
    /* Alles ausblenden was nicht ins Bild soll */
    #wpadminbar,
    #wpadminbar * { display: none !important; }
    html { margin-top: 0 !important; }
    ::-webkit-scrollbar { display: none; }
</style>
<script>
(function () {
    window.addEventListener('load', function () {
        // Admin-Bar komplett entfernen
        var adminBar = document.getElementById('wpadminbar');
        if (adminBar) adminBar.remove();
        // html margin-top zurücksetzen (WordPress setzt 32px für Admin-Bar)
        document.documentElement.style.marginTop = '0';
        document.body.style.marginTop = '0';
        // Ganz nach oben scrollen
        window.scrollTo(0, 0);

        setTimeout(function () {
            html2canvas(document.body, {
                backgroundColor : null,
                scale           : 1,
                width           : 1080,
                height          : 1920,
                windowWidth     : 1080,
                windowHeight    : 1920,
                scrollX         : 0,
                scrollY         : 0,
                x               : 0,
                y               : 0,
                useCORS         : true,
                allowTaint      : false,
                logging         : false,
            }).then(function (canvas) {
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
                        if (window.opener) {
                            window.opener.postMessage({ wcr_screenshot_done: true }, '*');
                            setTimeout(function () { window.close(); }, 800);
                        }
                    }, 500);
                }, 'image/jpeg', 0.93);

            }).catch(function (err) {
                alert('✗ Screenshot-Fehler: ' + err);
            });
        }, 2500);
    });
}());
</script>
<?php
}, 99);
