<?php
/**
 * Korn- und Hansemarkt Theme Functions
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$theme_data = wp_get_theme();
define( 'KUH_THEME_VERSION', $theme_data->get( 'Version' ) );
define( 'KUH_THEME_DIR', get_template_directory() );
define( 'KUH_THEME_URI', get_template_directory_uri() );

/**
 * Theme Setup
 */
function kuh_theme_setup() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'menus' );
    // add_theme_support( 'title-tag' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );
    add_theme_support( 'automatic-feed-links' );

    add_filter( 'should_load_separate_core_block_assets', '__return_false' );

    register_nav_menus( array(
        'primary'   => __( 'Hauptnavigation', 'korn-und-hansemarkt' ),
        'footer'    => __( 'Footer Navigation', 'korn-und-hansemarkt' ),
    ) );
}
add_action( 'after_setup_theme', 'kuh_theme_setup' );

/**
 * Daten für das Frontend bereitstellen
 */
function kuh_get_frontend_data() {
    return array(
        'restUrl'     => esc_url_raw( rest_url() ),
        'restNonce'   => wp_create_nonce( 'wp_rest' ),
        'themeUrl'    => KUH_THEME_URI,
        'homeUrl'     => home_url( '/' ),
        'siteName'    => get_bloginfo( 'name' ),
        'siteDesc'    => get_bloginfo( 'description' ),
        'logo'        => kuh_get_logo_url(),
        'menus'       => kuh_get_menus(),
        'header'      => array(
            'bg'     => get_theme_mod( 'kuh_header_bg', '#ffffff' ),
            'text'   => get_theme_mod( 'kuh_header_text', '#111827' ),
            'sticky' => (bool) get_theme_mod( 'kuh_header_sticky', true ),
        ),
    );
}

/**
 * SPA-Routing: Alle Requests auf index.php umleiten
 */
function kuh_rewrite_rules() {
    add_rewrite_rule( '^(?!wp-admin|wp-json|wp-login|wp-content|wp-includes).*$', 'index.php', 'top' );
}
add_action( 'init', 'kuh_rewrite_rules' );

/**
 * WordPress-Canonical-Redirect für SPA-Routen deaktivieren
 */
add_filter( 'redirect_canonical', function ( $redirect_url ) {
    if ( ! is_admin() ) {
        return false;
    }
    return $redirect_url;
} );

// Module laden
require_once KUH_THEME_DIR . '/inc/helpers.php';
require_once KUH_THEME_DIR . '/inc/assets.php';
require_once KUH_THEME_DIR . '/inc/customizer.php';
require_once KUH_THEME_DIR . '/inc/rest-api.php';
require_once KUH_THEME_DIR . '/inc/meta-fields.php';
require_once KUH_THEME_DIR . '/inc/seo.php';
