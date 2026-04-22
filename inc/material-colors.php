<?php
/**
 * Material Design 3 Farbsystem (vereinfacht) basierend auf OKLCH.
 *
 * Generiert aus wenigen Key Colors (primary, secondary, tertiary, error) alle
 * Tokens der Material-3-Palette inkl. Container-, Neutral- und Outline-Varianten.
 * Die "on-"-Farben (Textkontraste) werden automatisch aus der Tonal-Palette
 * abgeleitet und müssen nicht separat gesetzt werden.
 *
 * Hinweis: Dies ist ein OKLCH-Port, kein vollständiger HCT-Port. Für Web-UIs
 * ist OKLCH perzeptuell nah genug an Googles HCT-Algorithmus und deutlich
 * schlanker zu implementieren.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
 * Farbraum-Konvertierung: sRGB <-> Linear RGB <-> OKLab <-> OKLCH
 * ========================================================================== */

/**
 * Hex-String (#rrggbb) zu [r, g, b] mit Werten 0..1 im sRGB-Raum.
 */
function kuh_hex_to_srgb( $hex ) {
    $hex = ltrim( trim( (string) $hex ), '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
        return array( 0.0, 0.0, 0.0 );
    }
    return array(
        hexdec( substr( $hex, 0, 2 ) ) / 255.0,
        hexdec( substr( $hex, 2, 2 ) ) / 255.0,
        hexdec( substr( $hex, 4, 2 ) ) / 255.0,
    );
}

/**
 * [r, g, b] 0..1 zu Hex-String #rrggbb.
 */
function kuh_srgb_to_hex( $rgb ) {
    $out = '#';
    foreach ( $rgb as $c ) {
        $c    = max( 0.0, min( 1.0, (float) $c ) );
        $out .= str_pad( dechex( (int) round( $c * 255 ) ), 2, '0', STR_PAD_LEFT );
    }
    return $out;
}

/**
 * sRGB-Kanal (0..1) -> Linear RGB (0..1) (Gamma-Decode).
 */
function kuh_srgb_to_linear( $c ) {
    return $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
}

/**
 * Linear RGB (0..1) -> sRGB (0..1) (Gamma-Encode).
 */
function kuh_linear_to_srgb( $c ) {
    return $c <= 0.0031308 ? 12.92 * $c : 1.055 * pow( $c, 1.0 / 2.4 ) - 0.055;
}

/**
 * Linear RGB -> OKLab (Björn Ottosson).
 */
function kuh_linear_rgb_to_oklab( $rgb ) {
    list( $r, $g, $b ) = $rgb;

    $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
    $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
    $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

    $l_ = kuh_cbrt( $l );
    $m_ = kuh_cbrt( $m );
    $s_ = kuh_cbrt( $s );

    return array(
        0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_,
        1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_,
        0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_,
    );
}

/**
 * OKLab -> Linear RGB.
 */
function kuh_oklab_to_linear_rgb( $lab ) {
    list( $L, $a, $b ) = $lab;

    $l_ = $L + 0.3963377774 * $a + 0.2158037573 * $b;
    $m_ = $L - 0.1055613458 * $a - 0.0638541728 * $b;
    $s_ = $L - 0.0894841775 * $a - 1.2914855480 * $b;

    $l = $l_ * $l_ * $l_;
    $m = $m_ * $m_ * $m_;
    $s = $s_ * $s_ * $s_;

    return array(
        4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    );
}

/**
 * Cube root mit Vorzeichen-Erhalt.
 */
function kuh_cbrt( $x ) {
    return $x < 0 ? -pow( -$x, 1.0 / 3.0 ) : pow( $x, 1.0 / 3.0 );
}

/**
 * Hex -> OKLCH [L, C, H]. L in 0..1, C >= 0, H in 0..360.
 */
function kuh_hex_to_oklch( $hex ) {
    $srgb = kuh_hex_to_srgb( $hex );
    $lin  = array_map( 'kuh_srgb_to_linear', $srgb );
    list( $L, $a, $b ) = kuh_linear_rgb_to_oklab( $lin );
    $C = sqrt( $a * $a + $b * $b );
    $H = $C < 1e-6 ? 0.0 : fmod( rad2deg( atan2( $b, $a ) ) + 360.0, 360.0 );
    return array( $L, $C, $H );
}

/**
 * OKLCH -> Hex mit Gamut-Clamping (Chroma wird reduziert falls nötig).
 */
function kuh_oklch_to_hex( $L, $C, $H ) {
    $L = max( 0.0, min( 1.0, $L ) );
    $C = max( 0.0, $C );

    // Binäre Chroma-Reduktion bis Farbe in sRGB-Gamut liegt.
    $lo   = 0.0;
    $hi   = $C;
    $iter = 0;
    $rgb  = kuh_oklch_to_rgb_raw( $L, $hi, $H );
    while ( $iter < 20 && ! kuh_rgb_in_gamut( $rgb ) ) {
        $hi = ( $lo + $hi ) / 2;
        $rgb = kuh_oklch_to_rgb_raw( $L, $hi, $H );
        $iter++;
    }
    // Sicherheits-Clamp (auch bei Rundungsfehlern).
    foreach ( $rgb as &$c ) {
        $c = max( 0.0, min( 1.0, $c ) );
    }
    unset( $c );

    return kuh_srgb_to_hex( $rgb );
}

/**
 * Interne Konvertierung ohne Gamut-Check.
 */
function kuh_oklch_to_rgb_raw( $L, $C, $H ) {
    $h_rad = deg2rad( $H );
    $a     = $C * cos( $h_rad );
    $b     = $C * sin( $h_rad );
    $lin   = kuh_oklab_to_linear_rgb( array( $L, $a, $b ) );
    return array_map( 'kuh_linear_to_srgb', $lin );
}

/**
 * Prüft ob alle RGB-Komponenten innerhalb [0..1] liegen.
 */
function kuh_rgb_in_gamut( $rgb ) {
    foreach ( $rgb as $c ) {
        if ( $c < -0.0001 || $c > 1.0001 ) {
            return false;
        }
    }
    return true;
}

/* ==========================================================================
 * Tonal-Palette + M3-Rollen-Mapping
 * ========================================================================== */

/**
 * Gibt einen Tone (0..100) einer Tonal-Palette zurück.
 * Tone entspricht ungefähr OKLCH-L * 100 (M3 tone scale).
 *
 * @param string $source_hex Key Color.
 * @param int    $tone       0..100.
 * @return string Hex-Farbe.
 */
function kuh_tone( $source_hex, $tone ) {
    list( , $C, $H ) = kuh_hex_to_oklch( $source_hex );
    $L = max( 0.0, min( 1.0, $tone / 100.0 ) );
    return kuh_oklch_to_hex( $L, $C, $H );
}

/**
 * Neutral-Tone basierend auf dem Hue der Source-Farbe (M3 Standard:
 * niedrige Chroma für Surface/Background). Higher chroma für neutral-variant.
 *
 * @param string $source_hex Key Color (Hue-Lieferant).
 * @param int    $tone       0..100.
 * @param float  $chroma     Chroma-Wert in OKLCH (≈ 0.004 für Neutral, 0.016 für Variant).
 * @return string Hex-Farbe.
 */
function kuh_neutral_tone( $source_hex, $tone, $chroma = 0.004 ) {
    list( , , $H ) = kuh_hex_to_oklch( $source_hex );
    $L = max( 0.0, min( 1.0, $tone / 100.0 ) );
    return kuh_oklch_to_hex( $L, $chroma, $H );
}

/**
 * Relative Luminanz einer Hex-Farbe nach WCAG 2.x.
 */
function kuh_relative_luminance( $hex ) {
    $srgb = kuh_hex_to_srgb( $hex );
    $lin  = array_map( 'kuh_srgb_to_linear', $srgb );
    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * Kontrastverhältnis zweier Hex-Farben.
 */
function kuh_contrast_ratio( $hex_a, $hex_b ) {
    $l1 = kuh_relative_luminance( $hex_a );
    $l2 = kuh_relative_luminance( $hex_b );
    $hi = max( $l1, $l2 );
    $lo = min( $l1, $l2 );
    return ( $hi + 0.05 ) / ( $lo + 0.05 );
}

/**
 * Wählt automatisch schwarz oder weiß mit besserem Kontrast auf dem Hintergrund.
 */
function kuh_on_color( $background_hex ) {
    $white = '#ffffff';
    $black = '#000000';

    $c_white = kuh_contrast_ratio( $background_hex, $white );
    $c_black = kuh_contrast_ratio( $background_hex, $black );

    return $c_white >= $c_black ? $white : $black;
}

/**
 * Erzeugt die komplette Material-3-Palette (Light Scheme) aus Key Colors.
 *
 * @param array $keys Assoziatives Array mit mindestens 'primary'.
 *                    Optional: 'secondary', 'tertiary', 'error'.
 * @return array<string,string> slug => hex
 */
function kuh_material_palette_light( array $keys ) {
    $primary   = $keys['primary']   ?? '#6750a4';
    $secondary = $keys['secondary'] ?? $primary;
    $tertiary  = $keys['tertiary']  ?? $primary;
    $error     = $keys['error']     ?? '#ba1a1a';

    $primary_container   = kuh_tone( $primary, 90 );
    $secondary_container = kuh_tone( $secondary, 90 );
    $tertiary_container  = kuh_tone( $tertiary, 90 );
    $error_container     = kuh_tone( $error, 90 );

    return array(
        // Primary
        'primary'                    => $primary,
        'on-primary'                 => kuh_on_color( $primary ),
        'primary-container'          => $primary_container,
        'on-primary-container'       => kuh_on_color( $primary_container ),

        // Secondary
        'secondary'                  => $secondary,
        'on-secondary'               => kuh_on_color( $secondary ),
        'secondary-container'        => $secondary_container,
        'on-secondary-container'     => kuh_on_color( $secondary_container ),

        // Tertiary
        'tertiary'                   => $tertiary,
        'on-tertiary'                => kuh_on_color( $tertiary ),
        'tertiary-container'         => $tertiary_container,
        'on-tertiary-container'      => kuh_on_color( $tertiary_container ),

        // Error
        'error'                      => $error,
        'on-error'                   => kuh_on_color( $error ),
        'error-container'            => $error_container,
        'on-error-container'         => kuh_on_color( $error_container ),

        // Surface & Neutral (aus Primary-Hue, sehr niedrige Chroma)
        'surface'                    => kuh_neutral_tone( $primary, 98 ),
        'surface-dim'                => kuh_neutral_tone( $primary, 87 ),
        'surface-bright'             => kuh_neutral_tone( $primary, 98 ),
        'surface-container-lowest'   => kuh_neutral_tone( $primary, 100 ),
        'surface-container-low'      => kuh_neutral_tone( $primary, 96 ),
        'surface-container'          => kuh_neutral_tone( $primary, 94 ),
        'surface-container-high'     => kuh_neutral_tone( $primary, 92 ),
        'surface-container-highest'  => kuh_neutral_tone( $primary, 90 ),
        'on-surface'                 => kuh_neutral_tone( $primary, 10 ),

        // Surface Variant & Outline (Neutral-Variant: etwas mehr Chroma)
        'surface-variant'            => kuh_neutral_tone( $primary, 90, 0.016 ),
        'on-surface-variant'         => kuh_neutral_tone( $primary, 30, 0.016 ),
        'outline'                    => kuh_neutral_tone( $primary, 50, 0.016 ),
        'outline-variant'            => kuh_neutral_tone( $primary, 80, 0.016 ),

        // Inverse & Utility
        'inverse-surface'            => kuh_neutral_tone( $primary, 20 ),
        'inverse-on-surface'         => kuh_neutral_tone( $primary, 95 ),
        'inverse-primary'            => kuh_tone( $primary, 80 ),
        'scrim'                      => kuh_neutral_tone( $primary, 0 ),
        'shadow'                     => kuh_neutral_tone( $primary, 0 ),
    );
}

/**
 * Erzeugt die komplette Material-3-Palette (Dark Scheme) aus Key Colors.
 *
 * Im Dark Scheme werden die Rollen tonal invertiert:
 * - primary: heller Ton der Key Color (Tone 80)
 * - primary-container: dunkler Ton (Tone 30)
 * - on-primary: dunkler Ton (Tone 20)
 * - surface-Neutrale: sehr dunkel (Tone 6..24)
 *
 * @param array $keys Assoziatives Array mit mindestens 'primary'.
 *                    Optional: 'secondary', 'tertiary', 'error'.
 * @return array<string,string> slug => hex
 */
function kuh_material_palette_dark( array $keys, int $intensity = 50 ) {
    $primary   = $keys['primary']   ?? '#6750a4';
    $secondary = $keys['secondary'] ?? $primary;
    $tertiary  = $keys['tertiary']  ?? $primary;
    $error     = $keys['error']     ?? '#ba1a1a';

    // Intensity 0..100 verschiebt alle Surface-Tones linear.
    // 0   = sehr dunkel (OLED-Schwarz, M3-Original)
    // 50  = angenehm dunkel (Default)
    // 100 = deutlich heller (Surface ≈ Tone 28)
    $shift = (int) round( ( max( 0, min( 100, $intensity ) ) - 50 ) * 0.36 ); // ±18 Tones

    $t = function( $base ) use ( $shift ) {
        return max( 0, min( 100, $base + $shift ) );
    };

    // Key-Colors werden im Dark-Scheme 1:1 als primary/secondary/... übernommen,
    // damit die im Customizer gesetzten Farben genau so erscheinen wie gewählt.
    // Container- und On-Farben werden aus der Tonal-Palette der Key-Color abgeleitet.

    $primary_container   = kuh_tone( $primary, 30 );
    $secondary_container = kuh_tone( $secondary, 30 );
    $tertiary_container  = kuh_tone( $tertiary, 30 );
    $error_container     = kuh_tone( $error, 30 );

    return array(
        // Primary
        'primary'                    => $primary,
        'on-primary'                 => kuh_on_color( $primary ),
        'primary-container'          => $primary_container,
        'on-primary-container'       => kuh_on_color( $primary_container ),

        // Secondary
        'secondary'                  => $secondary,
        'on-secondary'               => kuh_on_color( $secondary ),
        'secondary-container'        => $secondary_container,
        'on-secondary-container'     => kuh_on_color( $secondary_container ),

        // Tertiary
        'tertiary'                   => $tertiary,
        'on-tertiary'                => kuh_on_color( $tertiary ),
        'tertiary-container'         => $tertiary_container,
        'on-tertiary-container'      => kuh_on_color( $tertiary_container ),

        // Error
        'error'                      => $error,
        'on-error'                   => kuh_on_color( $error ),
        'error-container'            => $error_container,
        'on-error-container'         => kuh_on_color( $error_container ),

        // Surface & Neutral (dunkel, Hue aus Primary) – leicht angehoben ggü.
        // reinem M3-Default (6 → 10), damit es nicht nach OLED-Schwarz wirkt
        // und Primary-Akzente besser lesbar sind.
        'surface'                    => kuh_neutral_tone( $primary, $t( 10 ) ),
        'surface-dim'                => kuh_neutral_tone( $primary, $t( 8 ) ),
        'surface-bright'             => kuh_neutral_tone( $primary, $t( 28 ) ),
        'surface-container-lowest'   => kuh_neutral_tone( $primary, $t( 6 ) ),
        'surface-container-low'      => kuh_neutral_tone( $primary, $t( 14 ) ),
        'surface-container'          => kuh_neutral_tone( $primary, $t( 16 ) ),
        'surface-container-high'     => kuh_neutral_tone( $primary, $t( 20 ) ),
        'surface-container-highest'  => kuh_neutral_tone( $primary, $t( 26 ) ),
        'on-surface'                 => kuh_neutral_tone( $primary, 92 ),

        // Surface Variant & Outline
        'surface-variant'            => kuh_neutral_tone( $primary, $t( 34 ), 0.016 ),
        'on-surface-variant'         => kuh_neutral_tone( $primary, 82, 0.016 ),
        'outline'                    => kuh_neutral_tone( $primary, 62, 0.016 ),
        'outline-variant'            => kuh_neutral_tone( $primary, $t( 34 ), 0.016 ),

        // Inverse & Utility
        'inverse-surface'            => kuh_neutral_tone( $primary, 90 ),
        'inverse-on-surface'         => kuh_neutral_tone( $primary, 20 ),
        'inverse-primary'            => kuh_tone( $primary, 40 ),
        'scrim'                      => kuh_neutral_tone( $primary, 0 ),
        'shadow'                     => kuh_neutral_tone( $primary, 0 ),
    );
}
