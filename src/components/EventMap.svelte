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
      customMapImageUrl?: string;
      customMapImageOpacity?: number;
      imageBounds?: {
        topLeft?: [number, number];
        topRight?: [number, number];
        bottomRight?: [number, number];
        bottomLeft?: [number, number];
      };
    };
    features?: PoiFeature[];
  }

  // ─── Props ────────────────────────────────────────────────────────────────
  interface Props {
    title: string;
    subtitle: string;
    mapHeight: number;
    mobileMapHeight: number;
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
    loadBaseTiles: boolean;
    mapBackgroundColor: string;
    enableExternalContentBlocker: boolean;
    externalContentBlockerTitle: string;
    externalContentBlockerText: string;
    externalContentButtonLabel: string;
    rememberExternalContentConsent: boolean;
    externalContentConsentStorageKey: string;
    privacyPolicyUrl: string;
    privacyPolicyLabel: string;
    cookieConsentCategory: string;
    poisData?: PoiData;
  }

  let {
    title = 'Geländeplan',
    subtitle = '',
    mapHeight = 580,
    mobileMapHeight = 420,
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
    loadBaseTiles = true,
    mapBackgroundColor = '#f3efe6',
    enableExternalContentBlocker = true,
    externalContentBlockerTitle = 'Externe Karteninhalte laden?',
    externalContentBlockerText = 'Beim Laden der Karte werden Daten von externen Anbietern (z. B. OpenStreetMap/CARTO) nachgeladen. Erst nach Zustimmung wird die Verbindung aufgebaut.',
    externalContentButtonLabel = 'Karte laden',
    rememberExternalContentConsent = true,
    externalContentConsentStorageKey = 'kuh-event-map-external-consent',
    privacyPolicyUrl = '',
    privacyPolicyLabel = 'Datenschutzerklärung',
    cookieConsentCategory = 'marketing',
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
    userLocation: false,
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
      renderUserLocation();
    }
  }

  // ─── State ────────────────────────────────────────────────────────────────
  let mapContainer: HTMLDivElement | null = null;
  let map: Map | null = null;
  const markers: Marker[] = [];
  let userLocationMarker: Marker | null = null;
  let userLocationPending = false;
  let customImageObjectUrl: string | null = null;
  let customImageRendering = false;
  let customImageRendered = false;
  let activePopup: Popup | null = null;
  let hasExternalContentConsent = $state(false);
  let initialConsentChecked = $state(false);
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

  function toImageCoordinates(minLng: number, maxLng: number, minLat: number, maxLat: number): [[number, number], [number, number], [number, number], [number, number]] {
    const lngPad = Math.max((maxLng - minLng) * 0.02, 0.0002);
    const latPad = Math.max((maxLat - minLat) * 0.02, 0.0002);

    return [
      [minLng - lngPad, maxLat + latPad],
      [maxLng + lngPad, maxLat + latPad],
      [maxLng + lngPad, minLat - latPad],
      [minLng - lngPad, minLat - latPad],
    ];
  }

  function isLngLatPair(value: unknown): value is [number, number] {
    return Array.isArray(value)
      && value.length >= 2
      && Number.isFinite(value[0])
      && Number.isFinite(value[1]);
  }

  function getCustomImageCoordinatesFromMeta(): [[number, number], [number, number], [number, number], [number, number]] | null {
    const bounds = poisData?.meta?.imageBounds;
    if (!bounds) return null;

    const { topLeft, topRight, bottomRight, bottomLeft } = bounds;
    if (!isLngLatPair(topLeft) || !isLngLatPair(topRight) || !isLngLatPair(bottomRight) || !isLngLatPair(bottomLeft)) {
      return null;
    }

    return [
      [topLeft[0], topLeft[1]],
      [topRight[0], topRight[1]],
      [bottomRight[0], bottomRight[1]],
      [bottomLeft[0], bottomLeft[1]],
    ];
  }

  function getImageCoordinatesFromData(): [[number, number], [number, number], [number, number], [number, number]] {
    // Vorrang: explizite Bildkoordinaten aus der JSON-Meta.
    const customBounds = getCustomImageCoordinatesFromMeta();
    if (customBounds) {
      return customBounds;
    }

    const areaCoords: Array<[number, number]> = [];
    const features = Array.isArray(poisData?.features) ? poisData.features : [];

    for (const feature of features) {
      const type = feature?.geometry?.type;
      if (type === 'Polygon' || type === 'MultiPolygon') {
        collectLngLatPairs(feature?.geometry?.coordinates, areaCoords);
      }
    }

    // Beste Positionierung: Fläche aus GeoJSON verwenden, falls vorhanden.
    if (areaCoords.length > 0) {
      let minLng = Number.POSITIVE_INFINITY;
      let maxLng = Number.NEGATIVE_INFINITY;
      let minLat = Number.POSITIVE_INFINITY;
      let maxLat = Number.NEGATIVE_INFINITY;

      for (const [lng, lat] of areaCoords) {
        minLng = Math.min(minLng, lng);
        maxLng = Math.max(maxLng, lng);
        minLat = Math.min(minLat, lat);
        maxLat = Math.max(maxLat, lat);
      }

      return toImageCoordinates(minLng, maxLng, minLat, maxLat);
    }

    // Wenn keine Fläche definiert ist: aktuelle Kartenansicht nutzen.
    if (map) {
      const bounds = map.getBounds();
      return [
        [bounds.getWest(), bounds.getNorth()],
        [bounds.getEast(), bounds.getNorth()],
        [bounds.getEast(), bounds.getSouth()],
        [bounds.getWest(), bounds.getSouth()],
      ];
    }

    // Letzter Fallback über Center-Metadaten.
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

  const effectiveCustomMapImageUrl = $derived(
    (poisData?.meta?.customMapImageUrl ?? '').trim() || (customMapImageUrl ?? '').trim()
  );

  const effectiveCustomMapImageOpacity = $derived(
    typeof poisData?.meta?.customMapImageOpacity === 'number' && Number.isFinite(poisData.meta.customMapImageOpacity)
      ? clamp(poisData.meta.customMapImageOpacity, 0, 100)
      : clamp(customMapImageOpacity, 0, 100)
  );

  function isExternalUrl(url: string): boolean {
    const candidate = (url ?? '').trim();
    if (!candidate) return false;

    try {
      const baseOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
      const resolved = new URL(candidate, baseOrigin);
      if (resolved.protocol === 'data:' || resolved.protocol === 'blob:') {
        return false;
      }

      if (typeof window === 'undefined') {
        return /^https?:\/\//i.test(candidate);
      }

      return resolved.origin !== window.location.origin;
    } catch {
      return /^https?:\/\//i.test(candidate);
    }
  }

  const usesExternalTileSources = $derived(loadBaseTiles);
  const usesExternalCustomImage = $derived(isExternalUrl(effectiveCustomMapImageUrl));
  const requiresExternalContentConsent = $derived(
    enableExternalContentBlocker && (usesExternalTileSources || usesExternalCustomImage)
  );
  const canLoadExternalMapContent = $derived(!requiresExternalContentConsent || hasExternalContentConsent);
  const localManualConsentValue = 'manual-allow-v2';

  function grantExternalContentConsent(): void {
    hasExternalContentConsent = true;

    if (!rememberExternalContentConsent) return;

    try {
      window.localStorage.setItem(externalContentConsentStorageKey, localManualConsentValue);
    } catch {
      // Ignorieren (z. B. Storage im Browser deaktiviert)
    }
  }

  function grantExternalContentConsentFromBanner(): void {
    // Banner-Consent darf die aktuelle Session freischalten,
    // aber keinen dauerhaften manuellen Override setzen.
    hasExternalContentConsent = true;
  }

  // ─── Complianz-Integration ────────────────────────────────────────────────
  function hasComplianzContext(): boolean {
    return typeof window.cmplz_has_consent === 'function'
      || document.cookie.includes('cmplz_');
  }

  function readCookieValue(name: string): string | null {
    const prefix = `${name}=`;
    for (const part of document.cookie.split(';')) {
      const cookie = part.trim();
      if (cookie.startsWith(prefix)) {
        return decodeURIComponent(cookie.slice(prefix.length));
      }
    }
    return null;
  }

  function checkComplianzConsent(): boolean {
    // Ohne Complianz-Kontext keine Freigabe durch diese Prüfung.
    if (!hasComplianzContext()) return false;

    // 1. Offizielle JS-API, falls verfügbar.
    if (typeof window.cmplz_has_consent === 'function') {
      return window.cmplz_has_consent(cookieConsentCategory);
    }

    // 2. Fallback: Complianz-Cookie direkt lesen.
    const cookieName = `cmplz_${cookieConsentCategory}`;
    return readCookieValue(cookieName) === 'allow';
  }

  async function prepareCustomImageUrl(url: string): Promise<string | null> {
    if (!url) return null;

    // Rasterbilder direkt verwenden.
    if (!/\.svg(?:$|[?#])/i.test(url)) {
      return url;
    }

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) {
        console.warn('[EventMap] SVG konnte nicht geladen werden:', response.status, url);
        return url;
      }

      const svgText = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(svgText, 'image/svg+xml');
      const svg = doc.documentElement;

      if (!svg || svg.tagName.toLowerCase() !== 'svg') {
        return url;
      }

      const hasWidth = !!svg.getAttribute('width');
      const hasHeight = !!svg.getAttribute('height');

      // Viele exportierte SVGs haben nur viewBox. MapLibre braucht verlässliche Dimensionen.
      if (!hasWidth || !hasHeight) {
        const viewBox = (svg.getAttribute('viewBox') ?? '').trim();
        const parts = viewBox.split(/[\s,]+/).map((n) => Number.parseFloat(n));
        const vbWidth = Number.isFinite(parts[2]) && parts[2] > 0 ? parts[2] : 2000;
        const vbHeight = Number.isFinite(parts[3]) && parts[3] > 0 ? parts[3] : 2000;

        if (!hasWidth) svg.setAttribute('width', String(vbWidth));
        if (!hasHeight) svg.setAttribute('height', String(vbHeight));
      }

      const serialized = new XMLSerializer().serializeToString(svg);
      const blob = new Blob([serialized], { type: 'image/svg+xml;charset=utf-8' });

      if (customImageObjectUrl) {
        URL.revokeObjectURL(customImageObjectUrl);
      }
      customImageObjectUrl = URL.createObjectURL(blob);
      return customImageObjectUrl;
    } catch (error) {
      console.warn('[EventMap] SVG-Aufbereitung fehlgeschlagen, Original-URL wird verwendet.', error);
      return url;
    }
  }

  function normalizeCustomImageUrl(url: string): string {
    if (!url) return '';

    try {
      const normalized = new URL(url, window.location.origin);
      if (window.location.protocol === 'https:' && normalized.protocol === 'http:') {
        normalized.protocol = 'https:';
      }
      return normalized.toString();
    } catch {
      // Fallback bei nicht vollständig parsebaren URLs.
      if (window.location.protocol === 'https:') {
        return url.replace(/^http:\/\//i, 'https://');
      }
      return url;
    }
  }

  function createUserLocationEl(): HTMLElement {
    const el = document.createElement('div');
    el.className = 'kuh-map-user-location';
    el.style.cssText = `
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: #2c7efc;
      border: 2px solid #ffffff;
      box-shadow: 0 0 0 4px rgba(44,126,252,0.35), 0 1px 6px rgba(0,0,0,0.35);
    `;
    return el;
  }

  function renderUserLocation() {
    if (!map) return;

    if (!legendVisibility.userLocation) {
      userLocationMarker?.remove();
      userLocationMarker = null;
      userLocationPending = false;
      return;
    }

    if (userLocationMarker || userLocationPending) {
      return;
    }

    if (!navigator.geolocation) {
      legendVisibility.userLocation = false;
      return;
    }

    userLocationPending = true;
    navigator.geolocation.getCurrentPosition(
      (position) => {
        userLocationPending = false;

        const lng = position.coords.longitude;
        const lat = position.coords.latitude;

        userLocationMarker?.remove();
        userLocationMarker = new maplibregl.Marker({ element: createUserLocationEl(), anchor: 'center' })
          .setLngLat([lng, lat])
          .addTo(map as Map);
      },
      () => {
        userLocationPending = false;
        legendVisibility.userLocation = false;
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000,
      }
    );
  }

  async function renderCustomImageLayer() {
    if (!map || !map.loaded()) return;
    if (customImageRendering) return;

    customImageRendering = true;

    try {
      const effectiveImageUrl = normalizeCustomImageUrl(effectiveCustomMapImageUrl);
      if (!effectiveImageUrl) {
        customImageRendered = false;
        return;
      }

      // await VOR dem Entfernen des alten Layers, damit bei Abbruch der Layer erhalten bleibt.
      const preparedUrl = await prepareCustomImageUrl(effectiveImageUrl);
      if (!preparedUrl) {
        customImageRendered = false;
        return;
      }

      // Karte muss noch existieren; loaded()-Check weglassen: MapLibre kann kurz
      // false zurückgeben während eines internen Style-Updates.
      if (!map) {
        customImageRendered = false;
        return;
      }

      // Erst jetzt alten Layer/Source entfernen – so bleibt bei Abbruch was da war.
      const existingLayer = map.getLayer(customImageLayerId);
      if (existingLayer) {
        map.removeLayer(customImageLayerId);
      }

      const existingSource = map.getSource(customImageSourceId);
      if (existingSource) {
        map.removeSource(customImageSourceId);
      }

      const coordinates = getImageCoordinatesFromData();

      map.addSource(customImageSourceId, {
        type: 'image',
        url: preparedUrl,
        coordinates,
      });

      // Image-Layer unterhalb der Area- und Marker-Layer einfügen.
      const beforeLayerId = map.getLayer(areaFillLayerId)
        ? areaFillLayerId
        : undefined;
      map.addLayer({
        id: customImageLayerId,
        type: 'raster',
        source: customImageSourceId,
        paint: {
          'raster-opacity': effectiveCustomMapImageOpacity / 100,
        },
      }, beforeLayerId);
      customImageRendered = true;
    } catch (error) {
      console.warn('[EventMap] Hintergrundbild konnte nicht als Layer hinzugefügt werden.', error);
      customImageRendered = false;
    } finally {
      customImageRendering = false;
    }
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
      if (
        !Array.isArray(coords)
        || coords.length < 2
        || typeof coords[0] !== 'number'
        || typeof coords[1] !== 'number'
      ) continue;

      const lng = coords[0];
      const lat = coords[1];
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
  function initializeMap() {
    if (map || !mapContainer) return;

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

    const styleSources: Record<string, any> = {};
    const styleLayers: any[] = [
      {
        id: 'kuh-map-background',
        type: 'background',
        paint: {
          'background-color': mapBackgroundColor,
        },
      },
    ];

    if (loadBaseTiles) {
      styleSources.base = {
        type: 'raster',
        tiles: baseTileUrls,
        tileSize: 256,
        attribution: tileAttribution,
        maxzoom: 19,
      };

      styleLayers.push({
        id: 'base-tiles',
        type: 'raster',
        source: 'base',
        minzoom: 0,
        maxzoom: 22,
      });
    }

    if (loadBaseTiles && useMinimalBaseMap && showStreetLabels) {
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

    if (loadBaseTiles) {
      map.addControl(
        new maplibregl.AttributionControl({ compact: true }),
        'bottom-right'
      );
    }

    map.addControl(
      new maplibregl.NavigationControl({ showCompass: false }),
      'top-right'
    );
    map.addControl(
      new maplibregl.ScaleControl({ maxWidth: 120, unit: 'metric' }),
      'bottom-left'
    );

    const renderAllMapOverlays = () => {
      void renderCustomImageLayer();
      renderAreas();
      renderMarkers();
      renderUserLocation();
    };

    const scheduleImageRenderFallback = () => {
      window.setTimeout(() => { void renderCustomImageLayer(); }, 0);
      window.setTimeout(() => { void renderCustomImageLayer(); }, 500);
      window.setTimeout(() => { void renderCustomImageLayer(); }, 1500);
    };

    // Robustes Initialisieren: je nach Timing kann 'load' bereits vorbei sein.
    renderAllMapOverlays();
    scheduleImageRenderFallback();
    map.on('load', renderAllMapOverlays);
    map.once('idle', renderAllMapOverlays);
  }

  onMount(() => {
    const hasComplianz = hasComplianzContext();
    let hasManualLocalOverride = false;

    if (rememberExternalContentConsent && typeof window !== 'undefined') {
      try {
        hasManualLocalOverride = window.localStorage.getItem(externalContentConsentStorageKey) === localManualConsentValue;
      } catch {
        hasManualLocalOverride = false;
      }
    }

    // 1. Complianz hat Vorrang: vorhandene lokale Karte-Freigaben sollen die
    //    Banner-Entscheidung nicht überschreiben.
    //    Ausnahme: expliziter manueller Karten-Override (Button "Karte laden").
    if (requiresExternalContentConsent && hasComplianz) {
      hasExternalContentConsent = checkComplianzConsent() || hasManualLocalOverride;
    } else if (hasManualLocalOverride) {
      // 2. Ohne Complianz-Kontext auf lokale Freigabe zurückfallen.
      hasExternalContentConsent = true;
    }

    // 3. Complianz-Event abhören – Fallback für den Fall, dass die Seite nach
    //    Zustimmung nicht neu geladen wird (z. B. Banner ohne Reload-Option).
    function onCmplzSetCookie() {
      if (!hasExternalContentConsent && checkComplianzConsent()) {
        grantExternalContentConsentFromBanner();
      }
    }
    document.addEventListener('cmplz_set_cookie', onCmplzSetCookie);

    initialConsentChecked = true;

    if (canLoadExternalMapContent) {
      initializeMap();
    }

    return () => {
      document.removeEventListener('cmplz_set_cookie', onCmplzSetCookie);
      markers.forEach((m) => m.remove());
      userLocationMarker?.remove();
      userLocationMarker = null;
      if (customImageObjectUrl) {
        URL.revokeObjectURL(customImageObjectUrl);
        customImageObjectUrl = null;
      }
      map?.remove();
      map = null;
    };
  });

  $effect(() => {
    if (!initialConsentChecked) return;
    if (canLoadExternalMapContent && !map) {
      initializeMap();
    }
  });

  onDestroy(() => {
    // Cleanup erfolgt über den onMount-Rückgabewert.
  });

  // Marker neu rendern wenn Sichtbarkeits-Props sich ändern
  $effect(() => {
    // Reaktivität auf alle show-Props
    void showLocations; void showEntrances; void showStages;
    void showParking; void showToilets; void showInfo;
    void effectiveCustomMapImageUrl; void effectiveCustomMapImageOpacity;
    void poisData?.meta?.imageBounds;
    if (map?.loaded()) {
      void renderCustomImageLayer();
      renderAreas();
      renderMarkers();
      renderUserLocation();
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
    {
      key: 'userLocation',
      label: 'Meine Position',
      emoji: '📍',
      color: '#2c7efc',
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

<section class="w-full" style="font-family: var(--font-body, 'Inter', sans-serif);">
  <!-- Kopfzeile -->
  {#if title}
    <div class="pb-2 text-center">
      <h2
        class="m-0 mb-2 text-[clamp(2rem,5vw,3rem)] leading-[1.1]"
        style="font-family: var(--font-headline, serif); color: var(--color-primary, #011e08);"
      >{title}</h2>
      {#if subtitle}
        <p
          class="m-0 text-base italic"
          style="font-family: var(--font-serif-italic, serif); color: var(--color-outline, #737971);"
        >{subtitle}</p>
      {/if}
    </div>
  {/if}

  <div class="w-full shadow-[0_0_0_2px_var(--color-secondary,#725c0c),0_4px_16px_rgba(0,0,0,0.25)] sm:shadow-[0_0_0_3px_var(--color-secondary,#725c0c),0_0_0_6px_var(--color-primary,#011e08),0_8px_32px_rgba(0,0,0,0.35)]">
    <!-- Karten-Wrapper mit Vintage-Filter -->
    <div
      class="kuh-event-map-wrapper relative w-full overflow-hidden rounded-none"
      style="height: {mapHeight}px; --kuh-event-map-mobile-height: {mobileMapHeight}px;"
    >
      <div
        bind:this={mapContainer}
        class="kuh-event-map-canvas relative z-1 h-full w-full filter-none"
        class:kuh-event-map-canvas--minimal={useMinimalBaseMap}
      ></div>

      {#if requiresExternalContentConsent && !hasExternalContentConsent}
        <div class="absolute inset-0 z-20 flex items-center justify-center bg-[rgba(11,22,13,0.78)] p-4 text-center text-white backdrop-blur-[1px]">
          <div class="max-w-136 rounded-md border border-[rgba(255,255,255,0.3)] bg-[rgba(0,0,0,0.35)] px-5 py-4 shadow-[0_10px_30px_rgba(0,0,0,0.35)]">
            <h3 class="m-0 text-lg font-bold">{externalContentBlockerTitle}</h3>
            <p class="mb-3 mt-2 text-sm leading-6 text-[rgba(255,255,255,0.92)]">{externalContentBlockerText}</p>

            <!-- Datenschutz-Hinweise -->
            <p class="mb-4 text-xs leading-5 text-[rgba(255,255,255,0.7)]">
              {#if loadBaseTiles}
                Externe Anbieter:
                <a
                  href="https://wiki.osmfoundation.org/wiki/Privacy_Policy"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="underline hover:text-white"
                >OpenStreetMap</a>{#if useMinimalBaseMap},
                <a
                  href="https://carto.com/privacy"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="underline hover:text-white"
                >CARTO</a>{/if}.
              {/if}
              {#if privacyPolicyUrl}
                {' '}Unsere
                <a
                  href={privacyPolicyUrl}
                  class="underline hover:text-white"
                >{privacyPolicyLabel}</a>.
              {/if}
            </p>

            <button
              type="button"
              class="rounded-sm border border-[rgba(255,255,255,0.7)] bg-white/95 px-4 py-2 text-sm font-semibold text-[#0b2313] transition-colors duration-200 hover:bg-white"
              onclick={grantExternalContentConsent}
            >
              {externalContentButtonLabel}
            </button>
          </div>
        </div>
      {/if}
    </div>

    <!-- Legende -->
    {#if showLegend && legendItems.length > 0}
      <div
        class="flex flex-wrap items-center gap-y-2 gap-x-5 border border-outline-variant border-t-0 bg-surface-container-low px-4 py-3 text-[0.75rem] text-on-surface sm:gap-y-2 sm:gap-x-3 sm:px-6 sm:py-4 sm:text-[0.8rem]"
        aria-label="Kartenlegende"
      >
        <span class="mr-1 text-[0.7rem] font-bold uppercase tracking-[0.08em] text-outline">Legende:</span>
        {#each legendItems as item}
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface-container-lowest px-2.5 py-1 font-inherit text-on-surface transition-[opacity,filter,transform] duration-200 ease-in-out hover:brightness-[0.98] active:scale-[0.98] {legendVisibility[item.key as keyof typeof legendVisibility] ? 'opacity-100' : 'opacity-45'}"
            onclick={() => toggleLegendItem(item.key as keyof typeof legendVisibility)}
            aria-pressed={legendVisibility[item.key as keyof typeof legendVisibility]}
          >
            <span class="inline-flex h-6 w-6 shrink-0 -rotate-45 items-center justify-center rounded-[50%_50%_50%_0] text-[11px]" style="background:{item.color};">
              <span class="inline-block rotate-45 leading-none">{item.emoji}</span>
            </span>
            {item.label}
          </button>
        {/each}
      </div>
    {/if}
  </div>
</section>

<style>
  .kuh-event-map-canvas--minimal {
    /* No-Labels-Basiskarte kontrastreicher statt ausgewaschen */
    filter: saturate(1.08) contrast(1.1) brightness(0.92);
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
    .kuh-event-map-wrapper {
      height: var(--kuh-event-map-mobile-height, 420px) !important;
    }
  }
</style>
