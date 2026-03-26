/**
 * Gutenberg Editor-Script für den Hero Section Block.
 *
 * Nutzt die WP Block-API (keine Svelte im Editor – Svelte wird
 * nur im SPA-Frontend verwendet).
 *
 * Der Content-Bereich (Titel, Datum, Button) wird über InnerBlocks
 * abgebildet, sodass Redakteure beliebige Blöcke platzieren können.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, MediaUpload, MediaUploadCheck, InspectorControls, InnerBlocks } = wp.blockEditor;
const { PanelBody, RangeControl, Button } = wp.components;
const { createElement: el, Fragment } = wp.element;

/** Standard-Template für InnerBlocks (Heading + Paragraph + Button) */
const INNER_BLOCKS_TEMPLATE = [
  ['core/heading', { level: 1, placeholder: 'Eventtitel', textAlign: 'center' }],
  ['core/paragraph', { placeholder: 'Datum & Uhrzeit', align: 'center' }],
  ['core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, [
    ['core/button', { text: 'Tickets sichern', url: '#' }],
  ]],
];

registerBlockType('kuh/hero-section', {
  deprecated: [
    {
      attributes: {
        images: { type: 'array', default: [] },
        overlayOpacity: { type: 'number', default: 40 },
        swapSpeed: { type: 'number', default: 5 },
      },
      migrate(attributes, innerBlocks) {
        const firstImage = attributes.images && attributes.images[0];
        return [
          {
            imageId: firstImage ? firstImage.id : 0,
            imageUrl: firstImage ? firstImage.url : '',
            imageAlt: firstImage ? firstImage.alt : '',
            overlayOpacity: attributes.overlayOpacity,
          },
          innerBlocks,
        ];
      },
      save() {
        return el(InnerBlocks.Content);
      },
    },
    {
      attributes: {
        imageId: { type: 'number', default: 0 },
        imageUrl: { type: 'string', default: '' },
        imageAlt: { type: 'string', default: '' },
        overlayOpacity: { type: 'number', default: 40 },
      },
      save() {
        return el(InnerBlocks.Content);
      },
    },
  ],
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'kuh-hero-section-editor' });
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
      // Block-Preview: Hero-Darstellung
      el(
        'div',
        blockProps,
        el(
          'div',
          {
            style: {
              position: 'relative',
              minHeight: '500px',
              borderRadius: '8px',
              overflow: 'hidden',
              display: 'flex',
              alignItems: 'center',
            },
          },
          // Hintergrundbild
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
            : el('div', {
                style: {
                  position: 'absolute',
                  inset: 0,
                  background: '#1f2937',
                },
              }),
          // Gradient Overlay
          el('div', {
            style: {
              position: 'absolute',
              inset: 0,
              background: 'linear-gradient(to right, rgba(30,58,30,' + (overlayOpacity / 100) + ') 0%, rgba(30,58,30,' + (overlayOpacity / 200) + ') 50%, transparent 100%)',
            },
          }),
          // Content-Bereich
          el(
            'div',
            {
              style: {
                position: 'relative',
                zIndex: 10,
                maxWidth: '42rem',
                padding: '3rem',
                color: '#fff',
              },
            },
            el(InnerBlocks, {
              template: INNER_BLOCKS_TEMPLATE,
              templateLock: false,
            })
          ),
          // Bild-Auswahl Overlay wenn kein Bild
          !imageUrl &&
            el(
              'div',
              {
                style: {
                  position: 'absolute',
                  inset: 0,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  background: 'rgba(17,24,39,0.7)',
                  zIndex: 20,
                },
              },
              el(MediaUploadCheck, null,
                el(MediaUpload, {
                  onSelect: onSelectImage,
                  allowedTypes: ['image'],
                  value: imageId,
                  render: ({ open }) =>
                    el(
                      Button,
                      { variant: 'primary', onClick: open, icon: 'format-image' },
                      ' Hintergrundbild auswählen'
                    ),
                })
              )
            )
        )
      )
    );
  },

  save() {
    return el(InnerBlocks.Content);
  },
});

/**
 * Alten Block-Namen registrieren, damit bestehende Inhalte
 * nicht als "Block-Fehler" angezeigt werden.
 * WordPress transformiert den alten Block automatisch.
 */
registerBlockType('kuh/hero-collage', {
  title: 'Hero (veraltet)',
  icon: 'cover-image',
  category: 'design',
  attributes: {
    images: { type: 'array', default: [] },
    imageId: { type: 'number', default: 0 },
    imageUrl: { type: 'string', default: '' },
    imageAlt: { type: 'string', default: '' },
    overlayOpacity: { type: 'number', default: 40 },
    swapSpeed: { type: 'number', default: 5 },
  },
  transforms: {
    to: [
      {
        type: 'block',
        blocks: ['kuh/hero-section'],
        transform(attributes, innerBlocks) {
          var firstImage = attributes.images && attributes.images[0];
          return wp.blocks.createBlock('kuh/hero-section', {
            imageId: attributes.imageId || (firstImage ? firstImage.id : 0),
            imageUrl: attributes.imageUrl || (firstImage ? firstImage.url : ''),
            imageAlt: attributes.imageAlt || (firstImage ? firstImage.alt : ''),
            overlayOpacity: attributes.overlayOpacity || 40,
          }, innerBlocks);
        },
      },
    ],
  },
  edit({ attributes }) {
    var blockProps = useBlockProps();
    return el(
      'div',
      blockProps,
      el('div', {
        style: {
          padding: '2rem',
          textAlign: 'center',
          background: '#fdf2c5',
          border: '2px dashed #d4a017',
          borderRadius: '8px',
        },
      },
        el('p', { style: { fontWeight: 'bold', marginBottom: '0.5rem' } }, '⚠️ Dieser Block wurde umbenannt.'),
        el('p', null, 'Bitte klicke auf den Block und wähle „In Hero Section umwandeln" im Block-Menü (⋮).'),
        el(InnerBlocks, { templateLock: false })
      )
    );
  },
  save() {
    return el(InnerBlocks.Content);
  },
});

})();
