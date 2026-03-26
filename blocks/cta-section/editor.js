/**
 * Gutenberg Editor-Script für den CTA-Section Block.
 */
/* global wp */
(function () {
const { registerBlockType } = wp.blocks;
const { useBlockProps, InnerBlocks } = wp.blockEditor;
const { createElement: el } = wp.element;

const INNER_BLOCKS_TEMPLATE = [
  ['core/heading', { level: 2, placeholder: 'Werde Teil des Marktes', textAlign: 'center' }],
  ['core/paragraph', { placeholder: 'Beschreibung des Call-to-Action…', align: 'center' }],
  ['core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, [
    ['core/button', { text: 'Jetzt mitmachen', url: '#' }],
  ]],
];

registerBlockType('kuh/cta-section', {
  edit() {
    const blockProps = useBlockProps({
      style: {
        background: '#011e08',
        borderRadius: '0.75rem',
        padding: '3rem 2rem',
        color: '#fff',
        textAlign: 'center',
      },
    });

    return el(
      'div',
      blockProps,
      el(InnerBlocks, {
        template: INNER_BLOCKS_TEMPLATE,
        templateLock: false,
      })
    );
  },
  save() {
    return el(InnerBlocks.Content);
  },
});
})();
