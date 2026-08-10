<?php
/**
 * Galerie: Bilder aus der Medienbibliothek und YouTube-Videos,
 * gemeinsam gefiltert nach Jahr und Fotograf.
 *
 * Bilder liegen als normale Attachments in der Medienbibliothek, Videos als
 * Beiträge des CPT `kuh_galerie_video`. Beide teilen sich dieselben Taxonomien.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_TAX_JAHR     = 'kuh_galerie_jahr';
const KUH_TAX_FOTOGRAF = 'kuh_fotograf';
const KUH_CPT_VIDEO    = 'kuh_galerie_video';

/**
 * Taxonomien „Jahr" und „Fotograf" sowie den Video-CPT registrieren.
 *
 * Die Taxonomien sind hierarchisch, damit im Backend eine Checkbox-Liste statt
 * eines Freitextfelds erscheint – das verhindert Tippfehler bei wiederkehrenden
 * Namen.
 */
function kuh_register_gallery_taxonomies() {
    register_post_type( KUH_CPT_VIDEO, array(
        'labels'       => array(
            'name'               => __( 'Galerie-Videos', 'korn-und-hansemarkt' ),
            'singular_name'      => __( 'Galerie-Video', 'korn-und-hansemarkt' ),
            'add_new'            => __( 'Neues Video hinzufügen', 'korn-und-hansemarkt' ),
            'add_new_item'       => __( 'Neues Video hinzufügen', 'korn-und-hansemarkt' ),
            'edit_item'          => __( 'Video bearbeiten', 'korn-und-hansemarkt' ),
            'new_item'           => __( 'Neues Video', 'korn-und-hansemarkt' ),
            'view_item'          => __( 'Video ansehen', 'korn-und-hansemarkt' ),
            'search_items'       => __( 'Videos suchen', 'korn-und-hansemarkt' ),
            'not_found'          => __( 'Keine Videos gefunden', 'korn-und-hansemarkt' ),
            'not_found_in_trash' => __( 'Keine Videos im Papierkorb', 'korn-und-hansemarkt' ),
            'menu_name'          => __( 'Galerie-Videos', 'korn-und-hansemarkt' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'upload.php',
        'show_in_rest' => true,
        'rest_base'    => 'gallery-videos',
        'supports'     => array( 'title', 'thumbnail', 'excerpt' ),
        'has_archive'  => false,
        'rewrite'      => false,
    ) );

    $object_types = array( 'attachment', KUH_CPT_VIDEO );

    register_taxonomy( KUH_TAX_JAHR, $object_types, array(
        'labels'             => array(
            'name'          => __( 'Jahre', 'korn-und-hansemarkt' ),
            'singular_name' => __( 'Jahr', 'korn-und-hansemarkt' ),
            'all_items'     => __( 'Alle Jahre', 'korn-und-hansemarkt' ),
            'edit_item'     => __( 'Jahr bearbeiten', 'korn-und-hansemarkt' ),
            'add_new_item'  => __( 'Neues Jahr hinzufügen', 'korn-und-hansemarkt' ),
            'search_items'  => __( 'Jahre durchsuchen', 'korn-und-hansemarkt' ),
            'menu_name'     => __( 'Galerie-Jahre', 'korn-und-hansemarkt' ),
        ),
        'public'             => true,
        'publicly_queryable' => false,
        'hierarchical'       => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'rest_base'          => 'gallery-years',
        'rewrite'            => false,
    ) );

    register_taxonomy( KUH_TAX_FOTOGRAF, $object_types, array(
        'labels'             => array(
            'name'          => __( 'Fotografen', 'korn-und-hansemarkt' ),
            'singular_name' => __( 'Fotograf/in', 'korn-und-hansemarkt' ),
            'all_items'     => __( 'Alle Fotografen', 'korn-und-hansemarkt' ),
            'edit_item'     => __( 'Fotograf/in bearbeiten', 'korn-und-hansemarkt' ),
            'add_new_item'  => __( 'Neue/n Fotograf/in hinzufügen', 'korn-und-hansemarkt' ),
            'search_items'  => __( 'Fotografen durchsuchen', 'korn-und-hansemarkt' ),
            'menu_name'     => __( 'Fotografen', 'korn-und-hansemarkt' ),
        ),
        'public'             => true,
        'publicly_queryable' => false,
        'hierarchical'       => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'rest_base'          => 'photographers',
        'rewrite'            => false,
    ) );

    register_term_meta( KUH_TAX_FOTOGRAF, 'kuh_fotograf_url', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => function () {
            return current_user_can( 'manage_categories' );
        },
    ) );

    register_post_meta( KUH_CPT_VIDEO, 'kuh_video_url', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );
}
add_action( 'init', 'kuh_register_gallery_taxonomies' );

/**
 * YouTube-Video-ID aus einer URL (oder einer blanken ID) extrahieren.
 *
 * @param string $url Eingabe aus dem Backend.
 * @return string Video-ID oder leerer String.
 */
function kuh_get_youtube_id( $url ) {
    $url = trim( (string) $url );

    if ( '' === $url ) {
        return '';
    }

    if ( preg_match( '#^[A-Za-z0-9_-]{11}$#', $url ) ) {
        return $url;
    }

    $patterns = array(
        '#youtu\.be/([A-Za-z0-9_-]{11})#',
        '#youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})#',
        '#youtube(?:-nocookie)?\.com/(?:embed|v|shorts|live)/([A-Za-z0-9_-]{11})#',
    );

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }
    }

    return '';
}

/**
 * Feld „Website/Instagram" im Formular zum Anlegen eines Fotografen.
 */
function kuh_fotograf_add_form_field() {
    wp_nonce_field( 'kuh_fotograf_meta', 'kuh_fotograf_meta_nonce' );
    ?>
    <div class="form-field">
        <label for="kuh_fotograf_url"><?php esc_html_e( 'Website / Instagram', 'korn-und-hansemarkt' ); ?></label>
        <input type="url" name="kuh_fotograf_url" id="kuh_fotograf_url" value="" placeholder="https://instagram.com/…" />
        <p><?php esc_html_e( 'Optional. Wird in der Galerie als Link auf den Fotografen-Namen verwendet.', 'korn-und-hansemarkt' ); ?></p>
    </div>
    <?php
}
add_action( KUH_TAX_FOTOGRAF . '_add_form_fields', 'kuh_fotograf_add_form_field' );

/**
 * Feld „Website/Instagram" im Bearbeiten-Formular eines Fotografen.
 *
 * @param WP_Term $term Term-Objekt.
 */
function kuh_fotograf_edit_form_field( $term ) {
    $url = get_term_meta( $term->term_id, 'kuh_fotograf_url', true );
    wp_nonce_field( 'kuh_fotograf_meta', 'kuh_fotograf_meta_nonce' );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="kuh_fotograf_url"><?php esc_html_e( 'Website / Instagram', 'korn-und-hansemarkt' ); ?></label></th>
        <td>
            <input type="url" name="kuh_fotograf_url" id="kuh_fotograf_url"
                   value="<?php echo esc_url( $url ); ?>" placeholder="https://instagram.com/…" />
            <p class="description"><?php esc_html_e( 'Optional. Wird in der Galerie als Link auf den Fotografen-Namen verwendet.', 'korn-und-hansemarkt' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( KUH_TAX_FOTOGRAF . '_edit_form_fields', 'kuh_fotograf_edit_form_field' );

/**
 * Fotografen-URL speichern.
 *
 * @param int $term_id Term-ID.
 */
function kuh_save_fotograf_meta( $term_id ) {
    if ( ! isset( $_POST['kuh_fotograf_meta_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kuh_fotograf_meta_nonce'] ) ), 'kuh_fotograf_meta' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
    if ( ! isset( $_POST['kuh_fotograf_url'] ) ) {
        return;
    }

    update_term_meta( $term_id, 'kuh_fotograf_url', esc_url_raw( wp_unslash( $_POST['kuh_fotograf_url'] ) ) );
}
add_action( 'created_' . KUH_TAX_FOTOGRAF, 'kuh_save_fotograf_meta' );
add_action( 'edited_' . KUH_TAX_FOTOGRAF, 'kuh_save_fotograf_meta' );

/**
 * Meta-Box für die YouTube-URL eines Galerie-Videos.
 */
function kuh_add_video_meta_box() {
    add_meta_box(
        'kuh_video_details',
        __( 'YouTube-Video', 'korn-und-hansemarkt' ),
        'kuh_video_meta_box_html',
        KUH_CPT_VIDEO,
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'kuh_add_video_meta_box' );

/**
 * @param WP_Post $post Video-Post.
 */
function kuh_video_meta_box_html( $post ) {
    $url      = get_post_meta( $post->ID, 'kuh_video_url', true );
    $video_id = kuh_get_youtube_id( $url );
    wp_nonce_field( 'kuh_video_meta', 'kuh_video_meta_nonce' );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="kuh_video_url"><?php esc_html_e( 'YouTube-URL', 'korn-und-hansemarkt' ); ?></label></th>
            <td>
                <input type="text" id="kuh_video_url" name="kuh_video_url"
                       value="<?php echo esc_attr( $url ); ?>" class="large-text"
                       placeholder="https://www.youtube.com/watch?v=…" />
                <p class="description">
                    <?php esc_html_e( 'Normale Video-URL, youtu.be-Kurzlink oder Shorts-URL.', 'korn-und-hansemarkt' ); ?>
                    <?php if ( $url && ! $video_id ) : ?>
                        <strong style="color:#b32d2e;"><?php esc_html_e( 'Aus dieser Eingabe konnte keine Video-ID gelesen werden.', 'korn-und-hansemarkt' ); ?></strong>
                    <?php elseif ( $video_id ) : ?>
                        <?php printf( esc_html__( 'Erkannte Video-ID: %s', 'korn-und-hansemarkt' ), '<code>' . esc_html( $video_id ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
    </table>
    <p class="description">
        <?php esc_html_e( 'Das Beitragsbild dient als Vorschaubild in der Galerie. Bleibt es leer, wird beim Speichern automatisch das YouTube-Vorschaubild übernommen. Erst beim Klick auf das Video wird eine Verbindung zu YouTube aufgebaut.', 'korn-und-hansemarkt' ); ?>
    </p>
    <?php
}

/**
 * YouTube-URL speichern.
 *
 * @param int $post_id Post-ID.
 */
function kuh_save_video_meta( $post_id ) {
    if ( ! isset( $_POST['kuh_video_meta_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kuh_video_meta_nonce'] ) ), 'kuh_video_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['kuh_video_url'] ) ) {
        update_post_meta( $post_id, 'kuh_video_url', sanitize_text_field( wp_unslash( $_POST['kuh_video_url'] ) ) );
    }

    kuh_maybe_sideload_video_poster( $post_id );
}
add_action( 'save_post_' . KUH_CPT_VIDEO, 'kuh_save_video_meta' );

/**
 * Vorschaubild von YouTube holen, solange kein Beitragsbild gesetzt ist.
 *
 * Lokale Kopie statt Hotlink: Sonst würde die Galerie schon beim Seitenaufruf
 * eine Verbindung zu YouTube herstellen.
 *
 * @param int $post_id Post-ID.
 */
function kuh_maybe_sideload_video_poster( $post_id ) {
    if ( get_post_thumbnail_id( $post_id ) ) {
        return;
    }

    $video_id = kuh_get_youtube_id( get_post_meta( $post_id, 'kuh_video_url', true ) );
    if ( ! $video_id ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    foreach ( array( 'maxresdefault', 'hqdefault' ) as $quality ) {
        $attachment_id = media_sideload_image(
            sprintf( 'https://i.ytimg.com/vi/%s/%s.jpg', $video_id, $quality ),
            $post_id,
            get_the_title( $post_id ),
            'id'
        );

        if ( ! is_wp_error( $attachment_id ) ) {
            wp_update_post( array(
                'ID'         => $attachment_id,
                'post_title' => sprintf(
                    /* translators: %s: Titel des Videos. */
                    __( 'Vorschaubild: %s', 'korn-und-hansemarkt' ),
                    get_the_title( $post_id )
                ),
            ) );
            set_post_thumbnail( $post_id, $attachment_id );
            return;
        }
    }
}

/**
 * Spalten in der Video-Übersicht im Admin.
 *
 * @param array $columns Bestehende Spalten.
 * @return array
 */
function kuh_video_admin_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['video_poster'] = __( 'Vorschau', 'korn-und-hansemarkt' );
            $new['video_id']     = __( 'Video-ID', 'korn-und-hansemarkt' );
        }
    }
    return $new;
}
add_filter( 'manage_' . KUH_CPT_VIDEO . '_posts_columns', 'kuh_video_admin_columns' );

/**
 * @param string $column  Spaltenname.
 * @param int    $post_id Post-ID.
 */
function kuh_video_admin_column_content( $column, $post_id ) {
    if ( 'video_poster' === $column ) {
        $thumb = get_the_post_thumbnail( $post_id, 'thumbnail', array( 'style' => 'max-height:48px;width:auto;' ) );
        echo $thumb ?: '—'; // phpcs:ignore WordPress.Security.EscapeOutput
    } elseif ( 'video_id' === $column ) {
        $video_id = kuh_get_youtube_id( get_post_meta( $post_id, 'kuh_video_url', true ) );
        echo $video_id ? '<code>' . esc_html( $video_id ) . '</code>' : '<span style="color:#b32d2e;">' . esc_html__( 'fehlt', 'korn-und-hansemarkt' ) . '</span>';
    }
}
add_action( 'manage_' . KUH_CPT_VIDEO . '_posts_custom_column', 'kuh_video_admin_column_content', 10, 2 );

/**
 * Filter-Dropdowns für Jahr und Fotograf über der Medienbibliothek (Listenansicht).
 */
function kuh_media_taxonomy_filters() {
    $screen = get_current_screen();
    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    foreach ( array( KUH_TAX_JAHR, KUH_TAX_FOTOGRAF ) as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $tax_object = get_taxonomy( $taxonomy );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reiner Anzeigefilter.
        $selected = isset( $_GET[ $taxonomy ] ) ? sanitize_key( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
        ?>
        <select name="<?php echo esc_attr( $taxonomy ); ?>">
            <option value=""><?php
                /* translators: %s: Name der Taxonomie. */
                printf( esc_html__( 'Alle %s', 'korn-und-hansemarkt' ), esc_html( $tax_object->labels->name ) );
            ?></option>
            <?php foreach ( $terms as $term ) : ?>
                <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>>
                    <?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
add_action( 'restrict_manage_posts', 'kuh_media_taxonomy_filters' );

/**
 * Einen Galerie-Eintrag (Bild oder Video) für die Ausgabe aufbereiten.
 *
 * @param WP_Post $post Attachment oder Video-Post.
 * @return array|null Null, wenn der Eintrag nicht darstellbar ist.
 */
function kuh_format_gallery_item( WP_Post $post ) {
    $is_video = KUH_CPT_VIDEO === $post->post_type;

    if ( ! $is_video && ! wp_attachment_is_image( $post->ID ) ) {
        return null;
    }

    $video_id = $is_video ? kuh_get_youtube_id( get_post_meta( $post->ID, 'kuh_video_url', true ) ) : '';
    if ( $is_video && ! $video_id ) {
        return null;
    }

    $photographers = array();
    foreach ( wp_get_object_terms( $post->ID, KUH_TAX_FOTOGRAF ) as $term ) {
        $photographers[] = array(
            'slug' => $term->slug,
            'name' => $term->name,
            'url'  => (string) get_term_meta( $term->term_id, 'kuh_fotograf_url', true ),
        );
    }

    $years     = wp_list_pluck( wp_get_object_terms( $post->ID, KUH_TAX_JAHR ), 'slug' );
    $thumb_id  = $is_video ? get_post_thumbnail_id( $post->ID ) : $post->ID;
    $thumb     = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium_large' ) : false;
    $large     = $is_video || ! $thumb_id
        ? false
        : ( wp_get_attachment_image_src( $thumb_id, '2048x2048' ) ?: wp_get_attachment_image_src( $thumb_id, 'full' ) );

    return array(
        'id'            => $post->ID,
        'type'          => $is_video ? 'video' : 'image',
        'videoId'       => $video_id,
        'title'         => $post->post_title,
        'caption'       => $is_video ? wp_strip_all_tags( $post->post_excerpt ) : ( wp_get_attachment_caption( $post->ID ) ?: '' ),
        'alt'           => $is_video ? $post->post_title : (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
        'thumb'         => $thumb ? $thumb[0] : '',
        'width'         => $thumb ? (int) $thumb[1] : 0,
        'height'        => $thumb ? (int) $thumb[2] : 0,
        'full'          => $large ? $large[0] : '',
        'years'         => array_values( $years ),
        'photographers' => $photographers,
    );
}

/**
 * Leichtgewichtiger Index aller Galerie-Einträge.
 *
 * Enthält nur ID, Typ und die zugeordneten Terms – genug zum Filtern, Sortieren
 * und Zählen, ohne für jeden Eintrag Bildgrößen und Meta zu laden. Erst die
 * Einträge der angeforderten Seite werden vollständig aufbereitet.
 *
 * @param string $order ASC oder DESC.
 * @return array Liste aus id, type, years, photographers – bereits sortiert.
 */
function kuh_get_gallery_index( $order = 'DESC' ) {
    static $cache = array();

    $order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
    if ( isset( $cache[ $order ] ) ) {
        return $cache[ $order ];
    }

    $query = new WP_Query( array(
        'post_type'              => array( 'attachment', KUH_CPT_VIDEO ),
        'post_status'            => array( 'inherit', 'publish' ),
        'posts_per_page'         => -1,
        'orderby'                => 'date',
        'order'                  => $order,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            array(
                'taxonomy' => KUH_TAX_JAHR,
                'operator' => 'EXISTS',
            ),
        ),
    ) );

    if ( empty( $query->posts ) ) {
        $cache[ $order ] = array();
        return $cache[ $order ];
    }

    $index = array();
    foreach ( $query->posts as $post_id ) {
        $index[ $post_id ] = array(
            'id'            => (int) $post_id,
            'type'          => '',
            'years'         => array(),
            'photographers' => array(),
        );
    }

    // Terms aller Einträge in einer einzigen Abfrage.
    $terms = wp_get_object_terms(
        array_keys( $index ),
        array( KUH_TAX_JAHR, KUH_TAX_FOTOGRAF ),
        array( 'fields' => 'all_with_object_id' )
    );

    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $key = KUH_TAX_JAHR === $term->taxonomy ? 'years' : 'photographers';
            $index[ $term->object_id ][ $key ][] = $term->slug;
        }
    }

    // Post-Typen ebenfalls gebündelt bestimmen.
    $video_ids = get_posts( array(
        'post_type'      => KUH_CPT_VIDEO,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post__in'       => array_keys( $index ),
        'no_found_rows'  => true,
    ) );
    $video_ids = array_flip( $video_ids );

    foreach ( $index as $post_id => $entry ) {
        $index[ $post_id ]['type'] = isset( $video_ids[ $post_id ] ) ? 'video' : 'image';
    }

    $index = array_values( $index );

    // Primär nach Galerie-Jahr. usort ist stabil, dadurch bleibt die
    // Datumssortierung der Abfrage innerhalb eines Jahres erhalten – und Videos
    // landen zwischen den Bildern ihres Jahres statt pauschal ganz oben.
    $descending = 'DESC' === $order;
    usort( $index, static function ( $a, $b ) use ( $descending ) {
        $year_a = kuh_gallery_sort_year( $a );
        $year_b = kuh_gallery_sort_year( $b );

        return $descending ? strnatcmp( $year_b, $year_a ) : strnatcmp( $year_a, $year_b );
    } );

    $cache[ $order ] = $index;

    return $index;
}

/**
 * Galerie-Daten seitenweise laden, inklusive der Filteroptionen.
 *
 * @param array $args {
 *     Optionale Parameter.
 *
 *     @type string $jahr     Slug eines Jahres, auf das gefiltert wird.
 *     @type string $fotograf Slug eines Fotografen, auf den gefiltert wird.
 *     @type string $typ      `bild` oder `video`; leer = beides.
 *     @type int    $page     1-basierte Seitennummer. Default 1.
 *     @type int    $per_page Einträge pro Seite. Default 48.
 *     @type string $order    ASC oder DESC (nach Galerie-Jahr, dann Datum). Default DESC.
 * }
 * @return array
 */
function kuh_get_gallery_data( array $args = array() ) {
    $args = wp_parse_args( $args, array(
        'jahr'     => '',
        'fotograf' => '',
        'typ'      => '',
        'page'     => 1,
        'per_page' => 48,
        'order'    => 'DESC',
    ) );

    $index    = kuh_get_gallery_index( $args['order'] );
    $jahr     = kuh_sanitize_gallery_slug( $args['jahr'] );
    $fotograf = kuh_sanitize_gallery_slug( $args['fotograf'] );
    $typ      = in_array( $args['typ'], array( 'bild', 'video' ), true ) ? $args['typ'] : '';

    $matching = array_values( array_filter( $index, static function ( $entry ) use ( $jahr, $fotograf, $typ ) {
        if ( $jahr && ! in_array( $jahr, $entry['years'], true ) ) {
            return false;
        }
        if ( $fotograf && ! in_array( $fotograf, $entry['photographers'], true ) ) {
            return false;
        }
        if ( $typ && $entry['type'] !== ( 'video' === $typ ? 'video' : 'image' ) ) {
            return false;
        }
        return true;
    } ) );

    $total    = count( $matching );
    $per_page = max( 1, (int) $args['per_page'] );
    $page     = max( 1, (int) $args['page'] );
    $offset   = ( $page - 1 ) * $per_page;
    $page_ids = wp_list_pluck( array_slice( $matching, $offset, $per_page ), 'id' );

    $items = array();
    if ( $page_ids ) {
        // Nur für die aktuelle Seite Bildgrößen, Meta und Terms laden.
        _prime_post_caches( $page_ids, true, true );
        foreach ( $page_ids as $post_id ) {
            $post = get_post( $post_id );
            $item = $post ? kuh_format_gallery_item( $post ) : null;
            if ( $item ) {
                $items[] = $item;
            }
        }
    }

    return array(
        'items'         => $items,
        'total'         => $total,
        'page'          => $page,
        'perPage'       => $per_page,
        'hasMore'       => $offset + count( $items ) < $total,
        'years'         => kuh_get_gallery_terms( KUH_TAX_JAHR, $index, 'years' ),
        'photographers' => kuh_get_gallery_terms( KUH_TAX_FOTOGRAF, $index, 'photographers' ),
    );
}

/**
 * Sortierschlüssel eines Eintrags: das jüngste zugeordnete Galerie-Jahr.
 *
 * @param array $item Aufbereiteter Eintrag.
 * @return string
 */
function kuh_gallery_sort_year( array $item ) {
    $years = $item['years'];

    if ( empty( $years ) ) {
        return '';
    }

    usort( $years, 'strnatcmp' );

    return (string) end( $years );
}

/**
 * Die in den geladenen Einträgen tatsächlich vorkommenden Terms einsammeln.
 *
 * So enthält die Filterleiste nie Optionen, die zu null Treffern führen.
 *
 * @param string $taxonomy Taxonomie-Slug.
 * @param array  $items    Aufbereitete Einträge aus kuh_format_gallery_item().
 * @param string $key      Schlüssel im Item-Array (`years` oder `photographers`).
 * @return array
 */
function kuh_get_gallery_terms( $taxonomy, array $items, $key ) {
    $used = array();
    foreach ( $items as $item ) {
        foreach ( $item[ $key ] as $entry ) {
            $slug          = is_array( $entry ) ? $entry['slug'] : $entry;
            $used[ $slug ] = ( $used[ $slug ] ?? 0 ) + 1;
        }
    }

    if ( empty( $used ) ) {
        return array();
    }

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'slug'       => array_keys( $used ),
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return array();
    }

    $result = array();
    foreach ( $terms as $term ) {
        $result[] = array(
            'slug'  => $term->slug,
            'name'  => $term->name,
            'count' => $used[ $term->slug ],
            'url'   => KUH_TAX_FOTOGRAF === $taxonomy
                ? (string) get_term_meta( $term->term_id, 'kuh_fotograf_url', true )
                : '',
        );
    }

    // Jahre absteigend (neuestes zuerst), Fotografen alphabetisch.
    if ( KUH_TAX_JAHR === $taxonomy ) {
        usort( $result, static fn( $a, $b ) => strnatcmp( $b['name'], $a['name'] ) );
    } else {
        usort( $result, static fn( $a, $b ) => strnatcmp( $a['name'], $b['name'] ) );
    }

    return $result;
}

/**
 * Term-Slug bereinigen.
 *
 * Eigene Funktion statt `sanitize_title` direkt: Die REST-API übergibt dem
 * sanitize_callback drei Argumente, und `sanitize_title` würde das zweite
 * (das WP_REST_Request-Objekt) bei leerer Eingabe als Fallback zurückgeben.
 *
 * @param mixed $value Rohwert.
 * @return string
 */
function kuh_sanitize_gallery_slug( $value ) {
    return sanitize_title( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * REST-Endpunkt: /wp-json/kuh/v1/gallery
 */
function kuh_register_gallery_rest_route() {
    register_rest_route( 'kuh/v1', '/gallery', array(
        'methods'             => 'GET',
        'callback'            => 'kuh_rest_get_gallery',
        'permission_callback' => '__return_true',
        'args'                => array(
            'jahr'     => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'kuh_sanitize_gallery_slug',
            ),
            'fotograf' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'kuh_sanitize_gallery_slug',
            ),
            'typ'      => array(
                'type'    => 'string',
                'default' => '',
                'enum'    => array( '', 'bild', 'video' ),
            ),
            'page'     => array(
                'type'    => 'integer',
                'default' => 1,
                'minimum' => 1,
            ),
            'per_page' => array(
                'type'    => 'integer',
                'default' => 48,
                'minimum' => 1,
                'maximum' => 200,
            ),
            'order'    => array(
                'type'    => 'string',
                'default' => 'DESC',
                'enum'    => array( 'ASC', 'DESC' ),
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'kuh_register_gallery_rest_route' );

/**
 * REST-Callback für /kuh/v1/gallery.
 *
 * @param WP_REST_Request $request Anfrage.
 * @return WP_REST_Response
 */
function kuh_rest_get_gallery( WP_REST_Request $request ) {
    return new WP_REST_Response( kuh_get_gallery_data( array(
        'jahr'     => $request->get_param( 'jahr' ),
        'fotograf' => $request->get_param( 'fotograf' ),
        'typ'      => $request->get_param( 'typ' ),
        'page'     => $request->get_param( 'page' ),
        'per_page' => $request->get_param( 'per_page' ),
        'order'    => $request->get_param( 'order' ),
    ) ), 200 );
}
