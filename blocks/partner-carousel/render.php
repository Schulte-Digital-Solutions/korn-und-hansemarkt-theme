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
$logo_min_width = min( 480, max( 80, absint( $attributes['logoMinWidth'] ?? 160 ) ) );
$speed      = absint( $attributes['speed'] ?? 30 );
$variant    = $attributes['variant'] ?? 'carousel';
$show_button = $attributes['showButton'] ?? true;
$button_label = $attributes['buttonLabel'] ?? 'Zur Partnerseite';
$button_url   = $attributes['buttonUrl'] ?? 'https://kornundhansemarkt.de/verein#partner';
$noscript_layout_style = 'grid' === $variant
    ? sprintf( 'display:grid;grid-template-columns:repeat(auto-fit,minmax(%dpx,1fr));gap:2rem;align-items:center;max-width:80rem;margin:0 auto;', $logo_min_width )
    : 'display:flex;flex-wrap:wrap;gap:2rem;justify-content:center;align-items:center;max-width:80rem;margin:0 auto;';
$noscript_logo_style = 'grid' === $variant
    ? 'display:block;width:100%;height:auto;object-fit:contain;'
    : sprintf( 'height:%dpx;width:auto;object-fit:contain;', $logo_height );
// Im Grid werden die Logos immer in Farbe dargestellt, nur das Karussell wird ausgegraut.
$noscript_logo_filter = 'grid' === $variant ? '' : 'filter:grayscale(1);opacity:0.6;';

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
    'logoMinWidth' => $logo_min_width,
    'speed'      => $speed,
    'variant'    => $variant,
    'showButton' => $show_button,
    'buttonLabel'=> $button_label,
    'buttonUrl'  => $button_url,
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
            <div style="<?php echo esc_attr( $noscript_layout_style ); ?>">
                <?php foreach ( $partners as $partner ) : ?>
                    <?php if ( $partner['logo'] ) : ?>
                        <?php if ( $partner['url'] ) : ?>
                            <a href="<?php echo esc_url( $partner['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="display:block;<?php echo esc_attr( $noscript_logo_filter ); ?>">
                                <img src="<?php echo esc_url( $partner['logo'] ); ?>"
                                     alt="<?php echo esc_attr( $partner['name'] ); ?>"
                                     style="<?php echo esc_attr( $noscript_logo_style ); ?>" />
                            </a>
                        <?php else : ?>
                            <img src="<?php echo esc_url( $partner['logo'] ); ?>"
                                 alt="<?php echo esc_attr( $partner['name'] ); ?>"
                                 style="<?php echo esc_attr( $noscript_logo_style . $noscript_logo_filter ); ?>" />
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if ( $show_button && $button_url ) : ?>
                <div style="text-align:center;margin-top:2rem;">
                    <a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.75rem;background:var(--wp--preset--color--primary,#011e08);color:#fff;padding:0.75rem 1.5rem;font-weight:600;text-decoration:none;">
                        <?php echo esc_html( $button_label ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </section>
    </noscript>
</div>
