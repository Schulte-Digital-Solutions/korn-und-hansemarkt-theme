<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import maplibregl from 'maplibre-gl';
  import 'maplibre-gl/dist/maplibre-gl.css';
  import type { Map, Marker, Popup } from 'maplibre-gl';

  interface PoiFeature {
    properties?: {
      category?: string;
      name?: string;
      description?: string;
    };
    geometry?: {
      type?: string;
      coordinates?: unknown;
    };
  }

  interface PoiData {
    meta?: {
      center?: [number, number];
      zoom?: number;
    };
    features?: PoiFeature[];
  }

  // ─── Props ────────────────────────────────────────────────────────────────
  interface Props {
    title: string;
    subtitle: string;
    mapHeight: number;
    useMinimalBaseMap: boolean;
    showStreetLabels: boolean;
    customMapImageUrl: string;
    customMapImageAlt: string;
    customMapImageOpacity: number;
    areaFillColor: string;
    areaFillOpacity: number;
    areaLineColor: string;
    locationColor: string;
    entranceColor: string;
    stageColor: string;
    parkingColor: string;
    toiletColor: string;
    infoColor: string;
    showLocations: boolean;
    showEntrances: boolean;
    showStages: boolean;
    showParking: boolean;
    showToilets: boolean;
    showInfo: boolean;
    showLegend: boolean;
    poisData?: PoiData;
  }

  let {
    title = 'Geländeplan',
    subtitle = '',
    mapHeight = 580,
    useMinimalBaseMap = true,
    showStreetLabels = false,
    customMapImageUrl = '',
    customMapImageAlt = '',
    customMapImageOpacity = 30,
    areaFillColor = '#9ccf9c',
    areaFillOpacity = 28,
    areaLineColor = '#4a8a4a',
    locationColor = '#15331b',
    entranceColor = '#725c0c',
    stageColor = '#8b1a1a',
    parkingColor = '#1a4a6b',
    toiletColor = '#4a4a6b',
    infoColor = '#2d6b4a',
    showLocations = true,
    showEntrances = true,
    showStages = true,
    showParking = true,
    showToilets = true,
    showInfo = true,
    showLegend = true,
    poisData = {
      meta: {
        center: [7.4836, 52.6742],
        zoom: 15,
      },
      features: [],
    },
  }: Props = $props();

  // ─── POI-Kategorien ───────────────────────────────────────────────────────
  interface PoiCategory {
    label: string;
    color: string;
    emoji: string;
    show: boolean;
  }

  const categories: Record<string, PoiCategory> = $derived({
    location:  { label: 'Benannte Orte',  color: locationColor, emoji: '🏛️', show: showLocations },
    entrance:  { label: 'Eingänge',       color: entranceColor, emoji: '🚪', show: showEntrances },
    stage:     { label: 'Bühnen',         color: stageColor,    emoji: '🎭', show: showStages },
    parking:   { label: 'Parkplätze',     color: parkingColor,  emoji: '🅿️', show: showParking },
    toilet:    { label: 'Toiletten',      color: toiletColor,   emoji: '🚻', show: showToilets },
    info:      { label: 'Info & Hilfe',   color: infoColor,     emoji: 'ℹ️', show: showInfo },
  });

  let legendVisibility = $state({
    area: true,
    location: true,
    entrance: true,
    stage: true,
    parking: true,
    toilet: true,
    info: true,
  });

  $effect(() => {
    legendVisibility.location = showLocations;
    legendVisibility.entrance = showEntrances;
    legendVisibility.stage = showStages;
    legendVisibility.parking = showParking;
    legendVisibility.toilet = showToilets;
    legendVisibility.info = showInfo;
  });

  function toggleLegendItem(key: keyof typeof legendVisibility): void {
    legendVisibility[key] = !legendVisibility[key];
    if (map?.loaded()) {
      renderAreas();
      renderMarkers();
    }
  }

  // ─── State ────────────────────────────────────────────────────────────────
  let mapContainer: HTMLDivElement;
  let map: Map | null = null;
  const markers: Marker[] = [];
  let activePopup: Popup | null = null;
  const areaSourceId = 'kuh-area-source';
  const areaFillLayerId = 'kuh-area-fill';
  const areaLineLayerId = 'kuh-area-line';
  const customImageSourceId = 'kuh-custom-image-source';
  const customImageLayerId = 'kuh-custom-image-layer';

  function clamp(value: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, value));
  }

  function collectLngLatPairs(input: unknown, result: Array<[number, number]>): void {
    if (!Array.isArray(input) || input.length === 0) return;

    if (
      input.length >= 2
      && typeof input[0] === 'number'
      && typeof input[1] === 'number'
    ) {
      result.push([input[0], input[1]]);
      return;
    }

    for (const child of input) {
      collectLngLatPairs(child, result);
    }
  }

  function getImageCoordinatesFromData(): [[number, number], [number, number], [number, number], [number, number]] {
    const coords: Array<[number, number]> = [];
    const features = Array.isArray(poisData?.features) ? poisData.features : [];

    for (const feature of features) {
      collectLngLatPairs(feature?.geometry?.coordinates, coords);
    }

    if (coords.length === 0) {
      const center = Array.isArray(poisData?.meta?.center) ? poisData.meta.center : [7.4836, 52.6742];
      const deltaLng = 0.002;
      const deltaLat = 0.0015;
      return [
        [center[0] - deltaLng, center[1] + deltaLat],
        [center[0] + deltaLng, center[1] + deltaLat],
        [center[0] + deltaLng, center[1] - deltaLat],
        [center[0] - deltaLng, center[1] - deltaLat],
      ];
    }

    let minLng = Number.POSITIVE_INFINITY;
    let maxLng = Number.NEGATIVE_INFINITY;
    let minLat = Number.POSITIVE_INFINITY;
    let maxLat = Number.NEGATIVE_INFINITY;

    for (const [lng, lat] of coords) {
      minLng = Math.min(minLng, lng);
      maxLng = Math.max(maxLng, lng);
      minLat = Math.min(minLat, lat);
      maxLat = Math.max(maxLat, lat);
    }

    const lngPad = Math.max((maxLng - minLng) * 0.02, 0.0002);
    const latPad = Math.max((maxLat - minLat) * 0.02, 0.0002);

    return [
      [minLng - lngPad, maxLat + latPad],
      [maxLng + lngPad, maxLat + latPad],
      [maxLng + lngPad, minLat - latPad],
      [minLng - lngPad, minLat - latPad],
    ];
  }

  function renderCustomImageLayer() {
    if (!map || !map.loaded()) return;

    const existingLayer = map.getLayer(customImageLayerId);
    if (existingLayer) {
      map.removeLayer(customImageLayerId);
    }

    const existingSource = map.getSource(customImageSourceId);
    if (existingSource) {
      map.removeSource(customImageSourceId);
    }

    if (!customMapImageUrl) {
      return;
    }

    map.addSource(customImageSourceId, {
      type: 'image',
      url: customMapImageUrl,
      coordinates: getImageCoordinatesFromData(),
    });

    map.addLayer({
      id: customImageLayerId,
      type: 'raster',
      source: customImageSourceId,
      paint: {
        'raster-opacity': clamp(customMapImageOpacity, 0, 100) / 100,
      },
    });
  }

  function renderAreas() {
    if (!map || !map.loaded()) return;

    const features = Array.isArray(poisData?.features) ? poisData.features : [];
    const areaFeatures = legendVisibility.area
      ? features.filter((feature) => {
          const type = feature?.geometry?.type;
          return type === 'Polygon' || type === 'MultiPolygon';
        })
      : [];

    const areaGeoJson = {
      type: 'FeatureCollection',
      features: areaFeatures,
    } as const;

    const existingSource = map.getSource(areaSourceId) as maplibregl.GeoJSONSource | undefined;

    if (existingSource) {
      existingSource.setData(areaGeoJson as any);
      return;
    }

    map.addSource(areaSourceId, {
      type: 'geojson',
      data: areaGeoJson as any,
    });

    map.addLayer({
      id: areaFillLayerId,
      type: 'fill',
      source: areaSourceId,
      paint: {
        'fill-color': areaFillColor,
        'fill-opacity': clamp(areaFillOpacity, 0, 100) / 100,
      },
    });

    map.addLayer({
      id: areaLineLayerId,
      type: 'line',
      source: areaSourceId,
      paint: {
        'line-color': areaLineColor,
        'line-width': 2,
        'line-opacity': 0.75,
      },
    });
  }

  // ─── Text-Label für benannte Orte ────────────────────────────────────────
  function createTextLabelEl(name: string, color: string): HTMLElement {
    const el = document.createElement('div');
    el.className = 'kuh-map-text-label';
    el.setAttribute('role', 'button');
    el.style.cssText = `
      display: inline-block;
      width: fit-content;
      white-space: nowrap;
      color: ${color};
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.03em;
      background: transparent;
      padding: 0;
      cursor: pointer;
      text-shadow:
        0 1px 3px rgba(255,255,255,0.95),
        0 -1px 3px rgba(255,255,255,0.95),
        1px 0 3px rgba(255,255,255,0.95),
        -1px 0 3px rgba(255,255,255,0.95);
    `;
    el.textContent = name;
    return el;
  }

  // ─── Marker-SVG erstellen ─────────────────────────────────────────────────
  function createMarkerEl(category: string): HTMLElement {
    const cat = categories[category];
    const el = document.createElement('div');
    el.className = 'kuh-map-marker';
    el.setAttribute('role', 'button');
    el.setAttribute('aria-label', cat?.label ?? category);

    // Flexbox-Column: offsetHeight ist bei jedem Zoom-Level stabil.
    // Keine position:absolute-Kinder – MapLibre misst so die Höhe korrekt.
    el.style.cssText = `
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 36px;
      cursor: pointer;
    `;

    const circle = document.createElement('div');
    circle.style.cssText = `
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: ${cat?.color ?? '#011e08'};
      border: 2px solid rgba(0,0,0,0.25);
      box-shadow: 0 2px 8px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    `;

    const inner = document.createElement('span');
    inner.style.cssText = `
      font-size: 16px;
      line-height: 1;
      user-select: none;
    `;
    inner.textContent = cat?.emoji ?? '📍';
    circle.appendChild(inner);

    const pointer = document.createElement('div');
    pointer.style.cssText = `
      width: 10px;
      height: 7px;
      background: ${cat?.color ?? '#011e08'};
      clip-path: polygon(50% 100%, 0 0, 100% 0);
      flex-shrink: 0;
    `;

    el.appendChild(circle);
    el.appendChild(pointer);

    return el;
  }

  // ─── Alle Marker rendern ──────────────────────────────────────────────────
  function renderMarkers() {
    if (!map) return;

    // Bestehende Marker entfernen
    markers.forEach((m) => m.remove());
    markers.length = 0;

    const features = Array.isArray(poisData?.features) ? poisData.features : [];

    for (const feature of features) {
      const category = feature?.properties?.category ?? '';
      const name = feature?.properties?.name ?? 'Ort';
      const description = feature?.properties?.description ?? '';
      const cat = categories[category];
      if (!cat || !cat.show || !legendVisibility[category as keyof typeof legendVisibility]) continue;

      const coords = feature?.geometry?.coordinates;
      if (!coords || coords.length < 2) continue;

      const [lng, lat] = coords;
      const isLocation = category === 'location';
      const el = isLocation ? createTextLabelEl(name, cat.color) : createMarkerEl(category);

      // Popup
      const popup = new maplibregl.Popup({
        offset: isLocation ? [0, -12] : [0, -40],
        closeButton: true,
        closeOnClick: false,
        maxWidth: '260px',
        className: 'kuh-map-popup',
      }).setHTML(`
        <div class="kuh-popup-inner">
          <span class="kuh-popup-emoji">${cat.emoji}</span>
          <div class="kuh-popup-text">
            <strong class="kuh-popup-name">${name}</strong>
            <p class="kuh-popup-desc">${description}</p>
          </div>
        </div>
      `);

      // Pins: transform-freier Wrapper (36x44), Spitze liegt mittig unten -> anchor 'bottom'
      // Text-Labels: anchor 'center' damit Text auf der Koordinate zentriert ist
      const marker = new maplibregl.Marker(
        isLocation
          ? { element: el, anchor: 'center' }
          : { element: el, anchor: 'bottom' }
      )
        .setLngLat([lng, lat])
        .setPopup(popup)
        .addTo(map);

      el.addEventListener('click', () => {
        if (activePopup && activePopup !== popup) {
          activePopup.remove();
        }
        activePopup = popup;
      });

      markers.push(marker);
    }
  }

  // ─── Map initialisieren ───────────────────────────────────────────────────
  onMount(() => {
    const center =
      Array.isArray(poisData?.meta?.center) && poisData.meta.center.length >= 2
        ? poisData.meta.center
        : [7.4836, 52.6742];

    const zoom = typeof poisData?.meta?.zoom === 'number' ? poisData.meta.zoom : 15;

    const baseTileUrls = useMinimalBaseMap
      ? [
          'https://a.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
          'https://b.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
          'https://c.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
          'https://d.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
        ]
      : ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'];

    const labelTileUrls = [
      'https://a.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
      'https://b.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
      'https://c.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
      'https://d.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
    ];

    const tileAttribution = useMinimalBaseMap
      ? '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende, © <a href="https://carto.com/attributions">CARTO</a>'
      : '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende';

    const styleSources: Record<string, any> = {
      base: {
        type: 'raster',
        tiles: baseTileUrls,
        tileSize: 256,
        attribution: tileAttribution,
        maxzoom: 19,
      },
    };

    const styleLayers: any[] = [
      {
        id: 'base-tiles',
        type: 'raster',
        source: 'base',
        minzoom: 0,
        maxzoom: 22,
      },
    ];

    if ( useMinimalBaseMap && showStreetLabels ) {
      styleSources.labels = {
        type: 'raster',
        tiles: labelTileUrls,
        tileSize: 256,
        maxzoom: 19,
      };

      styleLayers.push({
        id: 'street-labels',
        type: 'raster',
        source: 'labels',
        minzoom: 0,
        maxzoom: 22,
      });
    }

    map = new maplibregl.Map({
      container: mapContainer,
      style: {
        version: 8,
        name: 'KuH Vintage',
        sources: styleSources,
        layers: styleLayers,
      },
      center: center as [number, number],
      zoom: zoom ?? 15,
      attributionControl: false,
    });

    map.addControl(
      new maplibregl.AttributionControl({ compact: true }),
      'bottom-right'
    );
    map.addControl(
      new maplibregl.NavigationControl({ showCompass: false }),
      'top-right'
    );
    map.addControl(
      new maplibregl.ScaleControl({ maxWidth: 120, unit: 'metric' }),
      'bottom-left'
    );

    map.on('load', () => {
      renderCustomImageLayer();
      renderAreas();
      renderMarkers();
    });
  });

  onDestroy(() => {
    markers.forEach((m) => m.remove());
    map?.remove();
    map = null;
  });

  // Marker neu rendern wenn Sichtbarkeits-Props sich ändern
  $effect(() => {
    // Reaktivität auf alle show-Props
    void showLocations; void showEntrances; void showStages;
    void showParking; void showToilets; void showInfo;
    if (map?.loaded()) {
      renderCustomImageLayer();
      renderAreas();
      renderMarkers();
    }
  });

  // ─── Legende ──────────────────────────────────────────────────────────────
  const visibleCategories = $derived(
    Object.entries(categories).filter(([, cat]) => cat.show)
  );

  const legendItems = $derived([
    {
      key: 'area',
      label: 'Marktfläche',
      emoji: '🌿',
      color: '#9ccf9c',
      isVisible: true,
    },
    ...visibleCategories.map(([key, cat]) => ({
      key,
      label: cat.label,
      emoji: cat.emoji,
      color: cat.color,
      isVisible: true,
    })),
  ]);
</script>

<section class="kuh-event-map-section">
  <!-- Kopfzeile -->
  {#if title}
    <div class="kuh-event-map-header">
      <h2 class="kuh-event-map-title">{title}</h2>
      {#if subtitle}
        <p class="kuh-event-map-subtitle">{subtitle}</p>
      {/if}
    </div>
  {/if}

  <div class="kuh-event-map-frame">
    <!-- Karten-Wrapper mit Vintage-Filter -->
    <div class="kuh-event-map-wrapper" style="height: {mapHeight}px;">
      <div
        bind:this={mapContainer}
        class="kuh-event-map-canvas"
        class:kuh-event-map-canvas--minimal={useMinimalBaseMap}
      ></div>
    </div>

    <!-- Legende -->
    {#if showLegend && legendItems.length > 0}
      <div class="kuh-event-map-legend" aria-label="Kartenlegende">
        <span class="kuh-legend-label">Legende:</span>
        {#each legendItems as item}
          <button
            type="button"
            class="kuh-legend-item"
            class:kuh-legend-item--off={!legendVisibility[item.key as keyof typeof legendVisibility]}
            onclick={() => toggleLegendItem(item.key as keyof typeof legendVisibility)}
            aria-pressed={legendVisibility[item.key as keyof typeof legendVisibility]}
          >
            <span class="kuh-legend-dot" style="background:{item.color};">{item.emoji}</span>
            {item.label}
          </button>
        {/each}
      </div>
    {/if}
  </div>
</section>

<style>
  /* ── Layout ─────────────────────────────────────────────────────── */
  .kuh-event-map-section {
    width: 100%;
    font-family: var(--font-body, 'Inter', sans-serif);
  }

  .kuh-event-map-header {
    text-align: center;
    padding: 2.5rem 1.5rem 1.5rem;
  }

  .kuh-event-map-title {
    font-family: var(--font-headline, serif);
    font-size: clamp(2rem, 5vw, 3rem);
    color: var(--color-primary, #011e08);
    margin: 0 0 0.5rem;
    line-height: 1.1;
  }

  .kuh-event-map-subtitle {
    font-family: var(--font-serif-italic, serif);
    font-style: italic;
    color: var(--color-outline, #737971);
    font-size: 1rem;
    margin: 0;
  }

  /* ── Karten-Container ────────────────────────────────────────────── */
  .kuh-event-map-frame {
    width: 100%;
    box-shadow:
      0 0 0 3px var(--color-secondary, #725c0c),
      0 0 0 6px var(--color-primary, #011e08),
      0 8px 32px rgba(0, 0, 0, 0.35);
  }

  .kuh-event-map-wrapper {
    position: relative;
    width: 100%;
    border-radius: 0;
    overflow: hidden;
  }

  .kuh-event-map-canvas {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    filter: none;
  }

  .kuh-event-map-canvas--minimal {
    /* No-Labels-Basiskarte kontrastreicher statt ausgewaschen */
    filter: saturate(1.08) contrast(1.1) brightness(0.92);
  }

  /* ── Legende ─────────────────────────────────────────────────────── */
  .kuh-event-map-legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1.25rem;
    padding: 1rem 1.5rem;
    background: var(--color-surface-container-low, #f5f3f3);
    border: 1px solid var(--color-outline-variant, #c2c8bf);
    border-top: none;
    font-size: 0.8rem;
    color: var(--color-on-surface, #1b1c1c);
  }

  .kuh-legend-label {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.7rem;
    color: var(--color-outline, #737971);
    margin-right: 0.25rem;
  }

  .kuh-legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--color-outline-variant, #c2c8bf);
    border-radius: 999px;
    background: var(--color-surface-container-lowest, #fff);
    color: var(--color-on-surface, #1b1c1c);
    padding: 0.35rem 0.65rem;
    cursor: pointer;
    transition: opacity 0.2s ease, filter 0.2s ease, transform 0.1s ease;
    font: inherit;
  }

  .kuh-legend-item:hover {
    filter: brightness(0.98);
  }

  .kuh-legend-item:active {
    transform: scale(0.98);
  }

  .kuh-legend-item--off {
    opacity: 0.45;
  }

  .kuh-legend-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    font-size: 11px;
    flex-shrink: 0;
  }

  /* ── MapLibre Popup-Overrides ────────────────────────────────────── */
  :global(.kuh-map-popup .maplibregl-popup-content) {
    background: var(--color-surface-container-lowest, #fff);
    border: 1px solid var(--color-outline-variant, #c2c8bf);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    font-family: var(--font-body, 'Inter', sans-serif);
  }

  :global(.kuh-map-popup .maplibregl-popup-tip) {
    border-top-color: var(--color-surface-container-lowest, #fff);
  }

  :global(.kuh-map-popup .maplibregl-popup-close-button) {
    color: var(--color-outline, #737971);
    font-size: 1.25rem;
    line-height: 1;
    padding: 0.25rem 0.5rem;
    border: none;
    outline: none;
  }

  :global(.kuh-map-popup .maplibregl-popup-close-button:focus) {
    border: none;
    outline: none;
  }

  :global(.kuh-popup-inner) {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
  }

  :global(.kuh-popup-emoji) {
    font-size: 1.5rem;
    flex-shrink: 0;
    line-height: 1.2;
  }

  :global(.kuh-popup-text) {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
  }

  :global(.kuh-popup-name) {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--color-primary, #011e08);
    line-height: 1.2;
  }

  :global(.kuh-popup-desc) {
    font-size: 0.8rem;
    color: var(--color-outline, #737971);
    margin: 0;
    line-height: 1.4;
  }

  /* ── MapLibre Navigations-Controls ──────────────────────────────── */
  :global(.kuh-event-map-canvas .maplibregl-ctrl-group) {
    background: var(--color-surface-container-lowest, #fff);
    border: 1px solid var(--color-outline-variant, #c2c8bf) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    border-radius: 0.375rem;
  }

  :global(.kuh-event-map-canvas .maplibregl-ctrl-group button) {
    color: var(--color-primary, #011e08);
  }

  /* ── Responsiv ───────────────────────────────────────────────────── */
  @media (max-width: 640px) {
    .kuh-event-map-frame {
      box-shadow:
        0 0 0 2px var(--color-secondary, #725c0c),
        0 4px 16px rgba(0, 0, 0, 0.25);
    }

    .kuh-event-map-legend {
      font-size: 0.75rem;
      gap: 0.4rem 0.8rem;
      padding: 0.75rem 1rem;
    }
  }
</style>
