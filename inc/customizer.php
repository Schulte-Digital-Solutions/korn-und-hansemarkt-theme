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
 * Liefert die 4 Material-3-Key-Colors (mit Defaults aus theme.json).
 *
 * @return array<string,array{name:string,default:string}>
 */
function kuh_material_key_colors() {
    $palette = kuh_get_theme_json_palette();

    return array(
        'primary' => array(
            'name'    => __( 'Primärfarbe', 'korn-und-hansemarkt' ),
            'default' => $palette['primary']['color']   ?? '#004f3b',
        ),
        'secondary' => array(
            'name'    => __( 'Sekundärfarbe', 'korn-und-hansemarkt' ),
            'default' => $palette['secondary']['color'] ?? '#c0862a',
        ),
        'tertiary' => array(
            'name'    => __( 'Tertiärfarbe', 'korn-und-hansemarkt' ),
            'default' => $palette['tertiary']['color']  ?? '#6b4a2b',
        ),
        'error' => array(
            'name'    => __( 'Fehlerfarbe', 'korn-und-hansemarkt' ),
            'default' => $palette['error']['color']     ?? '#ba1a1a',
        ),
    );
}

/**
 * Liefert das aktuelle Key-Color-Set unter Berücksichtigung der Customizer-Werte.
 *
 * @return array<string,string> slug => hex
 */
function kuh_current_key_colors() {
    $keys = array();
    foreach ( kuh_material_key_colors() as $slug => $entry ) {
        $keys[ $slug ] = get_theme_mod( 'kuh_key_color_' . $slug, $entry['default'] );
    }
    return $keys;
}

/**
 * Customizer-Einstellungen registrieren
 */
function kuh_customize_register( $wp_customize ) {
    // === Abschnitt: Material-3 Key Colors ===
    $wp_customize->add_section( 'kuh_colors', array(
        'title'       => __( 'Theme-Farben (Material 3)', 'korn-und-hansemarkt' ),
        'description' => __(
            'Nach Material Design 3 werden aus vier Key Colors automatisch alle Token der Palette berechnet (Text-, Container-, Surface- und Outline-Farben inkl. garantierter Kontraste).',
            'korn-und-hansemarkt'
        ),
        'priority'    => 30,
    ) );

    foreach ( kuh_material_key_colors() as $slug => $entry ) {
        $setting_id = 'kuh_key_color_' . $slug;

        $wp_customize->add_setting( $setting_id, array(
            'default'           => $entry['default'],
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

    // Schriftgröße des Seitennamens
    $wp_customize->add_setting( 'kuh_header_title_size', array(
        'default'           => 1.5,
        'sanitize_callback' => function( $v ) {
            $v = (float) $v;
            return max( 0.75, min( 4.0, $v ) );
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_header_title_size', array(
        'label'       => __( 'Schriftgröße Seitenname (rem)', 'korn-und-hansemarkt' ),
        'description' => __( 'Größe des Seitennamens im Header. Standard: 1.5 rem.', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_header',
        'type'        => 'number',
        'input_attrs' => array( 'min' => 0.75, 'max' => 4.0, 'step' => 0.05 ),
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
 * Generiert die komplette Material-3-Palette aus den Customizer-Key-Colors
 * und spiegelt sie in die theme.json-Daten ein. Dadurch erzeugt WordPress
 * automatisch `--wp--preset--color--<slug>`-Variablen sowie die zugehörigen
 * Editor-Palette-Einträge.
 */
function kuh_apply_customizer_palette( $theme_json ) {
    $base_palette = kuh_get_theme_json_palette();
    if ( empty( $base_palette ) ) {
        return $theme_json;
    }

    $generated = kuh_material_palette_light( kuh_current_key_colors() );

    // Namen aus theme.json wiederverwenden, Farben aus Generator.
    $new_palette = array();
    foreach ( $base_palette as $slug => $entry ) {
        $new_palette[] = array(
            'slug'  => $slug,
            'name'  => $entry['name'],
            'color' => $generated[ $slug ] ?? $entry['color'],
        );
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

/* ==========================================================================
 * Darkmode
 * ========================================================================== */

/**
 * Aktuelle Darkmode-Key-Colors.
 * Default: gleiche Werte wie Light – Markenfarben bleiben erhalten.
 *
 * @return array<string,string>
 */
function kuh_current_dark_key_colors() {
    $light_keys = kuh_current_key_colors();
    $keys = array();
    foreach ( $light_keys as $slug => $light_value ) {
        $keys[ $slug ] = get_theme_mod( 'kuh_dark_key_color_' . $slug, $light_value );
    }
    return $keys;
}

/**
 * Darkmode-Customizer-Optionen registrieren.
 */
function kuh_customize_register_darkmode( $wp_customize ) {
    $wp_customize->add_section( 'kuh_darkmode', array(
        'title'       => __( 'Darkmode', 'korn-und-hansemarkt' ),
        'description' => __(
            'Darkmode aktivieren und Farben separat konfigurieren. Die Palette wird wie im Light-Scheme automatisch aus den Key Colors generiert.',
            'korn-und-hansemarkt'
        ),
        'priority'    => 31,
    ) );

    // Darkmode aktivieren
    $wp_customize->add_setting( 'kuh_darkmode_enabled', array(
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_enabled', array(
        'label'       => __( 'Darkmode aktivieren', 'korn-und-hansemarkt' ),
        'description' => __( 'Blendet oben rechts im Menü einen Umschalter (Hell / Dunkel / Automatisch) ein.', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'checkbox',
    ) );

    // Default-Modus
    $wp_customize->add_setting( 'kuh_darkmode_default_mode', array(
        'default'           => 'auto',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'auto', 'light', 'dark' ), true ) ? $value : 'auto';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_default_mode', array(
        'label'       => __( 'Standard-Modus', 'korn-und-hansemarkt' ),
        'description' => __( 'Welcher Modus wird für Besucher verwendet, die noch keine Auswahl getroffen haben?', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'select',
        'choices'     => array(
            'auto'  => __( 'Automatisch (System-Einstellung)', 'korn-und-hansemarkt' ),
            'light' => __( 'Hell', 'korn-und-hansemarkt' ),
            'dark'  => __( 'Dunkel', 'korn-und-hansemarkt' ),
        ),
    ) );

    // Darkmode-Intensität (Helligkeit der Surfaces)
    $wp_customize->add_setting( 'kuh_darkmode_intensity', array(
        'default'           => 60,
        'sanitize_callback' => function( $v ) {
            $v = (int) $v;
            return max( 0, min( 100, $v ) );
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_intensity', array(
        'label'       => __( 'Darkmode-Helligkeit', 'korn-und-hansemarkt' ),
        'description' => __( '0 = sehr dunkel (OLED-Schwarz) · 50 = Standard M3 · 100 = deutlich heller (Mid-Grau).', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'number',
        'input_attrs' => array( 'min' => 0, 'max' => 100, 'step' => 5 ),
    ) );

    // Überschriften-Darstellung im Darkmode
    $wp_customize->add_setting( 'kuh_darkmode_heading_style', array(
        'default'           => 'lighten',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'none', 'outline', 'lighten', 'backdrop' ), true ) ? $value : 'lighten';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_heading_style', array(
        'label'       => __( 'Überschriften-Darstellung (Dark)', 'korn-und-hansemarkt' ),
        'description' => __( 'Wie sollen Überschriften im Darkmode sichtbar gemacht werden, ohne die Primärfarbe zu ändern?', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'select',
        'choices'     => array(
            'none'     => __( 'Keine Anpassung', 'korn-und-hansemarkt' ),
            'outline'  => __( 'Outline (heller Konturstrich)', 'korn-und-hansemarkt' ),
            'lighten'  => __( 'Nur Überschriften aufhellen', 'korn-und-hansemarkt' ),
            'backdrop' => __( 'Hinterlegte Badge (Primary-Container)', 'korn-und-hansemarkt' ),
        ),
    ) );

    // Header-Hintergrund im Darkmode
    $wp_customize->add_setting( 'kuh_darkmode_header_bg', array(
        'default'           => 'surface-container',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'default', 'primary', 'primary-container', 'surface-container' ), true ) ? $value : 'surface-container';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_header_bg', array(
        'label'       => __( 'Header-Hintergrund (Dark)', 'korn-und-hansemarkt' ),
        'description' => __( 'Welche Farbe bekommt der Header im Darkmode?', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'select',
        'choices'     => array(
            'default'           => __( 'Wie im Light-Mode (Header-Hintergrund aus Customizer)', 'korn-und-hansemarkt' ),
            'surface-container' => __( 'Dunkle Surface (Standard Darkmode)', 'korn-und-hansemarkt' ),
            'primary'           => __( 'Primary (Markengrün)', 'korn-und-hansemarkt' ),
            'primary-container' => __( 'Primary-Container (abgedunkeltes Markengrün)', 'korn-und-hansemarkt' ),
        ),
    ) );

    // Header-Textfarbe (Site-Titel) im Darkmode
    $wp_customize->add_setting( 'kuh_darkmode_header_text', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_darkmode_header_text', array(
        'label'       => __( 'Header-Textfarbe (Dark)', 'korn-und-hansemarkt' ),
        'description' => __( 'Farbe des Site-Titels im Header. Leer lassen = automatisch passend zum gewählten Header-Hintergrund.', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
    ) ) );

    // Mobile Bottom-Nav Hintergrund im Darkmode
    $wp_customize->add_setting( 'kuh_darkmode_bottomnav_bg', array(
        'default'           => 'surface-container',
        'sanitize_callback' => function( $value ) {
            return in_array( $value, array( 'primary', 'primary-container', 'surface-container', 'surface-container-high' ), true ) ? $value : 'surface-container';
        },
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'kuh_darkmode_bottomnav_bg', array(
        'label'       => __( 'Mobile Bottom-Nav Hintergrund (Dark)', 'korn-und-hansemarkt' ),
        'description' => __( 'Hintergrundfarbe der festen Navigationsleiste am unteren Rand auf Mobilgeräten.', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
        'type'        => 'select',
        'choices'     => array(
            'surface-container'      => __( 'Dunkle Surface (Standard)', 'korn-und-hansemarkt' ),
            'surface-container-high' => __( 'Dunkle Surface (etwas heller)', 'korn-und-hansemarkt' ),
            'primary'                => __( 'Primary (Markengrün)', 'korn-und-hansemarkt' ),
            'primary-container'      => __( 'Primary-Container (abgedunkeltes Markengrün)', 'korn-und-hansemarkt' ),
        ),
    ) );

    // Mobile Bottom-Nav Textfarbe im Darkmode (freie Hex-Farbe, optional)
    $wp_customize->add_setting( 'kuh_darkmode_bottomnav_text', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'kuh_darkmode_bottomnav_text', array(
        'label'       => __( 'Mobile Bottom-Nav Textfarbe (Dark)', 'korn-und-hansemarkt' ),
        'description' => __( 'Farbe der Icons und Beschriftungen. Leer lassen = automatisch passend zum Hintergrund.', 'korn-und-hansemarkt' ),
        'section'     => 'kuh_darkmode',
    ) ) );

    // Dark-Key-Colors – gleiche vier wie im Light-Scheme
    foreach ( kuh_material_key_colors() as $slug => $entry ) {
        $setting_id = 'kuh_dark_key_color_' . $slug;

        $wp_customize->add_setting( $setting_id, array(
            'default'           => $entry['default'],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting_id, array(
            'label'   => sprintf( __( '%s (Dark)', 'korn-und-hansemarkt' ), $entry['name'] ),
            'section' => 'kuh_darkmode',
        ) ) );
    }
}
add_action( 'customize_register', 'kuh_customize_register_darkmode' );

/**
 * Gibt die Darkmode-Palette als CSS-Regeln (`html.dark { --color-...; }`) aus.
 * Wird im <head> nach den Haupt-Styles ausgegeben, überschreibt also die
 * Token-Defaults aus app.css.
 */
function kuh_output_darkmode_css() {
    if ( ! get_theme_mod( 'kuh_darkmode_enabled', false ) ) {
        return;
    }

    $palette = kuh_material_palette_dark(
        kuh_current_dark_key_colors(),
        (int) get_theme_mod( 'kuh_darkmode_intensity', 60 )
    );

    echo "<style id=\"kuh-darkmode-tokens\">\n";
    echo "html.dark {\n";
    foreach ( $palette as $slug => $hex ) {
        printf( "  --color-%s: %s;\n", $slug, $hex );
        // Für WordPress-Blocks die presets ebenfalls überschreiben
        printf( "  --wp--preset--color--%s: %s;\n", $slug, $hex );
    }
    echo "  color-scheme: dark;\n";
    echo "}\n";

    $style = get_theme_mod( 'kuh_darkmode_heading_style', 'lighten' );
    $keys  = kuh_current_dark_key_colors();
    // Akzentfarbe für Outline: automatisch aus Primary (M3 Tone 70) aufgehellt.
    $accent = kuh_tone( $keys['primary'], 70 );

    $selectors = "html.dark h1, html.dark h2, html.dark h3, html.dark .font-headline";

    if ( 'outline' === $style ) {
        printf(
            "%s { text-shadow: -1px -1px 0 %s, 1px -1px 0 %s, -1px 1px 0 %s, 1px 1px 0 %s; }\n",
            $selectors, $accent, $accent, $accent, $accent
        );
    } elseif ( 'lighten' === $style ) {
        $light = kuh_tone( $keys['primary'], 80 );
        printf( "%s { color: %s !important; }\n", $selectors, $light );
    } elseif ( 'backdrop' === $style ) {
        printf(
            "%s { display: inline-block; padding: 0.15em 0.5em; border-radius: 0.375rem; background-color: var(--color-primary-container); color: var(--color-on-primary-container) !important; }\n",
            $selectors
        );
    }

    // Header-Hintergrund im Darkmode
    $header_bg   = get_theme_mod( 'kuh_darkmode_header_bg', 'surface-container' );
    $header_text = get_theme_mod( 'kuh_darkmode_header_text', '' );
    if ( 'default' !== $header_bg ) {
        $bg_map = array(
            'primary'           => array( 'var(--color-primary)',           'var(--color-on-primary)' ),
            'primary-container' => array( 'var(--color-primary-container)', 'var(--color-on-primary-container)' ),
            'surface-container' => array( 'var(--color-surface-container)', 'var(--color-on-surface)' ),
        );
        if ( isset( $bg_map[ $header_bg ] ) ) {
            list( $bg, $fg ) = $bg_map[ $header_bg ];
            $title_color = ! empty( $header_text ) ? $header_text : $fg;
            // Header hat Inline-Styles aus dem Light-Customizer – per !important überschreiben.
            printf(
                "html.dark header { background-color: %s !important; color: %s !important; }\n",
                $bg, $fg
            );
            // Site-Titel (Span im Header) – Farbe kann separat überschrieben werden.
            printf(
                "html.dark header .font-headline, html.dark header .font-headline span { color: %s !important; }\n",
                $title_color
            );
        }
    } elseif ( ! empty( $header_text ) ) {
        // Selbst wenn der Header seinen Light-Hintergrund behält, kann die Titel-Farbe im Dark gesetzt werden.
        printf(
            "html.dark header .font-headline, html.dark header .font-headline span { color: %s !important; }\n",
            $header_text
        );
    }

    // Mobile Bottom-Nav im Darkmode
    $bn_bg_key = get_theme_mod( 'kuh_darkmode_bottomnav_bg', 'surface-container' );
    $bn_text   = get_theme_mod( 'kuh_darkmode_bottomnav_text', '' );
    $bn_map    = array(
        'primary'                => array( 'var(--color-primary)',                'var(--color-on-primary)' ),
        'primary-container'      => array( 'var(--color-primary-container)',      'var(--color-on-primary-container)' ),
        'surface-container'      => array( 'var(--color-surface-container)',      'var(--color-on-surface-variant)' ),
        'surface-container-high' => array( 'var(--color-surface-container-high)', 'var(--color-on-surface-variant)' ),
    );
    if ( isset( $bn_map[ $bn_bg_key ] ) ) {
        list( $bn_bg, $bn_fg ) = $bn_map[ $bn_bg_key ];
        $bn_text_color = ! empty( $bn_text ) ? $bn_text : $bn_fg;
        printf(
            "html.dark nav.fixed.bottom-0 { background-color: %s !important; }\n",
            $bn_bg
        );
        // Icons/Labels der Links; Tailwind-Hover-/Aktiv-Zustände (bg-white/5, text-primary) bleiben als Overlay erhalten.
        printf(
            "html.dark nav.fixed.bottom-0 a { color: %s !important; }\n",
            $bn_text_color
        );
    }

    echo "</style>\n";
}
add_action( 'wp_head', 'kuh_output_darkmode_css', 20 );
