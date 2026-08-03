import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks, useInnerBlocksProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';

const ALLOWED_BLOCKS = ['petshop/home-campaign'];

function Edit() {
	const blockProps = useBlockProps({
		className: 'petshop-home-campaigns-editor',
	});
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	});

	return (
		<div {...innerBlocksProps}>
			{innerBlocksProps.children}
		</div>
	);
}

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
});
