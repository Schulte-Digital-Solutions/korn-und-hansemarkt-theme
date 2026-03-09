<?php
/**
 * Custom Meta-Felder & Meta-Boxen
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta-Feld: Titel ausblenden (pro Seite/Beitrag)
 */
function kuh_register_title_meta() {
    $post_types = array( 'post', 'page' );
    foreach ( $post_types as $type ) {
        register_post_meta( $type, 'kuh_hide_title', array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'boolean',
            'default'       => false,
            'auth_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }
}
add_action( 'init', 'kuh_register_title_meta' );

/**
 * Meta-Box: Titel ausblenden
 */
function kuh_add_title_meta_box() {
    add_meta_box(
        'kuh_title_options',
        __( 'Titel-Anzeige', 'korn-und-hansemarkt' ),
        'kuh_title_meta_box_html',
        array( 'post', 'page' ),
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'kuh_add_title_meta_box' );

function kuh_title_meta_box_html( $post ) {
    $value = get_post_meta( $post->ID, 'kuh_hide_title', true );
    wp_nonce_field( 'kuh_title_meta', 'kuh_title_meta_nonce' );
    ?>
    <label>
        <input type="checkbox" name="kuh_hide_title" value="1" <?php checked( $value, '1' ); ?> />
        <?php esc_html_e( 'Titel auf dieser Seite ausblenden', 'korn-und-hansemarkt' ); ?>
    </label>
    <?php
}

function kuh_save_title_meta( $post_id ) {
    if ( ! isset( $_POST['kuh_title_meta_nonce'] ) ||
         ! wp_verify_nonce( $_POST['kuh_title_meta_nonce'], 'kuh_title_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $hide = isset( $_POST['kuh_hide_title'] ) ? '1' : '0';
    update_post_meta( $post_id, 'kuh_hide_title', $hide );
}
add_action( 'save_post', 'kuh_save_title_meta' );
