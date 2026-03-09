<?php
/**
 * Korn & Hansemarkt Theme Functions
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KUH_THEME_VERSION', '1.0.0' );
define( 'KUH_THEME_DIR', get_template_directory() );
define( 'KUH_THEME_URI', get_template_directory_uri() );

/**
 * Theme Setup
 */
function kuh_theme_setup() {
    // Theme-Unterstützung
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'menus' );

    // Dynamische laden der Block-Assets deaktivieren (da wir sie manuell einbinden)
    add_filter( 'should_load_separate_core_block_assets', '__return_false' );

    // Navigationsmenüs registrieren
    register_nav_menus( array(
        'primary'   => __( 'Hauptnavigation', 'korn-und-hansemarkt' ),
        'footer'    => __( 'Footer Navigation', 'korn-und-hansemarkt' ),
    ) );
}
add_action( 'after_setup_theme', 'kuh_theme_setup' );

/**
 * Assets einbinden
 */
function kuh_enqueue_assets() {
    // Block-Styles explizit laden (headless Theme ruft nie the_content() auf)
    wp_enqueue_style( 'wp-block-library' );

    $manifest_path = KUH_THEME_DIR . '/dist/.vite/manifest.json';
    $is_dev = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $use_dev_server = false;

    if ( $is_dev ) {
        // Prüfe ob der Vite Dev-Server läuft
        $sock = @fsockopen( 'localhost', 5173, $errno, $errstr, 1 );
        if ( $sock ) {
            $use_dev_server = true;
            fclose( $sock );
        }
    }

    if ( $use_dev_server ) {
        // Dev-Modus: Vite Dev Server einbinden
        wp_enqueue_script(
            'kuh-vite-client',
            'http://localhost:5173/@vite/client',
            array(),
            null,
            false
        );

        wp_enqueue_script(
            'kuh-app-dev',
            'http://localhost:5173/src/main.ts',
            array(),
            null,
            true
        );

        // type="module" für Vite-Scripts setzen
        add_filter( 'script_loader_tag', function( $tag, $handle ) {
            if ( 'kuh-vite-client' === $handle || 'kuh-app-dev' === $handle ) {
                return preg_replace( '/<script\b/', '<script type="module"', $tag );
            }
            return $tag;
        }, 10, 2 );
    } elseif ( file_exists( $manifest_path ) ) {
        // Produktion: Vite-Build-Assets einbinden
        $manifest = json_decode( file_get_contents( $manifest_path ), true );

        if ( isset( $manifest['src/main.ts'] ) ) {
            $entry = $manifest['src/main.ts'];

            // CSS einbinden
            if ( isset( $entry['css'] ) ) {
                foreach ( $entry['css'] as $index => $css_file ) {
                    wp_enqueue_style(
                        'kuh-style-' . $index,
                        KUH_THEME_URI . '/dist/' . $css_file,
                        array(),
                        KUH_THEME_VERSION
                    );
                }
            }

            // JS einbinden
            wp_enqueue_script(
                'kuh-app',
                KUH_THEME_URI . '/dist/' . $entry['file'],
                array(),
                KUH_THEME_VERSION,
                true
            );

            // Typ auf module setzen
            add_filter( 'script_loader_tag', function( $tag, $handle ) {
                if ( 'kuh-app' === $handle ) {
                    return preg_replace( '/<script\b/', '<script type="module"', $tag );
                }
                return $tag;
            }, 10, 2 );
        }
    }

    // WordPress-Daten an Frontend übergeben
    wp_localize_script(
        $use_dev_server ? 'kuh-app-dev' : 'kuh-app',
        'kuhData',
        kuh_get_frontend_data()
    );
}
add_action( 'wp_enqueue_scripts', 'kuh_enqueue_assets' );

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
 * Theme Customizer: Header & Farben
 */
function kuh_customize_register( $wp_customize ) {
    // === Abschnitt: Farben ===
    $wp_customize->add_section( 'kuh_colors', array(
        'title'    => __( 'Theme-Farben', 'korn-und-hansemarkt' ),
        'priority' => 30,
    ) );

    $colors = array(
        'primary'   => array( 'label' => 'Primärfarbe',   'default' => '#2c3e50' ),
        'secondary' => array( 'label' => 'Sekundärfarbe', 'default' => '#c0862a' ),
        'accent'    => array( 'label' => 'Akzentfarbe',   'default' => '#e67e22' ),
        'bg'        => array( 'label' => 'Hintergrund',   'default' => '#ffffff' ),
        'text'      => array( 'label' => 'Textfarbe',     'default' => '#333333' ),
        'muted'     => array( 'label' => 'Gedämpft',      'default' => '#6b7280' ),
    );

    foreach ( $colors as $key => $color ) {
        $wp_customize->add_setting( 'kuh_color_' . $key, array(
            'default'           => $color['default'],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_color_' . $key, array(
            'label'   => $color['label'],
            'section' => 'kuh_colors',
        ) ) );
    }

    // === Abschnitt: Header ===
    $wp_customize->add_section( 'kuh_header', array(
        'title'    => __( 'Header', 'korn-und-hansemarkt' ),
        'priority' => 25,
    ) );

    // Header Hintergrundfarbe
    $wp_customize->add_setting( 'kuh_header_bg', array(
        'default'           => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_header_bg', array(
        'label'   => __( 'Hintergrundfarbe', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
    ) ) );

    // Header Textfarbe
    $wp_customize->add_setting( 'kuh_header_text', array(
        'default'           => '#111827',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_header_text', array(
        'label'   => __( 'Textfarbe', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
    ) ) );

    // Header fixiert (sticky)
    $wp_customize->add_setting( 'kuh_header_sticky', array(
        'default'           => true,
        'sanitize_callback' => 'wp_validate_boolean',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_sticky', array(
        'label'   => __( 'Header fixiert (sticky)', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
        'type'    => 'checkbox',
    ) );
}
add_action( 'customize_register', 'kuh_customize_register' );

/**
 * Customizer-Farben als CSS-Variablen in den <head> ausgeben
 */
function kuh_customizer_css() {
    $colors = array(
        'primary'   => '#2c3e50',
        'secondary' => '#c0862a',
        'accent'    => '#e67e22',
        'bg'        => '#ffffff',
        'text'      => '#333333',
        'muted'     => '#6b7280',
    );

    echo '<style>:root {';
    foreach ( $colors as $key => $default ) {
        $value = get_theme_mod( 'kuh_color_' . $key, $default );
        echo '--color-' . esc_attr( $key ) . ':' . esc_attr( $value ) . ';';
    }
    echo '}</style>' . "\n";
}
add_action( 'wp_head', 'kuh_customizer_css', 5 );

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

/**
 * REST API erweitern: Featured Image URL zu Posts hinzufügen
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
 * Alle Requests auf index.php umleiten (SPA-Routing)
 */
function kuh_rewrite_rules() {
    add_rewrite_rule( '^(?!wp-admin|wp-json|wp-login|wp-content|wp-includes).*$', 'index.php', 'top' );
}
add_action( 'init', 'kuh_rewrite_rules' );

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
 * SEO: Meta-Tags für Suchmaschinen und Social-Media-Crawler
 *
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
