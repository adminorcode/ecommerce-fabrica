<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class AddressLookup
{
    private const AJAX_ACTION = 'petshop_lookup_cep';
    private const NONCE_ACTION = 'petshop_lookup_cep';

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajaxLookup']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'ajaxLookup']);
    }

    public static function enqueue(): void
    {
        if (!is_account_page() && !is_checkout()) {
            return;
        }

        $relative = 'assets/js/address-lookup.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;

        wp_enqueue_script(
            'petshop-address-lookup',
            plugins_url($relative, PETSHOP_CORE_FILE),
            [],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );

        wp_localize_script(
            'petshop-address-lookup',
            'petshopAddressLookup',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => self::AJAX_ACTION,
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'consulting' => __('Consultando CEP...', 'petshop-core'),
                'found' => __('Endereço encontrado pelo CEP.', 'petshop-core'),
                'invalid' => __('Informe um CEP válido com 8 dígitos.', 'petshop-core'),
                'unavailable' => __(
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                    'petshop-core'
                ),
            ]
        );
    }

    public static function ajaxLookup(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $cep = isset($_POST['cep'])
            ? preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['cep'])))
            : '';

        if (!is_string($cep) || strlen($cep) !== 8) {
            wp_send_json_error(
                [
                    'message' => __(
                        'Informe um CEP válido com 8 dígitos.',
                        'petshop-core'
                    ),
                ],
                400
            );
        }

        $result = self::lookupCep($cep);

        if (is_wp_error($result)) {
            wp_send_json_error(
                ['message' => $result->get_error_message()],
                502
            );
        }

        wp_send_json_success($result);
    }

    /**
     * @return array{logradouro: string, bairro: string, localidade: string, uf: string, complemento: string}|\WP_Error
     */
    public static function lookupCep(string $cep): array|\WP_Error
    {
        $cep = preg_replace('/\D+/', '', $cep) ?? '';

        if (strlen($cep) !== 8) {
            return new \WP_Error(
                'petshop_invalid_cep',
                __('Informe um CEP válido com 8 dígitos.', 'petshop-core')
            );
        }

        $response = wp_remote_get(
            'https://viacep.com.br/ws/' . rawurlencode($cep) . '/json/',
            [
                'timeout' => 5,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error(
                'petshop_viacep_unavailable',
                __(
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                    'petshop-core'
                )
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error(
                'petshop_viacep_unavailable',
                __(
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                    'petshop-core'
                )
            );
        }

        $data = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        if (!is_array($data)) {
            return new \WP_Error(
                'petshop_viacep_invalid_response',
                __(
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                    'petshop-core'
                )
            );
        }

        if (!empty($data['erro'])) {
            return new \WP_Error(
                'petshop_cep_not_found',
                __('CEP não encontrado. Confira o número informado.', 'petshop-core')
            );
        }

        return [
            'logradouro' => sanitize_text_field((string) ($data['logradouro'] ?? '')),
            'bairro' => sanitize_text_field((string) ($data['bairro'] ?? '')),
            'localidade' => sanitize_text_field((string) ($data['localidade'] ?? '')),
            'uf' => sanitize_text_field((string) ($data['uf'] ?? '')),
            'complemento' => sanitize_text_field((string) ($data['complemento'] ?? '')),
        ];
    }
}
