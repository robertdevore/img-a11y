<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class IMG_A11Y_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Image', 'img-a11y' ),
            'plural'   => __( 'Images', 'img-a11y' ),
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'thumbnail' => __( 'Thumbnail', 'img-a11y' ),
            'id'        => __( 'ID', 'img-a11y' ),
            'alt_text'  => __( 'Alt Text', 'img-a11y' ),
        ];
    }

    public function prepare_items() {
        $per_page     = 36;
        $current_page = $this->get_pagenum();

        // Default query arguments.
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
        ];

        // Apply filters based on the selected tab.
        if ( isset( $_GET['filter'] ) ) {
            $filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'non_decorative_no_alt';

            if ( $filter === 'decorative' ) {
                $args['meta_query'] = [
                    [
                        'key'     => '_is_decorative',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                ];
            } elseif ( $filter === 'non_decorative_no_alt' ) {
                $args['meta_query'] = [
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
                ];
            } elseif ( $filter === 'non_decorative_with_alt' ) {
                $args['meta_query'] = [
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
                ];
            }
        }

        $query = new WP_Query( $args );

        // Assign items and pagination.
        $this->items = $query->posts;

        $this->set_pagination_args( [
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => ceil( $query->found_posts / $per_page ),
        ] );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'thumbnail':
                // Fetch the thumbnail and make it a clickable link to the full-size image.
                $full_image_url = wp_get_attachment_url( $item->ID );
                return sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url( $full_image_url ),
                    wp_get_attachment_image( $item->ID, [ 80, 80 ] )
                );
            
            case 'id':
                // Return the ID as a link to the Edit Media screen.
                $edit_link = get_edit_post_link( $item->ID );
                return sprintf(
                    '<a href="%s">%d</a>',
                    esc_url( $edit_link ),
                    $item->ID
                );
            
            case 'alt_text':
                // Fetch the current alt text for the image.
                $alt_text = get_post_meta( $item->ID, '_wp_attachment_image_alt', true );
            
                // Render a text input for inline editing.
                return sprintf(
                    '<input type="text" class="img-a11y-alt-text" data-id="%d" value="%s" placeholder="%s">',
                    esc_attr( $item->ID ),
                    esc_attr( $alt_text ),
                    esc_html__( 'Enter Alt Text', 'img-a11y' )
                );            
            default:
                return esc_html__( 'N/A', 'img-a11y' );
        }
    }

    public function display_rows() {
        foreach ( $this->items as $item ) {
            echo '<tr>';
            foreach ( $this->get_columns() as $column_name => $column_display_name ) {
                echo '<td>';
                echo $this->column_default( $item, $column_name );
                echo '</td>';
            }
            echo '</tr>';
        }
    }
}
