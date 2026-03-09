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

    // Header Hintergrundfarbe
    $wp_customize->add_setting( 'kuh_header_bg', array(
        'default'           => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_header_bg', array(
        'label'   => __( 'Hintergrundfarbe', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
    ) ) );

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

    // Header fixiert (sticky)
    $wp_customize->add_setting( 'kuh_header_sticky', array(
        'default'           => true,
        'sanitize_callback' => 'wp_validate_boolean',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_sticky', array(
        'label'   => __( 'Header fixiert (sticky)', 'korn-und-hansemarkt' ),
        'section' => 'kuh_header',
        'type'    => 'checkbox',
    ) );
}
add_action( 'customize_register', 'kuh_customize_register' );

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
