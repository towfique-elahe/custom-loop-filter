<?php
/*
Plugin Name: Custom Loop Grid with Filter
Description: A custom plugin that adds a loop grid with filtering options using shortcodes.
Version: 1.7
Author: Orbit570
Author URI: https://towfiqueelahe.com/
*/

// Ensure the file is being loaded as a plugin
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Register Shortcodes

// General function to handle filter dropdown
function custom_generate_filter($taxonomies) {
    ob_start();

    // Taxonomy labels in German
    $taxonomy_labels = array(
        'directory_category' => 'Verzeichniskategorien',
        'designation'        => 'Fachrichtungen',
        'location'           => 'Standort',
    );

    echo '<div id="customFilter" class="filter-container">';

    // Search bar
    echo '<div class="search-bar">';
    echo '<input type="text" id="search-title" placeholder="Nach Namen suchen...">';
    echo '</div>';

    echo '<div class="filter-group-container">';

    foreach ($taxonomies as $taxonomy) {

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => false,
        ));

        if (!empty($terms) && !is_wp_error($terms)) {

            // Use German label if defined, fallback otherwise
            $label = isset($taxonomy_labels[$taxonomy])
                ? $taxonomy_labels[$taxonomy]
                : ucfirst(str_replace('_', ' ', $taxonomy));

            echo '<div class="filter-group">';

            echo '<select 
                    id="' . esc_attr($taxonomy) . '-filter"
                    class="taxonomy-filter"
                    data-taxonomy="' . esc_attr($taxonomy) . '">';

            echo '<option value="">Alle ' . esc_html($label) . '</option>';

            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->term_id) . '">'
                        . esc_html($term->name) .
                     '</option>';
            }

            echo '</select>';
            echo '<i class="fa fa-chevron-down" aria-hidden="true"></i>';
            echo '</div>';
        }
    }

    echo '<a class="filter-clear">Filter zurücksetzen</a>';
    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

// 1st Filter Shortcode: All three taxonomies
function custom_filter_all_taxonomies_shortcode($atts) {
    return custom_generate_filter(['directory_category', 'designation', 'location']);
}
add_shortcode('custom_filter_all_taxonomies', 'custom_filter_all_taxonomies_shortcode');

// 2nd Filter Shortcode: directory_category and location
function custom_filter_directory_category_location_shortcode($atts) {
    return custom_generate_filter(['directory_category', 'location']);
}
add_shortcode('custom_filter_directory_category_location', 'custom_filter_directory_category_location_shortcode');

// 3rd Filter Shortcode: location only
function custom_filter_location_shortcode($atts) {
    return custom_generate_filter(['location']);
}
add_shortcode('custom_filter_location', 'custom_filter_location_shortcode');

// Function to handle the grid display
function custom_loop_grid_shortcode($atts) {
    ob_start();

    $atts = shortcode_atts( array(
        'posts_per_page' => 9, // Default number of posts per page
        'paged' => 1, // Default to the first page
    ), $atts, 'custom_loop_grid' );

    // Check if we are on a taxonomy page for 'directory_category'
    if (is_tax('directory_category')) {
        // Get the current taxonomy term object
        $current_term = get_queried_object(); // Get the current term
        $term_id = $current_term->term_id; // Get the term ID
    } else {
        $term_id = ''; // No filtering if not on a taxonomy page
    }

    // Get the current page for pagination
    $paged = get_query_var('paged') ? get_query_var('paged') : 1;

    // WP_Query args to filter posts based on the current 'directory_category'
    $args = array(
        'post_type' => 'directory', // Your custom post type
        'posts_per_page' => $atts['posts_per_page'],
        'paged' => $paged,
    );

    // If we're on a directory_category page, filter posts by the current category
    if ($term_id) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'directory_category',
                'field' => 'term_id',
                'terms' => $term_id,
                'operator' => 'IN',
            ),
        );
    }

    // Query the posts
    $query = new WP_Query($args);

    if ($query->have_posts()) :
        echo '<div id="customLoopGrid" class="custom-loop-grid">';
        while ($query->have_posts()) : $query->the_post();
            // Get post taxonomies (or any taxonomy)
            $directory_category_terms = get_the_terms(get_the_ID(), 'directory_category');
            $location_terms = get_the_terms(get_the_ID(), 'location');
            $designation_terms = get_the_terms(get_the_ID(), 'designation');
            
            // Collect term IDs for each taxonomy
            $directory_category_ids = array_map(function($cat) { return $cat->term_id; }, $directory_category_terms);
            $location_ids = array_map(function($cat) { return $cat->term_id; }, $location_terms);
            $designation_ids = array_map(function($cat) { return $cat->term_id; }, $designation_terms);

            // Convert IDs to strings (for filtering comparison in JS)
            $directory_category_list = implode(',', $directory_category_ids);
            $location_list = implode(',', $location_ids);
            $designation_list = implode(',', $designation_ids);

            // Get featured image or set fallback image
            $featured_image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : plugin_dir_url(__FILE__) . 'assets/media/user-fallback.png';

            ?>
<div class="grid-item" data-directory_category="<?php echo esc_attr($directory_category_list); ?>"
    data-location="<?php echo esc_attr($location_list); ?>"
    data-designation="<?php echo esc_attr($designation_list); ?>">

    <!-- Display Featured Image or Fallback -->
    <div class="grid-item-image">
        <img src="<?php echo esc_url($featured_image); ?>" alt="<?php the_title(); ?>">
    </div>

    <h3><?php the_title(); ?></h3>

    <!-- Display Directory Category, Location, and Designation -->
    <div class="taxonomy-info">
        <span class="taxonomy directory-category">
            <?php 
                        if (!empty($directory_category_terms)) {
                            echo '<span class="icon-container"><i class="fa fa-user-md" aria-hidden="true"></i></span>' . esc_html(implode(', ', wp_list_pluck($directory_category_terms, 'name')));
                        }
                        ?>
        </span>
        <span class="taxonomy designation">
            <?php 
                        if (!empty($designation_terms)) {
                            echo '<span class="icon-container"><i class="fa fa-stethoscope" aria-hidden="true"></i></span>' . esc_html(implode(', ', wp_list_pluck($designation_terms, 'name')));
                        }
                        ?>
        </span>
        <span class="taxonomy location">
            <?php 
                        if (!empty($location_terms)) {
                            echo '<span class="icon-container"><i class="fa fa-map-marker" aria-hidden="true"></i></span>' . esc_html(implode(', ', wp_list_pluck($location_terms, 'name')));
                        }
                        ?>
        </span>
    </div>

    <div class="content"><?php the_excerpt(); ?></div>
</div>
<?php
        endwhile;
        echo '</div>';

        echo '<div id="customNoResults" class="no-posts-found" style="display:none;">';
        echo '<p>Es wurden keine passenden Einträge gefunden.</p>';
        echo '</div>';

        // Pagination
        echo '<div id="customPagination" class="pagination">';
        echo paginate_links(array(
            'total' => $query->max_num_pages,
            'current' => $paged,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ));
        echo '</div>';

        wp_reset_postdata();
    else :
        // Display "No Posts Found" message (visible only when no posts)
        echo '<div class="no-posts-found">';
        echo '<p>Es wurden keine passenden Einträge gefunden.</p>';
        echo '</div>';
    endif;

    return ob_get_clean();
}
add_shortcode('custom_loop_grid', 'custom_loop_grid_shortcode');

// Enqueue Styles and Scripts
function custom_loop_filter_assets() {
    $css_path = plugin_dir_path(__FILE__) . 'assets/css/styles.css';
    $js_path  = plugin_dir_path(__FILE__) . 'assets/js/scripts.js';

    wp_enqueue_style(
        'custom-loop-filter-styles',
        plugin_dir_url(__FILE__) . 'assets/css/styles.css',
        array(),
        filemtime($css_path)
    );

    wp_enqueue_script(
        'custom-loop-filter-scripts',
        plugin_dir_url(__FILE__) . 'assets/js/scripts.js',
        array('jquery'),
        filemtime($js_path),
        true
    );
}
add_action('wp_enqueue_scripts', 'custom_loop_filter_assets');

// Create assets folder with styles and scripts
function custom_loop_filter_plugin_setup() {
    if ( ! file_exists(plugin_dir_path(__FILE__) . 'assets/css') ) {
        mkdir(plugin_dir_path(__FILE__) . 'assets/css', 0755, true);
    }
    if ( ! file_exists(plugin_dir_path(__FILE__) . 'assets/js') ) {
        mkdir(plugin_dir_path(__FILE__) . 'assets/js', 0755, true);
    }
}
add_action('admin_init', 'custom_loop_filter_plugin_setup');

function custom_loop_filter_select2_assets() {

    // Select2 CSS
    wp_enqueue_style(
        'select2-css',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        array(),
        '4.1.0'
    );

    // Select2 JS
    wp_enqueue_script(
        'select2-js',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        array('jquery'),
        '4.1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'custom_loop_filter_select2_assets');