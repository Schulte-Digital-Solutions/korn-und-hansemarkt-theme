<?php
/**
 * Server-Side Render für den Partner-Karussell Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title      = $attributes['title'] ?? 'Unsere Partner & Unterstützer';
$show_title = $attributes['showTitle'] ?? true;
$logo_height = absint( $attributes['logoHeight'] ?? 48 );
$speed      = absint( $attributes['speed'] ?? 30 );
$variant    = $attributes['variant'] ?? 'carousel';

$partners = kuh_get_partners_data();

if ( empty( $partners ) ) {
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        echo '<div style="padding:2rem;text-align:center;color:#737971;">';
        echo '<p>Noch keine Partner angelegt.</p>';
        echo '<p style="font-size:0.875rem;">Lege Partner unter <strong>Partner → Neuen Partner anlegen</strong> an.</p>';
        echo '</div>';
    }
    return;
}

$block_data = array(
    'title'      => $title,
    'showTitle'  => $show_title,
    'logoHeight' => $logo_height,
    'speed'      => $speed,
    'variant'    => $variant,
    'partners'   => $partners,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                        => 'kuh-partner-carousel not-prose',
    'data-kuh-partner-carousel'    => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <section style="background:var(--wp--preset--color--surface-container-low,#f5f3f3);padding:3rem 1.5rem;overflow:hidden;">
            <?php if ( $show_title ) : ?>
                <h2 style="text-align:center;font-size:2rem;margin-bottom:2rem;color:var(--wp--preset--color--primary,#011e08);">
                    <?php echo esc_html( $title ); ?>
                </h2>
            <?php endif; ?>
            <div style="display:flex;flex-wrap:wrap;gap:2rem;justify-content:center;align-items:center;max-width:80rem;margin:0 auto;">
                <?php foreach ( $partners as $partner ) : ?>
                    <?php if ( $partner['logo'] ) : ?>
                        <?php if ( $partner['url'] ) : ?>
                            <a href="<?php echo esc_url( $partner['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="filter:grayscale(1);opacity:0.6;">
                                <img src="<?php echo esc_url( $partner['logo'] ); ?>"
                                     alt="<?php echo esc_attr( $partner['name'] ); ?>"
                                     style="height:<?php echo $logo_height; ?>px;width:auto;object-fit:contain;" />
                            </a>
                        <?php else : ?>
                            <img src="<?php echo esc_url( $partner['logo'] ); ?>"
                                 alt="<?php echo esc_attr( $partner['name'] ); ?>"
                                 style="height:<?php echo $logo_height; ?>px;width:auto;object-fit:contain;filter:grayscale(1);opacity:0.6;" />
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    </noscript>
</div>
