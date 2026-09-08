<?php
/**
 * Plugin Name: Fixxar Onderdelen Zoeker
 * Description: Zoekfunctie waarmee bezoekers stofzuigerzakken en inkt kunnen vinden op basis van het modelnummer van hun apparaat. Gebruik shortcode [fixxar_onderdelen_zoeker].
 * Version: 1.0.0
 * Author: Fixxar
 * Text Domain: fixxar-zoeker
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct toegang niet toegestaan.
}

// 1. Laad de update-checker library
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// 2. Koppel aan je GitHub repository
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/ColinZuko/fixxar-onderdelen-zoeker.git', // URL naar je GitHub repo
    __FILE__,
    'fixxar-onderdelen-zoeker' // De slug van je plugin (mapnaam)
);

// 3. Zorg dat updates via officiële GitHub "Releases" worden opgehaald
$myUpdateChecker->getVcsApi()->enableReleaseAssets();


define( 'FXR_ZOEKER_VERSION', '1.0.0' );
define( 'FXR_ZOEKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'FXR_ZOEKER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Stap 1: Custom taxonomy "Compatibele modellen" op producten.
 * Hiermee koppel je aan elk product (stofzuigerzak, inkt) een of meerdere
 * modelnummers van apparaten waar het bij past. Werkt als een "tags"-veld
 * op het productbewerkscherm: snel meerdere modellen intypen per product.
 */
add_action( 'init', 'fxr_register_model_taxonomy' );
function fxr_register_model_taxonomy() {
	$labels = array(
		'name'                       => 'Compatibele modellen',
		'singular_name'              => 'Compatibel model',
		'search_items'               => 'Zoek modellen',
		'popular_items'              => 'Populaire modellen',
		'all_items'                  => 'Alle modellen',
		'edit_item'                  => 'Model bewerken',
		'update_item'                => 'Model bijwerken',
		'add_new_item'               => 'Nieuw model toevoegen',
		'new_item_name'              => 'Naam nieuw model (bv. Dyson V8)',
		'separate_items_with_commas' => "Scheid modellen met komma's",
		'add_or_remove_items'        => 'Modellen toevoegen of verwijderen',
		'choose_from_most_used'      => 'Kies uit meest gebruikte modellen',
		'menu_name'                  => 'Compatibele modellen',
		'not_found'                  => 'Geen modellen gevonden',
	);

	register_taxonomy(
		'compatibel_model',
		array( 'product' ),
		array(
			'hierarchical'      => false, // Tag-stijl: snel meerdere modelnummers per product invoeren.
			'labels'            => $labels,
			'show_ui'           => true,
			// Uit gezet: bij veel gekoppelde modellen maakt deze kolom de
			// productenlijst in wp-admin te lang/onoverzichtelijk. Zet op
			// true als je de kolom toch weer wilt zien.
			'show_admin_column' => false,
			'show_in_quick_edit' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'compatibel-model' ),
			'show_in_rest'      => true,
		)
	);
}

/**
 * Stap 2: Shortcode [fixxar_onderdelen_zoeker] die het zoekveld + resultaten toont.
 * Plaats deze shortcode op een pagina (ook via UX Builder in Flatsome: voeg een
 * "Text/HTML" of shortcode-element toe en plak [fixxar_onderdelen_zoeker]).
 */
add_shortcode( 'fixxar_onderdelen_zoeker', 'fxr_render_zoeker_shortcode' );
function fxr_render_zoeker_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'title'       => 'Zoek je onderdeel',
			'placeholder' => 'Typ het modelnummer van je apparaat...',
			'min_chars'   => 2,
		),
		$atts,
		'fixxar_onderdelen_zoeker'
	);

	wp_enqueue_style( 'fxr-zoeker', FXR_ZOEKER_URL . 'assets/css/fxr-zoeker.css', array(), FXR_ZOEKER_VERSION );
	wp_enqueue_script( 'fxr-zoeker', FXR_ZOEKER_URL . 'assets/js/fxr-zoeker.js', array(), FXR_ZOEKER_VERSION, true );
	wp_localize_script(
		'fxr-zoeker',
		'fxrZoeker',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fxr_zoeker_nonce' ),
			'minChars' => (int) $atts['min_chars'],
		)
	);

	ob_start();
	?>
	<div class="fxr-zoeker" data-min-chars="<?php echo esc_attr( $atts['min_chars'] ); ?>">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<!-- Gewone <h2>: krijgt automatisch de kopstijl van je thema, geen
			     eigen lettergrootte/kleur nodig in fxr-zoeker.css. Wil je geen
			     titel, geef dan title="" mee aan de shortcode. -->
				 <label class="fxr-zoeker__eyebrow">PRODUCTZOEKER</label>
			<h2 class="fxr-zoeker__title">Inktcartidge of stofzuigeronderdeel zoeken</h2>
		<?php endif; ?>
		<div class="fxr-zoeker__form">
			<p class="fxr-zoeker__description">Vind gemakkelijk het juist stofzuigeronderdeel of de juiste inktcartridge voor jouw apparaat</p>
			<input
				type="text"
				id="fxr-zoeker-input"
				class="fxr-zoeker__input"
				placeholder="Vul hier het typenummer van je apparaat in..."
				autocomplete="off"
			/>
		</div>
		<div class="fxr-zoeker__status" aria-live="polite">
			<!-- Loader-animatie tijdens het zoeken, i.p.v. platte "Zoeken..."-tekst.
			     Ontwerp: https://uiverse.io/krlozCJ/horrible-fish-14 (MIT-licentie). -->
			<span class="fxr-zoeker__loader" hidden>
				<span class="fxr-zoeker__orbe" style="--index:0"></span>
				<span class="fxr-zoeker__orbe" style="--index:1"></span>
				<span class="fxr-zoeker__orbe" style="--index:2"></span>
				<span class="fxr-zoeker__orbe" style="--index:3"></span>
				<span class="fxr-zoeker__orbe" style="--index:4"></span>
			</span>
			<span class="fxr-zoeker__status-text"></span>
		</div>
		<div class="fxr-zoeker__results"></div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Stap 3: AJAX-handler die producten opzoekt op basis van het getypte modelnummer.
 * Zoekt in:
 *  - de taxonomie "compatibel_model" (het modelnummer dat jij aan een product hangt)
 *  - de SKU van het product (voor het geval het modelnummer ook als SKU is ingevoerd)
 * Werkt voor ingelogde bezoekers EN gewone websitebezoekers (nopriv).
 */
add_action( 'wp_ajax_fxr_zoek_onderdelen', 'fxr_ajax_zoek_onderdelen' );
add_action( 'wp_ajax_nopriv_fxr_zoek_onderdelen', 'fxr_ajax_zoek_onderdelen' );
function fxr_ajax_zoek_onderdelen() {
	check_ajax_referer( 'fxr_zoeker_nonce', 'nonce' );

	$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
	$term = trim( $term );

	if ( mb_strlen( $term ) < 2 ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => '',
			)
		);
	}

	global $wpdb;

	// 1) Matchende modelnummers (taxonomie-termen) zoeken op naam.
	$matching_terms = get_terms(
		array(
			'taxonomy'   => 'compatibel_model',
			'hide_empty' => false,
			'name__like' => $term,
			'fields'     => 'ids',
		)
	);
	if ( is_wp_error( $matching_terms ) ) {
		$matching_terms = array();
	}

	$product_ids = array();

	if ( ! empty( $matching_terms ) ) {
		$tax_product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'compatibel_model',
						'field'    => 'term_id',
						'terms'    => $matching_terms,
					),
				),
			)
		);
		$product_ids      = array_merge( $product_ids, $tax_product_ids );
	}

	// 2) Ook zoeken op SKU (voor het geval het modelnummer als SKU staat ingevoerd).
	$sku_matches = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s LIMIT 50",
			'%' . $wpdb->esc_like( $term ) . '%'
		)
	);
	$product_ids = array_merge( $product_ids, $sku_matches );
	$product_ids = array_unique( array_map( 'intval', $product_ids ) );

	if ( empty( $product_ids ) ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => 'Geen onderdelen gevonden voor "' . esc_html( $term ) . '".',
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
			'message'  => empty( $products_data ) ? 'Geen onderdelen gevonden voor "' . esc_html( $term ) . '".' : '',
		)
	);
}
