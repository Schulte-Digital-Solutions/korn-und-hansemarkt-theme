<?php
/**
 * Server-Side Render für den Highlights Grid Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$highlights = ! empty( $attributes['highlights'] ) && is_array( $attributes['highlights'] )
    ? $attributes['highlights']
    : array();

$block_data = array(
    'highlights' => $highlights,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                      => 'kuh-highlights-grid not-prose',
    'data-kuh-highlights-grid'   => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;max-width:80rem;margin:0 auto;padding:3rem 1.5rem;">
            <?php foreach ( $highlights as $item ) : ?>
                <div style="background:#f5f3f3;padding:2.5rem;border-radius:0.75rem;">
                    <span style="font-size:3rem;font-weight:bold;color:#725c0c;"><?php echo esc_html( $item['value'] ?? '' ); ?></span>
                    <h3 style="text-transform:uppercase;letter-spacing:0.1em;color:#011e08;margin-top:2rem;"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
                    <p style="font-size:0.875rem;color:#666;"><?php echo esc_html( $item['description'] ?? '' ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </noscript>
</div>
