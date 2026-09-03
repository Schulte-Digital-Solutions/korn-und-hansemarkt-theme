/**
 * Interaktiver Geländeplan-Editor für das WordPress-Backend
 * (Design → Event-Karte).
 *
 * Bearbeitet dieselbe GeoJSON-FeatureCollection, die auch der Block
 * `kuh/event-map` im Frontend rendert. Das versteckte Textarea
 * `#kuh_event_map_geojson` bleibt das Speicherfeld – der Editor schreibt
 * dort vor jedem Absenden den aktuellen Stand hinein.
 *
 * Datenmodell (bewusst nah an der GeoJSON-simplestyle-Spec):
 *   Point       properties: category, name, description, id, icon,
 *                           display ('pin' | 'label'), emoji, marker-color
 *   Polygon     properties: name, description, id, fill, fill-opacity, stroke
 *   LineString  properties: name, description, id, stroke, stroke-width, dashed
 *
 * @package KornUndHansemarkt
 */
/* global maplibregl, wp */
(function () {
    'use strict';

    var CONFIG = window.kuhEventMapAdmin || {};
    var CATEGORIES = CONFIG.categories || {};
    var AREA = CONFIG.areaDefaults || { fillColor: '#9ccf9c', fillOpacity: 28, lineColor: '#4a8a4a' };
    var ROUTE = CONFIG.routeDefaults || { color: '#8a5a2b', width: 4 };
    var EMOJI_PRESETS = CONFIG.emojiPresets || [];
    var TILES = CONFIG.tiles || {};
    var DEFAULT_CENTER = CONFIG.defaultCenter || [7.4836, 52.6742];
    var DEFAULT_ZOOM = typeof CONFIG.defaultZoom === 'number' ? CONFIG.defaultZoom : 15;

    var SOURCE_SHAPES = 'kuh-edit-shapes';
    var SOURCE_DRAFT = 'kuh-edit-draft';
    var SOURCE_IMAGE = 'kuh-edit-image';
    var LAYER_AREA_FILL = 'kuh-area-fill';
    var LAYER_AREA_LINE = 'kuh-area-line';
    var LAYER_ROUTE_CASING = 'kuh-route-casing';
    var LAYER_ROUTE_LINE = 'kuh-route-line';
    var LAYER_ROUTE_DASH = 'kuh-route-dash';
    var LAYER_SELECTED = 'kuh-selected-outline';
    var LAYER_IMAGE = 'kuh-image-layer';
    var SHAPE_LAYERS = [LAYER_AREA_FILL, LAYER_AREA_LINE, LAYER_ROUTE_LINE, LAYER_ROUTE_DASH, LAYER_ROUTE_CASING];

    var HINTS = {
        select: 'Element anklicken zum Auswählen · gedrückt halten und ziehen zum Verschieben',
        poi: 'In die Karte klicken, um einen Marker zu setzen · Esc bricht ab',
        text: 'In die Karte klicken, um eine Textbeschriftung zu setzen · Esc bricht ab',
        area: 'Klicken setzt Eckpunkte · Doppelklick oder Enter schließt die Fläche · Rücktaste entfernt den letzten Punkt · Esc bricht ab',
        route: 'Klicken setzt Wegpunkte · Doppelklick oder Enter beendet die Strecke · Rücktaste entfernt den letzten Punkt · Esc bricht ab',
    };

    // ── DOM ──────────────────────────────────────────────────────────────
    var root = document.getElementById('kuh-map-editor');
    if (!root) {
        return;
    }

    var el = {
        canvas: document.getElementById('kuh-map-canvas'),
        hint: document.getElementById('kuh-map-hint'),
        legend: document.getElementById('kuh-map-legend'),
        tools: document.getElementById('kuh-map-tools'),
        palette: document.getElementById('kuh-map-palette'),
        list: document.getElementById('kuh-map-list'),
        props: document.getElementById('kuh-map-props'),
        status: document.getElementById('kuh-map-status'),
        json: document.getElementById('kuh_event_map_geojson'),
        form: document.getElementById('kuh-map-form'),
        save: document.getElementById('kuh-map-save'),
        undo: document.getElementById('kuh-map-undo'),
        redo: document.getElementById('kuh-map-redo'),
        finish: document.getElementById('kuh-map-finish'),
        cancel: document.getElementById('kuh-map-cancel'),
        jsonApply: document.getElementById('kuh-map-json-apply'),
        viewSave: document.getElementById('kuh-map-view-save'),
        viewInfo: document.getElementById('kuh-map-view-info'),
        imageUrl: document.getElementById('kuh-map-image-url'),
        imagePick: document.getElementById('kuh-map-image-pick'),
        imageClear: document.getElementById('kuh-map-image-clear'),
        imageOpacity: document.getElementById('kuh-map-image-opacity'),
        imageOpacityOut: document.getElementById('kuh-map-image-opacity-out'),
        imageAlign: document.getElementById('kuh-map-image-align'),
        imageFitArea: document.getElementById('kuh-map-image-fit-area'),
        imageFitView: document.getElementById('kuh-map-image-fit-view'),
    };

    if (!el.canvas || !el.json) {
        return;
    }

    // ── State ────────────────────────────────────────────────────────────
    var state = {
        geo: emptyCollection(),
        tool: 'select',
        draftCategory: 'stage',
        selected: -1,
        draft: null,
        past: [],
        future: [],
        dirty: false,
        alignImage: false,
        visible: { area: true, route: true },
        dragPayload: null,
    };

    Object.keys(CATEGORIES).forEach(function (key) {
        state.visible[key] = true;
    });

    var map = null;
    var markers = [];
    var handles = [];
    var imageObjectUrl = null;
    var imageLayerUrl = null;
    var translating = null;
    var suppressNextClick = false;

    // ── Hilfsfunktionen ──────────────────────────────────────────────────

    function emptyCollection() {
        return {
            type: 'FeatureCollection',
            meta: { center: DEFAULT_CENTER.slice(), zoom: DEFAULT_ZOOM },
            features: [],
        };
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function categoryFor(key) {
        return (
            CATEGORIES[key] || {
                label: 'Ort',
                newLabel: 'Neuer Ort',
                color: '#444444',
                emoji: '📍',
                display: 'pin',
                icon: 'pin',
            }
        );
    }

    function props(feature) {
        // Altbestand kann `"properties": []` enthalten (leeres PHP-Array).
        if (!feature.properties || Array.isArray(feature.properties) || typeof feature.properties !== 'object') {
            feature.properties = {};
        }
        return feature.properties;
    }

    function geometryKind(feature) {
        var type = feature && feature.geometry ? feature.geometry.type : '';
        if (type === 'Point') return 'point';
        if (type === 'Polygon' || type === 'MultiPolygon') return 'area';
        if (type === 'LineString' || type === 'MultiLineString') return 'route';
        return 'other';
    }

    function styleFor(feature) {
        var p = props(feature);
        var cat = categoryFor(p.category);

        return {
            categoryLabel: cat.label,
            color: p['marker-color'] || cat.color,
            emoji: p.emoji || cat.emoji,
            display: p.display === 'label' || p.display === 'pin' ? p.display : cat.display || 'pin',
            name: p.name || '',
        };
    }

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function uniqueId(base, skipIndex) {
        var candidate = base || 'element';
        var counter = 2;

        while (
            state.geo.features.some(function (feature, index) {
                return index !== skipIndex && props(feature).id === candidate;
            })
        ) {
            candidate = base + '-' + counter;
            counter += 1;
        }

        return candidate;
    }

    function metersToDegrees(meters, lat) {
        var dLat = meters / 111320;
        var dLng = meters / (111320 * Math.max(0.2, Math.cos((lat * Math.PI) / 180)));
        return { dLat: dLat, dLng: dLng };
    }

    function setStatus(text, isError) {
        if (!el.status) return;
        el.status.textContent = text || '';
        el.status.classList.toggle('kuh-map-editor__status--error', !!isError);
    }

    function markDirty() {
        state.dirty = true;
        root.classList.add('kuh-map-editor--dirty');
    }

    // ── Änderungen & Historie ────────────────────────────────────────────

    function pushHistory(beforeJson) {
        state.past.push(beforeJson);
        if (state.past.length > 60) {
            state.past.shift();
        }
        state.future.length = 0;
    }

    function afterChange(message) {
        markDirty();
        syncTextarea();
        render();
        if (message) {
            setStatus(message, false);
        }
    }

    /**
     * Führt eine Mutation aus und legt vorher einen Undo-Punkt an.
     * Gibt `fn` explizit `false` zurück, gilt die Änderung als abgebrochen.
     */
    function mutate(message, fn) {
        var before = JSON.stringify(state.geo);
        if (fn() === false) {
            return;
        }
        pushHistory(before);
        afterChange(message);
    }

    function undo() {
        if (!state.past.length) {
            setStatus('Nichts zum Rückgängigmachen.', false);
            return;
        }
        state.future.push(JSON.stringify(state.geo));
        state.geo = normalizeGeo(JSON.parse(state.past.pop()));
        clampSelection();
        afterChange('Rückgängig.');
    }

    function redo() {
        if (!state.future.length) {
            setStatus('Nichts zum Wiederherstellen.', false);
            return;
        }
        state.past.push(JSON.stringify(state.geo));
        state.geo = normalizeGeo(JSON.parse(state.future.pop()));
        clampSelection();
        afterChange('Wiederhergestellt.');
    }

    function clampSelection() {
        if (state.selected >= state.geo.features.length) {
            state.selected = -1;
        }
    }

    // ── GeoJSON lesen / schreiben ────────────────────────────────────────

    function normalizeGeo(input) {
        var geo = input && typeof input === 'object' ? input : {};

        geo.type = 'FeatureCollection';

        if (!geo.meta || typeof geo.meta !== 'object' || Array.isArray(geo.meta)) {
            geo.meta = {};
        }

        if (!Array.isArray(geo.meta.center) || geo.meta.center.length < 2) {
            geo.meta.center = DEFAULT_CENTER.slice();
        }

        if (typeof geo.meta.zoom !== 'number' || !isFinite(geo.meta.zoom)) {
            geo.meta.zoom = DEFAULT_ZOOM;
        }

        if (!Array.isArray(geo.features)) {
            geo.features = [];
        }

        geo.features.forEach(function (feature) {
            if (feature && typeof feature === 'object') {
                feature.type = 'Feature';
                props(feature);
            }
        });

        geo.features = geo.features.filter(function (feature) {
            return feature && feature.geometry && feature.geometry.type;
        });

        return geo;
    }

    function readTextarea() {
        var parsed = JSON.parse(el.json.value || '{}');

        if (!parsed || typeof parsed !== 'object') {
            throw new Error('Das JSON muss ein Objekt sein.');
        }

        return normalizeGeo(parsed);
    }

    function syncTextarea() {
        el.json.value = JSON.stringify(state.geo, null, 4);
    }

    // ── Karte aufbauen ───────────────────────────────────────────────────

    function baseTileUrls() {
        if (Array.isArray(TILES.baseTileUrls) && TILES.baseTileUrls.length) {
            return TILES.baseTileUrls;
        }
        return ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'];
    }

    function buildStyle() {
        var sources = {
            base: {
                type: 'raster',
                tiles: baseTileUrls(),
                tileSize: 256,
                attribution: TILES.tileAttribution || '',
                maxzoom: 19,
            },
        };

        var layers = [
            {
                id: 'kuh-background',
                type: 'background',
                paint: { 'background-color': CONFIG.mapBackgroundColor || '#f3efe6' },
            },
            { id: 'base-tiles', type: 'raster', source: 'base' },
        ];

        if (Array.isArray(TILES.labelTileUrls) && TILES.labelTileUrls.length) {
            sources.labels = {
                type: 'raster',
                tiles: TILES.labelTileUrls,
                tileSize: 256,
                maxzoom: 19,
            };
            layers.push({ id: 'street-labels', type: 'raster', source: 'labels' });
        }

        return { version: 8, name: 'KuH Editor', sources: sources, layers: layers };
    }

    function initMap() {
        if (typeof maplibregl === 'undefined') {
            setStatus('MapLibre konnte nicht geladen werden – die Karte bleibt leer. Bitte Netzwerkverbindung prüfen.', true);
            return;
        }

        if (TILES.minimalBaseTiles) {
            el.canvas.classList.add('kuh-map-canvas--minimal');
        }

        map = new maplibregl.Map({
            container: el.canvas,
            style: buildStyle(),
            center: state.geo.meta.center,
            zoom: state.geo.meta.zoom,
            attributionControl: false,
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.ScaleControl({ maxWidth: 120, unit: 'metric' }), 'bottom-left');
        map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

        map.on('load', function () {
            addEditLayers();
            renderImageLayer();
            render();
            updateViewInfo();
        });

        map.on('click', onMapClick);
        map.on('dblclick', onMapDblClick);
        map.on('moveend', updateViewInfo);
        map.on('mousemove', onMapMouseMove);

        // Ein einziger Handler statt einer Registrierung pro Layer: sonst feuert
        // ein Mousedown über mehreren Layern mehrfach und Auswahl und Verschieben
        // kommen sich in die Quere.
        map.on('mousedown', onShapeMouseDown);

        SHAPE_LAYERS.forEach(function (layerId) {
            map.on('mouseenter', layerId, function () {
                if (state.tool === 'select') {
                    map.getCanvas().style.cursor = 'pointer';
                }
            });
            map.on('mouseleave', layerId, function () {
                map.getCanvas().style.cursor = '';
            });
        });
    }

    function addEditLayers() {
        map.addSource(SOURCE_SHAPES, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
        map.addSource(SOURCE_DRAFT, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });

        map.addLayer({
            id: LAYER_AREA_FILL,
            type: 'fill',
            source: SOURCE_SHAPES,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'fill-color': ['coalesce', ['get', 'fill'], AREA.fillColor],
                'fill-opacity': ['coalesce', ['get', 'fill-opacity'], AREA.fillOpacity / 100],
            },
        });

        map.addLayer({
            id: LAYER_AREA_LINE,
            type: 'line',
            source: SOURCE_SHAPES,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'line-color': ['coalesce', ['get', 'stroke'], AREA.lineColor],
                'line-width': ['coalesce', ['get', 'stroke-width'], 2],
                'line-opacity': 0.75,
            },
        });

        map.addLayer({
            id: LAYER_ROUTE_CASING,
            type: 'line',
            source: SOURCE_SHAPES,
            filter: ['==', ['geometry-type'], 'LineString'],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#ffffff',
                'line-width': ['+', ['coalesce', ['get', 'stroke-width'], ROUTE.width], 3],
                'line-opacity': 0.55,
            },
        });

        map.addLayer({
            id: LAYER_ROUTE_LINE,
            type: 'line',
            source: SOURCE_SHAPES,
            filter: ['all', ['==', ['geometry-type'], 'LineString'], ['!=', ['get', 'dashed'], true]],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': ['coalesce', ['get', 'stroke'], ROUTE.color],
                'line-width': ['coalesce', ['get', 'stroke-width'], ROUTE.width],
            },
        });

        map.addLayer({
            id: LAYER_ROUTE_DASH,
            type: 'line',
            source: SOURCE_SHAPES,
            filter: ['all', ['==', ['geometry-type'], 'LineString'], ['==', ['get', 'dashed'], true]],
            layout: { 'line-cap': 'butt', 'line-join': 'round' },
            paint: {
                'line-color': ['coalesce', ['get', 'stroke'], ROUTE.color],
                'line-width': ['coalesce', ['get', 'stroke-width'], ROUTE.width],
                'line-dasharray': [2, 1.6],
            },
        });

        map.addLayer({
            id: LAYER_SELECTED,
            type: 'line',
            source: SOURCE_SHAPES,
            filter: ['==', ['get', '_sel'], true],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#2271b1',
                'line-width': 3,
                'line-dasharray': [2, 1.5],
            },
        });

        map.addLayer({
            id: 'kuh-draft-fill',
            type: 'fill',
            source: SOURCE_DRAFT,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: { 'fill-color': '#2271b1', 'fill-opacity': 0.15 },
        });

        map.addLayer({
            id: 'kuh-draft-line',
            type: 'line',
            source: SOURCE_DRAFT,
            filter: ['!=', ['geometry-type'], 'Point'],
            paint: { 'line-color': '#2271b1', 'line-width': 2, 'line-dasharray': [2, 1.5] },
        });

        map.addLayer({
            id: 'kuh-draft-points',
            type: 'circle',
            source: SOURCE_DRAFT,
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 5,
                'circle-color': '#ffffff',
                'circle-stroke-color': '#2271b1',
                'circle-stroke-width': 2,
            },
        });
    }

    // ── Hintergrundbild ──────────────────────────────────────────────────

    function imageBoundsArray() {
        var bounds = state.geo.meta.imageBounds;

        if (bounds && bounds.topLeft && bounds.topRight && bounds.bottomRight && bounds.bottomLeft) {
            return [
                [Number(bounds.topLeft[0]), Number(bounds.topLeft[1])],
                [Number(bounds.topRight[0]), Number(bounds.topRight[1])],
                [Number(bounds.bottomRight[0]), Number(bounds.bottomRight[1])],
                [Number(bounds.bottomLeft[0]), Number(bounds.bottomLeft[1])],
            ];
        }

        return null;
    }

    function setImageBoundsFromArray(corners) {
        state.geo.meta.imageBounds = {
            topLeft: [corners[0][0], corners[0][1]],
            topRight: [corners[1][0], corners[1][1]],
            bottomRight: [corners[2][0], corners[2][1]],
            bottomLeft: [corners[3][0], corners[3][1]],
        };
    }

    function areaBoundingBox() {
        var minLng = Infinity;
        var maxLng = -Infinity;
        var minLat = Infinity;
        var maxLat = -Infinity;
        var found = false;

        state.geo.features.forEach(function (feature) {
            if (geometryKind(feature) !== 'area') return;

            eachCoordinate(feature.geometry.coordinates, function (lng, lat) {
                found = true;
                minLng = Math.min(minLng, lng);
                maxLng = Math.max(maxLng, lng);
                minLat = Math.min(minLat, lat);
                maxLat = Math.max(maxLat, lat);
            });
        });

        if (!found) return null;

        var lngPad = Math.max((maxLng - minLng) * 0.02, 0.0002);
        var latPad = Math.max((maxLat - minLat) * 0.02, 0.0002);

        return [
            [minLng - lngPad, maxLat + latPad],
            [maxLng + lngPad, maxLat + latPad],
            [maxLng + lngPad, minLat - latPad],
            [minLng - lngPad, minLat - latPad],
        ];
    }

    function eachCoordinate(input, callback) {
        if (!Array.isArray(input) || !input.length) return;

        if (typeof input[0] === 'number' && typeof input[1] === 'number') {
            callback(input[0], input[1]);
            return;
        }

        input.forEach(function (child) {
            eachCoordinate(child, callback);
        });
    }

    function viewBoundsArray() {
        if (!map) return null;
        var bounds = map.getBounds();

        return [
            [bounds.getWest(), bounds.getNorth()],
            [bounds.getEast(), bounds.getNorth()],
            [bounds.getEast(), bounds.getSouth()],
            [bounds.getWest(), bounds.getSouth()],
        ];
    }

    /**
     * Exportierte SVGs haben oft nur eine viewBox. MapLibre braucht für
     * Image-Sources verlässliche Pixelmaße – deshalb ergänzen wir sie.
     */
    function prepareImageUrl(url) {
        if (!/\.svg(?:$|[?#])/i.test(url)) {
            return Promise.resolve(url);
        }

        return fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.ok ? response.text() : null;
            })
            .then(function (svgText) {
                if (!svgText) return url;

                var doc = new DOMParser().parseFromString(svgText, 'image/svg+xml');
                var svg = doc.documentElement;

                if (!svg || svg.tagName.toLowerCase() !== 'svg') {
                    return url;
                }

                if (!svg.getAttribute('width') || !svg.getAttribute('height')) {
                    var viewBox = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
                    var width = isFinite(viewBox[2]) && viewBox[2] > 0 ? viewBox[2] : 2000;
                    var height = isFinite(viewBox[3]) && viewBox[3] > 0 ? viewBox[3] : 2000;
                    svg.setAttribute('width', String(width));
                    svg.setAttribute('height', String(height));
                }

                var blob = new Blob([new XMLSerializer().serializeToString(svg)], {
                    type: 'image/svg+xml;charset=utf-8',
                });

                if (imageObjectUrl) {
                    URL.revokeObjectURL(imageObjectUrl);
                }
                imageObjectUrl = URL.createObjectURL(blob);

                return imageObjectUrl;
            })
            .catch(function () {
                return url;
            });
    }

    function opacityValue() {
        var value = Number(state.geo.meta.customMapImageOpacity);
        return isFinite(value) ? Math.min(100, Math.max(0, value)) : 30;
    }

    function renderImageLayer() {
        if (!map || !map.getSource(SOURCE_SHAPES)) return;

        var url = String(state.geo.meta.customMapImageUrl || '').trim();

        if (!url) {
            removeImageLayer();
            return;
        }

        if (!imageBoundsArray()) {
            var fallback = areaBoundingBox() || viewBoundsArray();
            if (fallback) {
                setImageBoundsFromArray(fallback);
                syncTextarea();
            }
        }

        var corners = imageBoundsArray();
        if (!corners) return;

        if (imageLayerUrl === url && map.getSource(SOURCE_IMAGE)) {
            map.getSource(SOURCE_IMAGE).setCoordinates(corners);
            map.setPaintProperty(LAYER_IMAGE, 'raster-opacity', opacityValue() / 100);
            return;
        }

        prepareImageUrl(url).then(function (prepared) {
            if (!map) return;

            removeImageLayer();

            try {
                map.addSource(SOURCE_IMAGE, { type: 'image', url: prepared, coordinates: imageBoundsArray() });
                map.addLayer(
                    {
                        id: LAYER_IMAGE,
                        type: 'raster',
                        source: SOURCE_IMAGE,
                        paint: { 'raster-opacity': opacityValue() / 100 },
                    },
                    map.getLayer(LAYER_AREA_FILL) ? LAYER_AREA_FILL : undefined
                );
                imageLayerUrl = url;
            } catch (error) {
                setStatus('Hintergrundbild konnte nicht geladen werden: ' + error.message, true);
            }
        });
    }

    function removeImageLayer() {
        if (!map) return;
        if (map.getLayer(LAYER_IMAGE)) map.removeLayer(LAYER_IMAGE);
        if (map.getSource(SOURCE_IMAGE)) map.removeSource(SOURCE_IMAGE);
        imageLayerUrl = null;
    }

    // ── Rendering ────────────────────────────────────────────────────────

    function isVisible(feature) {
        var kind = geometryKind(feature);

        if (kind === 'area') return state.visible.area !== false;
        if (kind === 'route') return state.visible.route !== false;
        if (kind === 'point') {
            var category = props(feature).category;
            return state.visible[category] !== false;
        }

        return true;
    }

    function render() {
        renderShapeSource();
        renderDraftSource();
        renderMarkers();
        renderHandles();
        renderList();
        renderProps();
        renderLegend();
        renderToolbar();
    }

    function renderShapeSource() {
        if (!map || !map.getSource(SOURCE_SHAPES)) return;

        var features = [];

        state.geo.features.forEach(function (feature, index) {
            var kind = geometryKind(feature);
            if (kind !== 'area' && kind !== 'route') return;
            if (!isVisible(feature)) return;

            var extra = Object.assign({}, props(feature), { _idx: index, _sel: index === state.selected });
            features.push({ type: 'Feature', properties: extra, geometry: feature.geometry });
        });

        map.getSource(SOURCE_SHAPES).setData({ type: 'FeatureCollection', features: features });
    }

    function renderDraftSource() {
        if (!map || !map.getSource(SOURCE_DRAFT)) return;

        var features = [];

        if (state.draft && state.draft.coords.length) {
            var coords = state.draft.coords;

            coords.forEach(function (coord) {
                features.push({ type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: coord } });
            });

            if (coords.length >= 2) {
                if (state.draft.type === 'area') {
                    features.push({
                        type: 'Feature',
                        properties: {},
                        geometry: { type: 'Polygon', coordinates: [coords.concat([coords[0]])] },
                    });
                }

                features.push({
                    type: 'Feature',
                    properties: {},
                    geometry: { type: 'LineString', coordinates: coords },
                });
            }
        }

        map.getSource(SOURCE_DRAFT).setData({ type: 'FeatureCollection', features: features });
    }

    function clearMarkers() {
        markers.forEach(function (marker) {
            marker.remove();
        });
        markers = [];
    }

    function markerElement(feature, index) {
        var style = styleFor(feature);
        var selected = index === state.selected;

        if (style.display === 'label') {
            var label = document.createElement('div');
            label.className = 'kuh-map-label' + (selected ? ' kuh-map-label--selected' : '');
            label.style.color = style.color;
            label.textContent = style.name || style.categoryLabel;
            return label;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'kuh-map-marker' + (selected ? ' kuh-map-marker--selected' : '');

        var circle = document.createElement('div');
        circle.className = 'kuh-map-marker__circle';
        circle.style.background = style.color;
        circle.textContent = style.emoji;

        var pointer = document.createElement('div');
        pointer.className = 'kuh-map-marker__pointer';
        pointer.style.background = style.color;

        wrapper.appendChild(circle);
        wrapper.appendChild(pointer);

        return wrapper;
    }

    function renderMarkers() {
        if (!map) return;

        clearMarkers();

        state.geo.features.forEach(function (feature, index) {
            if (geometryKind(feature) !== 'point') return;
            if (!isVisible(feature)) return;

            var coords = feature.geometry.coordinates;
            if (!Array.isArray(coords) || !isFinite(coords[0]) || !isFinite(coords[1])) return;

            var style = styleFor(feature);
            var element = markerElement(feature, index);
            var draggable = state.tool === 'select';

            var marker = new maplibregl.Marker({
                element: element,
                anchor: style.display === 'label' ? 'center' : 'bottom',
                draggable: draggable,
            })
                .setLngLat([Number(coords[0]), Number(coords[1])])
                .addTo(map);

            // Auswahl erst beim Klick (mouseup), nicht bei mousedown:
            // sonst würde das Neu-Rendern den beginnenden Drag abbrechen.
            element.addEventListener('click', function (event) {
                if (state.tool !== 'select') {
                    return;
                }
                event.stopPropagation();
                if (state.selected !== index) {
                    selectFeature(index);
                }
            });

            if (draggable) {
                var before = null;

                marker.on('dragstart', function () {
                    before = JSON.stringify(state.geo);
                });

                marker.on('drag', function () {
                    var lngLat = marker.getLngLat();
                    feature.geometry.coordinates = [lngLat.lng, lngLat.lat];
                });

                marker.on('dragend', function () {
                    var lngLat = marker.getLngLat();
                    feature.geometry.coordinates = [lngLat.lng, lngLat.lat];
                    if (before) {
                        pushHistory(before);
                        before = null;
                    }
                    // Nach dem Ziehen ist das Element ausgewählt: der Klick nach
                    // dem Drag landet auf dem bereits ersetzten DOM-Knoten.
                    state.selected = index;
                    afterChange('Position von „' + (style.name || style.categoryLabel) + '" aktualisiert.');
                });
            }

            markers.push(marker);
        });
    }

    function clearHandles() {
        handles.forEach(function (handle) {
            handle.remove();
        });
        handles = [];
    }

    function createHandle(lngLat, className) {
        var element = document.createElement('div');
        element.className = 'kuh-map-handle' + (className ? ' ' + className : '');

        var handle = new maplibregl.Marker({ element: element, anchor: 'center', draggable: true })
            .setLngLat(lngLat)
            .addTo(map);

        handles.push(handle);

        return { marker: handle, element: element };
    }

    /**
     * Liefert den bearbeitbaren Punktring der Auswahl.
     * Polygone: äußerer Ring (letzter Punkt = erster Punkt).
     */
    function selectedRing() {
        var feature = state.geo.features[state.selected];
        if (!feature) return null;

        var kind = geometryKind(feature);

        if (kind === 'area' && feature.geometry.type === 'Polygon') {
            return { feature: feature, coords: feature.geometry.coordinates[0], closed: true };
        }

        if (kind === 'route' && feature.geometry.type === 'LineString') {
            return { feature: feature, coords: feature.geometry.coordinates, closed: false };
        }

        return null;
    }

    function renderHandles() {
        if (!map) return;

        clearHandles();

        if (state.alignImage && state.geo.meta.customMapImageUrl) {
            renderImageHandles();
        }

        if (state.tool !== 'select' || state.draft) return;

        var ring = selectedRing();
        if (!ring) return;

        var coords = ring.coords;
        var unique = ring.closed ? coords.length - 1 : coords.length;

        for (var i = 0; i < unique; i += 1) {
            renderVertexHandle(ring, i, unique);
        }

        for (var j = 0; j < unique - (ring.closed ? 0 : 1); j += 1) {
            renderMidHandle(ring, j, unique);
        }
    }

    function renderVertexHandle(ring, index, unique) {
        var coords = ring.coords;
        var handle = createHandle(coords[index], null);
        var before = null;
        var dragged = false;

        handle.marker.on('dragstart', function () {
            before = JSON.stringify(state.geo);
            dragged = true;
        });

        handle.marker.on('drag', function () {
            var lngLat = handle.marker.getLngLat();
            coords[index] = [lngLat.lng, lngLat.lat];

            // Geschlossene Ringe: Anfangs- und Endpunkt zusammenhalten.
            if (ring.closed && index === 0) {
                coords[coords.length - 1] = [lngLat.lng, lngLat.lat];
            }

            renderShapeSource();
        });

        handle.marker.on('dragend', function () {
            if (before) {
                pushHistory(before);
                before = null;
            }
            afterChange('Punkt verschoben.');
        });

        function removeVertex(event) {
            event.preventDefault();
            event.stopPropagation();

            var minimum = ring.closed ? 3 : 2;
            if (unique <= minimum) {
                setStatus(
                    ring.closed
                        ? 'Eine Fläche braucht mindestens 3 Punkte.'
                        : 'Eine Strecke braucht mindestens 2 Punkte.',
                    true
                );
                return;
            }

            mutate('Punkt entfernt.', function () {
                coords.splice(index, 1);
                if (ring.closed) {
                    // Ring wieder schließen (Index 0 kann entfernt worden sein).
                    coords[coords.length - 1] = [coords[0][0], coords[0][1]];
                }
            });
        }

        handle.element.addEventListener('contextmenu', removeVertex);
        handle.element.addEventListener('click', function (event) {
            event.stopPropagation();
            if (dragged) {
                dragged = false;
                return;
            }
            if (event.altKey) {
                removeVertex(event);
            }
        });
        handle.element.title = 'Ziehen zum Verschieben · Rechtsklick oder Alt+Klick entfernt den Punkt';
    }

    function renderMidHandle(ring, index, unique) {
        var coords = ring.coords;
        var next = (index + 1) % unique;
        var midpoint = [
            (coords[index][0] + coords[next][0]) / 2,
            (coords[index][1] + coords[next][1]) / 2,
        ];

        var handle = createHandle(midpoint, 'kuh-map-handle--mid');
        handle.element.title = 'Klicken oder ziehen fügt hier einen Punkt ein';
        var dragged = false;

        function insertVertex(position) {
            mutate('Punkt eingefügt.', function () {
                coords.splice(index + 1, 0, position);
            });
        }

        handle.marker.on('dragstart', function () {
            dragged = true;
        });

        handle.marker.on('dragend', function () {
            var lngLat = handle.marker.getLngLat();
            insertVertex([lngLat.lng, lngLat.lat]);
        });

        // Der Klick feuert auch nach einem Drag – dann hat dragend schon eingefügt.
        handle.element.addEventListener('click', function (event) {
            event.stopPropagation();
            if (dragged) {
                dragged = false;
                return;
            }
            insertVertex(midpoint);
        });
    }

    function renderImageHandles() {
        var corners = imageBoundsArray();
        if (!corners) return;

        corners.forEach(function (corner, index) {
            var handle = createHandle(corner, 'kuh-map-handle--image');
            handle.element.title = 'Ecke des Hintergrundbildes verschieben';
            var before = null;

            handle.marker.on('dragstart', function () {
                before = JSON.stringify(state.geo);
            });

            handle.marker.on('drag', function () {
                var lngLat = handle.marker.getLngLat();
                var updated = imageBoundsArray();
                updated[index] = [lngLat.lng, lngLat.lat];
                setImageBoundsFromArray(updated);

                if (map.getSource(SOURCE_IMAGE)) {
                    map.getSource(SOURCE_IMAGE).setCoordinates(updated);
                }
            });

            handle.marker.on('dragend', function () {
                if (before) {
                    pushHistory(before);
                    before = null;
                }
                afterChange('Bildausrichtung angepasst.');
            });
        });
    }

    function renderList() {
        if (!el.list) return;

        el.list.innerHTML = '';

        state.geo.features.forEach(function (feature, index) {
            var kind = geometryKind(feature);
            var style = styleFor(feature);
            var p = props(feature);

            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'kuh-map-list__item';
            item.setAttribute('aria-current', index === state.selected ? 'true' : 'false');

            var dot = document.createElement('span');
            dot.className = 'kuh-map-list__dot';

            var label = document.createElement('span');
            label.className = 'kuh-map-list__label';

            var type = document.createElement('span');
            type.className = 'kuh-map-list__type';

            if (kind === 'point') {
                dot.style.background = style.display === 'label' ? 'transparent' : style.color;
                dot.textContent = style.display === 'label' ? 'T' : style.emoji;
                if (style.display === 'label') {
                    dot.style.color = style.color;
                    dot.style.fontWeight = '700';
                }
                label.textContent = p.name || '(ohne Namen)';
                type.textContent = style.display === 'label' ? 'Text' : style.categoryLabel;
            } else if (kind === 'area') {
                dot.style.background = p.fill || AREA.fillColor;
                dot.textContent = '▧';
                label.textContent = p.name || 'Fläche';
                type.textContent = 'Fläche';
            } else if (kind === 'route') {
                dot.style.background = p.stroke || ROUTE.color;
                dot.textContent = '╱';
                label.textContent = p.name || 'Strecke';
                type.textContent = 'Strecke';
            } else {
                dot.textContent = '?';
                label.textContent = p.name || feature.geometry.type;
                type.textContent = feature.geometry.type;
            }

            item.appendChild(dot);
            item.appendChild(label);
            item.appendChild(type);

            item.addEventListener('click', function () {
                selectFeature(index);
                zoomToFeature(index);
            });

            el.list.appendChild(item);
        });
    }

    function renderLegend() {
        if (!el.legend) return;

        var counts = { area: 0, route: 0 };
        Object.keys(CATEGORIES).forEach(function (key) {
            counts[key] = 0;
        });

        state.geo.features.forEach(function (feature) {
            var kind = geometryKind(feature);
            if (kind === 'area') counts.area += 1;
            else if (kind === 'route') counts.route += 1;
            else if (kind === 'point') {
                var category = props(feature).category;
                if (typeof counts[category] === 'number') counts[category] += 1;
            }
        });

        var items = [{ key: 'area', label: 'Marktfläche', emoji: '🌿', color: AREA.fillColor }];

        if (counts.route > 0) {
            items.push({ key: 'route', label: 'Wege & Strecken', emoji: '🚶', color: ROUTE.color });
        }

        Object.keys(CATEGORIES).forEach(function (key) {
            var cat = CATEGORIES[key];
            items.push({ key: key, label: cat.label, emoji: cat.emoji, color: cat.color });
        });

        el.legend.innerHTML = '';

        var title = document.createElement('span');
        title.className = 'kuh-map-legend__title';
        title.textContent = 'Legende:';
        el.legend.appendChild(title);

        items.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'kuh-map-legend__item';
            button.setAttribute('aria-pressed', state.visible[item.key] !== false ? 'true' : 'false');
            button.title = 'Im Editor ein-/ausblenden (keine Auswirkung auf das Frontend)';

            var dot = document.createElement('span');
            dot.className = 'kuh-map-legend__dot';
            dot.style.background = item.color;
            dot.textContent = item.emoji;

            button.appendChild(dot);
            button.appendChild(document.createTextNode(item.label + ' (' + (counts[item.key] || 0) + ')'));

            button.addEventListener('click', function () {
                state.visible[item.key] = state.visible[item.key] === false;
                render();
            });

            el.legend.appendChild(button);
        });
    }

    function renderToolbar() {
        if (el.tools) {
            Array.prototype.forEach.call(el.tools.querySelectorAll('[data-kuh-tool]'), function (button) {
                button.setAttribute('aria-pressed', button.dataset.kuhTool === state.tool ? 'true' : 'false');
            });
        }

        if (el.undo) el.undo.disabled = !state.past.length;
        if (el.redo) el.redo.disabled = !state.future.length;
        if (el.finish) el.finish.hidden = !state.draft;
        if (el.cancel) el.cancel.hidden = !state.draft;

        el.canvas.classList.toggle('kuh-map-canvas--drawing', !!state.draft);
        el.canvas.classList.toggle(
            'kuh-map-canvas--placing',
            !state.draft && (state.tool === 'poi' || state.tool === 'text')
        );

        if (el.hint) {
            if (state.draft) {
                el.hint.textContent =
                    HINTS[state.draft.type] + ' · ' + state.draft.coords.length + ' Punkt(e) gesetzt';
            } else {
                el.hint.textContent = HINTS[state.tool] || '';
            }
        }

        if (el.imageAlign) {
            el.imageAlign.setAttribute('aria-pressed', state.alignImage ? 'true' : 'false');
            el.imageAlign.textContent = state.alignImage ? 'Ausrichten beenden' : 'Bild ausrichten';
        }
    }

    function updateViewInfo() {
        if (!el.viewInfo || !map) return;

        var center = map.getCenter();
        el.viewInfo.textContent =
            'Gespeichert: ' +
            Number(state.geo.meta.center[0]).toFixed(5) +
            ', ' +
            Number(state.geo.meta.center[1]).toFixed(5) +
            ' · Zoom ' +
            Number(state.geo.meta.zoom).toFixed(1) +
            ' — aktuell: ' +
            center.lng.toFixed(5) +
            ', ' +
            center.lat.toFixed(5) +
            ' · Zoom ' +
            map.getZoom().toFixed(1);
    }

    // ── Eigenschaften-Panel ──────────────────────────────────────────────

    var fieldCounter = 0;

    function field(labelText, control, help) {
        var wrapper = document.createElement('div');
        wrapper.className = 'kuh-map-field';

        var label = document.createElement('label');
        label.textContent = labelText;

        // Wrapper (z. B. Farbfelder) haben selbst kein Formularelement.
        var target = control.matches('input, select, textarea')
            ? control
            : control.querySelector('input, select, textarea');

        if (target) {
            fieldCounter += 1;
            target.id = target.id || 'kuh-map-field-' + fieldCounter;
            label.setAttribute('for', target.id);
        }

        wrapper.appendChild(label);
        wrapper.appendChild(control);

        if (help) {
            var hint = document.createElement('span');
            hint.className = 'kuh-map-field__help';
            hint.textContent = help;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function textInput(value, onChange, placeholder) {
        var input = document.createElement('input');
        input.type = 'text';
        input.value = value || '';
        if (placeholder) input.placeholder = placeholder;
        input.addEventListener('change', function () {
            onChange(input.value);
        });
        return input;
    }

    function numberInput(value, onChange, options) {
        var settings = options || {};
        var input = document.createElement('input');
        input.type = 'number';
        input.value = value;
        if (settings.step) input.step = settings.step;
        if (typeof settings.min === 'number') input.min = settings.min;
        if (typeof settings.max === 'number') input.max = settings.max;
        input.addEventListener('change', function () {
            var parsed = parseFloat(input.value);
            if (isFinite(parsed)) {
                onChange(parsed);
            }
        });
        return input;
    }

    function colorInput(value, fallback, onChange) {
        var wrapper = document.createElement('div');
        wrapper.className = 'kuh-map-color';

        var picker = document.createElement('input');
        picker.type = 'color';
        picker.value = /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback;

        var text = document.createElement('input');
        text.type = 'text';
        text.value = value || '';
        text.placeholder = fallback + ' (Standard)';

        picker.addEventListener('change', function () {
            text.value = picker.value;
            onChange(picker.value);
        });

        text.addEventListener('change', function () {
            onChange(text.value.trim());
        });

        wrapper.appendChild(picker);
        wrapper.appendChild(text);

        return wrapper;
    }

    function checkboxField(labelText, checked, onChange) {
        var wrapper = document.createElement('div');
        wrapper.className = 'kuh-map-field kuh-map-field--inline';

        var input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = !!checked;
        input.addEventListener('change', function () {
            onChange(input.checked);
        });

        var label = document.createElement('label');
        label.textContent = labelText;
        label.addEventListener('click', function () {
            input.click();
        });

        wrapper.appendChild(input);
        wrapper.appendChild(label);

        return wrapper;
    }

    function selectInput(options, value, onChange) {
        var select = document.createElement('select');

        options.forEach(function (option) {
            var node = document.createElement('option');
            node.value = option.value;
            node.textContent = option.label;
            select.appendChild(node);
        });

        select.value = value;
        select.addEventListener('change', function () {
            onChange(select.value);
        });

        return select;
    }

    function setProp(key, value, label) {
        var feature = state.geo.features[state.selected];
        if (!feature) return;

        mutate((label || 'Eigenschaft') + ' gespeichert.', function () {
            var p = props(feature);

            if (value === '' || value === null || typeof value === 'undefined') {
                delete p[key];
            } else {
                p[key] = value;
            }
        });
    }

    function renderProps() {
        if (!el.props) return;

        el.props.innerHTML = '';

        var feature = state.geo.features[state.selected];

        if (!feature) {
            var empty = document.createElement('p');
            empty.className = 'kuh-map-props__empty';
            empty.textContent = 'Kein Element ausgewählt. Klicke ein Element in der Karte oder in der Liste an.';
            el.props.appendChild(empty);
            return;
        }

        var kind = geometryKind(feature);
        var p = props(feature);

        el.props.appendChild(
            field(
                'Name',
                textInput(p.name, function (value) {
                    mutate('Name gespeichert.', function () {
                        var target = props(feature);
                        target.name = value;

                        if (!target.id && value) {
                            target.id = uniqueId(slugify(value), state.selected);
                        }
                    });
                }),
                kind === 'point' ? 'Erscheint als Titel im Popup bzw. als Text auf der Karte.' : ''
            )
        );

        el.props.appendChild(
            field(
                'ID (für Deep-Links wie /karte#buehne-rosche)',
                textInput(p.id, function (value) {
                    setProp('id', value ? uniqueId(slugify(value), state.selected) : '', 'ID');
                }),
                'Wird für Verlinkungen und die Bühnen-Zuordnung genutzt.'
            )
        );

        var description = document.createElement('textarea');
        description.rows = 3;
        description.value = p.description || '';
        description.addEventListener('change', function () {
            setProp('description', description.value, 'Beschreibung');
        });
        el.props.appendChild(field('Beschreibung', description));

        if (kind === 'point') {
            renderPointProps(feature, p);
        } else if (kind === 'area') {
            renderAreaProps(p);
        } else if (kind === 'route') {
            renderRouteProps(p);
        }

        renderPropActions(kind);
    }

    function renderPointProps(feature, p) {
        var categoryOptions = Object.keys(CATEGORIES).map(function (key) {
            return { value: key, label: CATEGORIES[key].label };
        });

        el.props.appendChild(
            field(
                'Kategorie',
                selectInput(categoryOptions, p.category || 'location', function (value) {
                    mutate('Kategorie gespeichert.', function () {
                        var target = props(feature);
                        target.category = value;
                        target.icon = categoryFor(value).icon;
                    });
                }),
                'Bestimmt Legende, Standardfarbe und Standard-Icon.'
            )
        );

        el.props.appendChild(
            field(
                'Darstellung',
                selectInput(
                    [
                        { value: 'pin', label: 'Marker mit Icon' },
                        { value: 'label', label: 'Nur Text' },
                    ],
                    styleFor(feature).display,
                    function (value) {
                        setProp('display', value, 'Darstellung');
                    }
                )
            )
        );

        var emojiInput = textInput(p.emoji, function (value) {
            setProp('emoji', value, 'Icon');
        }, categoryFor(p.category).emoji + ' (Standard)');

        el.props.appendChild(field('Icon / Emoji', emojiInput, 'Leer lassen für das Standard-Icon der Kategorie.'));

        if (EMOJI_PRESETS.length) {
            var presets = document.createElement('div');
            presets.className = 'kuh-map-emoji-presets';

            EMOJI_PRESETS.forEach(function (emoji) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = emoji;
                button.title = 'Icon ' + emoji + ' verwenden';
                button.addEventListener('click', function () {
                    setProp('emoji', emoji, 'Icon');
                });
                presets.appendChild(button);
            });

            el.props.appendChild(presets);
        }

        el.props.appendChild(
            field(
                'Farbe',
                colorInput(p['marker-color'], categoryFor(p.category).color, function (value) {
                    setProp('marker-color', value, 'Farbe');
                }),
                'Leer lassen für die Kategoriefarbe aus den Block-Einstellungen.'
            )
        );

        var coords = feature.geometry.coordinates;
        var position = document.createElement('div');
        position.className = 'kuh-map-row';

        position.appendChild(
            field(
                'Breite (Lat)',
                numberInput(Number(coords[1]).toFixed(6), function (value) {
                    mutate('Position gespeichert.', function () {
                        feature.geometry.coordinates = [Number(coords[0]), value];
                    });
                }, { step: '0.000001' })
            )
        );

        position.appendChild(
            field(
                'Länge (Lng)',
                numberInput(Number(coords[0]).toFixed(6), function (value) {
                    mutate('Position gespeichert.', function () {
                        feature.geometry.coordinates = [value, Number(coords[1])];
                    });
                }, { step: '0.000001' })
            )
        );

        el.props.appendChild(position);
    }

    function renderAreaProps(p) {
        el.props.appendChild(
            field(
                'Füllfarbe',
                colorInput(p.fill, AREA.fillColor, function (value) {
                    setProp('fill', value, 'Füllfarbe');
                }),
                'Leer lassen für die Farbe aus den Block-Einstellungen.'
            )
        );

        el.props.appendChild(
            field(
                'Deckkraft (%)',
                numberInput(
                    typeof p['fill-opacity'] === 'number' ? Math.round(p['fill-opacity'] * 100) : AREA.fillOpacity,
                    function (value) {
                        setProp('fill-opacity', Math.min(100, Math.max(0, value)) / 100, 'Deckkraft');
                    },
                    { min: 0, max: 100, step: '1' }
                )
            )
        );

        el.props.appendChild(
            field(
                'Linienfarbe',
                colorInput(p.stroke, AREA.lineColor, function (value) {
                    setProp('stroke', value, 'Linienfarbe');
                })
            )
        );
    }

    function renderRouteProps(p) {
        el.props.appendChild(
            field(
                'Linienfarbe',
                colorInput(p.stroke, ROUTE.color, function (value) {
                    setProp('stroke', value, 'Linienfarbe');
                })
            )
        );

        el.props.appendChild(
            field(
                'Linienbreite (px)',
                numberInput(typeof p['stroke-width'] === 'number' ? p['stroke-width'] : ROUTE.width, function (value) {
                    setProp('stroke-width', Math.min(20, Math.max(1, value)), 'Linienbreite');
                }, { min: 1, max: 20, step: '0.5' })
            )
        );

        el.props.appendChild(
            checkboxField('Gestrichelt darstellen', p.dashed === true, function (checked) {
                setProp('dashed', checked ? true : '', 'Linienstil');
            })
        );
    }

    function renderPropActions(kind) {
        var actions = document.createElement('div');
        actions.className = 'kuh-map-props__actions';

        var zoom = document.createElement('button');
        zoom.type = 'button';
        zoom.className = 'button';
        zoom.textContent = 'Anspringen';
        zoom.addEventListener('click', function () {
            zoomToFeature(state.selected);
        });
        actions.appendChild(zoom);

        if (kind === 'point') {
            var duplicate = document.createElement('button');
            duplicate.type = 'button';
            duplicate.className = 'button';
            duplicate.textContent = 'Duplizieren';
            duplicate.addEventListener('click', duplicateSelected);
            actions.appendChild(duplicate);
        }

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button button-link-delete';
        remove.textContent = 'Löschen';
        remove.addEventListener('click', deleteSelected);
        actions.appendChild(remove);

        el.props.appendChild(actions);
    }

    // ── Auswahl & Bearbeitung ────────────────────────────────────────────

    function selectFeature(index) {
        state.selected = index;
        render();
    }

    function zoomToFeature(index) {
        var feature = state.geo.features[index];
        if (!feature || !map) return;

        var kind = geometryKind(feature);

        if (kind === 'point') {
            map.easeTo({
                center: [Number(feature.geometry.coordinates[0]), Number(feature.geometry.coordinates[1])],
                zoom: Math.max(map.getZoom(), 17),
                duration: 500,
            });
            return;
        }

        var minLng = Infinity;
        var maxLng = -Infinity;
        var minLat = Infinity;
        var maxLat = -Infinity;

        eachCoordinate(feature.geometry.coordinates, function (lng, lat) {
            minLng = Math.min(minLng, lng);
            maxLng = Math.max(maxLng, lng);
            minLat = Math.min(minLat, lat);
            maxLat = Math.max(maxLat, lat);
        });

        if (!isFinite(minLng)) return;

        map.fitBounds(
            [
                [minLng, minLat],
                [maxLng, maxLat],
            ],
            { padding: 60, duration: 500, maxZoom: 18 }
        );
    }

    function deleteSelected() {
        var feature = state.geo.features[state.selected];
        if (!feature) return;

        var label = props(feature).name || geometryKind(feature);

        if (!window.confirm('„' + label + '" wirklich entfernen?')) {
            return;
        }

        var index = state.selected;

        mutate('„' + label + '" entfernt.', function () {
            state.geo.features.splice(index, 1);
            state.selected = -1;
        });
    }

    function duplicateSelected() {
        var feature = state.geo.features[state.selected];
        if (!feature || geometryKind(feature) !== 'point') return;

        mutate('Element dupliziert.', function () {
            var copy = clone(feature);
            var p = props(copy);
            var offset = metersToDegrees(25, Number(feature.geometry.coordinates[1]));

            copy.geometry.coordinates = [
                Number(feature.geometry.coordinates[0]) + offset.dLng,
                Number(feature.geometry.coordinates[1]) - offset.dLat,
            ];

            p.name = (p.name || 'Element') + ' (Kopie)';
            p.id = uniqueId(slugify(p.name), -1);

            state.geo.features.push(copy);
            state.selected = state.geo.features.length - 1;
        });
    }

    // ── Neue Elemente ────────────────────────────────────────────────────

    function createPoint(lngLat, category, display) {
        var cat = categoryFor(category);
        var name = display === 'label' ? 'Neuer Text' : cat.newLabel || cat.label;

        mutate('Element hinzugefügt – Eigenschaften rechts bearbeiten.', function () {
            var feature = {
                type: 'Feature',
                properties: {
                    id: uniqueId(slugify(name), -1),
                    category: category,
                    name: name,
                    description: '',
                    icon: cat.icon,
                },
                geometry: { type: 'Point', coordinates: [lngLat.lng, lngLat.lat] },
            };

            if (display === 'label') {
                feature.properties.display = 'label';
            }

            state.geo.features.push(feature);
            state.selected = state.geo.features.length - 1;
        });

        setTool('select');
    }

    function createDefaultArea(lngLat) {
        var offset = metersToDegrees(40, lngLat.lat);

        mutate('Fläche hinzugefügt – Eckpunkte per Griff verschieben.', function () {
            var ring = [
                [lngLat.lng - offset.dLng, lngLat.lat + offset.dLat],
                [lngLat.lng + offset.dLng, lngLat.lat + offset.dLat],
                [lngLat.lng + offset.dLng, lngLat.lat - offset.dLat],
                [lngLat.lng - offset.dLng, lngLat.lat - offset.dLat],
            ];
            ring.push([ring[0][0], ring[0][1]]);

            state.geo.features.push({
                type: 'Feature',
                properties: { id: uniqueId('flaeche', -1), name: 'Neue Fläche', description: '' },
                geometry: { type: 'Polygon', coordinates: [ring] },
            });

            state.selected = state.geo.features.length - 1;
        });

        setTool('select');
    }

    function createDefaultRoute(lngLat) {
        var offset = metersToDegrees(60, lngLat.lat);

        mutate('Strecke hinzugefügt – Punkte per Griff verschieben.', function () {
            state.geo.features.push({
                type: 'Feature',
                properties: { id: uniqueId('strecke', -1), name: 'Neue Strecke', description: '' },
                geometry: {
                    type: 'LineString',
                    coordinates: [
                        [lngLat.lng - offset.dLng, lngLat.lat],
                        [lngLat.lng + offset.dLng, lngLat.lat],
                    ],
                },
            });

            state.selected = state.geo.features.length - 1;
        });

        setTool('select');
    }

    function finishDraft() {
        if (!state.draft) return;

        var coords = dedupeCoords(state.draft.coords);
        var type = state.draft.type;
        var minimum = type === 'area' ? 3 : 2;

        if (coords.length < minimum) {
            setStatus(
                type === 'area'
                    ? 'Eine Fläche braucht mindestens 3 Punkte.'
                    : 'Eine Strecke braucht mindestens 2 Punkte.',
                true
            );
            return;
        }

        mutate(
            type === 'area' ? 'Fläche erstellt.' : 'Strecke erstellt.',
            function () {
                if (type === 'area') {
                    var ring = coords.slice();
                    ring.push([ring[0][0], ring[0][1]]);

                    state.geo.features.push({
                        type: 'Feature',
                        properties: { id: uniqueId('flaeche', -1), name: 'Neue Fläche', description: '' },
                        geometry: { type: 'Polygon', coordinates: [ring] },
                    });
                } else {
                    state.geo.features.push({
                        type: 'Feature',
                        properties: { id: uniqueId('strecke', -1), name: 'Neue Strecke', description: '' },
                        geometry: { type: 'LineString', coordinates: coords },
                    });
                }

                state.draft = null;
                state.selected = state.geo.features.length - 1;
            }
        );

        setTool('select');
    }

    function cancelDraft() {
        if (!state.draft) return;
        state.draft = null;
        setTool('select');
        setStatus('Zeichnen abgebrochen.', false);
    }

    /** Doppelklicks erzeugen einen doppelten Punkt – der fliegt hier raus. */
    function dedupeCoords(coords) {
        return coords.filter(function (coord, index) {
            if (index === 0) return true;
            var previous = coords[index - 1];
            return Math.abs(coord[0] - previous[0]) > 1e-9 || Math.abs(coord[1] - previous[1]) > 1e-9;
        });
    }

    // ── Werkzeuge ────────────────────────────────────────────────────────

    function setTool(tool, category) {
        if (state.draft && tool !== state.draft.type) {
            state.draft = null;
        }

        state.tool = tool;

        if (category) {
            state.draftCategory = category;
        }

        if (tool === 'area' || tool === 'route') {
            state.draft = { type: tool, coords: [] };
            if (map) map.doubleClickZoom.disable();
        } else if (map) {
            map.doubleClickZoom.enable();
        }

        render();
    }

    // ── Karten-Interaktion ───────────────────────────────────────────────

    function onMapClick(event) {
        if (suppressNextClick) {
            suppressNextClick = false;
            return;
        }

        if (state.draft) {
            state.draft.coords.push([event.lngLat.lng, event.lngLat.lat]);
            renderDraftSource();
            renderToolbar();
            return;
        }

        if (state.tool === 'poi') {
            createPoint(event.lngLat, state.draftCategory, 'pin');
            return;
        }

        if (state.tool === 'text') {
            createPoint(event.lngLat, state.draftCategory, 'label');
            return;
        }

        // Auswahl über die Vektor-Layer, sonst Auswahl aufheben.
        var hits = map.queryRenderedFeatures(event.point, { layers: visibleShapeLayers() });

        if (hits.length) {
            var index = hits[0].properties._idx;
            if (typeof index === 'number' && index !== state.selected) {
                selectFeature(index);
            }
            return;
        }

        if (state.selected !== -1) {
            selectFeature(-1);
        }
    }

    function visibleShapeLayers() {
        return SHAPE_LAYERS.filter(function (layerId) {
            return map.getLayer(layerId);
        });
    }

    function onMapDblClick(event) {
        if (!state.draft) return;
        event.preventDefault();
        finishDraft();
    }

    function onMapMouseMove() {
        if (state.draft || state.tool === 'poi' || state.tool === 'text') {
            map.getCanvas().style.cursor = state.draft ? 'crosshair' : 'copy';
        }
    }

    /**
     * Flächen und Strecken lassen sich als Ganzes verschieben: anklicken und
     * ziehen, statt jeden Punkt einzeln anzufassen. Die Auswahl wechselt dabei
     * direkt auf das angefasste Element.
     */
    function onShapeMouseDown(event) {
        if (state.tool !== 'select' || state.draft) return;

        // Marker, Textlabels und Punkt-Griffe liegen als DOM im Kartencontainer
        // und melden ihr Mousedown ebenfalls an die Karte. Sie haben Vorrang.
        var target = event.originalEvent && event.originalEvent.target;
        if (target && target.closest && target.closest('.maplibregl-marker')) return;

        var hits = map.queryRenderedFeatures(event.point, { layers: visibleShapeLayers() });
        if (!hits.length) return;

        var index = hits[0].properties._idx;
        if (typeof index !== 'number') return;

        event.preventDefault();

        if (index !== state.selected) {
            selectFeature(index);
        }

        translating = {
            index: index,
            last: event.lngLat,
            before: JSON.stringify(state.geo),
            moved: false,
        };

        map.dragPan.disable();
        map.getCanvas().style.cursor = 'grabbing';

        map.on('mousemove', onTranslateMove);
        map.once('mouseup', onTranslateEnd);
    }

    function onTranslateMove(event) {
        if (!translating) return;

        var dLng = event.lngLat.lng - translating.last.lng;
        var dLat = event.lngLat.lat - translating.last.lat;
        translating.last = event.lngLat;
        translating.moved = true;

        var feature = state.geo.features[translating.index];
        translateCoordinates(feature.geometry.coordinates, dLng, dLat);

        renderShapeSource();
        handles.forEach(function (handle) {
            var lngLat = handle.getLngLat();
            handle.setLngLat([lngLat.lng + dLng, lngLat.lat + dLat]);
        });
    }

    function onTranslateEnd() {
        if (!translating) return;

        map.off('mousemove', onTranslateMove);
        map.dragPan.enable();
        map.getCanvas().style.cursor = '';

        var moved = translating.moved;
        var before = translating.before;
        translating = null;

        if (moved) {
            // Der Klick nach dem Ziehen darf die Auswahl nicht aufheben.
            suppressNextClick = true;
            pushHistory(before);
            afterChange('Element verschoben.');
        }
    }

    function translateCoordinates(input, dLng, dLat) {
        if (!Array.isArray(input) || !input.length) return;

        if (typeof input[0] === 'number' && typeof input[1] === 'number') {
            input[0] += dLng;
            input[1] += dLat;
            return;
        }

        input.forEach(function (child) {
            translateCoordinates(child, dLng, dLat);
        });
    }

    // ── Drag & Drop aus der Palette ──────────────────────────────────────

    function setupPalette() {
        if (!el.palette) return;

        Array.prototype.forEach.call(el.palette.querySelectorAll('[data-kuh-create]'), function (chip) {
            var payload = {
                create: chip.dataset.kuhCreate,
                category: chip.dataset.kuhCategory || state.draftCategory,
            };

            chip.addEventListener('click', function () {
                if (payload.create === 'poi') {
                    setTool('poi', payload.category);
                } else if (payload.create === 'text') {
                    setTool('text', payload.category);
                } else if (payload.create === 'area') {
                    setTool('area');
                } else if (payload.create === 'route') {
                    setTool('route');
                }
            });

            chip.addEventListener('dragstart', function (event) {
                state.dragPayload = payload;
                chip.classList.add('kuh-map-palette__chip--dragging');
                event.dataTransfer.effectAllowed = 'copy';
                try {
                    event.dataTransfer.setData('text/plain', JSON.stringify(payload));
                } catch (error) {
                    // Manche Browser erlauben setData nur mit bestimmten Typen.
                }
            });

            chip.addEventListener('dragend', function () {
                chip.classList.remove('kuh-map-palette__chip--dragging');
                el.canvas.classList.remove('kuh-map-canvas--dropzone');
                state.dragPayload = null;
            });
        });

        el.canvas.addEventListener('dragenter', function (event) {
            if (!state.dragPayload) return;
            event.preventDefault();
            el.canvas.classList.add('kuh-map-canvas--dropzone');
        });

        el.canvas.addEventListener('dragover', function (event) {
            if (!state.dragPayload) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        });

        el.canvas.addEventListener('dragleave', function (event) {
            if (event.target === el.canvas) {
                el.canvas.classList.remove('kuh-map-canvas--dropzone');
            }
        });

        el.canvas.addEventListener('drop', function (event) {
            event.preventDefault();
            el.canvas.classList.remove('kuh-map-canvas--dropzone');

            var payload = state.dragPayload;

            if (!payload) {
                try {
                    payload = JSON.parse(event.dataTransfer.getData('text/plain'));
                } catch (error) {
                    return;
                }
            }

            state.dragPayload = null;

            if (!payload || !map) return;

            var rect = el.canvas.getBoundingClientRect();
            var lngLat = map.unproject([event.clientX - rect.left, event.clientY - rect.top]);

            if (payload.create === 'poi') {
                createPoint(lngLat, payload.category, 'pin');
            } else if (payload.create === 'text') {
                createPoint(lngLat, payload.category, 'label');
            } else if (payload.create === 'area') {
                createDefaultArea(lngLat);
            } else if (payload.create === 'route') {
                createDefaultRoute(lngLat);
            }
        });
    }

    // ── Hintergrundbild-Panel ────────────────────────────────────────────

    function setupImagePanel() {
        if (el.imageUrl) {
            el.imageUrl.value = state.geo.meta.customMapImageUrl || '';
            el.imageUrl.addEventListener('change', function () {
                mutate('Hintergrundbild gespeichert.', function () {
                    var value = el.imageUrl.value.trim();
                    if (value) {
                        state.geo.meta.customMapImageUrl = value;
                    } else {
                        delete state.geo.meta.customMapImageUrl;
                    }
                });
                renderImageLayer();
            });
        }

        if (el.imagePick) {
            el.imagePick.addEventListener('click', function () {
                if (!window.wp || !wp.media) {
                    setStatus('Die Mediathek ist auf dieser Seite nicht verfügbar.', true);
                    return;
                }

                var frame = wp.media({
                    title: 'Hintergrundbild für den Geländeplan',
                    button: { text: 'Bild verwenden' },
                    library: { type: ['image'] },
                    multiple: false,
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();

                    mutate('Hintergrundbild gesetzt.', function () {
                        state.geo.meta.customMapImageUrl = attachment.url;
                    });

                    if (el.imageUrl) el.imageUrl.value = attachment.url;
                    renderImageLayer();
                });

                frame.open();
            });
        }

        if (el.imageClear) {
            el.imageClear.addEventListener('click', function () {
                mutate('Hintergrundbild entfernt.', function () {
                    delete state.geo.meta.customMapImageUrl;
                });
                if (el.imageUrl) el.imageUrl.value = '';
                state.alignImage = false;
                removeImageLayer();
                render();
            });
        }

        if (el.imageOpacity) {
            el.imageOpacity.value = opacityValue();
            if (el.imageOpacityOut) el.imageOpacityOut.textContent = opacityValue() + '%';

            el.imageOpacity.addEventListener('input', function () {
                state.geo.meta.customMapImageOpacity = Number(el.imageOpacity.value);
                if (el.imageOpacityOut) el.imageOpacityOut.textContent = el.imageOpacity.value + '%';
                if (map && map.getLayer(LAYER_IMAGE)) {
                    map.setPaintProperty(LAYER_IMAGE, 'raster-opacity', opacityValue() / 100);
                }
            });

            el.imageOpacity.addEventListener('change', function () {
                markDirty();
                syncTextarea();
                setStatus('Deckkraft des Hintergrundbildes gespeichert.', false);
            });
        }

        if (el.imageAlign) {
            el.imageAlign.addEventListener('click', function () {
                if (!state.geo.meta.customMapImageUrl) {
                    setStatus('Zuerst ein Hintergrundbild wählen.', true);
                    return;
                }
                state.alignImage = !state.alignImage;
                render();
                setStatus(
                    state.alignImage
                        ? 'Ecken des Hintergrundbildes können jetzt gezogen werden.'
                        : 'Ausrichten beendet.',
                    false
                );
            });
        }

        if (el.imageFitArea) {
            el.imageFitArea.addEventListener('click', function () {
                var bounds = areaBoundingBox();
                if (!bounds) {
                    setStatus('Es ist keine Fläche vorhanden, an der das Bild ausgerichtet werden könnte.', true);
                    return;
                }
                mutate('Bild an Marktfläche ausgerichtet.', function () {
                    setImageBoundsFromArray(bounds);
                });
                renderImageLayer();
            });
        }

        if (el.imageFitView) {
            el.imageFitView.addEventListener('click', function () {
                var bounds = viewBoundsArray();
                if (!bounds) return;
                mutate('Bild an aktuelle Ansicht ausgerichtet.', function () {
                    setImageBoundsFromArray(bounds);
                });
                renderImageLayer();
            });
        }
    }

    // ── Sonstige Bedienelemente ──────────────────────────────────────────

    function setupControls() {
        if (el.tools) {
            Array.prototype.forEach.call(el.tools.querySelectorAll('[data-kuh-tool]'), function (button) {
                button.addEventListener('click', function () {
                    setTool(button.dataset.kuhTool, button.dataset.kuhCategory);
                });
            });
        }

        if (el.undo) el.undo.addEventListener('click', undo);
        if (el.redo) el.redo.addEventListener('click', redo);
        if (el.finish) el.finish.addEventListener('click', finishDraft);
        if (el.cancel) el.cancel.addEventListener('click', cancelDraft);

        // Der Speichern-Button ist ein echter Submit-Button; das Textarea wird
        // im submit-Handler aktualisiert, bevor der Browser das Formular liest.
        if (el.form) {
            el.form.addEventListener('submit', function () {
                syncTextarea();
                state.dirty = false;
                root.classList.remove('kuh-map-editor--dirty');
            });
        }

        if (el.jsonApply) {
            el.jsonApply.addEventListener('click', function () {
                try {
                    var parsed = readTextarea();
                    pushHistory(JSON.stringify(state.geo));
                    state.geo = parsed;
                    state.selected = -1;
                    state.draft = null;
                    renderImageLayer();
                    afterChange('JSON übernommen.');
                    if (el.imageUrl) el.imageUrl.value = state.geo.meta.customMapImageUrl || '';
                    if (el.imageOpacity) el.imageOpacity.value = opacityValue();
                    if (map) {
                        map.jumpTo({ center: state.geo.meta.center, zoom: state.geo.meta.zoom });
                    }
                } catch (error) {
                    setStatus('JSON konnte nicht gelesen werden: ' + error.message, true);
                }
            });
        }

        if (el.viewSave) {
            el.viewSave.addEventListener('click', function () {
                if (!map) return;
                var center = map.getCenter();

                mutate('Startansicht gespeichert.', function () {
                    state.geo.meta.center = [Number(center.lng.toFixed(6)), Number(center.lat.toFixed(6))];
                    state.geo.meta.zoom = Number(map.getZoom().toFixed(2));
                });

                updateViewInfo();
            });
        }

        document.addEventListener('keydown', onKeyDown);

        window.addEventListener('beforeunload', function (event) {
            if (!state.dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    function submitForm() {
        if (!el.form) return;
        syncTextarea();

        if (el.form.requestSubmit) {
            el.form.requestSubmit();
        } else {
            el.form.submit();
        }
    }

    function onKeyDown(event) {
        var key = event.key || '';
        var target = event.target;
        var inField =
            target &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable);

        if ((event.ctrlKey || event.metaKey) && key.toLowerCase() === 's') {
            event.preventDefault();
            submitForm();
            return;
        }

        if ((event.ctrlKey || event.metaKey) && key.toLowerCase() === 'z') {
            event.preventDefault();
            if (event.shiftKey) redo();
            else undo();
            return;
        }

        if ((event.ctrlKey || event.metaKey) && key.toLowerCase() === 'y') {
            event.preventDefault();
            redo();
            return;
        }

        if (inField) return;

        if (key === 'Escape') {
            if (state.draft) cancelDraft();
            else if (state.tool !== 'select') setTool('select');
            else if (state.selected !== -1) selectFeature(-1);
            return;
        }

        if (state.draft) {
            if (key === 'Enter') {
                event.preventDefault();
                finishDraft();
            } else if (key === 'Backspace' || key === 'Delete') {
                event.preventDefault();
                state.draft.coords.pop();
                renderDraftSource();
                renderToolbar();
            }
            return;
        }

        if (key === 'Delete' && state.selected !== -1) {
            event.preventDefault();
            deleteSelected();
            return;
        }

        var shortcuts = { v: 'select', p: 'poi', t: 'text', f: 'area', s: 'route' };
        var tool = shortcuts[key.toLowerCase()];

        if (tool) {
            event.preventDefault();
            setTool(tool);
        }
    }

    // ── Start ────────────────────────────────────────────────────────────

    try {
        state.geo = readTextarea();
    } catch (error) {
        state.geo = emptyCollection();
        setStatus('Gespeichertes JSON war fehlerhaft (' + error.message + ') – es wurde eine leere Karte geladen.', true);
    }

    syncTextarea();
    state.dirty = false;
    root.classList.remove('kuh-map-editor--dirty');

    setupControls();
    setupPalette();
    setupImagePanel();
    initMap();
    renderList();
    renderProps();
    renderLegend();
    renderToolbar();
})();
