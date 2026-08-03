import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	TextControl,
	Notice,
} from '@wordpress/components';

const REQUIRED_FIELDS = [
	{
		key: 'desktopImageId',
		message: __('Selecione a imagem desktop para publicar este banner.', 'petshop-core'),
	},
	{
		key: 'imageAlt',
		message: __('Informe o texto alternativo contextual.', 'petshop-core'),
	},
	{
		key: 'linkUrl',
		message: __('Informe o link de destino da campanha.', 'petshop-core'),
	},
];

export default function Edit({ attributes, setAttributes }) {
	const {
		desktopImageId,
		desktopImageUrl,
		mobileImageId,
		mobileImageUrl,
		imageAlt,
		linkUrl,
		editorLabel,
	} = attributes;

	const blockProps = useBlockProps({
		className: 'petshop-home-campaign-editor',
	});

	const missing = REQUIRED_FIELDS.filter(({ key }) => {
		if (key === 'desktopImageId') {
			return !desktopImageId;
		}

		return !String(attributes[key] || '').trim();
	});

	const setDesktopImage = (media) => {
		setAttributes({
			desktopImageId: media?.id || 0,
			desktopImageUrl: media?.url || '',
		});
	};

	const setMobileImage = (media) => {
		setAttributes({
			mobileImageId: media?.id || 0,
			mobileImageUrl: media?.url || '',
		});
	};

	const heading = editorLabel.trim() || __('Banner de campanha', 'petshop-core');

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Identificação no editor', 'petshop-core')} initialOpen={false}>
					<TextControl
						label={__('Rótulo interno', 'petshop-core')}
						help={__('Visível somente no editor para identificar a campanha.', 'petshop-core')}
						value={editorLabel}
						onChange={(value) => setAttributes({ editorLabel: value })}
					/>
				</PanelBody>
				<PanelBody title={__('Imagem desktop', 'petshop-core')} initialOpen>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={setDesktopImage}
							allowedTypes={['image']}
							value={desktopImageId || undefined}
							render={({ open }) => (
								<Button variant={desktopImageUrl ? 'secondary' : 'primary'} onClick={open}>
									{desktopImageUrl
										? __('Trocar imagem desktop', 'petshop-core')
										: __('Selecionar imagem desktop', 'petshop-core')}
								</Button>
							)}
						/>
					</MediaUploadCheck>
				</PanelBody>
				<PanelBody title={__('Imagem mobile', 'petshop-core')} initialOpen={false}>
					<p className="petshop-home-campaign-editor__help">
						{__('Opcional. Quando ausente, a loja usa a imagem desktop.', 'petshop-core')}
					</p>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={setMobileImage}
							allowedTypes={['image']}
							value={mobileImageId || undefined}
							render={({ open }) => (
								<Button variant={mobileImageUrl ? 'secondary' : 'primary'} onClick={open}>
									{mobileImageUrl
										? __('Trocar imagem mobile', 'petshop-core')
										: __('Selecionar imagem mobile', 'petshop-core')}
								</Button>
							)}
						/>
					</MediaUploadCheck>
					{mobileImageUrl ? (
						<Button
							variant="link"
							isDestructive
							onClick={() => setMobileImage(null)}
						>
							{__('Remover imagem mobile', 'petshop-core')}
						</Button>
					) : null}
				</PanelBody>
				<PanelBody title={__('Acessibilidade e link', 'petshop-core')} initialOpen>
					<TextControl
						label={__('Texto alternativo contextual', 'petshop-core')}
						value={imageAlt}
						onChange={(value) => setAttributes({ imageAlt: value })}
					/>
					<p className="components-base-control__help">
						{__('Link de destino', 'petshop-core')}
					</p>
					<URLInput
						value={linkUrl}
						onChange={(value) => setAttributes({ linkUrl: value || '' })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="petshop-home-campaign-editor__header">
					<strong>{heading}</strong>
					{linkUrl ? (
						<span className="petshop-home-campaign-editor__destination">
							{__('Destino:', 'petshop-core')} {linkUrl}
						</span>
					) : null}
				</div>

				{missing.map(({ message }) => (
					<Notice key={message} status="warning" isDismissible={false}>
						{message}
					</Notice>
				))}

				{desktopImageUrl ? (
					<div className="petshop-home-campaign-editor__preview">
						<img src={desktopImageUrl} alt={imageAlt || ''} />
					</div>
				) : (
					<div className="petshop-home-campaign-editor__placeholder">
						<MediaUploadCheck>
							<MediaUpload
								onSelect={setDesktopImage}
								allowedTypes={['image']}
								render={({ open }) => (
									<Button variant="primary" onClick={open}>
										{__('Selecionar imagem desktop', 'petshop-core')}
									</Button>
								)}
							/>
						</MediaUploadCheck>
					</div>
				)}

				{mobileImageUrl ? (
					<div className="petshop-home-campaign-editor__mobile-preview">
						<p>{__('Prévia mobile', 'petshop-core')}</p>
						<img src={mobileImageUrl} alt={imageAlt || ''} />
					</div>
				) : null}
			</div>
		</>
	);
}
