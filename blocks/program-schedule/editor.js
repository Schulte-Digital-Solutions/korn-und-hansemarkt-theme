/**
 * Gutenberg Editor-Script für den Bühnenplan-Block.
 *
 * Die Programmdaten liegen in den CPTs „Acts" und „Programmpunkte" – der Block
 * selbst hält nur noch Darstellungsoptionen. Im Editor wird deshalb eine
 * Zusammenfassung der vorhandenen Daten statt eines Editierformulars gezeigt.
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

const VIEW_OPTIONS = [
  { label: 'Zeitraster (Bühnen nebeneinander)', value: 'grid' },
  { label: 'Liste (chronologisch)', value: 'list' },
];

registerBlockType('kuh/program-schedule', {
  edit({ attributes, setAttributes }) {
    const { title, showTitle, titleFont, defaultView, showNowMarker, showActPanel, pixelsPerHour } = attributes;
    const [program, setProgram] = useState(null);
    const [error, setError] = useState(null);

    useEffect(function () {
      apiFetch({ path: '/kuh/v1/program' })
        .then(setProgram)
        .catch(function (e) { setError(e && e.message ? e.message : 'Programmdaten konnten nicht geladen werden.'); });
    }, []);

    const blockProps = useBlockProps({
      style: {
        background: '#f5f3f3',
        border: '1px solid #c2c8bf',
        borderRadius: '0.5rem',
        padding: '1.5rem',
      },
    });

    const days = (program && program.days) || [];
    const slotCount = days.reduce(function (sum, d) {
      return sum + ((d.slots && d.slots.length) || 0);
    }, 0);
    const acts = (program && program.acts) || [];

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
          el(SelectControl, {
            label: 'Standardansicht (Desktop)',
            value: defaultView,
            options: VIEW_OPTIONS,
            help: 'Auf Mobilgeräten wird immer die Listenansicht pro Bühne verwendet.',
            onChange: function (val) { setAttributes({ defaultView: val }); },
          }),
          el(RangeControl, {
            label: 'Höhe pro Stunde (px)',
            value: pixelsPerHour,
            min: 60,
            max: 400,
            step: 10,
            help: 'Bestimmt, wie hoch das Zeitraster gezeichnet wird.',
            onChange: function (val) { setAttributes({ pixelsPerHour: val }); },
          }),
          el(ToggleControl, {
            label: '„Jetzt"-Markierung anzeigen',
            checked: showNowMarker,
            help: 'Zeigt während des Marktes eine Linie an der aktuellen Uhrzeit.',
            onChange: function (val) { setAttributes({ showNowMarker: val }); },
          }),
          el(ToggleControl, {
            label: 'Act-Detailpanel',
            checked: showActPanel,
            help: 'Klick auf einen Act zeigt alle seine Auftritte.',
            onChange: function (val) { setAttributes({ showActPanel: val }); },
          })
        )
      ),

      el('div', { style: { display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' } },
        el('span', { className: 'dashicons dashicons-schedule', style: { color: '#15331b' } }),
        el('strong', { style: { fontSize: '1rem', color: '#011e08' } }, title || 'Bühnenplan')
      ),

      error && el(Notice, { status: 'error', isDismissible: false }, error),

      !program && !error && el('p', null, el(Spinner), ' Programmdaten werden geladen …'),

      program && days.length === 0 && el(
        Notice,
        { status: 'warning', isDismissible: false },
        'Noch keine Programmpunkte angelegt. Lege sie unter Programm → Programmpunkte an oder starte den Import unter Programm → Import.'
      ),

      program && days.length > 0 && el(
        Fragment,
        null,
        el('p', { style: { margin: '0 0 0.75rem', color: '#424940' } },
          days.length + ' Tage · ' + slotCount + ' Programmpunkte · ' + acts.length + ' Acts'
        ),
        el('ul', { style: { margin: 0, paddingLeft: '1.25rem', color: '#424940' } },
          days.map(function (day) {
            return el('li', { key: day.date },
              el('strong', null, day.label + ', ' + day.dateLabel),
              ' – ' + ((day.stages || []).map(function (s) { return s.name; }).join(', ') || 'ohne Bühne')
            );
          })
        ),
        el('p', { style: { marginBottom: 0, fontStyle: 'italic', color: '#737971' } },
          'Inhalte werden unter Programm → Programmpunkte gepflegt, nicht in diesem Block.'
        )
      )
    );
  },

  save() {
    return null;
  },
});
})();
