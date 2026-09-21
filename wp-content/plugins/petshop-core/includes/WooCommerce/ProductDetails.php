<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class ProductDetails
{
    private const NONCE_ACTION = 'petshop_calculate_shipping';
    private const FIELDS = [
        '_petshop_production_lead' => 'Prazo de produção',
        '_petshop_materials' => 'Materiais',
        '_petshop_contents' => 'Conteúdo da embalagem',
        '_petshop_care' => 'Cuidados',
        '_petshop_measurements' => 'Medidas',
    ];

    public static function bootstrap(): void
    {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'renderProductFields']);
        add_action('woocommerce_process_product_meta', [self::class, 'saveProductFields']);
        add_action('woocommerce_variation_options_inventory', [self::class, 'renderVariationFields'], 10, 3);
        add_action('woocommerce_save_product_variation', [self::class, 'saveVariationFields'], 10, 2);
        add_filter('woocommerce_available_variation', [self::class, 'extendVariationData'], 10, 3);
        add_filter('woocommerce_dropdown_variation_attribute_options_html', [self::class, 'addColorDataToOptions'], 10, 2);
        add_filter('woocommerce_product_tabs', [self::class, 'filterProductTabs'], 20);
        add_action('pa_color_add_form_fields', [self::class, 'renderColorAddField']);
        add_action('pa_color_edit_form_fields', [self::class, 'renderColorEditField']);
        add_action('created_pa_color', [self::class, 'saveColorField']);
        add_action('edited_pa_color', [self::class, 'saveColorField']);
        add_action('woocommerce_single_product_summary', [self::class, 'renderProductionAndSizeGuide'], 24);
        add_action('woocommerce_after_add_to_cart_form', [self::class, 'renderShippingCalculator'], 8);
        add_action('woocommerce_after_add_to_cart_form', [self::class, 'renderPersonalizationSlot'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('wp_ajax_petshop_calculate_shipping', [self::class, 'calculateShipping']);
        add_action('wp_ajax_nopriv_petshop_calculate_shipping', [self::class, 'calculateShipping']);
    }

    public static function renderProductFields(): void
    {
        echo '<div class="options_group">';
        foreach (self::FIELDS as $key => $label) {
            if ($key === '_petshop_production_lead') {
                woocommerce_wp_text_input(['id' => $key, 'label' => __($label, 'petshop-core'), 'description' => __('Informe somente um prazo aprovado, por exemplo “2 a 4 dias úteis”.', 'petshop-core'), 'desc_tip' => true]);
            } else {
                woocommerce_wp_textarea_input(['id' => $key, 'label' => __($label, 'petshop-core'), 'rows' => 3]);
            }
        }
        woocommerce_wp_select([
            'id' => '_petshop_size_guide_page_id',
            'label' => __('Guia de medidas', 'petshop-core'),
            'options' => self::pageOptions(),
            'description' => __('Página Gutenberg com instruções administráveis de medidas.', 'petshop-core'),
            'desc_tip' => true,
        ]);
        echo '</div>';
    }

    public static function saveProductFields(int $productId): void
    {
        if (!current_user_can('edit_post', $productId)) return;
        foreach (array_keys(self::FIELDS) as $key) {
            $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_textarea_field(wp_unslash((string) $_POST[$key])) : '';
            $value === '' ? delete_post_meta($productId, $key) : update_post_meta($productId, $key, $value);
        }
        $guideId = isset($_POST['_petshop_size_guide_page_id']) ? absint($_POST['_petshop_size_guide_page_id']) : 0;
        $guideId > 0 ? update_post_meta($productId, '_petshop_size_guide_page_id', $guideId) : delete_post_meta($productId, '_petshop_size_guide_page_id');
    }

    public static function renderVariationFields(int $loop, array $variationData, \WP_Post $variation): void
    {
        unset($variationData);
        woocommerce_wp_text_input([
            'id' => "petshop_variation_production_lead_{$loop}",
            'name' => "petshop_variation_production_lead[{$loop}]",
            'value' => (string) get_post_meta($variation->ID, '_petshop_production_lead', true),
            'label' => __('Prazo de produção', 'petshop-core'),
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    public static function saveVariationFields(int $variationId, int $loop): void
    {
        if (!current_user_can('edit_post', $variationId)) return;
        $raw = $_POST['petshop_variation_production_lead'][$loop] ?? '';
        $value = is_scalar($raw) ? sanitize_text_field(wp_unslash((string) $raw)) : '';
        $value === '' ? delete_post_meta($variationId, '_petshop_production_lead') : update_post_meta($variationId, '_petshop_production_lead', $value);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function extendVariationData(array $data, \WC_Product_Variable $product, \WC_Product_Variation $variation): array
    {
        $lead = trim((string) $variation->get_meta('_petshop_production_lead', true));
        if ($lead === '') $lead = trim((string) $product->get_meta('_petshop_production_lead', true));
        $data['petshop_production_lead'] = $lead;
        $data['petshop_sku'] = $variation->get_sku();
        return $data;
    }

    /** @param array<string, mixed> $args */
    public static function addColorDataToOptions(string $html, array $args): string
    {
        if (($args['attribute'] ?? '') !== 'pa_color') return $html;
        foreach (get_terms(['taxonomy' => 'pa_color', 'hide_empty' => false]) as $term) {
            if (!$term instanceof \WP_Term) continue;
            $color = sanitize_hex_color((string) get_term_meta($term->term_id, '_petshop_color_value', true));
            if (!is_string($color) || $color === '') continue;
            $needle = 'value="' . esc_attr($term->slug) . '"';
            $html = str_replace($needle, $needle . ' data-swatch-color="' . esc_attr($color) . '"', $html);
        }
        return $html;
    }

    public static function renderColorAddField(): void
    {
        wp_nonce_field('petshop_color_term', '_petshop_color_nonce');
        echo '<div class="form-field"><label for="petshop-color-value">' . esc_html__('Amostra da cor', 'petshop-core') . '</label><input id="petshop-color-value" name="petshop_color_value" type="color" value="#17676a"><p>' . esc_html__('Usada junto ao nome da cor na página do produto.', 'petshop-core') . '</p></div>';
    }

    public static function renderColorEditField(\WP_Term $term): void
    {
        $color = sanitize_hex_color((string) get_term_meta($term->term_id, '_petshop_color_value', true)) ?: '#17676a';
        wp_nonce_field('petshop_color_term', '_petshop_color_nonce');
        echo '<tr class="form-field"><th scope="row"><label for="petshop-color-value">' . esc_html__('Amostra da cor', 'petshop-core') . '</label></th><td><input id="petshop-color-value" name="petshop_color_value" type="color" value="' . esc_attr($color) . '"><p class="description">' . esc_html__('Usada junto ao nome da cor na página do produto.', 'petshop-core') . '</p></td></tr>';
    }

    public static function saveColorField(int $termId): void
    {
        $nonce = isset($_POST['_petshop_color_nonce']) && is_scalar($_POST['_petshop_color_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_petshop_color_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'petshop_color_term') || !current_user_can('manage_product_terms')) return;
        $color = isset($_POST['petshop_color_value']) && is_scalar($_POST['petshop_color_value']) ? sanitize_hex_color(wp_unslash((string) $_POST['petshop_color_value'])) : null;
        if (is_string($color) && $color !== '') update_term_meta($termId, '_petshop_color_value', $color);
    }

    /** @param array<string, array<string, mixed>> $tabs @return array<string, array<string, mixed>> */
    public static function filterProductTabs(array $tabs): array
    {
        global $product;
        if (!$product instanceof \WC_Product) return $tabs;
        $hasDetails = false;
        foreach (array_keys(self::FIELDS) as $key) {
            if ($key !== '_petshop_production_lead' && trim((string) $product->get_meta($key, true)) !== '') $hasDetails = true;
        }
        if ($hasDetails) {
            $tabs['petshop_details'] = ['title' => __('Detalhes e cuidados', 'petshop-core'), 'priority' => 15, 'callback' => [self::class, 'renderDetailsTab']];
        }
        if (!wc_review_ratings_enabled() || !comments_open($product->get_id())) unset($tabs['reviews']);
        return $tabs;
    }

    public static function renderDetailsTab(): void
    {
        global $product;
        if (!$product instanceof \WC_Product) return;
        echo '<dl class="petshop-product-details">';
        foreach (self::FIELDS as $key => $label) {
            if ($key === '_petshop_production_lead') continue;
            $value = trim((string) $product->get_meta($key, true));
            if ($value === '') continue;
            echo '<div><dt>' . esc_html(__($label, 'petshop-core')) . '</dt><dd>' . wp_kses_post(wpautop($value)) . '</dd></div>';
        }
        echo '</dl>';
    }

    public static function renderProductionAndSizeGuide(): void
    {
        global $product;
        if (!$product instanceof \WC_Product) return;
        $lead = trim((string) $product->get_meta('_petshop_production_lead', true));
        $guideId = (int) $product->get_meta('_petshop_size_guide_page_id', true);
        if ($lead === '' && $guideId <= 0 && !$product->is_type('variable')) return;
        echo '<div class="petshop-product-logistics" aria-live="polite">';
        echo '<p data-petshop-production-row' . ($lead === '' ? ' hidden' : '') . '><strong>' . esc_html__('Produção:', 'petshop-core') . '</strong> <span data-petshop-production-lead>' . esc_html($lead) . '</span></p>';
        if ($guideId > 0 && get_post_status($guideId) === 'publish') echo '<p><a href="' . esc_url((string) get_permalink($guideId)) . '">' . esc_html__('Consultar guia de medidas', 'petshop-core') . '</a></p>';
        echo '</div>';
    }

    public static function renderShippingCalculator(): void
    {
        global $product;
        if (!$product instanceof \WC_Product || !$product->needs_shipping()) return;
        echo '<section class="petshop-shipping-calculator" aria-labelledby="petshop-shipping-title"><h2 id="petshop-shipping-title">' . esc_html__('Calcular entrega', 'petshop-core') . '</h2>';
        echo '<form data-petshop-shipping-form><label for="petshop-shipping-postcode">' . esc_html__('CEP', 'petshop-core') . '</label><div><input id="petshop-shipping-postcode" name="postcode" inputmode="numeric" autocomplete="postal-code" maxlength="9" placeholder="00000-000" required>';
        echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $product->get_id()) . '"><input type="hidden" name="variation_id" value=""><button type="submit">' . esc_html__('Calcular entrega', 'petshop-core') . '</button></div></form>';
        echo '<div class="petshop-shipping-calculator__result" data-petshop-shipping-result aria-live="polite"></div></section>';
    }

    public static function renderPersonalizationSlot(): void
    {
        global $product;
        if ($product instanceof \WC_Product) do_action('petshop_product_personalization_slot', $product);
    }

    public static function enqueueAssets(): void
    {
        if (!is_product()) return;
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/product-experience.js';
        wp_enqueue_script('petshop-product-experience', plugins_url('assets/js/product-experience.js', PETSHOP_CORE_FILE), ['jquery', 'wc-add-to-cart-variation'], is_file($path) ? (string) filemtime($path) : '1.0.0', true);
        wp_add_inline_script('petshop-product-experience', 'window.petshopProductConfig=' . wp_json_encode([
            'ajaxUrl' => wp_make_link_relative(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'calculating' => __('Calculando opções de entrega…', 'petshop-core'),
            'invalidPostcode' => __('Informe um CEP brasileiro com 8 números.', 'petshop-core'),
            'genericError' => __('Não foi possível calcular agora. Revise o CEP e tente novamente.', 'petshop-core'),
            'selectVariation' => __('Escolha as opções obrigatórias antes de adicionar ao carrinho.', 'petshop-core'),
            'deliveryTo' => __('Entrega para', 'petshop-core'),
            'receiveIn' => __('Receba em', 'petshop-core'),
            'deliveryAtCheckout' => __('Prazo confirmado no carrinho', 'petshop-core'),
            'productionLabel' => __('Produção', 'petshop-core'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';', 'before');
    }

    public static function calculateShipping(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $postcode = isset($_POST['postcode']) && is_scalar($_POST['postcode']) ? preg_replace('/\D+/', '', wp_unslash((string) $_POST['postcode'])) : '';
        $productId = isset($_POST['variation_id']) && absint($_POST['variation_id']) > 0 ? absint($_POST['variation_id']) : absint($_POST['product_id'] ?? 0);
        if (!is_string($postcode) || strlen($postcode) !== 8 || $productId <= 0) wp_send_json_error(['message' => __('Informe um CEP e um produto válidos.', 'petshop-core')], 400);
        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product || !$product->is_purchasable()) wp_send_json_error(['message' => __('Produto indisponível para cálculo.', 'petshop-core')], 400);

        $quotes = ShippingQuotes::quote($product, $postcode);
        if ($quotes['rates'] === []) wp_send_json_error(['message' => __('Não há opção de entrega para este CEP. Confira o endereço ou fale com o atendimento.', 'petshop-core')], 404);
        wp_send_json_success($quotes);
    }

    /** @return array<int, string> */
    private static function pageOptions(): array
    {
        $options = [0 => __('Nenhum guia selecionado', 'petshop-core')];
        foreach (get_pages(['sort_column' => 'post_title']) as $page) $options[(int) $page->ID] = $page->post_title;
        return $options;
    }
}
