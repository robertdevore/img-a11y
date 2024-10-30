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
    // Return false immediately if content is empty
    if ( empty( trim( $content ) ) ) {
        return false;
    }

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

/**
 * Add an admin menu page for images without alt text.
 *
 * @return void
 */
function img_a11y_add_admin_menu() {
    add_media_page(
        __( 'IMG A11Y', 'img-a11y' ), // Page title
        __( 'IMG A11Y', 'img-a11y' ), // Menu title
        'manage_options', // Capability
        'img-a11y-images-without-alt-text', // Menu slug
        'img_a11y_settings_page' // Callback function
    );
}
add_action( 'admin_menu', 'img_a11y_add_admin_menu' );

/**
 * Display the admin page listing images without alt text.
 *
 * @return void
 */
function img_a11y_settings_page() {
    // Check if 'show_decorative' is set in GET parameters
    $show_decorative = isset( $_GET['show_decorative'] ) && $_GET['show_decorative'] == '1';

    // 1. Total Images
    $args_total_images = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];
    $total_images = get_posts( $args_total_images );
    $count_total_images = count( $total_images );

    // 2. Decorative Images Without Alt Text
    $args_decorative_no_alt = [
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
            'relation' => 'AND',
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
    ];
    $images_decorative_no_alt = get_posts( $args_decorative_no_alt );
    $count_decorative_no_alt = count( $images_decorative_no_alt );

    // 3. Decorative Images With Alt Text
    $args_decorative_with_alt = [
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
            [
                'key'     => '_wp_attachment_image_alt',
                'value'   => '',
                'compare' => '!=',
            ],
        ],
    ];
    $images_decorative_with_alt = get_posts( $args_decorative_with_alt );
    $count_decorative_with_alt = count( $images_decorative_with_alt );

    // 4. Non-Decorative Images Without Alt Text
    $args_non_decorative_no_alt = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
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
    ];
    $images_non_decorative_no_alt = get_posts( $args_non_decorative_no_alt );
    $count_non_decorative_no_alt = count( $images_non_decorative_no_alt );

    // 5. Non-Decorative Images With Alt Text
    $args_non_decorative_with_alt = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
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
    ];
    $images_non_decorative_with_alt = get_posts( $args_non_decorative_with_alt );
    $count_non_decorative_with_alt = count( $images_non_decorative_with_alt );

    // Now, let's display all counts
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Images Accessibility Overview', 'img-a11y' ); ?></h1>

        <!-- Enhanced Statistics Display -->
        <div class="img-a11y-stats">
            <div class="img-a11y-stat-item">
                <h2><?php echo esc_html( $count_decorative_with_alt + $count_decorative_no_alt ); ?></h2>
                <p><?php esc_html_e( 'Decorative Images', 'img-a11y' ); ?></p>
            </div>
            <div class="img-a11y-stat-item">
                <h2><?php echo esc_html( $count_non_decorative_no_alt ); ?></h2>
                <p><?php esc_html_e( 'Non-Decorative Images Without Alt Text', 'img-a11y' ); ?></p>
            </div>
            <div class="img-a11y-stat-item">
                <h2><?php echo esc_html( $count_non_decorative_with_alt ); ?></h2>
                <p><?php esc_html_e( 'Non-Decorative Images With Alt Text', 'img-a11y' ); ?></p>
            </div>
        </div>

    <form method="get" action="">
        <?php
        // Keep the page parameter to ensure the form submits to the same page
        if ( isset( $_GET['page'] ) ) {
            echo '<input type="hidden" name="page" value="' . esc_attr( $_GET['page'] ) . '">';
        }
        ?>
        <label>
            <input type="checkbox" name="show_decorative" value="1" <?php checked( $show_decorative ); ?> onchange="this.form.submit();">
            <?php esc_html_e( 'Include Decorative Images', 'img-a11y' ); ?>
        </label>
    </form>
    <hr style="margin: 24px 0;" />
    <style>
        .img-a11y-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        .img-a11y-stat-item {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            flex: 1;
            text-align: center;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .img-a11y-stat-item h2 {
            font-size: 2em;
            margin: 0;
            color: #007cba;
        }
        .img-a11y-stat-item p {
            margin: 10px 0 0;
            color: #555d66;
            font-size: 1em;
        }
        .img-a11y-thumbnail-column {
            width: 80px;
        }
        .img-a11y-id-column {
            max-width: 100px;
            word-break: break-word;
        }
        .img-a11y-decorative-column {
            width: 100px;
            text-align: center;
        }
    </style>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="img-a11y-thumbnail-column"><?php esc_html_e( 'Thumbnail', 'img-a11y' ); ?></th>
                    <th class="img-a11y-id-column"><?php esc_html_e( 'ID', 'img-a11y' ); ?></th>
                    <th><?php esc_html_e( 'Title', 'img-a11y' ); ?></th>
                    <th><?php esc_html_e( 'Decorative', 'img-a11y' ); ?></th>
                    <th><?php esc_html_e( 'File', 'img-a11y' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Build the meta query
                $meta_query = [
                    'relation' => 'AND',
                    // Images without alt text
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
                ];

                // If 'show_decorative' is not set, exclude decorative images
                if ( ! $show_decorative ) {
                    $meta_query[] = [
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
                    ];
                }

                $args = [
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post_mime_type' => 'image',
                    'posts_per_page' => -1,
                    'meta_query'     => $meta_query,
                ];

                $query = new WP_Query( $args );

                if ( $query->have_posts() ) {
                    while ( $query->have_posts() ) {
                        $query->the_post();
                        $id            = get_the_ID();
                        $title         = get_the_title();
                        $file          = wp_get_attachment_url( $id );
                        $edit_link     = get_edit_post_link( $id );
                        $thumbnail     = wp_get_attachment_image( $id, [ 80, 80 ] );
                        $is_decorative = get_post_meta( $id, '_is_decorative', true );

                        // Determine decorative status display
                        $decorative_display = $is_decorative ? __( 'Yes', 'img-a11y' ) : __( 'No', 'img-a11y' );

                        echo '<tr>';
                        echo '<td class="img-a11y-thumbnail-column">' . $thumbnail . '</td>';
                        echo '<td class="img-a11y-id-column"><a href="' . esc_url( $edit_link ) . '" target="_blank">' . esc_html( $id ) . '</a></td>';
                        echo '<td>' . esc_html( $title ) . '</td>';
                        echo '<td class="img-a11y-decorative-column">' . esc_html( $decorative_display ) . '</td>';
                        echo '<td><a href="' . esc_url( $file ) . '" target="_blank">' . esc_html__( 'View File', 'img-a11y' ) . '</a></td>';
                        echo '</tr>';
                    }
                    wp_reset_postdata();
                } else {
                    echo '<tr><td colspan="5">' . esc_html__( 'No images without alt text found.', 'img-a11y' ) . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
