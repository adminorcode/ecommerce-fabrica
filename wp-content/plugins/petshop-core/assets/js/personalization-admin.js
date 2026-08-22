/**
 * Media library selectors for the product personalization tab.
 */
( function ( $ ) {
	'use strict';

	var settings = window.petshopPersonalizationAdmin || {};

	$( document ).on( 'click', '[data-petshop-media-select]', function ( event ) {
		event.preventDefault();

		var field = $( this ).closest( '[data-petshop-media-field]' );
		var input = field.find( '[data-petshop-media-input]' );
		var preview = field.find( '[data-petshop-media-preview]' );

		var frame = wp.media( {
			title: settings.title || '',
			button: { text: settings.button || '' },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			input.val( attachment.id );

			var url = attachment.url;
			if ( attachment.sizes && attachment.sizes.thumbnail ) {
				url = attachment.sizes.thumbnail.url;
			}

			preview.html(
				$( '<img>', {
					src: url,
					alt: attachment.alt || '',
					css: { maxWidth: '80px', height: 'auto', verticalAlign: 'middle' },
				} )
			);
		} );

		frame.open();
	} );

	$( document ).on( 'click', '[data-petshop-media-clear]', function ( event ) {
		event.preventDefault();

		var field = $( this ).closest( '[data-petshop-media-field]' );
		field.find( '[data-petshop-media-input]' ).val( '' );
		field.find( '[data-petshop-media-preview]' ).empty();
	} );
} )( window.jQuery );
