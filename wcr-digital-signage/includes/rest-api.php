<?php
if (!defined('ABSPATH')) exit;

// Internes Secret für BE → WP Kommunikation
define('WCR_DS_API_SECRET', 'WCR_DS_2026');

add_action('rest_api_init', function() {

    // ── Alle Drinks ──
    register_rest_route('wakecamp/v1', '/drinks', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock, bild_url
                                  FROM drinks WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Drinks nach Typ ──
    register_rest_route('wakecamp/v1', '/drinks/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock, bild_url
                     FROM drinks WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Alle Food ──
    register_rest_route('wakecamp/v1', '/food', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock
                                  FROM food WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Food-Gruppen Status ──
    register_rest_route('wakecamp/v1', '/food/gruppen', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT typ, aktiv FROM wp_food_gruppen", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Food nach Typ ──
    register_rest_route('wakecamp/v1', '/food/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock
                     FROM food WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Events ──
    register_rest_route('wakecamp/v1', '/events', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT id, titel, beschreibung, datum, uhrzeit, bild_url
                                  FROM events WHERE aktiv = 1
                                  ORDER BY datum ASC", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Merch ──
    register_rest_route('wakecamp/v1', '/extra', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results(
                    "SELECT nummer, produkt, preis, bild_url
                     FROM extra WHERE nummer >= 6000 AND stock != 0
                     ORDER BY nummer ASC", ARRAY_A
                ) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Single Item by ID ──
    register_rest_route('wakecamp/v1', '/item/(?P<id>[0-9]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            $id       = (int) $req['id'];
            $tabellen = ['food', 'drinks', 'cable', 'camping', 'extra', 'ice'];
            foreach ($tabellen as $tabelle) {
                $row = $db->get_row($db->prepare(
                    "SELECT nummer, produkt, preis, menge, typ FROM `$tabelle` WHERE nummer = %d LIMIT 1", $id
                ), ARRAY_A);
                if ($row) return rest_ensure_response($row + ['table' => $tabelle]);
            }
            return new WP_Error('not_found', 'ID nicht gefunden', ['status' => 404]);
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Ping / Debug ──
    register_rest_route('wakecamp/v1', '/ping', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return ['status' => 'FEHLER'];
            return [
                'status'       => 'OK',
                'drinks_count' => (int) $db->get_var("SELECT COUNT(*) FROM drinks WHERE stock != 0"),
                'food_count'   => (int) $db->get_var("SELECT COUNT(*) FROM food WHERE stock != 0"),
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // ── DS-Settings (BE-Brücke) ──
    register_rest_route('wakecamp/v1', '/ds-settings', [
        [
            'methods'             => 'GET',
            'callback'            => function() {
                return rest_ensure_response([
                    'options' => get_option('wcr_ds_options', []),
                    'theme'   => get_option('wcr_ds_theme', 'glass'),
                ]);
            },
            'permission_callback' => '__return_true',
        ],
        [
            'methods'             => 'POST',
            'callback'            => function(WP_REST_Request $req) {
                if (($req->get_param('wcr_secret') ?? '') !== WCR_DS_API_SECRET) {
                    return new WP_Error('forbidden', 'Nicht autorisiert', ['status' => 403]);
                }
                $action = $req->get_param('action') ?? '';
                if ($action === 'save') {
                    $opts = $req->get_param('options');
                    if (is_array($opts)) {
                        $allowed = ['clr_green','clr_blue','clr_white','clr_text','clr_muted',
                                    'clr_bg','clr_bg_dark','clr_bg_glass','font_family',
                                    'viewport_w','viewport_h'];
                        $clean = [];
                        foreach ($allowed as $k) {
                            if (isset($opts[$k])) $clean[$k] = sanitize_text_field((string)$opts[$k]);
                        }
                        update_option('wcr_ds_options', $clean);
                        global $wpdb;
                        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%wcr%'");
                    }
                } elseif ($action === 'reset') {
                    update_option('wcr_ds_options', wcr_ds_defaults());
                } elseif ($action === 'theme') {
                    $theme = sanitize_text_field($req->get_param('theme') ?? '');
                    if (in_array($theme, ['glass', 'flat', 'aurora'], true))
                        update_option('wcr_ds_theme', $theme);
                } else {
                    return new WP_Error('invalid_action', 'Unbekannte Action', ['status' => 400]);
                }
                return rest_ensure_response(['ok' => true, 'action' => $action]);
            },
            'permission_callback' => '__return_true',
        ],
    ]);

    // ══════════════════════════════════════════════════════════
    // INSTAGRAM REST ENDPOINTS
    // ══════════════════════════════════════════════════════════

    // GET /wp-json/wakecamp/v1/instagram
    register_rest_route('wakecamp/v1', '/instagram', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response(WCR_Instagram::get_posts()),
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/wakecamp/v1/instagram/videos
    register_rest_route('wakecamp/v1', '/instagram/videos', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response(WCR_Instagram::get_videos()),
        'permission_callback' => '__return_true',
    ]);

});
