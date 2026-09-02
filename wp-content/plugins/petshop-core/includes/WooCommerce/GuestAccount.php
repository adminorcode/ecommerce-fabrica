<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class GuestAccount
{
    public static function bootstrap(): void
    {
        add_action('woocommerce_thankyou', [self::class, 'renderOffer'], 30);
        add_action('admin_post_nopriv_petshop_create_order_account', [self::class, 'handle']);
        add_action('admin_post_petshop_create_order_account', [self::class, 'handle']);
    }

    public static function renderOffer(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (
            !$order instanceof \WC_Order
            || !$order->has_status(['pending', 'processing', 'on-hold', 'completed'])
            || $order->get_customer_id() > 0
            || is_user_logged_in()
        ) {
            return;
        }

        $email = sanitize_email($order->get_billing_email());

        echo '<section class="petshop-guest-account">';
        echo '<h2>' . esc_html__('Acompanhe seus pedidos em uma conta', 'petshop-core') . '</h2>';

        $status = isset($_GET['account'])
            ? sanitize_key(wp_unslash((string) $_GET['account']))
            : '';

        if ($status === 'password_required') {
            echo '<p class="woocommerce-error">'
                . esc_html__('Escolha uma senha e confirme-a.', 'petshop-core')
                . '</p>';
        }

        if ($status === 'password_mismatch') {
            echo '<p class="woocommerce-error">'
                . esc_html__('As senhas informadas não são iguais.', 'petshop-core')
                . '</p>';
        }

        if (email_exists($email)) {
            echo '<p>'
                . esc_html__('Já existe uma conta para o e-mail deste pedido. Entre para consultar seus pedidos.', 'petshop-core')
                . '</p>';

            echo '<p><a class="button" href="'
                . esc_url(wc_get_page_permalink('myaccount'))
                . '">'
                . esc_html__('Entrar na minha conta', 'petshop-core')
                . '</a></p></section>';

            return;
        }

        echo '<p>'
            . esc_html__('Confirme o e-mail usado na compra e escolha sua senha para criar a conta agora.', 'petshop-core')
            . '</p>';

        echo '<form method="post" action="'
            . esc_url(wp_make_link_relative(admin_url('admin-post.php')))
            . '">';

        echo '<input type="hidden" name="action" value="petshop_create_order_account">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $orderId) . '">';
        echo '<input type="hidden" name="order_key" value="' . esc_attr($order->get_order_key()) . '">';

        echo '<p><label for="petshop-order-account-email">'
            . esc_html__('E-mail do pedido', 'petshop-core')
            . '</label>';
        echo '<input id="petshop-order-account-email" name="confirm_email" type="email" autocomplete="email" required></p>';

        echo '<p><label for="petshop-order-account-password">'
            . esc_html__('Escolha sua senha', 'petshop-core')
            . '</label>';
        echo '<input id="petshop-order-account-password" name="password" type="password" autocomplete="new-password" required></p>';

        echo '<p><label for="petshop-order-account-password-confirm">'
            . esc_html__('Confirme sua senha', 'petshop-core')
            . '</label>';
        echo '<input id="petshop-order-account-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required></p>';

        wp_nonce_field(
            'petshop_create_order_account_' . $orderId,
            '_petshop_account_nonce'
        );

        echo '<button type="submit">'
            . esc_html__('Criar minha conta', 'petshop-core')
            . '</button>';

        echo '</form></section>';
    }

    public static function handle(): void
    {
        $orderId = isset($_POST['order_id'])
            ? absint($_POST['order_id'])
            : 0;

        $key = isset($_POST['order_key']) && is_scalar($_POST['order_key'])
            ? wc_clean(wp_unslash((string) $_POST['order_key']))
            : '';

        $confirmedEmail = isset($_POST['confirm_email']) && is_scalar($_POST['confirm_email'])
            ? sanitize_email(wp_unslash((string) $_POST['confirm_email']))
            : '';

        $password = isset($_POST['password']) && is_scalar($_POST['password'])
            ? (string) wp_unslash($_POST['password'])
            : '';

        $passwordConfirm = isset($_POST['password_confirm']) && is_scalar($_POST['password_confirm'])
            ? (string) wp_unslash($_POST['password_confirm'])
            : '';

        $nonce = isset($_POST['_petshop_account_nonce']) && is_scalar($_POST['_petshop_account_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['_petshop_account_nonce']))
            : '';

        $order = wc_get_order($orderId);

        if (
            !$order instanceof \WC_Order
            || !wp_verify_nonce($nonce, 'petshop_create_order_account_' . $orderId)
            || !self::requestMatchesOrder($order, $key, $confirmedEmail)
        ) {
            wp_die(
                esc_html__('Não foi possível validar o pedido.', 'petshop-core'),
                '',
                ['response' => 403]
            );
        }

        $redirect = $order->get_checkout_order_received_url();

        if ($order->get_customer_id() > 0) {
            wp_safe_redirect(add_query_arg('account', 'existing', $redirect));
            exit;
        }

        $email = sanitize_email($order->get_billing_email());

        if ($email === '' || email_exists($email)) {
            wp_safe_redirect(add_query_arg('account', 'login', $redirect));
            exit;
        }

        if ($password === '' || $passwordConfirm === '') {
            wp_safe_redirect(add_query_arg('account', 'password_required', $redirect));
            exit;
        }

        if (!hash_equals($password, $passwordConfirm)) {
            wp_safe_redirect(add_query_arg('account', 'password_mismatch', $redirect));
            exit;
        }

        $userId = wc_create_new_customer(
            $email,
            '',
            $password,
            [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
            ]
        );

        if (is_wp_error($userId)) {
            wp_die(
                esc_html($userId->get_error_message()),
                '',
                ['response' => 400]
            );
        }

        try {
            $customer = new \WC_Customer((int) $userId);

            $customer->set_billing_first_name($order->get_billing_first_name());
            $customer->set_billing_last_name($order->get_billing_last_name());
            $customer->set_billing_company($order->get_billing_company());
            $customer->set_billing_address_1($order->get_billing_address_1());
            $customer->set_billing_address_2($order->get_billing_address_2());
            $customer->set_billing_city($order->get_billing_city());
            $customer->set_billing_state($order->get_billing_state());
            $customer->set_billing_postcode($order->get_billing_postcode());
            $customer->set_billing_country($order->get_billing_country());
            $customer->set_billing_email($email);
            $customer->set_billing_phone($order->get_billing_phone());
            $customer->save();

            $order->set_customer_id((int) $userId);
            $order->save();
        } catch (\Throwable $error) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $userId);

            wp_die(
                esc_html__(
                    'A conta não pôde ser vinculada ao pedido. Nenhuma conta foi mantida.',
                    'petshop-core'
                ),
                '',
                ['response' => 500]
            );
        }

        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie((int) $userId);
        } else {
            wp_set_current_user((int) $userId);
            wp_set_auth_cookie((int) $userId, true);
        }

        wp_safe_redirect(add_query_arg('account', 'created', $redirect));
        exit;
    }

    public static function requestMatchesOrder(
        \WC_Order $order,
        string $orderKey,
        string $confirmedEmail
    ): bool {
        $billingEmail = sanitize_email($order->get_billing_email());

        return $order->has_status([
            'pending',
            'processing',
            'on-hold',
            'completed',
        ])
            && $orderKey !== ''
            && hash_equals($order->get_order_key(), $orderKey)
            && $billingEmail !== ''
            && strtolower($billingEmail)
                === strtolower(sanitize_email($confirmedEmail));
    }
}