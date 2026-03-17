<?php
/**
 * Eigene Gutenberg-Blöcke registrieren.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme-Blöcke registrieren
 */
function kuh_register_blocks() {
    register_block_type( KUH_THEME_DIR . '/blocks/hero-collage' );
}
add_action( 'init', 'kuh_register_blocks' );
