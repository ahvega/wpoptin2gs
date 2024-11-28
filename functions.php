<?php
// Ensure this file is being included by a parent file
if (!defined('ABSPATH')) exit;

// Custom error logging function
function custom_error_log($message) {
	error_log(date('[Y-m-d H:i:s] ') . "Custom Log: " . $message . "\n", 3, WP_CONTENT_DIR . '/debug.log');
}

// Start session as early as possible
function start_session_early() {
	if (!session_id() && !headers_sent()) {
		session_start();
		custom_error_log("Session started successfully");
	} else {
		custom_error_log("Failed to start session. Headers already sent: " . (headers_sent() ? 'Yes' : 'No'));
	}
}
add_action('init', 'start_session_early', 1);

// Function to set the 'producto_comprado' session variable
function set_producto_comprado_session() {
	custom_error_log("Entering set_producto_comprado_session function");

	// Check if it's a single post of any type
	if (!is_single()) {
		custom_error_log("Not a single post page. Exiting function.");
		return;
	}

	// Get the current post type
	$post_type = get_post_type();
	custom_error_log("Current post type: " . $post_type);

	// Check if it's your customized portfolio post type
	if ($post_type !== 'avada_portfolio' && $post_type !== 'portfolio') {
		custom_error_log("Not a portfolio post type. Exiting function.");
		return;
	}

	// Initialize the session variable
	$_SESSION['producto_comprado'] = false;
	custom_error_log("Session variable 'producto_comprado' initialized to false");

	// Get the current user ID
	$user_id = get_current_user_id();
	custom_error_log("Current user ID: " . $user_id);
	if (!$user_id) {
		custom_error_log("No logged-in user. Exiting function.");
		return;
	}

	// Get the current post ID
	$post_id = get_the_ID();
	custom_error_log("Current post ID: " . $post_id);
	if (!$post_id) {
		custom_error_log("Unable to get current post ID. Exiting function.");
		return;
	}

	// Check if ACF function exists
	if (!function_exists('get_field')) {
		custom_error_log("ACF get_field() function does not exist. Exiting function.");
		return;
	}

	// Get the product ID from ACF field
	$product_id = get_field('producto_id', $post_id);
	custom_error_log("Product ID from ACF: " . $product_id);
	if (!$product_id) {
		custom_error_log("No associated Product ID found in ACF for post " . $post_id . ". Exiting function.");
		return;
	}

	// Check if WooCommerce function exists
	if (!function_exists('wc_customer_bought_product')) {
		custom_error_log("WooCommerce wc_customer_bought_product() function does not exist. Exiting function.");
		return;
	}

	// Check if the user has purchased the product
	$user = wp_get_current_user();
	$user_email = $user->user_email;
	custom_error_log("Checking purchase for User Email: " . $user_email . ", User ID: " . $user_id . ", Product ID: " . $product_id);
	$has_bought = wc_customer_bought_product($user_email, $user_id, $product_id);
	custom_error_log("Has user bought product? " . ($has_bought ? 'Yes' : 'No'));

	if ($has_bought) {
		$_SESSION['producto_comprado'] = true;
		custom_error_log("User has bought the product. Session variable set to true.");
	} else {
		custom_error_log("User has not bought the product. Session variable remains false.");
	}

	custom_error_log("Final value of session variable 'producto_comprado': " . var_export($_SESSION['producto_comprado'], true));
	custom_error_log("Raw result of wc_customer_bought_product: " . var_export($has_bought, true));
}
add_action('wp', 'set_producto_comprado_session');

// Function to check if content should be unblocked
function is_content_unblocked() {
	$is_unblocked = isset($_SESSION['producto_comprado']) && $_SESSION['producto_comprado'] === true;
	custom_error_log("is_content_unblocked called. Result: " . ($is_unblocked ? 'true' : 'false'));
	return $is_unblocked;
}

// Function to display content based on purchase status
function display_content_based_on_purchase($atts, $content = null) {
	custom_error_log("display_content_based_on_purchase shortcode called");
	if (is_content_unblocked()) {
		custom_error_log("Content is unblocked. Displaying protected content.");
		return do_shortcode($content);
	} else {
		custom_error_log("Content is blocked. Displaying message for non-purchasers.");
		return '<p>Este contenido solo está disponible para los clientes que han comprado el producto asociado.</p>';
	}
}
add_shortcode('protected_content', 'display_content_based_on_purchase');

// Keep existing code (if any) below this line

add_filter( 'big_image_size_threshold', '__return_false' );

add_filter( 'wp_lazy_loading_enabled', '__return_false' );

// //remove emoji support
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// // Remove rss feed links
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'feed_links', 2 );

// // remove wp-embed
add_action( 'wp_footer', function () {
	wp_dequeue_script( 'wp-embed' );
} );

add_action( 'wp_enqueue_scripts', function () {
	// // remove block library css
	wp_dequeue_style( 'wp-block-library' );
	// // remove comment reply JS
	wp_dequeue_script( 'comment-reply' );
} );

// Project Description.
/* *** *** *** *** *** *** *** *** ***
add_filter('fusion_portfolio_post_project_description_label', 'fusion_portfolio_post_project_description_label_change', 99, 4);
function fusion_portfolio_post_project_description_label_change($project_desc_html, $project_desc_title, $project_desc_title_style, $project_desc_tag)
{
    return '<' . $project_desc_tag . ' style="' . esc_attr__($project_desc_title_style) . '">' . esc_html__('Descripción', 'fusion-core') . '</' . $project_desc_tag . '>';
}

// Project Details.
add_filter('fusion_portfolio_post_project_details_label', 'fusion_portfolio_post_project_details_label_change', 99, 3);
function fusion_portfolio_post_project_details_label_change($project_details_html, $project_details_title, $project_details_tag)
{
    return '<' . $project_details_tag . '>' . esc_html__('Detalles', 'fusion-core') . '</' . $project_details_tag . '>';
}

// Skills Needed.
add_filter('fusion_portfolio_post_skills_label', 'fusion_portfolio_post_project_skills_label_change', 99, 3);
function fusion_portfolio_post_project_skills_label_change($project_skills_html, $project_skills_title, $project_skills_tag)
{
    return '<' . $project_skills_tag . '>' . esc_html__('Etapa Pedagógica', 'fusion-core') . '</' . $project_skills_tag . '>';
}

// Categories.
add_filter('fusion_portfolio_post_categories_label', 'fusion_portfolio_post_project_categories_label_change', 99, 3);
function fusion_portfolio_post_project_categories_label_change($project_categories_html, $project_categories_title, $project_categories_tag)
{
    return '<' . $project_categories_tag . '>' . esc_html__('Tema Ecológico', 'fusion-core') . '</' . $project_categories_tag . '>';
}

// Tags.
add_filter('fusion_portfolio_post_tags_label', 'fusion_portfolio_post_project_tags_label_change', 99, 3);
function fusion_portfolio_post_project_tags_label_change($project_tags_html, $project_tags_title, $project_tags_tag)
{
    return '<' . $project_tags_tag . '>' . esc_html__('Palabras clave', 'fusion-core') . '</' . $project_tags_tag . '>';
}


function my_portfolio_title($label, $post_type)
{
    if ('avada_portfolio' === $post_type) {
        return 'Actividades';
    }
    return $label;
}

add_filter('post_type_archive_title', 'my_portfolio_title', 2, 10);

*** *** *** *** *** */

/**
 * Redefine el tipo de entrada "portfolio" y sus taxonomías asociadas.
 *
 * El tipo de entrada "portfolio" se redefine como "actividad" con las taxonomías personalizadas
 * "tema," "etapa," y "palabras_clave" y los campos "title," "editor," "author," "thumbnail,"
 * "excerpt," y "comments". Las taxonomías "portfolio_category," "portfolio_skills," y
 * "portfolio_tags" se redefinen como "tema," "etapa," y "palabras_clave" y se asocian con el
 * tipo de entrada "portfolio".
 *
 *
 * function redefine_portfolio_post_type_and_taxonomies() {
 * register_post_type( 'portfolio',
 * array(
 * 'labels' => array(
 * 'name' => __( 'Actividades' ),
 * 'singular_name' => __( 'Actividad' )
 * ),
 * 'public' => true,
 * 'has_archive' => true,
 * 'rewrite' => array('slug' => 'actividades'),
 * 'taxonomies' => array('tema', 'etapa', 'palabras_clave'),
 * 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
 * )
 * );
 *
 * register_taxonomy( 'portfolio_category', 'portfolio',
 * array(
 * 'labels' => array(
 * 'name' => __( 'Temas' ),
 * 'singular_name' => __( 'Tema' )
 * ),
 * 'rewrite' => array( 'slug' => 'temas' )
 * )
 * );
 *
 * register_taxonomy( 'portfolio_skills', 'portfolio',
 * array(
 * 'labels' => array(
 * 'name' => __( 'Etapas' ),
 * 'singular_name' => __( 'Etapa' )
 * ),
 * 'rewrite' => array( 'slug' => 'etapas' )
 * )
 * );
 *
 * register_taxonomy( 'portfolio_tags', 'portfolio',
 * array(
 * 'labels' => array(
 * 'name' => __( 'Palabras clave' ),
 * 'singular_name' => __( 'Palabra clave' )
 * ),
 * 'rewrite' => array( 'slug' => 'palabras-clave' )
 * )
 * );
 * }
 * add_action( 'init', 'redefine_portfolio_post_type_and_taxonomies', 11 );
 * *** *** *** */

/*
 * Código para desbloquear contenido para compradores de Temas individuales */

/* Omit closing PHP tag to avoid "Headers already sent" issues. */
