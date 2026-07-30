import { spawn } from 'node:child_process';
import {
    access,
    cp,
    mkdir,
    readFile,
    rename,
    rm,
    stat,
    writeFile,
} from 'node:fs/promises';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const WORDPRESS_VERSION = '7.0.2';
const WOOCOMMERCE_VERSION = '10.9.4';
const WORDPRESS_URL = `https://wordpress.org/wordpress-${ WORDPRESS_VERSION }.zip`;
const WOOCOMMERCE_URL =
    `https://downloads.wordpress.org/plugin/woocommerce.${ WOOCOMMERCE_VERSION }.zip`;

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectDirectory = path.resolve(scriptDirectory, '..');
const runtimeDirectory = path.join(projectDirectory, '.local', 'wp-env');
const downloadsDirectory = path.join(runtimeDirectory, 'downloads');
const wordpressDirectory = path.join(runtimeDirectory, 'wordpress');
const woocommerceDirectory = path.join(runtimeDirectory, 'woocommerce');
const overridePath = path.join(projectDirectory, '.wp-env.override.json');
const npmCli = process.env.npm_execpath;
const wpEnvCli = path.join(
    projectDirectory,
    'node_modules',
    '@wordpress',
    'env',
    'bin',
    'wp-env'
);

function log(message) {
    console.log(`\n[bootstrap] ${ message }`);
}

function run(command, args, options = {}) {
    const {
        allowFailure = false,
        quiet = false,
    } = options;

    return new Promise((resolve, reject) => {
        const child = spawn(command, args, {
            cwd: projectDirectory,
            env: process.env,
            shell: false,
            stdio: quiet ? 'ignore' : 'inherit',
        });

        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0 || allowFailure) {
                resolve(code ?? 1);
                return;
            }

            reject(
                new Error(
                    `O comando falhou (${ code }): ${ command } ${ args.join(' ') }`
                )
            );
        });
    });
}

async function exists(filePath) {
    try {
        await access(filePath);
        return true;
    } catch {
        return false;
    }
}

async function download(url, destination, minimumBytes) {
    if (await exists(destination)) {
        const current = await stat(destination);
        if (current.size >= minimumBytes) {
            console.log(`[bootstrap] Download em cache: ${ path.basename(destination) }`);
            return;
        }
    }

    const temporary = `${ destination }.part-${ process.pid }`;
    await rm(temporary, { force: true });

    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            console.log(`[bootstrap] Baixando ${ url } (tentativa ${ attempt }/3)`);
            await run('curl', [
                '--fail',
                '--location',
                '--retry',
                '3',
                '--connect-timeout',
                '30',
                '--max-time',
                '300',
                '--user-agent',
                'petshop-local-bootstrap/1.0',
                '--output',
                temporary,
                url,
            ]);

            const downloaded = await stat(temporary);
            if (downloaded.size < minimumBytes) {
                throw new Error(
                    `Arquivo menor que o esperado (${ downloaded.size } bytes)`
                );
            }

            await rm(destination, { force: true });
            await rename(temporary, destination);
            return;
        } catch (error) {
            await rm(temporary, { force: true });
            if (attempt === 3) {
                throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, attempt * 2_000));
        }
    }
}

async function hasWordPressVersion(directory) {
    try {
        const versionFile = await readFile(
            path.join(directory, 'wp-includes', 'version.php'),
            'utf8'
        );
        return versionFile.includes(`$wp_version = '${ WORDPRESS_VERSION }';`);
    } catch {
        return false;
    }
}

async function hasWooCommerceVersion(directory) {
    try {
        const pluginFile = await readFile(
            path.join(directory, 'woocommerce.php'),
            'utf8'
        );
        return new RegExp(
            `^\\s*\\*\\s*Version:\\s*${ WOOCOMMERCE_VERSION.replaceAll('.', '\\.') }\\s*$`,
            'm'
        ).test(pluginFile);
    } catch {
        return false;
    }
}

async function extractArchive({
    archive,
    destination,
    archiveRoot,
    isValid,
}) {
    if (await isValid(destination)) {
        console.log(`[bootstrap] Runtime válido: ${ path.basename(destination) }`);
        return;
    }

    const temporary = `${ destination }.extract-${ process.pid }`;
    await rm(temporary, { recursive: true, force: true });
    await mkdir(temporary, { recursive: true });

    const extractZipModule = await import('extract-zip');
    const extractZip = promisify(extractZipModule.default);
    await extractZip(archive, { dir: temporary });

    const extracted = path.join(temporary, archiveRoot);
    if (!(await isValid(extracted))) {
        throw new Error(
            `O pacote ${ path.basename(archive) } foi extraído, mas não passou na validação.`
        );
    }

    await rm(destination, { recursive: true, force: true });
    await cp(extracted, destination, { recursive: true });
    if (!(await isValid(destination))) {
        throw new Error(
            `A cópia local de ${ path.basename(archive) } não passou na validação.`
        );
    }
    await rm(temporary, { recursive: true, force: true, maxRetries: 5, retryDelay: 500 });
}

async function writeWpEnvOverride() {
    let override = {};

    if (await exists(overridePath)) {
        try {
            override = JSON.parse(await readFile(overridePath, 'utf8'));
        } catch {
            throw new Error(
                '.wp-env.override.json existe, mas não contém JSON válido.'
            );
        }
    }

    override.core = './.local/wp-env/wordpress';
    override.plugins = [
        './.local/wp-env/woocommerce',
        './wp-content/plugins/petshop-core',
    ];

    await writeFile(
        overridePath,
        `${ JSON.stringify(override, null, 2) }\n`,
        'utf8'
    );
}

function wp(args, options = {}) {
    return run(
        process.execPath,
        [wpEnvCli, 'run', 'cli', 'wp', ...args],
        options
    );
}

async function main() {
    const nodeMajor = Number.parseInt(process.versions.node.split('.')[0], 10);
    if (nodeMajor !== 24) {
        throw new Error(
            `Node.js 24 é obrigatório. Versão atual: ${ process.versions.node }.`
        );
    }
    if (!npmCli) {
        throw new Error('Execute este bootstrap com: npm run bootstrap');
    }

    log('Verificando Docker');
    await run('docker', ['info'], { quiet: true });

    log('Instalando dependências Node');
    await run(process.execPath, [
        npmCli,
        'ci',
        '--no-audit',
        '--no-fund',
    ]);

    log('Preparando WordPress e WooCommerce');
    await mkdir(downloadsDirectory, { recursive: true });
    const wordpressArchive = path.join(
        downloadsDirectory,
        `wordpress-${ WORDPRESS_VERSION }.zip`
    );
    const woocommerceArchive = path.join(
        downloadsDirectory,
        `woocommerce-${ WOOCOMMERCE_VERSION }.zip`
    );

    if (!(await hasWordPressVersion(wordpressDirectory))) {
        await download(WORDPRESS_URL, wordpressArchive, 20_000_000);
    }
    if (!(await hasWooCommerceVersion(woocommerceDirectory))) {
        await download(WOOCOMMERCE_URL, woocommerceArchive, 10_000_000);
    }

    await extractArchive({
        archive: wordpressArchive,
        destination: wordpressDirectory,
        archiveRoot: 'wordpress',
        isValid: hasWordPressVersion,
    });
    await extractArchive({
        archive: woocommerceArchive,
        destination: woocommerceDirectory,
        archiveRoot: 'woocommerce',
        isValid: hasWooCommerceVersion,
    });
    await writeWpEnvOverride();

    log('Iniciando wp-env');
    await run(process.execPath, [
        wpEnvCli,
        'start',
        '--runtime=docker',
    ]);

    log('Garantindo a instalação do WordPress');
    const isInstalled = await wp(['core', 'is-installed'], {
        allowFailure: true,
        quiet: true,
    });
    if (isInstalled !== 0) {
        await wp([
            'core',
            'install',
            '--url=http://localhost:8888',
            '--title=Petshop',
            '--admin_user=admin',
            '--admin_password=password',
            '--admin_email=wordpress@example.com',
            '--skip-email',
        ]);
    }

    log('Instalando autoload do plugin local');
    await run(process.execPath, [
        wpEnvCli,
        'run',
        'cli',
        '--env-cwd=wp-content/plugins/petshop-core',
        'composer',
        'install',
        '--no-interaction',
        '--prefer-dist',
    ]);

    log('Aplicando idioma e opções do site');
    await wp([
        'language',
        'core',
        'install',
        'pt_BR',
        '--skip-plugins',
        '--skip-themes',
    ]);
    await wp([
        'site',
        'switch-language',
        'pt_BR',
        '--skip-plugins',
        '--skip-themes',
    ]);

    const optionUpdates = {
        blogname: 'Petshop',
        blogdescription: 'Tudo para o seu pet',
        timezone_string: 'America/Sao_Paulo',
        date_format: 'd/m/Y',
        time_format: 'H:i',
        permalink_structure: '/%postname%/',
    };
    const encodedOptions = Buffer.from(
        JSON.stringify(optionUpdates),
        'utf8'
    ).toString('base64');
    const php = `foreach (json_decode(base64_decode('${ encodedOptions }'), true) as $key => $value) { update_option($key, $value); }`;
    await wp(['eval', php, '--skip-plugins', '--skip-themes']);

    log('Ativando plugins e tema');
    await wp(['plugin', 'activate', 'woocommerce', 'petshop-core']);
    await wp(['theme', 'activate', 'petshop-theme', '--skip-plugins']);

    const translationExitCode = await wp(
        [
            'language',
            'plugin',
            'install',
            'woocommerce',
            'pt_BR',
            '--skip-plugins',
            '--skip-themes',
        ],
        { allowFailure: true }
    );
    if (translationExitCode !== 0) {
        console.warn(
            '[bootstrap] Aviso: a tradução do WooCommerce não pôde ser baixada agora.'
        );
    }

    await wp(['rewrite', 'flush']);

    log('Validando o ambiente');
    await wp(['core', 'is-installed', '--skip-plugins', '--skip-themes']);
    await wp(['plugin', 'is-active', 'woocommerce', '--skip-plugins', '--skip-themes']);
    await wp(['plugin', 'is-active', 'petshop-core', '--skip-plugins', '--skip-themes']);
    await wp(['theme', 'is-active', 'petshop-theme', '--skip-plugins', '--skip-themes']);

    console.log(`
Bootstrap concluído.

Loja:  http://localhost:8888/
Admin: http://localhost:8888/wp-admin/
Login: admin
Senha: password
`);
}

main().catch((error) => {
    console.error(`\n[bootstrap] ERRO: ${ error.message }`);
    process.exitCode = 1;
});
