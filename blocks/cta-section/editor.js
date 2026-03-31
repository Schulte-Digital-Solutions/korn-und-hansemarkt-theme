/**
 * Gutenberg Editor-Script für den CTA-Section Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, MediaUpload, MediaUploadCheck, InspectorControls, InnerBlocks } = wp.blockEditor;
const { PanelBody, RangeControl, Button } = wp.components;
const { createElement: el, Fragment } = wp.element;

const INNER_BLOCKS_TEMPLATE = [
  ['core/heading', { level: 2, placeholder: 'Werde Teil des Marktes', textAlign: 'center' }],
  ['core/paragraph', { placeholder: 'Beschreibung des Call-to-Action…', align: 'center' }],
  ['core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, [
    ['core/button', { text: 'Jetzt mitmachen', url: '#' }],
  ]],
];

registerBlockType('kuh/cta-section', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { imageId, imageUrl, imageAlt, overlayOpacity } = attributes;

    function onSelectImage(media) {
      setAttributes({
        imageId: media.id,
        imageUrl: media.sizes?.large?.url || media.url,
        imageAlt: media.alt || '',
      });
    }

    function onRemoveImage() {
      setAttributes({
        imageId: 0,
        imageUrl: '',
        imageAlt: '',
      });
    }

    return el(
      Fragment,
      null,
      // Sidebar Controls
      el(
        InspectorControls,
        null,
        el(
          PanelBody,
          { title: 'Hintergrundbild', initialOpen: true },
          el(MediaUploadCheck, null,
            el(MediaUpload, {
              onSelect: onSelectImage,
              allowedTypes: ['image'],
              value: imageId,
              render: ({ open }) =>
                el(
                  'div',
                  null,
                  imageUrl
                    ? el(
                        'div',
                        { style: { marginBottom: '8px' } },
                        el('img', {
                          src: imageUrl,
                          alt: imageAlt,
                          style: { width: '100%', height: 'auto', borderRadius: '4px', display: 'block' },
                        }),
                        el(
                          'div',
                          { style: { display: 'flex', gap: '8px', marginTop: '8px' } },
                          el(Button, { variant: 'secondary', onClick: open }, 'Bild ändern'),
                          el(Button, { isDestructive: true, variant: 'link', onClick: onRemoveImage }, 'Entfernen')
                        )
                      )
                    : el(Button, { variant: 'secondary', onClick: open }, 'Hintergrundbild auswählen')
                ),
            })
          )
        ),
        el(
          PanelBody,
          { title: 'Overlay', initialOpen: true },
          el(RangeControl, {
            label: 'Overlay-Deckkraft',
            value: overlayOpacity,
            onChange: (val) => setAttributes({ overlayOpacity: val }),
            min: 0,
            max: 100,
            step: 5,
          })
        )
      ),
      // Block-Preview
      el(
        'div',
        blockProps,
        el(
          'div',
          {
            style: {
              position: 'relative',
              borderRadius: '0.75rem',
              overflow: 'hidden',
              padding: '3rem 2rem',
              textAlign: 'center',
              color: '#fff',
            },
          },
          // Hintergrundbild oder Fallback-Farbe
          imageUrl
            ? el('img', {
                src: imageUrl,
                alt: imageAlt,
                style: {
                  position: 'absolute',
                  inset: 0,
                  width: '100%',
                  height: '100%',
                  objectFit: 'cover',
                  display: 'block',
                },
              })
            : null,
          // Farb-Overlay
          el('div', {
            style: {
              position: 'absolute',
              inset: 0,
              background: '#011e08',
              opacity: overlayOpacity / 100,
            },
          }),
          // Content-Bereich
          el(
            'div',
            { style: { position: 'relative', zIndex: 10 } },
            el(InnerBlocks, {
              template: INNER_BLOCKS_TEMPLATE,
              templateLock: false,
            })
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
