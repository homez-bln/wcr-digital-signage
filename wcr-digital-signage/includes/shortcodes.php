<?php
if (!defined('ABSPATH')) exit;

// ── Random Background ──
if (!function_exists('random_background_shortcode')) {
    function random_background_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'folder'  => 'images/backgrounds',
            'element' => 'div',
            'id'      => '',
            'class'   => '',
            'width'   => '100%',
            'height'  => '',
        ), $atts, 'random_bg');

        $folder_path = trailingslashit(get_stylesheet_directory() . '/' . $atts['folder']);
        $folder_url  = trailingslashit(get_stylesheet_directory_uri() . '/' . $atts['folder']);
        $images      = glob($folder_path . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

        $bg_url = ($images && count($images) > 0)
            ? $folder_url . basename($images[array_rand($images)])
            : '';

        $style = '';
        if ($bg_url) {
            $style .= "background-image: url('$bg_url');background-size:cover;background-position:center center;";
        }
        if (!empty($atts['width']))  $style .= 'width:'  . esc_attr($atts['width'])  . ';';
        if (!empty($atts['height'])) $style .= 'height:' . esc_attr($atts['height']) . ';';

        $output = '<' . esc_attr($atts['element']);
        if (!empty($atts['id']))    $output .= ' id="'    . esc_attr($atts['id'])    . '"';
        if (!empty($atts['class'])) $output .= ' class="' . esc_attr($atts['class']) . '"';
        if ($style)                 $output .= ' style="' . esc_attr($style)         . '"';
        $output .= '>';
        if ($content) $output .= do_shortcode($content);
        $output .= '</' . esc_attr($atts['element']) . '>';
        return $output;
    }
    add_shortcode('random_bg', 'random_background_shortcode');
}

// ── Öffnungszeiten ──
// Mo–So, Anfängerkurs-Badge inline in Sa/So-Zeile
if (!function_exists('opening_hours_pixel_perfect')) {
    function opening_hours_pixel_perfect($atts) {
        $atts       = shortcode_atts(array('tage' => 7), $atts);
        $externe_db = get_ionos_db_connection();
        if (!$externe_db) return '';

        $tz     = new DateTimeZone('Europe/Berlin');
        $today  = new DateTime('now', $tz);
        $dow    = (int)$today->format('N'); // 1=Mo … 7=So
        $montag = (clone $today)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
        $sonntag = (clone $today)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');

        // Mo–So + Kursdaten in einer Abfrage
        $query = $externe_db->prepare(
            "SELECT datum, start_time, end_time, is_closed,
                    COALESCE(course1, 0)      AS course1,
                    COALESCE(course1_text,'') AS course1_text,
                    COALESCE(course2, 0)      AS course2,
                    COALESCE(course2_text,'') AS course2_text
             FROM opening_hours
             WHERE datum >= %s AND datum <= %s
             ORDER BY datum ASC",
            $montag, $sonntag
        );
        $results         = $externe_db->get_results($query);
        $wochentage_kurz = [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa',7=>'So'];
        $lat = 52.1750;
        $lon = 13.1500;

        ob_start();

        // CSS einmalig ausgeben
        static $css_done = false;
        if (!$css_done) {
            $css_done = true;
            echo '<style>
/* Anfängerkurs-Badge inline */
.col-6-kurs {
    padding-left: 10px;
    vertical-align: middle;
    white-space: nowrap;
}
.oh-kurs-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .04em;
    padding: 5px 13px;
    border-radius: 30px;
    background: rgba(103,148,103,.13);
    color: var(--clr-green, #679467);
    border: 1px solid rgba(103,148,103,.35);
    line-height: 1;
    white-space: nowrap;
}
.oh-kurs-badge .kb-icon { font-size: 15px; }
/* Sa/So-Trennlinie */
.oh-weekend-sep td {
    border-top: 1px solid rgba(255,255,255,.10) !important;
    padding-top: 10px !important;
}
</style>' . "\n";
        }

        if ($results) {
            echo '<div class="oh-glass"><table class="oh-table">';

            $prevWasWeekday = false;

            foreach ($results as $row) {
                $timestamp  = strtotime($row->datum);
                $day_number = (int)date('N', $timestamp); // 1=Mo…7=So
                $tag        = $wochentage_kurz[$day_number] ?? date('D', $timestamp);
                $isWeekend  = ($day_number >= 6);
                $isClosed   = !empty($row->is_closed) && (int)$row->is_closed === 1;
                $hasStart   = !empty($row->start_time) && $row->start_time !== 'NULL';
                $hasEnd     = !empty($row->end_time)   && $row->end_time   !== 'NULL';

                // Kurs-Daten
                $c1     = (int)($row->course1 ?? 0);
                $c2     = (int)($row->course2 ?? 0);
                $c1txt  = trim($row->course1_text ?? '');
                $c2txt  = trim($row->course2_text ?? '');
                $hasKurs = $isWeekend && ($c1 || $c2);

                // Trennlinie wenn Sa folgt auf Fr
                $sepClass = ($isWeekend && !$prevWasWeekday) ? ' oh-weekend-sep' : '';
                $prevWasWeekday = $isWeekend;

                // ── GESCHLOSSEN ──
                if ($isClosed) {
                    echo '<tr class="geschlossen' . $sepClass . '">';
                    echo '<td class="col-1-day">'   . esc_html($tag) . ':</td>';
                    echo '<td class="col-2-start" colspan="3" style="text-align:center;letter-spacing:.15em;font-size:28px;color:rgba(255,59,48,.7)">GESCHLOSSEN</td>';
                    echo '<td class="col-5-unit"></td>';
                    echo '<td class="col-6-kurs"></td>';
                    echo '</tr>';
                    continue;
                }

                // Sa/So ohne Öffnungszeit: nur anzeigen wenn Kurs aktiv
                if ($isWeekend && !$hasStart && !$hasKurs) continue;

                // Mo–Fr ohne Öffnungszeit: überspringen
                if (!$isWeekend && !$hasStart) continue;

                // Startzeit
                $start = '';
                if ($hasStart) {
                    $start = substr($row->start_time, 0, 5);
                    if (substr($start, 2) === ':00') $start = substr($start, 0, 2);
                }

                // Endzeit
                $end = '';
                if ($hasEnd) {
                    $end = substr($row->end_time, 0, 5);
                    if (substr($end, 2) === ':00') $end = substr($end, 0, 2);
                } elseif ($hasStart) {
                    $sun_info = date_sun_info($timestamp, $lat, $lon);
                    $dt = new DateTime('@' . $sun_info['sunset']);
                    $dt->setTimezone($tz);
                    $end = $dt->format('H');
                }

                $isToday = ($row->datum === $today->format('Y-m-d'));
                $trClass = ($isToday ? 'today' : '') . $sepClass;

                // Badge bauen
                $badge = '';
                if ($hasKurs) {
                    $kursCount = ($c1 ? 1 : 0) + ($c2 ? 1 : 0);
                    // Kurszeiten kompakt zusammenfassen
                    $kursLabel = [];
                    if ($c1 && $c1txt) $kursLabel[] = $c1txt;
                    if ($c2 && $c2txt) $kursLabel[] = $c2txt;

                    if (!empty($kursLabel)) {
                        // Bei 1 Kurs: Zeit anzeigen. Bei 2 Kursen: "2 Kurse"
                        if (count($kursLabel) === 1) {
                            $badgeText = esc_html($kursLabel[0]);
                        } else {
                            $badgeText = count($kursLabel) . '\u00d7 Kurs';
                        }
                    } else {
                        $badgeText = $kursCount === 1 ? 'Kurs' : $kursCount . '\u00d7 Kurs';
                    }

                    $badge = '<span class="oh-kurs-badge">'
                           . '<span class="kb-icon">🏄</span>'
                           . $badgeText
                           . '</span>';
                }

                // ── Zeile ausgeben ──
                if ($hasStart) {
                    echo '<tr' . ($trClass ? ' class="' . trim($trClass) . '"' : '') . '>';
                    echo '<td class="col-1-day">'   . esc_html($tag)   . ':</td>';
                    echo '<td class="col-2-start">' . esc_html($start) . '</td>';
                    echo '<td class="col-3-sep">–</td>';
                    echo '<td class="col-4-end">'   . esc_html($end)   . '</td>';
                    echo '<td class="col-5-unit">UHR</td>';
                    echo '<td class="col-6-kurs">'  . $badge           . '</td>';
                    echo '</tr>';
                } elseif ($hasKurs) {
                    // Kurs-only Zeile (Sa/So ohne Öffnungszeit aber mit Kurs)
                    echo '<tr' . ($trClass ? ' class="' . trim($trClass) . '"' : '') . '>';
                    echo '<td class="col-1-day">' . esc_html($tag) . ':</td>';
                    echo '<td class="col-2-start" colspan="3" style="color:var(--clr-text-muted,#7a8a8a);font-size:22px;letter-spacing:.05em">Kein Wakeboard</td>';
                    echo '<td class="col-5-unit"></td>';
                    echo '<td class="col-6-kurs">' . $badge . '</td>';
                    echo '</tr>';
                }
            }

            echo '</table></div>'; // .oh-glass
        }

        return ob_get_clean();
    }
    add_shortcode('oeffnungszeiten', 'opening_hours_pixel_perfect');
}

// ── Random Split Photos ──
if (!shortcode_exists('random_split_photos')) {
    add_shortcode('random_split_photos', function ($atts) {
        $atts    = shortcode_atts(['folder' => 'split', 'ttl' => 3600, 'class' => 'rs-split'], $atts, 'random_split_photos');
        $uploads = wp_upload_dir();
        $rel     = trim((string)$atts['folder'], "/ \t\n\r\0\x0B");
        if ($rel === '' || strpos($rel, '..') !== false) return '';

        $dir = trailingslashit($uploads['basedir']) . $rel;
        $url = trailingslashit($uploads['baseurl']) . $rel;
        if (!is_dir($dir) || !is_readable($dir)) return '';

        $files = [];
        foreach (['jpg','jpeg','png','webp','gif'] as $ext) {
            $g = glob($dir . '/*.' . $ext);
            if (is_array($g)) $files = array_merge($files, $g);
        }
        $files = array_values(array_filter($files, fn($p) => is_file($p)));
        if (count($files) < 2) return '';

        try {
            $ionos = get_ionos_db_connection();
            if ($ionos) {
                $activeNames = $ionos->get_col($ionos->prepare(
                    "SELECT filename FROM media_files WHERE folder = %s AND is_active = 1", $rel
                ));
                if (!empty($activeNames)) {
                    $files = array_values(array_filter($files, function($path) use ($activeNames) {
                        return in_array(basename($path), $activeNames, true);
                    }));
                }
            }
        } catch (Exception $e) {}

        if (count($files) < 2) return '';

        $key        = 'rs_last_pair_' . md5($rel);
        $last       = get_transient($key);
        $exclude    = is_array($last) ? $last : [];
        $candidates = array_values(array_diff($files, $exclude));
        if (count($candidates) < 2) { $candidates = $files; }
        shuffle($candidates);
        $pair = array_slice($candidates, 0, 2);
        set_transient($key, $pair, max(60, (int)$atts['ttl']));

        $src1  = esc_url(trailingslashit($url) . basename($pair[0]));
        $src2  = esc_url(trailingslashit($url) . basename($pair[1]));
        $anim1 = (rand(0,1) === 1) ? 'rs-zoom-in' : 'rs-zoom-out';
        $anim2 = (rand(0,1) === 1) ? 'rs-zoom-in' : 'rs-zoom-out';

        return '<div class="' . esc_attr($atts['class']) . '">
          <figure class="rs-enter-1"><img src="' . $src1 . '" class="' . $anim1 . '" alt="" loading="eager"></figure>
          <figure class="rs-enter-2"><img src="' . $src2 . '" class="' . $anim2 . '" alt="" loading="eager"></figure>
        </div>';
    });
}

// ── Öffnungszeiten Foto ──
if (!defined('PHOTO_API_URL')) {
    define('PHOTO_API_URL', 'https://wcr-webpage.de/be/api/get_latest_photo.php');
}

if (!function_exists('simple_opening_hours_photo')) {
    function simple_opening_hours_photo($atts) {
        $atts     = shortcode_atts(array('width'=>'100%','height'=>'auto','class'=>'','style'=>''), $atts);
        $response = @file_get_contents(PHOTO_API_URL);
        if (!$response) return '<!-- API nicht erreichbar -->';
        $data = json_decode($response, true);
        if (!$data || !$data['success']) return '<!-- Kein Foto verfügbar -->';
        $inline_style = "width:{$atts['width']};height:{$atts['height']};";
        if (!empty($atts['style'])) $inline_style .= ' ' . $atts['style'];
        return sprintf('<img src="%s" alt="Öffnungszeiten" class="%s" style="%s" loading="lazy">',
            esc_url($data['url']), esc_attr($atts['class']), esc_attr($inline_style));
    }
    add_shortcode('opening_hours_photo', 'simple_opening_hours_photo');
}

// ── WCR DB Item Shortcode ──
if (!function_exists('wcr_db_item_shortcode')) {
    function wcr_db_item_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id'    => '',
            'feld'  => 'preis',
            'table' => '',
        ), $atts, 'wcr_db');

        $id = (int) $atts['id'];
        if (!$id) return '';

        $erlaubte_felder    = ['produkt', 'preis', 'menge', 'typ', 'stock'];
        $erlaubte_tabellen  = ['food', 'drinks', 'cable', 'camping', 'extra', 'ice'];
        if (!in_array($atts['feld'], $erlaubte_felder)) return '';

        $db = get_ionos_db_connection();
        if (!$db) return '';

        $tabellen = (!empty($atts['table']) && in_array($atts['table'], $erlaubte_tabellen))
            ? [$atts['table']]
            : $erlaubte_tabellen;

        $row = null;
        foreach ($tabellen as $tabelle) {
            $result = $db->get_row($db->prepare(
                "SELECT produkt, preis, menge, typ, stock FROM `$tabelle` WHERE nummer = %d LIMIT 1", $id
            ), ARRAY_A);
            if ($result) { $row = $result; break; }
        }

        if (!$row || !isset($row[$atts['feld']])) return '–';
        $wert = $row[$atts['feld']];

        if ($atts['feld'] === 'preis' && $wert !== null)
            return number_format((float)$wert, 2, ',', '.') . ' €';

        if ($atts['feld'] === 'menge' && $wert !== null)
            return rtrim(rtrim(number_format((float)$wert, 3, ',', '.'), '0'), ',') . ' l';

        return esc_html($wert ?? '–');
    }
    add_shortcode('wcr_db', 'wcr_db_item_shortcode');
}

// ── Merch Seite ──
if (!function_exists('wcr_sc_merch')) {
    function wcr_sc_merch($atts) {
        wp_enqueue_script(
            'wcr-merch',
            plugins_url('assets/js/wcr-merch.js', dirname(__FILE__)),
            [], '1.0.0', true
        );

        $slides     = [6000, 6001, 6004];
        $highlights = [6003, 6005, 6001];

        return '<div id="merch-wrap"
                     data-slides="'     . esc_attr(json_encode($slides))     . '"
                     data-highlights="' . esc_attr(json_encode($highlights)) . '"
                     data-api="/wp-json/wakecamp/v1/extra"
                     data-interval="4000">
            <div class="merch-main">
                <div class="merch-slider" id="merch-slider">
                    <div class="merch-slide-inner" id="merch-slide-inner"></div>
                </div>
                <div class="merch-dots" id="merch-dots"></div>
            </div>
            <aside class="merch-sidebar" id="merch-sidebar"></aside>
        </div>';
    }
    add_shortcode('wcr_merch', 'wcr_sc_merch');
}
