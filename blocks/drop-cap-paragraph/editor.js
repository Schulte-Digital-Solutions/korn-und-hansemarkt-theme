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

const FONT_FAMILIES = {
  gothic: "'Manuskript Gotisch', serif",
  serif: "'Newsreader', serif",
  inherit: 'inherit',
};

registerBlockType('kuh/drop-cap-paragraph', {
  edit({ attributes, setAttributes }) {
    const { text, dropCapColor, dropCapFont } = attributes;

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

    const richTextStyle = {};
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
