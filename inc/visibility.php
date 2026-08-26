<?php
/**
 * Responsive Sichtbarkeit für Blöcke
 *
 * Ergänzt jeden Block im Editor um das Panel "Sichtbarkeit" und übersetzt die
 * Auswahl in die Klassen `kuh-hide-mobile|tablet|desktop`.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Zuordnung Breakpoint-Key => CSS-Klasse.
 *
 * @return array<string,string>
 */
function kuh_visibility_class_map() {
    return array(
        'mobile'  => 'kuh-hide-mobile',
        'tablet'  => 'kuh-hide-tablet',
        'desktop' => 'kuh-hide-desktop',
    );
}

/**
 * Asset-Version anhand der Dateizeit ermitteln.
 *
 * @param string $relative_path Pfad relativ zum Theme-Verzeichnis.
 * @return string
 */
function kuh_visibility_asset_version( $relative_path ) {
    $file = KUH_THEME_DIR . $relative_path;

    return file_exists( $file ) ? (string) filemtime( $file ) : KUH_THEME_VERSION;
}

/**
 * Editor-Assets laden.
 */
function kuh_visibility_editor_assets() {
    wp_enqueue_script(
        'kuh-block-visibility',
        KUH_THEME_URI . '/assets/block-visibility/editor.js',
        array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose' ),
        kuh_visibility_asset_version( '/assets/block-visibility/editor.js' ),
        true
    );

    wp_enqueue_style(
        'kuh-block-visibility-editor',
        KUH_THEME_URI . '/assets/block-visibility/editor.css',
        array(),
        kuh_visibility_asset_version( '/assets/block-visibility/editor.css' )
    );
}
add_action( 'enqueue_block_editor_assets', 'kuh_visibility_editor_assets' );

/**
 * Frontend-Styles laden.
 */
function kuh_visibility_frontend_assets() {
    wp_enqueue_style(
        'kuh-block-visibility',
        KUH_THEME_URI . '/assets/block-visibility/frontend.css',
        array(),
        kuh_visibility_asset_version( '/assets/block-visibility/frontend.css' )
    );
}
add_action( 'wp_enqueue_scripts', 'kuh_visibility_frontend_assets' );

/**
 * Klassen serverseitig ergänzen – nötig für dynamische Blöcke,
 * deren Markup erst beim Rendern entsteht.
 *
 * @param string $block_content Gerendertes Block-Markup.
 * @param array  $block         Block-Daten.
 * @return string
 */
function kuh_visibility_render_block( $block_content, $block ) {
    if ( empty( $block['attrs']['kuhHiddenOn'] ) || ! is_array( $block['attrs']['kuhHiddenOn'] ) ) {
        return $block_content;
    }

    if ( '' === trim( (string) $block_content ) ) {
        return $block_content;
    }

    $class_map = kuh_visibility_class_map();
    $processor = new WP_HTML_Tag_Processor( $block_content );

    if ( ! $processor->next_tag() ) {
        return $block_content;
    }

    foreach ( $block['attrs']['kuhHiddenOn'] as $breakpoint ) {
        if ( isset( $class_map[ $breakpoint ] ) ) {
            $processor->add_class( $class_map[ $breakpoint ] );
        }
    }

    return $processor->get_updated_html();
}
add_filter( 'render_block', 'kuh_visibility_render_block', 10, 2 );
