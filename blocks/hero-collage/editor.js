/**
 * Gutenberg Editor-Script für den Hero-Collage Block.
 *
 * Nutzt die WP Block-API (keine Svelte im Editor – Svelte wird
 * nur im SPA-Frontend verwendet).
 *
 * Der Content-Bereich (Titel, Datum, Button) wird über InnerBlocks
 * abgebildet, sodass Redakteure beliebige Blöcke platzieren können.
 */
/* global wp */
const { registerBlockType } = wp.blocks;
const { useBlockProps, MediaUpload, InspectorControls, InnerBlocks } = wp.blockEditor;
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

registerBlockType('kuh/hero-collage', {
  edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'kuh-hero-collage-editor' });
    const { images, overlayOpacity, swapSpeed } = attributes;

    function onSelectImages(newImages) {
      setAttributes({
        images: newImages.map((img) => ({
          id: img.id,
          url: img.sizes?.large?.url || img.url,
          alt: img.alt || '',
        })),
      });
    }

    function removeImage(idToRemove) {
      setAttributes({
        images: images.filter((img) => img.id !== idToRemove),
      });
    }

    // Grid-Preview im Editor
    const gridItems = [];
    const maxVisible = 8;
    const visibleImages = images.slice(0, maxVisible);

    for (let i = 0; i < maxVisible; i++) {
      // Position 4 (Zeile 2, Mitte) = InnerBlocks-Bereich
      if (i === 4) {
        gridItems.push(
          el(
            'div',
            {
              key: 'center',
              style: {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                background: `rgba(0,0,0,${overlayOpacity / 100})`,
                color: '#fff',
                padding: '1rem',
                textAlign: 'center',
                minHeight: '180px',
              },
            },
            el(InnerBlocks, {
              template: INNER_BLOCKS_TEMPLATE,
              templateLock: false,
            })
          )
        );
        continue;
      }

      const img = visibleImages[i > 4 ? i - 1 : i];
      if (img) {
        gridItems.push(
          el(
            'div',
            { key: img.id + '-' + i, style: { position: 'relative', overflow: 'hidden' } },
            el('img', {
              src: img.url,
              alt: img.alt,
              style: { width: '100%', height: '100%', objectFit: 'cover', display: 'block', minHeight: '150px' },
            }),
            el(
              Button,
              {
                icon: 'no-alt',
                label: 'Bild entfernen',
                onClick: () => removeImage(img.id),
                style: {
                  position: 'absolute',
                  top: '4px',
                  right: '4px',
                  background: 'rgba(0,0,0,0.6)',
                  color: '#fff',
                  borderRadius: '50%',
                  minWidth: '24px',
                  height: '24px',
                  padding: '0',
                },
              }
            )
          )
        );
      } else {
        gridItems.push(
          el('div', {
            key: 'empty-' + i,
            style: { background: '#374151', minHeight: '150px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', fontSize: '0.75rem' },
          }, 'Bild ' + (i > 4 ? i : i + 1))
        );
      }
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
          { title: 'Einstellungen', initialOpen: true },
          el(RangeControl, {
            label: 'Overlay-Deckkraft',
            value: overlayOpacity,
            onChange: (val) => setAttributes({ overlayOpacity: val }),
            min: 0,
            max: 100,
            step: 5,
          }),
          el(RangeControl, {
            label: 'Bildwechsel-Geschwindigkeit (Sek.)',
            value: swapSpeed,
            onChange: (val) => setAttributes({ swapSpeed: val }),
            min: 2,
            max: 15,
            step: 1,
            help: 'Durchschnittliches Intervall in Sekunden zwischen Bildwechseln',
          })
        ),
        el(
          PanelBody,
          { title: 'Bilder', initialOpen: true },
          el(MediaUpload, {
            onSelect: onSelectImages,
            allowedTypes: ['image'],
            multiple: true,
            gallery: true,
            value: images.map((img) => img.id),
            render: ({ open }) =>
              el(
                Button,
                { variant: 'secondary', onClick: open },
                images.length ? 'Bilder bearbeiten' : 'Bilder auswählen'
              ),
          }),
          images.length > 0 &&
            el('p', { style: { marginTop: '8px', color: '#6b7280', fontSize: '0.8rem' } }, images.length + ' Bilder ausgewählt (min. 7 empfohlen)')
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
              display: 'grid',
              gridTemplateColumns: 'repeat(3, 1fr)',
              gap: '4px',
              background: '#111827',
              borderRadius: '8px',
              overflow: 'hidden',
              minHeight: '400px',
            },
          },
          ...gridItems
        ),
        images.length === 0 &&
          el(
            'div',
            {
              style: {
                position: 'absolute',
                inset: 0,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'rgba(17,24,39,0.8)',
                borderRadius: '8px',
              },
            },
            el(MediaUpload, {
              onSelect: onSelectImages,
              allowedTypes: ['image'],
              multiple: true,
              gallery: true,
              value: [],
              render: ({ open }) =>
                el(
                  Button,
                  { variant: 'primary', onClick: open, icon: 'format-gallery' },
                  ' Bilder für Hero-Collage auswählen'
                ),
            })
          )
      )
    );
  },

  save() {
    // InnerBlocks-Content wird serialisiert, render.php übernimmt das Rendering
    return el(InnerBlocks.Content);
  },
});
