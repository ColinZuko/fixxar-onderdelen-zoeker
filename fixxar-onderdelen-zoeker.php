<?php
/**
 * Plugin Name: Fixxar Onderdelen Zoeker
 * Description: Twee zoeksystemen (inkt en stofzuigeronderdelen) waarmee bezoekers via merk > serie > nummer het juiste onderdeel vinden. Shortcodes: [fixxar_inkt_zoeker] en [fixxar_stofzuiger_zoeker].
 * Version: 2.0.0
 * Author: Fixxar
 * Text Domain: fixxar-zoeker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct toegang niet toegestaan.
}

// Automatische updates via GitHub (Plugin Update Checker).
// Zodra je vanaf hier een nieuwe versie naar GitHub pusht (met een hogere
// "Version:" hierboven + een git tag/release), verschijnt in wp-admin bij
// Plugins automatisch een update-melding, net als bij een plugin uit de
// officiële WordPress-store.
require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$fxr_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/ColinZuko/fixxar-onderdelen-zoeker',
	__FILE__,
	'fixxar-onderdelen-zoeker'
);
$fxr_update_checker->getVcsApi()->enableReleaseAssets();

define( 'FXR_ZOEKER_VERSION', '2.0.0' );
define( 'FXR_ZOEKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'FXR_ZOEKER_URL', plugin_dir_url( __FILE__ ) );

/**
 * De twee taxonomieën, allebei hiërarchisch (zoals Categorieën):
 * Merk (top-niveau) > Serie (kind van Merk) > Model/nummer (kind van Serie).
 *
 * In wp-admin werkt dit hetzelfde als Categorieën aanmaken: eerst "HP" (geen
 * bovenliggende), dan "Deskjet" (bovenliggende: HP), dan "2720" (bovenliggende:
 * Deskjet) — en dat laatste (het specifieke model) vink je aan bij het product.
 */
add_action( 'init', 'fxr_register_taxonomies' );
function fxr_register_taxonomies() {
	$configs = array(
		'fxr_inkt_model'       => array(
			'name'      => 'Inkt: merk / serie / model',
			'menu_name' => 'Inkt-compatibiliteit',
			'slug'      => 'inkt-model',
		),
		'fxr_stofzuiger_model' => array(
			'name'      => 'Stofzuigers: merk / serie / model',
			'menu_name' => 'Stofzuiger-compatibiliteit',
			'slug'      => 'stofzuiger-model',
		),
	);

	foreach ( $configs as $taxonomy => $cfg ) {
		register_taxonomy(
			$taxonomy,
			array( 'product' ),
			array(
				'hierarchical'       => true, // Werkt als Categorieën: Merk > Serie > Model.
				'labels'             => array(
					'name'          => $cfg['name'],
					'singular_name' => 'Model',
					'menu_name'     => $cfg['menu_name'],
					'all_items'     => 'Alle ' . strtolower( $cfg['menu_name'] ),
					'edit_item'     => 'Bewerken',
					'add_new_item'  => 'Nieuwe toevoegen (Merk, Serie of Model)',
					'parent_item'   => 'Bovenliggend (Merk of Serie)',
					'search_items'  => 'Zoeken',
					'not_found'     => 'Niets gevonden',
				),
				'show_ui'           => true,
				// Uit gezet: bij veel gekoppelde modellen maakt deze kolom de
				// productenlijst in wp-admin te lang. Zet op true om 'm terug te zien.
				'show_admin_column' => false,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => $cfg['slug'] ),
				'show_in_rest'      => true,
			)
		);
	}
}

/**
 * Shortcodes: [fixxar_inkt_zoeker] en [fixxar_stofzuiger_zoeker].
 * Allebei tonen ze dezelfde opbouw (Merk-dropdown > Serie-dropdown > nummer-
 * invoerveld), maar praten tegen hun eigen taxonomie zodat een klant die "HP"
 * kiest bij inkt nooit stofzuigerzakken te zien krijgt en andersom.
 */
add_shortcode( 'fixxar_inkt_zoeker', 'fxr_render_inkt_shortcode' );
function fxr_render_inkt_shortcode( $atts ) {
	return fxr_render_zoeker_shortcode( $atts, 'fxr_inkt_model', 'Zoek je inkt' );
}

add_shortcode( 'fixxar_stofzuiger_zoeker', 'fxr_render_stofzuiger_shortcode' );
function fxr_render_stofzuiger_shortcode( $atts ) {
	return fxr_render_zoeker_shortcode( $atts, 'fxr_stofzuiger_model', 'Zoek je stofzuigeronderdeel' );
}

function fxr_render_zoeker_shortcode( $atts, $taxonomy, $default_title ) {
	$atts = shortcode_atts(
		array(
			'title'     => $default_title,
			'min_chars' => 1,
		),
		$atts,
		'fixxar_zoeker'
	);

	wp_enqueue_style( 'fxr-zoeker', FXR_ZOEKER_URL . 'assets/css/fxr-zoeker.css', array(), FXR_ZOEKER_VERSION );
	wp_enqueue_script( 'fxr-zoeker', FXR_ZOEKER_URL . 'assets/js/fxr-zoeker.js', array(), FXR_ZOEKER_VERSION, true );
	wp_localize_script(
		'fxr-zoeker',
		'fxrZoeker',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fxr_zoeker_nonce' ),
		)
	);

	// Merk-dropdown vast server-side vullen (top-niveau termen van deze taxonomie).
	$merken = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $merken ) ) {
		$merken = array();
	}

	ob_start();
	?>
	<div class="fxr-zoeker" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-min-chars="<?php echo esc_attr( $atts['min_chars'] ); ?>">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<!-- Gewone <h2>: krijgt automatisch de kopstijl van je thema. -->
			<h2 class="fxr-zoeker__title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<div class="fxr-zoeker__row">
			<div class="fxr-zoeker__field">
				<label for="fxr-merk-<?php echo esc_attr( $taxonomy ); ?>" class="fxr-zoeker__label">Merk</label>
				<select id="fxr-merk-<?php echo esc_attr( $taxonomy ); ?>" class="fxr-zoeker__select fxr-zoeker__select--merk">
					<option value="">Kies een merk...</option>
					<?php foreach ( $merken as $merk ) : ?>
						<option value="<?php echo esc_attr( $merk->term_id ); ?>"><?php echo esc_html( $merk->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="fxr-zoeker__field">
				<label for="fxr-serie-<?php echo esc_attr( $taxonomy ); ?>" class="fxr-zoeker__label">Serie</label>
				<select id="fxr-serie-<?php echo esc_attr( $taxonomy ); ?>" class="fxr-zoeker__select fxr-zoeker__select--serie" disabled>
					<option value="">Kies eerst een merk...</option>
				</select>
			</div>

			<div class="fxr-zoeker__field">
				<label for="fxr-nummer-<?php echo esc_attr( $taxonomy ); ?>" class="fxr-zoeker__label">Modelnummer</label>
				<input
					type="text"
					id="fxr-nummer-<?php echo esc_attr( $taxonomy ); ?>"
					class="fxr-zoeker__input fxr-zoeker__input--nummer"
					placeholder="Bijv. 2720"
					autocomplete="off"
					disabled
				/>
			</div>
		</div>

		<div class="fxr-zoeker__status" aria-live="polite">
			<!-- Draaiend Fixxar-tandwiel tijdens het zoeken. -->
			<span class="fxr-zoeker__loader" hidden>
				<?php echo file_get_contents( FXR_ZOEKER_PATH . 'assets/img/fxr-loader-icon.svg' ); // phpcs:ignore ?>
			</span>
			<span class="fxr-zoeker__status-text"></span>
		</div>
		<div class="fxr-zoeker__results"></div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX: geef de Serie-opties (kind-termen) terug voor een gekozen Merk.
 */
add_action( 'wp_ajax_fxr_get_series', 'fxr_ajax_get_series' );
add_action( 'wp_ajax_nopriv_fxr_get_series', 'fxr_ajax_get_series' );
function fxr_ajax_get_series() {
	check_ajax_referer( 'fxr_zoeker_nonce', 'nonce' );

	$taxonomy = fxr_valid_taxonomy( isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '' );
	$merk_id  = isset( $_POST['merk_id'] ) ? absint( $_POST['merk_id'] ) : 0;

	if ( ! $taxonomy || ! $merk_id ) {
		wp_send_json_error( array( 'message' => 'Ongeldig verzoek.' ) );
	}

	$series = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => $merk_id,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $series ) ) {
		$series = array();
	}

	$data = array();
	foreach ( $series as $serie ) {
		$data[] = array(
			'id'   => $serie->term_id,
			'name' => $serie->name,
		);
	}

	wp_send_json_success( array( 'series' => $data ) );
}

/**
 * AJAX: zoek producten op basis van gekozen Serie + getypt modelnummer.
 * Zoekt alleen tussen de Model-termen die kind zijn van de gekozen Serie,
 * zodat resultaten altijd binnen het gekozen merk + serie blijven.
 */
add_action( 'wp_ajax_fxr_zoek_onderdelen', 'fxr_ajax_zoek_onderdelen' );
add_action( 'wp_ajax_nopriv_fxr_zoek_onderdelen', 'fxr_ajax_zoek_onderdelen' );
function fxr_ajax_zoek_onderdelen() {
	check_ajax_referer( 'fxr_zoeker_nonce', 'nonce' );

	$taxonomy = fxr_valid_taxonomy( isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '' );
	$serie_id = isset( $_POST['serie_id'] ) ? absint( $_POST['serie_id'] ) : 0;
	$nummer   = isset( $_POST['nummer'] ) ? sanitize_text_field( wp_unslash( $_POST['nummer'] ) ) : '';
	$nummer   = trim( $nummer );

	if ( ! $taxonomy || ! $serie_id || '' === $nummer ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => '',
			)
		);
	}

	// Model-termen (kinderen van de gekozen serie) die het getypte nummer bevatten.
	$model_terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => $serie_id,
			'hide_empty' => false,
			'name__like' => $nummer,
			'fields'     => 'ids',
		)
	);
	if ( is_wp_error( $model_terms ) || empty( $model_terms ) ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => 'Geen onderdelen gevonden voor "' . esc_html( $nummer ) . '".',
			)
		);
	}

	$product_ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $model_terms,
				),
			),
		)
	);

	if ( empty( $product_ids ) ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => 'Geen onderdelen gevonden voor "' . esc_html( $nummer ) . '".',
			)
		);
	}

	$products_data = array();
	foreach ( $product_ids as $pid ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			break;
		}
		$product = wc_get_product( $pid );
		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}
		$products_data[] = array(
			'id'         => $pid,
			'title'      => $product->get_name(),
			'price_html' => $product->get_price_html(),
			'permalink'  => get_permalink( $pid ),
			'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(),
			'in_stock'   => $product->is_in_stock(),
		);
	}

	wp_send_json_success(
		array(
			'products' => $products_data,
			'message'  => empty( $products_data ) ? 'Geen onderdelen gevonden voor "' . esc_html( $nummer ) . '".' : '',
		)
	);
}

/**
 * Helper: alleen deze twee taxonomieën zijn geldig voor de AJAX-endpoints
 * (voorkomt dat iemand een willekeurige taxonomie kan opvragen).
 */
function fxr_valid_taxonomy( $taxonomy ) {
	$allowed = array( 'fxr_inkt_model', 'fxr_stofzuiger_model' );
	return in_array( $taxonomy, $allowed, true ) ? $taxonomy : false;
}
