<?php
/**
 * Button Block Style Anpassungen
 *
 * - Entfernt den `circular`-Block-Style (Border-Radius ist über die normalen
 *   Block-Optionen einstellbar).
 * - Entfernt den `shadow`-Block-Style (Schatten kann direkt über die normalen
 *   Block-Optionen gesteuert werden).
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * `circular`- und `shadow`-Block-Style für `core/button` im Editor abmelden.
 * CoBlocks registriert den Style per JS, deshalb auch hier per JS entfernen.
 */
function kuh_unregister_button_block_styles() {
    wp_register_script(
        'kuh-block-style-tweaks',
        '',
        array( 'wp-blocks', 'wp-dom-ready' ),
        KUH_THEME_VERSION,
        true
    );

    wp_enqueue_script( 'kuh-block-style-tweaks' );

    wp_add_inline_script(
        'kuh-block-style-tweaks',
        "wp.domReady(function () {\n" .
        "    if (wp && wp.blocks && typeof wp.blocks.unregisterBlockStyle === 'function') {\n" .
        "        wp.blocks.unregisterBlockStyle('core/button', 'circular');\n" .
        "        wp.blocks.unregisterBlockStyle('core/button', 'shadow');\n" .
        "    }\n" .
        "});"
    );
}
add_action( 'enqueue_block_editor_assets', 'kuh_unregister_button_block_styles' );
