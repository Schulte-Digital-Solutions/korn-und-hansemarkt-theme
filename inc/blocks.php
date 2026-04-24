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
    register_block_type( KUH_THEME_DIR . '/blocks/hero-section' );
    register_block_type( KUH_THEME_DIR . '/blocks/highlights-grid' );
    register_block_type( KUH_THEME_DIR . '/blocks/program-teaser' );
    register_block_type( KUH_THEME_DIR . '/blocks/cta-section' );
    register_block_type( KUH_THEME_DIR . '/blocks/drop-cap-paragraph' );
    register_block_type( KUH_THEME_DIR . '/blocks/partner-carousel' );
    register_block_type( KUH_THEME_DIR . '/blocks/program-schedule' );
    register_block_type( KUH_THEME_DIR . '/blocks/event-map' );
    register_block_type( KUH_THEME_DIR . '/blocks/contact-form' );

    // Alten Block-Namen als Alias registrieren (Rückwärtskompatibilität)
    register_block_type( 'kuh/hero-collage', array(
        'editor_script_handles' => array(),
        'render_callback'       => 'kuh_render_legacy_hero_collage',
    ) );
}
add_action( 'init', 'kuh_register_blocks' );

/**
 * Render-Callback für den alten kuh/hero-collage Block.
 * Leitet auf die render.php des neuen hero-section Blocks weiter.
 */
function kuh_render_legacy_hero_collage( $attributes, $content ) {
    // Alte images-Array → einzelnes Bild migrieren
    if ( ! empty( $attributes['images'] ) && empty( $attributes['imageId'] ) ) {
        $first = $attributes['images'][0] ?? null;
        if ( $first ) {
            $attributes['imageId'] = absint( $first['id'] ?? 0 );
        }
    }

    // Alle Attribute an die neue render.php weiterleiten
    $block = new WP_Block( array(
        'blockName' => 'kuh/hero-section',
        'attrs'     => $attributes,
    ) );

    ob_start();
    include KUH_THEME_DIR . '/blocks/hero-section/render.php';
    return ob_get_clean();
}
