<?php
/**
 * Event-Map Datenverwaltung (DB + Admin-Editor).
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_EVENT_MAP_OPTION_KEY = 'kuh_event_map_geojson';

/**
 * POI-Kategorien der Event-Karte.
 *
 * Label, Farbe und Emoji spiegeln die Standardwerte des Blocks
 * (siehe blocks/event-map/block.json und src/components/EventMap.svelte),
 * damit der Backend-Editor zeigt, was im Frontend erscheint.
 *
 * @return array<string,array{label:string,newLabel:string,color:string,emoji:string,icon:string,display:string}>
 */
function kuh_get_event_map_categories() {
    return array(
        'location' => array(
            'label'    => 'Benannte Orte',
            'newLabel' => 'Neuer Ort',
            'color'    => '#15331b',
            'emoji'    => '🏛️',
            'icon'     => 'building',
            'display'  => 'label',
        ),
        'entrance' => array(
            'label'    => 'Eingänge',
            'newLabel' => 'Neuer Eingang',
            'color'    => '#725c0c',
            'emoji'    => '🚪',
            'icon'     => 'entrance',
            'display'  => 'pin',
        ),
        'stage'    => array(
            'label'    => 'Bühnen',
            'newLabel' => 'Neue Bühne',
            'color'    => '#8b1a1a',
            'emoji'    => '🎭',
            'icon'     => 'stage',
            'display'  => 'pin',
        ),
        'parking'  => array(
            'label'    => 'Parkplätze',
            'newLabel' => 'Neuer Parkplatz',
            'color'    => '#1a4a6b',
            'emoji'    => '🅿️',
            'icon'     => 'parking',
            'display'  => 'pin',
        ),
        'toilet'   => array(
            'label'    => 'Toiletten',
            'newLabel' => 'Neue Toilette',
            'color'    => '#4a4a6b',
            'emoji'    => '🚻',
            'icon'     => 'toilet',
            'display'  => 'pin',
        ),
        'info'     => array(
            'label'    => 'Info & Hilfe',
            'newLabel' => 'Neuer Info-Punkt',
            'color'    => '#2d6b4a',
            'emoji'    => 'ℹ️',
            'icon'     => 'info',
            'display'  => 'pin',
        ),
    );
}

/**
 * Asset-Version anhand der Dateizeit ermitteln.
 *
 * @param string $relative_path Pfad relativ zum Theme-Verzeichnis.
 * @return string
 */
function kuh_event_map_asset_version( $relative_path ) {
    $file = KUH_THEME_DIR . $relative_path;

    return file_exists( $file ) ? (string) filemtime( $file ) : KUH_THEME_VERSION;
}

/**
 * Liest das Default-GeoJSON aus der Theme-Datei.
 */
function kuh_get_event_map_default_geojson_raw() {
    $file_path = KUH_THEME_DIR . '/src/assets/map/event-map-pois.json';

    if ( ! file_exists( $file_path ) ) {
        return '';
    }

    $raw = file_get_contents( $file_path );
    return is_string( $raw ) ? $raw : '';
}

/**
 * Prüft ob ein JSON-String gültiges GeoJSON (FeatureCollection) ist.
 */
function kuh_is_valid_geojson_string( $json, &$error_message = '' ) {
    if ( ! is_string( $json ) || '' === trim( $json ) ) {
        $error_message = 'Das JSON darf nicht leer sein.';
        return false;
    }

    $decoded = json_decode( $json, true );

    if ( JSON_ERROR_NONE !== json_last_error() ) {
        $error_message = 'Ungültiges JSON: ' . json_last_error_msg();
        return false;
    }

    if ( ! is_array( $decoded ) ) {
        $error_message = 'Das JSON muss ein Objekt sein.';
        return false;
    }

    if ( ( $decoded['type'] ?? '' ) !== 'FeatureCollection' ) {
        $error_message = 'GeoJSON muss den Typ "FeatureCollection" haben.';
        return false;
    }

    if ( ! isset( $decoded['features'] ) || ! is_array( $decoded['features'] ) ) {
        $error_message = 'GeoJSON benötigt ein Array "features".';
        return false;
    }

    return true;
}

/**
 * Liefert den gespeicherten JSON-String (DB) oder Fallback aus Datei.
 */
function kuh_get_event_map_geojson_raw() {
    $from_db = get_option( KUH_EVENT_MAP_OPTION_KEY, '' );

    if ( is_string( $from_db ) && '' !== trim( $from_db ) ) {
        return $from_db;
    }

    return kuh_get_event_map_default_geojson_raw();
}

/**
 * Liefert ein valides GeoJSON-Array für Frontend/Block.
 */
function kuh_get_event_map_geojson() {
    $raw = kuh_get_event_map_geojson_raw();
    $error_message = '';

    if ( kuh_is_valid_geojson_string( $raw, $error_message ) ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            // Fehlende Bild-Meta aus Datei-Fallback ergänzen,
            // damit ältere DB-JSON-Versionen weiterhin das Hintergrundbild zeigen.
            $default_raw = kuh_get_event_map_default_geojson_raw();
            if ( kuh_is_valid_geojson_string( $default_raw, $error_message ) ) {
                $default_decoded = json_decode( $default_raw, true );
                if ( is_array( $default_decoded ) ) {
                    $decoded_meta         = isset( $decoded['meta'] ) && is_array( $decoded['meta'] ) ? $decoded['meta'] : array();
                    $default_decoded_meta = isset( $default_decoded['meta'] ) && is_array( $default_decoded['meta'] ) ? $default_decoded['meta'] : array();

                    foreach ( array( 'customMapImageUrl', 'customMapImageOpacity', 'imageBounds' ) as $meta_key ) {
                        if ( ( ! isset( $decoded_meta[ $meta_key ] ) || '' === $decoded_meta[ $meta_key ] )
                            && isset( $default_decoded_meta[ $meta_key ] )
                        ) {
                            $decoded_meta[ $meta_key ] = $default_decoded_meta[ $meta_key ];
                        }
                    }

                    $decoded['meta'] = $decoded_meta;
                }
            }

            return $decoded;
        }
    }

    $fallback_raw = kuh_get_event_map_default_geojson_raw();

    if ( kuh_is_valid_geojson_string( $fallback_raw, $error_message ) ) {
        $decoded = json_decode( $fallback_raw, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }
    }

    return array(
        'type'     => 'FeatureCollection',
        'meta'     => array(
            'event'  => get_bloginfo( 'name' ),
            'center' => array( 7.4836, 52.6742 ),
            'zoom'   => 15,
        ),
        'features' => array(),
    );
}

/**
 * Sanitizer für das Option-Feld im WP-Admin.
 */
function kuh_sanitize_event_map_geojson_option( $value ) {
    $value = is_string( $value ) ? wp_unslash( $value ) : '';
    $error_message = '';

    if ( ! kuh_is_valid_geojson_string( $value, $error_message ) ) {
        add_settings_error(
            KUH_EVENT_MAP_OPTION_KEY,
            'kuh_event_map_geojson_invalid',
            $error_message,
            'error'
        );

        return get_option( KUH_EVENT_MAP_OPTION_KEY, '' );
    }

    $decoded = json_decode( $value, true );

    return wp_json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/**
 * Admin-Settings registrieren.
 */
function kuh_register_event_map_settings() {
    register_setting(
        'kuh_event_map_settings',
        KUH_EVENT_MAP_OPTION_KEY,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'kuh_sanitize_event_map_geojson_option',
            'default'           => '',
        )
    );
}
add_action( 'admin_init', 'kuh_register_event_map_settings' );

/**
 * Admin-Seite unter Design hinzufügen.
 */
function kuh_add_event_map_admin_page() {
    add_theme_page(
        'Event-Karte',
        'Event-Karte',
        'manage_options',
        'kuh-event-map',
        'kuh_render_event_map_admin_page'
    );
}
add_action( 'admin_menu', 'kuh_add_event_map_admin_page' );

/**
 * Assets nur für die Event-Karte-Adminseite laden.
 */
function kuh_enqueue_event_map_admin_assets( $hook_suffix ) {
    if ( 'appearance_page_kuh-event-map' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_style(
        'kuh-maplibre-admin',
        'https://unpkg.com/maplibre-gl@5.23.0/dist/maplibre-gl.css',
        array(),
        '5.23.0'
    );

    wp_enqueue_script(
        'kuh-maplibre-admin',
        'https://unpkg.com/maplibre-gl@5.23.0/dist/maplibre-gl.js',
        array(),
        '5.23.0',
        true
    );

    // Für die Bildauswahl des Hintergrundbildes.
    wp_enqueue_media();

    wp_enqueue_style(
        'kuh-event-map-admin',
        KUH_THEME_URI . '/assets/event-map-admin/editor.css',
        array( 'kuh-maplibre-admin' ),
        kuh_event_map_asset_version( '/assets/event-map-admin/editor.css' )
    );

    wp_enqueue_script(
        'kuh-event-map-admin',
        KUH_THEME_URI . '/assets/event-map-admin/editor.js',
        array( 'kuh-maplibre-admin' ),
        kuh_event_map_asset_version( '/assets/event-map-admin/editor.js' ),
        true
    );

    // Straßenlabels im Editor mitladen: erleichtert das Einnorden des Plans.
    wp_localize_script(
        'kuh-event-map-admin',
        'kuhEventMapAdmin',
        array(
            'tiles'              => kuh_get_event_map_tiles_config( true, true ),
            'categories'         => kuh_get_event_map_categories(),
            'areaDefaults'       => array(
                'fillColor'   => '#9ccf9c',
                'fillOpacity' => 28,
                'lineColor'   => '#4a8a4a',
            ),
            'routeDefaults'      => array(
                'color' => '#8a5a2b',
                'width' => 4,
            ),
            'mapBackgroundColor' => '#f3efe6',
            'defaultCenter'      => array( 7.4836, 52.6742 ),
            'defaultZoom'        => 15,
            'emojiPresets'       => array(
                '📍', '🏛️', '🚪', '🎭', '🅿️', '🚻', 'ℹ️', '🍺', '🍔', '🎡',
                '🎪', '🛍️', '🚑', '🚒', '♿', '🚰', '🎠', '🐎', '🎤', '🎻',
                '🔥', '📸', '🧒', '🚌', '🚲', '⛺', '🌿',
            ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'kuh_enqueue_event_map_admin_assets' );

/**
 * Reset auf Datei-Fallback (Option löschen).
 */
function kuh_handle_event_map_reset_action() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    check_admin_referer( 'kuh_event_map_reset_action' );

    delete_option( KUH_EVENT_MAP_OPTION_KEY );

    $redirect = add_query_arg(
        array(
            'page'  => 'kuh-event-map',
            'reset' => '1',
        ),
        admin_url( 'themes.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_kuh_event_map_reset', 'kuh_handle_event_map_reset_action' );

/**
 * Admin-View: Interaktiver Karten-Editor.
 *
 * Die Karte selbst ist das Bedienelement: Elemente lassen sich aus der Palette
 * per Drag & Drop absetzen, direkt verschieben und rechts feinjustieren.
 * Gespeichert wird weiterhin das GeoJSON im Feld KUH_EVENT_MAP_OPTION_KEY.
 */
function kuh_render_event_map_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Bewusst die effektiven Daten laden (inkl. der aus der Theme-Datei
    // ergänzten Bild-Meta): Der Editor soll zeigen, was das Frontend zeigt.
    // Beim Speichern werden diese Werte damit auch in der Datenbank festgeschrieben.
    $raw = wp_json_encode(
        kuh_get_event_map_geojson(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $categories = kuh_get_event_map_categories();

    settings_errors( KUH_EVENT_MAP_OPTION_KEY );

    if ( isset( $_GET['reset'] ) && '1' === $_GET['reset'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>Kartendaten wurden auf den Datei-Standard zurückgesetzt.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Event-Karte</h1>
        <p>
            Der Geländeplan wird direkt auf der Karte bearbeitet: Elemente aus der Palette
            per Drag &amp; Drop in die Karte ziehen, mit der Maus verschieben und rechts
            feinjustieren. Gespeichert wird ein GeoJSON – nach einem Reset gelten wieder
            die Daten aus <code>src/assets/map/event-map-pois.json</code>.
        </p>

        <div id="kuh-map-editor" class="kuh-map-editor">
            <form method="post" action="options.php" id="kuh-map-form">
                <?php settings_fields( 'kuh_event_map_settings' ); ?>

                <div class="kuh-map-editor__bar">
                    <div class="kuh-map-editor__tools" id="kuh-map-tools">
                        <?php
                        $kuh_tools = array(
                            'select' => array( 'label' => '↖ Auswählen', 'key' => 'V' ),
                            'poi'    => array( 'label' => '📍 Marker setzen', 'key' => 'P' ),
                            'text'   => array( 'label' => '🅣 Text setzen', 'key' => 'T' ),
                            'area'   => array( 'label' => '▧ Fläche zeichnen', 'key' => 'F' ),
                            'route'  => array( 'label' => '╱ Strecke zeichnen', 'key' => 'S' ),
                        );

                        foreach ( $kuh_tools as $kuh_tool => $kuh_tool_data ) :
                            ?>
                            <button
                                type="button"
                                class="kuh-map-tool"
                                data-kuh-tool="<?php echo esc_attr( $kuh_tool ); ?>"
                                aria-pressed="<?php echo 'select' === $kuh_tool ? 'true' : 'false'; ?>"
                            >
                                <span><?php echo esc_html( $kuh_tool_data['label'] ); ?></span>
                                <span class="kuh-map-tool__key"><?php echo esc_html( $kuh_tool_data['key'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="button" id="kuh-map-finish" hidden>Zeichnen abschließen</button>
                    <button type="button" class="button" id="kuh-map-cancel" hidden>Zeichnen abbrechen</button>

                    <span class="kuh-map-editor__bar-spacer"></span>

                    <span class="kuh-map-editor__dirty">Nicht gespeichert</span>
                    <button type="button" class="button" id="kuh-map-undo" title="Strg+Z">&#8630; Rückgängig</button>
                    <button type="button" class="button" id="kuh-map-redo" title="Strg+Umschalt+Z">&#8631; Wiederholen</button>
                    <button type="submit" class="button button-primary" id="kuh-map-save" title="Strg+S">Karte speichern</button>
                </div>

                <p class="kuh-map-editor__status" id="kuh-map-status"></p>

                <div class="kuh-map-editor__layout">
                    <div>
                        <div class="kuh-map-frame">
                            <div id="kuh-map-canvas" class="kuh-map-canvas">
                                <div id="kuh-map-hint" class="kuh-map-hint"></div>
                            </div>
                            <div id="kuh-map-legend" class="kuh-map-legend" aria-label="Kartenlegende"></div>
                        </div>
                    </div>

                    <div class="kuh-map-editor__sidebar">
                        <div class="kuh-map-panel">
                            <h3>Elemente hinzufügen</h3>
                            <p class="kuh-map-panel__hint">
                                In die Karte ziehen oder anklicken und dann in die Karte klicken.
                            </p>
                            <div class="kuh-map-palette" id="kuh-map-palette">
                                <?php foreach ( $categories as $kuh_key => $kuh_category ) : ?>
                                    <span
                                        class="kuh-map-palette__chip"
                                        draggable="true"
                                        role="button"
                                        tabindex="0"
                                        data-kuh-create="poi"
                                        data-kuh-category="<?php echo esc_attr( $kuh_key ); ?>"
                                        title="<?php echo esc_attr( $kuh_category['label'] ); ?> in die Karte ziehen"
                                    >
                                        <span
                                            class="kuh-map-palette__dot"
                                            style="background: <?php echo esc_attr( $kuh_category['color'] ); ?>;"
                                        ><?php echo esc_html( $kuh_category['emoji'] ); ?></span>
                                        <?php echo esc_html( $kuh_category['label'] ); ?>
                                    </span>
                                <?php endforeach; ?>

                                <span
                                    class="kuh-map-palette__chip"
                                    draggable="true"
                                    role="button"
                                    tabindex="0"
                                    data-kuh-create="text"
                                    data-kuh-category="location"
                                    title="Textbeschriftung in die Karte ziehen"
                                >
                                    <span class="kuh-map-palette__dot kuh-map-palette__dot--text" style="color:#15331b;">T</span>
                                    Textbeschriftung
                                </span>

                                <span
                                    class="kuh-map-palette__chip"
                                    draggable="true"
                                    role="button"
                                    tabindex="0"
                                    data-kuh-create="area"
                                    title="Fläche in die Karte ziehen (danach Eckpunkte verschieben)"
                                >
                                    <span class="kuh-map-palette__dot" style="background:#9ccf9c;">▧</span>
                                    Bereich / Fläche
                                </span>

                                <span
                                    class="kuh-map-palette__chip"
                                    draggable="true"
                                    role="button"
                                    tabindex="0"
                                    data-kuh-create="route"
                                    title="Strecke in die Karte ziehen (danach Punkte verschieben)"
                                >
                                    <span class="kuh-map-palette__dot" style="background:#8a5a2b;">╱</span>
                                    Strecke / Weg
                                </span>
                            </div>
                        </div>

                        <div class="kuh-map-panel">
                            <h3>Eigenschaften</h3>
                            <div class="kuh-map-props" id="kuh-map-props"></div>
                        </div>

                        <div class="kuh-map-panel">
                            <h3>Elemente</h3>
                            <div class="kuh-map-list" id="kuh-map-list"></div>
                        </div>

                        <div class="kuh-map-panel">
                            <h3>Startansicht</h3>
                            <p class="kuh-map-panel__hint" id="kuh-map-view-info"></p>
                            <button type="button" class="button" id="kuh-map-view-save">
                                Aktuelle Ansicht als Startansicht übernehmen
                            </button>
                        </div>

                        <div class="kuh-map-panel">
                            <h3>Hintergrundbild</h3>
                            <p class="kuh-map-panel__hint">
                                Liegt als Bild-Layer unter den Markern – z. B. der gezeichnete Geländeplan.
                            </p>

                            <div class="kuh-map-field">
                                <label for="kuh-map-image-url">Bild-URL</label>
                                <input type="text" id="kuh-map-image-url" class="code" placeholder="https://…" spellcheck="false" />
                            </div>

                            <p>
                                <button type="button" class="button" id="kuh-map-image-pick">Aus Mediathek wählen</button>
                                <button type="button" class="button" id="kuh-map-image-clear">Entfernen</button>
                            </p>

                            <div class="kuh-map-field">
                                <label for="kuh-map-image-opacity">
                                    Deckkraft <output id="kuh-map-image-opacity-out">30%</output>
                                </label>
                                <input type="range" id="kuh-map-image-opacity" min="0" max="100" step="1" value="30" />
                            </div>

                            <p>
                                <button type="button" class="button" id="kuh-map-image-align" aria-pressed="false">Bild ausrichten</button>
                                <button type="button" class="button" id="kuh-map-image-fit-area">An Fläche</button>
                                <button type="button" class="button" id="kuh-map-image-fit-view">An Ansicht</button>
                            </p>
                            <p class="kuh-map-panel__hint">
                                „Bild ausrichten" zeigt vier gelbe Ecken-Griffe, mit denen das Bild
                                passgenau über die Karte gelegt wird.
                            </p>
                        </div>

                        <details class="kuh-map-panel">
                            <summary><strong>Erweitert: GeoJSON direkt bearbeiten</strong></summary>
                            <p class="kuh-map-panel__hint">
                                Erlaubt ist GeoJSON vom Typ <strong>FeatureCollection</strong> mit
                                <strong>features</strong>-Array. Eigene Zusatzfelder bleiben beim
                                Bearbeiten in der Karte erhalten.
                            </p>
                            <textarea
                                id="kuh_event_map_geojson"
                                name="<?php echo esc_attr( KUH_EVENT_MAP_OPTION_KEY ); ?>"
                                rows="18"
                                class="large-text code"
                                spellcheck="false"
                                style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"
                            ><?php echo esc_textarea( $raw ); ?></textarea>
                            <p>
                                <button type="button" class="button" id="kuh-map-json-apply">JSON in die Karte übernehmen</button>
                            </p>
                        </details>
                    </div>
                </div>
            </form>

            <p class="description" style="margin-top:8px;">
                Kurzbefehle: <code>V</code> Auswählen · <code>P</code> Marker · <code>T</code> Text ·
                <code>F</code> Fläche · <code>S</code> Strecke · <code>Entf</code> löschen ·
                <code>Alt+Klick</code> auf einen Punkt-Griff entfernt ihn ·
                <code>Strg+Z</code> rückgängig · <code>Strg+S</code> speichern.
            </p>
        </div>

        <hr />

        <?php kuh_render_event_map_tiles_settings_form(); ?>

        <h2>Zurücksetzen</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'kuh_event_map_reset_action' ); ?>
            <input type="hidden" name="action" value="kuh_event_map_reset" />
            <p class="description" style="margin-bottom:8px;">
                Verwirft alle in der Datenbank gespeicherten Kartendaten und nutzt wieder die Theme-Datei.
            </p>
            <?php submit_button( 'Auf Datei-Standard zurücksetzen', 'secondary', 'submit', false ); ?>
        </form>
    </div>
    <?php
}

/**
 * POIs des Geländeplans als Auswahlliste: POI-ID => „Name (Kategorie)".
 *
 * Wird für die Verknüpfung von Bühnen mit der Karte genutzt.
 *
 * @return array<string,string>
 */
function kuh_get_event_map_poi_choices() {
    $geojson = kuh_get_event_map_geojson();
    $choices = array();

    foreach ( $geojson['features'] ?? array() as $feature ) {
        $props = $feature['properties'] ?? array();
        $id    = isset( $props['id'] ) ? (string) $props['id'] : '';
        $name  = isset( $props['name'] ) ? (string) $props['name'] : '';
        if ( ! $id || ! $name ) {
            continue;
        }

        $category = isset( $props['category'] ) ? (string) $props['category'] : '';
        $choices[ $id ] = $category ? sprintf( '%s (%s)', $name, $category ) : $name;
    }

    asort( $choices );

    return $choices;
}
