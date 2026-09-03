<?php
/**
 * Tile-Anbieter und API-Key für die Event-Karte.
 *
 * Kapselt Anbieterwahl, API-Key und Attribution der Kartenkacheln,
 * damit Frontend-Block und Admin-Vorschau dieselben URLs verwenden.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_EVENT_MAP_TILES_SETTINGS_GROUP       = 'kuh_event_map_tiles_settings';
const KUH_EVENT_MAP_TILES_PROVIDER_OPTION      = 'kuh_event_map_tiles_provider';
const KUH_EVENT_MAP_TILES_API_KEY_OPTION       = 'kuh_event_map_tiles_api_key';
const KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION    = 'kuh_event_map_tiles_custom_url';
const KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION = 'kuh_event_map_tiles_custom_labels_url';
const KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION   = 'kuh_event_map_tiles_attribution';

/**
 * Verfügbare Tile-Anbieter.
 */
function kuh_get_event_map_tile_providers() {
    return array(
        'carto'  => 'CARTO Voyager – reduzierte Basiskarte (API-Key erforderlich)',
        'osm'    => 'OpenStreetMap Standard (ohne API-Key)',
        'custom' => 'Eigener Tile-Server / anderer Anbieter',
    );
}

/**
 * Eingestellter Anbieter.
 */
function kuh_get_event_map_tiles_provider() {
    $provider = get_option( KUH_EVENT_MAP_TILES_PROVIDER_OPTION, 'carto' );

    return array_key_exists( $provider, kuh_get_event_map_tile_providers() ) ? $provider : 'carto';
}

/**
 * API-Key für die Kartenkacheln.
 *
 * Eine Konstante KUH_EVENT_MAP_TILES_API_KEY in der wp-config.php
 * hat Vorrang vor der im Backend gepflegten Option.
 */
function kuh_get_event_map_tiles_api_key() {
    if ( kuh_event_map_tiles_api_key_is_constant() ) {
        return trim( KUH_EVENT_MAP_TILES_API_KEY );
    }

    $key = get_option( KUH_EVENT_MAP_TILES_API_KEY_OPTION, '' );

    return is_string( $key ) ? trim( $key ) : '';
}

/**
 * Ist der API-Key per Konstante gesetzt? Dann gewinnt die Konstante.
 */
function kuh_event_map_tiles_api_key_is_constant() {
    return defined( 'KUH_EVENT_MAP_TILES_API_KEY' )
        && is_string( KUH_EVENT_MAP_TILES_API_KEY )
        && '' !== trim( KUH_EVENT_MAP_TILES_API_KEY );
}

/**
 * Hängt den API-Key an eine Tile-URL an.
 *
 * Enthält die URL den Platzhalter {key}, wird dieser ersetzt –
 * sonst wird der Key als Query-Parameter angehängt.
 */
function kuh_apply_event_map_tile_api_key( $url, $api_key, $query_param = 'key' ) {
    $url = trim( (string) $url );

    if ( '' === $url ) {
        return '';
    }

    if ( false !== strpos( $url, '{key}' ) ) {
        return str_replace( '{key}', rawurlencode( $api_key ), $url );
    }

    if ( '' === $api_key ) {
        return $url;
    }

    $separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';

    return $url . $separator . $query_param . '=' . rawurlencode( $api_key );
}

/**
 * Tile-Konfiguration für Frontend-Block und Admin-Vorschau.
 *
 * "provider" ist der tatsächlich genutzte Anbieter: Ist z. B. "custom"
 * eingestellt, aber keine URL gepflegt, wird auf OSM zurückgefallen –
 * und genau das steht dann auch im Rückgabewert (wichtig für die
 * Anbieter-Nennung im Consent-Hinweis).
 *
 * @param bool $use_minimal Reduzierte Basiskarte laut Block-Attribut.
 * @param bool $show_labels Straßenbeschriftungen als Overlay laden.
 * @return array{provider:string,baseTileUrls:string[],labelTileUrls:string[],tileAttribution:string,minimalBaseTiles:bool,hasApiKey:bool,needsApiKey:bool}
 */
function kuh_get_event_map_tiles_config( $use_minimal = true, $show_labels = false ) {
    $configured_provider = kuh_get_event_map_tiles_provider();
    $api_key             = kuh_get_event_map_tiles_api_key();

    $osm_attribution   = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende';
    $carto_attribution = $osm_attribution . ', © <a href="https://carto.com/attributions">CARTO</a>';
    $osm_tile_url      = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    $provider      = $configured_provider;
    $base_urls     = array();
    $label_urls    = array();
    $attribution   = $osm_attribution;
    // Nur eine reduzierte Basiskarte verträgt den Kontrast-Filter im Frontend.
    $minimal_tiles = false;

    if ( 'custom' === $configured_provider ) {
        $custom_base = kuh_apply_event_map_tile_api_key(
            (string) get_option( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION, '' ),
            $api_key
        );

        if ( '' !== $custom_base ) {
            $base_urls[]   = $custom_base;
            $attribution   = '';
            $minimal_tiles = $use_minimal;

            if ( $show_labels ) {
                $custom_labels = kuh_apply_event_map_tile_api_key(
                    (string) get_option( KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION, '' ),
                    $api_key
                );

                if ( '' !== $custom_labels ) {
                    $label_urls[] = $custom_labels;
                }
            }
        } else {
            // Keine eigene URL gepflegt: OSM statt leerer Karte.
            $provider    = 'osm';
            $base_urls[] = $osm_tile_url;
        }
    } elseif ( 'osm' === $configured_provider || ! $use_minimal ) {
        $provider    = 'osm';
        $base_urls[] = $osm_tile_url;
    } else {
        // Von CARTO dokumentierter Host für die Key-Nutzung (ohne a–d-Subdomains).
        $base_urls[] = kuh_apply_event_map_tile_api_key(
            'https://basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
            $api_key
        );

        if ( $show_labels ) {
            $label_urls[] = kuh_apply_event_map_tile_api_key(
                'https://basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
                $api_key
            );
        }

        $attribution   = $carto_attribution;
        $minimal_tiles = true;
    }

    $custom_attribution = trim( (string) get_option( KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION, '' ) );

    if ( '' !== $custom_attribution ) {
        $attribution = wp_kses_post( $custom_attribution );
    }

    return array(
        'provider'         => $provider,
        'baseTileUrls'     => $base_urls,
        'labelTileUrls'    => $label_urls,
        'tileAttribution'  => $attribution,
        'minimalBaseTiles' => $minimal_tiles,
        'hasApiKey'        => ( '' !== $api_key ),
        'needsApiKey'      => ( 'carto' === $configured_provider ),
    );
}

/**
 * Settings registrieren.
 *
 * Eigene Settings-Gruppe, damit das GeoJSON-Formular davon unberührt bleibt:
 * options.php leert alle Optionen einer Gruppe, die nicht mitgesendet werden.
 */
function kuh_register_event_map_tiles_settings() {
    register_setting(
        KUH_EVENT_MAP_TILES_SETTINGS_GROUP,
        KUH_EVENT_MAP_TILES_PROVIDER_OPTION,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'kuh_sanitize_event_map_tiles_provider',
            'default'           => 'carto',
        )
    );

    register_setting(
        KUH_EVENT_MAP_TILES_SETTINGS_GROUP,
        KUH_EVENT_MAP_TILES_API_KEY_OPTION,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'kuh_sanitize_event_map_tiles_api_key',
            'default'           => '',
        )
    );

    foreach ( array( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION, KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION ) as $option_key ) {
        register_setting(
            KUH_EVENT_MAP_TILES_SETTINGS_GROUP,
            $option_key,
            array(
                'type'              => 'string',
                'sanitize_callback' => 'kuh_sanitize_event_map_tile_url',
                'default'           => '',
            )
        );
    }

    register_setting(
        KUH_EVENT_MAP_TILES_SETTINGS_GROUP,
        KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'kuh_sanitize_event_map_tile_attribution',
            'default'           => '',
        )
    );
}
add_action( 'admin_init', 'kuh_register_event_map_tiles_settings' );

/**
 * Sanitizer: Anbieter.
 */
function kuh_sanitize_event_map_tiles_provider( $value ) {
    $value = is_string( $value ) ? sanitize_key( wp_unslash( $value ) ) : '';

    return array_key_exists( $value, kuh_get_event_map_tile_providers() ) ? $value : 'carto';
}

/**
 * Sanitizer: API-Key.
 */
function kuh_sanitize_event_map_tiles_api_key( $value ) {
    $value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';

    return sanitize_text_field( $value );
}

/**
 * Sanitizer: Tile-URL-Vorlage.
 *
 * esc_url_raw() würde die Platzhalter {z}/{x}/{y}/{key} zerstören,
 * deshalb werden sie für die Prüfung maskiert und danach zurückgeschrieben.
 */
function kuh_sanitize_event_map_tile_url( $value ) {
    $value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';

    if ( '' === $value ) {
        return '';
    }

    $placeholders = array( '{z}', '{x}', '{y}', '{s}', '{r}', '{key}' );
    $tokens       = array();
    $masked       = $value;

    foreach ( $placeholders as $index => $placeholder ) {
        $token            = 'KUHPLACEHOLDER' . $index;
        $tokens[ $token ] = $placeholder;
        $masked           = str_replace( $placeholder, $token, $masked );
    }

    $masked = esc_url_raw( $masked, array( 'http', 'https' ) );

    if ( '' === $masked ) {
        add_settings_error(
            KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION,
            'kuh_event_map_tile_url_invalid',
            'Die Tile-URL ist ungültig. Erlaubt sind nur http(s)-URLs.',
            'error'
        );

        return '';
    }

    return str_replace( array_keys( $tokens ), array_values( $tokens ), $masked );
}

/**
 * Sanitizer: Attribution (einfache Links erlaubt).
 */
function kuh_sanitize_event_map_tile_attribution( $value ) {
    $value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';

    return wp_kses(
        $value,
        array(
            'a'      => array(
                'href'   => array(),
                'title'  => array(),
                'target' => array(),
                'rel'    => array(),
            ),
            'span'   => array(),
            'strong' => array(),
            'em'     => array(),
        )
    );
}

/**
 * Admin-View: Formular für Anbieter und API-Key.
 */
function kuh_render_event_map_tiles_settings_form() {
    $provider           = kuh_get_event_map_tiles_provider();
    $api_key_locked     = kuh_event_map_tiles_api_key_is_constant();
    $api_key            = (string) get_option( KUH_EVENT_MAP_TILES_API_KEY_OPTION, '' );
    $custom_url         = (string) get_option( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION, '' );
    $custom_labels_url  = (string) get_option( KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION, '' );
    $custom_attribution = (string) get_option( KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION, '' );
    $config             = kuh_get_event_map_tiles_config( true, false );

    settings_errors( KUH_EVENT_MAP_TILES_PROVIDER_OPTION );
    settings_errors( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION );
    ?>
    <h2>Kartenkacheln &amp; API-Key</h2>
    <p class="description" style="margin-bottom:8px;">
        Legt fest, woher die Hintergrundkacheln der Karte geladen werden.
        Der API-Key wird an jede Kachel-Anfrage angehängt und gilt für alle Karten-Blöcke.
    </p>

    <?php if ( $config['needsApiKey'] && ! $config['hasApiKey'] ) : ?>
        <div class="notice notice-warning inline" style="margin:12px 0;">
            <p>
                Für <strong>CARTO</strong> ist noch kein API-Key hinterlegt.
                Ohne Key kann der Anbieter die Kacheln blockieren und die Karte bleibt leer.
                Einen kostenlosen Key gibt es unter
                <a href="https://carto.com/basemaps/apikey/" target="_blank" rel="noopener noreferrer">carto.com/basemaps/apikey</a>.
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( KUH_EVENT_MAP_TILES_SETTINGS_GROUP ); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( KUH_EVENT_MAP_TILES_PROVIDER_OPTION ); ?>">Anbieter</label>
                    </th>
                    <td>
                        <select
                            id="<?php echo esc_attr( KUH_EVENT_MAP_TILES_PROVIDER_OPTION ); ?>"
                            name="<?php echo esc_attr( KUH_EVENT_MAP_TILES_PROVIDER_OPTION ); ?>"
                        >
                            <?php foreach ( kuh_get_event_map_tile_providers() as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $provider, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <strong>OpenStreetMap Standard</strong> braucht keinen Key, zeigt aber alle
                            fremden Beschriftungen und POIs – die eigenen Marker heben sich dann weniger ab.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( KUH_EVENT_MAP_TILES_API_KEY_OPTION ); ?>">API-Key</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="<?php echo esc_attr( KUH_EVENT_MAP_TILES_API_KEY_OPTION ); ?>"
                            name="<?php echo esc_attr( KUH_EVENT_MAP_TILES_API_KEY_OPTION ); ?>"
                            value="<?php echo esc_attr( $api_key ); ?>"
                            class="regular-text code"
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <?php if ( $api_key_locked ) : ?>
                            <p class="description" style="color:#996800;">
                                <strong>Achtung:</strong> <code>KUH_EVENT_MAP_TILES_API_KEY</code> ist in der
                                <code>wp-config.php</code> gesetzt und hat Vorrang vor diesem Feld.
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            Wird bei CARTO als <code>key</code> an die Tile-URL angehängt.
                            Bei eigenen URLs steuert der Platzhalter <code>{key}</code>, wo der Key landet.
                        </p>
                        <p class="description">
                            <strong>Hinweis:</strong> Der Key ist im Browser sichtbar – das lässt sich bei
                            Kartenkacheln nicht vermeiden. Beim Anbieter deshalb auf die eigenen Domains
                            einschränken.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION ); ?>">Eigene Tile-URL</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION ); ?>"
                            name="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_URL_OPTION ); ?>"
                            value="<?php echo esc_attr( $custom_url ); ?>"
                            class="large-text code"
                            placeholder="https://api.example.com/maps/basic/{z}/{x}/{y}.png?key={key}"
                            spellcheck="false"
                        />
                        <p class="description">
                            Nur relevant beim Anbieter <em>Eigener Tile-Server</em>.
                            Platzhalter: <code>{z}</code>, <code>{x}</code>, <code>{y}</code>, <code>{key}</code>.
                            Bleibt das Feld leer, wird auf OpenStreetMap zurückgefallen.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION ); ?>">Eigene Tile-URL (Beschriftungen)</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION ); ?>"
                            name="<?php echo esc_attr( KUH_EVENT_MAP_TILES_CUSTOM_LABELS_OPTION ); ?>"
                            value="<?php echo esc_attr( $custom_labels_url ); ?>"
                            class="large-text code"
                            placeholder="optional – nur wenn der Block Straßennamen einblenden soll"
                            spellcheck="false"
                        />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION ); ?>">Attribution</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="<?php echo esc_attr( KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION ); ?>"
                            name="<?php echo esc_attr( KUH_EVENT_MAP_TILES_ATTRIBUTION_OPTION ); ?>"
                            value="<?php echo esc_attr( $custom_attribution ); ?>"
                            class="large-text code"
                            placeholder="leer lassen = Standard-Attribution des Anbieters"
                            spellcheck="false"
                        />
                        <p class="description">
                            Vorgeschriebener Quellenhinweis auf der Karte. Einfache Links sind erlaubt.
                            CARTO verlangt, dass CARTO und OpenStreetMap genannt bleiben.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( 'Karten-API speichern' ); ?>
    </form>
    <hr />
    <?php
}
