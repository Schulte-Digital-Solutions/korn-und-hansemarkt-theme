/**
 * Gutenberg Editor-Script für den Drop-Cap-Paragraph Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
const { PanelBody, SelectControl, ColorPalette } = wp.components;
const { createElement: el } = wp.element;

const FONT_OPTIONS = [
  { label: 'Gotisch (Manuskript)', value: 'gothic' },
  { label: 'Serif (Newsreader)', value: 'serif' },
  { label: 'Gleiche Schriftart', value: 'inherit' },
];

const WIDTH_OPTIONS = [
  { label: 'Schmal (45 ch)', value: '45ch' },
  { label: 'Lesbar (65 ch)', value: '65ch' },
  { label: 'Inhaltsbreite (56 rem)', value: '56rem' },
  { label: 'Breite Ausrichtung (80 rem)', value: '80rem' },
  { label: 'Volle Breite', value: '100%' },
];

const FONT_FAMILIES = {
  gothic: "'Manuskript Gotisch', serif",
  serif: "'Newsreader', serif",
  inherit: 'inherit',
};

registerBlockType('kuh/drop-cap-paragraph', {
  edit({ attributes, setAttributes }) {
    const { text, dropCapColor, dropCapFont, maxWidth } = attributes;

    const blockProps = useBlockProps({
      className: 'kuh-drop-cap-paragraph-editor',
    });

    const dropCapStyle = document.createElement('style');
    const styleId = 'kuh-drop-cap-editor-style';
    if (!document.getElementById(styleId)) {
      dropCapStyle.id = styleId;
      dropCapStyle.textContent = `
        .kuh-drop-cap-paragraph-editor .kuh-drop-cap-richtext::first-letter {
          font-size: 4em;
          float: left;
          line-height: 0.8;
          margin-right: 0.1em;
          margin-top: 0.05em;
          font-weight: 700;
        }
      `;
      document.head.appendChild(dropCapStyle);
    }

    const richTextStyle = {
      maxWidth: maxWidth || '65ch',
      marginLeft: 'auto',
      marginRight: 'auto',
    };
    if (dropCapColor || dropCapFont) {
      richTextStyle['--kuh-drop-cap-color'] = dropCapColor || 'inherit';
      richTextStyle['--kuh-drop-cap-font'] = FONT_FAMILIES[dropCapFont] || FONT_FAMILIES.gothic;
    }

    return el(
      'div',
      blockProps,
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Initial-Einstellungen', initialOpen: true },
          el(SelectControl, {
            label: 'Schriftart des Initials',
            value: dropCapFont || 'gothic',
            options: FONT_OPTIONS,
            onChange: function(value) { setAttributes({ dropCapFont: value }); },
          }),
          el(SelectControl, {
            label: 'Maximale Breite',
            help: 'Steuert die Zeilenlänge des Absatzes.',
            value: maxWidth || '65ch',
            options: WIDTH_OPTIONS,
            onChange: function(value) { setAttributes({ maxWidth: value }); },
          }),
          el('div', { style: { marginTop: '16px' } },
            el('label', {
              style: { display: 'block', marginBottom: '8px', fontWeight: 500 },
            }, 'Farbe des Initials'),
            el(ColorPalette, {
              value: dropCapColor,
              onChange: function(value) { setAttributes({ dropCapColor: value }); },
              clearable: true,
            })
          )
        )
      ),
      el(RichText, {
        tagName: 'p',
        className: 'kuh-drop-cap-richtext',
        value: text,
        onChange: function(value) { setAttributes({ text: value }); },
        placeholder: 'Text mit Initial eingeben…',
        style: richTextStyle,
      })
    );
  },
  save({ attributes }) {
    return el(RichText.Content, {
      tagName: 'p',
      value: attributes.text,
    });
  },
});
})();
