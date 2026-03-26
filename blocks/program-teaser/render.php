<?php
/**
 * Server-Side Render für den Programm-Teaser Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$days = ! empty( $attributes['days'] ) && is_array( $attributes['days'] )
    ? $attributes['days']
    : array();

$block_data = array(
    'days'        => $days,
    'contentHtml' => $content,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                     => 'kuh-program-teaser not-prose',
    'data-kuh-program-teaser'   => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <div style="max-width:80rem;margin:0 auto;padding:6rem 1.5rem;">
            <h2 style="font-size:3rem;color:#011e08;margin-bottom:3rem;">Programm</h2>
            <?php foreach ( $days as $day ) : ?>
                <div style="margin-bottom:2rem;">
                    <h3 style="font-size:1.5rem;font-weight:bold;color:#011e08;"><?php echo esc_html( $day['label'] ?? '' ); ?> – <?php echo esc_html( $day['date'] ?? '' ); ?></h3>
                    <?php foreach ( $day['events'] ?? array() as $event ) : ?>
                        <div style="display:flex;gap:1.5rem;padding:1rem 0;border-bottom:1px solid #eee;">
                            <span style="color:#725c0c;font-weight:bold;min-width:60px;"><?php echo esc_html( $event['time'] ?? '' ); ?></span>
                            <div>
                                <strong><?php echo esc_html( $event['title'] ?? '' ); ?></strong>
                                <p style="color:#666;"><?php echo esc_html( $event['description'] ?? '' ); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if ( $content ) : ?>
                <div style="padding-top:1rem;"><?php echo $content; // phpcs:ignore ?></div>
            <?php endif; ?>
        </div>
    </noscript>
</div>
