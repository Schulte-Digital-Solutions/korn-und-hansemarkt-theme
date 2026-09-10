<?php
/**
 * SPA Kontaktformular REST-Endpoint + Shortcode
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * hCaptcha Site Key laden (Konstante bevorzugt, dann Theme-Mod).
 */
function kuh_get_hcaptcha_site_key() {
    if ( defined( 'KUH_HCAPTCHA_SITE_KEY' ) ) {
        $value = (string) constant( 'KUH_HCAPTCHA_SITE_KEY' );
        if ( '' !== $value ) {
            return $value;
        }
    }
    return (string) get_theme_mod( 'kuh_hcaptcha_site_key', '' );
}

/**
 * hCaptcha Secret Key laden (Konstante bevorzugt, dann Theme-Mod).
 */
function kuh_get_hcaptcha_secret_key() {
    if ( defined( 'KUH_HCAPTCHA_SECRET_KEY' ) ) {
        $value = (string) constant( 'KUH_HCAPTCHA_SECRET_KEY' );
        if ( '' !== $value ) {
            return $value;
        }
    }
    return (string) get_theme_mod( 'kuh_hcaptcha_secret_key', '' );
}

/**
 * Ob hCaptcha fuer Kontaktanfragen aktiv ist.
 */
function kuh_is_hcaptcha_enabled() {
    return (bool) get_theme_mod( 'kuh_hcaptcha_enabled', false );
}

/**
 * wp_mail()-Fehler ins Debug-Log schreiben.
 */
add_action( 'wp_mail_failed', function ( $wp_error ) {
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( '[kuh contact-form] wp_mail fehlgeschlagen: ' . $wp_error->get_error_message() );
    }
} );

/**
 * REST-Route fuer Kontaktformular registrieren.
 */
function kuh_register_contact_endpoint() {
    register_rest_route( 'kuh/v1', '/contact', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'kuh_handle_contact_submit',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'kuh_register_contact_endpoint' );

/**
 * Kontaktanfrage verarbeiten und versenden.
 */
function kuh_handle_contact_submit( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'x_wp_nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Ungueltige Anfrage.', 'korn-und-hansemarkt' ), array( 'status' => 403 ) );
    }

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        return new WP_Error( 'invalid_payload', __( 'Ungueltige Daten.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
    }

    $subject        = sanitize_text_field( $params['subject'] ?? '' );
    $honeypot       = sanitize_text_field( $params['website'] ?? '' );
    $form_started   = (int) ( $params['formStartedAt'] ?? 0 );
    $hcaptcha_token = sanitize_text_field( $params['hcaptchaToken'] ?? '' );
    $requested_recipient = sanitize_email( $params['recipientEmail'] ?? '' );
    $recipient_token     = sanitize_text_field( $params['recipientToken'] ?? '' );
    $fields_token        = sanitize_text_field( $params['fieldsToken'] ?? '' );
    $form_id             = (int) ( $params['formId'] ?? 0 );
    $form_token          = sanitize_text_field( $params['formToken'] ?? '' );
    $confirmation_mail   = ! empty( $params['confirmationMail'] );
    $legacy_name         = sanitize_text_field( $params['name'] ?? '' );
    $legacy_email        = sanitize_email( $params['email'] ?? '' );
    $legacy_message      = sanitize_textarea_field( $params['message'] ?? '' );
    $has_dynamic_fields  = is_array( $params['fields'] ?? null ) && ! empty( $params['fields'] );

    if ( $has_dynamic_fields ) {
        $fields = kuh_sanitize_contact_submitted_fields( $params['fields'] );
        if ( empty( $fields ) ) {
            return new WP_Error( 'missing_fields', __( 'Bitte mindestens ein Feld ausfuellen.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
        }

        if ( empty( $fields_token ) || ! kuh_verify_form_fields_token( $fields, $fields_token ) ) {
            return new WP_Error( 'invalid_fields_token', __( 'Formular-Konfiguration ungueltig. Bitte Seite neu laden.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
        }

        foreach ( $fields as $field ) {
            $value = $field['value'];

            if ( ! empty( $field['required'] ) ) {
                $is_empty = ( 'checkbox' === $field['type'] ) ? ! $value : '' === trim( (string) $value );
                if ( $is_empty ) {
                    return new WP_Error( 'missing_fields', __( 'Bitte alle Pflichtfelder ausfuellen.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
                }
            }

            if ( 'email' === $field['type'] && '' !== (string) $value && ! is_email( (string) $value ) ) {
                return new WP_Error( 'invalid_email', __( 'Bitte eine gueltige E-Mail-Adresse angeben.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
            }

            if ( 'number' === $field['type'] && '' !== (string) $value && ! is_numeric( str_replace( ',', '.', (string) $value ) ) ) {
                return new WP_Error( 'invalid_number', __( 'Bitte eine gueltige Zahl angeben.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
            }

            if ( 'date' === $field['type'] && '' !== (string) $value ) {
                $date = \DateTime::createFromFormat( 'Y-m-d', (string) $value );
                if ( ! $date || $date->format( 'Y-m-d' ) !== (string) $value ) {
                    return new WP_Error( 'invalid_date', __( 'Bitte ein gueltiges Datum angeben.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
                }
            }
        }
    } else {
        if ( empty( $legacy_name ) || empty( $legacy_email ) || empty( $legacy_message ) ) {
            return new WP_Error( 'missing_fields', __( 'Bitte alle Pflichtfelder ausfuellen.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
        }

        if ( ! is_email( $legacy_email ) ) {
            return new WP_Error( 'invalid_email', __( 'Bitte eine gueltige E-Mail-Adresse angeben.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
        }

        $fields = array(
            array(
                'name'     => 'name',
                'label'    => __( 'Name', 'korn-und-hansemarkt' ),
                'type'     => 'text',
                'required' => true,
                'value'    => $legacy_name,
            ),
            array(
                'name'     => 'email',
                'label'    => __( 'E-Mail', 'korn-und-hansemarkt' ),
                'type'     => 'email',
                'required' => true,
                'value'    => $legacy_email,
            ),
            array(
                'name'     => 'message',
                'label'    => __( 'Nachricht', 'korn-und-hansemarkt' ),
                'type'     => 'textarea',
                'required' => true,
                'value'    => $legacy_message,
            ),
        );
    }

    if ( ! empty( $honeypot ) ) {
        return new WP_Error( 'spam_detected', __( 'Anfrage abgelehnt.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
    }

    $now = time();
    if ( $form_started <= 0 || ( $now - $form_started ) < 3 || ( $now - $form_started ) > 7200 ) {
        return new WP_Error( 'timing_invalid', __( 'Bitte Formular erneut ausfuellen.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
    }

    $client_ip = kuh_get_client_ip();
    if ( kuh_is_rate_limited( $client_ip ) ) {
        return new WP_Error( 'rate_limited', __( 'Zu viele Anfragen. Bitte spaeter erneut versuchen.', 'korn-und-hansemarkt' ), array( 'status' => 429 ) );
    }

    if ( kuh_is_hcaptcha_enabled() ) {
        $verify = kuh_verify_hcaptcha( $hcaptcha_token, $client_ip );
        if ( is_wp_error( $verify ) ) {
            return $verify;
        }
    }

    $default_recipient = get_theme_mod( 'kuh_contact_recipient', get_option( 'admin_email' ) );
    if ( ! is_email( $default_recipient ) ) {
        $default_recipient = get_option( 'admin_email' );
    }

    $recipient = $default_recipient;
    if ( is_email( $requested_recipient ) && ! empty( $recipient_token ) ) {
        if ( kuh_verify_recipient_token( $requested_recipient, $recipient_token ) ) {
            $recipient = $requested_recipient;
        }
    }

    // Serverseitige Formular-Konfiguration hat Vorrang vor den Client-Angaben.
    $form_config = kuh_get_verified_form_config( $form_id, $form_token );
    if ( $form_config ) {
        $confirmation_mail = (bool) $form_config['confirmationMail'];
    }

    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $mail_subject = sprintf(
        '[%s] %s',
        $site_name,
        ! empty( $subject ) ? $subject : __( 'Neue Kontaktanfrage', 'korn-und-hansemarkt' )
    );

    $body = array(
        'Neue Kontaktanfrage',
        '',
        'Betreff: ' . ( $subject ?: '-' ),
        '',
        'Formulardaten:',
    );

    $reply_name  = '';
    $reply_email = '';

    $data_lines = kuh_build_form_data_lines( $fields );
    $body       = array_merge( $body, $data_lines );

    foreach ( $fields as $field ) {
        $value_str = kuh_format_form_field_value( $field );

        if ( 'email' === $field['type'] && '' === $reply_email && is_email( $value_str ) ) {
            $reply_email = $value_str;
        }

        if ( in_array( $field['type'], array( 'text', 'textarea' ), true ) && '' === $reply_name && '' !== trim( $value_str ) ) {
            $reply_name = $value_str;
        }
    }

    $body[] = '';
    $body[] = 'IP: ' . $client_ip;
    $body[] = 'Zeitpunkt: ' . wp_date( 'Y-m-d H:i:s' );

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    if ( ! empty( $reply_email ) ) {
        $reply_to_name = ! empty( $reply_name ) ? $reply_name : __( 'Formular', 'korn-und-hansemarkt' );
        $headers[] = 'Reply-To: ' . sanitize_text_field( $reply_to_name ) . ' <' . sanitize_email( $reply_email ) . '>';
    }

    $sent = wp_mail( $recipient, $mail_subject, implode( "\n", $body ), $headers );
    if ( ! $sent ) {
        return new WP_Error( 'mail_failed', __( 'Nachricht konnte nicht gesendet werden. Bitte spaeter erneut versuchen.', 'korn-und-hansemarkt' ), array( 'status' => 500 ) );
    }

    kuh_increment_rate_limit( $client_ip );

    if ( $confirmation_mail && ! empty( $reply_email ) ) {
        $sender_name  = $reply_name ?: $reply_email;
        $timestamp    = wp_date( 'd.m.Y H:i' );
        $placeholders = array(
            '{name}'    => $sender_name,
            '{email}'   => $reply_email,
            '{betreff}' => $subject ?: '-',
            '{datum}'   => $timestamp,
            '{website}' => $site_name,
            '{daten}'   => implode( "\n", $data_lines ),
        );

        // Betreff und Text stammen aus der Formular-Konfiguration im Backend.
        $custom_subject = trim( (string) ( $form_config['confirmationSubject'] ?? '' ) );
        $custom_message = trim( (string) ( $form_config['confirmationMessage'] ?? '' ) );
        $include_data   = $form_config ? ! empty( $form_config['confirmationIncludeData'] ) : true;

        $confirm_subject = sprintf(
            '[%s] %s',
            $site_name,
            '' !== $custom_subject
                ? kuh_form_apply_placeholders( $custom_subject, $fields, $placeholders )
                : __( 'Empfangsbestaetigung deiner Anfrage', 'korn-und-hansemarkt' )
        );

        if ( '' !== $custom_message ) {
            $confirm_lines = explode( "\n", kuh_form_apply_placeholders( $custom_message, $fields, $placeholders ) );

            if ( $include_data ) {
                $confirm_lines[] = '';
                $confirm_lines[] = __( 'Deine Angaben:', 'korn-und-hansemarkt' );
                $confirm_lines[] = '';
                $confirm_lines   = array_merge( $confirm_lines, $data_lines );
                $confirm_lines[] = '';
                $confirm_lines[] = sprintf(
                    /* translators: %s: Datum/Uhrzeit */
                    __( 'Zeitpunkt: %s', 'korn-und-hansemarkt' ),
                    $timestamp
                );
            }
        } else {
            $confirm_lines = array(
                sprintf(
                    /* translators: %s: Name des Ansprechpartners */
                    __( 'Hallo %s,', 'korn-und-hansemarkt' ),
                    $sender_name
                ),
                '',
                __( 'wir haben deine Anfrage erhalten. Nachfolgend eine Kopie deiner Angaben:', 'korn-und-hansemarkt' ),
                '',
                'Betreff: ' . ( $subject ?: '-' ),
                '',
            );

            $confirm_lines   = array_merge( $confirm_lines, $data_lines );
            $confirm_lines[] = '';
            $confirm_lines[] = sprintf(
                /* translators: %s: Datum/Uhrzeit */
                __( 'Zeitpunkt: %s', 'korn-und-hansemarkt' ),
                $timestamp
            );
            $confirm_lines[] = '';
            $confirm_lines[] = __( 'Freundliche Gruesse', 'korn-und-hansemarkt' );
            $confirm_lines[] = $site_name;
        }

        $confirm_headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( is_email( $recipient ) ) {
            $confirm_headers[] = 'Reply-To: ' . $site_name . ' <' . sanitize_email( $recipient ) . '>';
        }

        $confirm_sent = wp_mail(
            $reply_email,
            $confirm_subject,
            implode( "\n", $confirm_lines ),
            $confirm_headers
        );

        if ( ! $confirm_sent && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[kuh contact-form] Bestaetigungsmail an ' . $reply_email . ' fehlgeschlagen.' );
        }
    }

    return new WP_REST_Response( array(
        'success' => true,
        'message' => __( 'Vielen Dank! Deine Nachricht wurde gesendet.', 'korn-und-hansemarkt' ),
    ), 200 );
}

/**
 * Token fuer eine Formular-ID erzeugen.
 *
 * Damit kann der Server die im Backend hinterlegte Konfiguration
 * (u. a. den Text der Bestaetigungsmail) laden, ohne dem Client
 * zu vertrauen.
 */
function kuh_create_form_token( $form_id ) {
    $form_id = (int) $form_id;
    if ( $form_id <= 0 ) {
        return '';
    }

    return hash_hmac( 'sha256', 'kuh_form:' . $form_id, wp_salt( 'auth' ) );
}

/**
 * Token einer Formular-ID pruefen.
 */
function kuh_verify_form_token( $form_id, $token ) {
    $expected = kuh_create_form_token( $form_id );
    if ( empty( $expected ) || empty( $token ) ) {
        return false;
    }

    return hash_equals( $expected, (string) $token );
}

/**
 * Konfiguration eines Formulars anhand von ID + Token laden.
 *
 * @return array|null Konfiguration oder null, wenn nicht verifizierbar.
 */
function kuh_get_verified_form_config( $form_id, $token ) {
    $form_id = (int) $form_id;

    if ( $form_id <= 0 || ! kuh_verify_form_token( $form_id, $token ) ) {
        return null;
    }

    if ( 'kuh_form' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
        return null;
    }

    return kuh_form_get_config( $form_id );
}

/**
 * Einen Feldwert fuer die Mail-Ausgabe formatieren.
 */
function kuh_format_form_field_value( $field ) {
    $type  = $field['type'] ?? 'text';
    $value = $field['value'] ?? '';

    if ( 'checkbox' === $type ) {
        return ! empty( $value ) ? __( 'Ja', 'korn-und-hansemarkt' ) : __( 'Nein', 'korn-und-hansemarkt' );
    }

    // ISO-Datum lesbar ausgeben (auch in der Kundenmail).
    if ( 'date' === $type && '' !== (string) $value ) {
        $date = \DateTime::createFromFormat( 'Y-m-d', (string) $value );
        if ( $date && $date->format( 'Y-m-d' ) === (string) $value ) {
            return $date->format( 'd.m.Y' );
        }
    }

    return (string) $value;
}

/**
 * Alle Formularangaben als "Label: Wert"-Zeilen aufbereiten.
 */
function kuh_build_form_data_lines( $fields ) {
    $lines = array();

    foreach ( $fields as $field ) {
        $label     = ! empty( $field['label'] ) ? $field['label'] : ( $field['name'] ?? '' );
        $value_str = kuh_format_form_field_value( $field );
        $lines[]   = sprintf( '%s: %s', $label, '' === trim( $value_str ) ? '-' : $value_str );
    }

    return $lines;
}

/**
 * Platzhalter im Backend-Text der Bestaetigungsmail ersetzen.
 *
 * Neben den festen Platzhaltern ({name}, {email}, ...) wird jedes
 * Formularfeld ueber {feld:feldname} verfuegbar gemacht.
 */
function kuh_form_apply_placeholders( $text, $fields, $context ) {
    $replacements = $context;

    foreach ( $fields as $field ) {
        $name = $field['name'] ?? '';
        if ( '' === $name ) {
            continue;
        }

        $value_str = kuh_format_form_field_value( $field );
        $replacements[ '{feld:' . $name . '}' ] = '' === trim( $value_str ) ? '-' : $value_str;
    }

    return strtr( (string) $text, $replacements );
}

/**
 * Token fuer blockbasierten E-Mail-Empfaenger erzeugen.
 */
function kuh_create_recipient_token( $recipient ) {
    $recipient = strtolower( trim( (string) $recipient ) );
    if ( empty( $recipient ) ) {
        return '';
    }

    return hash_hmac( 'sha256', $recipient, wp_salt( 'auth' ) );
}

/**
 * Token fuer blockbasierten E-Mail-Empfaenger validieren.
 */
function kuh_verify_recipient_token( $recipient, $token ) {
    $expected = kuh_create_recipient_token( $recipient );
    if ( empty( $expected ) || empty( $token ) ) {
        return false;
    }

    return hash_equals( $expected, (string) $token );
}

/**
 * Kontaktformular-Feldschema aus Block-Attributen bereinigen.
 */
function kuh_sanitize_contact_block_fields( $raw_fields ) {
    if ( ! is_array( $raw_fields ) ) {
        return array();
    }

    $allowed_types = array( 'text', 'email', 'number', 'tel', 'date', 'textarea', 'select', 'checkbox' );
    $clean         = array();

    foreach ( $raw_fields as $index => $field ) {
        if ( ! is_array( $field ) ) {
            continue;
        }

        $type = sanitize_key( $field['type'] ?? 'text' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'text';
        }

        $name = sanitize_key( $field['name'] ?? '' );
        if ( '' === $name ) {
            $name = 'feld_' . ( $index + 1 );
        }

        $label = sanitize_text_field( $field['label'] ?? $name );
        $id    = sanitize_key( $field['id'] ?? $name );
        if ( '' === $id ) {
            $id = $name;
        }

        $entry = array(
            'id'          => $id,
            'name'        => $name,
            'label'       => $label,
            'type'        => $type,
            'required'    => ! empty( $field['required'] ),
            'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
            'options'     => array(),
        );

        if ( 'select' === $type && ! empty( $field['options'] ) ) {
            $options = is_array( $field['options'] ) ? $field['options'] : array();
            foreach ( $options as $opt ) {
                $value = sanitize_text_field( $opt );
                if ( '' !== $value ) {
                    $entry['options'][] = $value;
                }
            }
        }

        $clean[] = $entry;
    }

    return array_values( array_slice( $clean, 0, 40 ) );
}

/**
 * Token fuer Feldschema erzeugen.
 */
function kuh_create_form_fields_token( $fields ) {
    $schema = kuh_sanitize_contact_block_fields( $fields );
    return hash_hmac( 'sha256', wp_json_encode( $schema ), wp_salt( 'auth' ) );
}

/**
 * Token fuer Feldschema pruefen.
 */
function kuh_verify_form_fields_token( $submitted_fields, $token ) {
    if ( empty( $token ) ) {
        return false;
    }

    $schema = array();
    foreach ( $submitted_fields as $field ) {
        $schema[] = array(
            'id'          => $field['id'] ?? '',
            'name'        => $field['name'] ?? '',
            'label'       => $field['label'] ?? '',
            'type'        => $field['type'] ?? 'text',
            'required'    => ! empty( $field['required'] ),
            'placeholder' => $field['placeholder'] ?? '',
            'options'     => is_array( $field['options'] ?? null ) ? $field['options'] : array(),
        );
    }

    $expected = kuh_create_form_fields_token( $schema );
    return hash_equals( $expected, (string) $token );
}

/**
 * Eingereichte dynamische Felder bereinigen.
 */
function kuh_sanitize_contact_submitted_fields( $raw_fields ) {
    $fields = kuh_sanitize_contact_block_fields( $raw_fields );
    $clean  = array();

    foreach ( $fields as $index => $field ) {
        $value = $raw_fields[ $index ]['value'] ?? '';

        if ( 'checkbox' === $field['type'] ) {
            $field['value'] = rest_sanitize_boolean( $value );
        } elseif ( 'textarea' === $field['type'] ) {
            $field['value'] = sanitize_textarea_field( (string) $value );
        } else {
            $field['value'] = sanitize_text_field( (string) $value );
        }

        $clean[] = $field;
    }

    return $clean;
}

/**
 * hCaptcha gegen die Verify-API pruefen.
 */
function kuh_verify_hcaptcha( $token, $remote_ip ) {
    $secret_key = kuh_get_hcaptcha_secret_key();
    if ( empty( $secret_key ) ) {
        return new WP_Error( 'hcaptcha_not_configured', __( 'Spam-Schutz ist nicht korrekt konfiguriert.', 'korn-und-hansemarkt' ), array( 'status' => 500 ) );
    }

    if ( empty( $token ) ) {
        return new WP_Error( 'hcaptcha_missing', __( 'Bitte bestaetige die Captcha-Pruefung.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
    }

    $response = wp_remote_post( 'https://hcaptcha.com/siteverify', array(
        'timeout' => 8,
        'body'    => array(
            'secret'   => $secret_key,
            'response' => $token,
            'remoteip' => $remote_ip,
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'hcaptcha_unreachable', __( 'Captcha-Validierung derzeit nicht verfuegbar.', 'korn-und-hansemarkt' ), array( 'status' => 503 ) );
    }

    $payload = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $payload['success'] ) ) {
        return new WP_Error( 'hcaptcha_failed', __( 'Captcha-Pruefung fehlgeschlagen.', 'korn-und-hansemarkt' ), array( 'status' => 400 ) );
    }

    return true;
}

/**
 * Einfache IP-Ermittlung fuer Logging/Rate-Limit.
 */
function kuh_get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $ip    = trim( $parts[0] );
    }

    return sanitize_text_field( $ip );
}

/**
 * Limitiert auf 5 erfolgreiche Mails pro 15 Minuten pro IP.
 */
function kuh_is_rate_limited( $ip ) {
    $key   = 'kuh_cf_rate_' . md5( $ip );
    $count = (int) get_transient( $key );

    return $count >= 5;
}

/**
 * Zaehler fuer Rate-Limit erhoehen.
 */
function kuh_increment_rate_limit( $ip ) {
    $key   = 'kuh_cf_rate_' . md5( $ip );
    $count = (int) get_transient( $key );
    set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
}

/**
 * Shortcode fuer SPA-Kontaktformular-Container.
 * Beispiel: [kuh_contact_form subject="Kontakt Korn- und Hansemarkt"]
 */
function kuh_contact_form_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'subject'   => __( 'Kontaktanfrage', 'korn-und-hansemarkt' ),
        'recipient' => '',
    ), $atts, 'kuh_contact_form' );

    $recipient = sanitize_email( $atts['recipient'] );
    $fields    = kuh_sanitize_contact_block_fields( array(
        array(
            'id'       => 'name',
            'name'     => 'name',
            'label'    => __( 'Name', 'korn-und-hansemarkt' ),
            'type'     => 'text',
            'required' => true,
        ),
        array(
            'id'       => 'email',
            'name'     => 'email',
            'label'    => __( 'E-Mail', 'korn-und-hansemarkt' ),
            'type'     => 'email',
            'required' => true,
        ),
        array(
            'id'       => 'message',
            'name'     => 'message',
            'label'    => __( 'Nachricht', 'korn-und-hansemarkt' ),
            'type'     => 'textarea',
            'required' => true,
        ),
    ) );

    $payload = wp_json_encode( array(
        'subject'        => sanitize_text_field( $atts['subject'] ),
        'recipientEmail' => $recipient,
        'recipientToken' => kuh_create_recipient_token( $recipient ),
        'fields'         => $fields,
        'fieldsToken'    => kuh_create_form_fields_token( $fields ),
    ) );

    return sprintf(
        '<div data-kuh-contact-form="%s"></div><noscript><p>%s</p></noscript>',
        esc_attr( $payload ),
        esc_html__( 'Bitte JavaScript aktivieren, um das Kontaktformular zu nutzen.', 'korn-und-hansemarkt' )
    );
}
add_shortcode( 'kuh_contact_form', 'kuh_contact_form_shortcode' );
