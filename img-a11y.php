<?php
/**
 * Plugin Name: IMG A11Y
 * Description: Adds fields for decorative image marking, long descriptions, and accessibility prompts to the WordPress media editor. Validates on post save to ensure all images meet accessibility requirements.
 * Version: 1.0.0
 * Author: Robert DeVore
 * Author URI: https://robertdevore.com/
 * License: GPL-2.0+
 * Text Domain: img-a11y
 * Domain Path: /languages
 * Update URI: https://github.com/robertdevore/img-a11y/
 */

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Set up update checker
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/robertdevore/img-a11y/',
    __FILE__,
    'img-a11y'
);
$myUpdateChecker->setBranch( 'main' );

// Current plugin version.
define( 'IMG_A11Y_VERSION', '1.0.0' );

/**
 * Block saving if images are missing alt tags (Classic Editor).
 *
 * @param int $post_id The ID of the post being saved.
 */
function img_a11y_block_save_if_missing_alt_classic( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $content = get_post_field( 'post_content', $post_id );

    if ( img_a11y_has_images_without_alt( $content ) ) {
        add_filter( 'redirect_post_location', function( $location ) {
            return add_query_arg( 'img_a11y_error', 'missing_alt', $location );
        });

        remove_action( 'save_post', 'img_a11y_block_save_if_missing_alt_classic' );
        wp_update_post( [
            'ID'           => $post_id,
            'post_status'  => 'draft'
        ] );
    }
}
add_action( 'save_post', 'img_a11y_block_save_if_missing_alt_classic', 10 );

/**
 * Block saving if images are missing alt tags (Gutenberg Editor).
 *
 * @param WP_Post $prepared_post The post data being saved.
 * @param WP_REST_Request $request The REST request.
 * @return WP_Error|WP_Post The post data or a WP_Error on failure.
 */
function img_a11y_block_save_if_missing_alt_gutenberg( $prepared_post, $request ) {
    if ( isset( $prepared_post->post_content ) && img_a11y_has_images_without_alt( $prepared_post->post_content ) ) {
        return new WP_Error(
            'missing_alt_tags',
            __( 'Save failed: Please ensure all images in the content have alt tags for accessibility.', 'img-a11y' ),
            [ 'status' => 400 ]
        );
    }

    return $prepared_post;
}
add_filter( 'rest_pre_insert_post', 'img_a11y_block_save_if_missing_alt_gutenberg', 10, 2 );

/**
 * Check for images without alt tags in content, excluding decorative images by metadata.
 *
 * @param string $content The post content.
 * @return bool True if there are images without alt tags, false otherwise.
 */
function img_a11y_has_images_without_alt( $content ) {
    $dom = new DOMDocument();
    @$dom->loadHTML( $content );

    $images = $dom->getElementsByTagName( 'img' );

    foreach ( $images as $img ) {
        $img_id = attachment_url_to_postid( $img->getAttribute( 'src' ) );

        // If this image has decorative metadata, skip it
        $is_decorative = get_post_meta( $img_id, '_is_decorative', true );
        
        if ( !$is_decorative && ( ! $img->hasAttribute( 'alt' ) || trim( $img->getAttribute( 'alt' ) ) === '' ) ) {
            return true;
        }
    }
    return false;
}

// Display admin notice if save is blocked due to missing alt tags (Classic Editor).
add_action( 'admin_notices', function() {
    if ( isset( $_GET['img_a11y_error'] ) && $_GET['img_a11y_error'] === 'missing_alt' ) {
        echo '<div class="notice notice-error"><p>';
        _e( 'Save failed: Please ensure all images in the post content have alt tags or are marked as decorative for accessibility.', 'img-a11y' );
        echo '</p></div>';
    }
});

/**
 * Add "Decorative" checkbox to the Edit Media screen.
 *
 * @param array $form_fields Existing attachment fields.
 * @param WP_Post $post The attachment post.
 * @return array Modified attachment fields.
 */
function img_a11y_add_decorative_field_to_media( $form_fields, $post ) {
    $decorative = get_post_meta( $post->ID, '_is_decorative', true );
    $form_fields['is_decorative'] = [
        'label' => __( 'Mark as Decorative', 'img-a11y' ),
        'input' => 'html',
        'html'  => '<input type="checkbox" name="attachments[' . $post->ID . '][is_decorative]" value="1" ' . checked( $decorative, 1, false ) . ' />',
        'helps' => __( 'Check if this image is decorative and does not require alt text.', 'img-a11y' ),
    ];

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'img_a11y_add_decorative_field_to_media', 10, 2 );

/**
 * Save "Decorative" field from the Edit Media screen.
 *
 * @param array $post The attachment post data.
 * @param array $attachment The attachment fields from the request.
 * @return array The modified attachment data.
 */
function img_a11y_save_decorative_field_from_media( $post, $attachment ) {
    if ( isset( $attachment['is_decorative'] ) ) {
        update_post_meta( $post['ID'], '_is_decorative', 1 );
    } else {
        delete_post_meta( $post['ID'], '_is_decorative' );
    }

    return $post;
}
add_filter( 'attachment_fields_to_save', 'img_a11y_save_decorative_field_from_media', 10, 2 );

/**
 * Block saving on Edit Media screen if alt text is missing and "Decorative" is not checked.
 *
 * @param array $post The attachment post data.
 * @param array $attachment The attachment fields from the request.
 * @return array Modified attachment data if Alt text is present, redirects if missing and not decorative.
 */
function img_a11y_block_media_save_if_missing_alt( $post, $attachment ) {
    // Check if this is an image attachment
    if ( 'image' === substr( get_post_mime_type( $post['ID'] ), 0, 5 ) ) {
        // Retrieve 'Decorative' meta value
        $is_decorative = ! empty( get_post_meta( $post['ID'], '_is_decorative', true ) );

        // Redirect with error if Alt is missing and image is not decorative
        if ( ! $is_decorative && empty( $attachment['post_excerpt'] ) ) { // Alt text is stored in 'post_excerpt'
            wp_redirect( add_query_arg( [
                'post'  => $post['ID'],
                'action'=> 'edit',
                'img_a11y_media_error' => 'missing_alt'
            ], admin_url( 'post.php' ) ) );
            exit; // Stop the script
        }
    }

    return $post;
}
add_filter( 'attachment_fields_to_save', 'img_a11y_block_media_save_if_missing_alt', 10, 2 );

// Display admin notice on Edit Media screen if alt text is missing and "Decorative" is unchecked.
add_action( 'admin_notices', function() {
    if ( isset( $_GET['img_a11y_media_error'] ) && $_GET['img_a11y_media_error'] === 'missing_alt' ) {
        echo '<div class="notice notice-error"><p>';
        _e( 'Save failed: Please provide an Alt tag for accessibility or mark the image as decorative.', 'img-a11y' );
        echo '</p></div>';
    }
});

/**
 * Enqueue JavaScript for adding the "Decorative" field in the media modal.
 */
function img_a11y_enqueue_media_modal_script() {
    wp_enqueue_script(
        'img-a11y-media-modal',
        plugin_dir_url( __FILE__ ) . 'js/img-a11y-media-modal.js',
        [ 'jquery' ],
        IMG_A11Y_VERSION,
        true
    );

    // Localize strings and data for JavaScript
    wp_localize_script( 'img-a11y-media-modal', 'imgA11yData', [
        'decorativeLabel' => __( 'Mark as Decorative', 'img-a11y' ),
        'decorativeHelp'  => __( 'Check if this image is decorative and does not require alt text.', 'img-a11y' ),
    ] );
}
add_action( 'admin_enqueue_scripts', 'img_a11y_enqueue_media_modal_script' );
