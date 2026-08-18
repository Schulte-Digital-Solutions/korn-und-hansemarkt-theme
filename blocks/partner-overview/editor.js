/**
 * Gutenberg Editor-Script für den Partnerübersicht-Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl, RangeControl } = wp.components;
const { createElement: el } = wp.element;
const ServerSideRender = wp.serverSideRender;

registerBlockType('kuh/partner-overview', {
  edit({ attributes, setAttributes }) {
    const { title, showTitle, logoMinWidth } = attributes;

    const blockProps = useBlockProps({
      style: {
        background: '#f5f3f3',
        borderRadius: '0.5rem',
        padding: '2rem',
        textAlign: 'center',
        minHeight: '120px',
      },
    });

    return el(
      'div',
      blockProps,
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Partnerübersicht-Einstellungen', initialOpen: true },
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
          el(RangeControl, {
            label: 'Minimale Logo-Breite (px)',
            help: 'Die Spaltenanzahl passt sich automatisch an den verfügbaren Platz an.',
            value: logoMinWidth,
            onChange: function (val) { setAttributes({ logoMinWidth: val }); },
            min: 80,
            max: 480,
            step: 10,
          })
        )
      ),
      el(ServerSideRender, {
        block: 'kuh/partner-overview',
        attributes: attributes,
      })
    );
  },
  save() {
    return null;
  },
});
})();