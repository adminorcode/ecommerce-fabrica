/**
 * Editor registration for the petshop/personalizable-products block.
 *
 * Written as plain ES5 so the block does not require a build step.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'petshop/personalizable-products', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Vitrine', 'petshop-core' ), initialOpen: true },
					el( RangeControl, {
						label: __( 'Quantidade de produtos', 'petshop-core' ),
						value: attributes.limit,
						min: 1,
						max: 24,
						onChange: function ( value ) {
							props.setAttributes( { limit: value || 8 } );
						},
					} ),
					el( RangeControl, {
						label: __( 'Colunas', 'petshop-core' ),
						value: attributes.columns,
						min: 2,
						max: 6,
						onChange: function ( value ) {
							props.setAttributes( { columns: value || 4 } );
						},
					} ),
					el( TextControl, {
						label: __( 'IDs de produtos (opcional)', 'petshop-core' ),
						help: __(
							'Lista separada por vírgula. Quando preenchida, só esses produtos entram na vitrine (ainda precisam estar habilitados e publicados).',
							'petshop-core'
						),
						value: attributes.productIds || '',
						onChange: function ( value ) {
							props.setAttributes( { productIds: value || '' } );
						},
					} ),
					el( TextControl, {
						label: __( 'Categorias (slugs, opcional)', 'petshop-core' ),
						help: __(
							'Slugs de product_cat separados por vírgula. Usado quando IDs não forem informados.',
							'petshop-core'
						),
						value: attributes.categorySlugs || '',
						onChange: function ( value ) {
							props.setAttributes( { categorySlugs: value || '' } );
						},
					} )
				)
			);

			var preview = ServerSideRender
				? el( ServerSideRender, {
						block: 'petshop/personalizable-products',
						attributes: attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								'p',
								null,
								__(
									'Nenhum produto com personalização habilitada foi publicado ainda.',
									'petshop-core'
								)
							);
						},
				  } )
				: el(
						'p',
						null,
						__( 'Vitrine de produtos personalizáveis.', 'petshop-core' )
				  );

			return el( 'div', blockProps, inspector, preview );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
