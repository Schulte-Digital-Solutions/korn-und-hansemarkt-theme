<?php
/**
 * SEO: Meta-Tags für Suchmaschinen und Social-Media-Crawler
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generiert serverseitig <title>, <meta description> und Open-Graph-Tags
 * basierend auf der aktuellen URL, damit Crawler die richtigen Inhalte sehen.
 */
function kuh_seo_meta_tags() {
    $request_uri = trim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    $site_name   = get_bloginfo( 'name' );
    $site_desc   = get_bloginfo( 'description' );
    $home_url    = home_url( '/' );

    $title       = $site_name;
    $description = $site_desc;
    $og_type     = 'website';
    $og_image    = '';
    $canonical   = $home_url;

    if ( empty( $request_uri ) || $request_uri === '/' ) {
        // Startseite
        $title     = $site_name . ( $site_desc ? ' – ' . $site_desc : '' );
        $canonical = $home_url;

    } elseif ( $request_uri === 'blog' ) {
        // Blog-Übersicht
        $title     = 'Blog – ' . $site_name;
        $canonical = home_url( '/blog' );

    } elseif ( preg_match( '#^post/([a-zA-Z0-9_-]+)$#', $request_uri, $matches ) ) {
        // Einzelner Beitrag
        $slug  = sanitize_title( $matches[1] );
        $posts = get_posts( array(
            'name'           => $slug,
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ) );

        if ( ! empty( $posts ) ) {
            $post        = $posts[0];
            $title       = wp_strip_all_tags( $post->post_title ) . ' – ' . $site_name;
            $description = wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 30 ) );
            $og_type     = 'article';
            $canonical   = home_url( '/post/' . $post->post_name );

            $thumb_id = get_post_thumbnail_id( $post->ID );
            if ( $thumb_id ) {
                $og_image = wp_get_attachment_image_url( $thumb_id, 'large' );
            }
        }

    } else {
        // WordPress-Seite nach Slug
        $slug  = sanitize_title( $request_uri );
        $pages = get_posts( array(
            'name'           => $slug,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ) );

        if ( ! empty( $pages ) ) {
            $page        = $pages[0];
            $title       = wp_strip_all_tags( $page->post_title ) . ' – ' . $site_name;
            $description = wp_strip_all_tags( $page->post_excerpt ?: wp_trim_words( $page->post_content, 30 ) );
            $canonical   = home_url( '/' . $page->post_name );

            $thumb_id = get_post_thumbnail_id( $page->ID );
            if ( $thumb_id ) {
                $og_image = wp_get_attachment_image_url( $thumb_id, 'large' );
            }
        }
    }

    // Ausgabe
    echo '<title>' . esc_html( $title ) . '</title>' . "\n";
    echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
    if ( $og_image ) {
        echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'kuh_seo_meta_tags', 1 );
