<?php
/**
 * Server-Side Render für den Programm-Zeitplan Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$days       = ! empty( $attributes['days'] ) && is_array( $attributes['days'] ) ? $attributes['days'] : array();
$title_font = ! empty( $attributes['titleFont'] ) ? $attributes['titleFont'] : 'headline';

$block_data = array(
    'days'      => $days,
    'titleFont' => $title_font,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                      => 'kuh-program-schedule not-prose',
    'data-kuh-program-schedule'  => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <div style="max-width:80rem;margin:0 auto;padding:3rem 1.5rem;">
            <?php foreach ( $days as $day ) : ?>
                <div style="margin-bottom:2.5rem;">
                    <h3 style="font-size:1.25rem;font-weight:bold;color:#011e08;margin-bottom:0.25rem;">
                        <?php echo esc_html( $day['label'] ?? '' ); ?> – <?php echo esc_html( $day['date'] ?? '' ); ?>
                    </h3>
                    <?php if ( ! empty( $day['subtitle'] ) ) : ?>
                        <p style="font-size:0.75rem;color:#725c0c;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:1rem;">
                            <?php echo esc_html( $day['subtitle'] ); ?>
                        </p>
                    <?php endif; ?>
                    <?php foreach ( $day['events'] ?? array() as $event ) :
                        $ev_type = $event['type'] ?? 'main';
                    ?>
                        <div style="display:flex;gap:1.5rem;padding:1rem 0;border-bottom:1px solid #eee;">
                            <span style="color:#011e08;font-weight:bold;min-width:60px;font-size:1.25rem;">
                                <?php if ( ! empty( $event['times'] ) ) : ?>
                                    <?php echo esc_html( $event['times'] ); ?>
                                <?php else : ?>
                                    <?php echo esc_html( $event['time'] ?? '' ); ?>
                                    <?php if ( 'recurring' === $ev_type && ! empty( $event['timeEnd'] ) ) : ?>
                                        <br><small style="color:#666;">– <?php echo esc_html( $event['timeEnd'] ); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                            <div>
                                <?php if ( 'recurring' === $ev_type ) : ?>
                                    <span style="background:#466649;color:#fff;padding:2px 8px;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.05em;">
                                        <?php echo esc_html( ! empty( $event['interval'] ) ? $event['interval'] : 'Wiederkehrend' ); ?>
                                    </span>
                                <?php endif; ?>
                                <strong style="font-size:1.125rem;"><?php echo esc_html( $event['title'] ?? '' ); ?></strong>
                                <?php if ( 'recurring' !== $ev_type && ! empty( $event['description'] ) ) : ?>
                                    <p style="color:#666;margin-top:0.25rem;"><?php echo esc_html( $event['description'] ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $event['location'] ) ) :
                                    $loc_slug = ! empty( $event['locationSlug'] )
                                        ? $event['locationSlug']
                                        : sanitize_title( $event['location'] );
                                ?>
                                    <p style="color:#725c0c;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-top:0.5rem;">
                                        <a href="<?php echo esc_url( home_url( '/karte?ort=' . urlencode( $loc_slug ) ) ); ?>" style="color:inherit;text-decoration:underline dotted;text-underline-offset:3px;">
                                            📍 <?php echo esc_html( $event['location'] ); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </noscript>
</div>
