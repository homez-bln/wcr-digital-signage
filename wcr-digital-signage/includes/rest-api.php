<?php
if (!defined('ABSPATH')) exit;

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
    // FIX: Fehlende DB-Prüfung – wenn get_ionos_db_connection() false zurückgibt,
    // würde der Code auf false->get_row() crashen statt einen sauberen Fehler zu liefern.
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

});
