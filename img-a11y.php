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

// Set up update checker.
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/robertdevore/img-a11y/',
    __FILE__,
    'img-a11y'
);
$myUpdateChecker->setBranch( 'main' );

// Define the plugin constants.
define( 'IMG_A11Y_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMG_A11Y_VERSION', '1.0.0' );

// Check if Composer's autoloader is already registered globally.
if ( ! class_exists( 'RobertDevore\WPComCheck\WPComPluginHandler' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use RobertDevore\WPComCheck\WPComPluginHandler;
new WPComPluginHandler( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );

// Add the List Table class.
require_once IMG_A11Y_PLUGIN_DIR . 'classes/Img_A11y_List_Table.php';

/**
 * Block saving if images are missing alt tags (Classic Editor).
 *
 * @param int $post_id The ID of the post being saved.
 * 
 * @since  1.0.0
 * @return void
 */
function img_a11y_block_save_if_missing_alt_classic( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $content = get_post_field( 'post_content', $post_id );

    if ( img_a11y_has_images_without_alt( $content ) ) {
        add_filter( 'redirect_post_location', function( $location ) {
            return add_query_arg( 'img_a11y_error', 'missing_alt', $location );
        } );

        remove_action( 'save_post', 'img_a11y_block_save_if_missing_alt_classic' );
        wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'draft'
        ] );
    }
}
add_action( 'save_post', 'img_a11y_block_save_if_missing_alt_classic', 10 );

/**
 * Block saving if images are missing alt tags (Gutenberg Editor).
 *
 * @param WP_Post $prepared_post The post data being saved.
 * @param WP_REST_Request $request The REST request.
 * 
 * @since  1.0.0
 * @return WP_Error|WP_Post The post data or a WP_Error on failure.
 */
function img_a11y_block_save_if_missing_alt_gutenberg( $prepared_post, $request ) {
    if ( isset( $prepared_post->post_content ) && img_a11y_has_images_without_alt( $prepared_post->post_content ) ) {
        return new WP_Error(
            'missing_alt_tags',
            esc_html__( 'Save failed: Please ensure all images in the content have alt tags for accessibility.', 'img-a11y' ),
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
 * 
 * @since  1.0.0
 * @return bool True if there are images without alt tags, false otherwise.
 */
function img_a11y_has_images_without_alt( $content ) {
    // Return false immediately if content is empty.
    if ( empty( trim( $content ) ) ) {
        return false;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML( $content );

    $images = $dom->getElementsByTagName( 'img' );

    foreach ( $images as $img ) {
        $img_id = attachment_url_to_postid( $img->getAttribute( 'src' ) );

        // If this image has decorative metadata, skip it.
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
        esc_html_e( 'Save failed: Please ensure all images in the post content have alt tags or are marked as decorative for accessibility.', 'img-a11y' );
        echo '</p></div>';
    }
});

/**
 * Add "Decorative" checkbox to the Edit Media screen.
 *
 * @param array $form_fields Existing attachment fields.
 * @param WP_Post $post The attachment post.
 * 
 * @since  1.0.0
 * @return array Modified attachment fields.
 */
function img_a11y_add_decorative_field_to_media( $form_fields, $post ) {
    $decorative = get_post_meta( $post->ID, '_is_decorative', true );
    $form_fields['is_decorative'] = [
        'label' => esc_html__( 'Mark as Decorative', 'img-a11y' ),
        'input' => 'html',
        'html'  => '<input type="checkbox" name="attachments[' . $post->ID . '][is_decorative]" value="1" ' . checked( $decorative, 1, false ) . ' />',
        'helps' => esc_html__( 'Check if this image is decorative and does not require alt text.', 'img-a11y' ),
    ];

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'img_a11y_add_decorative_field_to_media', 10, 2 );

/**
 * Save "Decorative" field from the Edit Media screen.
 *
 * @param array $post The attachment post data.
 * @param array $attachment The attachment fields from the request.
 * 
 * @since  1.0.0
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
 * 
 * @since  1.0.0
 * @return array Modified attachment data if Alt text is present, redirects if missing and not decorative.
 */
function img_a11y_block_media_save_if_missing_alt( $post, $attachment ) {
    // Check if this is an image attachment.
    if ( 'image' === substr( get_post_mime_type( $post['ID'] ), 0, 5 ) ) {
        // Retrieve 'Decorative' meta value.
        $is_decorative = ! empty( get_post_meta( $post['ID'], '_is_decorative', true ) );

        // Redirect with error if Alt is missing and image is not decorative.
        if ( ! $is_decorative && empty( $attachment['post_excerpt'] ) ) {
            wp_redirect( add_query_arg( [
                'post'                 => $post['ID'],
                'action'               => 'edit',
                'img_a11y_media_error' => 'missing_alt'
            ], admin_url( 'post.php' ) ) );
            exit;
        }
    }

    return $post;
}
add_filter( 'attachment_fields_to_save', 'img_a11y_block_media_save_if_missing_alt', 10, 2 );

/**
 * Display an admin notice on the Edit Media screen if the alt text is missing 
 * and the "Decorative" checkbox is unchecked.
 *
 * This function hooks into 'admin_notices' and checks for the 'img_a11y_media_error'
 * query parameter. If the error is set to 'missing_alt', it displays an error message.
 *
 * @since  1.0.0
 * @return void
 */
function img_a11y_display_admin_notice() {
    if ( isset( $_GET['img_a11y_media_error'] ) && $_GET['img_a11y_media_error'] === 'missing_alt' ) {
        echo '<div class="notice notice-error"><p>';
        esc_html_e( 'Save failed: Please provide an Alt tag for accessibility or mark the image as decorative.', 'img-a11y' );
        echo '</p></div>';
    }
}
add_action( 'admin_notices', 'img_a11y_display_admin_notice' );

/**
 * Enqueue JavaScript for adding the "Decorative" field in the media modal.
 * 
 * @since  1.0.0
 * @return void
 */
function img_a11y_enqueue_media_modal_script() {
    wp_enqueue_style(
        'img-a11y-styles',
        plugin_dir_url( __FILE__ ) . 'assets/css/img-a11y-styles.css',
        [],
        IMG_A11Y_VERSION,
        'all'
    );

    wp_enqueue_script(
        'img-a11y-media-modal',
        plugin_dir_url( __FILE__ ) . 'assets/js/img-a11y-media-modal.js',
        [ 'jquery' ],
        IMG_A11Y_VERSION,
        true
    );

    wp_localize_script( 'img-a11y-media-modal', 'imgA11yData', [
        'decorativeLabel' => esc_html__( 'Mark as Decorative', 'img-a11y' ),
        'decorativeHelp'  => esc_html__( 'Check if this image is decorative and does not require alt text.', 'img-a11y' ),
    ] );

    wp_enqueue_script(
        'img-a11y-inline-edit',
        plugin_dir_url( __FILE__ ) . 'assets/js/img-a11y-inline-edit.js',
        [ 'jquery' ],
        IMG_A11Y_VERSION,
        true
    );

    wp_localize_script( 'img-a11y-inline-edit', 'imgA11yAjax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'img_a11y_nonce' ),
    ] );

}
add_action( 'admin_enqueue_scripts', 'img_a11y_enqueue_media_modal_script' );

/**
 * Add an admin menu page for images without alt text.
 *
 * @since  1.0.0
 * @return void
 */
function img_a11y_add_admin_menu() {
    add_media_page(
        esc_html__( 'IMG A11Y', 'img-a11y' ),
        esc_html__( 'IMG A11Y', 'img-a11y' ),
        'manage_options',
        'img-a11y-images-without-alt-text',
        'img_a11y_settings_page'
    );
}
add_action( 'admin_menu', 'img_a11y_add_admin_menu' );

/**
 * Display the admin page listing images without alt text.
 *
 * @since  1.0.0
 * @return void
 */
function img_a11y_settings_page() {
    $list_table = new IMG_A11Y_List_Table();
    $list_table->prepare_items();

    // Count statistics for the filters.
    $decorative_count = count( get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_is_decorative',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ] ) );

    $non_decorative_no_alt_count = count( get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_is_decorative',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_is_decorative',
                        'value'   => '0',
                        'compare' => '=',
                    ],
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_wp_attachment_image_alt',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_wp_attachment_image_alt',
                        'value'   => '',
                        'compare' => '=',
                    ],
                ],
            ],
        ],
    ] ) );

    $non_decorative_with_alt_count = count( get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_is_decorative',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_is_decorative',
                        'value'   => '0',
                        'compare' => '=',
                    ],
                ],
                [
                    'key'     => '_wp_attachment_image_alt',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
        ],
    ] ) );

    $filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'non_decorative_no_alt';

    $base_url   = admin_url( 'upload.php' );
    $query_args = [
        'page' => 'img-a11y-images-without-alt-text',
    ];

    ?>
    <div class="wrap">
        <h1>
            <?php esc_html_e( 'Images Accessibility Overview', 'img-a11y' ); ?>
            <a id="img-a11y-support-btn" href="https://robertdevore.com/contact/" target="_blank" class="button button-alt" style="margin-left: 10px;">
                <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> <?php esc_html_e( 'Support', 'img-a11y' ); ?>
            </a>
            <a id="img-a11y-docs-btn" href="https://robertdevore.com/articles/img-a11y/" target="_blank" class="button button-alt" style="margin-left: 5px;">
                <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> <?php esc_html_e( 'Documentation', 'img-a11y' ); ?>
            </a>
        </h1>
        <hr />
        <div class="img-a11y-stats">
            <div class="img-a11y-stat-item <?php echo ( $filter === 'decorative' ) ? 'active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( array_merge( $query_args, [ 'filter' => 'decorative' ] ), $base_url ) ); ?>" style="text-decoration: none; color: inherit;">
                    <h2><?php echo esc_html( $decorative_count ); ?></h2>
                    <p><?php esc_html_e( 'Decorative Images', 'img-a11y' ); ?></p>
                </a>
            </div>
            <div class="img-a11y-stat-item <?php echo ( $filter === 'non_decorative_no_alt' ) ? 'active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( array_merge( $query_args, [ 'filter' => 'non_decorative_no_alt' ] ), $base_url ) ); ?>" style="text-decoration: none; color: inherit;">
                    <h2><?php echo esc_html( $non_decorative_no_alt_count ); ?></h2>
                    <p><?php esc_html_e( 'Non-Decorative Images Without Alt Text', 'img-a11y' ); ?></p>
                </a>
            </div>
            <div class="img-a11y-stat-item <?php echo ( $filter === 'non_decorative_with_alt' ) ? 'active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( array_merge( $query_args, [ 'filter' => 'non_decorative_with_alt' ] ), $base_url ) ); ?>" style="text-decoration: none; color: inherit;">
                    <h2><?php echo esc_html( $non_decorative_with_alt_count ); ?></h2>
                    <p><?php esc_html_e( 'Non-Decorative Images With Alt Text', 'img-a11y' ); ?></p>
                </a>
            </div>
        </div>
        <hr />
        <form method="get">
            <input type="hidden" name="page" value="img-a11y-images-without-alt-text" />
            <input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>" />
            <?php $list_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Enqueue custom admin CSS for the media editor screens.
 * 
 * @since  1.0.0
 * @return void
 */
function img_a11y_add_admin_css() {
    $screen = get_current_screen();

    // Only add CSS for media-related admin screens.
    if ( $screen && ( $screen->base === 'post' || $screen->base === 'upload' ) ) {
        echo '<style>
            .compat-field-is_decorative p { display: inline-block; }
        </style>';
    }
}
add_action( 'admin_head', 'img_a11y_add_admin_css' );

/**
 * Handles the AJAX request to update the alt text of an image attachment.
 *
 * This function verifies the nonce for security, checks user permissions,
 * and updates the `_wp_attachment_image_alt` meta field for the given attachment.
 * It then returns a JSON success or error response.
 *
 * @since  1.0.0
 * @return void Outputs a JSON response and exits.
 */
function img_a11y_update_alt_text() {
    // Verify nonce for security.
    check_ajax_referer( 'img_a11y_nonce', 'nonce' );

    // Get the data.
    $attachment_id = intval( $_POST['id'] );
    $alt_text      = sanitize_text_field( $_POST['alt_text'] );

    // Check if the user has permission to edit this attachment.
    if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this image.', 'img-a11y' ) ] );
    }

    // Update the alt text meta field.
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

    // Return success response.
    wp_send_json_success( [ 'message' => __( 'Alt text updated successfully.', 'img-a11y' ) ] );
}
add_action( 'wp_ajax_img_a11y_update_alt_text', 'img_a11y_update_alt_text' );
