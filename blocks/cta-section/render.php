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

// Hintergrundbild auflösen
$image_id  = absint( $attributes['imageId'] ?? 0 );
$image_url = '';
$image_alt = '';

if ( $image_id ) {
    $image_url = wp_get_attachment_image_url( $image_id, 'large' )
        ?: wp_get_attachment_image_url( $image_id, 'full' )
        ?: '';
    $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: '';
}

$block_data = array(
    'contentHtml'    => $content,
    'imageUrl'       => $image_url,
    'imageAlt'       => $image_alt,
    'overlayOpacity' => absint( $attributes['overlayOpacity'] ?? 85 ),
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                   => 'kuh-cta-section not-prose',
    'data-kuh-cta-section'    => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <div style="position:relative;background:#011e08;border-radius:0.75rem;padding:5rem 3rem;text-align:center;margin:0 1.5rem 6rem;max-width:80rem;overflow:hidden;">
            <?php if ( $image_url ) : ?>
                <img src="<?php echo esc_url( $image_url ); ?>"
                     alt="<?php echo esc_attr( $image_alt ); ?>"
                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" loading="lazy" />
                <div style="position:absolute;inset:0;background:var(--color-primary,#011e08);opacity:<?php echo esc_attr( $attributes['overlayOpacity'] ?? 85 ) / 100; ?>;"></div>
            <?php endif; ?>
            <div style="position:relative;color:#fff;">
                <?php echo $content; // phpcs:ignore ?>
            </div>
        </div>
    </noscript>
</div>
