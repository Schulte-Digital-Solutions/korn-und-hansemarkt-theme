/**
 * Gutenberg Editor-Script für die Acts-Übersicht.
 *
 * Die Inhalte kommen aus dem CPT „Acts"; der Block hält nur Darstellungsoptionen.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl, SelectControl, RangeControl, Spinner, Notice } = wp.components;
const { createElement: el, Fragment, useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

const FONT_OPTIONS = [
  { label: 'Manuskript Gotisch (Headline)', value: 'headline' },
  { label: 'Inter (Body)', value: 'body' },
  { label: 'Newsreader (Serif)', value: 'serif-italic' },
];

registerBlockType('kuh/act-overview', {
  edit({ attributes, setAttributes }) {
    const { title, showTitle, titleFont, cardMinWidth, showSearch, showShows, hideWithoutShows } = attributes;
    const [acts, setActs] = useState(null);
    const [error, setError] = useState(null);

    useEffect(function () {
      apiFetch({ path: '/kuh/v1/acts' })
        .then(setActs)
        .catch(function (e) { setError(e && e.message ? e.message : 'Acts konnten nicht geladen werden.'); });
    }, []);

    const blockProps = useBlockProps({
      style: {
        background: '#f5f3f3',
        border: '1px solid #c2c8bf',
        borderRadius: '0.5rem',
        padding: '1.5rem',
      },
    });

    const list = Array.isArray(acts) ? acts : [];
    const withImage = list.filter(function (a) { return a.image; }).length;
    const withText = list.filter(function (a) { return a.excerpt; }).length;

    return el(
      'div',
      blockProps,
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Darstellung', initialOpen: true },
          el(ToggleControl, {
            label: 'Überschrift anzeigen',
            checked: showTitle,
            onChange: function (val) { setAttributes({ showTitle: val }); },
          }),
          showTitle && el(TextControl, {
            label: 'Überschrift',
            value: title,
            onChange: function (val) { setAttributes({ title: val }); },
          }),
          el(SelectControl, {
            label: 'Schrift der Überschrift',
            value: titleFont,
            options: FONT_OPTIONS,
            onChange: function (val) { setAttributes({ titleFont: val }); },
          }),
          el(RangeControl, {
            label: 'Minimale Kartenbreite (px)',
            help: 'Die Spaltenanzahl passt sich automatisch an den verfügbaren Platz an.',
            value: cardMinWidth,
            min: 160,
            max: 480,
            step: 10,
            onChange: function (val) { setAttributes({ cardMinWidth: val }); },
          }),
          el(ToggleControl, {
            label: 'Suchfeld anzeigen',
            checked: showSearch,
            onChange: function (val) { setAttributes({ showSearch: val }); },
          }),
          el(ToggleControl, {
            label: 'Auftrittszeiten auf der Karte anzeigen',
            checked: showShows,
            onChange: function (val) { setAttributes({ showShows: val }); },
          }),
          el(ToggleControl, {
            label: 'Acts ohne Auftritt ausblenden',
            checked: hideWithoutShows,
            help: 'Blendet Acts aus, die noch keinem Programmpunkt zugeordnet sind.',
            onChange: function (val) { setAttributes({ hideWithoutShows: val }); },
          })
        )
      ),

      el('div', { style: { display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' } },
        el('span', { className: 'dashicons dashicons-groups', style: { color: '#15331b' } }),
        el('strong', { style: { fontSize: '1rem', color: '#011e08' } }, title || 'Acts-Übersicht')
      ),

      error && el(Notice, { status: 'error', isDismissible: false }, error),
      !acts && !error && el('p', null, el(Spinner), ' Acts werden geladen …'),

      acts && list.length === 0 && el(
        Notice,
        { status: 'warning', isDismissible: false },
        'Noch keine Acts angelegt. Lege sie unter Programm → Acts an.'
      ),

      acts && list.length > 0 && el(
        Fragment,
        null,
        el('p', { style: { margin: '0 0 0.75rem', color: '#424940' } },
          list.length + ' Acts · ' + withImage + ' mit Bild · ' + withText + ' mit Beschreibung'
        ),
        (withImage < list.length || withText < list.length) && el(
          Notice,
          { status: 'info', isDismissible: false },
          'Bild und Beschreibung werden je Act unter Programm → Acts gepflegt (Beitragsbild, Textbereich, Textauszug).'
        )
      )
    );
  },

  save() {
    return null;
  },
});
})();
