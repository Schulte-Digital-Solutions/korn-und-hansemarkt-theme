<?php
/**
 * Server-Side Render für den Drop-Cap-Paragraph Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text           = $attributes['text'] ?? '';
$drop_cap_color = $attributes['dropCapColor'] ?? '';
$drop_cap_font  = $attributes['dropCapFont'] ?? 'gothic';

// source:"html"-Attribute werden nur clientseitig geparst.
// Serverseitig den Text aus dem gespeicherten $content extrahieren.
if ( empty( $text ) && ! empty( $content ) ) {
    if ( preg_match( '/<p[^>]*>(.*)<\/p>/s', $content, $m ) ) {
        $text = $m[1];
    }
}

$block_data = array(
    'text'         => $text,
    'dropCapColor' => $drop_cap_color,
    'dropCapFont'  => $drop_cap_font,
);

$font_map = array(
    'gothic'  => "'Manuskript Gotisch', serif",
    'serif'   => "'Newsreader', serif",
    'inherit' => 'inherit',
);

$initial_font  = $font_map[ $drop_cap_font ] ?? $font_map['gothic'];
$initial_color = $drop_cap_color ? esc_attr( $drop_cap_color ) : 'var(--wp--preset--color--primary, #011e08)';

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                         => 'kuh-drop-cap-paragraph not-prose',
    'data-kuh-drop-cap-paragraph'   => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
    <noscript>
        <p style="font-size:1.125rem;line-height:1.8;max-width:65ch;margin:0 auto 2rem;">
            <style scoped>
                .kuh-drop-cap-noscript::first-letter {
                    font-size: 4em;
                    float: left;
                    line-height: 0.8;
                    margin-right: 0.1em;
                    margin-top: 0.05em;
                    font-weight: 700;
                    font-family: <?php echo esc_attr( $initial_font ); ?>;
                    color: <?php echo $initial_color; // phpcs:ignore -- escaped above ?>;
                }
            </style>
            <span class="kuh-drop-cap-noscript"><?php echo wp_kses_post( $text ); ?></span>
        </p>
    </noscript>
</div>
