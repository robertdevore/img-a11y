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
    // Avoid autosaves, revisions, and saves by unauthorized users
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Get post content
    $content = get_post_field( 'post_content', $post_id );

    // Check if images lack alt tags
    if ( img_a11y_has_images_without_alt( $content ) ) {
        // Set an error flag for the admin notice and stop the save process
        add_filter( 'redirect_post_location', function( $location ) {
            return add_query_arg( 'img_a11y_error', 'missing_alt', $location );
        });

        // Set the post to draft to prevent publishing
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
        // Block save with an error message
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
 * Check for images without alt tags in content.
 *
 * @param string $content The post content.
 * @return bool True if there are images without alt tags, false otherwise.
 */
function img_a11y_has_images_without_alt( $content ) {
    $dom = new DOMDocument();
    @$dom->loadHTML( $content );

    $images = $dom->getElementsByTagName( 'img' );

    foreach ( $images as $img ) {
        if ( ! $img->hasAttribute( 'alt' ) || trim( $img->getAttribute( 'alt' ) ) === '' ) {
            return true; // Found an image without an alt attribute
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