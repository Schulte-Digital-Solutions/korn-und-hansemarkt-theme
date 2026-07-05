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

        // Complianz SPA-Patch: conditionally_show_banner absichern.
        //
        // Complianz crasht wenn cmplz_check_cookie_policy_id() aufgerufen wird
        // bevor show_cookie_banner() die Variable cmplz_banner gesetzt hat
        // (TypeError: Cannot read properties of undefined).
        // Dieser Crash verhindert, dass Cookie-Banner und Platzhalter erscheinen.
        //
        // Lösung: die Funktion mit try/catch wrappen und bei Fehler
        // show_cookie_banner() nachholen, damit Banner + Platzhalter funktionieren.
        var _realCSB;
        Object.defineProperty(window, 'conditionally_show_banner', {
            configurable: true,
            enumerable: true,
            set: function(fn) {
                _realCSB = function() {
                    try {
                        fn.apply(this, arguments);
                    } catch(e) {
                        // Platzhalter trotzdem verarbeiten
                        if (typeof cmplz_set_blocked_content_container === 'function') {
                            try { cmplz_set_blocked_content_container(); } catch(e2) {}
                        }
                        // Cookie-Banner trotzdem anzeigen
                        if (typeof window.show_cookie_banner === 'function') {
                            try { window.show_cookie_banner(); } catch(e3) {}
                        }
                    }
                };
            },
            get: function() { return _realCSB; }
        });

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

/**
 * Complianz: AJAX-Content-Blocking für die SPA erzwingen.
 *
 * Damit Complianz das 2000ms-Intervall mit cmplz_set_blocked_content_container()
 * startet, muss block_ajax_content==1 sein. Die setInterval-Interception in
 * kuh_capture_init_callbacks() fängt die Referenz dann als
 * window.__cmplzRescanBlockedContent ab, sodass reinitBlocks() den Scan
 * sofort nach SPA-Navigation auslösen kann.
 */
function kuh_force_complianz_ajax_blocking( $settings, $banner ) {
    $settings['block_ajax_content'] = 1;
    return $settings;
}
add_filter( 'cmplz_cookiebanner_settings_front_end', 'kuh_force_complianz_ajax_blocking', 10, 2 );

/**
 * Loest einen Spacing-Wert auf.
 *
 * WordPress speichert Preset-Referenzen als "var:preset|spacing|<slug>". Diese
 * muessen fuer inline-Styles zu "var(--wp--preset--spacing--<slug>)" umgewandelt
 * werden. Rohe CSS-Werte (z.B. "2rem", "16px", "1.5em") werden auf sichere
 * Zeichen gefiltert und unveraendert zurueckgegeben.
 */
function kuh_resolve_spacing_value( $value ) {
    if ( ! is_string( $value ) || $value === '' ) {
        return '';
    }
    if ( str_contains( $value, 'var:preset|spacing|' ) ) {
        $slug = substr( $value, strrpos( $value, '|' ) + 1 );
        $slug = preg_replace( '/[^a-zA-Z0-9_-]/', '', $slug );
        if ( $slug === '' ) {
            return '';
        }
        return 'var(--wp--preset--spacing--' . $slug . ')';
    }
    // Rohe CSS-Werte auf sichere Zeichen beschraenken (Zahlen, Einheiten,
    // calc(), min(), max(), clamp(), var(), Komma/Leerzeichen).
    $safe = preg_replace( '/[^0-9a-zA-Z\.%\-\+\*\/\(\)\, ]/', '', $value );
    return trim( (string) $safe );
}

/**
 * Grid-Layout (Group-Block "Raster") und Block-Gap als inline-Style in den
 * gerenderten Markup schreiben.
 *
 * WordPress gibt die Spalten-Konfiguration (columnCount / minimumColumnWidth /
 * rowCount), die Kind-Positionierung (columnSpan / rowSpan / columnStart /
 * rowStart) und den blockGap normalerweise als separate CSS-Regel im <head>
 * aus (Style-Engine, Kontext "block-supports"). Bei SPA-Navigation ueber die
 * REST-API wird nur content.rendered ausgeliefert – dieses Head-CSS fehlt.
 * Wir schreiben die Regeln daher direkt als inline-Style an das gerenderte
 * Element.
 */
function kuh_inline_group_grid_layout( $block_content, $block ) {
    if ( empty( $block['blockName'] ) ) {
        return $block_content;
    }

    $decls = array();

    // A) Parent-Ebene: core/group mit layout.type=grid
    if ( 'core/group' === $block['blockName'] ) {
        $layout = $block['attrs']['layout'] ?? array();
        if ( ! empty( $layout ) && 'grid' === ( $layout['type'] ?? '' ) ) {
            $col_count = ! empty( $layout['columnCount'] ) ? absint( $layout['columnCount'] ) : 0;
            $row_count = ! empty( $layout['rowCount'] ) ? absint( $layout['rowCount'] ) : 0;
            $min_width = ! empty( $layout['minimumColumnWidth'] )
                ? preg_replace( '/[^0-9a-zA-Z\.%\-]/', '', (string) $layout['minimumColumnWidth'] )
                : '';

            if ( $col_count > 0 && $min_width !== '' ) {
                // Kombinationsmodus (columnCount + minimumColumnWidth): WP-Formel nachbauen.
                $gap     = '1.25rem';
                $max_val = 'max(min(' . $min_width . ',100%),(100% - (' . $gap . ' * (' . $col_count . ' - 1))) /' . $col_count . ')';
                $decls[] = 'grid-template-columns:repeat(auto-fill,minmax(' . $max_val . ',1fr))';
            } elseif ( $col_count > 0 ) {
                $decls[] = 'grid-template-columns:repeat(' . $col_count . ',minmax(0,1fr))';
            } elseif ( $min_width !== '' ) {
                $decls[] = 'grid-template-columns:repeat(auto-fill,minmax(min(' . $min_width . ',100%),1fr))';
            }
            if ( $row_count > 0 ) {
                $decls[] = 'grid-template-rows:repeat(' . $row_count . ',minmax(1rem,auto))';
            }
        }
    }

    // B) Kind-Ebene: beliebiger Block mit style.layout.column/rowSpan/Start
    $child = $block['attrs']['style']['layout'] ?? null;
    if ( is_array( $child ) ) {
        if ( isset( $child['columnSpan'] ) ) {
            $decls[] = 'grid-column:span ' . absint( $child['columnSpan'] );
        }
        if ( isset( $child['rowSpan'] ) ) {
            $decls[] = 'grid-row:span ' . absint( $child['rowSpan'] );
        }
        if ( isset( $child['columnStart'] ) ) {
            $decls[] = 'grid-column-start:' . absint( $child['columnStart'] );
        }
        if ( isset( $child['rowStart'] ) ) {
            $decls[] = 'grid-row-start:' . absint( $child['rowStart'] );
        }
    }

    // C) blockGap: Grid, Flex-Group, Columns, Buttons, Social-Links etc.
    $gap_attr = $block['attrs']['style']['spacing']['blockGap'] ?? null;
    if ( null !== $gap_attr ) {
        if ( is_array( $gap_attr ) ) {
            $row_gap = isset( $gap_attr['top'] ) ? kuh_resolve_spacing_value( $gap_attr['top'] ) : '';
            $col_gap = isset( $gap_attr['left'] ) ? kuh_resolve_spacing_value( $gap_attr['left'] ) : '';
            if ( $row_gap !== '' ) {
                $decls[] = 'row-gap:' . $row_gap;
            }
            if ( $col_gap !== '' ) {
                $decls[] = 'column-gap:' . $col_gap;
            }
        } else {
            $gap = kuh_resolve_spacing_value( $gap_attr );
            if ( $gap !== '' ) {
                $decls[] = 'gap:' . $gap;
            }
        }
    }

    // D) Flex-Layout Ausrichtung: justifyContent / verticalAlignment
    // WP schreibt diese Werte fuer flex-Layout ebenfalls als Head-CSS aus –
    // bei SPA-Navigation verloren. Fuer Grid setzt WP nur die Klasse
    // `is-content-justification-*`, die vom Theme-CSS aufgeloest wird.
    $layout = $block['attrs']['layout'] ?? array();
    if ( 'flex' === ( $layout['type'] ?? '' ) ) {
        $orientation = $layout['orientation'] ?? 'horizontal';
        $justify_map = array(
            'left'          => 'flex-start',
            'center'        => 'center',
            'right'         => 'flex-end',
            'space-between' => 'space-between',
            'stretch'       => 'stretch',
        );
        $align_map = array(
            'top'           => 'flex-start',
            'center'        => 'center',
            'bottom'        => 'flex-end',
            'stretch'       => 'stretch',
            'space-between' => 'space-between',
        );
        $jc = $layout['justifyContent'] ?? '';
        $va = $layout['verticalAlignment'] ?? '';

        if ( 'horizontal' === $orientation ) {
            if ( $jc !== '' && isset( $justify_map[ $jc ] ) ) {
                $decls[] = 'justify-content:' . $justify_map[ $jc ];
            }
            if ( $va !== '' && isset( $align_map[ $va ] ) ) {
                $decls[] = 'align-items:' . $align_map[ $va ];
            }
        } else {
            // vertical: Achsen werden gedreht
            $decls[] = 'flex-direction:column';
            if ( $jc !== '' && isset( $justify_map[ $jc ] ) ) {
                $decls[] = 'align-items:' . $justify_map[ $jc ];
            }
            if ( $va !== '' && isset( $align_map[ $va ] ) ) {
                $decls[] = 'justify-content:' . $align_map[ $va ];
            }
        }

        if ( ! empty( $layout['flexWrap'] ) && 'nowrap' === $layout['flexWrap'] ) {
            $decls[] = 'flex-wrap:nowrap';
        }
    }

    if ( empty( $decls ) ) {
        return $block_content;
    }
    $rule = implode( ';', $decls ) . ';';

    // Nur das erste (Wrapper-)Element anfassen.
    $done = false;
    return preg_replace_callback(
        '/<([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>/',
        function ( $m ) use ( $rule, &$done ) {
            if ( $done ) {
                return $m[0];
            }
            $done  = true;
            $tag   = $m[1];
            $attrs = (string) $m[2];
            if ( preg_match( '/\sstyle\s*=\s*"([^"]*)"/i', $attrs, $sm ) ) {
                $existing = $sm[1];
                // Bereits vom Nutzer gesetzte Werte nicht ueberschreiben.
                $filtered = array_filter(
                    explode( ';', $rule ),
                    function ( $decl ) use ( $existing ) {
                        $prop = trim( strtok( $decl, ':' ) );
                        return $prop !== '' && stripos( $existing, $prop . ':' ) === false;
                    }
                );
                if ( empty( $filtered ) ) {
                    return $m[0];
                }
                $new_style = rtrim( $existing, '; ' ) . ';' . implode( ';', $filtered ) . ';';
                $attrs     = (string) str_replace( $sm[0], ' style="' . esc_attr( $new_style ) . '"', $attrs );
            } else {
                $attrs .= ' style="' . esc_attr( $rule ) . '"';
            }
            return '<' . $tag . $attrs . '>';
        },
        $block_content,
        1
    );
}
add_filter( 'render_block', 'kuh_inline_group_grid_layout', 10, 2 );


