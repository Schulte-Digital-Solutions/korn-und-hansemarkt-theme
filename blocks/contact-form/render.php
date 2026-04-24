<?php
/**
 * Server-Side Render fuer den Kontaktformular-Block.
 *
 * @var array    $attributes Block-Attribute
 * @var string   $content    Gerenderter InnerBlocks-Inhalt
 * @var WP_Block $block      Block-Instanz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$subject         = sanitize_text_field( $attributes['subject'] ?? __( 'Kontaktanfrage', 'korn-und-hansemarkt' ) );
$recipient_email = sanitize_email( $attributes['recipientEmail'] ?? '' );
$recipient_token = kuh_create_recipient_token( $recipient_email );
$fields          = kuh_sanitize_contact_block_fields( $attributes['fields'] ?? array() );
$form_title      = sanitize_text_field( $attributes['formTitle'] ?? '' );
$form_intro      = sanitize_textarea_field( $attributes['formIntro'] ?? '' );
$submit_label    = sanitize_text_field( $attributes['submitLabel'] ?? __( 'Nachricht senden', 'korn-und-hansemarkt' ) );
$success_message = sanitize_text_field( $attributes['successMessage'] ?? __( 'Vielen Dank! Deine Nachricht wurde gesendet.', 'korn-und-hansemarkt' ) );
$privacy_note    = sanitize_textarea_field( $attributes['privacyNote'] ?? '' );

if ( empty( $fields ) ) {
    $fields = kuh_sanitize_contact_block_fields( array(
        array(
            'id'       => 'name',
            'name'     => 'name',
            'label'    => $attributes['nameLabel'] ?? __( 'Name', 'korn-und-hansemarkt' ),
            'type'     => 'text',
            'required' => true,
            'placeholder' => $attributes['namePlaceholder'] ?? '',
        ),
        array(
            'id'       => 'email',
            'name'     => 'email',
            'label'    => $attributes['emailLabel'] ?? __( 'E-Mail', 'korn-und-hansemarkt' ),
            'type'     => 'email',
            'required' => true,
            'placeholder' => $attributes['emailPlaceholder'] ?? '',
        ),
        array(
            'id'       => 'message',
            'name'     => 'message',
            'label'    => $attributes['messageLabel'] ?? __( 'Nachricht', 'korn-und-hansemarkt' ),
            'type'     => 'textarea',
            'required' => true,
            'placeholder' => $attributes['messagePlaceholder'] ?? '',
        ),
    ) );
}

$block_data = array(
    'subject'        => $subject,
    'recipientEmail' => $recipient_email,
    'recipientToken' => $recipient_token,
    'fields'         => $fields,
    'fieldsToken'    => kuh_create_form_fields_token( $fields ),
    'formTitle'      => $form_title,
    'formIntro'      => $form_intro,
    'submitLabel'    => $submit_label,
    'successMessage' => $success_message,
    'privacyNote'    => $privacy_note,
);

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class'                 => 'kuh-contact-form not-prose',
    'data-kuh-contact-form' => wp_json_encode( $block_data ),
) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore ?>></div>
