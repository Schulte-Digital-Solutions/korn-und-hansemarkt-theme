<?php
/**
 * REST API: Felder, Endpunkte & CORS
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Featured Image URL zu Posts/Pages hinzufügen
 */
function kuh_register_rest_fields() {
    register_rest_field( array( 'post', 'page' ), 'featured_image_url', array(
        'get_callback' => function( $post ) {
            $image_id = get_post_thumbnail_id( $post['id'] );
            if ( $image_id ) {
                return array(
                    'thumbnail'  => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
                    'medium'     => wp_get_attachment_image_url( $image_id, 'medium' ),
                    'large'      => wp_get_attachment_image_url( $image_id, 'large' ),
                    'full'       => wp_get_attachment_image_url( $image_id, 'full' ),
                );
            }
            return null;
        },
        'schema' => array(
            'type'        => 'object',
            'description' => 'Featured Image URLs in verschiedenen Größen',
        ),
    ) );
}
add_action( 'rest_api_init', 'kuh_register_rest_fields' );

/**
 * REST API Endpunkt für Menüs
 */
function kuh_register_menu_endpoint() {
    register_rest_route( 'kuh/v1', '/menus', array(
        'methods'  => 'GET',
        'callback' => function() {
            return new WP_REST_Response( kuh_get_menus(), 200 );
        },
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'kuh_register_menu_endpoint' );

/**
 * CORS-Header für REST API (Development)
 */
function kuh_cors_headers() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        header( 'Access-Control-Allow-Origin: http://localhost:5173' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
        header( 'Access-Control-Allow-Credentials: true' );
    }
}
add_action( 'rest_api_init', function() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', function( $value ) {
        kuh_cors_headers();
        return $value;
    } );
} );
