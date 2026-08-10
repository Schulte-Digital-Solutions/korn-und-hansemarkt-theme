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

$per_page = absint( $attributes['limit'] ?? 48 );
$order    = ( $attributes['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
$year     = kuh_sanitize_gallery_slug( $attributes['defaultYear'] ?? '' );

$gallery = kuh_get_gallery_data( array(
    'jahr'     => $year,
    'per_page' => $per_page,
    'order'    => $order,
) );

if ( empty( $gallery['items'] ) ) {
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        echo '<div style="padding:2rem;text-align:center;color:#737971;">';
        echo '<p>Noch keine Galerie-Inhalte vorhanden.</p>';
        echo '<p style="font-size:0.875rem;">Weise Bildern in der <strong>Medienbibliothek</strong> ein Galerie-Jahr zu oder lege unter <strong>Medien &rarr; Galerie-Videos</strong> ein Video an.</p>';
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
    'showTypeFilter'         => (bool) ( $attributes['showTypeFilter'] ?? true ),
    'showResultCount'        => (bool) ( $attributes['showResultCount'] ?? true ),
    'showCredit'             => (bool) ( $attributes['showCredit'] ?? true ),
    'defaultYear'            => $year,
    'order'                  => $order,
    'perPage'                => $per_page,
    'total'                  => $gallery['total'],
    'hasMore'                => $gallery['hasMore'],
    'items'                  => $gallery['items'],
    'years'                  => $gallery['years'],
    'photographers'          => $gallery['photographers'],
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'            => 'kuh-gallery not-prose',
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
                <?php foreach ( $gallery['items'] as $item ) : ?>
                    <figure style="margin:0;">
                        <?php if ( 'video' === $item['type'] ) : ?>
                            <a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $item['videoId'] ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php endif; ?>
                        <?php if ( $item['thumb'] ) : ?>
                            <img src="<?php echo esc_url( $item['thumb'] ); ?>"
                                 alt="<?php echo esc_attr( $item['alt'] ?: $item['title'] ); ?>"
                                 width="<?php echo (int) $item['width']; ?>"
                                 height="<?php echo (int) $item['height']; ?>"
                                 loading="lazy"
                                 style="width:100%;height:auto;border-radius:0.75rem;" />
                        <?php else : ?>
                            <span style="display:block;padding:2rem;background:#f5f3f3;border-radius:0.75rem;text-align:center;">
                                <?php echo esc_html( $item['title'] ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( 'video' === $item['type'] ) : ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $block_data['showCredit'] && ! empty( $item['photographers'] ) ) : ?>
                            <figcaption style="font-size:0.75rem;color:#737971;margin-top:0.25rem;">
                                <?php
                                /* translators: %s: Name des Fotografen. */
                                printf( esc_html__( 'Foto: %s', 'korn-und-hansemarkt' ), esc_html( implode( ', ', wp_list_pluck( $item['photographers'], 'name' ) ) ) );
                                ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>
    </noscript>
</div>
