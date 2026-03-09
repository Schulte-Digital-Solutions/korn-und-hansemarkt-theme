<?php
/**
 * Hilfsfunktionen: Logo, Menüs, SVG-Support
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logo-URL holen
 */
function kuh_get_logo_url() {
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        return wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }
    return '';
}

/**
 * Navigationsmenüs für die REST API bereitstellen
 */
function kuh_get_menus() {
    $menus = array();
    $locations = get_nav_menu_locations();

    foreach ( $locations as $location => $menu_id ) {
        if ( $menu_id ) {
            $menu_items = wp_get_nav_menu_items( $menu_id );
            if ( $menu_items ) {
                $menus[ $location ] = array_map( function( $item ) {
                    return array(
                        'id'     => $item->ID,
                        'title'  => $item->title,
                        'url'    => str_replace( home_url(), '', $item->url ),
                        'parent' => (int) $item->menu_item_parent,
                        'target' => $item->target,
                        'classes'=> implode( ' ', $item->classes ),
                    );
                }, $menu_items );
            }
        }
    }

    return $menus;
}

/**
 * SVG-Upload erlauben
 */
function kuh_allow_svg_upload( $mimes ) {
    $mimes['svg']  = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
}
add_filter( 'upload_mimes', 'kuh_allow_svg_upload' );

/**
 * SVG-Dateityp-Prüfung korrigieren (WordPress erkennt SVG sonst nicht korrekt)
 */
function kuh_fix_svg_mime_check( $data, $file, $filename, $mimes ) {
    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
    if ( 'svg' === strtolower( $ext ) ) {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'kuh_fix_svg_mime_check', 10, 4 );

/**
 * SVG-Vorschau in der Mediathek ermöglichen
 */
function kuh_svg_media_preview() {
    echo '<style>
        .attachment-266x266, .thumbnail img[src$=".svg"] {
            width: 100% !important;
            height: auto !important;
        }
    </style>';
}
add_action( 'admin_head', 'kuh_svg_media_preview' );
