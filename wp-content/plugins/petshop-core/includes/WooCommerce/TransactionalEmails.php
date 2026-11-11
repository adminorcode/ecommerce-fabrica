<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

use Petshop\Core\Settings\DefaultSettings;

defined('ABSPATH') || exit;

final class TransactionalEmails
{
    private const DEFAULTS_OPTION = 'petshop_email_layout_defaults_version';
    private const TRACKING_META_CARRIER = '_petshop_tracking_carrier';
    private const TRACKING_META_CODE = '_petshop_tracking_code';
    private const TRACKING_META_URL = '_petshop_tracking_url';

    private static bool $renderingHtml = false;

    /** @var array<string, array{heading:string, tracker:int, payment:bool}> */
    private const EMAILS = [
        'customer_processing_order' => ['heading' => 'Pagamento confirmado!', 'tracker' => 1, 'payment' => false],
        'customer_completed_order' => ['heading' => 'Pedido concluído!', 'tracker' => 4, 'payment' => false],
        'customer_on_hold_order' => ['heading' => 'Recebemos o seu pedido', 'tracker' => 0, 'payment' => true],
        'customer_invoice' => ['heading' => 'Fatura do pedido', 'tracker' => 0, 'payment' => true],
        'customer_cancelled_order' => ['heading' => 'Pedido cancelado', 'tracker' => 0, 'payment' => false],
        'customer_refunded_order' => ['heading' => 'Reembolso confirmado', 'tracker' => 0, 'payment' => false],
        'customer_failed_order' => ['heading' => 'Não foi possível confirmar o pagamento', 'tracker' => 0, 'payment' => false],
        'customer_note' => ['heading' => 'Atualização do seu pedido', 'tracker' => 0, 'payment' => false],
    ];

    public static function bootstrap(): void
    {
        add_action('woocommerce_init', [self::class, 'ensureEmailDefaults']);
        add_action('woocommerce_init', [self::class, 'registerAdditionalContentFilters']);
        add_action('woocommerce_email_header', [self::class, 'renderHeader'], 1, 2);
        add_action('woocommerce_email_footer', [self::class, 'renderFooter'], 1, 1);
        add_action('woocommerce_email_order_details', [self::class, 'renderOrderDetails'], 1, 4);
        add_action('woocommerce_email_customer_details', [self::class, 'suppressCustomerDetails'], 1, 4);
        add_filter('woocommerce_email_styles', [self::class, 'emailStyles'], 20, 2);

    }

    public static function registerAdditionalContentFilters(): void
    {
        foreach (WC()->mailer()->get_emails() as $email) {
            if (isset($email->id) && is_string($email->id)) {
                add_filter('woocommerce_email_additional_content_' . $email->id, [self::class, 'suppressAdditionalContent'], 20);
            }
        }
    }

    public static function ensureEmailDefaults(): void
    {
        if ((int) get_option(self::DEFAULTS_OPTION, 0) >= 1) {
            return;
        }

        foreach (self::EMAILS as $emailId => $config) {
            $option = 'woocommerce_' . $emailId . '_settings';
            $settings = get_option($option, []);
            $settings = is_array($settings) ? $settings : [];
            if (empty($settings['heading']) || !is_string($settings['heading'])) {
                $settings['heading'] = $config['heading'];
            }
            if (!array_key_exists('additional_content', $settings)) {
                $settings['additional_content'] = __('Confira abaixo o resumo e os próximos detalhes do seu pedido.', 'petshop-core');
            }
            update_option($option, $settings, false);
        }

        update_option(self::DEFAULTS_OPTION, 1, false);
    }

    public static function renderHeader(string $emailHeading, mixed $email): void
    {
        if (!self::isWooCommerceEmail($email) || self::isPlainEmailRender()) {
            return;
        }

        self::removeMailerCallback('woocommerce_email_header', 'email_header', 10);
        add_action('woocommerce_email_header', [self::class, 'restoreHeader'], PHP_INT_MAX);
        self::$renderingHtml = true;

        $order = self::emailOrder($email);
        $storeName = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $firstName = $order instanceof \WC_Order ? trim($order->get_billing_first_name()) : '';
        $greeting = $firstName === '' ? __('Olá!', 'petshop-core') : sprintf(__('Olá, %s!', 'petshop-core'), $firstName);
        $additional = self::additionalContent($email);

        echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta http-equiv="Content-Type" content="text/html; charset=' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . esc_html($storeName) . '</title></head>';
        echo '<body style="margin:0;padding:0;background:#F2F3F4;font-family:\'Nunito Sans\',Arial,Helvetica,sans-serif;color:#252426;">';
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F2F3F4;margin:0;padding:24px 12px;"><tr><td align="center">';
        echo '<table role="presentation" class="petshop-email-shell" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;background:#FFFFFF;border-collapse:collapse;border-top:6px solid #126E70;">';
        echo '<tr><td align="center" style="padding:28px 32px 16px;">' . self::logoHtml($storeName) . '</td></tr>';
        echo '<tr><td style="padding:0 40px 8px;text-align:center;"><p style="margin:0 0 10px;color:#126E70;font-size:20px;line-height:1.35;font-weight:700;">' . esc_html($greeting) . '</p>';
        echo '<h1 style="margin:0;color:#252426;font-size:30px;line-height:1.2;font-weight:800;">' . esc_html($emailHeading) . '</h1>';
        if ($additional !== '') {
            echo '<div style="margin:14px 0 0;color:#5E5D61;font-size:16px;line-height:1.55;">' . wp_kses_post(wpautop(wptexturize($additional))) . '</div>';
        }
        echo '</td></tr>';
    }

    public static function restoreHeader(): void
    {
        self::restoreMailerCallback('woocommerce_email_header', 'email_header', 10, 1);
        remove_action('woocommerce_email_header', [self::class, 'restoreHeader'], PHP_INT_MAX);
    }

    public static function renderFooter(mixed $email): void
    {
        if (!self::isWooCommerceEmail($email) || self::isPlainEmailRender()) {
            return;
        }

        if (!self::$renderingHtml) {
            return;
        }

        self::removeMailerCallback('woocommerce_email_footer', 'email_footer', 10);
        add_action('woocommerce_email_footer', [self::class, 'restoreFooter'], PHP_INT_MAX);

        $storeName = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $tagline = self::themeMod('petshop_footer_description');
        echo '<tr><td style="padding:0 40px 36px;"><p style="margin:0;color:#5E5D61;font-size:14px;line-height:1.6;text-align:center;">' . esc_html__('Você também pode acompanhar tudo pela área Minha conta.', 'petshop-core') . '</p></td></tr>';
        echo '<tr><td class="petshop-email-footer" style="background:#004F50;padding:28px 40px;text-align:center;color:#FFFFFF;">';
        echo '<p style="margin:0 0 8px;font-size:18px;line-height:1.3;font-weight:800;color:#FFFFFF;">' . esc_html($storeName) . '</p>';
        if ($tagline !== '') {
            echo '<p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#FFFFFF;">' . esc_html($tagline) . '</p>';
        }
        echo '<p style="margin:0;font-size:13px;line-height:1.5;color:#FFFFFF;"><a href="' . esc_url(home_url('/')) . '" style="color:#FFFFFF;text-decoration:none;">' . esc_html(home_url('/')) . '</a></p>';
        echo '</td></tr></table></td></tr></table></body></html>';
    }

    public static function restoreFooter(): void
    {
        self::$renderingHtml = false;
        self::restoreMailerCallback('woocommerce_email_footer', 'email_footer', 10, 1);
        remove_action('woocommerce_email_footer', [self::class, 'restoreFooter'], PHP_INT_MAX);
    }

    public static function suppressAdditionalContent(string $content): string
    {
        return self::$renderingHtml ? '' : $content;
    }

    public static function renderOrderDetails(\WC_Order $order, bool $sentToAdmin, bool $plainText, mixed $email): void
    {
        if ($sentToAdmin || $plainText || !self::isOrderLayoutEmail($email)) {
            return;
        }

        self::removeMailerCallback('woocommerce_email_order_details', 'order_details', 10);
        self::removeMailerCallback('woocommerce_email_order_details', 'order_schema_markup', 20);
        add_action('woocommerce_email_order_details', [self::class, 'restoreOrderDetails'], PHP_INT_MAX);

        echo '<tr><td style="padding:24px 40px 0;">';
        self::renderStatusBox($order);
        self::renderSummary($order);
        self::renderTracking($order);
        $tracker = self::EMAILS[$email->id]['tracker'] ?? 0;
        if ($tracker > 0) {
            self::renderTracker((int) $tracker);
        }
        self::renderCta($order, (string) $email->id);
        self::renderInfoBox();
        self::renderHelpBox();
        echo '</td></tr>';
    }

    public static function restoreOrderDetails(): void
    {
        self::restoreMailerCallback('woocommerce_email_order_details', 'order_details', 10, 4);
        self::restoreMailerCallback('woocommerce_email_order_details', 'order_schema_markup', 20, 4);
        remove_action('woocommerce_email_order_details', [self::class, 'restoreOrderDetails'], PHP_INT_MAX);
    }

    public static function suppressCustomerDetails(\WC_Order $order, bool $sentToAdmin, bool $plainText, mixed $email): void
    {
        unset($order, $sentToAdmin);
        if ($plainText || !self::isOrderLayoutEmail($email) || !self::$renderingHtml) {
            return;
        }
        self::removeMailerCallback('woocommerce_email_customer_details', 'customer_details', 10);
        self::removeMailerCallback('woocommerce_email_customer_details', 'email_addresses', 20);
        add_action('woocommerce_email_customer_details', [self::class, 'restoreCustomerDetails'], PHP_INT_MAX);
    }

    public static function restoreCustomerDetails(): void
    {
        self::restoreMailerCallback('woocommerce_email_customer_details', 'customer_details', 10, 3);
        self::restoreMailerCallback('woocommerce_email_customer_details', 'email_addresses', 20, 3);
        remove_action('woocommerce_email_customer_details', [self::class, 'restoreCustomerDetails'], PHP_INT_MAX);
    }

    public static function emailStyles(string $css, mixed $email): string
    {
        if (!self::isWooCommerceEmail($email)) {
            return $css;
        }
        return $css . "\n" . '.petshop-email-shell{font-family:"Nunito Sans",Arial,Helvetica,sans-serif}.petshop-email-shell a{color:#126E70}.petshop-email-shell .petshop-email-cta{background:#C94B0B;color:#FFFFFF!important}.petshop-email-total{color:#F47721}.petshop-email-footer{background:#004F50;color:#FFFFFF}';
    }

    private static function renderStatusBox(\WC_Order $order): void
    {
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #D8D9DB;background:#F2FBFC;border-radius:16px;margin:0 0 24px;"><tr>';
        echo '<td width="48" valign="top" style="padding:18px 0 18px 20px;color:#126E70;font-size:28px;line-height:1;">&#10003;</td>';
        echo '<td style="padding:18px 20px 18px 12px;"><p style="margin:0 0 4px;color:#252426;font-size:18px;line-height:1.35;font-weight:800;">' . sprintf(esc_html__('Pedido #%s', 'petshop-core'), esc_html($order->get_order_number())) . '</p>';
        echo '<p style="margin:0;color:#5E5D61;font-size:14px;line-height:1.5;">' . esc_html(wc_format_datetime($order->get_date_created())) . '</p></td></tr></table>';
    }

    private static function renderSummary(\WC_Order $order): void
    {
        echo '<h2 style="margin:0 0 14px;color:#252426;font-size:22px;line-height:1.25;font-weight:800;">' . esc_html__('Resumo do pedido', 'petshop-core') . '</h2>';
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin:0 0 20px;">';
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            echo '<tr><td style="padding:12px 0;border-bottom:1px solid #D8D9DB;color:#252426;font-size:15px;line-height:1.45;">';
            echo esc_html($item->get_name()) . '<br><span style="color:#5E5D61;">' . sprintf(esc_html__('Qtd. %d', 'petshop-core'), (int) $item->get_quantity()) . '</span>';
            $meta = wc_display_item_meta($item, ['echo' => false]);
            if (is_string($meta) && $meta !== '') {
                echo '<div style="color:#5E5D61;font-size:13px;">' . wp_kses_post($meta) . '</div>';
            }
            echo '</td><td align="right" style="padding:12px 0;border-bottom:1px solid #D8D9DB;color:#252426;font-size:15px;line-height:1.45;">' . wp_kses_post($order->get_formatted_line_subtotal($item)) . '</td></tr>';
        }
        self::summaryRow(__('Subtotal', 'petshop-core'), $order->get_subtotal_to_display());
        self::summaryRow(__('Entrega', 'petshop-core'), $order->get_shipping_to_display());
        if ($order->get_payment_method_title() !== '') {
            self::summaryRow(__('Pagamento', 'petshop-core'), esc_html($order->get_payment_method_title()));
        }
        echo '<tr><td style="padding:14px 0 0;color:#252426;font-size:18px;font-weight:800;">' . esc_html__('Total', 'petshop-core') . '</td><td align="right" class="petshop-email-total" style="padding:14px 0 0;color:#F47721;font-size:20px;font-weight:800;">' . wp_kses_post($order->get_formatted_order_total()) . '</td></tr>';
        echo '</table>';
    }

    private static function summaryRow(string $label, string $value): void
    {
        echo '<tr><td style="padding:10px 0;color:#5E5D61;font-size:14px;border-bottom:1px solid #D8D9DB;">' . esc_html($label) . '</td><td align="right" style="padding:10px 0;color:#252426;font-size:14px;border-bottom:1px solid #D8D9DB;">' . wp_kses_post($value) . '</td></tr>';
    }

    private static function renderTracker(int $currentStep): void
    {
        $steps = [
            __('Pagamento confirmado', 'petshop-core'),
            __('Separação / produção', 'petshop-core'),
            __('Pedido enviado', 'petshop-core'),
            __('Pedido concluído', 'petshop-core'),
        ];
        echo '<h2 style="margin:26px 0 14px;color:#252426;font-size:22px;line-height:1.25;font-weight:800;">' . esc_html__('Próximos passos', 'petshop-core') . '</h2>';
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;"><tr>';
        foreach ($steps as $index => $label) {
            $step = $index + 1;
            $color = $step === $currentStep ? '#C94B0B' : ($step < $currentStep ? '#126E70' : '#D8D9DB');
            echo '<td align="center" width="25%" valign="top" style="padding:0 4px;color:#5E5D61;font-size:12px;line-height:1.35;">';
            echo '<div style="width:24px;height:24px;line-height:24px;margin:0 auto 8px;background:' . esc_attr($color) . ';color:#FFFFFF;border-radius:999px;font-weight:800;">' . esc_html((string) $step) . '</div>';
            echo esc_html($label) . '</td>';
        }
        echo '</tr></table>';
    }

    private static function renderCta(\WC_Order $order, string $emailId): void
    {
        $isPayment = self::EMAILS[$emailId]['payment'] ?? false;
        $label = $isPayment ? __('Pagar agora', 'petshop-core') : __('Acompanhar meu pedido', 'petshop-core');
        $url = $isPayment ? $order->get_checkout_payment_url() : self::viewOrderUrl($order);
        echo '<p style="margin:0 0 24px;text-align:center;"><a class="petshop-email-cta" href="' . esc_url($url) . '" style="display:inline-block;background:#C94B0B;color:#FFFFFF;text-decoration:none;border-radius:999px;padding:14px 24px;font-size:15px;line-height:1;font-weight:800;">' . esc_html($label) . '</a></p>';
    }

    private static function renderTracking(\WC_Order $order): void
    {
        $carrier = trim((string) $order->get_meta(self::TRACKING_META_CARRIER, true));
        $code = trim((string) $order->get_meta(self::TRACKING_META_CODE, true));
        $url = trim((string) $order->get_meta(self::TRACKING_META_URL, true));
        if ($carrier === '' && $code === '' && $url === '') {
            return;
        }

        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F2FBFC;border:1px solid #D8D9DB;margin:0 0 22px;"><tr><td style="padding:16px 18px;">';
        echo '<p style="margin:0 0 8px;color:#126E70;font-size:15px;font-weight:800;">' . esc_html__('Rastreamento da entrega', 'petshop-core') . '</p>';
        if ($carrier !== '') {
            echo '<p style="margin:0 0 4px;color:#5E5D61;font-size:14px;line-height:1.5;"><strong>' . esc_html__('Transportadora:', 'petshop-core') . '</strong> ' . esc_html($carrier) . '</p>';
        }
        if ($code !== '') {
            echo '<p style="margin:0 0 4px;color:#5E5D61;font-size:14px;line-height:1.5;"><strong>' . esc_html__('Código:', 'petshop-core') . '</strong> ' . esc_html($code) . '</p>';
        }
        if ($url !== '') {
            echo '<p style="margin:8px 0 0;font-size:14px;"><a href="' . esc_url($url) . '" style="color:#126E70;">' . esc_html__('Acompanhar entrega', 'petshop-core') . '</a></p>';
        }
        echo '</td></tr></table>';
    }

    private static function renderInfoBox(): void
    {
        $title = self::themeMod('petshop_email_info_title');
        $body = self::themeMod('petshop_email_info_text');
        if ($title === '' && $body === '') {
            return;
        }
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#FAF7F1;border:1px solid #D8D9DB;margin:0 0 18px;"><tr><td style="padding:16px 18px;">';
        if ($title !== '') {
            echo '<p style="margin:0 0 6px;color:#126E70;font-size:15px;font-weight:800;">' . esc_html($title) . '</p>';
        }
        if ($body !== '') {
            echo '<p style="margin:0;color:#5E5D61;font-size:14px;line-height:1.55;">' . nl2br(esc_html($body)) . '</p>';
        }
        echo '</td></tr></table>';
    }

    private static function renderHelpBox(): void
    {
        $body = self::themeMod('petshop_email_help_text');
        $whatsapp = self::themeMod('petshop_footer_whatsapp');
        $email = self::themeMod('petshop_footer_email');
        echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F2FBFC;border:1px solid #D8D9DB;margin:0 0 24px;"><tr><td style="padding:16px 18px;">';
        echo '<p style="margin:0 0 6px;color:#126E70;font-size:15px;font-weight:800;">' . esc_html__('Precisa de ajuda?', 'petshop-core') . '</p>';
        if ($body !== '') {
            echo '<p style="margin:0 0 10px;color:#5E5D61;font-size:14px;line-height:1.55;">' . nl2br(esc_html($body)) . '</p>';
        }
        if ($whatsapp !== '') {
            echo '<p style="margin:0 0 4px;font-size:14px;"><a href="' . esc_url($whatsapp) . '" style="color:#126E70;">' . esc_html__('Falar pelo WhatsApp', 'petshop-core') . '</a></p>';
        }
        if ($email !== '') {
            echo '<p style="margin:0;font-size:14px;"><a href="mailto:' . esc_attr($email) . '" style="color:#126E70;">' . esc_html($email) . '</a></p>';
        }
        echo '</td></tr></table>';
    }

    private static function logoHtml(string $storeName): string
    {
        $logoId = (int) get_theme_mod('custom_logo', 0);
        $logoUrl = $logoId > 0 ? wp_get_attachment_image_url($logoId, 'full') : false;
        if (is_string($logoUrl) && $logoUrl !== '') {
            $alt = trim((string) get_post_meta($logoId, '_wp_attachment_image_alt', true));
            return '<img src="' . esc_url($logoUrl) . '" width="180" alt="' . esc_attr($alt !== '' ? $alt : $storeName) . '" style="display:block;width:180px;max-width:180px;height:auto;border:0;">';
        }
        return '<p style="margin:0;color:#126E70;font-size:24px;line-height:1.2;font-weight:800;">' . esc_html($storeName) . '</p>';
    }

    private static function additionalContent(mixed $email): string
    {
        if (!is_object($email) || !method_exists($email, 'get_option')) {
            return '';
        }
        $content = (string) $email->get_option('additional_content', '');
        return method_exists($email, 'format_string') ? trim((string) $email->format_string($content)) : trim($content);
    }

    private static function emailOrder(mixed $email): ?\WC_Order
    {
        return is_object($email) && property_exists($email, 'object') && $email->object instanceof \WC_Order ? $email->object : null;
    }

    private static function viewOrderUrl(\WC_Order $order): string
    {
        if ($order->get_user_id() > 0) {
            return $order->get_view_order_url();
        }

        return $order->get_checkout_order_received_url();
    }

    private static function isWooCommerceEmail(mixed $email): bool
    {
        return $email instanceof \WC_Email && isset($email->id) && is_string($email->id) && $email->id !== '';
    }

    private static function isOrderLayoutEmail(mixed $email): bool
    {
        return is_object($email) && isset($email->id) && is_string($email->id) && array_key_exists($email->id, self::EMAILS);
    }

    private static function isPlainEmailRender(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = isset($frame['file']) && is_string($frame['file']) ? str_replace('\\', '/', $frame['file']) : '';
            if (str_contains($file, '/templates/emails/plain/') || str_contains($file, '/woocommerce/emails/plain/')) {
                return true;
            }
        }

        return false;
    }

    private static function themeMod(string $id): string
    {
        return trim((string) get_theme_mod($id, DefaultSettings::get($id)));
    }

    private static function removeMailerCallback(string $hook, string $method, int $priority): void
    {
        $mailer = WC()->mailer();
        if (is_object($mailer)) {
            remove_action($hook, [$mailer, $method], $priority);
        }
    }

    private static function restoreMailerCallback(string $hook, string $method, int $priority, int $acceptedArgs): void
    {
        $mailer = WC()->mailer();
        if (is_object($mailer) && !has_action($hook, [$mailer, $method])) {
            add_action($hook, [$mailer, $method], $priority, $acceptedArgs);
        }
    }
}
