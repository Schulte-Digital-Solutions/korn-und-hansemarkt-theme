/**
 * Gutenberg Editor-Script für den Highlights Grid Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, Button } = wp.components;
const { createElement: el, Fragment } = wp.element;

registerBlockType('kuh/highlights-grid', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { highlights } = attributes;

    function updateHighlight(index, field, value) {
      const updated = highlights.map((item, i) =>
        i === index ? { ...item, [field]: value } : item
      );
      setAttributes({ highlights: updated });
    }

    function addHighlight() {
      setAttributes({
        highlights: [...highlights, { value: '', title: '', description: '' }],
      });
    }

    function removeHighlight(index) {
      setAttributes({
        highlights: highlights.filter((_, i) => i !== index),
      });
    }

    return el(
      Fragment,
      null,
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Highlights', initialOpen: true },
          highlights.map((item, i) =>
            el(
              'div',
              { key: i, style: { marginBottom: '16px', padding: '12px', background: '#f0f0f0', borderRadius: '4px' } },
              el(TextControl, { label: 'Wert', value: item.value, onChange: (v) => updateHighlight(i, 'value', v) }),
              el(TextControl, { label: 'Titel', value: item.title, onChange: (v) => updateHighlight(i, 'title', v) }),
              el(TextControl, { label: 'Beschreibung', value: item.description, onChange: (v) => updateHighlight(i, 'description', v) }),
              el(Button, { isDestructive: true, variant: 'secondary', onClick: () => removeHighlight(i), style: { marginTop: '8px' } }, 'Entfernen')
            )
          ),
          el(Button, { variant: 'primary', onClick: addHighlight }, 'Highlight hinzufügen')
        )
      ),
      el(
        'div',
        blockProps,
        el(
          'div',
          { style: { display: 'grid', gridTemplateColumns: `repeat(${Math.min(highlights.length, 3)}, 1fr)`, gap: '1.5rem' } },
          highlights.map((item, i) =>
            el(
              'div',
              { key: i, style: { background: '#f5f3f3', padding: '2.5rem', borderRadius: '0.75rem' } },
              el('span', { style: { fontSize: '3rem', fontWeight: 'bold', color: '#725c0c', display: 'block', marginBottom: '2rem' } }, item.value || '…'),
              el('h3', { style: { textTransform: 'uppercase', letterSpacing: '0.1em', color: '#011e08', fontSize: '1.25rem', fontWeight: 'bold' } }, item.title || 'Titel'),
              el('p', { style: { fontSize: '0.875rem', color: '#666', marginTop: '0.5rem' } }, item.description || 'Beschreibung')
            )
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
