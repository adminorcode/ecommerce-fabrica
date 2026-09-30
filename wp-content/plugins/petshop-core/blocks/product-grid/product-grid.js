import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	Notice,
	PanelBody,
	RangeControl,
	SearchControl,
	SelectControl,
	Spinner,
	ToolbarGroup,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

const MODES = [
	{ label: __('Mais vendidos', 'petshop-core'), value: 'popular' },
	{ label: __('Por categoria', 'petshop-core'), value: 'category' },
	{ label: __('Coleção sazonal', 'petshop-core'), value: 'seasonal' },
	{ label: __('Seleção manual', 'petshop-core'), value: 'manual' },
];

const ORDERBY = [
	{ label: __('Mais recentes', 'petshop-core'), value: 'date' },
	{ label: __('Mais vendidos', 'petshop-core'), value: 'popularity' },
	{ label: __('Título', 'petshop-core'), value: 'title' },
	{ label: __('Preço', 'petshop-core'), value: 'price' },
	{ label: __('Ordem manual da loja', 'petshop-core'), value: 'menu_order' },
];

const ORDER = [
	{ label: __('Decrescente', 'petshop-core'), value: 'DESC' },
	{ label: __('Crescente', 'petshop-core'), value: 'ASC' },
];

function normalizeIds(ids) {
	return [...new Set((ids || []).map((id) => Number(id)).filter((id) => id > 0))];
}

function usePetshopSearch(endpoint, params) {
	const [items, setItems] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');
	const key = JSON.stringify(params);

	useEffect(() => {
		if (!params.search && !params.include) {
			setItems([]);
			setIsLoading(false);
			setError('');
			return () => {};
		}

		let alive = true;
		setIsLoading(true);
		setError('');
		const timeout = setTimeout(() => {
			apiFetch({ path: `/petshop/v1/product-grid/${endpoint}?${new URLSearchParams(params)}` })
				.then((response) => {
					if (alive) {
						setItems(response || []);
					}
				})
				.catch(() => {
					if (alive) {
						setError(__('Não foi possível carregar os dados.', 'petshop-core'));
					}
				})
				.finally(() => {
					if (alive) {
						setIsLoading(false);
					}
				});
		}, 250);

		return () => {
			alive = false;
			clearTimeout(timeout);
		};
	}, [endpoint, key]);

	return { items, isLoading, error };
}

function ProductSelector({ productIds, setAttributes }) {
	const [search, setSearch] = useState('');
	const { items: results, isLoading, error } = usePetshopSearch('products', { search });
	const { items: selectedProducts } = usePetshopSearch('products', {
		include: normalizeIds(productIds).join(','),
	});
	const selectedMap = useMemo(
		() => new Map(selectedProducts.map((product) => [product.id, product])),
		[selectedProducts]
	);
	const options = results
		.filter((product) => !productIds.includes(product.id))
		.map((product) => ({ label: product.label, value: String(product.id) }));

	const move = (id, direction) => {
		const current = normalizeIds(productIds);
		const index = current.indexOf(id);
		const nextIndex = index + direction;
		if (index < 0 || nextIndex < 0 || nextIndex >= current.length) {
			return;
		}
		const next = [...current];
		[next[index], next[nextIndex]] = [next[nextIndex], next[index]];
		setAttributes({ productIds: next });
	};

	return (
		<div className="petshop-product-grid-editor__control">
			<SearchControl
				label={__('Buscar produto por nome ou SKU', 'petshop-core')}
				value={search}
				onChange={setSearch}
				placeholder={__('Digite nome ou SKU', 'petshop-core')}
			/>
			{isLoading && <Spinner />}
			{error && <Notice status="error" isDismissible={false}>{error}</Notice>}
			<ComboboxControl
				label={__('Adicionar produto', 'petshop-core')}
				value=""
				options={options}
				onChange={(value) => {
					const id = Number(value);
					if (id > 0) {
						setAttributes({ productIds: [...normalizeIds(productIds), id] });
					}
				}}
			/>
			<ul className="petshop-product-grid-editor__selected">
				{normalizeIds(productIds).map((id, index) => {
					const product = selectedMap.get(id);
					return (
						<li key={id}>
							<span>{product ? product.label : `#${id}`}</span>
							<ToolbarGroup>
								<Button
									label={__('Mover para cima', 'petshop-core')}
									icon="arrow-up-alt2"
									disabled={index === 0}
									onClick={() => move(id, -1)}
								/>
								<Button
									label={__('Mover para baixo', 'petshop-core')}
									icon="arrow-down-alt2"
									disabled={index === productIds.length - 1}
									onClick={() => move(id, 1)}
								/>
								<Button
									label={__('Remover produto', 'petshop-core')}
									icon="no-alt"
									onClick={() => setAttributes({ productIds: productIds.filter((item) => item !== id) })}
								/>
							</ToolbarGroup>
						</li>
					);
				})}
			</ul>
		</div>
	);
}

function CategorySelector({ categoryIds, setAttributes }) {
	const [search, setSearch] = useState('');
	const { items: results, isLoading, error } = usePetshopSearch('categories', { search });
	const { items: selectedCategories } = usePetshopSearch('categories', {
		include: normalizeIds(categoryIds).join(','),
	});
	const selectedMap = useMemo(
		() => new Map(selectedCategories.map((category) => [category.id, category])),
		[selectedCategories]
	);
	const options = results
		.filter((category) => !categoryIds.includes(category.id))
		.map((category) => ({ label: category.label, value: String(category.id) }));

	return (
		<div className="petshop-product-grid-editor__control">
			<SearchControl
				label={__('Buscar categoria por nome', 'petshop-core')}
				value={search}
				onChange={setSearch}
				placeholder={__('Digite o nome da categoria', 'petshop-core')}
			/>
			{isLoading && <Spinner />}
			{error && <Notice status="error" isDismissible={false}>{error}</Notice>}
			<ComboboxControl
				label={__('Adicionar categoria', 'petshop-core')}
				value=""
				options={options}
				onChange={(value) => {
					const id = Number(value);
					if (id > 0) {
						setAttributes({ categoryIds: [...normalizeIds(categoryIds), id] });
					}
				}}
			/>
			<ul className="petshop-product-grid-editor__selected">
				{normalizeIds(categoryIds).map((id) => {
					const category = selectedMap.get(id);
					return (
						<li key={id}>
							<span>{category ? category.label : `#${id}`}</span>
							<Button
								label={__('Remover categoria', 'petshop-core')}
								icon="no-alt"
								onClick={() => setAttributes({ categoryIds: categoryIds.filter((item) => item !== id) })}
							/>
						</li>
					);
				})}
			</ul>
		</div>
	);
}

function Edit({ attributes, setAttributes }) {
	const mode = attributes.selectionMode || 'popular';
	const columns = Number(attributes.columns) || 4;
	const blockProps = useBlockProps({
		className: 'petshop-product-grid-editor',
		style: { '--petshop-product-grid-editor-columns': columns },
	});

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Configuração da vitrine', 'petshop-core')} initialOpen>
					<SelectControl
						label={__('Modo de seleção', 'petshop-core')}
						value={mode}
						options={MODES}
						onChange={(selectionMode) => setAttributes({ selectionMode })}
					/>
					{mode === 'manual' && (
						<ProductSelector productIds={normalizeIds(attributes.productIds)} setAttributes={setAttributes} />
					)}
					{mode === 'category' && (
						<CategorySelector categoryIds={normalizeIds(attributes.categoryIds)} setAttributes={setAttributes} />
					)}
					<RangeControl
						label={__('Quantidade de produtos', 'petshop-core')}
						value={attributes.limit}
						min={1}
						max={20}
						onChange={(limit) => setAttributes({ limit })}
					/>
					<RangeControl
						label={__('Colunas no desktop', 'petshop-core')}
						value={attributes.columns}
						min={2}
						max={6}
						onChange={(columns) => setAttributes({ columns })}
					/>
					{mode !== 'manual' && mode !== 'popular' && (
						<>
							<SelectControl
								label={__('Ordenar por', 'petshop-core')}
								value={attributes.orderby}
								options={ORDERBY}
								onChange={(orderby) => setAttributes({ orderby })}
							/>
							<SelectControl
								label={__('Direção', 'petshop-core')}
								value={attributes.order}
								options={ORDER}
								onChange={(order) => setAttributes({ order })}
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>
			<div className="petshop-product-grid-editor__preview">
				<ServerSideRender block={metadata.name} attributes={attributes} />
			</div>
		</div>
	);
}

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
});

[
	['popular', __('Mais vendidos', 'petshop-core'), { selectionMode: 'popular', orderby: 'popularity' }],
	['category', __('Por categoria', 'petshop-core'), { selectionMode: 'category' }],
	['seasonal', __('Coleção sazonal', 'petshop-core'), { selectionMode: 'seasonal', orderby: 'date', order: 'DESC' }],
	['manual', __('Seleção manual', 'petshop-core'), { selectionMode: 'manual', orderby: 'menu_order' }],
].forEach(([name, title, attrs]) => {
	registerBlockVariation(metadata.name, {
		name,
		title,
		attributes: { limit: 4, columns: 4, ...attrs },
		scope: ['inserter', 'transform'],
	});
});
