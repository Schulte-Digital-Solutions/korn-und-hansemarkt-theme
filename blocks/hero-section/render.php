<?php
/**
 * Server-Side Render für den Hero Section Block.
 *
 * Gibt einen Container mit JSON-Daten aus, den die Svelte-SPA
 * im Frontend erkennt und als interaktive Komponente mountet.
 *
 * Der Content-Bereich (Titel, Datum, Button etc.) wird über InnerBlocks
 * definiert und als gerendertes HTML an die Svelte-Komponente übergeben.
 *
 * @package KornUndHansemarkt
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Einzelnes Hintergrundbild auflösen
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
    'imageUrl'       => $image_url,
    'imageAlt'       => $image_alt,
    'contentHtml'    => $content,
    'overlayOpacity' => absint( $attributes['overlayOpacity'] ?? 40 ),
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                   => 'kuh-hero-section not-prose',
    'data-kuh-hero-section'   => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes ?>>
    <!-- Fallback / Noscript: statische Darstellung -->
    <noscript>
        <div style="position:relative;min-height:80vh;display:flex;align-items:center;overflow:hidden;">
            <?php if ( $image_url ) : ?>
                <img src="<?php echo esc_url( $image_url ); ?>"
                     alt="<?php echo esc_attr( $image_alt ); ?>"
                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" loading="lazy" />
            <?php endif; ?>
            <div style="position:absolute;inset:0;background:linear-gradient(to right,rgba(30,58,30,0.8),rgba(30,58,30,0.4),transparent);"></div>
            <div style="position:relative;z-index:10;max-width:42rem;padding:3rem;color:#fff;">
                <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- InnerBlocks content is already escaped by WordPress ?>
            </div>
        </div>
    </noscript>
</div>
