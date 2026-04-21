<?php
/**
 * Theme Customizer: Farben & Header
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Liest die Farbpalette aus theme.json und gibt sie als assoziatives
 * Array (slug => array( name, color )) zurück. Statisch gecacht.
 */
function kuh_get_theme_json_palette() {
    static $palette = null;
    if ( null !== $palette ) {
        return $palette;
    }

    $palette = array();
    $file    = get_stylesheet_directory() . '/theme.json';
    if ( ! file_exists( $file ) ) {
        return $palette;
    }

    $data = json_decode( file_get_contents( $file ), true );
    $entries = $data['settings']['color']['palette'] ?? array();
    foreach ( $entries as $entry ) {
        if ( empty( $entry['slug'] ) ) {
            continue;
        }
        $palette[ $entry['slug'] ] = array(
            'name'  => $entry['name']  ?? $entry['slug'],
            'color' => $entry['color'] ?? '#000000',
        );
    }
    return $palette;
}

/**
 * Customizer-Einstellungen registrieren
 */
function kuh_customize_register( $wp_customize ) {
    // === Abschnitt: Farben ===
    $wp_customize->add_section( 'kuh_colors', array(
        'title'       => __( 'Theme-Farben', 'korn-und-hansemarkt' ),
        'description' => __( 'Alle Farben der Theme-Palette. Standardwerte stammen aus theme.json.', 'korn-und-hansemarkt' ),
        'priority'    => 30,
    ) );

    $palette = kuh_get_theme_json_palette();

    foreach ( $palette as $slug => $entry ) {
        $setting_id = 'kuh_color_' . $slug;

        $wp_customize->add_setting( $setting_id, array(
            'default'           => $entry['color'],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting_id, array(
            'label'   => $entry['name'],
            'section' => 'kuh_colors',
        ) ) );
    }

    // === Abschnitt: Header ===
    $wp_customize->add_section( 'kuh_header', array(
        'title'    => __( 'Header', 'korn-und-hansemarkt' ),
        'priority' => 25,
    ) );

    // Header Hintergrundfarbe (unterstützt Alpha: #rrggbbaa oder rgba())
    $wp_customize->add_setting( 'kuh_header_bg', array(
        'default'           => '#ffffff',
        'sanitize_callback' => 'kuh_sanitize_color_alpha',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_bg', array(
        'label'       => __( 'Hintergrundfarbe', 'korn-und-hansemarkt' ),
        'description' => __( 'Hex (#rrggbb), mit Alpha (#rrggbbaa) oder rgba(r,g,b,a)', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_header',
        'type'        => 'text',
    ) );

    // Header Textfarbe
    $wp_customize->add_setting( 'kuh_header_text', array(
        'default'           => '#111827',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_header_text', array(
        'label'   => __( 'Textfarbe', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
    ) ) );

    // Header-Verhalten
    $wp_customize->add_setting( 'kuh_header_behavior', array(
        'default'           => 'sticky',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'sticky', 'normal', 'autohide' ), true ) ? $value : 'sticky';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_behavior', array(
        'label'   => __( 'Header-Verhalten', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
        'type'    => 'select',
        'choices' => array(
            'sticky'   => __( 'Immer sichtbar (sticky)', 'korn-und-hansemarkt' ),
            'normal'   => __( 'Normal (scrollt mit)', 'korn-und-hansemarkt' ),
            'autohide' => __( 'Beim Hochscrollen einblenden', 'korn-und-hansemarkt' ),
        ),
    ) );


    // Header Anzeige: Text oder Logo
    $wp_customize->add_setting( 'kuh_header_display', array(
        'default'           => 'text',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'text', 'image' ), true ) ? $value : 'text';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_display', array(
        'label'   => __( 'Logo-Anzeige', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
        'type'    => 'radio',
        'choices' => array(
            'text'  => __( 'Seitenname (Text)', 'korn-und-hansemarkt' ),
            'image' => __( 'Logo (Bild)', 'korn-und-hansemarkt' ),
        ),
    ) );
    // === Abschnitt: Footer ===
    $wp_customize->add_section( 'kuh_footer', array(
        'title'    => __( 'Footer', 'korn-und-hansemarkt' ),
        'priority' => 35,
    ) );

    $wp_customize->add_setting( 'kuh_footer_description', array(
        'default'           => 'Seit über 40 Jahren das kulturelle Highlight in der historischen Kornbrennerstadt Haselünne.',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_description', array(
        'label'   => __( 'Beschreibung', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'textarea',
    ) );

    $wp_customize->add_setting( 'kuh_footer_contact_name', array(
        'default'           => 'Stadt Haselünne',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_contact_name', array(
        'label'   => __( 'Kontakt: Name', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'kuh_footer_contact_address', array(
        'default'           => 'Rathausplatz 1',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_contact_address', array(
        'label'   => __( 'Kontakt: Straße', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'kuh_footer_contact_zip', array(
        'default'           => '49740',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_contact_zip', array(
        'label'   => __( 'Kontakt: PLZ', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'kuh_footer_contact_city', array(
        'default'           => 'Haselünne',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_contact_city', array(
        'label'   => __( 'Kontakt: Ort', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'kuh_footer_copyright', array(
        'default'           => 'Korn- und Hansemarkt. Alle Rechte vorbehalten.',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_footer_copyright', array(
        'label'   => __( 'Copyright-Text', 'korn-und-hansemarkt' ),
        'section' => 'kuh_footer',
        'type'    => 'text',
    ) );
}
add_action( 'customize_register', 'kuh_customize_register' );

/**
 * Sanitize-Callback für Farbwerte mit optionalem Alpha-Kanal.
 * Erlaubt: #rgb, #rrggbb, #rrggbbaa, rgba(r,g,b,a)
 */
function kuh_sanitize_color_alpha( $value ) {
    // #rrggbb oder #rrggbbaa
    if ( preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
        return $value;
    }
    // rgba(r, g, b, a)
    if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
        return $value;
    }
    return '#ffffff';
}

/**
 * Customizer-Werte in die theme.json-Palette einspiegeln.
 *
 * WordPress schreibt daraus automatisch die `--wp--preset--color--<slug>`-
 * CSS-Variablen, die vom Theme-CSS (app.css → `--color-<slug>`) und vom
 * Block-Editor (Palette + `.has-<slug>-color`-Regeln) verwendet werden.
 * Es werden nur Slugs überschrieben, deren Wert vom theme.json-Default
 * abweicht; so bleibt die Palette als robuster Fallback erhalten.
 */
function kuh_apply_customizer_palette( $theme_json ) {
    $palette = kuh_get_theme_json_palette();
    if ( empty( $palette ) ) {
        return $theme_json;
    }

    $new_palette = array();
    $changed     = false;

    foreach ( $palette as $slug => $entry ) {
        $default = $entry['color'];
        $value   = get_theme_mod( 'kuh_color_' . $slug, $default );
        if ( $value !== $default ) {
            $changed = true;
        }
        $new_palette[] = array(
            'slug'  => $slug,
            'name'  => $entry['name'],
            'color' => $value,
        );
    }

    if ( ! $changed ) {
        return $theme_json;
    }

    return $theme_json->update_with( array(
        'version'  => 3,
        'settings' => array(
            'color' => array(
                'palette' => $new_palette,
            ),
        ),
    ) );
}
add_filter( 'wp_theme_json_data_theme', 'kuh_apply_customizer_palette' );
