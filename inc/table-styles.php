<?php
/**
 * Table Block Style Anpassungen
 *
 * Registriert zusaetzliche Style-Varianten fuer core/table. Der Core-Style
 * `stripes` bleibt erhalten und wird im Theme-CSS moderner gerendert.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Zusaetzliche Block-Style-Varianten fuer die Tabelle registrieren.
 */
function kuh_register_table_block_styles() {
    $styles = array(
        array(
            'name'  => 'bordered',
            'label' => __( 'Umrandet', 'korn-und-hansemarkt' ),
        ),
        array(
            'name'  => 'minimal',
            'label' => __( 'Minimal', 'korn-und-hansemarkt' ),
        ),
        array(
            'name'  => 'card',
            'label' => __( 'Karten', 'korn-und-hansemarkt' ),
        ),
        array(
            'name'  => 'compact',
            'label' => __( 'Kompakt', 'korn-und-hansemarkt' ),
        ),
        array(
            'name'  => 'primary',
            'label' => __( 'Prim\u00e4rfarbe', 'korn-und-hansemarkt' ),
        ),
    );

    foreach ( $styles as $style ) {
        register_block_style( 'core/table', $style );
    }
}
add_action( 'init', 'kuh_register_table_block_styles' );
