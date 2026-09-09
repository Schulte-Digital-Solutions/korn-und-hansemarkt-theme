<?php
/**
 * Zentrale Formular-Verwaltung: CPT "kuh_form" mit Builder-Metabox.
 *
 * Formulare werden seitenuebergreifend gespeichert und im
 * Kontaktformular-Block (kuh/contact-form) ueber formId referenziert.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default-Konfiguration eines Formulars.
 */
function kuh_form_default_config() {
    return array(
        'subject'          => __( 'Kontaktanfrage', 'korn-und-hansemarkt' ),
        'recipientEmail'   => '',
        'formTitle'        => '',
        'formIntro'        => '',
        'submitLabel'      => __( 'Nachricht senden', 'korn-und-hansemarkt' ),
        'successMessage'   => __( 'Vielen Dank! Deine Nachricht wurde gesendet.', 'korn-und-hansemarkt' ),
        'privacyNote'      => '',
        'confirmationMail' => false,
        'fields'           => array(),
    );
}

/**
 * Beitragstyp "Formulare" registrieren.
 */
function kuh_register_form_cpt() {
    register_post_type( 'kuh_form', array(
        'labels'       => array(
            'name'          => __( 'Formulare', 'korn-und-hansemarkt' ),
            'singular_name' => __( 'Formular', 'korn-und-hansemarkt' ),
            'add_new_item'  => __( 'Neues Formular erstellen', 'korn-und-hansemarkt' ),
            'edit_item'     => __( 'Formular bearbeiten', 'korn-und-hansemarkt' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-feedback',
        'supports'     => array( 'title' ),
    ) );
}
add_action( 'init', 'kuh_register_form_cpt' );

/**
 * Builder-Metabox registrieren.
 */
function kuh_form_add_meta_box() {
    add_meta_box(
        'kuh-form-builder',
        __( 'Formular-Definition', 'korn-und-hansemarkt' ),
        'kuh_form_render_meta_box',
        'kuh_form',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'kuh_form_add_meta_box' );

/**
 * Gespeicherte Konfiguration eines Formulars laden (sanitisiert).
 */
function kuh_form_get_config( $post_id ) {
    $raw = get_post_meta( $post_id, 'kuh_form_config', true );
    return kuh_form_sanitize_config( is_array( $raw ) ? $raw : array() );
}

/**
 * Konfiguration bereinigen (Labels/Texte + Felder via contact-form Sanitizer).
 */
function kuh_form_sanitize_config( $config ) {
    $defaults = kuh_form_default_config();
    $config   = is_array( $config ) ? $config : array();

    $clean = array(
        'subject'          => sanitize_text_field( $config['subject'] ?? $defaults['subject'] ),
        'recipientEmail'   => sanitize_email( $config['recipientEmail'] ?? '' ),
        'formTitle'        => sanitize_text_field( $config['formTitle'] ?? '' ),
        'formIntro'        => sanitize_textarea_field( $config['formIntro'] ?? '' ),
        'submitLabel'      => sanitize_text_field( $config['submitLabel'] ?? $defaults['submitLabel'] ),
        'successMessage'   => sanitize_text_field( $config['successMessage'] ?? $defaults['successMessage'] ),
        'privacyNote'      => sanitize_textarea_field( $config['privacyNote'] ?? '' ),
        'confirmationMail' => ! empty( $config['confirmationMail'] ),
        'fields'           => kuh_sanitize_contact_block_fields( $config['fields'] ?? array() ),
    );

    return $clean;
}

/**
 * Metabox rendern: JSON-Versteckfeld + JS-Builder.
 */
function kuh_form_render_meta_box( $post ) {
    wp_nonce_field( 'kuh_form_save', 'kuh_form_nonce' );

    $config = kuh_form_get_config( $post->ID );
    $i18n   = array(
        'field'        => __( 'Feld', 'korn-und-hansemarkt' ),
        'name'         => __( 'Feldname (technisch)', 'korn-und-hansemarkt' ),
        'label'        => __( 'Label', 'korn-und-hansemarkt' ),
        'type'         => __( 'Typ', 'korn-und-hansemarkt' ),
        'placeholder'  => __( 'Platzhalter', 'korn-und-hansemarkt' ),
        'options'      => __( 'Select-Optionen (eine pro Zeile)', 'korn-und-hansemarkt' ),
        'width'        => __( 'Breite (Desktop)', 'korn-und-hansemarkt' ),
        'required'     => __( 'Pflichtfeld', 'korn-und-hansemarkt' ),
        'remove'       => __( 'Feld entfernen', 'korn-und-hansemarkt' ),
        'add'          => __( 'Feld hinzufuegen', 'korn-und-hansemarkt' ),
        'typeOptions'  => array(
            array( 'value' => 'text', 'label' => __( 'Text', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'email', 'label' => __( 'E-Mail', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'number', 'label' => __( 'Nummer', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'tel', 'label' => __( 'Telefon', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'date', 'label' => __( 'Datum', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'textarea', 'label' => __( 'Textarea', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'select', 'label' => __( 'Select', 'korn-und-hansemarkt' ) ),
            array( 'value' => 'checkbox', 'label' => __( 'Checkbox', 'korn-und-hansemarkt' ) ),
        ),
        'widthOptions' => array(
            array( 'value' => 1, 'label' => '25 %' ),
            array( 'value' => 2, 'label' => '50 %' ),
            array( 'value' => 4, 'label' => '100 %' ),
        ),
    );
    ?>
    <div class="kuh-form-builder">
        <h4><?php esc_html_e( 'Versand', 'korn-und-hansemarkt' ); ?></h4>
        <p>
            <label for="kuh-form-subject"><strong><?php esc_html_e( 'Betreff', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <input type="text" id="kuh-form-subject" class="widefat" value="<?php echo esc_attr( $config['subject'] ); ?>">
        </p>
        <p>
            <label for="kuh-form-recipient"><strong><?php esc_html_e( 'Empfaenger E-Mail', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <input type="email" id="kuh-form-recipient" class="widefat" value="<?php echo esc_attr( $config['recipientEmail'] ); ?>">
            <span class="description"><?php esc_html_e( 'Leer lassen fuer die Standard-Empfaengeradresse aus den Theme-Einstellungen.', 'korn-und-hansemarkt' ); ?></span>
        </p>
        <p>
            <label>
                <input type="checkbox" id="kuh-form-confirmation" <?php checked( $config['confirmationMail'] ); ?>>
                <strong><?php esc_html_e( 'Bestaetigungsmail an Absender senden (Kopie der Angaben)', 'korn-und-hansemarkt' ); ?></strong>
            </label>
        </p>

        <h4><?php esc_html_e( 'Texte', 'korn-und-hansemarkt' ); ?></h4>
        <p>
            <label for="kuh-form-title"><strong><?php esc_html_e( 'Formular-Titel', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <input type="text" id="kuh-form-title" class="widefat" value="<?php echo esc_attr( $config['formTitle'] ); ?>">
        </p>
        <p>
            <label for="kuh-form-intro"><strong><?php esc_html_e( 'Einleitungstext', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <textarea id="kuh-form-intro" class="widefat" rows="3"><?php echo esc_textarea( $config['formIntro'] ); ?></textarea>
        </p>
        <p>
            <label for="kuh-form-submit"><strong><?php esc_html_e( 'Button-Text', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <input type="text" id="kuh-form-submit" class="widefat" value="<?php echo esc_attr( $config['submitLabel'] ); ?>">
        </p>
        <p>
            <label for="kuh-form-success"><strong><?php esc_html_e( 'Erfolgsmeldung', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <input type="text" id="kuh-form-success" class="widefat" value="<?php echo esc_attr( $config['successMessage'] ); ?>">
        </p>
        <p>
            <label for="kuh-form-privacy"><strong><?php esc_html_e( 'Datenschutz-Hinweis', 'korn-und-hansemarkt' ); ?></strong></label><br>
            <textarea id="kuh-form-privacy" class="widefat" rows="3"><?php echo esc_textarea( $config['privacyNote'] ); ?></textarea>
        </p>

        <h4><?php esc_html_e( 'Felder', 'korn-und-hansemarkt' ); ?></h4>
        <div id="kuh-form-fields"></div>
        <p class="description">
            <?php esc_html_e( 'Unterschriftsfelder entfallen – eine Pflicht-Checkbox ersetzt die Unterschrift (SEPA-E-Mandat).', 'korn-und-hansemarkt' ); ?>
        </p>
    </div>
    <input type="hidden" id="kuh-form-config-json" name="kuh_form_config_json" value="">
    <script id="kuh-form-builder-data" type="application/json"><?php echo wp_json_encode( array( 'config' => $config, 'i18n' => $i18n ) ); ?></script>
    <?php
}

/**
 * Konfiguration beim Speichern sichern.
 */
function kuh_form_save_config( $post_id ) {
    if ( ! isset( $_POST['kuh_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kuh_form_nonce'] ) ), 'kuh_form_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $json = isset( $_POST['kuh_form_config_json'] ) ? wp_unslash( (string) $_POST['kuh_form_config_json'] ) : '';
    $raw  = json_decode( $json, true );

    if ( ! is_array( $raw ) ) {
        return;
    }

    update_post_meta( $post_id, 'kuh_form_config', kuh_form_sanitize_config( $raw ) );
}
add_action( 'save_post_kuh_form', 'kuh_form_save_config' );

/**
 * Builder-Assets (CSS/JS) nur auf dem CPT-Bildschirm laden.
 */
function kuh_form_admin_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'kuh_form' !== $screen->post_type ) {
        return;
    }

    $handle = 'kuh-form-builder';
    wp_register_style( $handle, KUH_THEME_URI . '/inc/form-builder-admin.css', array(), KUH_THEME_VERSION );
    wp_register_script( $handle, KUH_THEME_URI . '/inc/form-builder-admin.js', array(), KUH_THEME_VERSION, true );
    wp_enqueue_style( $handle );
    wp_enqueue_script( $handle );
}
add_action( 'admin_enqueue_scripts', 'kuh_form_admin_assets' );

/**
 * Vier grundlegende Formularvorlagen einmalig anlegen.
 *
 * Die Migration ist idempotent: Bereits vorhandene Formular-Beitraege bleiben
 * unveraendert; alte transliterierte Standardtitel werden einmalig korrigiert.
 */
function kuh_form_seed_defaults() {
    $forms = array(
        array(
            'title'  => 'Beitrittserklärung',
            'legacy_titles' => array( 'Beitrittserklaerung' ),
            'config' => array(
                'subject'          => 'Beitrittsantrag Korn- und Hansemarkt e.V.',
                'formTitle'        => 'Beitrittserklärung',
                'formIntro'        => 'Bitte fuelle alle Pflichtfelder aus. Deine Angaben werden vertraulich verarbeitet.',
                'confirmationMail' => true,
                'fields'           => array(
                    array( 'name' => 'name', 'label' => 'Name, Vorname', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'beruf_firma', 'label' => 'Beruf / Firma', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'strasse', 'label' => 'Strasse, Hausnummer', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'plz_ort', 'label' => 'PLZ, Ort', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'telefon', 'label' => 'Telefon', 'type' => 'tel', 'cols' => 2 ),
                    array( 'name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'kreditinstitut', 'label' => 'Kreditinstitut', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'bic', 'label' => 'BIC', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'angaben_bestaetigt', 'label' => 'Ich bestaetige die Richtigkeit meiner Angaben.', 'type' => 'checkbox', 'required' => true, 'cols' => 4 ),
                    array( 'name' => 'sepa_mandat', 'label' => 'Ich ermaechtige den Korn- und Hansemarkt e.V., den Mitgliedsbeitrag per SEPA-Lastschrift einzuziehen.', 'type' => 'checkbox', 'required' => true, 'cols' => 4 ),
                ),
            ),
        ),
        array(
            'title'  => 'Künstlervertrag',
            'legacy_titles' => array( 'Kuenstlervertrag' ),
            'config' => array(
                'subject'          => 'Kuenstlervertrag',
                'formTitle'        => 'Künstlervertrag',
                'confirmationMail' => true,
                'fields'           => array(
                    array( 'name' => 'name', 'label' => 'Name, Vorname', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'kunstler_handwerker', 'label' => 'Kuenstlerin / Handwerkerin', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'strasse', 'label' => 'Strasse, Hausnummer', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'plz_ort', 'label' => 'PLZ, Ort', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'telefon', 'label' => 'Telefon', 'type' => 'tel', 'cols' => 2 ),
                    array( 'name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'auftritt_freitag', 'label' => 'Auftritt Freitag (Datum)', 'type' => 'date', 'cols' => 2 ),
                    array( 'name' => 'auftritt_samstag', 'label' => 'Auftritt Samstag (Datum)', 'type' => 'date', 'cols' => 2 ),
                    array( 'name' => 'auftritt_sonntag', 'label' => 'Auftritt Sonntag (Datum)', 'type' => 'date', 'cols' => 2 ),
                    array( 'name' => 'auftritt_zeiten', 'label' => 'Auftrittszeiten / weitere Angaben', 'type' => 'textarea', 'cols' => 4 ),
                    array( 'name' => 'walking_act', 'label' => 'Walking-Act', 'type' => 'select', 'options' => array( 'Nein', 'Ja' ), 'cols' => 2 ),
                    array( 'name' => 'walking_act_thema', 'label' => 'Walking-Act Thema', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'honorar', 'label' => 'Honorar (EUR)', 'type' => 'number', 'cols' => 2 ),
                    array( 'name' => 'uebernachtung', 'label' => 'Uebernachtung / Zimmer', 'type' => 'select', 'options' => array( 'Keine', 'Einzelzimmer', 'Doppelzimmer' ), 'cols' => 2 ),
                    array( 'name' => 'sonstiges', 'label' => 'Sonstiges', 'type' => 'textarea', 'cols' => 4 ),
                    array( 'name' => 'angaben_bestaetigt', 'label' => 'Ich bestaetige die Richtigkeit meiner Angaben.', 'type' => 'checkbox', 'required' => true, 'cols' => 4 ),
                ),
            ),
        ),
        array(
            'title'  => 'Standanmeldung',
            'legacy_titles' => array(),
            'config' => array(
                'subject'          => 'Standanmeldung',
                'formTitle'        => 'Standanmeldung',
                'confirmationMail' => true,
                'fields'           => array(
                    array( 'name' => 'name', 'label' => 'Name, Vorname', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'gewerbe', 'label' => 'Gewerbe / Angebot', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'strasse', 'label' => 'Strasse, Hausnummer', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'plz_ort', 'label' => 'PLZ, Ort', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'telefon', 'label' => 'Telefon', 'type' => 'tel', 'cols' => 2 ),
                    array( 'name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'stand', 'label' => 'Stand', 'type' => 'select', 'options' => array( 'Leihstand', 'Eigener Stand' ), 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'stand_masse', 'label' => 'Masse eigener Stand', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'strom', 'label' => 'Strom', 'type' => 'checkbox', 'cols' => 1 ),
                    array( 'name' => 'kraftstrom', 'label' => 'Kraftstrom', 'type' => 'checkbox', 'cols' => 1 ),
                    array( 'name' => 'wasser', 'label' => 'Wasser', 'type' => 'checkbox', 'cols' => 1 ),
                    array( 'name' => 'beleuchtung', 'label' => 'Beleuchtung', 'type' => 'checkbox', 'cols' => 1 ),
                    array( 'name' => 'personen_betrieb', 'label' => 'Personenanzahl (Betrieb)', 'type' => 'number', 'cols' => 2 ),
                    array( 'name' => 'personen_aufbau', 'label' => 'Personenanzahl (Aufbau)', 'type' => 'number', 'cols' => 2 ),
                    array( 'name' => 'sonstiges', 'label' => 'Sonstiges', 'type' => 'textarea', 'cols' => 4 ),
                    array( 'name' => 'angaben_bestaetigt', 'label' => 'Ich bestaetige die Richtigkeit meiner Angaben.', 'type' => 'checkbox', 'required' => true, 'cols' => 4 ),
                ),
            ),
        ),
        array(
            'title'  => 'Mitgliedschaft kündigen',
            'legacy_titles' => array( 'Mitgliedschaft kuendigen' ),
            'config' => array(
                'subject'          => 'Kuendigung Mitgliedschaft',
                'formTitle'        => 'Mitgliedschaft kündigen',
                'confirmationMail' => true,
                'fields'           => array(
                    array( 'name' => 'name', 'label' => 'Name, Vorname', 'type' => 'text', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'mitgliedsnummer', 'label' => 'Mitgliedsnummer (falls bekannt)', 'type' => 'text', 'cols' => 2 ),
                    array( 'name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'kuendigung_zum', 'label' => 'Kuendigung zum (Datum)', 'type' => 'date', 'required' => true, 'cols' => 2 ),
                    array( 'name' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'textarea', 'cols' => 4 ),
                    array( 'name' => 'kuendigung_bestaetigt', 'label' => 'Ich kuendige meine Mitgliedschaft hiermit fristgerecht.', 'type' => 'checkbox', 'required' => true, 'cols' => 4 ),
                ),
            ),
        ),
    );

    foreach ( $forms as $form ) {
        $existing = get_page_by_title( $form['title'], OBJECT, 'kuh_form' );
        if ( ! $existing && ! empty( $form['legacy_titles'] ) ) {
            foreach ( $form['legacy_titles'] as $legacy_title ) {
                $existing = get_page_by_title( $legacy_title, OBJECT, 'kuh_form' );
                if ( $existing ) {
                    wp_update_post( array(
                        'ID'         => $existing->ID,
                        'post_title' => $form['title'],
                    ) );
                    break;
                }
            }
        }

        if ( $existing ) {
            continue;
        }

        $post_id = wp_insert_post(
            array(
                'post_title'  => $form['title'],
                'post_type'   => 'kuh_form',
                'post_status' => 'publish',
            ),
            true
        );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'kuh_form_config', kuh_form_sanitize_config( $form['config'] ) );
        }
    }

    update_option( 'kuh_form_defaults_seeded', current_time( 'mysql' ), false );
}
add_action( 'admin_init', 'kuh_form_seed_defaults' );
