<?php
/**
 * UX Builder (Flatsome): de gecombineerde zoeker [fixxar_zoeker] als eigen
 * element "Onderdelenzoeker" (categorie Shop), met alle instellingen in het
 * zijpaneel. Zo plaats en pas je de zoeker zichtbaar aan, zonder
 * shortcode-attributen te typen. De preview is de echte, server-side
 * gerenderde shortcode (geen aparte template), dus wat je in de builder ziet
 * is precies wat bezoekers zien.
 *
 * Let op: de defaults hieronder moeten gelijk zijn aan die in
 * fxr_render_gecombineerde_shortcode() en fxr_design_defaults(). UX Builder
 * schrijft een attribuut alleen in de shortcode als het afwijkt van de default.
 *
 * Zonder Flatsome doet dit bestand niets; de shortcode werkt dan gewoon.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'ux_builder_setup', 'fxr_register_ux_builder_element' );
function fxr_register_ux_builder_element() {
	if ( ! function_exists( 'add_ux_builder_shortcode' ) ) {
		return;
	}

	add_ux_builder_shortcode(
		'fixxar_zoeker',
		array(
			'name'      => 'Onderdelenzoeker',
			'category'  => 'Shop',
			'thumbnail' => FXR_ZOEKER_URL . 'assets/img/fxr-ux-builder-thumb.svg',
			'info'      => '{{ title }}',
			'wrap'      => false,
			'options'   => array(
				'tekst_opties'      => array(
					'type'    => 'group',
					'heading' => 'Tekst',
					'options' => array(
						'eyebrow'   => array(
							'type'    => 'textfield',
							'heading' => 'Klein tekstje boven de titel',
							'default' => 'Fixxar Zoekmachine',
						),
						'title'     => array(
							'type'       => 'textfield',
							'heading'    => 'Titel',
							'default'    => 'Eén zoekmachine voor alle inktcartridges en stofzuigerzakken',
							'auto_focus' => true,
						),
						'title_tag' => array(
							'type'    => 'select',
							'heading' => 'Titel-tag (grootte volgt het thema)',
							'default' => 'h2',
							'options' => array(
								'h1' => 'H1',
								'h2' => 'H2',
								'h3' => 'H3',
								'h4' => 'H4',
								'p'  => 'Paragraaf',
							),
						),
					),
				),

				'categorie_opties'  => array(
					'type'    => 'group',
					'heading' => 'Categorieën',
					'options' => array(
						'categorieen'            => array(
							'type'    => 'select',
							'heading' => 'Zoeken in',
							'default' => 'inkt,stofzuiger',
							'options' => array(
								'inkt,stofzuiger' => 'Inkt + stofzuigers (met knoppen)',
								'inkt'            => 'Alleen inkt',
								'stofzuiger'      => 'Alleen stofzuigers',
							),
						),
						'categorie'              => array(
							'type'       => 'radio-buttons',
							'heading'    => 'Actief bij laden',
							'default'    => 'inkt',
							'conditions' => 'categorieen === "inkt,stofzuiger"',
							'options'    => array(
								'inkt'       => array( 'title' => 'Inkt' ),
								'stofzuiger' => array( 'title' => 'Stofzuigers' ),
							),
						),
						'label_inkt'             => array(
							'type'       => 'textfield',
							'heading'    => 'Knoptekst inkt',
							'default'    => 'Inkt',
							'conditions' => 'categorieen === "inkt,stofzuiger"',
						),
						'label_stofzuiger'       => array(
							'type'       => 'textfield',
							'heading'    => 'Knoptekst stofzuigers',
							'default'    => 'Stofzuigers',
							'conditions' => 'categorieen === "inkt,stofzuiger"',
						),
						'placeholder_inkt'       => array(
							'type'       => 'textfield',
							'heading'    => 'Placeholder inkt',
							'default'    => 'Bijv. HP, Deskjet, 2720...',
							'conditions' => 'categorieen !== "stofzuiger"',
						),
						'placeholder_stofzuiger' => array(
							'type'       => 'textfield',
							'heading'    => 'Placeholder stofzuigers',
							'default'    => 'Bijv. Miele, S241i, GD1000...',
							'conditions' => 'categorieen !== "inkt"',
						),
						'min_chars'              => array(
							'type'    => 'slider',
							'heading' => 'Voorstellen na … tekens',
							'default' => 2,
							'min'     => 1,
							'max'     => 5,
						),
					),
				),

				'vormgeving_opties' => array(
					'type'    => 'group',
					'heading' => 'Vormgeving',
					'options' => array(
						'stijl'      => array(
							'type'    => 'radio-buttons',
							'heading' => 'Stijl',
							'default' => 'gevuld',
							'options' => array(
								'gevuld'      => array( 'title' => 'Gevuld' ),
								'kaart'       => array( 'title' => 'Kaart' ),
								'transparant' => array( 'title' => 'Transparant' ),
							),
						),
						'kleur'      => array(
							'type'        => 'colorpicker',
							'heading'     => 'Accentkleur (leeg = themakleur)',
							'default'     => '',
							'alpha'       => true,
							'format'      => 'rgb',
							'position'    => 'bottom right',
						),
						'tekstkleur' => array(
							'type'    => 'radio-buttons',
							'heading' => 'Tekstkleur',
							'default' => '',
							'options' => array(
								''       => array( 'title' => 'Auto' ),
								'licht'  => array( 'title' => 'Licht' ),
								'donker' => array( 'title' => 'Donker' ),
							),
						),
						'uitlijning' => array(
							'type'    => 'radio-buttons',
							'heading' => 'Uitlijning',
							'default' => 'left',
							'options' => array(
								'left'   => array(
									'title' => 'Links',
									'icon'  => 'dashicons-editor-alignleft',
								),
								'center' => array(
									'title' => 'Midden',
									'icon'  => 'dashicons-editor-aligncenter',
								),
							),
						),
						'breedte'    => array(
							'type'        => 'scrubfield',
							'heading'     => 'Max. breedte zoekveld',
							'default'     => '',
							'placeholder' => '680px',
							'min'         => 200,
							'max'         => 1400,
							'step'        => 10,
						),
						'padding'    => array(
							'type'        => 'scrubfield',
							'heading'     => 'Padding',
							'default'     => '',
							'placeholder' => 'auto',
							'min'         => 0,
							'max'         => 120,
							'step'        => 1,
						),
						'afronding'  => array(
							'type'        => 'scrubfield',
							'heading'     => 'Afronding',
							'default'     => '',
							'placeholder' => '14px',
							'min'         => 0,
							'max'         => 40,
							'step'        => 1,
						),
					),
				),

				'advanced_options'  => array(
					'type'    => 'group',
					'heading' => 'Advanced',
					'options' => array(
						'class'      => array(
							'type'    => 'textfield',
							'heading' => 'Class',
							'default' => '',
						),
						'visibility' => array(
							'type'    => 'select',
							'heading' => 'Visibility',
							'default' => '',
							'options' => array(
								''                               => 'Visible',
								'hidden'                         => 'Hidden',
								'hide-for-medium'                => 'Only for Desktop',
								'show-for-small'                 => 'Only for Mobile',
								'show-for-medium hide-for-small' => 'Only for Tablet',
								'show-for-medium'                => 'Hide for Desktop',
								'hide-for-small'                 => 'Hide for Mobile',
							),
						),
					),
				),
			),
		)
	);
}
