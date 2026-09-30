import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { InspectorControls, useBlockProps, InnerBlocks, useInnerBlocksProps } from '@wordpress/block-editor';
import { Notice, PanelBody } from '@wordpress/components';
import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';

const ALLOWED_BLOCKS = ['petshop/home-campaign'];
const MAX_CAMPAIGNS = 3;

function Edit({ clientId }) {
	const innerCount = useSelect(
		(select) => select('core/block-editor').getBlockCount(clientId),
		[clientId]
	);
	const blockProps = useBlockProps({
		className: 'petshop-home-campaigns-editor',
	});
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		renderAppender: innerCount < MAX_CAMPAIGNS ? InnerBlocks.ButtonBlockAppender : false,
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Carrossel', 'petshop-core')} initialOpen>
					<p>
						{__(
							'Cadastre até 3 banners. Com 2 ou 3 imagens válidas, a loja exibe um carrossel com troca automática.',
							'petshop-core'
						)}
					</p>
					<p>
						{__(
							'O tempo de visualização de cada imagem é configurado no banner correspondente. Padrão: 10 segundos.',
							'petshop-core'
						)}
					</p>
				</PanelBody>
			</InspectorControls>
			<div {...innerBlocksProps}>
				{innerCount > MAX_CAMPAIGNS ? (
					<Notice status="warning" isDismissible={false}>
						{__('Somente os 3 primeiros banners válidos aparecem na loja.', 'petshop-core')}
					</Notice>
				) : null}
				{innerBlocksProps.children}
			</div>
		</>
	);
}

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
});
