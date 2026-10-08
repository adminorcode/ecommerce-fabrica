(function (blocks, element, i18n) {
    const { registerBlockType } = blocks;
    const { createElement: el } = element;
    const { __ } = i18n;

    registerBlockType('petshop/cart-shipping-quote', {
        edit() {
            return el('section', { className: 'petshop-cart-shipping is-editor-preview' }, [
                el('strong', { key: 'title' }, __('Calcular entrega por CEP', 'petshop-core')),
                el('p', { key: 'description' }, __('O campo de CEP aparece aqui no carrinho publicado.', 'petshop-core')),
            ]);
        },
        save() { return null; },
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n);
