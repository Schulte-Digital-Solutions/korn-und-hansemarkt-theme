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
 * Admin-View: JSON bearbeiten.
 */
function kuh_render_event_map_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $raw = kuh_get_event_map_geojson_raw();

    if ( '' === trim( $raw ) ) {
        $raw = kuh_get_event_map_default_geojson_raw();
    }

    settings_errors( KUH_EVENT_MAP_OPTION_KEY );

    if ( isset( $_GET['reset'] ) && '1' === $_GET['reset'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>Kartendaten wurden auf den Datei-Standard zurückgesetzt.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Event-Karte</h1>
        <p>
            Bearbeite hier das GeoJSON für den Karten-Block.
            Bei leerem Wert oder Reset werden die Daten aus
            <code>src/assets/map/event-map-pois.json</code> verwendet.
        </p>

        <div class="notice notice-info" style="margin:12px 0 16px 0;">
            <p>
                Die POI-Maske unten bearbeitet sichtbar das JSON-Feld.
                Du kannst weiterhin direkt im JSON arbeiten und eigene Felder oder Feature-Typen ergänzen.
            </p>
        </div>

        <h2>Vorschau</h2>
        <p class="description" style="margin-bottom:8px;">
            Vorschau basiert direkt auf dem aktuellen JSON-Inhalt (auch ohne Speichern).
        </p>
        <div style="display:flex;gap:8px;align-items:center;margin:8px 0 12px 0;">
            <button type="button" class="button" id="kuh_preview_refresh">Vorschau aktualisieren</button>
            <span id="kuh_preview_status" style="color:#50575e;"></span>
        </div>
        <div
            id="kuh_event_map_preview"
            style="height:380px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden;margin-bottom:16px;background:#f6f7f7;"
        ></div>

        <form method="post" action="options.php">
            <?php settings_fields( 'kuh_event_map_settings' ); ?>

            <h2>POI-Maske (optional)</h2>
            <p class="description" style="margin-bottom:8px;">
                Für schnelle Pflege von Punkt-Markern. Eigene/komplexe Features bitte direkt im JSON bearbeiten.
            </p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 12px 0;">
                <button type="button" class="button" id="kuh_poi_import_from_json">POIs aus JSON laden</button>
                <button type="button" class="button" id="kuh_poi_add_row">POI hinzufügen</button>
                <button type="button" class="button button-secondary" id="kuh_poi_apply_to_json">POI-Maske ins JSON übernehmen</button>
                <span id="kuh_poi_status" style="align-self:center;color:#50575e;"></span>
            </div>

            <table class="widefat striped" id="kuh_poi_table" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="width:14%;">ID</th>
                        <th style="width:16%;">Name</th>
                        <th style="width:12%;">Kategorie</th>
                        <th style="width:10%;">Lat</th>
                        <th style="width:10%;">Lng</th>
                        <th>Beschreibung</th>
                        <th style="width:90px;">Aktion</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="kuh_event_map_geojson">GeoJSON</label>
                        </th>
                        <td>
                            <textarea
                                id="kuh_event_map_geojson"
                                name="<?php echo esc_attr( KUH_EVENT_MAP_OPTION_KEY ); ?>"
                                rows="28"
                                class="large-text code"
                                style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"
                            ><?php echo esc_textarea( $raw ); ?></textarea>
                            <p class="description">
                                Erlaubt ist GeoJSON vom Typ <strong>FeatureCollection</strong> mit <strong>features</strong>-Array.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( 'GeoJSON speichern' ); ?>
        </form>

        <script>
        (function () {
            const jsonField = document.getElementById('kuh_event_map_geojson');
            const tbody = document.querySelector('#kuh_poi_table tbody');
            const status = document.getElementById('kuh_poi_status');
            const importBtn = document.getElementById('kuh_poi_import_from_json');
            const addBtn = document.getElementById('kuh_poi_add_row');
            const applyBtn = document.getElementById('kuh_poi_apply_to_json');
            const previewBtn = document.getElementById('kuh_preview_refresh');
            const previewStatus = document.getElementById('kuh_preview_status');
            const previewContainer = document.getElementById('kuh_event_map_preview');

            const CATEGORY_OPTIONS = [
                { value: 'location', label: 'Benannte Orte' },
                { value: 'entrance', label: 'Eingänge' },
                { value: 'stage', label: 'Bühnen' },
                { value: 'parking', label: 'Parkplätze' },
                { value: 'toilet', label: 'Toiletten' },
                { value: 'info', label: 'Info & Hilfe' },
            ];

            const ICON_BY_CATEGORY = {
                location: 'building',
                entrance: 'entrance',
                stage: 'stage',
                parking: 'parking',
                toilet: 'toilet',
                info: 'info',
            };

            let previewMap = null;
            let previewMarkers = [];
            const previewAreaSourceId = 'kuh-preview-area-source';
            const previewAreaFillLayerId = 'kuh-preview-area-fill';
            const previewAreaLineLayerId = 'kuh-preview-area-line';

            function setStatus(text, isError) {
                status.textContent = text || '';
                status.style.color = isError ? '#b32d2e' : '#1d2327';
            }

            function setPreviewStatus(text, isError) {
                previewStatus.textContent = text || '';
                previewStatus.style.color = isError ? '#b32d2e' : '#1d2327';
            }

            function safeParseJson() {
                try {
                    const parsed = JSON.parse(jsonField.value || '{}');
                    if (!parsed || typeof parsed !== 'object') {
                        throw new Error('JSON muss ein Objekt sein.');
                    }
                    if (!Array.isArray(parsed.features)) {
                        parsed.features = [];
                    }
                    return parsed;
                } catch (err) {
                    throw new Error(err && err.message ? err.message : 'Ungültiges JSON');
                }
            }

            function createInputCell(value, placeholder) {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'regular-text';
                input.style.width = '100%';
                input.value = value || '';
                input.placeholder = placeholder || '';
                td.appendChild(input);
                return { td, input };
            }

            function createTextareaCell(value, placeholder) {
                const td = document.createElement('td');
                const input = document.createElement('textarea');
                input.rows = 2;
                input.className = 'large-text';
                input.style.width = '100%';
                input.value = value || '';
                input.placeholder = placeholder || '';
                td.appendChild(input);
                return { td, input };
            }

            function createCategoryCell(value) {
                const td = document.createElement('td');
                const select = document.createElement('select');
                select.className = 'regular-text';
                select.style.width = '100%';

                CATEGORY_OPTIONS.forEach(function (item) {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    select.appendChild(option);
                });

                const current = (value || '').trim();
                if (current && !CATEGORY_OPTIONS.some(function (item) { return item.value === current; })) {
                    const customOption = document.createElement('option');
                    customOption.value = current;
                    customOption.textContent = current + ' (custom)';
                    select.appendChild(customOption);
                }

                select.value = current || 'location';
                td.appendChild(select);

                return { td, input: select };
            }

            function iconForCategory(category) {
                return ICON_BY_CATEGORY[category] || 'pin';
            }

            function addRow(data) {
                const tr = document.createElement('tr');

                const idCell = createInputCell(data.id || '', 'z.B. eingang-nord');
                const nameCell = createInputCell(data.name || '', 'z.B. Eingang Nord');
                const categoryCell = createCategoryCell(data.category || 'location');
                const latCell = createInputCell(typeof data.lat === 'number' ? String(data.lat) : '', '52.6742');
                const lngCell = createInputCell(typeof data.lng === 'number' ? String(data.lng) : '', '7.4836');
                const descCell = createTextareaCell(data.description || '', 'Kurzbeschreibung');

                tr.appendChild(idCell.td);
                tr.appendChild(nameCell.td);
                tr.appendChild(categoryCell.td);
                tr.appendChild(latCell.td);
                tr.appendChild(lngCell.td);
                tr.appendChild(descCell.td);

                const actionTd = document.createElement('td');
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'button button-link-delete';
                removeBtn.textContent = 'Entfernen';
                removeBtn.addEventListener('click', function () {
                    tr.remove();
                    setStatus('POI entfernt. Klick auf "POI-Maske ins JSON übernehmen" zum Speichern im JSON.', false);
                });
                actionTd.appendChild(removeBtn);
                tr.appendChild(actionTd);

                tr._kuhPoi = {
                    id: idCell.input,
                    name: nameCell.input,
                    category: categoryCell.input,
                    lat: latCell.input,
                    lng: lngCell.input,
                    description: descCell.input,
                };

                tbody.appendChild(tr);
            }

            function clearRows() {
                tbody.innerHTML = '';
            }

            function rowsToPointFeatures() {
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const features = [];

                for (const row of rows) {
                    const ref = row._kuhPoi;
                    if (!ref) continue;

                    const lat = parseFloat(ref.lat.value);
                    const lng = parseFloat(ref.lng.value);

                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        continue;
                    }

                    features.push({
                        type: 'Feature',
                        properties: {
                            id: (ref.id.value || '').trim(),
                            category: (ref.category.value || '').trim(),
                            name: (ref.name.value || '').trim(),
                            description: (ref.description.value || '').trim(),
                            icon: iconForCategory((ref.category.value || '').trim()),
                        },
                        geometry: {
                            type: 'Point',
                            coordinates: [lng, lat],
                        },
                    });
                }

                return features;
            }

            function importFromJson() {
                try {
                    const geo = safeParseJson();
                    clearRows();

                    let pointCount = 0;
                    geo.features.forEach(function (feature) {
                        if (!feature || !feature.geometry || feature.geometry.type !== 'Point') return;
                        const coords = feature.geometry.coordinates || [];
                        if (!Array.isArray(coords) || coords.length < 2) return;

                        const lng = Number(coords[0]);
                        const lat = Number(coords[1]);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                        addRow({
                            id: feature.properties && feature.properties.id ? feature.properties.id : '',
                            name: feature.properties && feature.properties.name ? feature.properties.name : '',
                            category: feature.properties && feature.properties.category ? feature.properties.category : '',
                            description: feature.properties && feature.properties.description ? feature.properties.description : '',
                            lat: lat,
                            lng: lng,
                        });
                        pointCount += 1;
                    });

                    setStatus(pointCount + ' Punkt-Feature(s) aus JSON geladen.', false);
                } catch (err) {
                    setStatus('Import fehlgeschlagen: ' + err.message, true);
                }
            }

            function applyRowsToJson() {
                try {
                    const geo = safeParseJson();
                    const newPoints = rowsToPointFeatures();

                    // Nicht-Punkt-Features erhalten, damit Custom-Features nicht verloren gehen.
                    const nonPointFeatures = geo.features.filter(function (feature) {
                        return !feature || !feature.geometry || feature.geometry.type !== 'Point';
                    });

                    geo.features = newPoints.concat(nonPointFeatures);

                    jsonField.value = JSON.stringify(geo, null, 2);
                    setStatus(
                        newPoints.length + ' Punkt-Feature(s) ins JSON geschrieben. Nicht-Punkt-Features wurden beibehalten.',
                        false
                    );

                    renderPreview();
                } catch (err) {
                    setStatus('Übernahme fehlgeschlagen: ' + err.message, true);
                }
            }

            function colorForCategory(category) {
                switch (category) {
                    case 'location': return '#15331b';
                    case 'entrance': return '#725c0c';
                    case 'stage': return '#8b1a1a';
                    case 'parking': return '#1a4a6b';
                    case 'toilet': return '#4a4a6b';
                    case 'info': return '#2d6b4a';
                    default: return '#444';
                }
            }

            function clearPreviewMarkers() {
                previewMarkers.forEach(function (marker) { marker.remove(); });
                previewMarkers = [];
            }

            function renderPreviewAreas(geo) {
                const areaFeatures = (geo.features || []).filter(function (feature) {
                    if (!feature || !feature.geometry) return false;
                    return feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon';
                });

                const areaGeo = {
                    type: 'FeatureCollection',
                    features: areaFeatures,
                };

                const source = previewMap.getSource(previewAreaSourceId);
                if (source && source.setData) {
                    source.setData(areaGeo);
                    return;
                }

                previewMap.addSource(previewAreaSourceId, {
                    type: 'geojson',
                    data: areaGeo,
                });

                previewMap.addLayer({
                    id: previewAreaFillLayerId,
                    type: 'fill',
                    source: previewAreaSourceId,
                    paint: {
                        'fill-color': '#9ccf9c',
                        'fill-opacity': 0.28,
                    },
                });

                previewMap.addLayer({
                    id: previewAreaLineLayerId,
                    type: 'line',
                    source: previewAreaSourceId,
                    paint: {
                        'line-color': '#4a8a4a',
                        'line-width': 2,
                        'line-opacity': 0.75,
                    },
                });
            }

            function ensurePreviewMap(center, zoom) {
                if (!window.maplibregl) {
                    throw new Error('MapLibre konnte im Backend nicht geladen werden.');
                }

                if (!previewMap) {
                    previewMap = new window.maplibregl.Map({
                        container: previewContainer,
                        style: {
                            version: 8,
                            sources: {
                                osm: {
                                    type: 'raster',
                                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                                    tileSize: 256,
                                    maxzoom: 19,
                                },
                            },
                            layers: [
                                {
                                    id: 'osm',
                                    type: 'raster',
                                    source: 'osm',
                                },
                            ],
                        },
                        center: center,
                        zoom: zoom,
                    });

                    previewMap.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right');
                } else {
                    previewMap.jumpTo({ center: center, zoom: zoom });
                }
            }

            function renderPreview() {
                try {
                    const geo = safeParseJson();
                    const center = Array.isArray(geo.meta && geo.meta.center) && geo.meta.center.length >= 2
                        ? [Number(geo.meta.center[0]), Number(geo.meta.center[1])]
                        : [7.4836, 52.6742];
                    const zoom = typeof (geo.meta && geo.meta.zoom) === 'number' ? geo.meta.zoom : 15;

                    ensurePreviewMap(center, zoom);
                    clearPreviewMarkers();
                    renderPreviewAreas(geo);

                    let points = 0;
                    let areas = 0;
                    (geo.features || []).forEach(function (feature) {
                        if (feature && feature.geometry && (feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon')) {
                            areas += 1;
                        }

                        if (!feature || !feature.geometry || feature.geometry.type !== 'Point') return;
                        const coords = feature.geometry.coordinates || [];
                        if (!Array.isArray(coords) || coords.length < 2) return;

                        const lng = Number(coords[0]);
                        const lat = Number(coords[1]);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                        const category = feature.properties && feature.properties.category ? String(feature.properties.category) : '';
                        const name = feature.properties && feature.properties.name ? String(feature.properties.name) : 'Ort';

                        const markerEl = document.createElement('div');
                        markerEl.style.width = '14px';
                        markerEl.style.height = '14px';
                        markerEl.style.borderRadius = '50%';
                        markerEl.style.background = colorForCategory(category);
                        markerEl.style.border = '2px solid #fff';
                        markerEl.style.boxShadow = '0 0 0 1px rgba(0,0,0,0.35)';
                        markerEl.title = name;

                        const popup = new window.maplibregl.Popup({ offset: 10 }).setHTML(
                            '<strong>' + name.replace(/</g, '&lt;') + '</strong>'
                        );

                        const marker = new window.maplibregl.Marker({ element: markerEl })
                            .setLngLat([lng, lat])
                            .setPopup(popup)
                            .addTo(previewMap);

                        previewMarkers.push(marker);
                        points += 1;
                    });

                    setPreviewStatus(points + ' Punkt-Feature(s), ' + areas + ' Flaechen-Feature(s) in Vorschau.', false);
                } catch (err) {
                    setPreviewStatus('Vorschau fehlgeschlagen: ' + (err && err.message ? err.message : String(err)), true);
                }
            }

            importBtn.addEventListener('click', importFromJson);
            addBtn.addEventListener('click', function () {
                addRow({ id: '', name: '', category: '', lat: '', lng: '', description: '' });
                setStatus('Leere POI-Zeile hinzugefügt.', false);
            });
            applyBtn.addEventListener('click', applyRowsToJson);
            previewBtn.addEventListener('click', renderPreview);

            importFromJson();
            renderPreview();
        })();
        </script>

        <hr />

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'kuh_event_map_reset_action' ); ?>
            <input type="hidden" name="action" value="kuh_event_map_reset" />
            <?php submit_button( 'Auf Datei-Standard zurücksetzen', 'secondary', 'submit', false ); ?>
        </form>
    </div>
    <?php
}
