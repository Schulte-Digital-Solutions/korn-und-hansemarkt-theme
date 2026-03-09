<?php
/**
 * Assets einbinden (Vite Dev Server / Production Build)
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
