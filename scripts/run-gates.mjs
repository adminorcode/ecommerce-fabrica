import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const isWindows = process.platform === 'win32';
const args = process.argv.slice(2);
const browser = args.includes('--browser');
const pdp = args.includes('--pdp');
const cart = args.includes('--cart');
const contentAudit = args.includes('--content-audit');
const skipProvision = args.includes('--skip-provision');
const changed = args.includes('--changed');

const run = (command, commandArgs, options = {}) => {
    const result = spawnSync(command, commandArgs, {
        cwd: root,
        stdio: options.capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
        encoding: 'utf8',
        env: { ...process.env, ...(options.env || {}) },
    });

    if ((result.status ?? 1) !== 0) {
        if (options.capture) {
            process.stderr.write(result.stderr || result.stdout || '');
        }
        throw new Error(`${command} ${commandArgs.join(' ')} falhou com codigo ${result.status ?? 1}`);
    }

    return options.capture ? (result.stdout || '').trim() : '';
};

const dockerCli = (...wpArgs) => run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', 'wp', ...wpArgs]);
const dockerCliOutput = (...wpArgs) => run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', 'wp', ...wpArgs], { capture: true });
const evalFile = (script) => dockerCli('eval-file', `/var/www/html/scripts/${script}`);

const changedFiles = () => {
    const tracked = run('git', ['diff', '--name-only', '--diff-filter=ACMRTUXB', 'HEAD'], { capture: true })
        .split(/\r?\n/)
        .filter(Boolean);
    const untracked = run('git', ['ls-files', '--others', '--exclude-standard'], { capture: true })
        .split(/\r?\n/)
        .filter(Boolean);

    return [...new Set([...tracked, ...untracked])]
        .filter((file) => !file.startsWith('.opencode/') && file !== 'opencode.json' && !file.startsWith('.local/'))
        .sort();
};

const containerPhpPath = (file) => {
    if (file.startsWith('wp-content/')) {
        return `/var/www/html/${file}`;
    }
    if (file.startsWith('scripts/')) {
        return `/var/www/html/${file}`;
    }

    return null;
};

const runtimePath = (file) => {
    if (file.startsWith('wp-content/plugins/petshop-core/') || file.startsWith('wp-content/themes/petshop-theme/') || file.startsWith('scripts/')) {
        return `/var/www/html/${file}`;
    }

    return null;
};

const syncChangedRuntimeFiles = (files) => {
    for (const file of files) {
        const destination = runtimePath(file);
        if (destination === null || !existsSync(join(root, file))) {
            continue;
        }

        run('docker', ['cp', file, `petshop-wordpress-1:${destination}`]);
    }
};

const lintChangedFiles = (files) => {
    for (const file of files) {
        if (file.endsWith('.mjs') || file.endsWith('.js')) {
            if (file.startsWith('scripts/') && existsSync(join(root, file))) {
                run('node', ['--check', file]);
            }
        }

        if (file.endsWith('.php')) {
            const phpPath = containerPhpPath(file);
            if (phpPath !== null) {
                run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', 'php', '-l', phpPath]);
            }
        }
    }
};

const runFocusedProvision = (suites) => {
    if (skipProvision) {
        return;
    }

    if (suites.has('storefront') || suites.has('commercial') || suites.has('product-grid') || suites.has('order-received-030')) {
        dockerCli('eval', 'Petshop\\Core\\StorefrontCatalog::maybeEnsureCategories();');
        dockerCli('eval', 'Petshop\\Core\\StorefrontExperience::maybeEnsureStorefront();');
    }

    if (suites.has('commercial')) {
        evalFile('seed-animal-republik-launches.php');
        evalFile('sync-commercial-page-catalog-links.php');
    }
};

const classifySuites = (files) => {
    const suites = new Set();
    const browserScripts = new Set();

    for (const file of files) {
        if (file === 'package.json' || file === 'scripts/run-gates.mjs' || file === 'scripts/run-gates.sh' || file === 'scripts/run-gates.ps1') {
            suites.add('runner');
        }
        if (file.startsWith('docs/') || file.startsWith('Plans/')) {
            suites.add('docs');
        }
        if (
            file.includes('ProductGridBlock.php')
            || file.startsWith('wp-content/plugins/petshop-core/blocks/product-grid/')
            || file.startsWith('wp-content/plugins/petshop-core/blocks/build/product-grid')
            || file.includes('validate-016-product-grid')
        ) {
            suites.add('product-grid');
            browserScripts.add('validate-016-product-grid-browser.mjs');
        }
        if (
            file.includes('StorefrontProvisioning.php')
            || file.includes('StorefrontProvisioner.php')
            || file.includes('class-storefront-experience.php')
        ) {
            suites.add('storefront');
        }
        if (
            file.includes('018-commercial')
            || file.includes('animal-republik')
            || file.includes('sync-commercial-page-catalog-links')
            || file.includes('commercial-animal-republik')
            || file.includes('commercial-premium')
        ) {
            suites.add('commercial');
            browserScripts.add('validate-018-commercial-pages-browser.mjs');
        }
        if (file.includes('validate-no-theme-hero') || file.startsWith('wp-content/themes/petshop-theme/')) {
            suites.add('theme');
            browserScripts.add('validate-no-theme-hero-browser.mjs');
        }
        if (file.includes('005-session-01')) {
            suites.add('storefront-005-01');
            browserScripts.add('validate-005-session-01-browser.mjs');
        }
        if (file.includes('005-session-02')) {
            suites.add('storefront-005-02');
            browserScripts.add('validate-005-session-02-browser.mjs');
        }
        if (file.includes('005-catalog-layout')) {
            browserScripts.add('validate-005-catalog-layout-browser.mjs');
        }
        if (
            file.includes('CatalogFilter.php')
            || file.includes('catalog-filter.js')
            || file.includes('validate-021-catalog-filters')
            || file.startsWith('wp-content/themes/petshop-theme/')
        ) {
            browserScripts.add('validate-021-catalog-filters-browser.mjs');
        }
        if (file.includes('005-pdp')) {
            browserScripts.add('validate-005-pdp-browser.mjs');
        }
        if (file.includes('005-cart')) {
            browserScripts.add('validate-005-cart-browser.mjs');
        }
        if (file.includes('013-')) {
            suites.add('checkout-013');
            browserScripts.add('validate-013-browser.mjs');
        }
        if (file.includes('014-identity') || file.includes('validate-014-docs-and-tokens')) {
            suites.add('identity-014');
        }
        if (file.includes('015-support')) {
            suites.add('support-015');
        }
        if (
            file.includes('CategoryIcons')
            || file.includes('category-icon')
            || file.includes('CategoryGrid')
            || file.includes('CategoryTermMeta')
            || file.includes('validate-022-category-icons')
            || file.includes('022-icones-vitrine')
        ) {
            suites.add('category-icons-022');
            browserScripts.add('validate-022-category-icons-browser.mjs');
        }
        if (
            file.includes('Personalization')
            || file.includes('personalizer')
            || file.includes('personalizable-products')
            || file.includes('validate-012-personalization')
            || file.includes('validate-012-personalizer')
            || file.includes('012-personalizador')
            || file.includes('012-sessoes-00')
            || file.includes('guia-personalizacao')
            || file.includes('operacao-personalizacoes')
        ) {
            suites.add('personalization-012');
            browserScripts.add('validate-012-personalizer-browser.mjs');
        }
        if (
            file.includes('030-frase-pedido-recebido')
            || file.includes('OrderReceivedMessage')
            || file.includes('validate-030')
            || file.includes('petshop_order_received_text')
        ) {
            suites.add('order-received-030');
            browserScripts.add('validate-030-order-received-browser.mjs');
        }
        if (
            file.includes('027-calculadora-frete-hub')
            || file.includes('ShippingQuotes')
            || file.includes('ProductDetails.php')
            || file.includes('product-experience.js')
            || file.includes('validate-027-shipping-hub')
        ) {
            suites.add('shipping-hub-027');
            browserScripts.add('validate-027-shipping-hub-browser.mjs');
        }
        if (
            file.includes('023-rodape')
            ||             file.includes('validate-023-footer')
            || file.includes('petshop_footer')
            || file.includes('institutional-footer')
            || (file.includes('DefaultSettings.php') && file.includes('petshop-core'))
            || file.includes('Admin/Customizer.php')
        ) {
            suites.add('footer-023');
            browserScripts.add('validate-023-footer-browser.mjs');
        }
        if (
            file.includes('home-campaign')
            || file.includes('HomeCampaign')
            || file.includes('class-home-campaign-blocks')
            || file.includes('validate-024-home-campaigns')
            || file.includes('024-carrossel-banner')
        ) {
            suites.add('campaigns-024');
            browserScripts.add('validate-024-home-campaigns-carousel-browser.mjs');
        }
    }

    return { suites, browserScripts };
};

const runFocusedSuites = (suites) => {
    if (suites.has('docs') || suites.has('identity-014') || suites.has('runner')) {
        run('node', ['scripts/validate-014-docs-and-tokens.mjs']);
    }
    if (suites.has('storefront')) {
        evalFile('validate-storefront.php');
    }
    if (suites.has('storefront-005-01')) {
        evalFile('validate-005-session-01.php');
        evalFile('test-005-session-01-persistence.php');
    }
    if (suites.has('storefront-005-02')) {
        evalFile('validate-005-session-02.php');
        evalFile('test-005-session-02-persistence.php');
    }
    if (suites.has('checkout-013')) {
        evalFile('validate-013-hpos.php');
        evalFile('validate-013-security.php');
        evalFile('test-013-persistence.php');
    }
    if (suites.has('identity-014')) {
        evalFile('validate-014-identity-campaigns.php');
    }
    if (suites.has('support-015')) {
        evalFile('validate-015-support-section.php');
    }
    if (suites.has('category-icons-022')) {
        evalFile('validate-022-category-icons.php');
    }
    if (suites.has('personalization-012')) {
        evalFile('validate-012-personalization.php');
        evalFile('smoke-012-order-flow.php');
    }
    if (suites.has('footer-023')) {
        evalFile('validate-023-footer.php');
    }
    if (suites.has('campaigns-024')) {
        evalFile('validate-024-home-campaigns-carousel.php');
    }
    if (suites.has('order-received-030')) {
        evalFile('validate-030.php');
    }
    if (suites.has('shipping-hub-027')) {
        evalFile('validate-027-shipping-hub.php');
    }
    if (suites.has('product-grid')) {
        evalFile('validate-016-product-grid.php');
    }
    if (suites.has('commercial')) {
        evalFile('validate-018-commercial-pages.php');
        evalFile('validate-animal-republik-products.php');
    }
};

const runBrowserScripts = (browserScripts) => {
    if (!browser || browserScripts.size === 0) {
        return;
    }

    const originalHome = dockerCliOutput('option', 'get', 'home');
    const originalSiteUrl = dockerCliOutput('option', 'get', 'siteurl');
    const publicHome = process.env.PETSHOP_PUBLIC_URL || 'http://localhost:8888';
    const restoreHome = originalHome.includes('://wordpress') ? publicHome : originalHome;
    const restoreSiteUrl = originalSiteUrl.includes('://wordpress') ? publicHome : originalSiteUrl;
    try {
        dockerCli('option', 'update', 'home', 'http://wordpress');
        dockerCli('option', 'update', 'siteurl', 'http://wordpress');
        dockerCli('cache', 'flush');

        for (const script of browserScripts) {
            run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '-e', 'PETSHOP_BASE_URL=http://wordpress', '-e', 'PETSHOP_CANONICAL_HOST=localhost:8888', 'node', 'node', `/workspace/scripts/${script}`]);
        }
    } finally {
        dockerCli('option', 'update', 'home', restoreHome);
        dockerCli('option', 'update', 'siteurl', restoreSiteUrl);
        dockerCli('cache', 'flush');
    }
};

const runChangedValidation = () => {
    const files = changedFiles();
    if (files.length === 0) {
        console.log('validate:changed: nenhum arquivo alterado detectado.');
        return;
    }

    console.log(`validate:changed: ${files.length} arquivo(s) alterado(s) detectado(s).`);
    syncChangedRuntimeFiles(files);
    lintChangedFiles(files);

    const { suites, browserScripts } = classifySuites(files);
    runFocusedProvision(suites);
    runFocusedSuites(suites);
    runBrowserScripts(browserScripts);

    if (suites.size === 0 && (!browser || browserScripts.size === 0)) {
        console.log('validate:changed: apenas lint/check sintatico foi necessario para os arquivos alterados.');
        return;
    }

    console.log(`validate:changed: suites=${[...suites].sort().join(', ') || 'nenhuma'} browser=${browser ? [...browserScripts].sort().join(', ') || 'nenhum' : 'desativado'}`);
};

const runFullValidation = () => {
    const script = isWindows
        ? join(root, 'scripts', 'run-gates.ps1')
        : join(root, 'scripts', 'run-gates.sh');

    if (isWindows) {
        const pwshArgs = [
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            script,
            ...(browser ? ['-Browser'] : []),
            ...(pdp ? ['-Pdp'] : []),
            ...(cart ? ['-Cart'] : []),
            ...(contentAudit ? ['-ContentAudit'] : []),
            ...(skipProvision ? ['-SkipProvision'] : []),
        ];
        run('powershell', pwshArgs);
        return;
    }

    if (!existsSync(script)) {
        console.error(`Script nao encontrado: ${script}`);
        process.exit(1);
    }

    run('bash', [
        script,
        ...(browser ? ['--browser'] : []),
        ...(pdp ? ['--pdp'] : []),
        ...(cart ? ['--cart'] : []),
        ...(contentAudit ? ['--content-audit'] : []),
        ...(skipProvision ? ['--skip-provision'] : []),
    ]);
};

try {
    if (changed) {
        runChangedValidation();
    } else {
        runFullValidation();
    }
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
}
