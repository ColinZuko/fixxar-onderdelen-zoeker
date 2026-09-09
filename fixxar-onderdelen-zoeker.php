<?php
/**
 * Plugin Name: Fixxar Onderdelen Zoeker
 * Description: Twee zoeksystemen (inkt en stofzuigeronderdelen) waarmee bezoekers het juiste onderdeel vinden. Beide via één zoekveld met autocomplete over alle Merk/Serie/Model-combinaties, dat pas resultaten toont zodra een voorstel is gekozen. Shortcodes: [fixxar_inkt_zoeker] en [fixxar_stofzuiger_zoeker].
 * Version: 2.2.1
 * Author: Fixxar
 * Text Domain: fixxar-zoeker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct toegang niet toegestaan.
}

define( 'FXR_ZOEKER_VERSION', '2.2.1' );
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
 *
 * Beide staan standaard op mode "autocomplete": één zoekveld dat meezoekt op
 * de volledige combinatie Merk + Serie + Model (over alle merken heen), en
 * pas resultaten toont zodra de klant een voorstel uit de lijst kiest — nooit
 * op basis van vrij getypte tekst. Er is ook nog een oudere mode "dropdown"
 * (Merk-dropdown -> Serie-dropdown -> modelnummer-invoerveld) die per
 * shortcode terug te zetten is met een attribuut, mocht dat ooit gewenst
 * zijn: [fixxar_inkt_zoeker mode="dropdown"].
 */
add_shortcode( 'fixxar_inkt_zoeker', 'fxr_render_inkt_shortcode' );
function fxr_render_inkt_shortcode( $atts ) {
	return fxr_render_zoeker_shortcode( $atts, 'fxr_inkt_model', 'Zoek je inkt', 'autocomplete', 'Bijv. HP, Deskjet, 2720...' );
}

add_shortcode( 'fixxar_stofzuiger_zoeker', 'fxr_render_stofzuiger_shortcode' );
function fxr_render_stofzuiger_shortcode( $atts ) {
	return fxr_render_zoeker_shortcode( $atts, 'fxr_stofzuiger_model', 'Zoek je stofzuigeronderdeel', 'autocomplete', 'Bijv. Miele, S241i, GD1000...' );
}

function fxr_render_zoeker_shortcode( $atts, $taxonomy, $default_title, $default_mode, $default_placeholder = '' ) {
	$atts = shortcode_atts(
		array(
			'title'       => $default_title,
			'mode'        => $default_mode, // "dropdown" of "autocomplete".
			'min_chars'   => 'autocomplete' === $default_mode ? 2 : 1,
			'placeholder' => $default_placeholder, // Alleen gebruikt in mode "autocomplete".
		),
		$atts,
		'fixxar_zoeker'
	);

	$mode = in_array( $atts['mode'], array( 'dropdown', 'autocomplete' ), true ) ? $atts['mode'] : $default_mode;

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

	// Unieke id-suffix zodat twee zoekers (bv. inkt + stofzuiger) op dezelfde
	// pagina elkaars id's/labels niet overschrijven.
	static $instance = 0;
	++$instance;
	$uid = $taxonomy . '-' . $instance;

	ob_start();

	if ( 'autocomplete' === $mode ) {
		fxr_render_autocomplete_markup( $atts, $taxonomy, $uid, $mode );
	} else {
		fxr_render_dropdown_markup( $atts, $taxonomy, $uid, $mode );
	}

	return ob_get_clean();
}

/**
 * Markup voor mode "dropdown": Merk-dropdown > Serie-dropdown > nummerveld.
 */
function fxr_render_dropdown_markup( $atts, $taxonomy, $uid, $mode ) {
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
	?>
	<div class="fxr-zoeker fxr-zoeker--<?php echo esc_attr( fxr_taxonomy_css_slug( $taxonomy ) ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>" data-min-chars="<?php echo esc_attr( $atts['min_chars'] ); ?>">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<!-- Gewone <h2>: krijgt automatisch de kopstijl van je thema. -->
			<h2 class="fxr-zoeker__title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<div class="fxr-zoeker__row">
			<div class="fxr-zoeker__field">
				<label for="fxr-merk-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__label">Merk</label>
				<select id="fxr-merk-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__select fxr-zoeker__select--merk">
					<option value="">Kies een merk...</option>
					<?php foreach ( $merken as $merk ) : ?>
						<option value="<?php echo esc_attr( $merk->term_id ); ?>"><?php echo esc_html( $merk->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="fxr-zoeker__field">
				<label for="fxr-serie-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__label">Serie</label>
				<select id="fxr-serie-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__select fxr-zoeker__select--serie" disabled>
					<option value="">Kies eerst een merk...</option>
				</select>
			</div>

			<div class="fxr-zoeker__field">
				<label for="fxr-nummer-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__label">Modelnummer</label>
				<input
					type="text"
					id="fxr-nummer-<?php echo esc_attr( $uid ); ?>"
					class="fxr-zoeker__input fxr-zoeker__input--nummer"
					placeholder="Bijv. 2720"
					autocomplete="off"
					disabled
				/>
			</div>
		</div>

		<?php fxr_render_status_en_results(); ?>
	</div>
	<?php
}

/**
 * Markup voor mode "autocomplete": één zoekveld + voorstellenlijst (combobox).
 * Toont pas resultaten zodra een voorstel is gekozen, niet bij vrij typen.
 */
function fxr_render_autocomplete_markup( $atts, $taxonomy, $uid, $mode ) {
	?>
	<div class="fxr-zoeker fxr-zoeker--<?php echo esc_attr( fxr_taxonomy_css_slug( $taxonomy ) ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>" data-min-chars="<?php echo esc_attr( $atts['min_chars'] ); ?>">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h2 class="fxr-zoeker__title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<div class="fxr-zoeker__field fxr-zoeker__field--autocomplete">
			<label for="fxr-model-<?php echo esc_attr( $uid ); ?>" class="fxr-zoeker__label">Merk, serie of modelnummer</label>
			<!-- Combobox-patroon: tekstveld + lijst met voorstellen eronder.
			     role/aria-* zorgen dat schermlezers en toetsenbordgebruikers
			     hetzelfde kunnen als iemand met een muis. -->
			<div class="fxr-combobox">
				<input
					type="text"
					id="fxr-model-<?php echo esc_attr( $uid ); ?>"
					class="fxr-zoeker__input fxr-zoeker__input--model"
					placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
					autocomplete="off"
					role="combobox"
					aria-expanded="false"
					aria-autocomplete="list"
					aria-controls="fxr-listbox-<?php echo esc_attr( $uid ); ?>"
				/>
				<!-- Bewaart het gekozen term_id; leeg = nog niets (geldigs) gekozen. -->
				<input type="hidden" class="fxr-zoeker__model-id" value="" />
				<ul
					class="fxr-combobox__listbox"
					id="fxr-listbox-<?php echo esc_attr( $uid ); ?>"
					role="listbox"
					hidden
				></ul>
			</div>
		</div>

		<?php fxr_render_status_en_results(); ?>
	</div>
	<?php
}

/**
 * Statusregel (loader/melding) + resultatengrid — hetzelfde voor beide modes.
 */
function fxr_render_status_en_results() {
	?>
	<div class="fxr-zoeker__status" aria-live="polite">
		<!-- Draaiend Fixxar-tandwiel tijdens het zoeken. -->
		<span class="fxr-zoeker__loader" hidden>
			<?php echo file_get_contents( FXR_ZOEKER_PATH . 'assets/img/fxr-loader-icon.svg' ); // phpcs:ignore ?>
		</span>
		<span class="fxr-zoeker__status-text"></span>
	</div>
	<div class="fxr-zoeker__results"></div>
	<?php
}

/**
 * AJAX: geef de Serie-opties (kind-termen) terug voor een gekozen Merk.
 * Alleen gebruikt door mode "dropdown".
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
 * Alleen gebruikt door mode "dropdown".
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

	$products_data = fxr_get_products_data_by_terms( $taxonomy, $model_terms );

	wp_send_json_success(
		array(
			'products' => $products_data,
			'message'  => empty( $products_data ) ? 'Geen onderdelen gevonden voor "' . esc_html( $nummer ) . '".' : '',
		)
	);
}

/**
 * AJAX: zoek Model-termen (het onderste niveau, dus nooit een Merk of Serie
 * zelf) over alle merken/series heen. Voedt de voorstellenlijst van mode
 * "autocomplete". Geeft ook de Merk/Serie mee zodat de klant per voorstel
 * kan zien bij welk toestel het hoort.
 *
 * Er wordt gezocht op de VOLLEDIGE combinatie "Merk Serie Model" (niet
 * alleen de modelnaam zelf), en elk getypt woord mag ergens in die combinatie
 * voorkomen, in willekeurige volgorde. Zo vindt een klant een model door
 * te typen op het merk ("Miele"), op merk + serie ("Miele Complete C2"), of
 * gewoon op het modelnummer zelf ("Complete C2") — wat het meest natuurlijk
 * voelt hangt af van wat de klant toevallig van zijn toestel weet.
 */
add_action( 'wp_ajax_fxr_search_models', 'fxr_ajax_search_models' );
add_action( 'wp_ajax_nopriv_fxr_search_models', 'fxr_ajax_search_models' );
function fxr_ajax_search_models() {
	check_ajax_referer( 'fxr_zoeker_nonce', 'nonce' );

	$taxonomy = fxr_valid_taxonomy( isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '' );
	$zoekterm = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$zoekterm = trim( $zoekterm );

	if ( ! $taxonomy || '' === $zoekterm ) {
		wp_send_json_success( array( 'models' => array() ) );
	}

	// De hele taxonomie in één keer ophalen (voor deze twee taxonomieën gaat
	// het om enkele honderden tot ~1000 termen) is sneller en simpeler dan
	// per term losse queries doen om Serie/Merk op te zoeken, en het is de
	// enige manier om op de VOLLEDIGE Merk+Serie+Model-combinatie te zoeken
	// in plaats van alleen op de losse modelnaam.
	$alle_termen = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $alle_termen ) ) {
		$alle_termen = array();
	}

	// Opzoektabellen in het geheugen opbouwen: term_id -> term-object, en
	// welke term_id's ergens als 'parent' worden gebruikt (= hebben kinderen,
	// dus zijn geen Model maar een Merk of Serie).
	$term_op_id        = array();
	$heeft_kinderen    = array();
	foreach ( $alle_termen as $term ) {
		$term_op_id[ $term->term_id ] = $term;
		if ( $term->parent ) {
			$heeft_kinderen[ $term->parent ] = true;
		}
	}

	$zoekwoorden = array_filter( preg_split( '/\s+/', fxr_normaliseer_zoektekst( $zoekterm ) ) );
	$genormaliseerde_zoekterm = fxr_normaliseer_zoektekst( $zoekterm );

	$gevonden = array();
	foreach ( $alle_termen as $term ) {
		// Een echte Model-term staat niet bovenaan (heeft een parent, is dus
		// geen Merk) en heeft zelf geen kinderen (niemand gebruikt 'm als
		// parent, dus is het geen Serie).
		if ( 0 === (int) $term->parent || isset( $heeft_kinderen[ $term->term_id ] ) ) {
			continue;
		}

		$serie = isset( $term_op_id[ $term->parent ] ) ? $term_op_id[ $term->parent ] : null;
		$merk  = ( $serie && $serie->parent && isset( $term_op_id[ $serie->parent ] ) ) ? $term_op_id[ $serie->parent ] : null;

		$volledige_naam = trim( ( $merk ? $merk->name . ' ' : '' ) . ( $serie ? $serie->name . ' ' : '' ) . $term->name );
		$haystack       = fxr_normaliseer_zoektekst( $volledige_naam );

		$alle_woorden_gevonden = true;
		foreach ( $zoekwoorden as $woord ) {
			if ( false === strpos( $haystack, $woord ) ) {
				$alle_woorden_gevonden = false;
				break;
			}
		}
		if ( ! $alle_woorden_gevonden ) {
			continue;
		}

		$gevonden[] = array(
			'id'     => $term->term_id,
			'name'   => $term->name,
			'serie'  => $serie ? $serie->name : '',
			'merk'   => $merk ? $merk->name : '',
			// Voor sortering hieronder: begint de volledige naam met precies
			// wat er getypt is? Dat voelt voor de klant als de beste match.
			'_score' => ( 0 === strpos( $haystack, $genormaliseerde_zoekterm ) ) ? 0 : 1,
		);
	}

	// Beste match (score, dan alfabetisch) eerst, en tot 15 voorstellen tonen
	// — genoeg om te kunnen kiezen, niet zoveel dat de lijst onoverzichtelijk
	// wordt.
	usort(
		$gevonden,
		function ( $a, $b ) {
			if ( $a['_score'] !== $b['_score'] ) {
				return $a['_score'] <=> $b['_score'];
			}
			return strcasecmp( $a['merk'] . $a['serie'] . $a['name'], $b['merk'] . $b['serie'] . $b['name'] );
		}
	);
	$gevonden = array_slice( $gevonden, 0, 15 );
	foreach ( $gevonden as &$item ) {
		unset( $item['_score'] );
	}
	unset( $item );

	wp_send_json_success( array( 'models' => $gevonden ) );
}

/**
 * Helper: tekst geschikt maken om te vergelijken tijdens het zoeken
 * (kleine letters, overtollige spaties weg). Simpel met opzet — dit hoeft
 * geen volledige tekstnormalisatie te zijn, alleen consistent voor beide
 * kanten van de vergelijking (getypte tekst en de Merk/Serie/Model-namen).
 */
function fxr_normaliseer_zoektekst( $tekst ) {
	return trim( mb_strtolower( $tekst, 'UTF-8' ) );
}

/**
 * AJAX: zoek producten op basis van één gekozen Model-term_id.
 * Wordt aangeroepen zodra de klant in mode "autocomplete" een voorstel
 * uit de lijst kiest — dus nooit op basis van vrij getypte tekst.
 */
add_action( 'wp_ajax_fxr_zoek_op_model', 'fxr_ajax_zoek_op_model' );
add_action( 'wp_ajax_nopriv_fxr_zoek_op_model', 'fxr_ajax_zoek_op_model' );
function fxr_ajax_zoek_op_model() {
	check_ajax_referer( 'fxr_zoeker_nonce', 'nonce' );

	$taxonomy = fxr_valid_taxonomy( isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '' );
	$model_id = isset( $_POST['model_id'] ) ? absint( $_POST['model_id'] ) : 0;

	if ( ! $taxonomy || ! $model_id ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => '',
			)
		);
	}

	// Bevestig dat dit term_id echt bij deze taxonomie hoort (voorkomt dat
	// iemand handmatig een term_id van iets anders meestuurt).
	$term = get_term( $model_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		wp_send_json_success(
			array(
				'products' => array(),
				'message'  => 'Onbekend model.',
			)
		);
	}

	$products_data = fxr_get_products_data_by_terms( $taxonomy, array( $model_id ) );

	wp_send_json_success(
		array(
			'products' => $products_data,
			'message'  => empty( $products_data ) ? 'Geen onderdelen gevonden voor "' . esc_html( $term->name ) . '".' : '',
		)
	);
}

/**
 * Helper: haal WooCommerce-productgegevens op voor alles dat getagd is met
 * één of meer van de gegeven term_ids in de gegeven taxonomie. Gedeeld door
 * beide zoek-AJAX-endpoints (dropdown-modus en autocomplete-modus).
 */
function fxr_get_products_data_by_terms( $taxonomy, $term_ids ) {
	if ( empty( $term_ids ) || ! function_exists( 'wc_get_product' ) ) {
		return array();
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
					'terms'    => $term_ids,
				),
			),
		)
	);

	$products_data = array();
	foreach ( $product_ids as $pid ) {
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

	return $products_data;
}

/**
 * Helper: alleen deze twee taxonomieën zijn geldig voor de AJAX-endpoints
 * (voorkomt dat iemand een willekeurige taxonomie kan opvragen).
 */
function fxr_valid_taxonomy( $taxonomy ) {
	$allowed = array( 'fxr_inkt_model', 'fxr_stofzuiger_model' );
	return in_array( $taxonomy, $allowed, true ) ? $taxonomy : false;
}

/**
 * Helper: korte, css-vriendelijke naam per taxonomie, gebruikt voor de
 * modifier-class op de zoeker-wrapper (bv. "fxr-zoeker--inkt"), zodat je de
 * twee zoekers los van elkaar kunt stylen in fxr-zoeker.css.
 */
function fxr_taxonomy_css_slug( $taxonomy ) {
	$slugs = array(
		'fxr_inkt_model'       => 'inkt',
		'fxr_stofzuiger_model' => 'stofzuiger',
	);
	return isset( $slugs[ $taxonomy ] ) ? $slugs[ $taxonomy ] : sanitize_html_class( $taxonomy );
}
