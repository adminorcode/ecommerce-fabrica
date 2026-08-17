#!/usr/bin/env node
/**
 * Prepara pacote de deploy HostGator/cPanel a partir do ambiente Docker atual.
 * Saída: outputs/deploy-cpanel/<stamp>/
 */

import { spawnSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const isWindows = process.platform === 'win32';
const stamp = new Date()
  .toISOString()
  .replace(/[-:]/g, '')
  .replace(/\.\d+Z$/, '')
  .replace('T', '-');
const outDir = join(root, 'outputs', 'deploy-cpanel', stamp);
const stageDir = join(outDir, 'stage');
const wpContentDir = join(outDir, 'wp-content');

const fail = (message) => {
  console.error(`prepare-deploy: ${message}`);
  process.exit(1);
};

const run = (command, args, options = {}) => {
  const result = spawnSync(command, args, {
    cwd: root,
    encoding: 'utf8',
    stdio: options.capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
    env: {
      ...process.env,
      MSYS_NO_PATHCONV: '1',
      MSYS2_ARG_CONV_EXCL: '*',
    },
    shell: options.shell === true,
  });

  if ((result.status ?? 1) !== 0) {
    if (options.capture) {
      process.stderr.write(result.stderr || result.stdout || '');
    }
    fail(`${command} ${args.join(' ')} falhou (codigo ${result.status ?? 1})`);
  }

  return options.capture ? (result.stdout || '').trim() : '';
};

const dockerCli = (...args) => run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', ...args]);
const dockerCliCapture = (...args) => run('docker', ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', ...args], { capture: true });

const assertDocker = () => {
  const ps = run('docker', ['compose', 'ps', '--status', 'running', '--format', '{{.Name}}'], { capture: true });
  if (!ps.split(/\r?\n/).some((name) => name.includes('wordpress'))) {
    fail('Contêiner WordPress nao esta em execucao. Suba com: docker compose up -d --wait');
  }
};

const copyFiltered = (source, destination, excludedDirNames = []) => {
  mkdirSync(destination, { recursive: true });
  cpSync(source, destination, {
    recursive: true,
    filter: (src) => {
      const base = src.split(/[/\\]/).pop();
      if (excludedDirNames.includes(base)) {
        return false;
      }
      return true;
    },
  });
};

const zipWithPython = (sourceDir, zipPath, rootName) => {
  const script = `
from pathlib import Path
import zipfile
src = Path(r'''${sourceDir}''')
dest = Path(r'''${zipPath}''')
if dest.exists():
    dest.unlink()
with zipfile.ZipFile(dest, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
    for path in src.rglob('*'):
        if path.is_file():
            zf.write(path, f"${rootName}/{path.relative_to(src).as_posix()}")
print(dest.stat().st_size)
`;
  const tmp = mkdtempSync(join(tmpdir(), 'petshop-deploy-'));
  const pyFile = join(tmp, 'zip.py');
  try {
    writeFileSync(pyFile, script);
    const size = run(isWindows ? 'python' : 'python3', [pyFile], { capture: true });
    console.log(`prepare-deploy: zip ${rootName} -> ${zipPath} (${size} bytes)`);
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }
};

const extractTarGzWithPython = (tarPath, destinationDir) => {
  const script = `
from pathlib import Path
import tarfile
tar_path = Path(r'''${tarPath}''')
dest = Path(r'''${destinationDir}''')
dest.mkdir(parents=True, exist_ok=True)
with tarfile.open(tar_path, 'r:gz') as tar:
    try:
        tar.extractall(dest, filter='data')
    except TypeError:
        tar.extractall(dest)
print('extracted', tar_path.name, '->', dest)
`;
  const tmp = mkdtempSync(join(tmpdir(), 'petshop-deploy-'));
  const pyFile = join(tmp, 'untar.py');
  try {
    writeFileSync(pyFile, script);
    run(isWindows ? 'python' : 'python3', [pyFile]);
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }
};

const tarGzWithPython = (sourceDir, tarPath, arcName) => {
  const script = `
from pathlib import Path
import tarfile
src = Path(r'''${sourceDir}''')
dest = Path(r'''${tarPath}''')
if dest.exists():
    dest.unlink()
with tarfile.open(dest, 'w:gz') as tar:
    tar.add(src, arcname=r'''${arcName}''')
print(dest.stat().st_size)
`;
  const tmp = mkdtempSync(join(tmpdir(), 'petshop-deploy-'));
  const pyFile = join(tmp, 'tar.py');
  try {
    writeFileSync(pyFile, script);
    const size = run(isWindows ? 'python' : 'python3', [pyFile], { capture: true });
    console.log(`prepare-deploy: tar ${arcName} -> ${tarPath} (${size} bytes)`);
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }
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
  if (bytes < 1024 ** 3) return `${(bytes / (1024 ** 2)).toFixed(1)} MB`;
  return `${(bytes / (1024 ** 3)).toFixed(2)} GB`;
};

assertDocker();
mkdirSync(stageDir, { recursive: true });
mkdirSync(join(wpContentDir, 'themes'), { recursive: true });
mkdirSync(join(wpContentDir, 'plugins'), { recursive: true });

console.log(`prepare-deploy: saida -> ${outDir}`);

// Sync worktree into runtime volumes so uploads/export match current code surface.
run('docker', ['cp', join(root, 'wp-content/themes/petshop-theme'), 'petshop-wordpress-1:/var/www/html/wp-content/themes/']);
run('docker', ['cp', join(root, 'wp-content/plugins/petshop-core'), 'petshop-wordpress-1:/var/www/html/wp-content/plugins/']);

const themeStage = join(stageDir, 'petshop-theme');
const pluginStage = join(stageDir, 'petshop-core');
copyFiltered(join(root, 'wp-content/themes/petshop-theme'), themeStage, ['.git', 'node_modules']);
copyFiltered(
  join(root, 'wp-content/plugins/petshop-core'),
  pluginStage,
  ['.git', 'node_modules', 'tests', 'phpunit', 'myclabs', 'sebastian', 'phar-io', 'theseer', 'nikic']
);

// Extra cleanup for nested vendor test stacks if filter only matched leaf names at root.
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
  const target = join(pluginStage, nested);
  if (existsSync(target)) {
    rmSync(target, { recursive: true, force: true });
  }
}

if (!existsSync(join(pluginStage, 'vendor/autoload.php'))) {
  fail('petshop-core/vendor/autoload.php ausente no pacote');
}

cpSync(themeStage, join(wpContentDir, 'themes/petshop-theme'), { recursive: true });
cpSync(pluginStage, join(wpContentDir, 'plugins/petshop-core'), { recursive: true });

const uploadsTar = join(outDir, 'uploads.tar.gz');
run('docker', [
  'compose',
  '--profile',
  'tools',
  'run',
  '--rm',
  '--no-deps',
  '-v',
  `${outDir}:/out`,
  'cli',
  'sh',
  '-c',
  'cd /var/www/html/wp-content && tar -czf /out/uploads.tar.gz uploads',
]);

extractTarGzWithPython(uploadsTar, wpContentDir);

const dbSql = join(outDir, 'petshop-db.sql');
run('docker', [
  'compose',
  '--profile',
  'tools',
  'run',
  '--rm',
  '--no-deps',
  '-v',
  `${outDir}:/out`,
  'cli',
  'wp',
  'db',
  'export',
  '/out/petshop-db.sql',
  '--add-drop-table',
]);

zipWithPython(themeStage, join(outDir, 'petshop-theme.zip'), 'petshop-theme');
zipWithPython(pluginStage, join(outDir, 'petshop-core.zip'), 'petshop-core');
tarGzWithPython(wpContentDir, join(outDir, 'wp-content-deploy.tar.gz'), 'wp-content');

const branch = run('git', ['branch', '--show-current'], { capture: true }) || 'DETACHED';
const commit = run('git', ['rev-parse', '--short', 'HEAD'], { capture: true });
let home = '';
let siteurl = '';
try {
  home = dockerCliCapture('wp', 'option', 'get', 'home');
  siteurl = dockerCliCapture('wp', 'option', 'get', 'siteurl');
} catch {
  home = '(indisponivel)';
  siteurl = '(indisponivel)';
}

const style = readFileSync(join(wpContentDir, 'themes/petshop-theme/style.css'), 'utf8');
if (!style.includes('Template: blocksy')) {
  fail('style.css do tema nao declara Template: blocksy');
}
const versionLine = style.split(/\r?\n/).find((line) => line.startsWith('Version:')) || 'Version: ?';

const required = [
  'wp-content/themes/petshop-theme/style.css',
  'wp-content/plugins/petshop-core/petshop-core.php',
  'wp-content/plugins/petshop-core/assets/js/wishlist.js',
  'wp-content/plugins/petshop-core/assets/js/catalog-filter.js',
  'wp-content/plugins/petshop-core/assets/js/category-icon-media.js',
  'wp-content/plugins/petshop-core/vendor/autoload.php',
  'wp-content/uploads',
  'petshop-theme.zip',
  'petshop-core.zip',
  'uploads.tar.gz',
  'petshop-db.sql',
  'wp-content-deploy.tar.gz',
];

for (const relative of required) {
  mustExist(relative);
}

const manifest = [
  `stamp=${stamp}`,
  `branch=${branch}`,
  `commit=${commit}`,
  `generated=${new Date().toISOString()}`,
  `home=${home}`,
  `siteurl=${siteurl}`,
  `theme=${versionLine}`,
  'contents=wp-content/{themes/petshop-theme,plugins/petshop-core,uploads} + zips + db',
].join('\n');
writeFileSync(join(outDir, 'MANIFEST.txt'), `${manifest}\n`);

const abs = resolve(outDir);
const summary = [
  '',
  '=== Pacote de deploy pronto ===',
  `Pasta: ${abs}`,
  `Tema ZIP: ${join(abs, 'petshop-theme.zip')} (${humanSize(statSync(join(abs, 'petshop-theme.zip')).size)})`,
  `Plugin ZIP: ${join(abs, 'petshop-core.zip')} (${humanSize(statSync(join(abs, 'petshop-core.zip')).size)})`,
  `Uploads: ${join(abs, 'uploads.tar.gz')} (${humanSize(statSync(join(abs, 'uploads.tar.gz')).size)})`,
  `Banco: ${join(abs, 'petshop-db.sql')} (${humanSize(statSync(join(abs, 'petshop-db.sql')).size)})`,
  `wp-content (copiar): ${join(abs, 'wp-content')}`,
  `wp-content (tar): ${join(abs, 'wp-content-deploy.tar.gz')} (${humanSize(statSync(join(abs, 'wp-content-deploy.tar.gz')).size)})`,
  `Manifest: ${join(abs, 'MANIFEST.txt')}`,
  '',
].join('\n');

console.log(summary);
writeFileSync(join(outDir, 'WHERE.txt'), `${abs}\n`);
