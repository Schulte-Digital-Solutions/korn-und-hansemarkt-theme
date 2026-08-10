<?php
/**
 * Server-Side Render für den Galerie-Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$limit = absint( $attributes['limit'] ?? 500 );
$order = ( $attributes['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

$gallery = kuh_get_gallery_data( array(
    'limit' => $limit,
    'order' => $order,
) );

if ( empty( $gallery['images'] ) ) {
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        echo '<div style="padding:2rem;text-align:center;color:#737971;">';
        echo '<p>Noch keine Galerie-Bilder vorhanden.</p>';
        echo '<p style="font-size:0.875rem;">Weise Bildern in der <strong>Medienbibliothek</strong> ein Galerie-Jahr zu.</p>';
        echo '</div>';
    }
    return;
}

$block_data = array(
    'title'                  => $attributes['title'] ?? 'Bildergalerie',
    'showTitle'              => (bool) ( $attributes['showTitle'] ?? true ),
    'columns'                => absint( $attributes['columns'] ?? 3 ),
    'showYearFilter'         => (bool) ( $attributes['showYearFilter'] ?? true ),
    'showPhotographerFilter' => (bool) ( $attributes['showPhotographerFilter'] ?? true ),
    'showResultCount'        => (bool) ( $attributes['showResultCount'] ?? true ),
    'showCredit'             => (bool) ( $attributes['showCredit'] ?? true ),
    'defaultYear'            => kuh_sanitize_gallery_slug( $attributes['defaultYear'] ?? '' ),
    'images'                 => $gallery['images'],
    'years'                  => $gallery['years'],
    'photographers'          => $gallery['photographers'],
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'           => 'kuh-gallery not-prose',
    'data-kuh-gallery' => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <section style="max-width:80rem;margin:0 auto;padding:3rem 1.5rem;">
            <?php if ( $block_data['showTitle'] ) : ?>
                <h2 style="text-align:center;font-size:2rem;margin-bottom:2rem;color:var(--wp--preset--color--primary,#011e08);">
                    <?php echo esc_html( $block_data['title'] ); ?>
                </h2>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo (int) $block_data['columns']; ?>,1fr);gap:1rem;">
                <?php foreach ( $gallery['images'] as $image ) : ?>
                    <figure style="margin:0;">
                        <img src="<?php echo esc_url( $image['thumb'] ); ?>"
                             alt="<?php echo esc_attr( $image['alt'] ?: $image['title'] ); ?>"
                             width="<?php echo (int) $image['width']; ?>"
                             height="<?php echo (int) $image['height']; ?>"
                             loading="lazy"
                             style="width:100%;height:auto;border-radius:0.75rem;" />
                        <?php if ( $block_data['showCredit'] && ! empty( $image['photographers'] ) ) : ?>
                            <figcaption style="font-size:0.75rem;color:#737971;margin-top:0.25rem;">
                                <?php
                                /* translators: %s: Name des Fotografen. */
                                printf( esc_html__( 'Foto: %s', 'korn-und-hansemarkt' ), esc_html( implode( ', ', wp_list_pluck( $image['photographers'], 'name' ) ) ) );
                                ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>
    </noscript>
</div>
