<?php
/**
 * Server-Side Render für den CTA-Section Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$block_data = array(
    'contentHtml' => $content,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                   => 'kuh-cta-section not-prose',
    'data-kuh-cta-section'    => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <div style="background:#011e08;border-radius:0.75rem;padding:5rem 3rem;text-align:center;margin:0 1.5rem 6rem;max-width:80rem;">
            <div style="color:#fff;">
                <?php echo $content; // phpcs:ignore ?>
            </div>
        </div>
    </noscript>
</div>
