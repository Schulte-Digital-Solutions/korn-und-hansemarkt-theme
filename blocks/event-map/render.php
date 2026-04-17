<?php
/**
 * Server-Side Render für den Event-Map Block.
 *
 * Übergibt Attribute als JSON an die Svelte-Komponente.
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

$block_data = array(
    'title'          => sanitize_text_field( $attributes['title'] ?? 'Geländeplan' ),
    'subtitle'       => sanitize_text_field( $attributes['subtitle'] ?? '' ),
    'mapHeight'      => absint( $attributes['mapHeight'] ?? 580 ),
    'mobileMapHeight' => absint( $attributes['mobileMapHeight'] ?? 420 ),
    'useMinimalBaseMap' => (bool) ( $attributes['useMinimalBaseMap'] ?? true ),
    'showStreetLabels' => (bool) ( $attributes['showStreetLabels'] ?? false ),
    // Hintergrundbild-Einstellungen kommen aus der GeoJSON-Meta,
    // Gutenberg-Attribute werden dafür nicht mehr genutzt.
    'customMapImageUrl' => '',
    'customMapImageAlt' => '',
    'customMapImageOpacity' => 30,
    'areaFillColor'  => sanitize_hex_color( $attributes['areaFillColor'] ?? '#9ccf9c' ) ?: '#9ccf9c',
    'areaFillOpacity' => min( 100, max( 0, absint( $attributes['areaFillOpacity'] ?? 28 ) ) ),
    'areaLineColor'  => sanitize_hex_color( $attributes['areaLineColor'] ?? '#4a8a4a' ) ?: '#4a8a4a',
    'locationColor'  => sanitize_hex_color( $attributes['locationColor'] ?? '#15331b' ) ?: '#15331b',
    'entranceColor'  => sanitize_hex_color( $attributes['entranceColor'] ?? '#725c0c' ) ?: '#725c0c',
    'stageColor'     => sanitize_hex_color( $attributes['stageColor'] ?? '#8b1a1a' ) ?: '#8b1a1a',
    'parkingColor'   => sanitize_hex_color( $attributes['parkingColor'] ?? '#1a4a6b' ) ?: '#1a4a6b',
    'toiletColor'    => sanitize_hex_color( $attributes['toiletColor'] ?? '#4a4a6b' ) ?: '#4a4a6b',
    'infoColor'      => sanitize_hex_color( $attributes['infoColor'] ?? '#2d6b4a' ) ?: '#2d6b4a',
    'showLocations'  => (bool) ( $attributes['showLocations'] ?? true ),
    'showEntrances'  => (bool) ( $attributes['showEntrances'] ?? true ),
    'showStages'     => (bool) ( $attributes['showStages'] ?? true ),
    'showParking'    => (bool) ( $attributes['showParking'] ?? true ),
    'showToilets'    => (bool) ( $attributes['showToilets'] ?? true ),
    'showInfo'       => (bool) ( $attributes['showInfo'] ?? true ),
    'showLegend'     => (bool) ( $attributes['showLegend'] ?? true ),
    'poisData'       => kuh_get_event_map_geojson(),
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'              => 'kuh-event-map not-prose',
    'data-kuh-event-map' => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes ?>>
    <noscript>
        <div style="background:#f5f1e8;border:1px solid #c4a96a;border-radius:0.75rem;padding:3rem;text-align:center;font-family:serif;">
            <h2 style="color:#011e08;margin-bottom:0.5rem;"><?php echo esc_html( $block_data['title'] ); ?></h2>
            <?php if ( $block_data['subtitle'] ) : ?>
                <p style="color:#555;"><?php echo esc_html( $block_data['subtitle'] ); ?></p>
            <?php endif; ?>
            <p style="color:#725c0c;margin-top:1rem;">Bitte aktivieren Sie JavaScript, um die interaktive Karte anzuzeigen.</p>
        </div>
    </noscript>
</div>
