<?php

declare(strict_types=1);

use Petshop\Core\WooCommerce\MercadoPagoReturn;
use PHPUnit\Framework\TestCase;

final class MercadoPagoReturnTest extends TestCase
{
    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    protected function setUp(): void
    {
        $GLOBALS['petshop_test_filters'] = [];
        $GLOBALS['petshop_test_orders'] = [];
        $GLOBALS['petshop_test_logged_in'] = false;
        $GLOBALS['petshop_test_query_vars'] = [];
        $GLOBALS['petshop_test_home_url'] = 'https://store.test';
        $GLOBALS['petshop_test_wc'] = new WooCommerce();
        WC()->session = new PetshopTestSession();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testBootstrapRegistersOnlyPublicWooCommerceAndWordPressHooks(): void
    {
        MercadoPagoReturn::bootstrap();

        self::assertSame(
            [MercadoPagoReturn::class, 'handlePaymentSuccessfulResult'],
            $GLOBALS['petshop_test_filters']['woocommerce_payment_successful_result'][0]['callback']
        );
        self::assertSame(2, $GLOBALS['petshop_test_filters']['woocommerce_payment_successful_result'][0]['accepted_args']);
        self::assertSame(
            [MercadoPagoReturn::class, 'handleStoreApiCheckoutOrderProcessed'],
            $GLOBALS['petshop_test_filters']['woocommerce_store_api_checkout_order_processed'][0]['callback']
        );
        self::assertSame(
            [MercadoPagoReturn::class, 'handleClassicCheckoutOrderProcessed'],
            $GLOBALS['petshop_test_filters']['woocommerce_checkout_order_processed'][0]['callback']
        );
        self::assertSame(3, $GLOBALS['petshop_test_filters']['woocommerce_checkout_order_processed'][0]['accepted_args']);
        self::assertArrayNotHasKey('woocommerce_available_payment_gateways', $GLOBALS['petshop_test_filters']);
        self::assertSame(
            [MercadoPagoReturn::class, 'markUncacheableReturnRequest'],
            $GLOBALS['petshop_test_filters']['wp'][0]['callback']
        );
        self::assertSame(
            [MercadoPagoReturn::class, 'handleReturnRequest'],
            $GLOBALS['petshop_test_filters']['template_redirect'][0]['callback']
        );
    }

    public function testPaymentSuccessfulResultArmsSessionForMercadoPagoOrder(): void
    {
        $GLOBALS['petshop_test_orders'][10] = new WC_Order(10, MercadoPagoReturn::GATEWAY_ID);

        $result = MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success', 'redirect' => 'https://mp.test'], 10);

        self::assertSame(['result' => 'success', 'redirect' => 'https://mp.test'], $result);
        self::assertSame(10, MercadoPagoReturn::sessionState()['order_id']);
        self::assertSame(MercadoPagoReturn::GATEWAY_ID, MercadoPagoReturn::sessionState()['gateway']);
        self::assertFalse(MercadoPagoReturn::sessionState()['ambiguous']);
        self::assertTrue(WC()->session->cookieSet);
        self::assertSame(1, WC()->session->saveCount);
    }

    public function testPaymentSuccessfulResultIgnoresOtherGatewaysAndFailureResult(): void
    {
        $GLOBALS['petshop_test_orders'][10] = new WC_Order(10, 'cod');
        $GLOBALS['petshop_test_orders'][11] = new WC_Order(11, MercadoPagoReturn::GATEWAY_ID);

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 10);
        self::assertNull(MercadoPagoReturn::sessionState());

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'failure'], 11);
        self::assertNull(MercadoPagoReturn::sessionState());
    }

    public function testSameOrderRenewsExpiredStateIsReplacedAndSecondActiveOrderBecomesAmbiguous(): void
    {
        $GLOBALS['petshop_test_orders'][10] = new WC_Order(10, MercadoPagoReturn::GATEWAY_ID);
        $GLOBALS['petshop_test_orders'][11] = new WC_Order(11, MercadoPagoReturn::GATEWAY_ID);

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 10);
        $first = MercadoPagoReturn::sessionState();
        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 10);
        self::assertSame(10, MercadoPagoReturn::sessionState()['order_id']);
        self::assertFalse(MercadoPagoReturn::sessionState()['ambiguous']);
        self::assertSame($first['token'], MercadoPagoReturn::sessionState()['token']);
        self::assertGreaterThanOrEqual($first['created_at'], MercadoPagoReturn::sessionState()['created_at']);

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 10,
            'created_at' => time() - 8000,
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => false,
            'token' => self::TOKEN,
        ]);
        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 11);
        self::assertSame(11, MercadoPagoReturn::sessionState()['order_id']);
        self::assertFalse(MercadoPagoReturn::sessionState()['ambiguous']);

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 10);
        self::assertSame(10, MercadoPagoReturn::sessionState()['order_id']);
        self::assertTrue(MercadoPagoReturn::sessionState()['ambiguous']);
    }

    public function testAmbiguousStateCannotBecomeTrustedAgainUntilConsumedOrExpired(): void
    {
        $GLOBALS['petshop_test_orders'][10] = new WC_Order(10, MercadoPagoReturn::GATEWAY_ID);
        $GLOBALS['petshop_test_orders'][11] = new WC_Order(11, MercadoPagoReturn::GATEWAY_ID);

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 10);
        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 11);
        self::assertSame(11, MercadoPagoReturn::sessionState()['order_id']);
        self::assertTrue(MercadoPagoReturn::sessionState()['ambiguous']);

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 11);
        self::assertSame(11, MercadoPagoReturn::sessionState()['order_id']);
        self::assertTrue(MercadoPagoReturn::sessionState()['ambiguous']);

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 11,
            'created_at' => time() - 8000,
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => true,
            'token' => self::TOKEN,
        ]);
        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 11);
        self::assertSame(11, MercadoPagoReturn::sessionState()['order_id']);
        self::assertFalse(MercadoPagoReturn::sessionState()['ambiguous']);
    }

    public function testStoreApiProcessedOrderUsesTheSameSessionContract(): void
    {
        MercadoPagoReturn::handleStoreApiCheckoutOrderProcessed(new WC_Order(20, MercadoPagoReturn::GATEWAY_ID));
        self::assertSame(20, MercadoPagoReturn::sessionState()['order_id']);

        WC()->session = new PetshopTestSession();
        MercadoPagoReturn::handleStoreApiCheckoutOrderProcessed(new WC_Order(21, 'cod'));
        self::assertNull(MercadoPagoReturn::sessionState());
    }

    public function testSuccessAndPendingRedirectLoggedUsersToOrdersAndGuestsToOrderReceived(): void
    {
        $GLOBALS['petshop_test_orders'][30] = new WC_Order(30, MercadoPagoReturn::GATEWAY_ID);
        $this->authorizeReturn($GLOBALS['petshop_test_orders'][30]);
        $GLOBALS['petshop_test_logged_in'] = true;
        self::assertSame('https://store.test/minha-conta/orders/', MercadoPagoReturn::processReturn('success'));
        self::assertNull(WC()->session->get(MercadoPagoReturn::SESSION_KEY));

        $GLOBALS['petshop_test_logged_in'] = false;
        $this->authorizeReturn($GLOBALS['petshop_test_orders'][30]);
        self::assertSame(
            'https://store.test/checkout/order-received/30/?key=wc_order_30',
            MercadoPagoReturn::processReturn('pending')
        );
    }

    public function testFailureRedirectsToSameOrderPaymentUrlOnlyWhenOrderNeedsPayment(): void
    {
        $payable = new WC_Order(40, MercadoPagoReturn::GATEWAY_ID, true);
        $paid = new WC_Order(41, MercadoPagoReturn::GATEWAY_ID, false);
        $GLOBALS['petshop_test_orders'][40] = $payable;
        $GLOBALS['petshop_test_orders'][41] = $paid;

        $this->authorizeReturn($payable);
        self::assertSame(
            'https://store.test/checkout/order-pay/40/?pay_for_order=true&key=wc_order_40',
            MercadoPagoReturn::processReturn('failure')
        );

        $this->authorizeReturn($paid);
        self::assertSame(
            'https://store.test/checkout/order-received/41/?key=wc_order_41',
            MercadoPagoReturn::processReturn('failure')
        );

        $this->authorizeReturn($paid);
        $GLOBALS['petshop_test_logged_in'] = true;
        self::assertSame('https://store.test/minha-conta/orders/', MercadoPagoReturn::processReturn('failure'));
    }

    public function testFailureConsumesSessionAndOrderPayRetryRearmsTheSameOrder(): void
    {
        $payable = new WC_Order(40, MercadoPagoReturn::GATEWAY_ID, true);
        $GLOBALS['petshop_test_orders'][40] = $payable;

        $this->authorizeReturn($payable);
        self::assertSame(
            'https://store.test/checkout/order-pay/40/?pay_for_order=true&key=wc_order_40',
            MercadoPagoReturn::processReturn('failure')
        );
        self::assertNull(WC()->session->get(MercadoPagoReturn::SESSION_KEY));

        MercadoPagoReturn::handlePaymentSuccessfulResult(['result' => 'success'], 40);
        self::assertSame(40, MercadoPagoReturn::sessionState()['order_id']);
        self::assertFalse(MercadoPagoReturn::sessionState()['ambiguous']);
    }

    public function testInvalidSessionStatesFailClosedWithoutUsingMercadoPagoGetParameters(): void
    {
        $_GET['order_id'] = '50';
        $_GET['external_reference'] = 'store50';
        $_GET['payment_id'] = 'fake-payment';
        $_GET[MercadoPagoReturn::TOKEN_QUERY_ARG] = self::TOKEN;
        $GLOBALS['petshop_test_orders'][50] = new WC_Order(50, MercadoPagoReturn::GATEWAY_ID);

        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 50,
            'created_at' => time() - 8000,
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => false,
            'token' => self::TOKEN,
        ]);
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 50,
            'created_at' => time(),
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => true,
            'token' => self::TOKEN,
        ]);
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 999,
            'created_at' => time(),
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => false,
            'token' => self::TOKEN,
        ]);
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));

        WC()->session->set(MercadoPagoReturn::SESSION_KEY, [
            'order_id' => 51,
            'created_at' => time(),
            'gateway' => MercadoPagoReturn::GATEWAY_ID,
            'ambiguous' => false,
            'token' => self::TOKEN,
        ]);
        $GLOBALS['petshop_test_orders'][51] = new WC_Order(51, 'cod');
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));
    }

    public function testInvalidSessionForLoggedUserFallsBackOnlyToOrders(): void
    {
        $GLOBALS['petshop_test_logged_in'] = true;

        self::assertSame('https://store.test/minha-conta/orders/', MercadoPagoReturn::processReturn('success'));
    }

    public function testMalformedReturnQueryInputIsIgnored(): void
    {
        $method = new ReflectionMethod(MercadoPagoReturn::class, 'currentReturnType');

        $GLOBALS['petshop_test_query_vars'][MercadoPagoReturn::QUERY_VAR] = ['success'];
        self::assertNull($method->invoke(null));

        $GLOBALS['petshop_test_query_vars'] = [];
        $_GET[MercadoPagoReturn::QUERY_VAR] = ['success'];
        self::assertNull($method->invoke(null));
    }

    public function testSharedReturnWithoutMatchingTokenDoesNotExposeOrderKey(): void
    {
        $order = new WC_Order(30, MercadoPagoReturn::GATEWAY_ID);
        $GLOBALS['petshop_test_orders'][30] = $order;
        MercadoPagoReturn::armReturnSession($order);

        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('success'));

        MercadoPagoReturn::armReturnSession($order);
        $_GET[MercadoPagoReturn::TOKEN_QUERY_ARG] = str_repeat('b', 32);
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('pending'));

        MercadoPagoReturn::armReturnSession($order);
        $_GET[MercadoPagoReturn::TOKEN_QUERY_ARG] = ['not-a-token'];
        self::assertSame('https://store.test/', MercadoPagoReturn::processReturn('failure'));
    }

    public function testPrepareCheckoutReturnWritesPerPaymentBackUrlsWithoutRemovingGateway(): void
    {
        $gateway = new PetshopTestGateway([
            'enabled' => 'yes',
            'auto_return' => 'no',
            'success_url' => '',
        ]);
        WC()->gateways = [
            MercadoPagoReturn::GATEWAY_ID => $gateway,
            'cod' => new PetshopTestGateway(['enabled' => 'yes']),
        ];
        $GLOBALS['petshop_test_orders'][70] = new WC_Order(70, MercadoPagoReturn::GATEWAY_ID);

        MercadoPagoReturn::prepareCheckoutReturn($GLOBALS['petshop_test_orders'][70]);

        $token = MercadoPagoReturn::sessionState()['token'];
        self::assertSame('yes', $gateway->settings['auto_return']);
        self::assertSame('yes', $gateway->settings['enabled']);
        self::assertSame(MercadoPagoReturn::returnUrl('success', $token), $gateway->settings['success_url']);
        self::assertSame(MercadoPagoReturn::returnUrl('pending', $token), $gateway->settings['pending_url']);
        self::assertSame(MercadoPagoReturn::returnUrl('failure', $token), $gateway->settings['failure_url']);
        self::assertSame('yes', WC()->gateways['cod']->settings['enabled']);

        $GLOBALS['petshop_test_orders'][71] = new WC_Order(71, MercadoPagoReturn::GATEWAY_ID);
        MercadoPagoReturn::prepareCheckoutReturn($GLOBALS['petshop_test_orders'][71]);
        $secondToken = MercadoPagoReturn::sessionState()['token'];
        self::assertNotSame($token, $secondToken);
        self::assertSame(MercadoPagoReturn::returnUrl('success', $secondToken), $gateway->settings['success_url']);
    }

    public function testPreferenceSettingsStayUntouchedOnHttpLocalhostAndLoopback(): void
    {
        foreach (['http://store.test', 'https://localhost', 'https://127.0.0.1', 'https://[::1]'] as $homeUrl) {
            $GLOBALS['petshop_test_home_url'] = $homeUrl;
            $gateway = new PetshopTestGateway([
                'enabled' => 'yes',
                'auto_return' => 'no',
                'success_url' => '',
            ]);

            self::assertFalse(MercadoPagoReturn::applyPreferenceReturnSettings($gateway, self::TOKEN));
            self::assertSame('no', $gateway->settings['auto_return']);
            self::assertSame('', $gateway->settings['success_url']);
            self::assertArrayNotHasKey('pending_url', $gateway->settings);
            self::assertArrayNotHasKey('failure_url', $gateway->settings);
        }
    }

    public function testPreferenceSettingsLoadExistingGatewayOptionsBeforeWritingBackUrls(): void
    {
        $gateway = new class {
            /** @var array<string, string> */
            public array $settings = [];

            public function init_settings(): void
            {
                $this->settings = ['enabled' => 'yes'];
            }
        };

        self::assertTrue(MercadoPagoReturn::applyPreferenceReturnSettings($gateway, self::TOKEN));
        self::assertSame('yes', $gateway->settings['enabled']);
        self::assertSame('yes', $gateway->settings['auto_return']);
        self::assertSame(MercadoPagoReturn::returnUrl('success', self::TOKEN), $gateway->settings['success_url']);
    }

    public function testReturnRequestIsMarkedUncacheable(): void
    {
        $_GET[MercadoPagoReturn::QUERY_VAR] = 'success';

        MercadoPagoReturn::markUncacheableReturnRequest();

        self::assertTrue(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE);
        self::assertTrue($GLOBALS['petshop_test_nocache_headers'] ?? false);
    }

    public function testRegisterQueryVarAndReturnUrlsAreDeterministic(): void
    {
        self::assertContains(MercadoPagoReturn::QUERY_VAR, MercadoPagoReturn::registerQueryVar([]));
        self::assertSame('https://store.test/?petshop_mp_return=success', MercadoPagoReturn::returnUrl('success'));
        self::assertSame('https://store.test/?petshop_mp_return=pending', MercadoPagoReturn::returnUrl('pending'));
        self::assertSame('https://store.test/?petshop_mp_return=failure', MercadoPagoReturn::returnUrl('failure'));
        self::assertSame(
            'https://store.test/?petshop_mp_return=success&petshop_mp_token=' . self::TOKEN,
            MercadoPagoReturn::returnUrl('success', self::TOKEN)
        );
    }

    private function authorizeReturn(WC_Order $order): void
    {
        MercadoPagoReturn::armReturnSession($order);
        $_GET[MercadoPagoReturn::TOKEN_QUERY_ARG] = MercadoPagoReturn::sessionState()['token'];
    }
}

final class PetshopTestSession
{
    /** @var array<string, mixed> */
    private array $values = [];
    public bool $cookieSet = false;
    public int $saveCount = 0;

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function set_customer_session_cookie(bool $set): void
    {
        $this->cookieSet = $set;
    }

    public function save_data(): void
    {
        $this->saveCount++;
    }
}

final class PetshopTestGateway
{
    /** @param array<string, string> $settings */
    public function __construct(public array $settings)
    {
    }

    public function get_option(string $key): string
    {
        return $this->settings[$key] ?? '';
    }
}
