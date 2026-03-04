<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Random Background ───────────────────────────────────────────────────────
function random_background_shortcode( $atts, $content = null ) {
    $atts = shortcode_atts( array(
        'folder'  => 'images/backgrounds',
        'element' => 'div',
        'id'      => '',
        'class'   => '',
        'width'   => '100%',
        'height'  => '',
    ), $atts, 'randombg' );

    $folder_path = trailingslashit( get_stylesheet_directory() ) . $atts['folder'];
    $folder_url  = trailingslashit( get_stylesheet_directory_uri() ) . $atts['folder'];
    $images      = glob( $folder_path . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE );
    $bg_url      = $images && count( $images ) > 0 ? $folder_url . '/' . basename( $images[ array_rand( $images ) ] ) : '';

    $style = '';
    if ( $bg_url ) $style .= "background-image:url({$bg_url});background-size:cover;background-position:center center;";
    if ( ! empty( $atts['width'] ) )  $style .= "width:{$atts['width']};";
    if ( ! empty( $atts['height'] ) ) $style .= "height:{$atts['height']};";

    $output = '<' . esc_attr( $atts['element'] );
    if ( ! empty( $atts['id'] ) )    $output .= ' id="'    . esc_attr( $atts['id'] )    . '"';
    if ( ! empty( $atts['class'] ) ) $output .= ' class="' . esc_attr( $atts['class'] ) . '"';
    if ( $style )                    $output .= ' style="' . esc_attr( $style )          . '"';
    $output .= '>';
    if ( $content ) $output .= do_shortcode( $content );
    $output .= '</' . esc_attr( $atts['element'] ) . '>';

    return $output;
}
add_shortcode( 'randombg', 'random_background_shortcode' );

// ─── Öffnungszeiten ──────────────────────────────────────────────────────────
// Attribute:
//   range = "current7" (default) | "last7" | "next7"
//   "current7" = heute bis heute+6
//   "last7"    = heute-6 bis heute
//   "next7"    = Montag nächste Woche bis Sonntag nächste Woche
function opening_hours_pixel_perfect( $atts ) {
    $atts = shortcode_atts( array(
        'tage'  => 7,
        'range' => 'current7',
    ), $atts );

    $externe_db = get_ionos_db_connection();
    if ( ! $externe_db ) return '';

    $today = date( 'Y-m-d' );

    switch ( $atts['range'] ) {
        case 'last7':
            $von = date( 'Y-m-d', strtotime( '-6 days' ) );
            $bis = $today;
            break;
        case 'next7':
            $von = date( 'Y-m-d', strtotime( 'monday next week' ) );
            $bis = date( 'Y-m-d', strtotime( 'sunday next week' ) );
            break;
        case 'current7':
        default:
            $von = $today;
            $bis = date( 'Y-m-d', strtotime( '+6 days' ) );
            break;
    }

    $query   = $externe_db->prepare(
        "SELECT datum, start_time, end_time FROM opening_hours
         WHERE datum >= %s AND datum <= %s
           AND start_time IS NOT NULL AND start_time != ''
         ORDER BY datum ASC",
        $von, $bis
    );
    $results = $externe_db->get_results( $query );

    $wochentage_kurz = [ 1=>'Mo', 2=>'Di', 3=>'Mi', 4=>'Do', 5=>'Fr', 6=>'Sa', 7=>'So' ];
    $lat_berlin      = 52.5200;
    $lon_berlin      = 13.4050;

    ob_start();
    if ( $results ) {
        echo '<div class="oh-glass"><table class="oh-table">';
        foreach ( $results as $row ) {
            $timestamp  = strtotime( $row->datum );
            $day_number = date( 'N', $timestamp );
            $tag        = $wochentage_kurz[ $day_number ];

            // Start-Zeit immer als HH:MM
            $start = substr( $row->start_time, 0, 5 );

            // End-Zeit: wenn gesetzt → HH:MM, sonst Sonnenuntergang
            if ( ! empty( $row->end_time ) && $row->end_time !== 'NULL' ) {
                $end = substr( $row->end_time, 0, 5 );
            } else {
                $sun_info = date_sun_info( $timestamp, $lat_berlin, $lon_berlin );
                $dt       = new DateTime( '@' . $sun_info['sunset'] );
                $dt->setTimezone( new DateTimeZone( 'Europe/Berlin' ) );
                $end      = $dt->format( 'H:i' );
            }

            echo '<tr>';
            echo '<td class="col-1-day">'   . esc_html( $tag )   . '</td>';
            echo '<td class="col-2-start">' . esc_html( $start ) . '</td>';
            echo '<td class="col-3-sep">–</td>';
            echo '<td class="col-4-end">'   . esc_html( $end )   . '</td>';
            echo '<td class="col-5-unit">UHR</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    return ob_get_clean();
}
add_shortcode( 'oeffnungszeiten', 'opening_hours_pixel_perfect' );

// ─── Random Split Photos ──────────────────────────────────────────────────────
add_shortcode( 'randomsplitphotos', function( $atts ) {
    $atts = shortcode_atts( [
        'folder' => 'split',
        'ttl'    => 3600,
        'class'  => 'rs-split',
    ], $atts, 'randomsplitphotos' );

    $uploads = wp_upload_dir();
    $rel     = trim( (string) $atts['folder'], "/\x00\x0B" );
    if ( $rel && strpos( $rel, '..' ) !== false ) return '';

    $dir   = trailingslashit( $uploads['basedir'] ) . $rel;
    $url   = trailingslashit( $uploads['baseurl'] )  . $rel;

    if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) return '';

    $files = [];
    foreach ( [ 'jpg','jpeg','png','webp','gif' ] as $ext ) {
        $g = glob( $dir . '/*.' . $ext );
        if ( is_array( $g ) ) $files = array_merge( $files, $g );
    }
    $files = array_values( array_filter( $files, 'is_file' ) );
    if ( count( $files ) < 2 ) return '';

    try {
        $ionos = get_ionos_db_connection();
        if ( $ionos ) {
            $active_names = $ionos->get_col( $ionos->prepare(
                "SELECT filename FROM media_files WHERE folder = %s AND is_active = 1", $rel
            ) );
            if ( ! empty( $active_names ) ) {
                $files = array_values( array_filter( $files, function( $path ) use ( $active_names ) {
                    return in_array( basename( $path ), $active_names, true );
                } ) );
            }
        }
    } catch ( Exception $e ) {}

    if ( count( $files ) < 2 ) return '';

    $key      = 'rs_last_pair_' . md5( $rel );
    $last     = get_transient( $key );
    $exclude  = is_array( $last ) ? $last : [];
    $candidates = array_values( array_diff( $files, $exclude ) );
    if ( count( $candidates ) < 2 ) $candidates = $files;

    shuffle( $candidates );
    $pair = array_slice( $candidates, 0, 2 );
    set_transient( $key, $pair, max( 60, intval( $atts['ttl'] ) ) );

    $src1  = esc_url( trailingslashit( $url ) . basename( $pair[0] ) );
    $src2  = esc_url( trailingslashit( $url ) . basename( $pair[1] ) );
    $anim1 = rand( 0, 1 ) === 1 ? 'rs-zoom-in' : 'rs-zoom-out';
    $anim2 = rand( 0, 1 ) === 1 ? 'rs-zoom-in' : 'rs-zoom-out';

    return '<div class="' . esc_attr( $atts['class'] ) . '">'
        . '<figure class="rs-enter-1"><img src="' . $src1 . '" class="' . $anim1 . '" alt="" loading="eager"></figure>'
        . '<figure class="rs-enter-2"><img src="' . $src2 . '" class="' . $anim2 . '" alt="" loading="eager"></figure>'
        . '</div>';
} );

// ─── Öffnungszeiten Foto ──────────────────────────────────────────────────────
if ( ! defined( 'PHOTO_API_URL' ) ) define( 'PHOTO_API_URL', 'https://wcr-webpage.de/be/api/get-latest-photo.php' );

function simple_opening_hours_photo( $atts ) {
    $atts = shortcode_atts( [ 'width' => '100%', 'height' => 'auto', 'class' => '', 'style' => '' ], $atts );

    $response = file_get_contents( PHOTO_API_URL );
    if ( ! $response ) return '<!-- API nicht erreichbar -->';
    $data = json_decode( $response, true );
    if ( ! $data || ! $data['success'] ) return '<!-- Kein Foto verfügbar -->';

    $inline_style = "width:{$atts['width']};height:{$atts['height']};";
    if ( ! empty( $atts['style'] ) ) $inline_style .= $atts['style'];

    return sprintf(
        '<img src="%s" alt="Öffnungszeiten" class="%s" style="%s" loading="lazy">',
        esc_url( $data['url'] ),
        esc_attr( $atts['class'] ),
        esc_attr( $inline_style )
    );
}
add_shortcode( 'openinghoursphoto', 'simple_opening_hours_photo' );
