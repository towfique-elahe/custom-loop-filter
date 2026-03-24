<?php
/*
Plugin Name: Custom Loop Grid with Filter
Description: A custom plugin that adds a loop grid with filtering options using shortcodes.
Version: 1.8
Author: Towfique Elahe
Author URI: https://towfiqueelahe.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------
// Filter shortcodes
// -------------------------------------------------------

function custom_generate_filter( $taxonomies ) {
    ob_start();

    $taxonomy_labels = array(
        'directory_category'  => 'Verzeichniskategorien',
        'designation'         => 'Fachrichtungen',
        'location'            => 'Standort',
        'directory_specialty' => 'Kategorien',
    );

    echo '<div id="customFilter" class="filter-container">';

    echo '<div class="search-bar">';
    echo '<input type="text" id="search-title" placeholder="Nach Namen suchen...">';
    echo '</div>';

    echo '<div class="filter-group-container">';

    foreach ( $taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => false,
        ) );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            $label = isset( $taxonomy_labels[ $taxonomy ] )
                ? $taxonomy_labels[ $taxonomy ]
                : ucfirst( str_replace( '_', ' ', $taxonomy ) );

            echo '<div class="filter-group">';

            if ( $taxonomy === 'location' ) {
                echo '<div class="location-search-container">';
                echo '<input type="text" 
                        id="location-search" 
                        class="location-search" 
                        placeholder="' . esc_attr( $label ) . ' suchen..." 
                        autocomplete="off">';
                echo '<i class="fa fa-search" aria-hidden="true"></i>';
                echo '</div>';
            } else {
                echo '<select 
                        id="' . esc_attr( $taxonomy ) . '-filter"
                        class="taxonomy-filter"
                        data-taxonomy="' . esc_attr( $taxonomy ) . '">';
                echo '<option value="">' . esc_html( $label ) . '</option>';
                foreach ( $terms as $term ) {
                    echo '<option value="' . esc_attr( $term->term_id ) . '">'
                        . esc_html( $term->name ) .
                        '</option>';
                }
                echo '</select>';
                echo '<i class="fa fa-chevron-down" aria-hidden="true"></i>';
            }

            echo '</div>';
        }
    }

    echo '<a class="filter-clear">Filter zurücksetzen</a>';
    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

function custom_filter_all_taxonomies_shortcode( $atts ) {
    return custom_generate_filter( [ 'directory_category', 'designation', 'location' ] );
}
add_shortcode( 'custom_filter_all_taxonomies', 'custom_filter_all_taxonomies_shortcode' );

function custom_filter_directory_category_location_shortcode( $atts ) {
    return custom_generate_filter( [ 'directory_category', 'location' ] );
}
add_shortcode( 'custom_filter_directory_category_location', 'custom_filter_directory_category_location_shortcode' );

function custom_filter_location_shortcode( $atts ) {
    return custom_generate_filter( [ 'location' ] );
}
add_shortcode( 'custom_filter_location', 'custom_filter_location_shortcode' );

function custom_filter_directory_specialty_location_shortcode( $atts ) {
    return custom_generate_filter( [ 'directory_specialty', 'location' ] );
}
add_shortcode( 'custom_filter_directory_specialty_location', 'custom_filter_directory_specialty_location_shortcode' );

// -------------------------------------------------------
// Grid shortcode
// -------------------------------------------------------

function custom_loop_grid_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'posts_per_page' => 9,
    ), $atts, 'custom_loop_grid' );

    // Pass config to JS
    wp_localize_script( 'custom-loop-filter-scripts', 'clgConfig', array(
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'clg_filter_nonce' ),
        'postsPerPage' => intval( $atts['posts_per_page'] ),
        'isTaxPage'    => is_tax( 'directory_category' ) ? 1 : 0,
        'termId'       => is_tax( 'directory_category' ) ? get_queried_object()->term_id : 0,
    ) );

    ob_start();

    // Initial load: first page, no filters
    $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

    $args = array(
        'post_type'      => 'directory',
        'posts_per_page' => intval( $atts['posts_per_page'] ),
        'paged'          => $paged,
    );

    if ( is_tax( 'directory_category' ) ) {
        $term_id          = get_queried_object()->term_id;
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'directory_category',
                'field'    => 'term_id',
                'terms'    => $term_id,
                'operator' => 'IN',
            ),
        );
    }

    $query = new WP_Query( $args );

    echo '<div id="customLoopGrid" class="custom-loop-grid">';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            clg_render_grid_item( get_the_ID() );
        }
        wp_reset_postdata();
    }

    echo '</div>';

    echo '<div id="customNoResults" class="no-posts-found" style="display:none;">';
    echo '<p>Es wurden keine passenden Einträge gefunden.</p>';
    echo '</div>';

    echo '<div id="customPagination" class="pagination">';
    if ( $query->have_posts() || $query->max_num_pages > 1 ) {
        echo paginate_links( array(
            'total'     => $query->max_num_pages,
            'current'   => $paged,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ) );
    }
    echo '</div>';

    if ( ! $query->have_posts() && $query->post_count === 0 ) {
        echo '<div class="no-posts-found"><p>Es wurden keine passenden Einträge gefunden.</p></div>';
    }

    return ob_get_clean();
}
add_shortcode( 'custom_loop_grid', 'custom_loop_grid_shortcode' );

// -------------------------------------------------------
// Shared helper: render a single grid item
// -------------------------------------------------------

function clg_render_grid_item( $post_id ) {
    $directory_category_terms = get_the_terms( $post_id, 'directory_category' );
    $location_terms           = get_the_terms( $post_id, 'location' );
    $designation_terms        = get_the_terms( $post_id, 'designation' );
    $specialty_terms          = get_the_terms( $post_id, 'directory_specialty' );

    $directory_category_ids = ( ! empty( $directory_category_terms ) && ! is_wp_error( $directory_category_terms ) )
        ? array_map( fn( $t ) => $t->term_id, $directory_category_terms ) : [];
    $location_ids           = ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) )
        ? array_map( fn( $t ) => $t->term_id, $location_terms ) : [];
    $designation_ids        = ( ! empty( $designation_terms ) && ! is_wp_error( $designation_terms ) )
        ? array_map( fn( $t ) => $t->term_id, $designation_terms ) : [];
    $specialty_ids          = ( ! empty( $specialty_terms ) && ! is_wp_error( $specialty_terms ) )
        ? array_map( fn( $t ) => $t->term_id, $specialty_terms ) : [];

    $featured_image = has_post_thumbnail( $post_id )
        ? get_the_post_thumbnail_url( $post_id, 'full' )
        : plugin_dir_url( __FILE__ ) . 'assets/media/user-fallback.png';

    ?>
    <div class="grid-item"
         data-directory_category="<?php echo esc_attr( implode( ',', $directory_category_ids ) ); ?>"
         data-location="<?php echo esc_attr( implode( ',', $location_ids ) ); ?>"
         data-designation="<?php echo esc_attr( implode( ',', $designation_ids ) ); ?>"
         data-directory_specialty="<?php echo esc_attr( implode( ',', $specialty_ids ) ); ?>">

        <div class="grid-item-image">
            <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
        </div>

        <h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>

        <div class="taxonomy-info">
            <span class="taxonomy directory-category">
                <?php if ( ! empty( $specialty_terms ) && ! is_wp_error( $specialty_terms ) ) : ?>
                    <span class="icon-container"><i class="fa fa-user-md" aria-hidden="true"></i></span>
                    <?php echo esc_html( implode( ', ', wp_list_pluck( $specialty_terms, 'name' ) ) ); ?>
                <?php endif; ?>
            </span>
            <span class="taxonomy designation">
                <?php if ( ! empty( $designation_terms ) && ! is_wp_error( $designation_terms ) ) : ?>
                    <span class="icon-container"><i class="fa fa-stethoscope" aria-hidden="true"></i></span>
                    <?php echo esc_html( implode( ', ', wp_list_pluck( $designation_terms, 'name' ) ) ); ?>
                <?php endif; ?>
            </span>
            <span class="taxonomy location">
                <?php if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) : ?>
                    <span class="icon-container"><i class="fa fa-map-marker" aria-hidden="true"></i></span>
                    <?php echo esc_html( implode( ', ', wp_list_pluck( $location_terms, 'name' ) ) ); ?>
                <?php endif; ?>
            </span>
        </div>

        <div class="content"><?php echo apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ); ?></div>
    </div>
    <?php
}

// -------------------------------------------------------
// AJAX handler — runs server-side query across ALL pages
// -------------------------------------------------------

function clg_ajax_filter() {
    check_ajax_referer( 'clg_filter_nonce', 'nonce' );

    $posts_per_page       = isset( $_POST['posts_per_page'] ) ? intval( $_POST['posts_per_page'] ) : 9;
    $paged                = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
    $title_search         = isset( $_POST['title_search'] ) ? sanitize_text_field( $_POST['title_search'] ) : '';
    $location_search      = isset( $_POST['location_search'] ) ? sanitize_text_field( $_POST['location_search'] ) : '';
    $taxonomy_filters     = isset( $_POST['taxonomy_filters'] ) && is_array( $_POST['taxonomy_filters'] )
        ? $_POST['taxonomy_filters'] : [];
    $locked_term_id       = isset( $_POST['locked_term_id'] ) ? intval( $_POST['locked_term_id'] ) : 0;

    $args = array(
        'post_type'      => 'directory',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    );

    // Title search
    if ( $title_search !== '' ) {
        $args['s'] = $title_search;
    }

    $tax_query = array( 'relation' => 'AND' );

    // Locked taxonomy page term
    if ( $locked_term_id ) {
        $tax_query[] = array(
            'taxonomy' => 'directory_category',
            'field'    => 'term_id',
            'terms'    => $locked_term_id,
            'operator' => 'IN',
        );
    }

    // Dropdown taxonomy filters
    foreach ( $taxonomy_filters as $taxonomy => $term_id ) {
        $taxonomy = sanitize_key( $taxonomy );
        $term_id  = intval( $term_id );
        if ( $term_id && taxonomy_exists( $taxonomy ) ) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_id,
                'operator' => 'IN',
            );
        }
    }

    // Location text search — search across all location terms
    if ( $location_search !== '' ) {
        $location_terms = get_terms( array(
            'taxonomy'   => 'location',
            'search'     => $location_search,
            'hide_empty' => false,
        ) );

        if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) {
            $location_ids = wp_list_pluck( $location_terms, 'term_id' );
            $tax_query[]  = array(
                'taxonomy' => 'location',
                'field'    => 'term_id',
                'terms'    => $location_ids,
                'operator' => 'IN',
            );
        } else {
            // No matching location terms → force zero results
            wp_send_json_success( array(
                'html'          => '',
                'found_posts'   => 0,
                'max_num_pages' => 0,
                'paged'         => $paged,
                'pagination'    => '',
            ) );
        }
    }

    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query( $args );

    ob_start();
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            clg_render_grid_item( get_the_ID() );
        }
        wp_reset_postdata();
    }
    $html = ob_get_clean();

    // Build pagination HTML
    $pagination = paginate_links( array(
        'total'     => $query->max_num_pages,
        'current'   => $paged,
        'type'      => 'plain',
        'prev_text' => '&laquo; Previous',
        'next_text' => 'Next &raquo;',
    ) );

    wp_send_json_success( array(
        'html'          => $html,
        'found_posts'   => $query->found_posts,
        'max_num_pages' => $query->max_num_pages,
        'paged'         => $paged,
        'pagination'    => $pagination ? $pagination : '',
    ) );
}
add_action( 'wp_ajax_clg_filter', 'clg_ajax_filter' );
add_action( 'wp_ajax_nopriv_clg_filter', 'clg_ajax_filter' );

// -------------------------------------------------------
// Assets
// -------------------------------------------------------

function custom_loop_filter_assets() {
    $css_path = plugin_dir_path( __FILE__ ) . 'assets/css/styles.css';
    $js_path  = plugin_dir_path( __FILE__ ) . 'assets/js/scripts.js';

    wp_enqueue_style(
        'custom-loop-filter-styles',
        plugin_dir_url( __FILE__ ) . 'assets/css/styles.css',
        array(),
        filemtime( $css_path )
    );

    wp_enqueue_script(
        'custom-loop-filter-scripts',
        plugin_dir_url( __FILE__ ) . 'assets/js/scripts.js',
        array( 'jquery' ),
        filemtime( $js_path ),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'custom_loop_filter_assets' );

function custom_loop_filter_select2_assets() {
    wp_enqueue_style(
        'select2-css',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        array(),
        '4.1.0'
    );
    wp_enqueue_script(
        'select2-js',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        array( 'jquery' ),
        '4.1.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'custom_loop_filter_select2_assets' );

function custom_loop_filter_plugin_setup() {
    if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'assets/css' ) ) {
        mkdir( plugin_dir_path( __FILE__ ) . 'assets/css', 0755, true );
    }
    if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'assets/js' ) ) {
        mkdir( plugin_dir_path( __FILE__ ) . 'assets/js', 0755, true );
    }
}
add_action( 'admin_init', 'custom_loop_filter_plugin_setup' );
