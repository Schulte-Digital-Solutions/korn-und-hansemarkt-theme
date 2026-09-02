<?php
/**
 * Server-Side Render für den Bühnenplan-Block.
 *
 * Die Programmdaten stammen aus den CPTs (siehe inc/program-cpt.php) und werden
 * als JSON an die Svelte-Komponente übergeben. Das <noscript>-Markup bildet den
 * kompletten Plan zusätzlich als reine HTML-Liste ab.
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

$program = kuh_get_program_data();

$map_path = '/' . ltrim( sanitize_text_field( $attributes['mapPath'] ?? '/karte' ), '/' );

$block_data = array(
    'title'         => sanitize_text_field( $attributes['title'] ?? 'Bühnenplan' ),
    'showTitle'     => (bool) ( $attributes['showTitle'] ?? true ),
    'titleFont'     => sanitize_key( $attributes['titleFont'] ?? 'headline' ),
    'defaultView'   => in_array( $attributes['defaultView'] ?? 'grid', array( 'grid', 'list' ), true ) ? $attributes['defaultView'] : 'grid',
    'showNowMarker' => (bool) ( $attributes['showNowMarker'] ?? true ),
    'showActPanel'  => (bool) ( $attributes['showActPanel'] ?? true ),
    'pixelsPerHour' => min( 400, max( 60, absint( $attributes['pixelsPerHour'] ?? 120 ) ) ),
    'mapPath'       => $map_path,
    'days'          => $program['days'],
    'acts'          => $program['acts'],
);

if ( empty( $program['days'] ) ) {
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        echo '<div style="padding:2rem;text-align:center;color:#737971;">';
        echo '<p>Noch keine Programmpunkte angelegt.</p>';
        echo '<p style="font-size:0.875rem;">Lege Programmpunkte unter <strong>Programm → Programmpunkte</strong> an oder nutze <strong>Programm → Import</strong>.</p>';
        echo '</div>';
    }
    return;
}

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                     => 'kuh-program-schedule not-prose',
    'data-kuh-program-schedule' => wp_json_encode( $block_data ),
) );

/**
 * Zeitangabe eines Slots als Text.
 *
 * @param array $slot Slot-Daten.
 * @return string
 */
$format_time = static function ( $slot ) {
    if ( ! empty( $slot['end'] ) ) {
        return $slot['start'] . '–' . $slot['end'] . ' Uhr';
    }
    return 'ab ' . $slot['start'] . ' Uhr';
};
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes ?>>
    <noscript>
        <div style="max-width:80rem;margin:0 auto;padding:3rem 1.5rem;">
            <?php if ( $block_data['showTitle'] ) : ?>
                <h2 style="color:#011e08;margin-bottom:2rem;"><?php echo esc_html( $block_data['title'] ); ?></h2>
            <?php endif; ?>

            <?php foreach ( $program['days'] as $day ) : ?>
                <section style="margin-bottom:3rem;">
                    <h3 style="font-size:1.5rem;color:#011e08;margin-bottom:0.25rem;">
                        <?php echo esc_html( $day['label'] ); ?>, <?php echo esc_html( $day['dateLabel'] ); ?>
                    </h3>

                    <?php
                    $groups = array();
                    foreach ( $day['stages'] as $stage ) {
                        $groups[] = array(
                            'name'  => $stage['name'],
                            'slots' => array_values( array_filter( $day['slots'], static function ( $slot ) use ( $stage ) {
                                return $slot['stage'] === $stage['slug'];
                            } ) ),
                        );
                    }
                    ?>

                    <?php foreach ( $groups as $group ) : ?>
                        <?php if ( ! $group['slots'] ) : continue; endif; ?>
                        <h4 style="font-size:1.125rem;color:#15331b;margin:1.5rem 0 0.5rem;">
                            <?php echo esc_html( $group['name'] ); ?>
                        </h4>
                        <ul style="list-style:none;padding:0;margin:0;">
                            <?php foreach ( $group['slots'] as $slot ) : ?>
                                <li style="display:flex;gap:1rem;padding:0.5rem 0;border-bottom:1px solid #e4e2e2;">
                                    <span style="min-width:9rem;color:#011e08;font-weight:bold;">
                                        <?php echo esc_html( $format_time( $slot ) ); ?>
                                    </span>
                                    <span>
                                        <?php echo esc_html( $slot['title'] ?: $slot['actName'] ); ?>
                                        <?php if ( ! empty( $slot['note'] ) ) : ?>
                                            <span style="color:#737971;"> – <?php echo esc_html( $slot['note'] ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </noscript>
</div>
