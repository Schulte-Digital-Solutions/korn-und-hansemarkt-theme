/**
 * Gutenberg Editor-Script für den Programm-Zeitplan Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, TextareaControl, SelectControl, Button, BaseControl } = wp.components;
const { createElement: el, Fragment, useState } = wp.element;

const EVENT_TYPES = [
  { label: '⭐ Haupt-Event', value: 'main' },
  { label: '📌 Neben-Event', value: 'side' },
  { label: '🔁 Wiederkehrend', value: 'recurring' },
];

const FONT_OPTIONS = [
  { label: 'Manuskript Gotisch (Headline)', value: 'headline' },
  { label: 'Inter (Body)', value: 'body' },
  { label: 'Newsreader (Serif)', value: 'serif-italic' },
];

const TYPE_COLORS = {
  main: '#15331b',
  side: '#725c0c',
  recurring: '#466649',
};

const TYPE_LABELS = {
  main: '⭐ Haupt',
  side: '📌 Neben',
  recurring: '🔁 Wiederkehrend',
};

function toSlug(text) {
  return text.toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function formatDateDE(isoStr) {
  if (!isoStr) return '';
  const d = new Date(isoStr + 'T00:00:00');
  return d.toLocaleDateString('de-DE', { day: 'numeric', month: 'long' });
}

function newEvent(type) {
  const base = { type, time: '', title: '', location: '', locationSlug: '', icon: 'location_on' };
  if (type === 'recurring') return { ...base, timeEnd: '', times: '', interval: '' };
  return { ...base, description: '' };
}

registerBlockType('kuh/program-schedule', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { days, titleFont } = attributes;
    const [activeDay, setActiveDay] = useState(0);

    function updateDay(dayIndex, field, value) {
      const updated = days.map((day, i) =>
        i === dayIndex ? { ...day, [field]: value } : day
      );
      setAttributes({ days: updated });
    }

    function updateEvent(dayIndex, eventIndex, field, value) {
      const updated = days.map((day, di) => {
        if (di !== dayIndex) return day;
        return {
          ...day,
          events: day.events.map((ev, ei) =>
            ei === eventIndex ? { ...ev, [field]: value } : ev
          ),
        };
      });
      setAttributes({ days: updated });
    }

    function addEvent(dayIndex, type) {
      const updated = days.map((day, i) => {
        if (i !== dayIndex) return day;
        return { ...day, events: [...day.events, newEvent(type)] };
      });
      setAttributes({ days: updated });
    }

    function removeEvent(dayIndex, eventIndex) {
      const updated = days.map((day, i) => {
        if (i !== dayIndex) return day;
        return { ...day, events: day.events.filter((_, ei) => ei !== eventIndex) };
      });
      setAttributes({ days: updated });
    }

    function moveEvent(dayIndex, eventIndex, direction) {
      const updated = days.map((day, i) => {
        if (i !== dayIndex) return day;
        const events = [...day.events];
        const newIndex = eventIndex + direction;
        if (newIndex < 0 || newIndex >= events.length) return day;
        [events[eventIndex], events[newIndex]] = [events[newIndex], events[eventIndex]];
        return { ...day, events };
      });
      setAttributes({ days: updated });
    }

    function addDay() {
      setAttributes({
        days: [...days, { label: 'Neuer Tag', date: '', events: [] }],
      });
    }

    function removeDay(index) {
      setAttributes({ days: days.filter((_, i) => i !== index) });
      if (activeDay >= days.length - 1) setActiveDay(Math.max(0, days.length - 2));
    }

    function renderEventFields(ev, di, ei) {
      const evType = ev.type || 'main';
      const fields = [
        el(SelectControl, {
          label: 'Event-Typ',
          value: evType,
          options: EVENT_TYPES,
          onChange: (v) => updateEvent(di, ei, 'type', v),
        }),
        el(TextControl, { label: 'Uhrzeit', value: ev.time, onChange: (v) => updateEvent(di, ei, 'time', v) }),
      ];

      if (evType === 'recurring') {
        fields.push(
          el(TextControl, { label: 'Ende (Uhrzeit)', value: ev.timeEnd || '', onChange: (v) => updateEvent(di, ei, 'timeEnd', v), help: 'Für Zeitraum, z.B. 22:00' }),
          el(TextControl, { label: 'Feste Uhrzeiten', value: ev.times || '', onChange: (v) => updateEvent(di, ei, 'times', v), help: 'Komma-getrennt, z.B. 11:00, 14:00, 17:00 (ersetzt Von–Bis)' }),
          el(TextControl, { label: 'Intervall-Text', value: ev.interval || '', onChange: (v) => updateEvent(di, ei, 'interval', v), help: 'z.B. "stündlich", "3× täglich", "alle 30 Min."' })
        );
      }

      fields.push(
        el(TextControl, { label: 'Titel', value: ev.title, onChange: (v) => updateEvent(di, ei, 'title', v) })
      );

      if (evType !== 'recurring') {
        fields.push(
          el(TextareaControl, { label: 'Beschreibung', value: ev.description || '', onChange: (v) => updateEvent(di, ei, 'description', v), rows: 2 })
        );
      }

      fields.push(
        el(TextControl, { label: 'Ort', value: ev.location || '', onChange: (v) => updateEvent(di, ei, 'location', v) }),
        el(TextControl, {
          label: 'Ort-Slug (für Karte)',
          value: ev.locationSlug || '',
          onChange: (v) => updateEvent(di, ei, 'locationSlug', v),
          help: 'URL-Slug für die Karten-Verlinkung. Leer = automatisch aus Ort generiert' + (ev.location && !ev.locationSlug ? ' → ' + toSlug(ev.location) : ''),
        }),
        el(TextControl, {
          label: 'Icon (Material Symbol)',
          value: ev.icon || 'location_on',
          onChange: (v) => updateEvent(di, ei, 'icon', v),
          help: 'z.B. location_on, theater_comedy, notifications, church, storefront',
        }),
        el(Button, { isDestructive: true, variant: 'link', onClick: () => removeEvent(di, ei) }, 'Event entfernen')
      );

      return fields;
    }

    return el(
      Fragment,
      null,
      el(
        InspectorControls,
        null,
        // Global settings
        el(
          PanelBody,
          { title: 'Darstellung', initialOpen: true },
          el(SelectControl, {
            label: 'Titel-Schriftart',
            value: titleFont,
            options: FONT_OPTIONS,
            onChange: (v) => setAttributes({ titleFont: v }),
          })
        ),
        // Day panels
        days.map((day, di) =>
          el(
            PanelBody,
            { key: di, title: day.label || 'Tag ' + (di + 1), initialOpen: di === activeDay },
            el(TextControl, { label: 'Bezeichnung', value: day.label, onChange: (v) => updateDay(di, 'label', v) }),
            el(BaseControl, { label: 'Datum', __nextHasNoMarginBottom: true },
              el('input', { type: 'date', value: day.date || '', onChange: (e) => updateDay(di, 'date', e.target.value), style: { width: '100%', padding: '6px 8px', border: '1px solid #8c8f94', borderRadius: '4px' } })
            ),
            el('hr'),
            el('h4', { style: { fontWeight: 'bold', marginBottom: '8px' } }, 'Events (' + day.events.length + ')'),
            day.events.map((ev, ei) => {
              const evType = ev.type || 'main';
              return el(
                'div',
                {
                  key: ei,
                  style: {
                    marginBottom: '16px',
                    padding: '12px',
                    background: '#f5f5f5',
                    borderRadius: '6px',
                    borderLeft: '4px solid ' + (TYPE_COLORS[evType] || '#999'),
                  },
                },
                el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
                  el('strong', { style: { fontSize: '12px' } },
                    (TYPE_LABELS[evType] || '') + '  ' + (ev.time || '??:??') + ' – ' + (ev.title || 'Ohne Titel')
                  ),
                  el('div', { style: { display: 'flex', gap: '4px' } },
                    el(Button, { icon: 'arrow-up', size: 'small', disabled: ei === 0, onClick: () => moveEvent(di, ei, -1) }),
                    el(Button, { icon: 'arrow-down', size: 'small', disabled: ei === day.events.length - 1, onClick: () => moveEvent(di, ei, 1) })
                  )
                ),
                ...renderEventFields(ev, di, ei)
              );
            }),
            el('div', { style: { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap' } },
              el(Button, { variant: 'primary', onClick: () => addEvent(di, 'main'), style: { fontSize: '12px' } }, '+ Haupt-Event'),
              el(Button, { variant: 'secondary', onClick: () => addEvent(di, 'side'), style: { fontSize: '12px' } }, '+ Neben-Event'),
              el(Button, { variant: 'tertiary', onClick: () => addEvent(di, 'recurring'), style: { fontSize: '12px' } }, '+ Wiederkehrend')
            ),
            el('hr'),
            el(Button, { isDestructive: true, variant: 'secondary', onClick: () => removeDay(di) }, 'Tag entfernen')
          )
        ),
        el(PanelBody, { title: 'Tage verwalten', initialOpen: false },
          el(Button, { variant: 'primary', onClick: addDay }, '+ Tag hinzufügen')
        )
      ),
      // Block Preview
      el(
        'div',
        blockProps,
        // Day Tabs – rounded style like frontend
        el(
          'div',
          { style: { display: 'flex', gap: '10px', marginBottom: '24px', overflowX: 'auto', padding: '8px 16px' } },
          days.map((day, i) =>
            el(
              'button',
              {
                key: i,
                onClick: () => setActiveDay(i),
                style: {
                  padding: '10px 20px',
                  border: 'none',
                  cursor: 'pointer',
                  borderRadius: '12px',
                  background: activeDay === i ? '#1a1c1a' : '#f3f1ee',
                  color: activeDay === i ? '#fff' : '#1b1c1c99',
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'flex-start',
                  transition: 'all 0.15s ease',
                  boxShadow: activeDay === i ? '0 1px 3px rgba(0,0,0,0.12)' : 'none',
                  fontWeight: activeDay === i ? 'bold' : 'normal',
                  flexShrink: 0,
                },
              },
              el('span', { style: { fontSize: '10px', textTransform: 'uppercase', letterSpacing: '0.05em', opacity: 0.7 } }, day.label),
              el('span', { style: { fontSize: '13px', marginTop: '2px' } }, formatDateDE(day.date) || day.date)
            )
          )
        ),
        // Events preview – card layout matching frontend
        el(
          'div',
          { style: { display: 'flex', flexDirection: 'column', gap: '10px', maxWidth: '720px', margin: '0 auto', padding: '0 16px' } },
          (days[activeDay]?.events || []).map((ev, i) => {
            const evType = ev.type || 'main';
            const isRecurring = evType === 'recurring';
            const isMain = evType === 'main';
            const isSide = evType === 'side';

            if (isMain) {
              // Haupt-Event: prominent card
              return el(
                'div',
                {
                  key: i,
                  style: {
                    display: 'flex',
                    gap: '18px',
                    padding: '22px',
                    background: '#fff',
                    borderRadius: '16px',
                    border: '1px solid rgba(194,200,191,0.1)',
                    boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                    alignItems: 'flex-start',
                  },
                },
                el('div', { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', flexShrink: 0 } },
                  el('span', { style: { fontSize: '20px', fontWeight: 'bold', color: '#15331b', letterSpacing: '-0.03em' } }, ev.time || '??:??'),
                  i < (days[activeDay]?.events?.length ?? 0) - 1
                    ? el('div', { style: { width: '1px', height: '36px', background: 'rgba(194,200,191,0.3)', margin: '6px 0' } })
                    : null
                ),
                el('div', { style: { flex: 1, minWidth: 0 } },
                  el('h4', { style: { fontSize: '20px', fontWeight: 'bold', color: '#15331b', lineHeight: '1.2', marginBottom: '4px' } }, ev.title || 'Ohne Titel'),
                  ev.description
                    ? el('p', { style: { color: 'rgba(27,28,28,0.7)', fontSize: '13px', lineHeight: '1.5', marginTop: '4px' } }, ev.description)
                    : null,
                  ev.location
                    ? el('div', { style: { marginTop: '10px', display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#725c0c', cursor: 'pointer' } },
                        el('span', { style: { fontSize: '14px' } }, '📍'),
                        el('span', { style: { fontSize: '11px', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em', textDecoration: 'underline', textDecorationStyle: 'dotted', textUnderlineOffset: '3px' } }, ev.location)
                      )
                    : null
                )
              );
            }

            if (isSide) {
              // Neben-Event: compact
              return el(
                'div',
                {
                  key: i,
                  style: {
                    display: 'flex',
                    gap: '14px',
                    padding: '14px 18px',
                    background: '#f3f1ee',
                    borderRadius: '12px',
                    alignItems: 'flex-start',
                  },
                },
                el('span', { style: { fontSize: '14px', fontWeight: 'bold', color: 'rgba(27,28,28,0.6)', letterSpacing: '-0.03em', flexShrink: 0 } }, ev.time || '??:??'),
                el('div', { style: { flex: 1, minWidth: 0 } },
                  el('h4', { style: { fontSize: '14px', fontWeight: '600', color: '#1b1c1c', lineHeight: '1.3' } }, ev.title || 'Ohne Titel'),
                  ev.description
                    ? el('p', { style: { color: 'rgba(27,28,28,0.5)', fontSize: '13px', marginTop: '2px' } }, ev.description)
                    : null,
                  ev.location
                    ? el('div', { style: { marginTop: '6px', display: 'inline-flex', alignItems: 'center', gap: '3px', color: 'rgba(114,92,12,0.7)', cursor: 'pointer' } },
                        el('span', { style: { fontSize: '13px' } }, '📍'),
                        el('span', { style: { fontSize: '10px', fontWeight: '500', textTransform: 'uppercase', letterSpacing: '0.06em', textDecoration: 'underline', textDecorationStyle: 'dotted', textUnderlineOffset: '3px' } }, ev.location)
                      )
                    : null
                )
              );
            }

            // Recurring event: green accent
            return el(
              'div',
              {
                key: i,
                style: {
                  display: 'flex',
                  gap: '14px',
                  padding: '12px 18px',
                  background: 'rgba(70,102,73,0.04)',
                  borderRadius: '12px',
                  border: '1px solid rgba(70,102,73,0.12)',
                  alignItems: 'center',
                },
              },
              el('div', { style: { flexShrink: 0, textAlign: 'center', minWidth: '50px' } },
                ev.times
                  ? el('div', { style: { display: 'flex', flexDirection: 'column', gap: '2px' } },
                      ...ev.times.split(',').map(function(t){ return t.trim(); }).filter(Boolean).map(function(t, ti) {
                        return el('span', { key: ti, style: { fontSize: '11px', fontWeight: 'bold', color: '#15331b', background: 'rgba(70,102,73,0.1)', padding: '2px 6px', borderRadius: '4px' } }, t);
                      })
                    )
                  : el('span', null,
                      el('span', { style: { fontSize: '13px', fontWeight: 'bold', color: '#15331b' } }, ev.time || '??:??'),
                      ev.timeEnd
                        ? el('span', { style: { fontSize: '11px', color: '#666', display: 'block' } }, '– ' + ev.timeEnd)
                        : null
                    )
              ),
              el('div', { style: { flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: '3px' } },
                el('span', { style: { fontSize: '13px', fontWeight: '600', color: '#1b1c1c' } }, ev.title || 'Ohne Titel'),
                el('span', { style: { alignSelf: 'flex-start', fontSize: '9px', fontWeight: 'bold', textTransform: 'uppercase', letterSpacing: '0.05em', background: 'rgba(70,102,73,0.8)', color: '#fff', padding: '2px 8px', borderRadius: '4px' } }, ev.interval || 'Wiederkehrend'),
                ev.location
                  ? el('div', { style: { display: 'inline-flex', alignItems: 'center', gap: '3px', color: 'rgba(114,92,12,0.6)', cursor: 'pointer' } },
                      el('span', { style: { fontSize: '12px' } }, '📍'),
                      el('span', { style: { fontSize: '10px', fontWeight: '500', textTransform: 'uppercase', letterSpacing: '0.06em', textDecoration: 'underline', textDecorationStyle: 'dotted', textUnderlineOffset: '3px' } }, ev.location)
                    )
                  : null
              )
            );
          })
        ),
        (days[activeDay]?.events || []).length === 0
          ? el('p', { style: { color: '#999', padding: '2rem 0', textAlign: 'center' } }, 'Noch keine Events. Füge Events in der Seitenleiste hinzu.')
          : null
      )
    );
  },
  save() {
    return null;
  },
});
})();
