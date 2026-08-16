<?php

declare(strict_types=1);

use Petshop\Core\StorefrontExperience;
use Petshop\Core\Migration\HomeMigrator;
use Petshop\Core\Settings\DefaultSettings;
use Petshop\Core\Storefront\CatalogFilter;
use Petshop\Core\WooCommerce\Routes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontExperienceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['petshop_categories']);
        unset($_GET['product_cat'], $_GET['filter_pa_color'], $_GET['filter_pa_size'], $_GET['stock_status']);
    }

    #[DataProvider('categoryQueryProvider')]
    public function testSelectedCatalogCategorySlugsAreSanitized(mixed $queryValue, array $expected): void
    {
        $_GET['petshop_categories'] = $queryValue;
        $method = new ReflectionMethod(StorefrontExperience::class, 'selectedCatalogCategorySlugs');

        self::assertSame($expected, $method->invoke(null));
    }

    public static function categoryQueryProvider(): iterable
    {
        yield 'comma-separated slugs' => ['Conjuntos, gravatas,conjuntos', ['conjuntos', 'gravatas']];
        yield 'multiple query values' => [['Lacos', 'kits economicos,'], ['lacos', 'kits-economicos']];
        yield 'empty values' => [', ,', []];
    }

    public function testCatalogFilterAddsSelectedCategoriesToMainProductArchive(): void
    {
        $_GET['petshop_categories'] = 'conjuntos,gravatas';
        $query = new WP_Query([
            'main_query' => true,
            'post_type_archive' => 'product',
            'tax_query' => [['taxonomy' => 'visibility']],
        ]);

        StorefrontExperience::applyCatalogCategoryFilter($query);

        self::assertSame(
            [
                ['taxonomy' => 'visibility'],
                'relation' => 'AND',
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => ['conjuntos', 'gravatas'],
                    'operator' => 'IN',
                    'include_children' => true,
                ],
            ],
            $query->get('tax_query')
        );
    }

    public function testCatalogFilterCombinesAttributesWithAndAndTermsWithIn(): void
    {
        $_GET['product_cat'] = ['bandanas', 'lacos'];
        $_GET['filter_pa_color'] = 'azul,verde';
        $_GET['filter_pa_size'] = 'p';
        $_GET['stock_status'] = 'instock';
        $query = new WP_Query(['main_query' => true, 'post_type_archive' => 'product']);

        CatalogFilter::applyCatalogCategoryFilter($query);

        $taxQuery = $query->get('tax_query');
        self::assertSame('AND', $taxQuery['relation']);
        self::assertSame(['bandanas', 'lacos'], $taxQuery[0]['terms']);
        self::assertSame('IN', $taxQuery[1]['operator']);
        self::assertSame(['azul', 'verde'], $taxQuery[1]['terms']);
        self::assertSame('pa_size', $taxQuery[2]['taxonomy']);
        self::assertSame('_stock_status', $query->get('meta_query')[0]['key']);
    }

    public function testCanonicalCatalogParametersDropUnknownAndInvalidValues(): void
    {
        $parameters = CatalogFilter::canonicalParametersFromRequest([
            'petshop_categories' => 'Laços, Bandanas,laços',
            'filter_pa_color' => ['Azul', ''],
            'stock_status' => 'invalid',
            'orderby' => 'price',
            'unknown' => 'discard-me',
        ]);

        self::assertSame([
            'product_cat' => ['lacos', 'bandanas'],
            'filter_pa_color' => 'azul',
            'orderby' => 'price',
        ], $parameters);
    }

    public function testLocalizedWooCommerceRouteRegistryIsDeterministic(): void
    {
        self::assertSame([
            'shop' => 'loja',
            'cart' => 'carrinho',
            'checkout' => 'finalizar-compra',
            'my-account' => 'minha-conta',
        ], Routes::slugs());
    }

    public function testExactSkuSearchConstrainsThePostId(): void
    {
        $query = new WP_Query(['_petshop_exact_sku_product_id' => 42]);

        self::assertSame(' AND wp_posts.ID = 42', StorefrontExperience::filterExactSkuSearch('unused', $query));
    }

    public function testExactSkuSearchLeavesNormalSearchUntouched(): void
    {
        $query = new WP_Query(['_petshop_exact_sku_product_id' => 0]);

        self::assertSame(' AND post_title LIKE %s', StorefrontExperience::filterExactSkuSearch(' AND post_title LIKE %s', $query));
    }

    public function testExactSkuSearchResolvesAProductSku(): void
    {
        $GLOBALS['petshop_test_skus'] = ['SKU-42' => 42];
        $GLOBALS['petshop_test_products'] = [42 => new PetshopTestProduct()];
        $query = new WP_Query(['main_query' => true, 'search' => true, 'post_type' => 'product', 's' => 'SKU-42']);

        StorefrontExperience::resolveExactSkuSearch($query);

        self::assertSame(42, $query->get('_petshop_exact_sku_product_id'));
        self::assertSame(1, $query->get('posts_per_page'));
    }

    public function testExactSkuSearchUsesTheParentForVariationSkus(): void
    {
        $GLOBALS['petshop_test_skus'] = ['VAR-42' => 43];
        $GLOBALS['petshop_test_products'] = [43 => new PetshopTestProduct(true, 42)];
        $query = new WP_Query(['main_query' => true, 'search' => true, 'post_type' => 'product', 's' => 'VAR-42']);

        StorefrontExperience::resolveExactSkuSearch($query);

        self::assertSame(42, $query->get('_petshop_exact_sku_product_id'));
    }

    public function testHomeMigratorExposesTheCanonicalSchemaRegistry(): void
    {
        self::assertSame(26, HomeMigrator::currentSchema());
        self::assertSame(range(7, 26), array_keys(HomeMigrator::registry()));
        self::assertNotContains(false, array_map('is_callable', HomeMigrator::registry()));
        $showcase = '[petshop_product_showcase title="Sentinela"]';
        self::assertSame($showcase, HomeMigrator::registry()[17]($showcase, '', '', 0));
    }

    public function testCustomizerDefaultsHaveASinglePluginOwnedSource(): void
    {
        self::assertSame('Atendimento', DefaultSettings::get('petshop_support_label'));
        self::assertSame('Compra segura', DefaultSettings::get('petshop_checkout_assurance_text'));
        self::assertSame('Antes de adicionar ao carrinho', DefaultSettings::get('petshop_product_assurance_title'));
    }

    public function testCatalogFilterDoesNotUseAStaticLayoutFlag(): void
    {
        self::assertFalse((new ReflectionClass(CatalogFilter::class))->hasProperty('catalogLayoutOpen'));
    }

    public function testReviewsSectionDelegatesToTheExtractedShortcodeRenderer(): void
    {
        self::assertSame('', StorefrontExperience::renderReviewsSection(['limit' => 2]));
    }
}
