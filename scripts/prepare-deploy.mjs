#!/usr/bin/env node
/**
 * Prepara pacote de deploy: wp-content (tema + plugin do worktree, uploads do
 * volume Docker) e dump SQL. Sem ZIP, sem tar extra, sem copiar node_modules.
 */

import { spawnSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const stamp = new Date()
  .toISOString()
  .replace(/[-:]/g, '')
  .replace(/\.\d+Z$/, '')
  .replace('T', '-');
const outDir = join(root, 'outputs', 'deploy-cpanel', stamp);
const wpContentDir = join(outDir, 'wp-content');
const excludedNames = [
  '.git',
  'node_modules',
  'tests',
  'phpunit',
  'myclabs',
  'sebastian',
  'phar-io',
  'theseer',
  'nikic',
];

const fail = (message) => {
  console.error(`prepare-deploy: ${message}`);
  process.exit(1);
};

const run = (command, args, options = {}) => {
  const result = spawnSync(command, args, {
    cwd: options.cwd ?? root,
    encoding: options.capture ? 'utf8' : undefined,
    maxBuffer: 32 * 1024 * 1024,
    stdio: options.capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
    env: {
      ...process.env,
      MSYS_NO_PATHCONV: '1',
      MSYS2_ARG_CONV_EXCL: '*',
    },
  });

  if ((result.status ?? 1) !== 0) {
    if (options.capture) {
      process.stderr.write(result.stderr || result.stdout || '');
    }
    fail(`${command} ${args.join(' ')} falhou (codigo ${result.status ?? 1})`);
  }

  return options.capture ? (result.stdout || '').trim() : '';
};

const assertDocker = () => {
  const ps = run('docker', ['compose', 'ps', '--status', 'running', '--format', '{{.Name}}'], {
    capture: true,
  });
  if (!ps.split(/\r?\n/).some((name) => name.includes('wordpress'))) {
    fail('Contêiner WordPress nao esta em execucao. Suba com: docker compose up -d --wait');
  }
};

const copyFiltered = (source, destination) => {
  mkdirSync(destination, { recursive: true });
  cpSync(source, destination, {
    recursive: true,
    filter: (src) => !excludedNames.includes(src.split(/[/\\]/).pop()),
  });
};

const mustExist = (relativePath) => {
  const absolute = join(outDir, relativePath);
  if (!existsSync(absolute)) {
    fail(`Arquivo obrigatorio ausente: ${absolute}`);
  }
  return absolute;
};

const humanSize = (bytes) => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  return `${(bytes / (1024 ** 3)).toFixed(2)} GB`;
};

const dirSize = (dir) => {
  let total = 0;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    total += entry.isDirectory() ? dirSize(full) : statSync(full).size;
  }
  return total;
};

assertDocker();
mkdirSync(join(wpContentDir, 'themes'), { recursive: true });
mkdirSync(join(wpContentDir, 'plugins'), { recursive: true });

console.log(`prepare-deploy: saida -> ${outDir}`);
console.log('prepare-deploy: copiando tema e plugin do worktree');

copyFiltered(join(root, 'wp-content/themes/petshop-theme'), join(wpContentDir, 'themes/petshop-theme'));
copyFiltered(join(root, 'wp-content/plugins/petshop-core'), join(wpContentDir, 'plugins/petshop-core'));
copyFiltered(
  join(root, 'wp-content/plugins/melhor-envio-cotacao'),
  join(wpContentDir, 'plugins/melhor-envio-cotacao')
);
copyFiltered(
  join(root, 'wp-content/plugins/woo-better-shipping-calculator-for-brazil'),
  join(wpContentDir, 'plugins/woo-better-shipping-calculator-for-brazil')
);

const pluginOut = join(wpContentDir, 'plugins/petshop-core');

for (const nested of [
  'vendor/phpunit',
  'vendor/myclabs',
  'vendor/sebastian',
  'vendor/phar-io',
  'vendor/theseer',
  'vendor/nikic',
  'tests',
  'node_modules',
  'phpunit.xml.dist',
]) {
  const target = join(pluginOut, nested);
  if (existsSync(target)) {
    rmSync(target, { recursive: true, force: true });
  }
}

if (!existsSync(join(pluginOut, 'vendor/autoload.php'))) {
  fail('petshop-core/vendor/autoload.php ausente no pacote');
}

// Strip phpunit/myclabs then dump-autoload --no-dev on THIS copy only.
// Leaving worktree autoload intact (PHPUnit). Shipping stripped folders
// with a require-dev autoload fatals production on deep_copy.php.
console.log('prepare-deploy: regenerando Composer autoload sem require-dev');
run('docker', [
  'compose',
  '--profile',
  'tools',
  'run',
  '--rm',
  '--no-deps',
  '-v',
  `${pluginOut}:/plugin`,
  'cli',
  'composer',
  'dump-autoload',
  '--no-dev',
  '--optimize',
  '-d',
  '/plugin',
]);

const autoloadSnapshot = [
  'vendor/composer/autoload_files.php',
  'vendor/composer/autoload_static.php',
  'vendor/composer/autoload_psr4.php',
]
  .map((relative) => join(pluginOut, relative))
  .filter((absolute) => existsSync(absolute))
  .map((absolute) => readFileSync(absolute, 'utf8'))
  .join('\n');

if (/myclabs|phpunit\/phpunit|deep-copy/i.test(autoloadSnapshot)) {
  fail('autoload de producao ainda referencia vendor de desenvolvimento (myclabs/phpunit)');
}

const style = readFileSync(join(wpContentDir, 'themes/petshop-theme/style.css'), 'utf8');
if (!style.includes('Template: blocksy')) {
  fail('style.css do tema nao declara Template: blocksy');
}

const uploadsTar = join(outDir, '.uploads.tar');
const dbSql = join(outDir, 'petshop-db.sql');

console.log('prepare-deploy: exportando uploads e SQL do contêiner wordpress');
run('docker', [
  'compose',
  'exec',
  '-T',
  'wordpress',
  'sh',
  '-c',
  'tar -C /var/www/html/wp-content -cf /tmp/petshop-uploads.tar uploads && wp db export /tmp/petshop-db.sql --add-drop-table',
]);
run('docker', ['compose', 'cp', 'wordpress:/tmp/petshop-uploads.tar', uploadsTar]);
run('docker', ['compose', 'cp', 'wordpress:/tmp/petshop-db.sql', dbSql]);
run('tar', ['-xf', '.uploads.tar', '-C', 'wp-content'], { cwd: outDir });
rmSync(uploadsTar, { force: true });
run('docker', [
  'compose',
  'exec',
  '-T',
  'wordpress',
  'rm',
  '-f',
  '/tmp/petshop-uploads.tar',
  '/tmp/petshop-db.sql',
]);

const required = [
  'wp-content/themes/petshop-theme/style.css',
  'wp-content/plugins/petshop-core/petshop-core.php',
  'wp-content/plugins/petshop-core/assets/js/wishlist.js',
  'wp-content/plugins/petshop-core/assets/js/catalog-filter.js',
  'wp-content/plugins/petshop-core/assets/js/category-icon-media.js',
  'wp-content/plugins/petshop-core/vendor/autoload.php',
  'wp-content/plugins/melhor-envio-cotacao/melhor-envio-beta.php',
  'wp-content/plugins/melhor-envio-cotacao/vendor/autoload.php',
  'wp-content/plugins/woo-better-shipping-calculator-for-brazil/wc-better-shipping-calculator-for-brazil.php',
  'wp-content/plugins/woo-better-shipping-calculator-for-brazil/vendor/autoload.php',
  'wp-content/uploads',
  'petshop-db.sql',
];

for (const relative of required) {
  mustExist(relative);
}

const branch = run('git', ['branch', '--show-current'], { capture: true }) || 'DETACHED';
const commit = run('git', ['rev-parse', '--short', 'HEAD'], { capture: true });
const versionLine = style.split(/\r?\n/).find((line) => line.startsWith('Version:')) || 'Version: ?';

const manifest = [
  `stamp=${stamp}`,
  `branch=${branch}`,
  `commit=${commit}`,
  `generated=${new Date().toISOString()}`,
  `theme=${versionLine}`,
  'contents=wp-content/{themes/petshop-theme,plugins/petshop-core,plugins/melhor-envio-cotacao,plugins/woo-better-shipping-calculator-for-brazil,uploads} + petshop-db.sql',
].join('\n');
writeFileSync(join(outDir, 'MANIFEST.txt'), `${manifest}\n`);

const abs = resolve(outDir);
writeFileSync(join(outDir, 'WHERE.txt'), `${abs}\n`);

console.log(
  [
    '',
    '=== Pacote de deploy pronto ===',
    `Pasta: ${abs}`,
    `wp-content: ${join(abs, 'wp-content')} (${humanSize(dirSize(wpContentDir))})`,
    `Banco: ${join(abs, 'petshop-db.sql')} (${humanSize(statSync(dbSql).size)})`,
    `Manifest: ${join(abs, 'MANIFEST.txt')}`,
    '',
  ].join('\n')
);
