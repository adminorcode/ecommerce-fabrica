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
        if (!$order instanceof \WC_Order || !$order->has_status(['pending', 'processing', 'on-hold', 'completed']) || $order->get_customer_id() > 0 || is_user_logged_in()) return;
        $email = $order->get_billing_email();
        echo '<section class="petshop-guest-account"><h2>' . esc_html__('Acompanhe seus pedidos em uma conta', 'petshop-core') . '</h2>';
        if (email_exists($email)) {
            echo '<p>' . esc_html__('Já existe uma conta para o e-mail deste pedido. Entre para consultar seus pedidos.', 'petshop-core') . '</p><p><a class="button" href="' . esc_url(wc_get_page_permalink('myaccount')) . '">' . esc_html__('Entrar na minha conta', 'petshop-core') . '</a></p></section>';
            return;
        }
        echo '<p>' . esc_html__('Confirme o e-mail usado na compra. Você receberá um link seguro para definir a senha.', 'petshop-core') . '</p><form method="post" action="' . esc_url(wp_make_link_relative(admin_url('admin-post.php'))) . '">';
        echo '<input type="hidden" name="action" value="petshop_create_order_account"><input type="hidden" name="order_id" value="' . esc_attr((string) $orderId) . '"><input type="hidden" name="order_key" value="' . esc_attr($order->get_order_key()) . '">';
        echo '<p><label for="petshop-order-account-email">' . esc_html__('E-mail do pedido', 'petshop-core') . '</label><input id="petshop-order-account-email" name="confirm_email" type="email" autocomplete="email" required></p>';
        wp_nonce_field('petshop_create_order_account_' . $orderId, '_petshop_account_nonce');
        echo '<button type="submit">' . esc_html__('Criar conta para este pedido', 'petshop-core') . '</button></form></section>';
    }

    public static function handle(): void
    {
        $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $key = isset($_POST['order_key']) && is_scalar($_POST['order_key']) ? wc_clean(wp_unslash((string) $_POST['order_key'])) : '';
        $confirmedEmail = isset($_POST['confirm_email']) && is_scalar($_POST['confirm_email']) ? sanitize_email(wp_unslash((string) $_POST['confirm_email'])) : '';
        $nonce = isset($_POST['_petshop_account_nonce']) && is_scalar($_POST['_petshop_account_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_petshop_account_nonce'])) : '';
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || !wp_verify_nonce($nonce, 'petshop_create_order_account_' . $orderId) || !self::requestMatchesOrder($order, $key, $confirmedEmail)) wp_die(esc_html__('Não foi possível validar o pedido.', 'petshop-core'), '', ['response' => 403]);
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
        $userId = wc_create_new_customer($email, '', '', ['first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name()]);
        if (is_wp_error($userId)) wp_die(esc_html($userId->get_error_message()), '', ['response' => 400]);
        try {
            $order->set_customer_id((int) $userId);
            $order->save();
        } catch (\Throwable $error) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $userId);
            wp_die(esc_html__('A conta não pôde ser vinculada ao pedido. Nenhuma conta foi mantida.', 'petshop-core'), '', ['response' => 500]);
        }
        wp_safe_redirect(add_query_arg('account', 'created', $redirect));
        exit;
    }

    public static function requestMatchesOrder(\WC_Order $order, string $orderKey, string $confirmedEmail): bool
    {
        $billingEmail = sanitize_email($order->get_billing_email());
        return $order->has_status(['pending', 'processing', 'on-hold', 'completed'])
            && $orderKey !== ''
            && hash_equals($order->get_order_key(), $orderKey)
            && $billingEmail !== ''
            && strtolower($billingEmail) === strtolower(sanitize_email($confirmedEmail));
    }
}
