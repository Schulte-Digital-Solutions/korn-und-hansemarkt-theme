<?php
/**
 * SPA Block-Kompatibilität
 *
 * Generische Lösung, damit Block-Assets (JS/CSS) von beliebigen Plugins
 * in der SPA korrekt geladen und initialisiert werden.
 *
 * Problem: Plugins initialisieren ihre Block-Scripts auf zwei Arten:
 *   a) DOMContentLoaded/load-Callbacks → werden abgefangen und wiederholbar gemacht.
 *   b) IIFEs, die sofort bei Script-Evaluation laufen → werden nach Content-Injection
 *      durch Neuerstellen des <script>-Tags erneut ausgeführt.
 *
 * Lösung:
 * 1. DOMContentLoaded/load-Callbacks abfangen und wiederholbar machen
 * 2. Alle Block-Scripts/Styles aus der WP Block-Registry vorladen
 * 3. Frontend-Scripts für Re-Execution markieren (data-kuh-reinit)
 * 4. Plugin-spezifische Assets über Action Hook erweiterbar
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frühes Script in <head> einfügen, das DOMContentLoaded- und
 * window.load-Callbacks abfängt und für die SPA wiederholbar macht.
 */
function kuh_capture_init_callbacks() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <script>
    (function() {
        var captured = [];
        var nativeDocAdd = document.addEventListener;
        var nativeWinAdd = window.addEventListener;

        document.addEventListener = function(type, fn, opts) {
            if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                captured.push(fn);
            }
            return nativeDocAdd.call(this, type, fn, opts);
        };

        window.addEventListener = function(type, fn, opts) {
            if (type === 'load' && typeof fn === 'function') {
                captured.push(fn);
            }
            return nativeWinAdd.call(this, type, fn, opts);
        };

        window.__kuhReinitBlocks = function() {
            // 1. Re-run captured DOMContentLoaded/load-Callbacks
            for (var i = 0; i < captured.length; i++) {
                try { captured[i](); } catch(e) {}
            }
            // 2. Re-execute IIFE scripts (data-kuh-reinit)
            var scripts = document.querySelectorAll('script[data-kuh-reinit][src]');
            scripts.forEach(function(old) {
                var el = document.createElement('script');
                el.src = old.src;
                el.setAttribute('data-kuh-reinit', '');
                old.parentNode.insertBefore(el, old.nextSibling);
                old.parentNode.removeChild(old);
            });
        };
    })();
    </script>
    <?php
}
add_action( 'wp_head', 'kuh_capture_init_callbacks', 1 );

/** Script-Handles, die für Re-Execution markiert werden sollen. */
global $kuh_reinit_handles;
$kuh_reinit_handles = array();

/**
 * Alle verwendeten Block-Typen aus einem Array von parsed Blocks sammeln.
 */
function kuh_collect_block_types( array $blocks, array &$types ) {
    foreach ( $blocks as $block ) {
        if ( ! empty( $block['blockName'] ) ) {
            $types[ $block['blockName'] ] = true;
        }
        if ( ! empty( $block['innerBlocks'] ) ) {
            kuh_collect_block_types( $block['innerBlocks'], $types );
        }
    }
}

/**
 * Block-Assets für ALLE veröffentlichten Seiten/Beiträge vorladen,
 * damit bei SPA-Navigation sämtliche Block-Scripts verfügbar sind.
 *
 * 1. Alle Script-/Style-Handles aus der WP Block-Registry
 * 2. Plugin-spezifische Assets per Action Hook 'kuh_preload_block_plugin_assets'
 */
function kuh_preload_block_assets() {
    if ( is_admin() ) {
        return;
    }

    global $wpdb, $kuh_reinit_handles;

    // Alle veröffentlichten Posts/Pages mit Blöcken laden.
    $rows = $wpdb->get_results(
        "SELECT ID, post_content FROM {$wpdb->posts}
         WHERE post_status = 'publish'
         AND post_content LIKE '%<!-- wp:%'
         AND post_type IN ('post', 'page')"
    );

    $block_types = array();
    $post_ids    = array();

    foreach ( $rows as $row ) {
        $post_ids[] = intval( $row->ID );
        $blocks = parse_blocks( $row->post_content );
        kuh_collect_block_types( $blocks, $block_types );
    }

    // Alle Block-Script/Style-Handles aus der Registry enqueuen.
    $registry = WP_Block_Type_Registry::get_instance();
    foreach ( array_keys( $block_types ) as $block_name ) {
        $block_type = $registry->get_registered( $block_name );
        if ( ! $block_type ) {
            continue;
        }

        // view_script (Frontend-only)
        foreach ( $block_type->view_script_handles ?? array() as $handle ) {
            wp_enqueue_script( $handle );
            $kuh_reinit_handles[ $handle ] = true;
        }
        // script (Frontend + Editor, z.B. CoBlocks Swiper)
        foreach ( $block_type->script_handles ?? array() as $handle ) {
            wp_enqueue_script( $handle );
            $kuh_reinit_handles[ $handle ] = true;
        }
        // view_style (Frontend-only)
        foreach ( $block_type->view_style_handles ?? array() as $handle ) {
            wp_enqueue_style( $handle );
        }
        // style (Frontend + Editor)
        foreach ( $block_type->style_handles ?? array() as $handle ) {
            wp_enqueue_style( $handle );
        }
    }

    /**
     * Hook für plugin-spezifische Asset-Vorladung.
     *
     * @param int[]  $post_ids    IDs aller Posts/Pages mit Blöcken.
     * @param array  $block_types Assoziatives Array der verwendeten Block-Typen.
     */
    do_action( 'kuh_preload_block_plugin_assets', $post_ids, $block_types );
}
add_action( 'wp_enqueue_scripts', 'kuh_preload_block_assets', 99 );

/**
 * data-kuh-reinit Attribut an Block-Plugin-Scripts anhängen,
 * damit sie nach SPA-Navigationen erneut ausgeführt werden können.
 */
function kuh_tag_reinit_scripts( $tag, $handle, $src ) {
    global $kuh_reinit_handles;

    if ( is_admin() || empty( $kuh_reinit_handles[ $handle ] ) ) {
        return $tag;
    }

    // Core-Blöcke nicht re-executen (nur Plugin-Scripts).
    if ( str_contains( $src, '/wp-includes/' ) ) {
        return $tag;
    }

    return str_replace( '<script ', '<script data-kuh-reinit ', $tag );
}
add_filter( 'script_loader_tag', 'kuh_tag_reinit_scripts', 10, 3 );

/**
 * Spectra (UAG) – Assets pro Post vorladen.
 */
function kuh_preload_spectra_assets( array $post_ids, array $block_types ) {
    if ( ! class_exists( 'UAGB_Post_Assets' ) ) {
        return;
    }

    // Nur Posts laden, die tatsächlich Spectra-Blöcke enthalten.
    $has_uagb = false;
    foreach ( array_keys( $block_types ) as $name ) {
        if ( str_starts_with( $name, 'uagb/' ) ) {
            $has_uagb = true;
            break;
        }
    }
    if ( ! $has_uagb ) {
        return;
    }

    foreach ( $post_ids as $post_id ) {
        $assets = new UAGB_Post_Assets( $post_id );
        $assets->enqueue_scripts();
    }
}
add_action( 'kuh_preload_block_plugin_assets', 'kuh_preload_spectra_assets', 10, 2 );
