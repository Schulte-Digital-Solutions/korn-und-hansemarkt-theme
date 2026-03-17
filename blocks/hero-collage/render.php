<?php
/**
 * Server-Side Render für den Hero-Collage Block.
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

// Bilder mit korrekten URLs anreichern
$images = array();
if ( ! empty( $attributes['images'] ) && is_array( $attributes['images'] ) ) {
    foreach ( $attributes['images'] as $img ) {
        $id = isset( $img['id'] ) ? absint( $img['id'] ) : 0;
        if ( ! $id ) {
            continue;
        }
        $sizes = array();
        foreach ( array( 'medium_large', 'large', 'full' ) as $size ) {
            $src = wp_get_attachment_image_url( $id, $size );
            if ( $src ) {
                $sizes[ $size ] = $src;
            }
        }
        $images[] = array(
            'id'  => $id,
            'url' => $sizes['large'] ?? ( $sizes['full'] ?? '' ),
            'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: '',
        );
    }
}

$block_data = array(
    'images'         => $images,
    'contentHtml'    => $content,
    'overlayOpacity' => absint( $attributes['overlayOpacity'] ?? 40 ),
    'swapSpeed'      => absint( $attributes['swapSpeed'] ?? 5 ),
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                   => 'kuh-hero-collage not-prose',
    'data-kuh-hero-collage'   => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes ?>>
    <!-- Fallback / Noscript: statische Darstellung -->
    <noscript>
        <div style="position:relative;min-height:80vh;display:grid;grid-template-columns:repeat(3,1fr);gap:4px;padding:4px;background:#111;">
            <?php foreach ( array_slice( $images, 0, 8 ) as $i => $img ) : ?>
                <?php if ( $i === 4 ) : ?>
                    <div style="display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.7);color:#fff;padding:2rem;text-align:center;">
                        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- InnerBlocks content is already escaped by WordPress ?>
                    </div>
                <?php else : ?>
                    <img src="<?php echo esc_url( $img['url'] ); ?>"
                         alt="<?php echo esc_attr( $img['alt'] ); ?>"
                         style="width:100%;height:100%;object-fit:cover;" loading="lazy" />
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </noscript>
</div>
