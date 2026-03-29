<?php
/**
 * Custom Post Type: Partner / Unterstützer
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT „Partner" registrieren
 */
function kuh_register_partner_cpt() {
    register_post_type( 'kuh_partner', array(
        'labels'       => array(
            'name'               => __( 'Partner', 'korn-und-hansemarkt' ),
            'singular_name'      => __( 'Partner', 'korn-und-hansemarkt' ),
            'add_new'            => __( 'Neuen Partner anlegen', 'korn-und-hansemarkt' ),
            'add_new_item'       => __( 'Neuen Partner anlegen', 'korn-und-hansemarkt' ),
            'edit_item'          => __( 'Partner bearbeiten', 'korn-und-hansemarkt' ),
            'new_item'           => __( 'Neuer Partner', 'korn-und-hansemarkt' ),
            'view_item'          => __( 'Partner ansehen', 'korn-und-hansemarkt' ),
            'search_items'       => __( 'Partner suchen', 'korn-und-hansemarkt' ),
            'not_found'          => __( 'Keine Partner gefunden', 'korn-und-hansemarkt' ),
            'not_found_in_trash' => __( 'Keine Partner im Papierkorb', 'korn-und-hansemarkt' ),
            'menu_name'          => __( 'Partner', 'korn-und-hansemarkt' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'partners',
        'menu_icon'    => 'dashicons-groups',
        'menu_position'=> 25,
        'supports'     => array( 'title', 'thumbnail' ),
        'has_archive'  => false,
        'rewrite'      => false,
    ) );

    // Meta-Feld: Website-URL des Partners
    register_post_meta( 'kuh_partner', 'kuh_partner_url', array(
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'string',
        'default'       => '',
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );

}
add_action( 'init', 'kuh_register_partner_cpt' );

/**
 * Meta-Box für Partner-Einstellungen (URL + Reihenfolge)
 */
function kuh_add_partner_meta_box() {
    add_meta_box(
        'kuh_partner_details',
        __( 'Partner-Details', 'korn-und-hansemarkt' ),
        'kuh_partner_meta_box_html',
        'kuh_partner',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'kuh_add_partner_meta_box' );

function kuh_partner_meta_box_html( $post ) {
    $url = get_post_meta( $post->ID, 'kuh_partner_url', true );
    wp_nonce_field( 'kuh_partner_meta', 'kuh_partner_meta_nonce' );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="kuh_partner_url"><?php esc_html_e( 'Website-URL', 'korn-und-hansemarkt' ); ?></label></th>
            <td>
                <input type="url" id="kuh_partner_url" name="kuh_partner_url"
                       value="<?php echo esc_url( $url ); ?>" class="regular-text"
                       placeholder="https://beispiel.de" />
                <p class="description"><?php esc_html_e( 'Link zur Website des Partners (optional).', 'korn-und-hansemarkt' ); ?></p>
            </td>
        </tr>
    </table>
    <p class="description" style="margin-top:1em;">
        <?php esc_html_e( 'Das Beitragsbild wird als Logo des Partners angezeigt. SVG-Logos werden empfohlen.', 'korn-und-hansemarkt' ); ?>
    </p>
    <?php
}

function kuh_save_partner_meta( $post_id ) {
    if ( ! isset( $_POST['kuh_partner_meta_nonce'] ) ||
         ! wp_verify_nonce( $_POST['kuh_partner_meta_nonce'], 'kuh_partner_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['kuh_partner_url'] ) ) {
        update_post_meta( $post_id, 'kuh_partner_url', esc_url_raw( $_POST['kuh_partner_url'] ) );
    }
}
add_action( 'save_post_kuh_partner', 'kuh_save_partner_meta' );

/**
 * Spalten in der Partner-Übersicht im Admin
 */
function kuh_partner_admin_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['partner_logo']  = __( 'Logo', 'korn-und-hansemarkt' );
            $new['partner_url']   = __( 'Website', 'korn-und-hansemarkt' );
        }
    }
    return $new;
}
add_filter( 'manage_kuh_partner_posts_columns', 'kuh_partner_admin_columns' );

function kuh_partner_admin_column_content( $column, $post_id ) {
    if ( 'partner_logo' === $column ) {
        $thumb = get_the_post_thumbnail( $post_id, 'thumbnail', array( 'style' => 'max-height:40px;width:auto;' ) );
        echo $thumb ?: '—';
    } elseif ( 'partner_url' === $column ) {
        $url = get_post_meta( $post_id, 'kuh_partner_url', true );
        echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>' : '—';
    }
}
add_action( 'manage_kuh_partner_posts_custom_column', 'kuh_partner_admin_column_content', 10, 2 );

/**
 * REST API Endpunkt: Alle Partner (für SPA + Block-Rendering)
 */
function kuh_register_partner_rest_route() {
    register_rest_route( 'kuh/v1', '/partners', array(
        'methods'             => 'GET',
        'callback'            => 'kuh_get_partners',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'kuh_register_partner_rest_route' );

/**
 * Partner-Daten als Array zurückgeben (intern + REST)
 */
function kuh_get_partners_data() {
    $partners = get_posts( array(
        'post_type'      => 'kuh_partner',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $data = array();
    foreach ( $partners as $partner ) {
        $thumb_id = get_post_thumbnail_id( $partner->ID );
        $logo_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

        $data[] = array(
            'id'   => $partner->ID,
            'name' => $partner->post_title,
            'logo' => $logo_url,
            'url'  => get_post_meta( $partner->ID, 'kuh_partner_url', true ),
        );
    }

    return $data;
}

function kuh_get_partners() {
    return new WP_REST_Response( kuh_get_partners_data(), 200 );
}
