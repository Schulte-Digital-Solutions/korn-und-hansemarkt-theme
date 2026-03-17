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

/**
 * Google-Maps-URLs in Iframes auf die Embed-Variante umschreiben.
 *
 * Reguläre https://www.google.com/maps?q=… URLs setzen X-Frame-Options: sameorigin
 * und lassen sich nicht in Iframes laden. Die URL-Variante mit &output=embed
 * ist dafür vorgesehen und liefert keinen X-Frame-Options-Header.
 */
function kuh_fix_google_maps_embeds( $content ) {
    if ( strpos( $content, 'google.com/maps' ) === false ) {
        return $content;
    }

    return preg_replace_callback(
        '/<iframe([^>]*)\ssrc=["\']((https?:\/\/)(www\.)?google\.[a-z.]+\/maps\?[^"\']*)["\']/',
        function ( $matches ) {
            $url = $matches[2];
            if ( strpos( $url, 'output=embed' ) === false ) {
                $url .= '&output=embed';
            }
            return '<iframe' . $matches[1] . ' src="' . esc_url( $url ) . '"';
        },
        $content
    );
}
add_filter( 'the_content', 'kuh_fix_google_maps_embeds', 5 );
add_filter( 'rest_prepare_post', function( $response ) {
    $data = $response->get_data();
    if ( ! empty( $data['content']['rendered'] ) ) {
        $data['content']['rendered'] = kuh_fix_google_maps_embeds( $data['content']['rendered'] );
    }
    return $response;
}, 5 );
add_filter( 'rest_prepare_page', function( $response ) {
    $data = $response->get_data();
    if ( ! empty( $data['content']['rendered'] ) ) {
        $data['content']['rendered'] = kuh_fix_google_maps_embeds( $data['content']['rendered'] );
    }
    return $response;
}, 5 );

/**
 * Complianz Content-Blocker auf REST-API-Responses anwenden.
 *
 * Complianz blockiert Iframes/Scripts nur im Output-Buffer (template_redirect),
 * der bei REST-Requests nie feuert. Dieser Filter wendet die Tag-Replacement
 * manuell auf content.rendered an, damit die SPA geblockten Content erhält.
 */
function kuh_complianz_rest_blocking( $response, $post, $request ) {
    if ( ! class_exists( 'COMPLIANZ' ) || ! isset( COMPLIANZ::$cookie_blocker ) ) {
        return $response;
    }

    $data = $response->get_data();
    if ( ! empty( $data['content']['rendered'] ) ) {
        $data['content']['rendered'] = COMPLIANZ::$cookie_blocker->replace_tags( $data['content']['rendered'] );
    }
    if ( ! empty( $data['excerpt']['rendered'] ) ) {
        $data['excerpt']['rendered'] = COMPLIANZ::$cookie_blocker->replace_tags( $data['excerpt']['rendered'] );
    }
    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_prepare_post', 'kuh_complianz_rest_blocking', 99, 3 );
add_filter( 'rest_prepare_page', 'kuh_complianz_rest_blocking', 99, 3 );
