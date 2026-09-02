<?php
/**
 * Programm: Custom Post Types „Act" und „Programmpunkt" + Taxonomie „Bühne".
 *
 * Datenmodell des Bühnenplans:
 *   kuh_act   – ein Act (Band, Gruppe, Walking Act). Tritt mehrfach auf.
 *   kuh_stage – Taxonomie der Bühnen/Spielorte, mit Reihenfolge und Karten-Anker.
 *   kuh_slot  – ein einzelner Programmpunkt: Datum + Uhrzeit + Bühne + Act.
 *
 * Interne Positionen (Aufbau, Soundcheck, Umbau) werden bewusst nicht abgebildet –
 * der Bühnenplan hier ist die öffentliche Sicht.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPTs und Taxonomie registrieren
 */
function kuh_register_program_cpts() {
    register_post_type( 'kuh_act', array(
        'labels'        => array(
            'name'               => __( 'Acts', 'korn-und-hansemarkt' ),
            'singular_name'      => __( 'Act', 'korn-und-hansemarkt' ),
            'all_items'          => __( 'Acts', 'korn-und-hansemarkt' ),
            'add_new'            => __( 'Neuen Act anlegen', 'korn-und-hansemarkt' ),
            'add_new_item'       => __( 'Neuen Act anlegen', 'korn-und-hansemarkt' ),
            'edit_item'          => __( 'Act bearbeiten', 'korn-und-hansemarkt' ),
            'view_item'          => __( 'Act ansehen', 'korn-und-hansemarkt' ),
            'search_items'       => __( 'Acts suchen', 'korn-und-hansemarkt' ),
            'not_found'          => __( 'Keine Acts gefunden', 'korn-und-hansemarkt' ),
            'not_found_in_trash' => __( 'Keine Acts im Papierkorb', 'korn-und-hansemarkt' ),
            // Oberster Menüpunkt der ganzen Programm-Verwaltung.
            'menu_name'          => __( 'Programm', 'korn-und-hansemarkt' ),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_rest'  => true,
        'rest_base'     => 'acts',
        'menu_icon'     => 'dashicons-calendar-alt',
        'menu_position' => 26,
        // page-attributes liefert das Feld „Reihenfolge" (menu_order), mit dem sich
        // die Sortierung der Acts-Übersicht von Hand festlegen lässt.
        'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
        'has_archive'   => false,
        'rewrite'       => false,
    ) );

    register_post_type( 'kuh_slot', array(
        'labels'        => array(
            'name'               => __( 'Programmpunkte', 'korn-und-hansemarkt' ),
            'singular_name'      => __( 'Programmpunkt', 'korn-und-hansemarkt' ),
            'all_items'          => __( 'Programmpunkte', 'korn-und-hansemarkt' ),
            'add_new'            => __( 'Neuen Programmpunkt anlegen', 'korn-und-hansemarkt' ),
            'add_new_item'       => __( 'Neuen Programmpunkt anlegen', 'korn-und-hansemarkt' ),
            'edit_item'          => __( 'Programmpunkt bearbeiten', 'korn-und-hansemarkt' ),
            'search_items'       => __( 'Programmpunkte suchen', 'korn-und-hansemarkt' ),
            'not_found'          => __( 'Keine Programmpunkte gefunden', 'korn-und-hansemarkt' ),
            'menu_name'          => __( 'Programmpunkte', 'korn-und-hansemarkt' ),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_rest'  => true,
        'rest_base'     => 'slots',
        'show_in_menu'  => 'edit.php?post_type=kuh_act',
        'supports'      => array( 'title' ),
        'has_archive'   => false,
        'rewrite'       => false,
    ) );

    register_taxonomy( 'kuh_stage', array( 'kuh_slot' ), array(
        'labels'             => array(
            'name'          => __( 'Bühnen', 'korn-und-hansemarkt' ),
            'singular_name' => __( 'Bühne', 'korn-und-hansemarkt' ),
            'all_items'     => __( 'Bühnen', 'korn-und-hansemarkt' ),
            'add_new_item'  => __( 'Neue Bühne anlegen', 'korn-und-hansemarkt' ),
            'edit_item'     => __( 'Bühne bearbeiten', 'korn-und-hansemarkt' ),
            'menu_name'     => __( 'Bühnen', 'korn-und-hansemarkt' ),
        ),
        'public'             => false,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'show_in_menu'       => true,
        'show_in_quick_edit' => true,
        'hierarchical'       => false,
        'show_admin_column'  => true,
        'rewrite'            => false,
    ) );

    $auth = function () {
        return current_user_can( 'edit_posts' );
    };

    foreach ( array( 'kuh_slot_date', 'kuh_slot_start', 'kuh_slot_end', 'kuh_slot_note', 'kuh_slot_key' ) as $key ) {
        register_post_meta( 'kuh_slot', $key, array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'default'       => '',
            'auth_callback' => $auth,
        ) );
    }

    register_post_meta( 'kuh_slot', 'kuh_slot_act', array(
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'integer',
        'default'       => 0,
        'auth_callback' => $auth,
    ) );

    foreach ( array( 'kuh_act_genre', 'kuh_act_url', 'kuh_act_slug', 'kuh_act_color' ) as $key ) {
        register_post_meta( 'kuh_act', $key, array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'default'       => '',
            'auth_callback' => $auth,
        ) );
    }
}
add_action( 'init', 'kuh_register_program_cpts' );

/**
 * Farbpalette für Acts.
 *
 * Die Hex-Werte dienen nur der Vorschau im Backend – im Frontend kommen die
 * Farben aus dem style-Block von src/components/ProgramSchedule.svelte, wo es
 * zusätzlich Dark-Mode-Varianten gibt. Beide Listen müssen synchron bleiben.
 *
 * @return array<string,array{label:string,hex:string}>
 */
function kuh_get_act_palette() {
    return array(
        '0' => array( 'label' => __( 'Grün', 'korn-und-hansemarkt' ),    'hex' => '#d7f0d9' ),
        '1' => array( 'label' => __( 'Blau', 'korn-und-hansemarkt' ),    'hex' => '#cfe5ff' ),
        '2' => array( 'label' => __( 'Orange', 'korn-und-hansemarkt' ),  'hex' => '#ffdcc2' ),
        '3' => array( 'label' => __( 'Violett', 'korn-und-hansemarkt' ), 'hex' => '#e7ddff' ),
        '4' => array( 'label' => __( 'Mint', 'korn-und-hansemarkt' ),    'hex' => '#bcf0c9' ),
        '5' => array( 'label' => __( 'Rosa', 'korn-und-hansemarkt' ),    'hex' => '#ffd8e4' ),
        '6' => array( 'label' => __( 'Gold', 'korn-und-hansemarkt' ),    'hex' => '#fcdd83' ),
        '7' => array( 'label' => __( 'Petrol', 'korn-und-hansemarkt' ),  'hex' => '#cfe8e4' ),
    );
}

/**
 * Untermenüpunkt „Bühnen" ergänzen.
 *
 * WordPress hängt Taxonomie-Untermenüs an das Menü ihres Post-Types. Da
 * `kuh_slot` per `show_in_menu` auf das Programm-Menü umgeleitet wird, entfällt
 * dieser Automatismus – deshalb hier von Hand.
 */
function kuh_add_stage_submenu() {
    add_submenu_page(
        'edit.php?post_type=kuh_act',
        __( 'Bühnen', 'korn-und-hansemarkt' ),
        __( 'Bühnen', 'korn-und-hansemarkt' ),
        'manage_categories',
        'edit-tags.php?taxonomy=kuh_stage&post_type=kuh_slot'
    );
}
add_action( 'admin_menu', 'kuh_add_stage_submenu' );

/**
 * „Bühnen" im Programm-Menü als aktiv markieren, wenn die Taxonomie bearbeitet wird.
 *
 * @param string $file Aktuelle Parent-Datei.
 * @return string
 */
function kuh_highlight_stage_submenu( $file ) {
    global $pagenow, $taxnow;

    if ( 'edit-tags.php' === $pagenow && 'kuh_stage' === $taxnow ) {
        return 'edit.php?post_type=kuh_act';
    }

    return $file;
}
add_filter( 'parent_file', 'kuh_highlight_stage_submenu' );

/**
 * Doppelten Untermenüpunkt „Neuen Act anlegen" entfernen – das Anlegen läuft
 * über den Button in der Acts-Übersicht.
 */
function kuh_clean_program_submenu() {
    remove_submenu_page( 'edit.php?post_type=kuh_act', 'post-new.php?post_type=kuh_act' );
}
add_action( 'admin_menu', 'kuh_clean_program_submenu', 999 );

/* -------------------------------------------------------------------------
 * Term-Meta der Bühnen (Reihenfolge, Karten-Anker, Untertitel)
 * ---------------------------------------------------------------------- */

/**
 * Formularfelder für Bühnen-Terms ausgeben.
 *
 * @param WP_Term|string $term Term beim Bearbeiten, Taxonomie-Slug beim Anlegen.
 */
function kuh_stage_term_fields( $term ) {
    $is_edit  = $term instanceof WP_Term;
    $order    = $is_edit ? (int) get_term_meta( $term->term_id, 'kuh_stage_order', true ) : 10;
    $location = $is_edit ? (string) get_term_meta( $term->term_id, 'kuh_stage_location', true ) : '';
    $subtitle = $is_edit ? (string) get_term_meta( $term->term_id, 'kuh_stage_subtitle', true ) : '';

    wp_nonce_field( 'kuh_stage_meta', 'kuh_stage_meta_nonce' );

    $pois = function_exists( 'kuh_get_event_map_poi_choices' ) ? kuh_get_event_map_poi_choices() : array();

    $fields = array(
        array( 'kuh_stage_order', __( 'Reihenfolge', 'korn-und-hansemarkt' ), 'number', $order, __( 'Spaltenreihenfolge im Bühnenplan (kleinere Zahl = weiter links).', 'korn-und-hansemarkt' ) ),
        array( 'kuh_stage_subtitle', __( 'Untertitel', 'korn-und-hansemarkt' ), 'text', $subtitle, __( 'Optional, z. B. „auf dem Gelände".', 'korn-und-hansemarkt' ) ),
    );

    foreach ( $fields as $f ) {
        list( $name, $label, $type, $value, $desc ) = $f;
        if ( $is_edit ) {
            echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
        } else {
            echo '<div class="form-field"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
        }
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" />',
            esc_attr( $type ),
            esc_attr( $name ),
            esc_attr( $name ),
            esc_attr( $value )
        );
        echo '<p class="description">' . esc_html( $desc ) . '</p>';
        echo $is_edit ? '</td></tr>' : '</div>';
    }

    // Karten-Anker: Auswahl aus den POIs des Geländeplans.
    if ( $is_edit ) {
        echo '<tr class="form-field"><th scope="row"><label for="kuh_stage_location">' . esc_html__( 'Ort auf der Karte', 'korn-und-hansemarkt' ) . '</label></th><td>';
    } else {
        echo '<div class="form-field"><label for="kuh_stage_location">' . esc_html__( 'Ort auf der Karte', 'korn-und-hansemarkt' ) . '</label>';
    }
    echo '<select id="kuh_stage_location" name="kuh_stage_location">';
    echo '<option value="">' . esc_html__( '— keine Verlinkung —', 'korn-und-hansemarkt' ) . '</option>';
    foreach ( $pois as $poi_id => $poi_label ) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr( $poi_id ),
            selected( $location, $poi_id, false ),
            esc_html( $poi_label )
        );
    }
    if ( $location && ! isset( $pois[ $location ] ) ) {
        printf(
            '<option value="%1$s" selected>%1$s (nicht in der Karte gefunden)</option>',
            esc_attr( $location )
        );
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Verlinkt die Bühne im Bühnenplan auf den Geländeplan und springt dort direkt zu diesem Ort.', 'korn-und-hansemarkt' ) . '</p>';
    echo $is_edit ? '</td></tr>' : '</div>';
}
add_action( 'kuh_stage_add_form_fields', 'kuh_stage_term_fields' );
add_action( 'kuh_stage_edit_form_fields', 'kuh_stage_term_fields' );

/**
 * Term-Meta der Bühne speichern.
 *
 * @param int $term_id Term-ID.
 */
function kuh_save_stage_term_meta( $term_id ) {
    if ( ! isset( $_POST['kuh_stage_meta_nonce'] ) ||
         ! wp_verify_nonce( sanitize_key( $_POST['kuh_stage_meta_nonce'] ), 'kuh_stage_meta' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    if ( isset( $_POST['kuh_stage_order'] ) ) {
        update_term_meta( $term_id, 'kuh_stage_order', absint( $_POST['kuh_stage_order'] ) );
    }
    if ( isset( $_POST['kuh_stage_subtitle'] ) ) {
        update_term_meta( $term_id, 'kuh_stage_subtitle', sanitize_text_field( wp_unslash( $_POST['kuh_stage_subtitle'] ) ) );
    }
    if ( isset( $_POST['kuh_stage_location'] ) ) {
        update_term_meta( $term_id, 'kuh_stage_location', sanitize_title( wp_unslash( $_POST['kuh_stage_location'] ) ) );
    }
}
add_action( 'created_kuh_stage', 'kuh_save_stage_term_meta' );
add_action( 'edited_kuh_stage', 'kuh_save_stage_term_meta' );

/**
 * Spalten der Bühnen-Übersicht: Reihenfolge und Karten-Anker sichtbar machen.
 *
 * @param array $columns Bestehende Spalten.
 * @return array
 */
function kuh_stage_admin_columns( $columns ) {
    $columns['kuh_stage_order']    = __( 'Reihenfolge', 'korn-und-hansemarkt' );
    $columns['kuh_stage_location'] = __( 'Karte', 'korn-und-hansemarkt' );
    return $columns;
}
add_filter( 'manage_edit-kuh_stage_columns', 'kuh_stage_admin_columns' );

/**
 * Inhalt der eigenen Bühnen-Spalten.
 *
 * @param string $content Bisheriger Inhalt.
 * @param string $column  Spalten-Key.
 * @param int    $term_id Term-ID.
 * @return string
 */
function kuh_stage_admin_column_content( $content, $column, $term_id ) {
    if ( 'kuh_stage_order' === $column ) {
        return esc_html( (string) (int) get_term_meta( $term_id, 'kuh_stage_order', true ) );
    }
    if ( 'kuh_stage_location' === $column ) {
        $loc = (string) get_term_meta( $term_id, 'kuh_stage_location', true );
        return $loc ? '<code>' . esc_html( $loc ) . '</code>' : '—';
    }
    return $content;
}
add_filter( 'manage_kuh_stage_custom_column', 'kuh_stage_admin_column_content', 10, 3 );

/* -------------------------------------------------------------------------
 * Meta-Box für Programmpunkte
 * ---------------------------------------------------------------------- */

function kuh_add_slot_meta_box() {
    add_meta_box(
        'kuh_slot_details',
        __( 'Programmpunkt', 'korn-und-hansemarkt' ),
        'kuh_slot_meta_box_html',
        'kuh_slot',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'kuh_add_slot_meta_box' );

/**
 * Alle Acts als ID => Titel (für Auswahlfelder).
 *
 * @return array<int,string>
 */
function kuh_get_act_choices() {
    static $choices = null;
    if ( is_array( $choices ) ) {
        return $choices;
    }

    $choices = array();
    foreach ( get_posts( array(
        'post_type'      => 'kuh_act',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) ) as $act ) {
        $choices[ $act->ID ] = $act->post_title;
    }

    return $choices;
}

/**
 * HTML der Programmpunkt-Meta-Box.
 *
 * @param WP_Post $post Aktueller Beitrag.
 */
function kuh_slot_meta_box_html( $post ) {
    $date   = (string) get_post_meta( $post->ID, 'kuh_slot_date', true );
    $start  = (string) get_post_meta( $post->ID, 'kuh_slot_start', true );
    $end    = (string) get_post_meta( $post->ID, 'kuh_slot_end', true );
    $note   = (string) get_post_meta( $post->ID, 'kuh_slot_note', true );
    $act_id = (int) get_post_meta( $post->ID, 'kuh_slot_act', true );

    wp_nonce_field( 'kuh_slot_meta', 'kuh_slot_meta_nonce' );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="kuh_slot_act"><?php esc_html_e( 'Act', 'korn-und-hansemarkt' ); ?></label></th>
            <td>
                <select id="kuh_slot_act" name="kuh_slot_act">
                    <option value="0"><?php esc_html_e( '— kein Act (freier Titel) —', 'korn-und-hansemarkt' ); ?></option>
                    <?php foreach ( kuh_get_act_choices() as $id => $label ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $act_id, $id ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Ohne Act wird der Beitragstitel angezeigt (z. B. „Gottesdienst", „Festumzug").', 'korn-und-hansemarkt' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="kuh_slot_date"><?php esc_html_e( 'Datum', 'korn-und-hansemarkt' ); ?></label></th>
            <td><input type="date" id="kuh_slot_date" name="kuh_slot_date" value="<?php echo esc_attr( $date ); ?>" required /></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Uhrzeit', 'korn-und-hansemarkt' ); ?></th>
            <td>
                <label for="kuh_slot_start"><?php esc_html_e( 'von', 'korn-und-hansemarkt' ); ?></label>
                <input type="time" id="kuh_slot_start" name="kuh_slot_start" value="<?php echo esc_attr( $start ); ?>" required />
                <label for="kuh_slot_end" style="margin-left:1em;"><?php esc_html_e( 'bis', 'korn-und-hansemarkt' ); ?></label>
                <input type="time" id="kuh_slot_end" name="kuh_slot_end" value="<?php echo esc_attr( $end ); ?>" />
                <p class="description"><?php esc_html_e( 'Ende optional – ohne Ende wird „ab HH:MM" angezeigt.', 'korn-und-hansemarkt' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="kuh_slot_note"><?php esc_html_e( 'Hinweis', 'korn-und-hansemarkt' ); ?></label></th>
            <td>
                <input type="text" id="kuh_slot_note" name="kuh_slot_note" class="large-text" value="<?php echo esc_attr( $note ); ?>" />
                <p class="description"><?php esc_html_e( 'Kurzer Zusatz, z. B. „im Anschluss an den Umzug".', 'korn-und-hansemarkt' ); ?></p>
            </td>
        </tr>
    </table>
    <p class="description">
        <?php esc_html_e( 'Ohne Bühne erscheint der Programmpunkt nicht im Bühnenplan – der Plan ist ein Raster aus Bühnenspalten. Tagesweite Punkte wie der Festumzug gehören in den Programm-Teaser.', 'korn-und-hansemarkt' ); ?>
    </p>
    <?php
}

/**
 * Prüft, ob die aktuelle Anfrage zum Speichern eines Programm-Beitrags berechtigt ist.
 *
 * Deckt sowohl das normale Bearbeitungsformular (eigener Nonce) als auch die
 * Schnellbearbeitung ab (WordPress-Nonce `_inline_edit`).
 *
 * @param int    $post_id     Beitrags-ID.
 * @param string $nonce_field Name des eigenen Nonce-Feldes.
 * @param string $nonce_action Zugehörige Nonce-Action.
 * @return bool
 */
function kuh_can_save_program_post( $post_id, $nonce_field, $nonce_action ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return false;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return false;
    }

    $own_nonce = isset( $_POST[ $nonce_field ] ) &&
        wp_verify_nonce( sanitize_key( $_POST[ $nonce_field ] ), $nonce_action );

    $inline_nonce = isset( $_POST['_inline_edit'] ) &&
        wp_verify_nonce( sanitize_key( $_POST['_inline_edit'] ), 'inlineeditnonce' );

    return $own_nonce || $inline_nonce;
}

/**
 * Programmpunkt-Meta speichern (Formular und Schnellbearbeitung).
 *
 * @param int $post_id Beitrags-ID.
 */
function kuh_save_slot_meta( $post_id ) {
    if ( ! kuh_can_save_program_post( $post_id, 'kuh_slot_meta_nonce', 'kuh_slot_meta' ) ) {
        return;
    }

    $time = static function ( $key ) {
        $raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $raw ) ? $raw : '';
    };

    if ( isset( $_POST['kuh_slot_date'] ) ) {
        $date = sanitize_text_field( wp_unslash( $_POST['kuh_slot_date'] ) );
        update_post_meta( $post_id, 'kuh_slot_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
    }
    if ( isset( $_POST['kuh_slot_start'] ) ) {
        update_post_meta( $post_id, 'kuh_slot_start', $time( 'kuh_slot_start' ) );
    }
    if ( isset( $_POST['kuh_slot_end'] ) ) {
        update_post_meta( $post_id, 'kuh_slot_end', $time( 'kuh_slot_end' ) );
    }
    if ( isset( $_POST['kuh_slot_act'] ) ) {
        update_post_meta( $post_id, 'kuh_slot_act', absint( $_POST['kuh_slot_act'] ) );
    }
    if ( isset( $_POST['kuh_slot_note'] ) ) {
        update_post_meta( $post_id, 'kuh_slot_note', sanitize_text_field( wp_unslash( $_POST['kuh_slot_note'] ) ) );
    }
}
add_action( 'save_post_kuh_slot', 'kuh_save_slot_meta' );

/* -------------------------------------------------------------------------
 * Meta-Box für Acts
 * ---------------------------------------------------------------------- */

function kuh_add_act_meta_box() {
    add_meta_box(
        'kuh_act_details',
        __( 'Act-Details', 'korn-und-hansemarkt' ),
        'kuh_act_meta_box_html',
        'kuh_act',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'kuh_add_act_meta_box' );

/**
 * HTML der Act-Meta-Box.
 *
 * @param WP_Post $post Aktueller Beitrag.
 */
function kuh_act_meta_box_html( $post ) {
    $genre = (string) get_post_meta( $post->ID, 'kuh_act_genre', true );
    $url   = (string) get_post_meta( $post->ID, 'kuh_act_url', true );
    $color = (string) get_post_meta( $post->ID, 'kuh_act_color', true );
    wp_nonce_field( 'kuh_act_meta', 'kuh_act_meta_nonce' );
    ?>
    <p>
        <label for="kuh_act_genre"><strong><?php esc_html_e( 'Genre / Art', 'korn-und-hansemarkt' ); ?></strong></label><br />
        <input type="text" id="kuh_act_genre" name="kuh_act_genre" class="widefat"
               value="<?php echo esc_attr( $genre ); ?>" placeholder="z. B. Mittelalter-Rock" />
    </p>
    <p>
        <label for="kuh_act_url"><strong><?php esc_html_e( 'Website', 'korn-und-hansemarkt' ); ?></strong></label><br />
        <input type="url" id="kuh_act_url" name="kuh_act_url" class="widefat"
               value="<?php echo esc_url( $url ); ?>" placeholder="https://" />
    </p>

    <p style="margin-bottom:0.25em;">
        <strong><?php esc_html_e( 'Farbe im Bühnenplan', 'korn-und-hansemarkt' ); ?></strong>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:0.4em;">
        <label style="display:flex;align-items:center;gap:0.3em;padding:0.25em 0.5em;border:1px solid #c3c4c7;border-radius:3px;cursor:pointer;">
            <input type="radio" name="kuh_act_color" value="" <?php checked( $color, '' ); ?> />
            <span><?php esc_html_e( 'automatisch', 'korn-und-hansemarkt' ); ?></span>
        </label>
        <?php foreach ( kuh_get_act_palette() as $index => $swatch ) : ?>
            <label title="<?php echo esc_attr( $swatch['label'] ); ?>"
                   style="display:flex;align-items:center;gap:0.3em;padding:0.25em 0.5em;border:1px solid #c3c4c7;border-radius:3px;cursor:pointer;background:<?php echo esc_attr( $swatch['hex'] ); ?>;color:#1b1c1c;">
                <input type="radio" name="kuh_act_color" value="<?php echo esc_attr( $index ); ?>" <?php checked( $color, (string) $index ); ?> />
                <span><?php echo esc_html( $swatch['label'] ); ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="description">
        <?php esc_html_e( '„automatisch" verteilt die Farben gleichmäßig über alle Acts. Eine feste Farbe bleibt über alle Tage und Bühnen gleich.', 'korn-und-hansemarkt' ); ?>
    </p>

    <p class="description" style="margin-top:1em;">
        <?php esc_html_e( 'Das Beitragsbild ist das Foto des Acts. Der Textbereich ist die ausführliche Beschreibung, der Textauszug der kurze Anreißer in der Übersicht.', 'korn-und-hansemarkt' ); ?>
    </p>
    <?php
}

/**
 * Act-Meta speichern (Formular und Schnellbearbeitung).
 *
 * @param int $post_id Beitrags-ID.
 */
function kuh_save_act_meta( $post_id ) {
    if ( ! kuh_can_save_program_post( $post_id, 'kuh_act_meta_nonce', 'kuh_act_meta' ) ) {
        return;
    }

    if ( isset( $_POST['kuh_act_genre'] ) ) {
        update_post_meta( $post_id, 'kuh_act_genre', sanitize_text_field( wp_unslash( $_POST['kuh_act_genre'] ) ) );
    }
    if ( isset( $_POST['kuh_act_url'] ) ) {
        update_post_meta( $post_id, 'kuh_act_url', esc_url_raw( wp_unslash( $_POST['kuh_act_url'] ) ) );
    }
    if ( isset( $_POST['kuh_act_color'] ) ) {
        $color = sanitize_text_field( wp_unslash( $_POST['kuh_act_color'] ) );
        update_post_meta( $post_id, 'kuh_act_color', isset( kuh_get_act_palette()[ $color ] ) ? $color : '' );
    }
}
add_action( 'save_post_kuh_act', 'kuh_save_act_meta' );

/* -------------------------------------------------------------------------
 * Admin-Übersichten
 * ---------------------------------------------------------------------- */

/**
 * Spalten der Programmpunkt-Liste definieren.
 *
 * @param array $columns Bestehende Spalten.
 * @return array
 */
function kuh_slot_admin_columns( $columns ) {
    $new = array( 'cb' => $columns['cb'] ?? '' );
    $new['slot_date'] = __( 'Tag', 'korn-und-hansemarkt' );
    $new['slot_time'] = __( 'Zeit', 'korn-und-hansemarkt' );
    $new['title']     = __( 'Act / Titel', 'korn-und-hansemarkt' );
    if ( isset( $columns['taxonomy-kuh_stage'] ) ) {
        $new['taxonomy-kuh_stage'] = __( 'Bühne', 'korn-und-hansemarkt' );
    }
    $new['slot_note'] = __( 'Hinweis', 'korn-und-hansemarkt' );
    return $new;
}
add_filter( 'manage_kuh_slot_posts_columns', 'kuh_slot_admin_columns' );

/**
 * Sortierbare Spalten der Programmpunkt-Liste.
 *
 * @param array $columns Sortierbare Spalten.
 * @return array
 */
function kuh_slot_sortable_columns( $columns ) {
    $columns['slot_date'] = 'slot_date';
    return $columns;
}
add_filter( 'manage_edit-kuh_slot_sortable_columns', 'kuh_slot_sortable_columns' );

/**
 * Inhalt der eigenen Programmpunkt-Spalten ausgeben.
 *
 * Die versteckten `kuh-inline-*`-Werte werden von der Schnellbearbeitung gelesen.
 *
 * @param string $column  Spalten-Key.
 * @param int    $post_id Beitrags-ID.
 */
function kuh_slot_admin_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'slot_date':
            $date = (string) get_post_meta( $post_id, 'kuh_slot_date', true );
            $ts   = $date ? strtotime( $date ) : false;
            echo $ts ? esc_html( date_i18n( 'D, j. M Y', $ts ) ) : '—';

            // Nutzdaten für die Schnellbearbeitung.
            printf(
                '<div class="hidden" id="kuh-inline-%1$d" data-date="%2$s" data-start="%3$s" data-end="%4$s" data-act="%5$d" data-note="%6$s"></div>',
                (int) $post_id,
                esc_attr( $date ),
                esc_attr( (string) get_post_meta( $post_id, 'kuh_slot_start', true ) ),
                esc_attr( (string) get_post_meta( $post_id, 'kuh_slot_end', true ) ),
                (int) get_post_meta( $post_id, 'kuh_slot_act', true ),
                esc_attr( (string) get_post_meta( $post_id, 'kuh_slot_note', true ) )
            );
            break;

        case 'slot_time':
            $start = (string) get_post_meta( $post_id, 'kuh_slot_start', true );
            $end   = (string) get_post_meta( $post_id, 'kuh_slot_end', true );
            if ( ! $start ) {
                echo '—';
            } else {
                echo esc_html( $end ? $start . '–' . $end : 'ab ' . $start );
            }
            break;

        case 'slot_note':
            $note = (string) get_post_meta( $post_id, 'kuh_slot_note', true );
            echo $note ? esc_html( $note ) : '—';
            break;
    }
}
add_action( 'manage_kuh_slot_posts_custom_column', 'kuh_slot_admin_column_content', 10, 2 );

/**
 * Spalten der Acts-Liste definieren.
 *
 * @param array $columns Bestehende Spalten.
 * @return array
 */
function kuh_act_admin_columns( $columns ) {
    $new = array( 'cb' => $columns['cb'] ?? '' );
    $new['act_image'] = __( 'Bild', 'korn-und-hansemarkt' );
    $new['title']     = __( 'Act', 'korn-und-hansemarkt' );
    $new['act_genre'] = __( 'Genre', 'korn-und-hansemarkt' );
    $new['act_color'] = __( 'Farbe', 'korn-und-hansemarkt' );
    $new['act_order'] = __( 'Reihenfolge', 'korn-und-hansemarkt' );
    $new['act_shows'] = __( 'Auftritte', 'korn-und-hansemarkt' );
    $new['act_url']   = __( 'Website', 'korn-und-hansemarkt' );
    return $new;
}
add_filter( 'manage_kuh_act_posts_columns', 'kuh_act_admin_columns' );

/**
 * Inhalt der eigenen Acts-Spalten ausgeben.
 *
 * @param string $column  Spalten-Key.
 * @param int    $post_id Beitrags-ID.
 */
function kuh_act_admin_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'act_image':
            $thumb = get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:4px;' ) );
            echo $thumb ? $thumb : '<span style="color:#a7aaad;">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            printf(
                '<div class="hidden" id="kuh-inline-%1$d" data-genre="%2$s" data-url="%3$s" data-color="%4$s"></div>',
                (int) $post_id,
                esc_attr( (string) get_post_meta( $post_id, 'kuh_act_genre', true ) ),
                esc_attr( (string) get_post_meta( $post_id, 'kuh_act_url', true ) ),
                esc_attr( (string) get_post_meta( $post_id, 'kuh_act_color', true ) )
            );
            break;

        case 'act_genre':
            $genre = (string) get_post_meta( $post_id, 'kuh_act_genre', true );
            echo $genre ? esc_html( $genre ) : '—';
            break;

        case 'act_color':
            $color   = (string) get_post_meta( $post_id, 'kuh_act_color', true );
            $palette = kuh_get_act_palette();
            if ( isset( $palette[ $color ] ) ) {
                printf(
                    '<span style="display:inline-block;width:1.1em;height:1.1em;border-radius:3px;border:1px solid #c3c4c7;background:%s;vertical-align:middle;"></span> %s',
                    esc_attr( $palette[ $color ]['hex'] ),
                    esc_html( $palette[ $color ]['label'] )
                );
            } else {
                echo '<span style="color:#a7aaad;">' . esc_html__( 'automatisch', 'korn-und-hansemarkt' ) . '</span>';
            }
            break;

        case 'act_shows':
            $count = count( kuh_get_act_slot_ids( $post_id ) );
            if ( ! $count ) {
                echo '<span style="color:#a7aaad;">0</span>';
                break;
            }
            printf(
                '<a href="%s">%d</a>',
                esc_url( add_query_arg(
                    array( 'post_type' => 'kuh_slot', 'kuh_act' => $post_id ),
                    admin_url( 'edit.php' )
                ) ),
                (int) $count
            );
            break;

        case 'act_order':
            $order = (int) get_post_field( 'menu_order', $post_id );
            echo $order
                ? esc_html( (string) $order )
                : '<span style="color:#a7aaad;" title="' . esc_attr__( 'ohne Wert – alphabetisch einsortiert', 'korn-und-hansemarkt' ) . '">0</span>';
            break;

        case 'act_url':
            $url = (string) get_post_meta( $post_id, 'kuh_act_url', true );
            echo $url
                ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>'
                : '—';
            break;
    }
}
add_action( 'manage_kuh_act_posts_custom_column', 'kuh_act_admin_column_content', 10, 2 );

/**
 * Spalte „Reihenfolge" sortierbar machen.
 *
 * @param array $columns Sortierbare Spalten.
 * @return array
 */
function kuh_act_sortable_columns( $columns ) {
    $columns['act_order'] = 'menu_order';
    return $columns;
}
add_filter( 'manage_edit-kuh_act_sortable_columns', 'kuh_act_sortable_columns' );

/**
 * Acts-Liste im Admin in derselben Reihenfolge zeigen wie im Frontend.
 *
 * @param WP_Query $query Aktuelle Query.
 */
function kuh_act_admin_order( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'kuh_act' !== $query->get( 'post_type' ) ) {
        return;
    }
    if ( $query->get( 'orderby' ) ) {
        return;
    }
    // Eigene ORDER BY-Klausel, weil sich „0 zuletzt" mit orderby nicht ausdrücken lässt.
    $query->set( 'kuh_order_by_menu_order', true );
}
add_action( 'pre_get_posts', 'kuh_act_admin_order' );

/**
 * ORDER BY der Acts-Liste: gesetzte Reihenfolge zuerst, dann alphabetisch.
 *
 * Spiegelt kuh_compare_act_order(), damit die Liste im Backend dieselbe
 * Reihenfolge zeigt wie die Acts-Übersicht im Frontend.
 *
 * @param string   $orderby Bisherige Klausel.
 * @param WP_Query $query   Aktuelle Query.
 * @return string
 */
function kuh_act_admin_orderby_clause( $orderby, $query ) {
    if ( ! $query->get( 'kuh_order_by_menu_order' ) ) {
        return $orderby;
    }

    global $wpdb;

    return "CASE WHEN {$wpdb->posts}.menu_order = 0 THEN 1 ELSE 0 END ASC, "
        . "{$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC";
}
add_filter( 'posts_orderby', 'kuh_act_admin_orderby_clause', 10, 2 );

/**
 * IDs aller Programmpunkte eines Acts.
 *
 * @param int $act_id Act-ID.
 * @return int[]
 */
function kuh_get_act_slot_ids( $act_id ) {
    return get_posts( array(
        'post_type'      => 'kuh_slot',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => 'kuh_slot_act', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'meta_value'     => (int) $act_id,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
    ) );
}

/**
 * Programmpunkte im Admin chronologisch sortieren und nach Tag/Act filtern.
 *
 * @param WP_Query $query Aktuelle Query.
 */
function kuh_slot_admin_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'kuh_slot' !== $query->get( 'post_type' ) ) {
        return;
    }

    $meta_query = array();

    if ( ! empty( $_GET['kuh_act'] ) ) {
        $meta_query[] = array(
            'key'   => 'kuh_slot_act',
            'value' => absint( $_GET['kuh_act'] ),
        );
    }

    if ( ! empty( $_GET['kuh_day'] ) ) {
        $day = sanitize_text_field( wp_unslash( $_GET['kuh_day'] ) );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
            $meta_query[] = array(
                'key'   => 'kuh_slot_date',
                'value' => $day,
            );
        }
    }

    if ( $meta_query ) {
        $query->set( 'meta_query', $meta_query );
    }

    if ( ! $query->get( 'orderby' ) || 'slot_date' === $query->get( 'orderby' ) ) {
        // Nach Datum und dann Startzeit – beides als Meta, deshalb zwei Klauseln.
        $query->set( 'meta_query', array_merge( $meta_query, array(
            'relation' => 'AND',
            'date_cl'  => array( 'key' => 'kuh_slot_date', 'compare' => 'EXISTS' ),
            'start_cl' => array( 'key' => 'kuh_slot_start', 'compare' => 'EXISTS' ),
        ) ) );
        $query->set( 'orderby', array( 'date_cl' => 'ASC', 'start_cl' => 'ASC' ) );
        $query->set( 'order', $query->get( 'order' ) ?: 'ASC' );
    }

    if ( ! $query->get( 'posts_per_page' ) || -1 === (int) $query->get( 'posts_per_page' ) ) {
        $query->set( 'posts_per_page', 200 );
    }
}
add_action( 'pre_get_posts', 'kuh_slot_admin_query' );

/**
 * Tages-Filter über der Programmpunkt-Liste.
 *
 * @param string $post_type Aktueller Post-Type.
 */
function kuh_slot_admin_day_filter( $post_type ) {
    if ( 'kuh_slot' !== $post_type ) {
        return;
    }

    global $wpdb;
    $dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
        'kuh_slot_date'
    ) );

    if ( ! $dates ) {
        return;
    }

    $current = isset( $_GET['kuh_day'] ) ? sanitize_text_field( wp_unslash( $_GET['kuh_day'] ) ) : '';
    echo '<select name="kuh_day">';
    echo '<option value="">' . esc_html__( 'Alle Tage', 'korn-und-hansemarkt' ) . '</option>';
    foreach ( $dates as $date ) {
        $ts = strtotime( $date );
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr( $date ),
            selected( $current, $date, false ),
            esc_html( $ts ? date_i18n( 'D, j. F Y', $ts ) : $date )
        );
    }
    echo '</select>';
}
add_action( 'restrict_manage_posts', 'kuh_slot_admin_day_filter' );

/* -------------------------------------------------------------------------
 * Schnellbearbeitung
 * ---------------------------------------------------------------------- */

/**
 * Zusatzfelder in der Schnellbearbeitung ausgeben.
 *
 * @param string $column    Spalten-Key, an dem die Felder hängen.
 * @param string $post_type Post-Type.
 */
function kuh_quick_edit_fields( $column, $post_type ) {
    if ( 'kuh_slot' === $post_type && 'slot_date' === $column ) {
        wp_nonce_field( 'kuh_slot_meta', 'kuh_slot_meta_nonce' );
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php esc_html_e( 'Act', 'korn-und-hansemarkt' ); ?></span>
                    <select name="kuh_slot_act">
                        <option value="0"><?php esc_html_e( '— kein Act —', 'korn-und-hansemarkt' ); ?></option>
                        <?php foreach ( kuh_get_act_choices() as $id => $label ) : ?>
                            <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Datum', 'korn-und-hansemarkt' ); ?></span>
                    <input type="date" name="kuh_slot_date" value="" />
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Von', 'korn-und-hansemarkt' ); ?></span>
                    <input type="time" name="kuh_slot_start" value="" />
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Bis', 'korn-und-hansemarkt' ); ?></span>
                    <input type="time" name="kuh_slot_end" value="" />
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Hinweis', 'korn-und-hansemarkt' ); ?></span>
                    <input type="text" name="kuh_slot_note" value="" />
                </label>
            </div>
        </fieldset>
        <?php
        return;
    }

    if ( 'kuh_act' === $post_type && 'act_genre' === $column ) {
        wp_nonce_field( 'kuh_act_meta', 'kuh_act_meta_nonce' );
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php esc_html_e( 'Genre', 'korn-und-hansemarkt' ); ?></span>
                    <input type="text" name="kuh_act_genre" value="" />
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Website', 'korn-und-hansemarkt' ); ?></span>
                    <input type="url" name="kuh_act_url" value="" />
                </label>
                <label>
                    <span class="title"><?php esc_html_e( 'Farbe', 'korn-und-hansemarkt' ); ?></span>
                    <select name="kuh_act_color">
                        <option value=""><?php esc_html_e( 'automatisch', 'korn-und-hansemarkt' ); ?></option>
                        <?php foreach ( kuh_get_act_palette() as $index => $swatch ) : ?>
                            <option value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $swatch['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }
}
add_action( 'quick_edit_custom_box', 'kuh_quick_edit_fields', 10, 2 );

/**
 * Script für die Schnellbearbeitung laden.
 *
 * @param string $hook Aktuelle Admin-Seite.
 */
function kuh_enqueue_program_admin_assets( $hook ) {
    if ( 'edit.php' !== $hook ) {
        return;
    }
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
    if ( ! in_array( $post_type, array( 'kuh_slot', 'kuh_act' ), true ) ) {
        return;
    }

    wp_enqueue_script(
        'kuh-program-quick-edit',
        KUH_THEME_URI . '/assets/program-admin/quick-edit.js',
        array( 'jquery', 'inline-edit-post' ),
        KUH_THEME_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'kuh_enqueue_program_admin_assets' );

/* -------------------------------------------------------------------------
 * Datenaufbereitung fürs Frontend
 * ---------------------------------------------------------------------- */

/**
 * „HH:MM" in Minuten seit Mitternacht umrechnen.
 *
 * Zeiten vor 06:00 gelten als Nachtstunden des Vortags und werden nach
 * hinten geschoben, damit ein Slot um 00:30 nach 23:00 einsortiert wird.
 *
 * @param string $time Zeit im Format HH:MM.
 * @return int Minuten, oder -1 bei ungültiger Eingabe.
 */
function kuh_program_minutes( $time ) {
    if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $time, $m ) ) {
        return -1;
    }
    $minutes = ( (int) $m[1] ) * 60 + (int) $m[2];
    return $minutes < 360 ? $minutes + 1440 : $minutes;
}

/**
 * Alle Bühnen als slug-indiziertes Array (Reihenfolge, Karten-Anker, Untertitel).
 *
 * @return array<int,array>
 */
function kuh_get_stage_map() {
    $terms = get_terms( array(
        'taxonomy'   => 'kuh_stage',
        'hide_empty' => false,
    ) );

    $stages = array();
    if ( is_wp_error( $terms ) ) {
        return $stages;
    }

    foreach ( $terms as $term ) {
        $stages[ $term->term_id ] = array(
            'slug'         => $term->slug,
            'name'         => $term->name,
            'subtitle'     => (string) get_term_meta( $term->term_id, 'kuh_stage_subtitle', true ),
            'locationSlug' => (string) get_term_meta( $term->term_id, 'kuh_stage_location', true ),
            'order'        => (int) get_term_meta( $term->term_id, 'kuh_stage_order', true ),
        );
    }

    return $stages;
}

/**
 * Kompletten Bühnenplan als Array aufbereiten (intern + REST + Block-Render).
 *
 * @return array{days:array,acts:array}
 */
function kuh_get_program_data() {
    $cached = wp_cache_get( 'kuh_program_data' );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $slots = get_posts( array(
        'post_type'      => 'kuh_slot',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'suppress_filters' => true,
    ) );

    $stages   = kuh_get_stage_map();
    $days     = array();
    $used_act = array();

    foreach ( $slots as $slot ) {
        $date  = (string) get_post_meta( $slot->ID, 'kuh_slot_date', true );
        $start = (string) get_post_meta( $slot->ID, 'kuh_slot_start', true );
        if ( ! $date || ! $start ) {
            continue;
        }

        $act_id    = (int) get_post_meta( $slot->ID, 'kuh_slot_act', true );
        $act_title = $act_id ? get_the_title( $act_id ) : '';
        $title     = get_the_title( $slot->ID );

        $terms      = wp_get_object_terms( $slot->ID, 'kuh_stage', array( 'fields' => 'ids' ) );
        $stage_id   = ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
        $stage_slug = $stage_id && isset( $stages[ $stage_id ] ) ? $stages[ $stage_id ]['slug'] : '';

        // Der Bühnenplan ist ein Raster aus Bühnen – ein Programmpunkt ohne Bühne
        // hat dort keine Spalte. Solche Punkte (Festumzug, Eröffnung …) werden im
        // Programm-Teaser gepflegt und hier bewusst ausgelassen.
        if ( ! $stage_slug ) {
            continue;
        }

        if ( ! isset( $days[ $date ] ) ) {
            $ts = strtotime( $date );
            $days[ $date ] = array(
                'date'      => $date,
                'slug'      => sanitize_title( $ts ? date_i18n( 'l', $ts ) : $date ),
                'label'     => $ts ? date_i18n( 'l', $ts ) : $date,
                'dateLabel' => $ts ? date_i18n( 'j. F Y', $ts ) : $date,
                'stages'    => array(),
                'slots'     => array(),
            );
        }

        $entry = array(
            'id'      => $slot->ID,
            'stage'   => $stage_slug,
            'start'   => $start,
            'end'     => (string) get_post_meta( $slot->ID, 'kuh_slot_end', true ),
            'title'   => $act_title && $title === $act_title ? '' : $title,
            'act'     => $act_id ? get_post_field( 'post_name', $act_id ) : '',
            'actName' => $act_title,
            'note'    => (string) get_post_meta( $slot->ID, 'kuh_slot_note', true ),
        );

        if ( $act_id ) {
            $used_act[ $act_id ] = true;
        }

        $days[ $date ]['slots'][] = $entry;

        if ( ! isset( $days[ $date ]['stages'][ $stage_slug ] ) ) {
            $days[ $date ]['stages'][ $stage_slug ] = $stages[ $stage_id ];
        }
    }

    // Sortieren: Tage chronologisch, Bühnen nach Reihenfolge, Slots nach Startzeit.
    ksort( $days );
    foreach ( $days as &$day ) {
        uasort( $day['stages'], static function ( $a, $b ) {
            return ( $a['order'] <=> $b['order'] ) ?: strcmp( $a['name'], $b['name'] );
        } );
        $day['stages'] = array_values( $day['stages'] );

        usort( $day['slots'], static function ( $a, $b ) {
            return kuh_program_minutes( $a['start'] ) <=> kuh_program_minutes( $b['start'] );
        } );
    }
    unset( $day );

    $acts = array();
    foreach ( array_keys( $used_act ) as $act_id ) {
        $acts[] = kuh_format_act( $act_id );
    }
    // Bewusst alphabetisch und nicht nach „Reihenfolge": diese Liste steuert im
    // Bühnenplan die automatische Farbvergabe. Würde sie der manuellen Sortierung
    // folgen, würden sich beim Umsortieren der Acts-Übersicht die Farben im
    // Zeitraster mitverschieben.
    usort( $acts, static function ( $a, $b ) {
        return strcasecmp( $a['name'], $b['name'] );
    } );

    $data = array(
        'days' => array_values( $days ),
        'acts' => $acts,
    );

    wp_cache_set( 'kuh_program_data', $data, '', 300 );

    return $data;
}

/**
 * Einen Act für die Ausgabe formatieren.
 *
 * @param int $act_id Act-ID.
 * @return array
 */
function kuh_format_act( $act_id ) {
    $thumb_id = get_post_thumbnail_id( $act_id );
    $content  = get_post_field( 'post_content', $act_id );
    $excerpt  = get_post_field( 'post_excerpt', $act_id );

    $color   = (string) get_post_meta( $act_id, 'kuh_act_color', true );
    $palette = kuh_get_act_palette();

    // Maße mitliefern: die Bilder werden ungeschnitten mit automatischer Höhe
    // dargestellt – ohne width/height gäbe es beim Nachladen einen Layout-Sprung.
    $image = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium_large' ) : false;

    return array(
        'id'        => (int) $act_id,
        'slug'      => get_post_field( 'post_name', $act_id ),
        'name'      => get_the_title( $act_id ),
        'genre'     => (string) get_post_meta( $act_id, 'kuh_act_genre', true ),
        'url'       => (string) get_post_meta( $act_id, 'kuh_act_url', true ),
        // Leer = automatische Verteilung im Frontend.
        'color'     => isset( $palette[ $color ] ) ? $color : '',
        'excerpt'   => $excerpt ? wp_strip_all_tags( $excerpt ) : wp_trim_words( wp_strip_all_tags( $content ), 28 ),
        'text'      => wp_strip_all_tags( $content ),
        'image'       => $image ? $image[0] : '',
        'imageWidth'  => $image ? (int) $image[1] : 0,
        'imageHeight' => $image ? (int) $image[2] : 0,
        'imageAlt'    => $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '',
        'order'     => (int) get_post_field( 'menu_order', $act_id ),
    );
}

/**
 * Alle Acts inklusive ihrer Auftritte – für die Acts-Übersicht.
 *
 * Enthält auch Acts ohne Programmpunkt, damit sie in der Übersicht nicht fehlen.
 *
 * @return array<int,array>
 */
function kuh_get_acts_overview_data() {
    $cached = wp_cache_get( 'kuh_acts_overview' );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $stages = kuh_get_stage_map();

    // Auftritte je Act sammeln.
    $shows = array();
    foreach ( get_posts( array(
        'post_type'      => 'kuh_slot',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) ) as $slot ) {
        $act_id = (int) get_post_meta( $slot->ID, 'kuh_slot_act', true );
        $date   = (string) get_post_meta( $slot->ID, 'kuh_slot_date', true );
        $start  = (string) get_post_meta( $slot->ID, 'kuh_slot_start', true );
        if ( ! $act_id || ! $date || ! $start ) {
            continue;
        }

        $terms    = wp_get_object_terms( $slot->ID, 'kuh_stage', array( 'fields' => 'ids' ) );
        $stage_id = ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;

        // Konsistent zum Bühnenplan: nur Auftritte mit Bühne.
        if ( ! $stage_id || ! isset( $stages[ $stage_id ] ) ) {
            continue;
        }

        $ts = strtotime( $date );

        $shows[ $act_id ][] = array(
            'date'      => $date,
            'dayLabel'  => $ts ? date_i18n( 'D', $ts ) : $date,
            'dateLabel' => $ts ? date_i18n( 'j. F', $ts ) : $date,
            'start'     => $start,
            'end'       => (string) get_post_meta( $slot->ID, 'kuh_slot_end', true ),
            'stage'     => $stages[ $stage_id ]['name'],
            'stageSlug' => $stages[ $stage_id ]['slug'],
            'location'  => $stages[ $stage_id ]['locationSlug'],
        );
    }

    $acts = array();
    foreach ( get_posts( array(
        'post_type'      => 'kuh_act',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) ) as $act ) {
        $entry          = kuh_format_act( $act->ID );
        $entry['shows'] = $shows[ $act->ID ] ?? array();

        usort( $entry['shows'], static function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] )
                ?: ( kuh_program_minutes( $a['start'] ) <=> kuh_program_minutes( $b['start'] ) );
        } );

        $acts[] = $entry;
    }

    usort( $acts, 'kuh_compare_act_order' );

    wp_cache_set( 'kuh_acts_overview', $acts, '', 300 );

    return $acts;
}

/**
 * Acts nach dem Feld „Reihenfolge" sortieren.
 *
 * WordPress setzt `menu_order` standardmäßig auf 0. Sortierte man einfach
 * aufsteigend, landeten alle gepflegten Acts (1, 2, 3 …) hinter den
 * ungepflegten. Deshalb gilt 0 als „nicht gepflegt" und kommt zuletzt;
 * innerhalb einer Gruppe wird alphabetisch sortiert.
 *
 * @param array $a Erster Act.
 * @param array $b Zweiter Act.
 * @return int
 */
function kuh_compare_act_order( $a, $b ) {
    $order_a = ( $a['order'] ?? 0 ) > 0 ? (int) $a['order'] : PHP_INT_MAX;
    $order_b = ( $b['order'] ?? 0 ) > 0 ? (int) $b['order'] : PHP_INT_MAX;

    return ( $order_a <=> $order_b ) ?: strcasecmp( $a['name'], $b['name'] );
}

/**
 * Cache leeren, sobald sich Programmdaten ändern.
 */
function kuh_flush_program_cache() {
    wp_cache_delete( 'kuh_program_data' );
    wp_cache_delete( 'kuh_acts_overview' );
}
add_action( 'save_post_kuh_slot', 'kuh_flush_program_cache' );
add_action( 'save_post_kuh_act', 'kuh_flush_program_cache' );
add_action( 'deleted_post', 'kuh_flush_program_cache' );
add_action( 'edited_kuh_stage', 'kuh_flush_program_cache' );
add_action( 'created_kuh_stage', 'kuh_flush_program_cache' );

/**
 * REST-Endpunkte für Bühnenplan und Acts registrieren.
 */
function kuh_register_program_rest_routes() {
    register_rest_route( 'kuh/v1', '/program', array(
        'methods'             => 'GET',
        'callback'            => static function () {
            return new WP_REST_Response( kuh_get_program_data(), 200 );
        },
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'kuh/v1', '/acts', array(
        'methods'             => 'GET',
        'callback'            => static function () {
            return new WP_REST_Response( kuh_get_acts_overview_data(), 200 );
        },
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'kuh_register_program_rest_routes' );
