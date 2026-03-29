/**
 * Gutenberg Editor-Script für den Partner-Karussell Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl, RangeControl, SelectControl } = wp.components;
const { createElement: el } = wp.element;
const ServerSideRender = wp.serverSideRender;

registerBlockType('kuh/partner-carousel', {
  edit({ attributes, setAttributes }) {
    const { title, showTitle, logoHeight, speed, variant } = attributes;

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
          { title: 'Karussell-Einstellungen', initialOpen: true },
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
            label: 'Variante',
            value: variant,
            options: [
              { label: 'Karussell (Endlos-Scroll)', value: 'carousel' },
              { label: 'Raster (Grid)', value: 'grid' },
            ],
            onChange: function (val) { setAttributes({ variant: val }); },
          }),
          el(RangeControl, {
            label: 'Logo-Höhe (px)',
            value: logoHeight,
            onChange: function (val) { setAttributes({ logoHeight: val }); },
            min: 24,
            max: 96,
            step: 4,
          }),
          variant === 'carousel' && el(RangeControl, {
            label: 'Geschwindigkeit (Sekunden)',
            value: speed,
            onChange: function (val) { setAttributes({ speed: val }); },
            min: 10,
            max: 120,
            step: 5,
          })
        )
      ),
      el(ServerSideRender, {
        block: 'kuh/partner-carousel',
        attributes: attributes,
      })
    );
  },
  save() {
    return null; // Dynamischer Block, wird serverseitig gerendert
  },
});
})();
