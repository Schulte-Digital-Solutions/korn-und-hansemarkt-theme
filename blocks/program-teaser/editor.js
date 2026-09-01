/**
 * Gutenberg Editor-Script für den Programm-Teaser Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls, InnerBlocks } = wp.blockEditor;
const { PanelBody, TextControl, SelectControl, Button } = wp.components;
const { createElement: el, Fragment, useState } = wp.element;

const INNER_BLOCKS_TEMPLATE = [
  ['core/paragraph', { placeholder: 'z.B. Link zum vollen Programm', align: 'left' }],
];

/**
 * Uhrzeit ("17:00", "9.30") in Minuten seit Mitternacht umrechnen.
 * Nicht parsebare/leere Zeiten landen beim Sortieren am Ende.
 */
function timeToMinutes(time) {
  const match = /^(\d{1,2})[:.](\d{2})/.exec(String(time || '').trim());
  if (!match) return Number.MAX_SAFE_INTEGER;
  return Number(match[1]) * 60 + Number(match[2]);
}

/** ▲/▼-Buttonpaar zum Verschieben eines Eintrags. */
function reorderButtons(config) {
  return el(
    'div',
    { style: { display: 'flex', gap: '4px' } },
    el(Button, {
      icon: 'arrow-up-alt2',
      label: config.upLabel,
      showTooltip: true,
      size: 'small',
      variant: 'secondary',
      disabled: !config.canMoveUp,
      onClick: config.onMoveUp,
    }),
    el(Button, {
      icon: 'arrow-down-alt2',
      label: config.downLabel,
      showTooltip: true,
      size: 'small',
      variant: 'secondary',
      disabled: !config.canMoveDown,
      onClick: config.onMoveDown,
    })
  );
}

const SPACING_OPTIONS = [
  { label: 'Kein Abstand', value: 'none' },
  { label: 'Kompakt', value: 'compact' },
  { label: 'Standard', value: 'standard' },
  { label: 'Großzügig', value: 'spacious' },
];

registerBlockType('kuh/program-teaser', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { days, title, padding, margin } = attributes;
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

    function addEvent(dayIndex) {
      const updated = days.map((day, i) => {
        if (i !== dayIndex) return day;
        return { ...day, events: [...day.events, { time: '', title: '', description: '' }] };
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

    /** Tag um offset Positionen verschieben; activeDay folgt dem Tag. */
    function moveDay(dayIndex, offset) {
      const target = dayIndex + offset;
      if (target < 0 || target >= days.length) return;
      const updated = days.slice();
      updated[dayIndex] = days[target];
      updated[target] = days[dayIndex];
      setAttributes({ days: updated });
      if (activeDay === dayIndex) setActiveDay(target);
      else if (activeDay === target) setActiveDay(dayIndex);
    }

    /** Event innerhalb seines Tages um offset Positionen verschieben. */
    function moveEvent(dayIndex, eventIndex, offset) {
      const events = days[dayIndex] && days[dayIndex].events ? days[dayIndex].events : [];
      const target = eventIndex + offset;
      if (target < 0 || target >= events.length) return;
      const reordered = events.slice();
      reordered[eventIndex] = events[target];
      reordered[target] = events[eventIndex];
      setAttributes({
        days: days.map((day, i) => (i === dayIndex ? { ...day, events: reordered } : day)),
      });
    }

    /** Events eines Tages chronologisch sortieren; gleiche Zeiten behalten ihre Reihenfolge. */
    function sortEventsByTime(dayIndex) {
      const updated = days.map((day, i) => {
        if (i !== dayIndex) return day;
        const sorted = day.events
          .map((ev, ei) => ({ ev, ei }))
          .sort((a, b) => timeToMinutes(a.ev.time) - timeToMinutes(b.ev.time) || a.ei - b.ei)
          .map((entry) => entry.ev);
        return { ...day, events: sorted };
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

    return el(
      Fragment,
      null,
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Teaser-Einstellungen', initialOpen: true },
          el(TextControl, {
            label: 'Überschrift',
            value: title,
            onChange: (value) => setAttributes({ title: value }),
          }),
          el(SelectControl, {
            label: 'Innenabstand',
            value: padding,
            options: SPACING_OPTIONS,
            onChange: (value) => setAttributes({ padding: value }),
          }),
          el(SelectControl, {
            label: 'Außenabstand oben/unten',
            value: margin,
            options: SPACING_OPTIONS,
            onChange: (value) => setAttributes({ margin: value }),
          })
        ),
        days.map((day, di) =>
          el(
            PanelBody,
            { key: di, title: day.label || `Tag ${di + 1}`, initialOpen: di === activeDay },
            el(
              'div',
              { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '12px' } },
              el('strong', { style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.05em', color: '#666' } }, `Tag ${di + 1} von ${days.length}`),
              reorderButtons({
                upLabel: 'Tag nach oben',
                downLabel: 'Tag nach unten',
                canMoveUp: di > 0,
                canMoveDown: di < days.length - 1,
                onMoveUp: () => moveDay(di, -1),
                onMoveDown: () => moveDay(di, 1),
              })
            ),
            el(TextControl, { label: 'Bezeichnung', value: day.label, onChange: (v) => updateDay(di, 'label', v) }),
            el(TextControl, { label: 'Datum', value: day.date, onChange: (v) => updateDay(di, 'date', v) }),
            el('hr'),
            day.events.map((ev, ei) =>
              el(
                'div',
                { key: ei, style: { marginBottom: '12px', padding: '8px', background: '#f5f5f5', borderRadius: '4px' } },
                el(
                  'div',
                  { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' } },
                  el('strong', { style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.05em', color: '#666' } }, `Event ${ei + 1}`),
                  reorderButtons({
                    upLabel: 'Event nach oben',
                    downLabel: 'Event nach unten',
                    canMoveUp: ei > 0,
                    canMoveDown: ei < day.events.length - 1,
                    onMoveUp: () => moveEvent(di, ei, -1),
                    onMoveDown: () => moveEvent(di, ei, 1),
                  })
                ),
                el(TextControl, { label: 'Uhrzeit', value: ev.time, onChange: (v) => updateEvent(di, ei, 'time', v) }),
                el(TextControl, { label: 'Titel', value: ev.title, onChange: (v) => updateEvent(di, ei, 'title', v) }),
                el(TextControl, { label: 'Beschreibung', value: ev.description, onChange: (v) => updateEvent(di, ei, 'description', v) }),
                el(Button, { isDestructive: true, variant: 'link', onClick: () => removeEvent(di, ei) }, 'Event entfernen')
              )
            ),
            el(
              'div',
              { style: { display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '8px' } },
              el(Button, { variant: 'secondary', onClick: () => addEvent(di) }, 'Event hinzufügen'),
              el(
                Button,
                {
                  variant: 'tertiary',
                  icon: 'clock',
                  disabled: day.events.length < 2,
                  onClick: () => sortEventsByTime(di),
                },
                'Nach Uhrzeit sortieren'
              )
            ),
            el('hr'),
            el(Button, { isDestructive: true, variant: 'secondary', onClick: () => removeDay(di) }, 'Tag entfernen')
          )
        ),
        el(PanelBody, { title: 'Tage verwalten', initialOpen: false },
          el(Button, { variant: 'primary', onClick: addDay }, 'Tag hinzufügen')
        )
      ),
      el(
        'div',
        blockProps,
        el('h2', { style: { fontSize: '3rem', color: '#011e08', marginBottom: '2rem' } }, title),
        el(
          'div',
          { style: { display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '2rem' } },
          // Day tabs
          el(
            'div',
            { style: { display: 'flex', flexDirection: 'column', gap: '0.5rem' } },
            days.map((day, i) =>
              el(
                'button',
                {
                  key: i,
                  onClick: () => setActiveDay(i),
                  style: {
                    textAlign: 'left',
                    padding: '1rem',
                    borderRadius: '0.5rem',
                    border: 'none',
                    borderLeft: activeDay === i ? '4px solid #725c0c' : '4px solid transparent',
                    background: activeDay === i ? '#ecfdf5' : 'transparent',
                    cursor: 'pointer',
                  },
                },
                el('span', { style: { display: 'block', fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.1em', color: activeDay === i ? '#725c0c' : '#999' } }, day.date),
                el('span', { style: { fontSize: '1.5rem', fontWeight: 'bold', color: activeDay === i ? '#011e08' : '#aaa' } }, day.label)
              )
            )
          ),
          // Events
          el(
            'div',
            null,
            (days[activeDay]?.events || []).map((ev, i) =>
              el(
                'div',
                { key: i, style: { display: 'flex', gap: '1.5rem', padding: '1rem 0', borderBottom: '1px solid #eee' } },
                el('span', { style: { color: '#725c0c', fontWeight: 'bold', minWidth: '60px' } }, ev.time),
                el(
                  'div',
                  null,
                  el('strong', null, ev.title),
                  el('p', { style: { color: '#666', marginTop: '0.5rem' } }, ev.description)
                )
              )
            ),
            el('div', { style: { paddingTop: '1rem' } }, el(InnerBlocks, { template: INNER_BLOCKS_TEMPLATE, templateLock: false }))
          )
        )
      )
    );
  },
  save() {
    return el(InnerBlocks.Content);
  },
});
})();
