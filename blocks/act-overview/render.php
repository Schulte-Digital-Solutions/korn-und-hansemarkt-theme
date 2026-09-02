<?php
/**
 * Server-Side Render für die Acts-Übersicht.
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

$acts = kuh_get_acts_overview_data();

if ( ! empty( $attributes['hideWithoutShows'] ) ) {
    $acts = array_values( array_filter( $acts, static function ( $act ) {
        return ! empty( $act['shows'] );
    } ) );
}

if ( empty( $acts ) ) {
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        echo '<div style="padding:2rem;text-align:center;color:#737971;">';
        echo '<p>Noch keine Acts angelegt.</p>';
        echo '<p style="font-size:0.875rem;">Lege sie unter <strong>Programm → Acts</strong> an.</p>';
        echo '</div>';
    }
    return;
}

$block_data = array(
    'title'        => sanitize_text_field( $attributes['title'] ?? 'Künstler & Gruppen' ),
    'showTitle'    => (bool) ( $attributes['showTitle'] ?? true ),
    'titleFont'    => sanitize_key( $attributes['titleFont'] ?? 'headline' ),
    'cardMinWidth' => min( 480, max( 160, absint( $attributes['cardMinWidth'] ?? 260 ) ) ),
    'showSearch'   => (bool) ( $attributes['showSearch'] ?? true ),
    'showShows'    => (bool) ( $attributes['showShows'] ?? true ),
    'mapPath'      => '/' . ltrim( sanitize_text_field( $attributes['mapPath'] ?? '/karte' ), '/' ),
    'acts'         => $acts,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                  => 'kuh-act-overview not-prose',
    'data-kuh-act-overview'  => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes ?>>
    <noscript>
        <div style="max-width:80rem;margin:0 auto;padding:3rem 1.5rem;">
            <?php if ( $block_data['showTitle'] ) : ?>
                <h2 style="color:#011e08;margin-bottom:2rem;"><?php echo esc_html( $block_data['title'] ); ?></h2>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(<?php echo (int) $block_data['cardMinWidth']; ?>px,1fr));gap:1.5rem;">
                <?php foreach ( $acts as $act ) : ?>
                    <article style="background:#f5f3f3;border-radius:0.75rem;overflow:hidden;">
                        <?php if ( $act['image'] ) : ?>
                            <img src="<?php echo esc_url( $act['image'] ); ?>"
                                 alt="<?php echo esc_attr( $act['imageAlt'] ?: $act['name'] ); ?>"
                                 style="width:100%;height:160px;object-fit:cover;display:block;" />
                        <?php endif; ?>
                        <div style="padding:1rem;">
                            <h3 style="font-size:1.125rem;color:#011e08;margin:0 0 0.25rem;">
                                <?php echo esc_html( $act['name'] ); ?>
                            </h3>
                            <?php if ( $act['genre'] ) : ?>
                                <p style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#725c0c;margin:0 0 0.5rem;">
                                    <?php echo esc_html( $act['genre'] ); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ( $act['excerpt'] ) : ?>
                                <p style="font-size:0.875rem;color:#424940;margin:0 0 0.75rem;">
                                    <?php echo esc_html( $act['excerpt'] ); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ( $block_data['showShows'] && $act['shows'] ) : ?>
                                <ul style="list-style:none;padding:0;margin:0;font-size:0.8125rem;color:#424940;">
                                    <?php foreach ( $act['shows'] as $show ) : ?>
                                        <li>
                                            <strong><?php echo esc_html( $show['dayLabel'] ); ?></strong>
                                            <?php echo esc_html( $show['end'] ? $show['start'] . '–' . $show['end'] : 'ab ' . $show['start'] ); ?>
                                            <?php if ( $show['stage'] ) : ?>
                                                · <?php echo esc_html( $show['stage'] ); ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ( $act['url'] ) : ?>
                                <p style="margin:0.75rem 0 0;">
                                    <a href="<?php echo esc_url( $act['url'] ); ?>" target="_blank" rel="noopener noreferrer"
                                       style="font-size:0.8125rem;color:#725c0c;">Website</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </noscript>
</div>
