<?php
/**
 * Import des Bühnenplans aus einer JSON-Datei.
 *
 * Ablauf: Datei hochladen (oder JSON einfügen) → Vorschau prüfen → Import ausführen.
 * Zwischen beiden Schritten liegt die geparste Datei in einem Transient, damit sie
 * nicht erneut hochgeladen werden muss.
 *
 * Import-Modi:
 *   day   – Für jeden Tag, der in der Datei vorkommt, werden alle bestehenden
 *           Programmpunkte dieses Tages gelöscht und neu aufgebaut. Tage, die nicht
 *           in der Datei stehen, bleiben unberührt. (Standard)
 *   merge – Nur anlegen und aktualisieren, nichts löschen.
 *   all   – Alle Programmpunkte löschen und komplett neu aufbauen.
 *
 * Acts und Bühnen werden in keinem Modus gelöscht, damit Bilder, Beschreibungen und
 * Karten-Verknüpfungen erhalten bleiben.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_PROGRAM_IMPORT_MODES = array( 'day', 'merge', 'all' );

/**
 * Pfad zur mitgelieferten Beispieldatei.
 *
 * Reine Format-Vorlage für den Download-Button – die echten Programmdaten liegen
 * in der Datenbank. Fehlt die Datei, wird der Button einfach ausgeblendet.
 *
 * @return string
 */
function kuh_program_seed_file() {
    return KUH_THEME_DIR . '/assets/program/beispiel-import.json';
}

/**
 * Transient-Key der zwischengespeicherten Upload-Daten des aktuellen Users.
 *
 * @return string
 */
function kuh_program_import_transient_key() {
    return 'kuh_program_import_' . get_current_user_id();
}

/**
 * Import-Seite im Admin registrieren.
 */
function kuh_register_program_import_page() {
    add_submenu_page(
        'edit.php?post_type=kuh_act',
        __( 'Bühnenplan importieren', 'korn-und-hansemarkt' ),
        __( 'Import', 'korn-und-hansemarkt' ),
        'manage_options',
        'kuh-program-import',
        'kuh_program_import_page_html'
    );
}
add_action( 'admin_menu', 'kuh_register_program_import_page' );

/**
 * Download der Vorlagedatei.
 */
function kuh_program_download_template() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'korn-und-hansemarkt' ) );
    }
    check_admin_referer( 'kuh_program_template' );

    $file = kuh_program_seed_file();
    if ( ! file_exists( $file ) ) {
        wp_die( esc_html__( 'Vorlage nicht gefunden.', 'korn-und-hansemarkt' ) );
    }

    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="buehnenplan-beispiel.json"' );
    readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
add_action( 'admin_post_kuh_program_template', 'kuh_program_download_template' );

/* -------------------------------------------------------------------------
 * Export als CSV
 * ---------------------------------------------------------------------- */

/**
 * Spalten der Export-/Import-CSV.
 *
 * Dieselben Felder wie in der JSON-Vorlage; `stage_name` und `act_name` sind
 * zusätzliche Klartextspalten, damit die Datei in Excel lesbar bleibt und neue
 * Bühnen oder Acts einen Namen mitbringen können.
 *
 * @return string[]
 */
function kuh_program_csv_columns() {
    return array( 'date', 'start', 'end', 'stage', 'stage_name', 'act', 'act_name', 'title', 'note' );
}

/**
 * Alle Programmpunkte als Zeilen für den Export.
 *
 * Enthält bewusst auch Punkte ohne Bühne (Spalte `stage` bleibt leer): sonst
 * würde ein Re-Import im Modus „Tage ersetzen" genau diese Einträge löschen.
 *
 * @return array<int,array<string,string>>
 */
function kuh_program_export_rows() {
    $stages = kuh_get_stage_map();
    $rows   = array();

    $slots = get_posts( array(
        'post_type'      => 'kuh_slot',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) );

    foreach ( $slots as $slot ) {
        $date  = (string) get_post_meta( $slot->ID, 'kuh_slot_date', true );
        $start = (string) get_post_meta( $slot->ID, 'kuh_slot_start', true );
        if ( ! $date || ! $start ) {
            continue;
        }

        $terms      = wp_get_object_terms( $slot->ID, 'kuh_stage', array( 'fields' => 'ids' ) );
        $stage_id   = ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
        $stage      = $stage_id && isset( $stages[ $stage_id ] ) ? $stages[ $stage_id ] : null;

        $act_id   = (int) get_post_meta( $slot->ID, 'kuh_slot_act', true );
        $act_name = $act_id ? get_the_title( $act_id ) : '';
        $title    = get_the_title( $slot->ID );

        $rows[] = array(
            'date'       => $date,
            'start'      => $start,
            'end'        => (string) get_post_meta( $slot->ID, 'kuh_slot_end', true ),
            'stage'      => $stage ? $stage['slug'] : '',
            'stage_name' => $stage ? $stage['name'] : '',
            'act'        => $act_id ? get_post_field( 'post_name', $act_id ) : '',
            'act_name'   => $act_name,
            // Wie in der JSON-Vorlage: nur setzen, wenn er vom Act-Namen abweicht.
            'title'      => ( $act_name && $title === $act_name ) ? '' : $title,
            'note'       => (string) get_post_meta( $slot->ID, 'kuh_slot_note', true ),
        );
    }

    usort( $rows, static function ( $a, $b ) {
        return strcmp( $a['date'], $b['date'] )
            ?: ( kuh_program_minutes( $a['start'] ) <=> kuh_program_minutes( $b['start'] ) )
            ?: strcmp( $a['stage_name'], $b['stage_name'] );
    } );

    return $rows;
}

/**
 * Aktuellen Stand als CSV ausliefern.
 */
function kuh_program_export_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'korn-und-hansemarkt' ) );
    }
    check_admin_referer( 'kuh_program_export' );

    $rows = kuh_program_export_rows();

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="buehnenplan-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

    // BOM, damit Excel die Umlaute als UTF-8 erkennt. Über denselben Stream wie
    // die Daten geschrieben, damit die Reihenfolge unabhängig vom Output-Buffering ist.
    fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

    // Semikolon: Excel erwartet in deutscher Locale kein Komma.
    fputcsv( $out, kuh_program_csv_columns(), ';' );
    foreach ( $rows as $row ) {
        fputcsv( $out, array_values( $row ), ';' );
    }

    fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    exit;
}
add_action( 'admin_post_kuh_program_export', 'kuh_program_export_csv' );

/* -------------------------------------------------------------------------
 * CSV einlesen
 * ---------------------------------------------------------------------- */

/**
 * CSV in dieselbe Struktur überführen, die der JSON-Import erwartet.
 *
 * Akzeptiert Semikolon, Komma und Tabulator als Trennzeichen und erkennt die
 * Spalten über die Kopfzeile – die Reihenfolge ist also frei. Deutsche
 * Spaltennamen werden mitverstanden, damit auch handgetippte Dateien laufen.
 *
 * @param string $raw Dateiinhalt.
 * @return array|WP_Error
 */
function kuh_program_parse_csv( $raw ) {
    $raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

    $handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
    fwrite( $handle, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    rewind( $handle );

    // Trennzeichen aus der Kopfzeile ableiten.
    $first     = (string) strtok( $raw, "\r\n" );
    $delimiter = ';';
    $best      = substr_count( $first, ';' );
    foreach ( array( ',' => substr_count( $first, ',' ), "\t" => substr_count( $first, "\t" ) ) as $candidate => $count ) {
        if ( $count > $best ) {
            $delimiter = $candidate;
            $best      = $count;
        }
    }

    $header = fgetcsv( $handle, 0, $delimiter );
    if ( ! is_array( $header ) ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return new WP_Error( 'kuh_csv_empty', __( 'Die CSV-Datei ist leer.', 'korn-und-hansemarkt' ) );
    }

    $aliases = array(
        'datum'       => 'date',
        'tag'         => 'date',
        'von'         => 'start',
        'beginn'      => 'start',
        'ende'        => 'end',
        'bis'         => 'end',
        'buehne'      => 'stage',
        'bühne'       => 'stage',
        'buehne_name' => 'stage_name',
        'bühne_name'  => 'stage_name',
        'titel'       => 'title',
        'hinweis'     => 'note',
        'notiz'       => 'note',
    );

    $map = array();
    foreach ( $header as $index => $label ) {
        $key = strtolower( trim( (string) $label ) );
        $key = str_replace( ' ', '_', $key );
        $key = $aliases[ $key ] ?? $key;
        if ( in_array( $key, kuh_program_csv_columns(), true ) ) {
            $map[ $key ] = $index;
        }
    }

    if ( ! isset( $map['date'], $map['start'] ) ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return new WP_Error(
            'kuh_csv_header',
            __( 'In der Kopfzeile fehlen die Spalten „date" und „start". Erwartet werden: date, start, end, stage, stage_name, act, act_name, title, note.', 'korn-und-hansemarkt' )
        );
    }

    $cell = static function ( array $row, $index ) {
        return isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
    };

    $slots  = array();
    $stages = array();
    $acts   = array();

    while ( false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) {
        // Leerzeilen überspringen.
        if ( ! is_array( $row ) || '' === implode( '', array_map( 'strval', $row ) ) ) {
            continue;
        }

        $stage_slug = isset( $map['stage'] ) ? kuh_program_slugify( $cell( $row, $map['stage'] ) ) : '';
        $act_slug   = isset( $map['act'] ) ? kuh_program_slugify( $cell( $row, $map['act'] ) ) : '';
        $stage_name = isset( $map['stage_name'] ) ? $cell( $row, $map['stage_name'] ) : '';
        $act_name   = isset( $map['act_name'] ) ? $cell( $row, $map['act_name'] ) : '';

        // Nur Name gepflegt, Slug leer? Dann Slug aus dem Namen bilden.
        if ( ! $stage_slug && $stage_name ) {
            $stage_slug = kuh_program_slugify( $stage_name );
        }
        if ( ! $act_slug && $act_name ) {
            $act_slug = kuh_program_slugify( $act_name );
        }

        if ( $stage_slug && ! isset( $stages[ $stage_slug ] ) ) {
            $stages[ $stage_slug ] = array( 'slug' => $stage_slug, 'name' => $stage_name ?: $stage_slug );
        }
        if ( $act_slug && ! isset( $acts[ $act_slug ] ) ) {
            $acts[ $act_slug ] = array( 'slug' => $act_slug, 'name' => $act_name ?: $act_slug );
        }

        $slots[] = array(
            'date'  => $cell( $row, $map['date'] ),
            'start' => $cell( $row, $map['start'] ),
            'end'   => isset( $map['end'] ) ? $cell( $row, $map['end'] ) : '',
            'stage' => $stage_slug,
            'act'   => $act_slug,
            'title' => isset( $map['title'] ) ? $cell( $row, $map['title'] ) : '',
            'note'  => isset( $map['note'] ) ? $cell( $row, $map['note'] ) : '',
        );
    }

    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

    if ( ! $slots ) {
        return new WP_Error( 'kuh_csv_no_rows', __( 'Die CSV-Datei enthält außer der Kopfzeile keine Zeilen.', 'korn-und-hansemarkt' ) );
    }

    return array(
        'stages' => array_values( $stages ),
        'acts'   => array_values( $acts ),
        'slots'  => $slots,
    );
}

/* -------------------------------------------------------------------------
 * KI-Prompt
 * ---------------------------------------------------------------------- */

/**
 * Prompt erzeugen, mit dem sich ein Bühnenplan-PDF in das Importformat übersetzen lässt.
 *
 * Die vorhandenen Bühnen und Acts werden mit ausgegeben, damit die KI auf bestehende
 * Einträge abbildet statt Dubletten unter anderer Schreibweise zu erzeugen.
 *
 * @return string
 */
function kuh_program_get_ai_prompt() {
    $stages = array();
    foreach ( kuh_get_stage_map() as $stage ) {
        $stages[] = sprintf( '%s = "%s"', $stage['name'], $stage['slug'] );
    }
    sort( $stages );

    $acts = array();
    foreach ( kuh_get_act_choices() as $act_id => $name ) {
        $acts[] = sprintf( '%s = "%s"', $name, get_post_field( 'post_name', $act_id ) );
    }
    sort( $acts );

    $stage_list = $stages ? implode( "\n", $stages ) : '(noch keine Bühnen angelegt)';
    $act_list   = $acts ? implode( "\n", $acts ) : '(noch keine Acts angelegt)';

    $lines = array(
        'Du bekommst einen Bühnenplan des Korn- und Hansemarkts Haselünne als PDF, Bild oder Tabelle.',
        'Wandle ihn in JSON für den Website-Import um. Antworte ausschließlich mit dem JSON, ohne Erklärung und ohne Markdown-Codeblock.',
        '',
        'AUFBAU DER VORLAGE',
        'Der Plan ist ein Raster: Spalten sind Bühnen bzw. Spielorte, Zeilen sind Uhrzeiten.',
        'Jede Bühnenspalte hat links eine eigene schmale Zeitspalte. Eine Zelle gilt von ihrer Uhrzeit',
        'bis zur nächsten Uhrzeit derselben Bühnenspalte. Ordne Texte immer der Spalte zu, in der sie',
        'waagerecht stehen – nicht der Spalte daneben.',
        '',
        'WAS NICHT ÜBERNOMMEN WIRD',
        'Nur öffentliche Auftritte übernehmen. Interne Positionen weglassen, insbesondere:',
        'Aufbau, Abbau, Soundcheck, Umbau, Stromanschluss, "keine Technik", "Headset spielt vor der Bühne",',
        '"bereit halten", "Bierbänke", "Musikeinspieler", Stuhlaufbau, "Feuerwehr – Platz absperren".',
        'Steht so ein Vermerk direkt bei einem Auftritt und beschreibt ihn (z. B. "spielen vorm Pavillon"),',
        'gehört er in das Feld "note" des Auftritts.',
        '',
        'AUSGABEFORMAT',
        '{',
        '  "days": [ { "date": "2026-09-12", "label": "Samstag" } ],',
        '  "stages": [ { "slug": "buehne-rosche", "name": "Bühne Rosche", "order": 20, "subtitle": "" } ],',
        '  "acts": [ { "slug": "reigenwillig", "name": "Reigenwillig", "genre": "" } ],',
        '  "slots": [',
        '    { "date": "2026-09-12", "stage": "buehne-rosche", "start": "11:00", "end": "11:30", "act": "reigenwillig", "note": "" }',
        '  ]',
        '}',
        '',
        'FELDER',
        '- date: Pflicht, Format YYYY-MM-DD.',
        '- start: Pflicht, Format HH:MM (24 Stunden).',
        '- end: optional. Leer lassen, wenn die Vorlage kein Ende nennt ("ab 19:00").',
        '- stage: Slug der Bühne. Leer lassen für tagesweite Programmpunkte ohne Bühne.',
        '- act: Slug des Acts. Leer lassen, wenn es kein Act ist (z. B. Gottesdienst, Festumzug).',
        '- title: nur nötig, wenn abweichend vom Act-Namen oder wenn "act" leer ist.',
        '- note: kurzer Zusatz, z. B. "im Anschluss an den Umzug".',
        '',
        'BESTEHENDE BÜHNEN – diese Slugs bitte genau so verwenden:',
        $stage_list,
        '',
        'BESTEHENDE ACTS – diese Slugs bitte genau so verwenden:',
        $act_list,
        '',
        'Kommt eine Bühne oder ein Act neu dazu, bilde den Slug aus dem Namen: klein, Bindestriche,',
        'Umlaute ausschreiben (ä→ae, ö→oe, ü→ue, ß→ss). Neue Bühnen und Acts unter "stages" bzw. "acts"',
        'mit aufführen. Läuft ein Act mehrfach, ist das ein Eintrag unter "acts" und mehrere unter "slots".',
        'Laufen mehrere Acts gleichzeitig am selben Ort (z. B. Walking Acts), lege für jeden einen',
        'eigenen Slot mit demselben Zeitfenster an.',
    );

    return implode( "\n", $lines );
}

/* -------------------------------------------------------------------------
 * Admin-Seite
 * ---------------------------------------------------------------------- */

/**
 * HTML der Import-Seite.
 */
function kuh_program_import_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $notice  = null;
    $preview = null;
    $mode    = 'day';

    if ( isset( $_POST['kuh_program_import_nonce'] ) &&
         wp_verify_nonce( sanitize_key( $_POST['kuh_program_import_nonce'] ), 'kuh_program_import' ) ) {

        $mode   = isset( $_POST['kuh_import_mode'] ) ? sanitize_key( $_POST['kuh_import_mode'] ) : 'day';
        $mode   = in_array( $mode, KUH_PROGRAM_IMPORT_MODES, true ) ? $mode : 'day';
        $action = isset( $_POST['kuh_import_action'] ) ? sanitize_key( $_POST['kuh_import_action'] ) : 'preview';

        if ( 'apply' === $action ) {
            $data = get_transient( kuh_program_import_transient_key() );
            if ( ! is_array( $data ) ) {
                $notice = new WP_Error( 'kuh_no_data', __( 'Die Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.', 'korn-und-hansemarkt' ) );
            } else {
                $notice = kuh_program_apply_import( $data, $mode );
                delete_transient( kuh_program_import_transient_key() );
            }
        } else {
            $data = kuh_program_read_submitted_data();
            if ( is_wp_error( $data ) ) {
                $notice = $data;
            } else {
                set_transient( kuh_program_import_transient_key(), $data, HOUR_IN_SECONDS );
                $preview = kuh_program_build_preview( $data, $mode );
            }
        }
    }

    $prompt      = kuh_program_get_ai_prompt();
    $seed_exists = file_exists( kuh_program_seed_file() );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bühnenplan importieren', 'korn-und-hansemarkt' ); ?></h1>

        <?php if ( is_wp_error( $notice ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $notice->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $notice ) ) : ?>
            <div class="notice notice-success">
                <p>
                    <?php
                    printf(
                        /* translators: 1: Bühnen, 2: Acts, 3: neue Programmpunkte, 4: gelöschte Programmpunkte */
                        esc_html__( 'Import abgeschlossen: %1$d Bühnen und %2$d Acts abgeglichen, %3$d Programmpunkte geschrieben, %4$d entfernt.', 'korn-und-hansemarkt' ),
                        (int) $notice['stages'],
                        (int) $notice['acts'],
                        (int) $notice['slots_written'],
                        (int) $notice['slots_deleted']
                    );
                    ?>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kuh_slot' ) ); ?>">
                        <?php esc_html_e( 'Programmpunkte ansehen', 'korn-und-hansemarkt' ); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>

        <h2 class="title"><?php esc_html_e( 'Aktuellen Stand exportieren', 'korn-und-hansemarkt' ); ?></h2>
        <p class="description" style="max-width:52em;">
            <?php
            printf(
                /* translators: %d: Anzahl der Programmpunkte */
                esc_html__( 'Lädt alle %d Programmpunkte als CSV mit denselben Feldern wie die Import-Vorlage. Die Datei lässt sich in Excel bearbeiten und hier wieder einlesen.', 'korn-und-hansemarkt' ),
                count( kuh_program_export_rows() )
            );
            ?>
        </p>
        <p>
            <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kuh_program_export' ), 'kuh_program_export' ) ); ?>">
                <?php esc_html_e( 'Als CSV exportieren', 'korn-und-hansemarkt' ); ?>
            </a>
        </p>

        <hr />

        <h2 class="title"><?php esc_html_e( '1. PDF von einer KI ins Importformat übersetzen', 'korn-und-hansemarkt' ); ?></h2>
        <p class="description" style="max-width:52em;">
            <?php esc_html_e( 'Diesen Prompt kopieren, zusammen mit dem Bühnenplan-PDF in Claude oder ChatGPT geben und die JSON-Antwort als Datei speichern. Der Prompt enthält die bereits angelegten Bühnen und Acts, damit keine Dubletten entstehen.', 'korn-und-hansemarkt' ); ?>
        </p>
        <textarea id="kuh-import-prompt" readonly rows="12" class="large-text code"
                  style="font-size:12px;"><?php echo esc_textarea( $prompt ); ?></textarea>
        <p>
            <button type="button" class="button" id="kuh-copy-prompt">
                <?php esc_html_e( 'Prompt kopieren', 'korn-und-hansemarkt' ); ?>
            </button>
            <?php if ( $seed_exists ) : ?>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kuh_program_template' ), 'kuh_program_template' ) ); ?>">
                    <?php esc_html_e( 'Beispieldatei herunterladen', 'korn-und-hansemarkt' ); ?>
                </a>
            <?php endif; ?>
        </p>

        <hr />

        <h2 class="title"><?php esc_html_e( '2. Datei hochladen und prüfen', 'korn-und-hansemarkt' ); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'kuh_program_import', 'kuh_program_import_nonce' ); ?>
            <input type="hidden" name="kuh_import_action" value="preview" />

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="kuh_import_file"><?php esc_html_e( 'Datei', 'korn-und-hansemarkt' ); ?></label></th>
                    <td>
                        <input type="file" id="kuh_import_file" name="kuh_import_file"
                               accept=".json,.csv,application/json,text/csv,text/plain" />
                        <p class="description">
                            <?php esc_html_e( 'JSON (von der KI) oder CSV (aus dem Export). Alternativ unten direkt einfügen.', 'korn-und-hansemarkt' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="kuh_import_json"><?php esc_html_e( 'oder Daten einfügen', 'korn-und-hansemarkt' ); ?></label></th>
                    <td>
                        <textarea id="kuh_import_json" name="kuh_import_json" rows="6" class="large-text code"
                                  placeholder="&#123;&quot;slots&quot;: [ … ]&#125;    oder    date;start;end;stage;…"></textarea>
                        <p class="description"><?php esc_html_e( 'Das Format wird automatisch erkannt.', 'korn-und-hansemarkt' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Modus', 'korn-und-hansemarkt' ); ?></th>
                    <td>
                        <?php kuh_program_render_mode_choices( $mode ); ?>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Vorschau anzeigen', 'korn-und-hansemarkt' ), 'secondary' ); ?>
        </form>

        <?php if ( $preview ) : ?>
            <hr />
            <h2 class="title"><?php esc_html_e( '3. Vorschau', 'korn-und-hansemarkt' ); ?></h2>
            <?php kuh_program_render_preview( $preview, $mode ); ?>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('kuh-copy-prompt')?.addEventListener('click', function () {
        const field = document.getElementById('kuh-import-prompt');
        field.select();
        navigator.clipboard?.writeText(field.value).then(
            () => { this.textContent = <?php echo wp_json_encode( __( 'Kopiert', 'korn-und-hansemarkt' ) ); ?>; },
            () => { document.execCommand('copy'); }
        );
    });
    </script>
    <?php
}

/**
 * Auswahlfelder für den Import-Modus ausgeben.
 *
 * @param string $current Aktuell gewählter Modus.
 */
function kuh_program_render_mode_choices( $current ) {
    $modes = array(
        'day'   => array(
            __( 'Tage ersetzen', 'korn-und-hansemarkt' ),
            __( 'Empfohlen. Jeder Tag, der in der Datei vorkommt, wird komplett neu aufgebaut. Tage, die nicht in der Datei stehen, bleiben unverändert.', 'korn-und-hansemarkt' ),
        ),
        'merge' => array(
            __( 'Ergänzen', 'korn-und-hansemarkt' ),
            __( 'Legt neue Programmpunkte an und aktualisiert gleiche. Löscht nichts – verschobene Auftritte bleiben doppelt stehen.', 'korn-und-hansemarkt' ),
        ),
        'all'   => array(
            __( 'Komplett ersetzen', 'korn-und-hansemarkt' ),
            __( 'Löscht alle Programmpunkte und baut sie aus der Datei neu auf. Nur sinnvoll, wenn die Datei das gesamte Programm enthält.', 'korn-und-hansemarkt' ),
        ),
    );

    foreach ( $modes as $key => $mode ) {
        printf(
            '<p style="margin:0 0 .75em;"><label><input type="radio" name="kuh_import_mode" value="%s" %s /> <strong>%s</strong></label><br /><span class="description" style="margin-left:1.9em;display:block;max-width:46em;">%s</span></p>',
            esc_attr( $key ),
            checked( $current, $key, false ),
            esc_html( $mode[0] ),
            esc_html( $mode[1] )
        );
    }
}

/* -------------------------------------------------------------------------
 * Einlesen und Validieren
 * ---------------------------------------------------------------------- */

/**
 * Hochgeladene Datei oder eingefügtes JSON einlesen und normalisieren.
 *
 * @return array|WP_Error
 */
function kuh_program_read_submitted_data() {
    $raw = '';

    if ( ! empty( $_FILES['kuh_import_file']['tmp_name'] ) && empty( $_FILES['kuh_import_file']['error'] ) ) {
        $upload = $_FILES['kuh_import_file'];

        if ( (int) $upload['size'] > 4 * MB_IN_BYTES ) {
            return new WP_Error( 'kuh_too_large', __( 'Die Datei ist größer als 4 MB.', 'korn-und-hansemarkt' ) );
        }
        if ( ! is_uploaded_file( $upload['tmp_name'] ) ) {
            return new WP_Error( 'kuh_bad_upload', __( 'Der Upload konnte nicht gelesen werden.', 'korn-und-hansemarkt' ) );
        }

        $raw = (string) file_get_contents( $upload['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    } elseif ( ! empty( $_POST['kuh_import_json'] ) ) {
        $raw = (string) wp_unslash( $_POST['kuh_import_json'] );
    }

    $raw = trim( $raw );
    if ( '' === $raw ) {
        return new WP_Error( 'kuh_no_input', __( 'Bitte eine Datei hochladen oder Daten einfügen.', 'korn-und-hansemarkt' ) );
    }

    // Freundlich gegenüber KI-Antworten, die das JSON in einen Codeblock packen.
    $raw = preg_replace( '/^```(?:json|csv)?\s*|\s*```$/', '', $raw );
    // BOM entfernen, sonst schlägt die Formaterkennung am ersten Zeichen fehl.
    $raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

    // JSON oder CSV? Am ersten Zeichen erkennbar – so bekommt der Nutzer die
    // Fehlermeldung des Formats, das er tatsächlich hochgeladen hat.
    if ( '' !== $raw && ( '{' === $raw[0] || '[' === $raw[0] ) ) {
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return new WP_Error(
                'kuh_bad_json',
                sprintf(
                    /* translators: %s: JSON-Fehlermeldung */
                    __( 'Kein gültiges JSON: %s', 'korn-und-hansemarkt' ),
                    json_last_error_msg()
                )
            );
        }
    } else {
        $data = kuh_program_parse_csv( $raw );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
    }

    if ( empty( $data['slots'] ) || ! is_array( $data['slots'] ) ) {
        return new WP_Error( 'kuh_no_slots', __( 'Es wurden keine Programmpunkte gefunden – im JSON fehlt das Feld „slots", in der CSV die Datenzeilen.', 'korn-und-hansemarkt' ) );
    }

    return $data;
}

/**
 * Slug erzeugen – Umlaute werden ausgeschrieben, damit Slugs stabil bleiben.
 *
 * @param string $text Ausgangstext.
 * @return string
 */
function kuh_program_slugify( $text ) {
    $text = strtr(
        (string) $text,
        array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' )
    );
    return sanitize_title( $text );
}

/**
 * Import-Daten prüfen und einen Plan mit Zählerständen und Warnungen erzeugen.
 *
 * @param array  $data Geparste Import-Daten.
 * @param string $mode Import-Modus.
 * @return array
 */
function kuh_program_build_preview( array $data, $mode ) {
    $stage_map = kuh_get_stage_map();
    $known_stages = array();
    foreach ( $stage_map as $stage ) {
        $known_stages[ $stage['slug'] ] = $stage['name'];
    }

    $known_acts = array();
    foreach ( kuh_get_act_choices() as $act_id => $name ) {
        $known_acts[ get_post_field( 'post_name', $act_id ) ] = $name;
    }

    $declared_stages = array();
    foreach ( $data['stages'] ?? array() as $stage ) {
        $slug = kuh_program_slugify( $stage['slug'] ?? ( $stage['name'] ?? '' ) );
        if ( $slug ) {
            $declared_stages[ $slug ] = (string) ( $stage['name'] ?? $slug );
        }
    }

    $declared_acts = array();
    foreach ( $data['acts'] ?? array() as $act ) {
        $slug = kuh_program_slugify( $act['slug'] ?? ( $act['name'] ?? '' ) );
        if ( $slug ) {
            $declared_acts[ $slug ] = (string) ( $act['name'] ?? $slug );
        }
    }

    $errors   = array();
    $warnings = array();
    $days     = array();
    $new_stages = array();
    $new_acts   = array();
    $valid      = array();

    foreach ( $data['slots'] as $index => $slot ) {
        $line = $index + 1;

        $date  = (string) ( $slot['date'] ?? '' );
        $start = (string) ( $slot['start'] ?? '' );
        $end   = (string) ( $slot['end'] ?? '' );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $errors[] = sprintf( __( 'Eintrag %d: „date" fehlt oder hat nicht das Format YYYY-MM-DD.', 'korn-und-hansemarkt' ), $line );
            continue;
        }
        if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $start ) ) {
            $errors[] = sprintf( __( 'Eintrag %d: „start" fehlt oder hat nicht das Format HH:MM.', 'korn-und-hansemarkt' ), $line );
            continue;
        }
        if ( '' !== $end && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $end ) ) {
            $errors[] = sprintf( __( 'Eintrag %d: „end" hat nicht das Format HH:MM.', 'korn-und-hansemarkt' ), $line );
            continue;
        }

        $stage_slug = kuh_program_slugify( $slot['stage'] ?? '' );
        $act_slug   = kuh_program_slugify( $slot['act'] ?? '' );
        $title      = trim( (string) ( $slot['title'] ?? '' ) );

        if ( $stage_slug && ! isset( $known_stages[ $stage_slug ] ) ) {
            $new_stages[ $stage_slug ] = $declared_stages[ $stage_slug ] ?? $stage_slug;
        }
        if ( $act_slug && ! isset( $known_acts[ $act_slug ] ) ) {
            $new_acts[ $act_slug ] = $declared_acts[ $act_slug ] ?? $act_slug;
        }
        if ( ! $act_slug && ! $title ) {
            $errors[] = sprintf( __( 'Eintrag %d: weder „act" noch „title" gesetzt.', 'korn-und-hansemarkt' ), $line );
            continue;
        }

        if ( '' !== $end && kuh_program_minutes( $end ) <= kuh_program_minutes( $start ) ) {
            $warnings[] = sprintf(
                /* translators: 1: Zeile, 2: Startzeit, 3: Endzeit */
                __( 'Eintrag %1$d: Ende (%3$s) liegt nicht nach dem Start (%2$s) – wird als offenes Ende behandelt.', 'korn-und-hansemarkt' ),
                $line,
                $start,
                $end
            );
        }

        $label = $act_slug
            ? ( $known_acts[ $act_slug ] ?? $declared_acts[ $act_slug ] ?? $act_slug )
            : $title;

        $valid[] = array(
            'date'  => $date,
            'start' => $start,
            'end'   => $end,
            'stage' => $stage_slug,
            'act'   => $act_slug,
            'title' => $title,
            'note'  => trim( (string) ( $slot['note'] ?? '' ) ),
            'label' => $label,
        );

        $days[ $date ] = ( $days[ $date ] ?? 0 ) + 1;
    }

    ksort( $days );

    // Überschneidungen prüfen: gleiche Bühne doppelt, gleicher Act an zwei Orten.
    $warnings = array_merge( $warnings, kuh_program_find_conflicts( $valid, $known_stages + $new_stages ) );

    // Wie viele bestehende Punkte würde der Modus entfernen?
    $to_delete = 0;
    if ( 'all' === $mode ) {
        $to_delete = count( kuh_program_get_slot_ids() );
    } elseif ( 'day' === $mode ) {
        foreach ( array_keys( $days ) as $date ) {
            $to_delete += count( kuh_program_get_slot_ids( $date ) );
        }
    }

    $without_stage = count( array_filter( $valid, static function ( $slot ) {
        return '' === $slot['stage'];
    } ) );

    return array(
        'days'         => $days,
        'slots'        => $valid,
        'errors'       => $errors,
        'warnings'     => $warnings,
        'newStages'    => $new_stages,
        'newActs'      => $new_acts,
        'toDelete'     => $to_delete,
        'withoutStage' => $without_stage,
    );
}

/**
 * Zeitliche Konflikte in den Importdaten finden.
 *
 * @param array $slots  Validierte Slots.
 * @param array $stages Slug => Name.
 * @return string[] Warnungen.
 */
function kuh_program_find_conflicts( array $slots, array $stages ) {
    $warnings = array();

    $span = static function ( $slot ) {
        $from = kuh_program_minutes( $slot['start'] );
        $to   = '' !== $slot['end'] ? kuh_program_minutes( $slot['end'] ) : $from + 25;
        return array( $from, max( $to, $from + 1 ) );
    };

    // Gleiche Bühne, überlappende Zeit.
    $by_stage = array();
    foreach ( $slots as $slot ) {
        if ( ! $slot['stage'] ) {
            continue;
        }
        $by_stage[ $slot['date'] . '|' . $slot['stage'] ][] = $slot;
    }

    foreach ( $by_stage as $key => $group ) {
        usort( $group, static function ( $a, $b ) {
            return kuh_program_minutes( $a['start'] ) <=> kuh_program_minutes( $b['start'] );
        } );

        for ( $i = 1; $i < count( $group ); $i++ ) {
            list( $prev_from, $prev_to ) = $span( $group[ $i - 1 ] );
            list( $from )                = $span( $group[ $i ] );
            if ( $from < $prev_to ) {
                list( $date, $stage ) = explode( '|', $key );
                $warnings[] = sprintf(
                    /* translators: 1: Bühne, 2: Datum, 3: erster Act, 4: zweiter Act */
                    __( '%1$s am %2$s: „%3$s" (%5$s) und „%4$s" (%6$s) überschneiden sich.', 'korn-und-hansemarkt' ),
                    $stages[ $stage ] ?? $stage,
                    $date,
                    $group[ $i - 1 ]['label'],
                    $group[ $i ]['label'],
                    $group[ $i - 1 ]['start'],
                    $group[ $i ]['start']
                );
            }
        }
    }

    // Gleicher Act zur gleichen Zeit an zwei Orten.
    $by_act = array();
    foreach ( $slots as $slot ) {
        if ( ! $slot['act'] ) {
            continue;
        }
        $by_act[ $slot['date'] . '|' . $slot['act'] ][] = $slot;
    }

    foreach ( $by_act as $group ) {
        usort( $group, static function ( $a, $b ) {
            return kuh_program_minutes( $a['start'] ) <=> kuh_program_minutes( $b['start'] );
        } );

        for ( $i = 1; $i < count( $group ); $i++ ) {
            list( , $prev_to ) = $span( $group[ $i - 1 ] );
            list( $from )      = $span( $group[ $i ] );
            $same_stage        = $group[ $i ]['stage'] === $group[ $i - 1 ]['stage'];
            if ( $from < $prev_to && ! $same_stage ) {
                $warnings[] = sprintf(
                    /* translators: 1: Act, 2: Datum, 3: erste Bühne, 4: zweite Bühne, 5: Uhrzeit */
                    __( '„%1$s" am %2$s zeitgleich auf %3$s und %4$s (ab %5$s).', 'korn-und-hansemarkt' ),
                    $group[ $i ]['label'],
                    $group[ $i ]['date'],
                    $stages[ $group[ $i - 1 ]['stage'] ] ?? __( 'ohne Bühne', 'korn-und-hansemarkt' ),
                    $stages[ $group[ $i ]['stage'] ] ?? __( 'ohne Bühne', 'korn-und-hansemarkt' ),
                    $group[ $i ]['start']
                );
            }
        }
    }

    return array_values( array_unique( $warnings ) );
}

/**
 * IDs bestehender Programmpunkte, optional auf einen Tag begrenzt.
 *
 * @param string $date Datum YYYY-MM-DD, oder leer für alle.
 * @return int[]
 */
function kuh_program_get_slot_ids( $date = '' ) {
    $args = array(
        'post_type'      => 'kuh_slot',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );

    if ( $date ) {
        $args['meta_key']   = 'kuh_slot_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $args['meta_value'] = $date;           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
    }

    return get_posts( $args );
}

/**
 * Vorschau-Tabelle ausgeben.
 *
 * @param array  $preview Vorschaudaten.
 * @param string $mode    Gewählter Modus.
 */
function kuh_program_render_preview( array $preview, $mode ) {
    $mode_labels = array(
        'day'   => __( 'Tage ersetzen', 'korn-und-hansemarkt' ),
        'merge' => __( 'Ergänzen', 'korn-und-hansemarkt' ),
        'all'   => __( 'Komplett ersetzen', 'korn-und-hansemarkt' ),
    );
    ?>
    <?php if ( $preview['errors'] ) : ?>
        <div class="notice notice-error inline">
            <p><strong><?php esc_html_e( 'Diese Einträge werden übersprungen:', 'korn-und-hansemarkt' ); ?></strong></p>
            <ul style="list-style:disc;margin-left:1.5em;">
                <?php foreach ( array_slice( $preview['errors'], 0, 25 ) as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
                <?php if ( count( $preview['errors'] ) > 25 ) : ?>
                    <li><?php printf( esc_html__( '… und %d weitere', 'korn-und-hansemarkt' ), count( $preview['errors'] ) - 25 ); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ( $preview['warnings'] ) : ?>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e( 'Hinweise – der Import läuft trotzdem durch:', 'korn-und-hansemarkt' ); ?></strong></p>
            <ul style="list-style:disc;margin-left:1.5em;">
                <?php foreach ( array_slice( $preview['warnings'], 0, 25 ) as $warning ) : ?>
                    <li><?php echo esc_html( $warning ); ?></li>
                <?php endforeach; ?>
                <?php if ( count( $preview['warnings'] ) > 25 ) : ?>
                    <li><?php printf( esc_html__( '… und %d weitere', 'korn-und-hansemarkt' ), count( $preview['warnings'] ) - 25 ); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="max-width:52em;">
        <tbody>
            <tr>
                <th style="width:16em;"><?php esc_html_e( 'Modus', 'korn-und-hansemarkt' ); ?></th>
                <td><strong><?php echo esc_html( $mode_labels[ $mode ] ?? $mode ); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Programmpunkte in der Datei', 'korn-und-hansemarkt' ); ?></th>
                <td><?php echo (int) count( $preview['slots'] ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Werden vorher entfernt', 'korn-und-hansemarkt' ); ?></th>
                <td>
                    <?php if ( $preview['toDelete'] ) : ?>
                        <strong style="color:#b32d2e;"><?php echo (int) $preview['toDelete']; ?></strong>
                        <?php if ( 'day' === $mode ) : ?>
                            <span class="description"><?php esc_html_e( '– nur an den Tagen, die in der Datei vorkommen', 'korn-und-hansemarkt' ); ?></span>
                        <?php endif; ?>
                    <?php else : ?>
                        0
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Tage', 'korn-und-hansemarkt' ); ?></th>
                <td>
                    <?php foreach ( $preview['days'] as $date => $count ) : ?>
                        <?php $ts = strtotime( $date ); ?>
                        <div>
                            <?php echo esc_html( $ts ? date_i18n( 'D, j. F Y', $ts ) : $date ); ?>
                            – <?php printf( esc_html( _n( '%d Programmpunkt', '%d Programmpunkte', $count, 'korn-und-hansemarkt' ) ), (int) $count ); ?>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php if ( ! empty( $preview['withoutStage'] ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Ohne Bühne', 'korn-und-hansemarkt' ); ?></th>
                    <td>
                        <strong><?php echo (int) $preview['withoutStage']; ?></strong>
                        <span class="description">
                            <?php esc_html_e( '– werden importiert, erscheinen aber nicht im Bühnenplan (dort gibt es nur Bühnenspalten). Tagesweite Punkte wie der Festumzug gehören in den Programm-Teaser.', 'korn-und-hansemarkt' ); ?>
                        </span>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Neue Bühnen', 'korn-und-hansemarkt' ); ?></th>
                <td><?php echo $preview['newStages'] ? esc_html( implode( ', ', $preview['newStages'] ) ) : '—'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Neue Acts', 'korn-und-hansemarkt' ); ?></th>
                <td><?php echo $preview['newActs'] ? esc_html( implode( ', ', $preview['newActs'] ) ) : '—'; ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ( $preview['slots'] ) : ?>
        <form method="post" style="margin-top:1.5em;">
            <?php wp_nonce_field( 'kuh_program_import', 'kuh_program_import_nonce' ); ?>
            <input type="hidden" name="kuh_import_action" value="apply" />
            <input type="hidden" name="kuh_import_mode" value="<?php echo esc_attr( $mode ); ?>" />
            <?php
            submit_button(
                __( 'Import jetzt ausführen', 'korn-und-hansemarkt' ),
                'primary',
                'submit',
                false,
                array( 'onclick' => 'return confirm(' . wp_json_encode( __( 'Import ausführen? Bestehende Programmpunkte der betroffenen Tage werden dabei ersetzt.', 'korn-und-hansemarkt' ) ) . ');' )
            );
            ?>
        </form>
    <?php endif; ?>
    <?php
}

/* -------------------------------------------------------------------------
 * Schreiben
 * ---------------------------------------------------------------------- */

/**
 * Import ausführen.
 *
 * @param array  $data Geparste Import-Daten.
 * @param string $mode Import-Modus.
 * @return array|WP_Error Zählerstände oder Fehler.
 */
function kuh_program_apply_import( array $data, $mode ) {
    $preview = kuh_program_build_preview( $data, $mode );
    if ( ! $preview['slots'] ) {
        return new WP_Error( 'kuh_nothing_valid', __( 'Die Datei enthält keinen gültigen Programmpunkt.', 'korn-und-hansemarkt' ) );
    }

    $counts = array( 'stages' => 0, 'acts' => 0, 'slots_written' => 0, 'slots_deleted' => 0 );

    // Bühnen abgleichen (nie löschen).
    $stage_ids = array();
    foreach ( kuh_get_stage_map() as $term_id => $stage ) {
        $stage_ids[ $stage['slug'] ] = $term_id;
    }

    $stage_meta = array();
    foreach ( $data['stages'] ?? array() as $stage ) {
        $slug = kuh_program_slugify( $stage['slug'] ?? ( $stage['name'] ?? '' ) );
        if ( $slug ) {
            $stage_meta[ $slug ] = $stage;
        }
    }

    foreach ( $preview['newStages'] as $slug => $name ) {
        $created = wp_insert_term( $name, 'kuh_stage', array( 'slug' => $slug ) );
        if ( is_wp_error( $created ) ) {
            continue;
        }
        $stage_ids[ $slug ] = (int) $created['term_id'];
    }

    foreach ( $stage_ids as $slug => $term_id ) {
        if ( ! isset( $stage_meta[ $slug ] ) ) {
            continue;
        }
        $meta = $stage_meta[ $slug ];
        if ( isset( $meta['order'] ) ) {
            update_term_meta( $term_id, 'kuh_stage_order', absint( $meta['order'] ) );
        }
        if ( isset( $meta['subtitle'] ) ) {
            update_term_meta( $term_id, 'kuh_stage_subtitle', sanitize_text_field( $meta['subtitle'] ) );
        }
        // locationSlug nur setzen, wenn die Datei einen liefert – im Backend
        // gepflegte Karten-Verknüpfungen sollen nicht verloren gehen.
        if ( ! empty( $meta['locationSlug'] ) ) {
            update_term_meta( $term_id, 'kuh_stage_location', sanitize_title( $meta['locationSlug'] ) );
        }
        $counts['stages']++;
    }

    // Acts abgleichen (nie löschen, Bild und Text bleiben erhalten).
    $act_ids = array();
    foreach ( kuh_get_act_choices() as $act_id => $name ) {
        $act_ids[ get_post_field( 'post_name', $act_id ) ] = $act_id;
    }

    $act_meta = array();
    foreach ( $data['acts'] ?? array() as $act ) {
        $slug = kuh_program_slugify( $act['slug'] ?? ( $act['name'] ?? '' ) );
        if ( $slug ) {
            $act_meta[ $slug ] = $act;
        }
    }

    foreach ( $preview['newActs'] as $slug => $name ) {
        $new_id = wp_insert_post( array(
            'post_type'   => 'kuh_act',
            'post_status' => 'publish',
            'post_title'  => $name,
            'post_name'   => $slug,
        ), true );
        if ( is_wp_error( $new_id ) ) {
            continue;
        }
        update_post_meta( $new_id, 'kuh_act_slug', $slug );
        $act_ids[ $slug ] = (int) $new_id;
    }

    foreach ( $act_ids as $slug => $act_id ) {
        if ( ! isset( $act_meta[ $slug ] ) ) {
            continue;
        }
        $meta = $act_meta[ $slug ];
        if ( ! empty( $meta['genre'] ) ) {
            update_post_meta( $act_id, 'kuh_act_genre', sanitize_text_field( $meta['genre'] ) );
        }
        if ( ! empty( $meta['url'] ) ) {
            update_post_meta( $act_id, 'kuh_act_url', esc_url_raw( $meta['url'] ) );
        }
        $counts['acts']++;
    }

    // Bestehende Programmpunkte je Modus entfernen.
    $delete_ids = array();
    if ( 'all' === $mode ) {
        $delete_ids = kuh_program_get_slot_ids();
    } elseif ( 'day' === $mode ) {
        foreach ( array_keys( $preview['days'] ) as $date ) {
            $delete_ids = array_merge( $delete_ids, kuh_program_get_slot_ids( $date ) );
        }
    }

    foreach ( array_unique( $delete_ids ) as $id ) {
        wp_delete_post( (int) $id, true );
        $counts['slots_deleted']++;
    }

    // Programmpunkte schreiben.
    foreach ( $preview['slots'] as $slot ) {
        $act_id = $slot['act'] && isset( $act_ids[ $slot['act'] ] ) ? $act_ids[ $slot['act'] ] : 0;
        $title  = $slot['title'] ?: ( $act_id ? get_the_title( $act_id ) : __( 'Programmpunkt', 'korn-und-hansemarkt' ) );
        $key    = implode( '|', array( $slot['date'], $slot['start'], $slot['stage'], $slot['act'], sanitize_title( $title ) ) );

        $slot_id = 0;
        if ( 'merge' === $mode ) {
            $existing = get_posts( array(
                'post_type'      => 'kuh_slot',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => 'kuh_slot_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value'     => $key,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            ) );
            $slot_id = $existing ? (int) $existing[0] : 0;
        }

        $postarr = array(
            'post_type'   => 'kuh_slot',
            'post_status' => 'publish',
            'post_title'  => $title,
        );
        if ( $slot_id ) {
            $postarr['ID'] = $slot_id;
            $slot_id       = wp_update_post( $postarr, true );
        } else {
            $slot_id = wp_insert_post( $postarr, true );
        }

        if ( is_wp_error( $slot_id ) ) {
            continue;
        }

        $end = ( '' !== $slot['end'] && kuh_program_minutes( $slot['end'] ) > kuh_program_minutes( $slot['start'] ) )
            ? $slot['end']
            : '';

        update_post_meta( $slot_id, 'kuh_slot_key', $key );
        update_post_meta( $slot_id, 'kuh_slot_date', $slot['date'] );
        update_post_meta( $slot_id, 'kuh_slot_start', $slot['start'] );
        update_post_meta( $slot_id, 'kuh_slot_end', $end );
        update_post_meta( $slot_id, 'kuh_slot_act', $act_id );
        update_post_meta( $slot_id, 'kuh_slot_note', $slot['note'] );

        if ( $slot['stage'] && isset( $stage_ids[ $slot['stage'] ] ) ) {
            wp_set_object_terms( $slot_id, array( $stage_ids[ $slot['stage'] ] ), 'kuh_stage', false );
        } else {
            wp_set_object_terms( $slot_id, array(), 'kuh_stage', false );
        }

        $counts['slots_written']++;
    }

    kuh_flush_program_cache();

    return $counts;
}
