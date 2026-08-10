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
	TextareaControl,
	Notice,
	RadioControl,
} from '@wordpress/components';

const ARTWORK_REQUIRED_FIELDS = [
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

const EDITORIAL_REQUIRED_FIELDS = [
	{
		key: 'desktopImageId',
		message: __('Selecione a imagem de apoio da campanha editorial.', 'petshop-core'),
	},
	{
		key: 'imageAlt',
		message: __('Informe o texto alternativo contextual da imagem.', 'petshop-core'),
	},
	{
		key: 'title',
		message: __('Informe o titulo da campanha editorial.', 'petshop-core'),
	},
	{
		key: 'ctaLabel',
		message: __('Informe o rotulo do CTA.', 'petshop-core'),
	},
	{
		key: 'linkUrl',
		message: __('Informe o destino do CTA.', 'petshop-core'),
	},
];

export default function Edit({ attributes, setAttributes }) {
	const {
		campaignMode = 'artwork',
		desktopImageId,
		desktopImageUrl,
		mobileImageId,
		mobileImageUrl,
		imageAlt,
		linkUrl,
		editorLabel,
		eyebrow,
		title,
		text,
		benefit,
		ctaLabel,
	} = attributes;
	const isEditorial = campaignMode === 'editorial';

	const blockProps = useBlockProps({
		className: 'petshop-home-campaign-editor',
	});

	const requiredFields = isEditorial ? EDITORIAL_REQUIRED_FIELDS : ARTWORK_REQUIRED_FIELDS;
	const missing = requiredFields.filter(({ key }) => {
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
	const modeLabel = isEditorial
		? __('Campanha editorial', 'petshop-core')
		: __('Arte final', 'petshop-core');

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Tipo de campanha', 'petshop-core')} initialOpen>
					<RadioControl
						label={__('Modalidade', 'petshop-core')}
						selected={campaignMode}
						options={[
							{
								label: __('Arte final', 'petshop-core'),
								value: 'artwork',
							},
							{
								label: __('Campanha editorial', 'petshop-core'),
								value: 'editorial',
							},
						]}
						onChange={(value) => setAttributes({ campaignMode: value })}
					/>
				</PanelBody>
				<PanelBody title={__('Identificacao no editor', 'petshop-core')} initialOpen={false}>
					<TextControl
						label={__('Rotulo interno', 'petshop-core')}
						help={__('Visivel somente no editor para identificar a campanha.', 'petshop-core')}
						value={editorLabel}
						onChange={(value) => setAttributes({ editorLabel: value })}
					/>
				</PanelBody>
				{isEditorial ? (
					<PanelBody title={__('Copy editorial', 'petshop-core')} initialOpen>
						<TextControl
							label={__('Eyebrow', 'petshop-core')}
							value={eyebrow}
							onChange={(value) => setAttributes({ eyebrow: value })}
						/>
						<TextControl
							label={__('Titulo', 'petshop-core')}
							value={title}
							onChange={(value) => setAttributes({ title: value })}
						/>
						<TextareaControl
							label={__('Texto', 'petshop-core')}
							value={text}
							onChange={(value) => setAttributes({ text: value })}
						/>
						<TextControl
							label={__('Beneficio', 'petshop-core')}
							value={benefit}
							onChange={(value) => setAttributes({ benefit: value })}
						/>
						<TextControl
							label={__('Rotulo do CTA', 'petshop-core')}
							value={ctaLabel}
							onChange={(value) => setAttributes({ ctaLabel: value })}
						/>
					</PanelBody>
				) : null}
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
						{isEditorial ? __('Destino do CTA', 'petshop-core') : __('Link de destino', 'petshop-core')}
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
					<span className="petshop-home-campaign-editor__mode">{modeLabel}</span>
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

				{isEditorial ? (
					<div className="petshop-home-campaign-editor__editorial-preview">
						<div className="petshop-home-campaign-editor__editorial-copy">
							<TextControl
								label={__('Eyebrow', 'petshop-core')}
								value={eyebrow}
								onChange={(value) => setAttributes({ eyebrow: value })}
							/>
							<TextControl
								label={__('Titulo', 'petshop-core')}
								value={title}
								onChange={(value) => setAttributes({ title: value })}
							/>
							<TextareaControl
								label={__('Texto', 'petshop-core')}
								value={text}
								onChange={(value) => setAttributes({ text: value })}
							/>
							<TextControl
								label={__('Beneficio', 'petshop-core')}
								value={benefit}
								onChange={(value) => setAttributes({ benefit: value })}
							/>
							<TextControl
								label={__('CTA', 'petshop-core')}
								value={ctaLabel}
								onChange={(value) => setAttributes({ ctaLabel: value })}
							/>
						</div>
						<div className="petshop-home-campaign-editor__editorial-media">
							{desktopImageUrl ? (
								<img src={desktopImageUrl} alt={imageAlt || ''} />
							) : (
								<div className="petshop-home-campaign-editor__placeholder">
									<MediaUploadCheck>
										<MediaUpload
											onSelect={setDesktopImage}
											allowedTypes={['image']}
											render={({ open }) => (
												<Button variant="primary" onClick={open}>
													{__('Selecionar imagem de apoio', 'petshop-core')}
												</Button>
											)}
										/>
									</MediaUploadCheck>
								</div>
							)}
						</div>
					</div>
				) : desktopImageUrl ? (
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
						<p>{__('Previa mobile', 'petshop-core')}</p>
						<img src={mobileImageUrl} alt={imageAlt || ''} />
					</div>
				) : null}
			</div>
		</>
	);
}
