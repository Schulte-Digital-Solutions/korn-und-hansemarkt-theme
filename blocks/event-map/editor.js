/**
 * Gutenberg Editor-Script für den Event-Map Block.
 *
 * Einfacher Editor mit Sidebar-Controls für Sichtbarkeit
 * der verschiedenen POI-Kategorien.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl, RangeControl } = wp.components;
const { createElement: el, Fragment } = wp.element;

registerBlockType('kuh/event-map', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'kuh-event-map-editor' });
    const {
      title,
      subtitle,
      mapHeight,
      useMinimalBaseMap,
      showStreetLabels,
      areaFillColor,
      areaFillOpacity,
      areaLineColor,
      locationColor,
      entranceColor,
      stageColor,
      parkingColor,
      toiletColor,
      infoColor,
      showLocations,
      showEntrances,
      showStages,
      showParking,
      showToilets,
      showInfo,
      showLegend,
    } = attributes;

    return el(
      Fragment,
      null,

      // Sidebar
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Beschriftung', initialOpen: true },
          el(TextControl, {
            label: 'Titel',
            value: title,
            onChange: (v) => setAttributes({ title: v }),
          }),
          el(TextControl, {
            label: 'Untertitel',
            value: subtitle,
            onChange: (v) => setAttributes({ subtitle: v }),
          })
        ),
        el(
          PanelBody,
          { title: 'Karte', initialOpen: false },
          el(ToggleControl, {
            label: 'Basiskarte ohne Orts-POIs/Labels nutzen',
            checked: useMinimalBaseMap,
            onChange: (v) => setAttributes({ useMinimalBaseMap: v }),
          }),
          el(ToggleControl, {
            label: 'Straßennamen auf der Basiskarte anzeigen',
            checked: showStreetLabels,
            onChange: (v) => setAttributes({ showStreetLabels: v }),
            help: 'Funktioniert besonders mit der No-POI-Basiskarte, um Orientierung zu verbessern.',
          }),
          el(RangeControl, {
            label: 'Kartenhöhe (px)',
            value: mapHeight,
            onChange: (v) => setAttributes({ mapHeight: v }),
            min: 300,
            max: 900,
            step: 20,
          })
        ),
        el(
          PanelBody,
          { title: 'Sichtbare Orte', initialOpen: true },
          el(ToggleControl, {
            label: 'Benannte Orte (Bödiker Oberschulte, Heyt, Rosche)',
            checked: showLocations,
            onChange: (v) => setAttributes({ showLocations: v }),
          }),
          el(ToggleControl, {
            label: 'Eingänge',
            checked: showEntrances,
            onChange: (v) => setAttributes({ showEntrances: v }),
          }),
          el(ToggleControl, {
            label: 'Bühnen',
            checked: showStages,
            onChange: (v) => setAttributes({ showStages: v }),
          }),
          el(ToggleControl, {
            label: 'Parkplätze',
            checked: showParking,
            onChange: (v) => setAttributes({ showParking: v }),
          }),
          el(ToggleControl, {
            label: 'Toiletten',
            checked: showToilets,
            onChange: (v) => setAttributes({ showToilets: v }),
          }),
          el(ToggleControl, {
            label: 'Information & Erste Hilfe',
            checked: showInfo,
            onChange: (v) => setAttributes({ showInfo: v }),
          })
        ),
        el(
          PanelBody,
          { title: 'Legende', initialOpen: false },
          el(ToggleControl, {
            label: 'Legende anzeigen',
            checked: showLegend,
            onChange: (v) => setAttributes({ showLegend: v }),
          })
        ),
        el(
          PanelBody,
          { title: 'Farben', initialOpen: false },
          el(TextControl, {
            label: 'Marktfläche Füllfarbe (Hex)',
            value: areaFillColor,
            onChange: (v) => setAttributes({ areaFillColor: v }),
          }),
          el(RangeControl, {
            label: 'Marktfläche Deckkraft (%)',
            value: areaFillOpacity,
            onChange: (v) => setAttributes({ areaFillOpacity: v }),
            min: 0,
            max: 100,
            step: 1,
          }),
          el(TextControl, {
            label: 'Marktfläche Linienfarbe (Hex)',
            value: areaLineColor,
            onChange: (v) => setAttributes({ areaLineColor: v }),
          }),
          el(TextControl, {
            label: 'Benannte Orte Farbe (Hex)',
            value: locationColor,
            onChange: (v) => setAttributes({ locationColor: v }),
          }),
          el(TextControl, {
            label: 'Eingänge Farbe (Hex)',
            value: entranceColor,
            onChange: (v) => setAttributes({ entranceColor: v }),
          }),
          el(TextControl, {
            label: 'Bühnen Farbe (Hex)',
            value: stageColor,
            onChange: (v) => setAttributes({ stageColor: v }),
          }),
          el(TextControl, {
            label: 'Parkplätze Farbe (Hex)',
            value: parkingColor,
            onChange: (v) => setAttributes({ parkingColor: v }),
          }),
          el(TextControl, {
            label: 'Toiletten Farbe (Hex)',
            value: toiletColor,
            onChange: (v) => setAttributes({ toiletColor: v }),
          }),
          el(TextControl, {
            label: 'Info & Hilfe Farbe (Hex)',
            value: infoColor,
            onChange: (v) => setAttributes({ infoColor: v }),
          })
        )
      ),

      // Editor-Vorschau
      el(
        'div',
        blockProps,
        el(
          'div',
          {
            style: {
              background: '#f5f1e8',
              border: '2px dashed #c4a96a',
              borderRadius: '8px',
              padding: '2rem',
              textAlign: 'center',
              minHeight: '200px',
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '0.5rem',
            },
          },
          el('span', { style: { fontSize: '2rem' } }, '🗺️'),
          el(
            'strong',
            { style: { fontSize: '1.125rem', color: '#011e08' } },
            title || 'Geländeplan'
          ),
          el(
            'p',
            { style: { fontSize: '0.875rem', color: '#555', margin: 0 } },
            'Interaktive Karte wird im Frontend angezeigt'
          ),
          el(
            'p',
            { style: { fontSize: '0.75rem', color: '#888', margin: 0 } },
            [
              useMinimalBaseMap && 'No-POI-Karte',
              showLocations && 'Orte',
              showEntrances && 'Eingänge',
              showStages && 'Bühnen',
              showParking && 'Parkplätze',
              showToilets && 'WC',
              showInfo && 'Info',
            ]
              .filter(Boolean)
              .join(' · ')
          )
        )
      )
    );
  },

  save() {
    return null; // Dynamic block, rendered server-side
  },
});
})();
