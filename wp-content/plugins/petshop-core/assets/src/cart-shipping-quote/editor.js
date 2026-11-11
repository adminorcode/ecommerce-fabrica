(function (blocks, element, i18n, blockEditor) {
    const { registerBlockType } = blocks;
    const { createElement: el } = element;
    const { __ } = i18n;

    registerBlockType('petshop/cart-shipping-quote', {
        apiVersion: 3,
        edit() {
            return el('section', blockEditor.useBlockProps({ className: 'petshop-cart-shipping is-editor-preview' }), [
                el('strong', { key: 'title' }, __('Calcular entrega por CEP', 'petshop-core')),
                el('p', { key: 'description' }, __('O campo de CEP aparece aqui no carrinho publicado.', 'petshop-core')),
            ]);
        },
        save() { return null; },
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor);
