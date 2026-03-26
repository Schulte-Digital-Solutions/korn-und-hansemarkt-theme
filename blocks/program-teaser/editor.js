/**
 * Gutenberg Editor-Script für den Programm-Teaser Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls, InnerBlocks } = wp.blockEditor;
const { PanelBody, TextControl, Button } = wp.components;
const { createElement: el, Fragment, useState } = wp.element;

const INNER_BLOCKS_TEMPLATE = [
  ['core/paragraph', { placeholder: 'z.B. Link zum vollen Programm', align: 'left' }],
];

registerBlockType('kuh/program-teaser', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { days } = attributes;
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
        days.map((day, di) =>
          el(
            PanelBody,
            { key: di, title: day.label || `Tag ${di + 1}`, initialOpen: di === activeDay },
            el(TextControl, { label: 'Bezeichnung', value: day.label, onChange: (v) => updateDay(di, 'label', v) }),
            el(TextControl, { label: 'Datum', value: day.date, onChange: (v) => updateDay(di, 'date', v) }),
            el('hr'),
            day.events.map((ev, ei) =>
              el(
                'div',
                { key: ei, style: { marginBottom: '12px', padding: '8px', background: '#f5f5f5', borderRadius: '4px' } },
                el(TextControl, { label: 'Uhrzeit', value: ev.time, onChange: (v) => updateEvent(di, ei, 'time', v) }),
                el(TextControl, { label: 'Titel', value: ev.title, onChange: (v) => updateEvent(di, ei, 'title', v) }),
                el(TextControl, { label: 'Beschreibung', value: ev.description, onChange: (v) => updateEvent(di, ei, 'description', v) }),
                el(Button, { isDestructive: true, variant: 'link', onClick: () => removeEvent(di, ei) }, 'Event entfernen')
              )
            ),
            el(Button, { variant: 'secondary', onClick: () => addEvent(di), style: { marginTop: '8px' } }, 'Event hinzufügen'),
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
        el('h2', { style: { fontSize: '3rem', color: '#011e08', marginBottom: '2rem' } }, 'Programm'),
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
