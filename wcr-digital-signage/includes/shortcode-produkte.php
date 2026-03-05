<?php
/**
 * WCR Produkte Spotlight Shortcode
 *
 * Verwendung:
 *   [wcr_produkte id1="42" id2="17" id3="99"]
 *   [wcr_produkte id1="42" id2="17" id3="99" titel="Unsere Empfehlungen" table="food"]
 *
 * Parameter:
 *   id1, id2, id3  – Datenbank-Nummer (Spalte `nummer`) der Produkte
 *   titel          – Überschrift (Standard: "Unsere Empfehlungen")
 *   table          – Optional: Tabelle eingrenzen (food, drinks, cable, camping, extra, ice)
 *
 * Jede Card zeigt: Produktname · Preis · Menge (falls vorhanden)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'wcr_sc_produkte' ) ) {

    function wcr_sc_produkte( $atts ) {

        $atts = shortcode_atts( [
            'id1'   => '',
            'id2'   => '',
            'id3'   => '',
            'titel' => 'Unsere Empfehlungen',
            'table' => '',
        ], $atts, 'wcr_produkte' );

        // ── CSS laden ──
        wp_enqueue_style(
            'wcr-produkte',
            WCR_DS_URL . 'assets/css/wcr-produkte.css',
            [],
            WCR_DS_VERSION
        );

        // ── DB-Verbindung ──
        $db = get_ionos_db_connection();

        $erlaubte_tabellen = [ 'food', 'drinks', 'cable', 'camping', 'extra', 'ice' ];
        $tabellen = ( ! empty( $atts['table'] ) && in_array( $atts['table'], $erlaubte_tabellen, true ) )
            ? [ $atts['table'] ]
            : $erlaubte_tabellen;

        // ── Hilfsfunktion: Produkt per Nummer aus DB laden ──
        $get_produkt = function( $nummer ) use ( $db, $tabellen ) {
            if ( ! $nummer || ! $db ) return null;
            $id = (int) $nummer;
            if ( $id <= 0 ) return null;
            foreach ( $tabellen as $tabelle ) {
                $row = $db->get_row(
                    $db->prepare(
                        "SELECT produkt, preis, menge, typ FROM `$tabelle` WHERE nummer = %d LIMIT 1",
                        $id
                    ),
                    ARRAY_A
                );
                if ( $row ) return $row;
            }
            return null;
        };

        // ── Produkte laden ──
        $ids      = [ $atts['id1'], $atts['id2'], $atts['id3'] ];
        $produkte = [];
        foreach ( $ids as $raw_id ) {
            $produkte[] = $get_produkt( $raw_id );
        }

        // ── HTML ausgeben ──
        $titel = esc_html( $atts['titel'] );

        ob_start();
        ?>
        <div class="wcr-produkte-wrap">

            <!-- Header -->
            <div class="wcr-produkte-header">
                <div class="wcr-produkte-header-line"></div>
                <div class="wcr-produkte-header-inner">
                    <div class="wcr-produkte-dot"></div>
                    <?php echo $titel; ?>
                    <div class="wcr-produkte-dot"></div>
                </div>
                <div class="wcr-produkte-header-line right"></div>
            </div>

            <!-- Cards -->
            <div class="wcr-produkte-grid">
                <?php foreach ( $produkte as $i => $p ) :
                    $num = (int) $ids[ $i ];
                ?>
                <div class="wcr-produkte-card<?php echo ( ! $p ) ? ' is-error' : ''; ?>">

                    <?php if ( $p ) : ?>

                        <div class="wcr-produkte-num"># <?php echo esc_html( $num ); ?></div>

                        <div class="wcr-produkte-name">
                            <?php echo esc_html( $p['produkt'] ?? '–' ); ?>
                        </div>

                        <div class="wcr-produkte-divider"></div>

                        <div class="wcr-produkte-preis">
                            <?php
                            $preis = isset( $p['preis'] ) && $p['preis'] !== null
                                ? number_format( (float) $p['preis'], 2, ',', '.' )
                                : '–';
                            echo esc_html( $preis );
                            if ( $p['preis'] !== null ) :
                            ?><span class="wp-currency">€</span><?php endif; ?>
                        </div>

                        <?php if ( ! empty( $p['menge'] ) ) : ?>
                        <div class="wcr-produkte-menge">
                            <?php
                            $menge = rtrim( rtrim( number_format( (float) $p['menge'], 3, ',', '.' ), '0' ), ',' );
                            echo esc_html( $menge ) . ' l';
                            ?>
                        </div>
                        <?php endif; ?>

                    <?php else : ?>

                        <div class="wcr-produkte-num"># <?php echo esc_html( $num ?: '?' ); ?></div>
                        <div class="wcr-produkte-name">Produkt nicht gefunden</div>

                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    add_shortcode( 'wcr_produkte', 'wcr_sc_produkte' );
}
