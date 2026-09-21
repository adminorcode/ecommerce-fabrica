/**
 * Petshop product personalizer.
 *
 * Uses the bundled Fabric.js build (no CDN). The visual canvas is decoupled
 * from the production resolution: the final PNG is always rebuilt at
 * mm / 25.4 * DPI pixels and, when a mask exists, alpha-clipped to it.
 */
( function () {
	'use strict';

	var config = window.petshopPersonalizerConfig;
	if ( ! config || ! window.fabric ) {
		return;
	}

	var product = config.product || {};
	var text = config.i18n || {};
	var CANVAS_SIZE = 520;
	var PRINT_AREA_RATIO = 0.72;

	var state = {
		canvas: null,
		printArea: null,
		guide: null,
		uploadToken: '',
		uploadDataUrl: '',
		history: [],
		historyIndex: -1,
		restoring: false,
		confirmed: false,
		busy: false,
	};

	var dom = {};

	document.addEventListener( 'DOMContentLoaded', function () {
		dom.root = document.querySelector( '[data-petshop-personalizer]' );
		dom.body = document.querySelector( '[data-petshop-personalizer-body]' );
		dom.dialog = document.querySelector( '[data-petshop-personalizer-dialog]' );
		dom.title = document.querySelector( '.petshop-personalizer__title' );
		dom.form = document.querySelector( 'form.cart' );

		if ( ! dom.root || ! dom.body ) {
			return;
		}

		if ( dom.title ) {
			dom.title.textContent = text.title || '';
		}

		bindTriggers();
		ensureHiddenField();

		if ( config.autoOpen ) {
			open();
		}
	} );

	function bindTriggers() {
		var openers = document.querySelectorAll( '[data-petshop-personalize-open]' );
		Array.prototype.forEach.call( openers, function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				open();
			} );
		} );

		var closers = document.querySelectorAll( '[data-petshop-personalizer-close]' );
		Array.prototype.forEach.call( closers, function ( button ) {
			button.addEventListener( 'click', close );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && dom.root && ! dom.root.hasAttribute( 'hidden' ) ) {
				close();
			}
		} );

		var resizeTimer = null;
		window.addEventListener( 'resize', function () {
			if ( resizeTimer ) {
				window.clearTimeout( resizeTimer );
			}

			resizeTimer = window.setTimeout( fitStage, 150 );
		} );

		if ( dom.form ) {
			dom.form.addEventListener( 'submit', function ( event ) {
				var field = dom.form.querySelector( '[name="' + config.fieldName + '"]' );
				if ( field && field.value === '' ) {
					event.preventDefault();
					window.alert( text.required || '' );
					open();
				}
			} );
		}
	}

	function ensureHiddenField() {
		if ( ! dom.form || dom.form.querySelector( '[name="' + config.fieldName + '"]' ) ) {
			return;
		}

		var field = document.createElement( 'input' );
		field.type = 'hidden';
		field.name = config.fieldName;
		field.value = '';
		dom.form.appendChild( field );
	}

	function open() {
		if ( ! dom.root ) {
			return;
		}

		dom.root.removeAttribute( 'hidden' );
		document.body.classList.add( 'petshop-personalizer-open' );

		if ( ! state.canvas ) {
			renderInterface();
			buildCanvas();
		}

		fitStage();

		if ( dom.dialog ) {
			dom.dialog.focus();
		}
	}

	/**
	 * Fabric writes inline width/height on the canvas element, so the visible size has
	 * to be recalculated in JavaScript to keep the art square inside small viewports.
	 */
	function fitStage() {
		if ( ! state.canvas || ! dom.body ) {
			return;
		}

		var stage = dom.body.querySelector( '.petshop-personalizer__stage' );
		if ( ! stage ) {
			return;
		}

		var available = Math.min( stage.clientWidth - 24, window.innerHeight * 0.5 );
		var size = Math.round( Math.max( 240, Math.min( CANVAS_SIZE, available ) ) );

		state.canvas.setDimensions(
			{ width: size + 'px', height: size + 'px' },
			{ cssOnly: true }
		);
		state.canvas.calcOffset();
	}

	function close() {
		if ( ! dom.root ) {
			return;
		}

		dom.root.setAttribute( 'hidden', 'hidden' );
		document.body.classList.remove( 'petshop-personalizer-open' );
	}

	function renderInterface() {
		dom.body.innerHTML = '';

		if ( product.instruction ) {
			var instruction = document.createElement( 'p' );
			instruction.className = 'petshop-personalizer__instruction';
			instruction.textContent = product.instruction;
			dom.body.appendChild( instruction );
		}

		var stage = document.createElement( 'div' );
		stage.className = 'petshop-personalizer__stage';
		var canvasElement = document.createElement( 'canvas' );
		canvasElement.id = 'petshop-personalizer-canvas';
		canvasElement.width = CANVAS_SIZE;
		canvasElement.height = CANVAS_SIZE;
		stage.appendChild( canvasElement );
		dom.body.appendChild( stage );

		var controls = document.createElement( 'div' );
		controls.className = 'petshop-personalizer__controls';
		dom.body.appendChild( controls );

		if ( product.allowText ) {
			dom.textInput = createInput( controls, text.textPlaceholder );
			dom.fontSelect = createSelect( controls, text.font, product.fonts || [] );
			dom.colorSelect = createSelect( controls, text.color, product.colors || [] );
			createButton( controls, text.addText, addText );
		}

		if ( product.allowImage ) {
			dom.fileInput = document.createElement( 'input' );
			dom.fileInput.type = 'file';
			dom.fileInput.accept = 'image/jpeg,image/png,image/webp';
			dom.fileInput.className = 'petshop-personalizer__file';
			dom.fileInput.setAttribute( 'aria-label', text.addImage || '' );
			dom.fileInput.addEventListener( 'change', handleUpload );
			controls.appendChild( dom.fileInput );
		}

		createButton( controls, text.remove, removeSelected );
		createButton( controls, text.undo, undo );
		createButton( controls, text.redo, redo );
		createButton( controls, text.reset, reset );

		dom.status = document.createElement( 'p' );
		dom.status.className = 'petshop-personalizer__status';
		dom.status.setAttribute( 'role', 'status' );
		dom.status.setAttribute( 'aria-live', 'polite' );
		dom.body.appendChild( dom.status );

		var actions = document.createElement( 'div' );
		actions.className = 'petshop-personalizer__actions';
		dom.confirmButton = createButton( actions, text.confirm, confirmArt, 'petshop-personalizer__confirm' );
		dom.body.appendChild( actions );
	}

	function createInput( parent, placeholder ) {
		var input = document.createElement( 'input' );
		input.type = 'text';
		input.maxLength = 120;
		input.placeholder = placeholder || '';
		input.setAttribute( 'aria-label', placeholder || '' );
		input.className = 'petshop-personalizer__text-input';
		parent.appendChild( input );

		return input;
	}

	function createSelect( parent, label, options ) {
		var select = document.createElement( 'select' );
		select.className = 'petshop-personalizer__select';
		select.setAttribute( 'aria-label', label || '' );
		options.forEach( function ( option ) {
			var node = document.createElement( 'option' );
			node.value = option;
			node.textContent = option;
			select.appendChild( node );
		} );
		parent.appendChild( select );

		return select;
	}

	function createButton( parent, label, handler, className ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button ' + ( className || 'petshop-personalizer__button' );
		button.textContent = label || '';
		button.addEventListener( 'click', handler );
		parent.appendChild( button );

		return button;
	}

	function buildCanvas() {
		state.canvas = new fabric.Canvas( 'petshop-personalizer-canvas', {
			backgroundColor: '#ffffff',
			preserveObjectStacking: true,
			selection: true,
		} );

		var ratio = ( product.widthMm || 1 ) / ( product.heightMm || 1 );
		var areaWidth = CANVAS_SIZE * PRINT_AREA_RATIO;
		var areaHeight = areaWidth / ratio;
		if ( areaHeight > CANVAS_SIZE * PRINT_AREA_RATIO ) {
			areaHeight = CANVAS_SIZE * PRINT_AREA_RATIO;
			areaWidth = areaHeight * ratio;
		}

		state.printArea = {
			left: ( CANVAS_SIZE - areaWidth ) / 2,
			top: ( CANVAS_SIZE - areaHeight ) / 2,
			width: areaWidth,
			height: areaHeight,
		};

		state.guide = new fabric.Rect( {
			left: state.printArea.left,
			top: state.printArea.top,
			width: areaWidth,
			height: areaHeight,
			fill: 'rgba(0,0,0,0)',
			stroke: '#17676a',
			strokeDashArray: [ 8, 6 ],
			strokeWidth: 2,
			selectable: false,
			evented: false,
			hoverCursor: 'default',
		} );
		state.canvas.add( state.guide );

		if ( product.mockupUrl ) {
			fabric.Image.fromURL(
				product.mockupUrl,
				function ( image ) {
					if ( ! image ) {
						return;
					}
					var scale = Math.min( CANVAS_SIZE / image.width, CANVAS_SIZE / image.height );
					image.set( {
						originX: 'center',
						originY: 'center',
						left: CANVAS_SIZE / 2,
						top: CANVAS_SIZE / 2,
						scaleX: scale,
						scaleY: scale,
					} );
					state.canvas.setBackgroundImage( image, state.canvas.renderAll.bind( state.canvas ) );
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		if ( product.maskUrl ) {
			fabric.Image.fromURL(
				product.maskUrl,
				function ( image ) {
					if ( ! image ) {
						return;
					}
					image.set( {
						left: state.printArea.left,
						top: state.printArea.top,
						scaleX: state.printArea.width / image.width,
						scaleY: state.printArea.height / image.height,
						opacity: 0.25,
						selectable: false,
						evented: false,
					} );
					state.canvas.setOverlayImage( image, state.canvas.renderAll.bind( state.canvas ) );
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		state.canvas.on( 'object:modified', pushHistory );
		pushHistory();
	}

	function artObjects() {
		return state.canvas.getObjects().filter( function ( object ) {
			return object !== state.guide;
		} );
	}

	function addText() {
		var value = ( dom.textInput.value || '' ).trim();
		if ( value === '' ) {
			return;
		}

		var existing = artObjects().filter( function ( object ) {
			return object.type === 'i-text' || object.type === 'text';
		} );
		if ( existing.length >= ( product.maxTextBoxes || 1 ) ) {
			announce( text.maxTextReached );

			return;
		}

		var textObject = new fabric.IText( value, {
			left: state.printArea.left + state.printArea.width / 2,
			top: state.printArea.top + state.printArea.height / 2,
			originX: 'center',
			originY: 'center',
			fontFamily: dom.fontSelect ? dom.fontSelect.value : 'Arial',
			fill: dom.colorSelect ? dom.colorSelect.value : '#111111',
			fontSize: Math.round( state.printArea.height * 0.18 ),
			textAlign: 'center',
		} );

		state.canvas.add( textObject );
		state.canvas.setActiveObject( textObject );
		state.canvas.requestRenderAll();
		dom.textInput.value = '';
		pushHistory();
	}

	function handleUpload( event ) {
		var file = event.target.files && event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}

		var alreadyAdded = artObjects().some( function ( object ) {
			return object.type === 'image';
		} );
		if ( alreadyAdded ) {
			announce( text.imageAlreadyAdded );
			dom.fileInput.value = '';

			return;
		}

		var payload = new FormData();
		payload.append( 'action', config.uploadAction );
		payload.append( 'nonce', config.nonce );
		payload.append( 'product_id', product.productId );
		payload.append( 'file', file );

		setBusy( true );
		request( payload )
			.then( function ( data ) {
				state.uploadToken = data.token;
				state.uploadDataUrl = data.dataUrl;
				if ( data.lowResolution ) {
					announce( ( text.lowResolution || '' ) + ' ' + ( data.recommended || '' ) );
				} else {
					announce( '' );
				}

				return placeUploadedImage( data.dataUrl );
			} )
			.catch( function ( error ) {
				announce( error.message || text.uploadError );
			} )
			.then( function () {
				setBusy( false );
				dom.fileInput.value = '';
			} );
	}

	function placeUploadedImage( dataUrl ) {
		return new Promise( function ( resolve ) {
			fabric.Image.fromURL( dataUrl, function ( image ) {
				if ( ! image ) {
					resolve();

					return;
				}

				var scale = Math.min(
					( state.printArea.width * 0.8 ) / image.width,
					( state.printArea.height * 0.8 ) / image.height
				);
				image.set( {
					originX: 'center',
					originY: 'center',
					left: state.printArea.left + state.printArea.width / 2,
					top: state.printArea.top + state.printArea.height / 2,
					scaleX: scale,
					scaleY: scale,
				} );
				state.canvas.add( image );
				state.canvas.setActiveObject( image );
				state.canvas.requestRenderAll();
				pushHistory();
				resolve();
			} );
		} );
	}

	function removeSelected() {
		var active = state.canvas.getActiveObjects();
		if ( ! active.length ) {
			return;
		}

		active.forEach( function ( object ) {
			if ( object.type === 'image' ) {
				state.uploadToken = '';
				state.uploadDataUrl = '';
			}
			state.canvas.remove( object );
		} );
		state.canvas.discardActiveObject();
		state.canvas.requestRenderAll();
		pushHistory();
	}

	function reset() {
		artObjects().forEach( function ( object ) {
			state.canvas.remove( object );
		} );
		state.uploadToken = '';
		state.uploadDataUrl = '';
		state.canvas.requestRenderAll();
		announce( '' );
		pushHistory();
	}

	function serializeArt() {
		return artObjects().map( function ( object ) {
			if ( object.type === 'image' ) {
				return {
					type: 'image',
					left: object.left,
					top: object.top,
					scaleX: object.scaleX,
					scaleY: object.scaleY,
					angle: object.angle,
				};
			}

			return {
				type: 'text',
				text: object.text,
				font: object.fontFamily,
				color: object.fill,
				fontSize: object.fontSize,
				left: object.left,
				top: object.top,
				scaleX: object.scaleX,
				scaleY: object.scaleY,
				angle: object.angle,
				align: object.textAlign,
			};
		} );
	}

	function pushHistory() {
		if ( state.restoring ) {
			return;
		}

		state.history = state.history.slice( 0, state.historyIndex + 1 );
		state.history.push( JSON.stringify( serializeArt() ) );
		if ( state.history.length > 20 ) {
			state.history.shift();
		}
		state.historyIndex = state.history.length - 1;
	}

	function undo() {
		if ( state.historyIndex <= 0 ) {
			return;
		}

		state.historyIndex -= 1;
		restoreHistory();
	}

	function redo() {
		if ( state.historyIndex >= state.history.length - 1 ) {
			return;
		}

		state.historyIndex += 1;
		restoreHistory();
	}

	function restoreHistory() {
		var snapshot = JSON.parse( state.history[ state.historyIndex ] || '[]' );
		state.restoring = true;

		artObjects().forEach( function ( object ) {
			state.canvas.remove( object );
		} );

		var pending = snapshot.filter( function ( item ) {
			return item.type === 'image';
		} ).length;

		snapshot.forEach( function ( item ) {
			if ( item.type === 'text' ) {
				state.canvas.add(
					new fabric.IText( item.text, {
						left: item.left,
						top: item.top,
						originX: 'center',
						originY: 'center',
						fontFamily: item.font,
						fill: item.color,
						fontSize: item.fontSize,
						scaleX: item.scaleX,
						scaleY: item.scaleY,
						angle: item.angle,
						textAlign: item.align,
					} )
				);

				return;
			}

			if ( item.type === 'image' && state.uploadDataUrl ) {
				fabric.Image.fromURL( state.uploadDataUrl, function ( image ) {
					image.set( {
						originX: 'center',
						originY: 'center',
						left: item.left,
						top: item.top,
						scaleX: item.scaleX,
						scaleY: item.scaleY,
						angle: item.angle,
					} );
					state.canvas.add( image );
					pending -= 1;
					if ( pending <= 0 ) {
						state.restoring = false;
						state.canvas.requestRenderAll();
					}
				} );
			}
		} );

		if ( pending <= 0 ) {
			state.restoring = false;
		}
		state.canvas.requestRenderAll();
	}

	function designPayload() {
		var area = state.printArea;

		return {
			schema: 1,
			objects: serializeArt().map( function ( item ) {
				var base = {
					type: item.type,
					left: ( item.left - area.left ) / area.width,
					top: ( item.top - area.top ) / area.height,
					angle: item.angle,
				};

				if ( item.type === 'text' ) {
					base.text = item.text;
					base.font = item.font;
					base.color = item.color;
					base.align = item.align;
					base.fontSize = ( item.fontSize * ( item.scaleY || 1 ) ) / area.height;

					return base;
				}

				base.scaleX = item.scaleX;
				base.scaleY = item.scaleY;

				return base;
			} ),
		};
	}

	function confirmArt() {
		if ( state.busy ) {
			return;
		}

		if ( ! artObjects().length ) {
			announce( text.emptyCanvas );

			return;
		}

		setBusy( true );
		announce( text.confirming );

		var previewUrl = exportPreview();

		exportProduction()
			.then( function ( productionUrl ) {
				var payload = new FormData();
				payload.append( 'action', config.draftAction );
				payload.append( 'nonce', config.nonce );
				payload.append( 'product_id', product.productId );
				payload.append( 'design', JSON.stringify( designPayload() ) );
				payload.append( 'preview', previewUrl );
				payload.append( 'production', productionUrl );
				if ( state.uploadToken ) {
					payload.append( 'upload_token', state.uploadToken );
				}

				return request( payload );
			} )
			.then( function ( data ) {
				state.confirmed = true;
				applyToCartForm( data.publicId );
				announce( text.confirmed );
				setBusy( false );
				close();
			} )
			.catch( function ( error ) {
				announce( error.message || text.genericError );
				setBusy( false );
			} );
	}

	function exportPreview() {
		state.guide.visible = false;
		state.canvas.requestRenderAll();
		var url = state.canvas.toDataURL( {
			format: 'png',
			multiplier: 800 / CANVAS_SIZE,
		} );
		state.guide.visible = true;
		state.canvas.requestRenderAll();

		return url;
	}

	function exportProduction() {
		var area = state.printArea;
		var background = state.canvas.backgroundImage;
		var overlay = state.canvas.overlayImage;

		state.guide.visible = false;
		state.canvas.backgroundImage = null;
		state.canvas.overlayImage = null;
		state.canvas.backgroundColor = 'rgba(0,0,0,0)';
		state.canvas.renderAll();

		var cropped = state.canvas.toDataURL( {
			format: 'png',
			left: area.left,
			top: area.top,
			width: area.width,
			height: area.height,
			multiplier: product.widthPx / area.width,
		} );

		state.canvas.backgroundImage = background;
		state.canvas.overlayImage = overlay;
		state.canvas.backgroundColor = '#ffffff';
		state.guide.visible = true;
		state.canvas.renderAll();

		return rasterizeExact( cropped, product.widthPx, product.heightPx ).then( applyMask );
	}

	function rasterizeExact( dataUrl, width, height ) {
		return new Promise( function ( resolve, reject ) {
			var image = new Image();
			image.onload = function () {
				var canvas = document.createElement( 'canvas' );
				canvas.width = width;
				canvas.height = height;
				var context = canvas.getContext( '2d' );
				context.clearRect( 0, 0, width, height );
				context.drawImage( image, 0, 0, width, height );
				resolve( canvas );
			};
			image.onerror = function () {
				reject( new Error( text.genericError ) );
			};
			image.src = dataUrl;
		} );
	}

	function applyMask( canvas ) {
		if ( ! product.maskUrl ) {
			return canvas.toDataURL( 'image/png' );
		}

		return new Promise( function ( resolve ) {
			var mask = new Image();
			mask.crossOrigin = 'anonymous';
			mask.onload = function () {
				var context = canvas.getContext( '2d' );
				context.globalCompositeOperation = 'destination-in';
				context.drawImage( mask, 0, 0, canvas.width, canvas.height );
				context.globalCompositeOperation = 'source-over';
				resolve( canvas.toDataURL( 'image/png' ) );
			};
			mask.onerror = function () {
				resolve( canvas.toDataURL( 'image/png' ) );
			};
			mask.src = product.maskUrl;
		} );
	}

	function applyToCartForm( publicId ) {
		if ( ! dom.form ) {
			return;
		}

		var field = dom.form.querySelector( '[name="' + config.fieldName + '"]' );
		if ( field ) {
			field.value = publicId;
		}

		var openers = document.querySelectorAll( '[data-petshop-personalize-open]' );
		Array.prototype.forEach.call( openers, function ( button ) {
			button.textContent = text.confirmed || button.textContent;
		} );
	}

	function request( payload ) {
		return window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload,
			} )
			.then( function ( response ) {
				return response.json().catch( function () {
					throw new Error( text.genericError );
				} );
			} )
			.then( function ( body ) {
				if ( ! body || ! body.success ) {
					throw new Error( ( body && body.data && body.data.message ) || text.genericError );
				}

				return body.data;
			} );
	}

	function setBusy( busy ) {
		state.busy = busy;
		if ( dom.confirmButton ) {
			dom.confirmButton.disabled = busy;
			dom.confirmButton.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}
	}

	function announce( message ) {
		if ( dom.status ) {
			dom.status.textContent = message || '';
		}
	}
} )();
