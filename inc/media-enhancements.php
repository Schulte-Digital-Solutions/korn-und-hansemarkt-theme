<?php
/**
 * Mediathek-Erweiterungen: Upload-Ordner-Filter, Auflösungs-Filter,
 * Dateityp-Filter, erweiterte Spalten und Suche.
 *
 * Ordner werden automatisch aus der wp-content/uploads/ Verzeichnisstruktur
 * gelesen (Jahr/Monat). Kein manuelles Zuweisen nötig.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * 1. Upload-Ordner aus dem Dateisystem lesen
 * ========================================================================= */

/**
 * Alle vorhandenen Upload-Unterordner rekursiv ermitteln.
 *
 * Gibt sowohl WordPress-Standard-Ordner (2025/03) als auch
 * manuell angelegte Ordner (kuh/logos, kuh/bilder24, …) zurück.
 *
 * @return array Assoziatives Array: 'relativer/pfad' => 'Label'
 */
function kuh_get_upload_folders() {
    $upload_dir = wp_get_upload_dir();
    $base       = $upload_dir['basedir'];
    $folders    = array();

    if ( ! is_dir( $base ) ) {
        return $folders;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ( $iterator as $item ) {
        if ( ! $item->isDir() ) {
            continue;
        }

        $rel_path = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $base ) + 1 ) );

        // WordPress-interne Ordner überspringen
        if ( preg_match( '#^(cache|wflogs|wp-|\.)|/\.|/cache#i', $rel_path ) ) {
            continue;
        }

        $folders[ $rel_path ] = $rel_path;
    }

    ksort( $folders );

    return $folders;
}

/* =========================================================================
 * 2. Filter-Dropdowns in der Mediathek (Listen-Ansicht)
 * ========================================================================= */

function kuh_media_filter_dropdowns() {
    $screen = get_current_screen();
    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    // --- Upload-Ordner-Dropdown ---
    $folders  = kuh_get_upload_folders();
    $sel_folder = isset( $_GET['kuh_folder'] ) ? sanitize_text_field( $_GET['kuh_folder'] ) : '';
    ?>
    <select name="kuh_folder">
        <option value=""><?php esc_html_e( 'Alle Ordner', 'korn-und-hansemarkt' ); ?></option>
        <?php foreach ( $folders as $path => $label ) : ?>
            <option value="<?php echo esc_attr( $path ); ?>" <?php selected( $sel_folder, $path ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php
    // --- Auflösungs-Dropdown ---
    $resolution = isset( $_GET['kuh_resolution'] ) ? sanitize_text_field( $_GET['kuh_resolution'] ) : '';
    ?>
    <select name="kuh_resolution">
        <option value=""><?php esc_html_e( 'Alle Auflösungen', 'korn-und-hansemarkt' ); ?></option>
        <option value="small" <?php selected( $resolution, 'small' ); ?>><?php esc_html_e( 'Klein (< 640px)', 'korn-und-hansemarkt' ); ?></option>
        <option value="medium" <?php selected( $resolution, 'medium' ); ?>><?php esc_html_e( 'Mittel (640–1920px)', 'korn-und-hansemarkt' ); ?></option>
        <option value="large" <?php selected( $resolution, 'large' ); ?>><?php esc_html_e( 'Groß (> 1920px)', 'korn-und-hansemarkt' ); ?></option>
    </select>

    <?php
    // --- Dateityp-Dropdown ---
    $filetype = isset( $_GET['kuh_filetype'] ) ? sanitize_text_field( $_GET['kuh_filetype'] ) : '';
    ?>
    <select name="kuh_filetype">
        <option value=""><?php esc_html_e( 'Alle Dateitypen', 'korn-und-hansemarkt' ); ?></option>
        <option value="jpg" <?php selected( $filetype, 'jpg' ); ?>>JPEG</option>
        <option value="png" <?php selected( $filetype, 'png' ); ?>>PNG</option>
        <option value="webp" <?php selected( $filetype, 'webp' ); ?>>WebP</option>
        <option value="svg" <?php selected( $filetype, 'svg' ); ?>>SVG</option>
        <option value="gif" <?php selected( $filetype, 'gif' ); ?>>GIF</option>
        <option value="pdf" <?php selected( $filetype, 'pdf' ); ?>>PDF</option>
        <option value="video" <?php selected( $filetype, 'video' ); ?>><?php esc_html_e( 'Videos', 'korn-und-hansemarkt' ); ?></option>
    </select>
    <?php
}
add_action( 'restrict_manage_posts', 'kuh_media_filter_dropdowns' );

/* =========================================================================
 * 3. Query anpassen (Listen-Ansicht)
 * ========================================================================= */

function kuh_media_filter_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    // Ordner-Filter: nach _wp_attached_file Meta filtern (z.B. "2025/03/bild.jpg" oder "kuh/logos/bild.png")
    $folder = isset( $_GET['kuh_folder'] ) ? sanitize_text_field( $_GET['kuh_folder'] ) : '';
    if ( $folder && preg_match( '#^[a-zA-Z0-9/_-]+$#', $folder ) ) {
        $meta_query = $query->get( 'meta_query' ) ?: array();
        $meta_query[] = array(
            'key'     => '_wp_attached_file',
            'value'   => $folder . '/',
            'compare' => 'LIKE',
        );
        $query->set( 'meta_query', $meta_query );
    }

    // Auflösungs-Filter
    $resolution = isset( $_GET['kuh_resolution'] ) ? sanitize_text_field( $_GET['kuh_resolution'] ) : '';
    if ( $resolution ) {
        $meta_query = $query->get( 'meta_query' ) ?: array();

        switch ( $resolution ) {
            case 'small':
                $meta_query[] = array(
                    'key'     => '_kuh_image_width',
                    'value'   => 640,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                );
                break;
            case 'medium':
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_kuh_image_width',
                        'value'   => 640,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => '_kuh_image_width',
                        'value'   => 1920,
                        'compare' => '<=',
                        'type'    => 'NUMERIC',
                    ),
                );
                break;
            case 'large':
                $meta_query[] = array(
                    'key'     => '_kuh_image_width',
                    'value'   => 1920,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                );
                break;
        }

        $query->set( 'meta_query', $meta_query );
    }

    // Dateityp-Filter
    $filetype = isset( $_GET['kuh_filetype'] ) ? sanitize_text_field( $_GET['kuh_filetype'] ) : '';
    if ( $filetype ) {
        $mime_map = array(
            'jpg'   => 'image/jpeg',
            'png'   => 'image/png',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'gif'   => 'image/gif',
            'pdf'   => 'application/pdf',
            'video' => 'video',
        );

        if ( isset( $mime_map[ $filetype ] ) ) {
            $query->set( 'post_mime_type', $mime_map[ $filetype ] );
        }
    }
}
add_action( 'pre_get_posts', 'kuh_media_filter_query' );

/* =========================================================================
 * 4. Bild-Abmessungen als Meta speichern (für Auflösungs-Filter)
 * ========================================================================= */

function kuh_save_image_dimensions( $attachment_id ) {
    $file = get_attached_file( $attachment_id );
    if ( ! $file || ! file_exists( $file ) ) {
        return;
    }

    $mime = get_post_mime_type( $attachment_id );
    if ( ! $mime || ! str_starts_with( $mime, 'image/' ) ) {
        return;
    }

    $metadata = wp_get_attachment_metadata( $attachment_id );
    $width  = $metadata['width'] ?? 0;
    $height = $metadata['height'] ?? 0;

    if ( ! $width && function_exists( 'getimagesize' ) ) {
        $size = @getimagesize( $file );
        if ( $size ) {
            $width  = $size[0];
            $height = $size[1];
        }
    }

    update_post_meta( $attachment_id, '_kuh_image_width', absint( $width ) );
    update_post_meta( $attachment_id, '_kuh_image_height', absint( $height ) );
}
add_action( 'add_attachment', 'kuh_save_image_dimensions' );
add_action( 'edit_attachment', 'kuh_save_image_dimensions' );

function kuh_maybe_backfill_dimensions( $attachment_id ) {
    if ( get_post_meta( $attachment_id, '_kuh_image_width', true ) ) {
        return;
    }
    kuh_save_image_dimensions( $attachment_id );
}

/* =========================================================================
 * 5. Admin-Spalten erweitern: Ordner, Auflösung, Dateigröße
 * ========================================================================= */

function kuh_media_admin_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'parent' === $key ) {
            $new['kuh_folder']     = __( 'Ordner', 'korn-und-hansemarkt' );
            $new['kuh_dimensions'] = __( 'Auflösung', 'korn-und-hansemarkt' );
            $new['kuh_filesize']   = __( 'Dateigröße', 'korn-und-hansemarkt' );
        }
    }
    return $new;
}
add_filter( 'manage_media_columns', 'kuh_media_admin_columns' );

function kuh_media_admin_column_content( $column, $post_id ) {
    if ( 'kuh_folder' === $column ) {
        $attached_file = get_post_meta( $post_id, '_wp_attached_file', true );
        if ( $attached_file ) {
            $dir = dirname( $attached_file );
            echo '<code>' . esc_html( '.' === $dir ? '/' : $dir ) . '</code>';
        } else {
            echo '<code>/</code>';
        }
    } elseif ( 'kuh_dimensions' === $column ) {
        kuh_maybe_backfill_dimensions( $post_id );
        $w = get_post_meta( $post_id, '_kuh_image_width', true );
        $h = get_post_meta( $post_id, '_kuh_image_height', true );
        if ( $w && $h ) {
            $megapixels = round( ( $w * $h ) / 1000000, 1 );
            echo esc_html( "{$w} × {$h} px" );
            if ( $megapixels >= 1 ) {
                echo '<br><small style="color:#737971;">' . esc_html( $megapixels . ' MP' ) . '</small>';
            }
        } else {
            echo '—';
        }
    } elseif ( 'kuh_filesize' === $column ) {
        $file = get_attached_file( $post_id );
        if ( $file && file_exists( $file ) ) {
            echo esc_html( size_format( filesize( $file ) ) );
        } else {
            echo '—';
        }
    }
}
add_action( 'manage_media_custom_column', 'kuh_media_admin_column_content', 10, 2 );

function kuh_media_sortable_columns( $columns ) {
    $columns['kuh_dimensions'] = 'kuh_dimensions';
    $columns['kuh_filesize']   = 'kuh_filesize';
    return $columns;
}
add_filter( 'manage_upload_sortable_columns', 'kuh_media_sortable_columns' );

function kuh_media_sort_handler( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    $orderby = $query->get( 'orderby' );
    if ( 'kuh_dimensions' === $orderby ) {
        $query->set( 'meta_key', '_kuh_image_width' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}
add_action( 'pre_get_posts', 'kuh_media_sort_handler' );

/* =========================================================================
 * 6. Filter in Grid-Ansicht UND Bild-Auswahl-Dialog (wp.media Modal)
 * ========================================================================= */

/**
 * Filter-Dropdowns für wp.media überall im Admin bereitstellen,
 * nicht nur auf der Mediathek-Seite, sondern auch im Editor-Modal
 * (Beitragsbild, Bild-Block, Galerie, etc.).
 */
function kuh_media_modal_filters() {
    // Nur laden wenn wp-media-views enqueued ist
    if ( ! wp_script_is( 'media-views', 'enqueued' ) && ! wp_script_is( 'media-views', 'done' ) ) {
        // Prüfen ob wir auf einer Seite sind die den Media-Dialog nutzen könnte
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        $media_screens = array( 'upload', 'post', 'page', 'kuh_partner' );
        // Auch alle Custom Post Types mit Editor
        if ( ! in_array( $screen->id, $media_screens, true ) && ! $screen->is_block_editor && 'post' !== $screen->base ) {
            return;
        }
    }

    $folders = kuh_get_upload_folders();

    ?>
    <script>
    (function() {
        // Warte bis wp.media verfügbar ist (kann verzögert geladen werden)
        function kuhInitMediaFilters() {
            if (typeof wp === 'undefined' || !wp.media || !wp.media.view || !wp.media.view.AttachmentsBrowser) {
                return;
            }

            // Nur einmal patchen
            if (wp.media.view.AttachmentsBrowser.__kuhPatched) return;
            wp.media.view.AttachmentsBrowser.__kuhPatched = true;

            var folders = <?php echo wp_json_encode( $folders ); ?>;
            var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;

            wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend({
                createToolbar: function() {
                    AttachmentsBrowser.prototype.createToolbar.call(this);

                    // --- Ordner-Filter ---
                    var folderKeys = Object.keys(folders);
                    if (folderKeys.length > 0) {
                        var FolderFilter = wp.media.view.AttachmentFilters.extend({
                            id: 'kuh-folder-filter',
                            createFilters: function() {
                                var f = { all: { text: <?php echo wp_json_encode( __( 'Alle Ordner', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_folder: '' }, priority: 10 } };
                                folderKeys.forEach(function(path, i) {
                                    f['folder_' + i] = { text: folders[path], props: { kuh_folder: path }, priority: 20 + i };
                                });
                                this.filters = f;
                            },
                        });
                        this.toolbar.set('kuhFolderFilter', new FolderFilter({
                            controller: this.controller,
                            model: this.collection.props,
                            priority: -80,
                        }).render());
                    }

                    // --- Auflösungs-Filter ---
                    var ResolutionFilter = wp.media.view.AttachmentFilters.extend({
                        id: 'kuh-resolution-filter',
                        createFilters: function() {
                            this.filters = {
                                all:    { text: <?php echo wp_json_encode( __( 'Alle Auflösungen', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_resolution: '' }, priority: 10 },
                                small:  { text: <?php echo wp_json_encode( __( 'Klein (< 640px)', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_resolution: 'small' }, priority: 20 },
                                medium: { text: <?php echo wp_json_encode( __( 'Mittel (640–1920px)', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_resolution: 'medium' }, priority: 30 },
                                large:  { text: <?php echo wp_json_encode( __( 'Groß (> 1920px)', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_resolution: 'large' }, priority: 40 },
                            };
                        },
                    });
                    this.toolbar.set('kuhResolutionFilter', new ResolutionFilter({
                        controller: this.controller,
                        model: this.collection.props,
                        priority: -75,
                    }).render());

                    // --- Dateityp-Filter ---
                    var TypeFilter = wp.media.view.AttachmentFilters.extend({
                        id: 'kuh-type-filter',
                        createFilters: function() {
                            this.filters = {
                                all:   { text: <?php echo wp_json_encode( __( 'Alle Dateitypen', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_filetype: '' }, priority: 10 },
                                jpg:   { text: 'JPEG', props: { kuh_filetype: 'jpg' }, priority: 20 },
                                png:   { text: 'PNG', props: { kuh_filetype: 'png' }, priority: 30 },
                                webp:  { text: 'WebP', props: { kuh_filetype: 'webp' }, priority: 40 },
                                svg:   { text: 'SVG', props: { kuh_filetype: 'svg' }, priority: 50 },
                                gif:   { text: 'GIF', props: { kuh_filetype: 'gif' }, priority: 60 },
                                pdf:   { text: 'PDF', props: { kuh_filetype: 'pdf' }, priority: 70 },
                                video: { text: <?php echo wp_json_encode( __( 'Videos', 'korn-und-hansemarkt' ) ); ?>, props: { kuh_filetype: 'video' }, priority: 80 },
                            };
                        },
                    });
                    this.toolbar.set('kuhTypeFilter', new TypeFilter({
                        controller: this.controller,
                        model: this.collection.props,
                        priority: -70,
                    }).render());
                },
            });
        }

        // Sofort versuchen falls wp.media schon geladen
        kuhInitMediaFilters();

        // Falls wp.media noch nicht da ist: auf DOMContentLoaded + Fallback-Interval
        if (!wp.media || !wp.media.view || !wp.media.view.AttachmentsBrowser || !wp.media.view.AttachmentsBrowser.__kuhPatched) {
            document.addEventListener('DOMContentLoaded', kuhInitMediaFilters);
            // Fallback: Block-Editor lädt wp.media verzögert
            var attempts = 0;
            var interval = setInterval(function() {
                attempts++;
                kuhInitMediaFilters();
                if ((wp.media && wp.media.view && wp.media.view.AttachmentsBrowser && wp.media.view.AttachmentsBrowser.__kuhPatched) || attempts > 50) {
                    clearInterval(interval);
                }
            }, 200);
        }
    })();
    </script>
    <?php
}
add_action( 'admin_footer', 'kuh_media_modal_filters' );

/**
 * AJAX-Query für die Grid-Ansicht: Filter anwenden.
 */
function kuh_media_grid_ajax_filter( $query ) {
    if ( ! is_admin() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        return;
    }
    if ( empty( $_REQUEST['action'] ) || 'query-attachments' !== $_REQUEST['action'] ) {
        return;
    }

    $meta_query = $query->get( 'meta_query' ) ?: array();

    // Ordner-Filter
    $folder = isset( $_REQUEST['query']['kuh_folder'] ) ? sanitize_text_field( $_REQUEST['query']['kuh_folder'] ) : '';
    if ( $folder && preg_match( '#^[a-zA-Z0-9/_-]+$#', $folder ) ) {
        $meta_query[] = array(
            'key'     => '_wp_attached_file',
            'value'   => $folder . '/',
            'compare' => 'LIKE',
        );
    }

    // Auflösungs-Filter
    $resolution = isset( $_REQUEST['query']['kuh_resolution'] ) ? sanitize_text_field( $_REQUEST['query']['kuh_resolution'] ) : '';
    if ( $resolution ) {
        switch ( $resolution ) {
            case 'small':
                $meta_query[] = array(
                    'key'     => '_kuh_image_width',
                    'value'   => 640,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                );
                break;
            case 'medium':
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_kuh_image_width',
                        'value'   => 640,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => '_kuh_image_width',
                        'value'   => 1920,
                        'compare' => '<=',
                        'type'    => 'NUMERIC',
                    ),
                );
                break;
            case 'large':
                $meta_query[] = array(
                    'key'     => '_kuh_image_width',
                    'value'   => 1920,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                );
                break;
        }
    }

    // Dateityp-Filter
    $filetype = isset( $_REQUEST['query']['kuh_filetype'] ) ? sanitize_text_field( $_REQUEST['query']['kuh_filetype'] ) : '';
    if ( $filetype ) {
        $mime_map = array(
            'jpg'   => 'image/jpeg',
            'png'   => 'image/png',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'gif'   => 'image/gif',
            'pdf'   => 'application/pdf',
            'video' => 'video',
        );
        if ( isset( $mime_map[ $filetype ] ) ) {
            $query->set( 'post_mime_type', $mime_map[ $filetype ] );
        }
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'kuh_media_grid_ajax_filter' );

/* =========================================================================
 * 7. Bestehende Bilder: Meta-Daten nachträglich befüllen (Admin-Tool)
 * ========================================================================= */

function kuh_register_media_meta_tool() {
    add_management_page(
        __( 'Medien-Meta aktualisieren', 'korn-und-hansemarkt' ),
        __( 'Medien-Meta', 'korn-und-hansemarkt' ),
        'manage_options',
        'kuh-media-meta',
        'kuh_media_meta_tool_page'
    );
}
add_action( 'admin_menu', 'kuh_register_media_meta_tool' );

function kuh_media_meta_tool_page() {
    $updated = 0;

    if ( isset( $_POST['kuh_update_media_meta'] ) && check_admin_referer( 'kuh_update_media_meta' ) ) {
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( $attachments as $att_id ) {
            if ( ! get_post_meta( $att_id, '_kuh_image_width', true ) ) {
                kuh_save_image_dimensions( $att_id );
                $updated++;
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Medien-Meta aktualisieren', 'korn-und-hansemarkt' ); ?></h1>
        <p><?php esc_html_e( 'Ergänzt fehlende Auflösungs-Metadaten für bestehende Bilder, damit der Auflösungs-Filter funktioniert.', 'korn-und-hansemarkt' ); ?></p>

        <?php if ( $updated > 0 ) : ?>
            <div class="notice notice-success">
                <p><?php printf( esc_html__( '%d Bilder aktualisiert.', 'korn-und-hansemarkt' ), $updated ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'kuh_update_media_meta' ); ?>
            <p>
                <button type="submit" name="kuh_update_media_meta" value="1" class="button button-primary">
                    <?php esc_html_e( 'Metadaten jetzt aktualisieren', 'korn-und-hansemarkt' ); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}
