<?php
/**
 * Galerie: Taxonomien für Medien (Jahr & Fotograf), Admin-UI und REST-Endpunkt.
 *
 * Die Bilder liegen als normale Attachments in der Medienbibliothek und werden
 * dort mit Jahr und Fotograf verschlagwortet.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_TAX_JAHR     = 'kuh_galerie_jahr';
const KUH_TAX_FOTOGRAF = 'kuh_fotograf';

/**
 * Taxonomien „Jahr" und „Fotograf" für Anhänge registrieren.
 *
 * Beide sind hierarchisch, damit im Backend eine Checkbox-Liste statt eines
 * Freitextfelds erscheint – das verhindert Tippfehler bei wiederkehrenden Namen.
 */
function kuh_register_gallery_taxonomies() {
    register_taxonomy( KUH_TAX_JAHR, 'attachment', array(
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

    register_taxonomy( KUH_TAX_FOTOGRAF, 'attachment', array(
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
}
add_action( 'init', 'kuh_register_gallery_taxonomies' );

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
 * Ein Attachment für die Galerie-Ausgabe aufbereiten.
 *
 * @param WP_Post $attachment Attachment-Post.
 * @return array
 */
function kuh_format_gallery_image( WP_Post $attachment ) {
    $photographers = array();
    foreach ( wp_get_object_terms( $attachment->ID, KUH_TAX_FOTOGRAF ) as $term ) {
        $photographers[] = array(
            'slug' => $term->slug,
            'name' => $term->name,
            'url'  => (string) get_term_meta( $term->term_id, 'kuh_fotograf_url', true ),
        );
    }

    $years = wp_list_pluck( wp_get_object_terms( $attachment->ID, KUH_TAX_JAHR ), 'slug' );
    $thumb = wp_get_attachment_image_src( $attachment->ID, 'medium_large' );
    $large = wp_get_attachment_image_src( $attachment->ID, '2048x2048' )
        ?: wp_get_attachment_image_src( $attachment->ID, 'full' );

    return array(
        'id'            => $attachment->ID,
        'title'         => $attachment->post_title,
        'caption'       => wp_get_attachment_caption( $attachment->ID ) ?: '',
        'alt'           => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
        'thumb'         => $thumb ? $thumb[0] : '',
        'width'         => $thumb ? (int) $thumb[1] : 0,
        'height'        => $thumb ? (int) $thumb[2] : 0,
        'full'          => $large ? $large[0] : '',
        'years'         => array_values( $years ),
        'photographers' => $photographers,
    );
}

/**
 * Galerie-Daten inklusive der vorkommenden Filterwerte laden.
 *
 * @param array $args {
 *     Optionale Parameter.
 *
 *     @type string $jahr     Slug eines Jahres, auf das vorgefiltert wird.
 *     @type string $fotograf Slug eines Fotografen, auf den vorgefiltert wird.
 *     @type int    $limit    Maximale Anzahl Bilder. -1 für alle. Default 500.
 *     @type string $order    ASC oder DESC (nach Upload-Datum). Default DESC.
 * }
 * @return array
 */
function kuh_get_gallery_data( array $args = array() ) {
    $args = wp_parse_args( $args, array(
        'jahr'     => '',
        'fotograf' => '',
        'limit'    => 500,
        'order'    => 'DESC',
    ) );

    $query_args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => (int) $args['limit'],
        'orderby'        => 'date',
        'order'          => 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC',
        'no_found_rows'  => true,
        'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'relation' => 'AND',
            array(
                'taxonomy' => KUH_TAX_JAHR,
                'operator' => 'EXISTS',
            ),
        ),
    );

    if ( $args['jahr'] ) {
        $query_args['tax_query'][] = array(
            'taxonomy' => KUH_TAX_JAHR,
            'field'    => 'slug',
            'terms'    => kuh_sanitize_gallery_slug( $args['jahr'] ),
        );
    }

    if ( $args['fotograf'] ) {
        $query_args['tax_query'][] = array(
            'taxonomy' => KUH_TAX_FOTOGRAF,
            'field'    => 'slug',
            'terms'    => kuh_sanitize_gallery_slug( $args['fotograf'] ),
        );
    }

    $query  = new WP_Query( $query_args );
    $images = array_map( 'kuh_format_gallery_image', $query->posts );

    return array(
        'images'        => $images,
        'years'         => kuh_get_gallery_terms( KUH_TAX_JAHR, $images, 'years' ),
        'photographers' => kuh_get_gallery_terms( KUH_TAX_FOTOGRAF, $images, 'photographers' ),
    );
}

/**
 * Die in den geladenen Bildern tatsächlich vorkommenden Terms einsammeln.
 *
 * So enthält die Filterleiste nie Optionen, die zu null Treffern führen.
 *
 * @param string $taxonomy Taxonomie-Slug.
 * @param array  $images   Aufbereitete Bilder aus kuh_format_gallery_image().
 * @param string $key      Schlüssel im Bild-Array (`years` oder `photographers`).
 * @return array
 */
function kuh_get_gallery_terms( $taxonomy, array $images, $key ) {
    $used = array();
    foreach ( $images as $image ) {
        foreach ( $image[ $key ] as $entry ) {
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
            'limit'    => array(
                'type'    => 'integer',
                'default' => 500,
                'minimum' => 1,
                'maximum' => 2000,
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
        'limit'    => $request->get_param( 'limit' ),
    ) ), 200 );
}
