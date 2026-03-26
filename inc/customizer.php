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
 * Customizer-Einstellungen registrieren
 */
function kuh_customize_register( $wp_customize ) {
    // === Abschnitt: Farben ===
    $wp_customize->add_section( 'kuh_colors', array(
        'title'    => __( 'Theme-Farben', 'korn-und-hansemarkt' ),
        'priority' => 30,
    ) );

    $colors = array(
        'primary'   => array( 'label' => 'Primärfarbe',   'default' => '#2c3e50' ),
        'secondary' => array( 'label' => 'Sekundärfarbe', 'default' => '#c0862a' ),
        'accent'    => array( 'label' => 'Akzentfarbe',   'default' => '#e67e22' ),
        'bg'        => array( 'label' => 'Hintergrund',   'default' => '#ffffff' ),
        'text'      => array( 'label' => 'Textfarbe',     'default' => '#333333' ),
        'muted'     => array( 'label' => 'Gedämpft',      'default' => '#6b7280' ),
    );

    foreach ( $colors as $key => $color ) {
        $wp_customize->add_setting( 'kuh_color_' . $key, array(
            'default'           => $color['default'],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_color_' . $key, array(
            'label'   => $color['label'],
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
 * Customizer-Farben als CSS-Variablen in den <head> ausgeben
 */
function kuh_customizer_css() {
    $colors = array(
        'primary'   => '#2c3e50',
        'secondary' => '#c0862a',
        'accent'    => '#e67e22',
        'bg'        => '#ffffff',
        'text'      => '#333333',
        'muted'     => '#6b7280',
    );

    echo '<style>:root {';
    foreach ( $colors as $key => $default ) {
        $value = get_theme_mod( 'kuh_color_' . $key, $default );
        echo '--color-' . esc_attr( $key ) . ':' . esc_attr( $value ) . ';';
    }
    echo '}</style>' . "\n";
}
add_action( 'wp_head', 'kuh_customizer_css', 5 );

/**
 * theme.json-Farbpalette dynamisch mit Customizer-Werten überschreiben,
 * damit der Block-Editor immer die aktuellen Farben zeigt.
 */
function kuh_override_theme_json_colors( $theme_json ) {
    $color_map = array(
        'primary'    => '#2c3e50',
        'secondary'  => '#c0862a',
        'accent'     => '#e67e22',
        'background' => '#ffffff',
        'text'       => '#333333',
        'muted'      => '#6b7280',
    );

    // Customizer-Schlüssel auf theme.json-Slugs mappen
    $customizer_to_slug = array(
        'primary'   => 'primary',
        'secondary' => 'secondary',
        'accent'    => 'accent',
        'bg'        => 'background',
        'text'      => 'text',
        'muted'     => 'muted',
    );

    $palette = array();
    $labels = array(
        'primary'    => 'Primärfarbe',
        'secondary'  => 'Sekundärfarbe',
        'accent'     => 'Akzentfarbe',
        'background' => 'Hintergrund',
        'text'       => 'Textfarbe',
        'muted'      => 'Gedämpft',
    );

    foreach ( $customizer_to_slug as $mod_key => $slug ) {
        $default = $color_map[ $slug ];
        $palette[] = array(
            'slug'  => $slug,
            'color' => get_theme_mod( 'kuh_color_' . $mod_key, $default ),
            'name'  => $labels[ $slug ],
        );
    }

    $new_data = array(
        'version'  => 3,
        'settings' => array(
            'color' => array(
                'palette' => $palette,
            ),
        ),
    );

    return $theme_json->update_with( $new_data );
}
add_filter( 'wp_theme_json_data_theme', 'kuh_override_theme_json_colors' );
