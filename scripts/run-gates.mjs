import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const isWindows = process.platform === 'win32';
const script = isWindows
    ? join(root, 'scripts', 'run-gates.ps1')
    : join(root, 'scripts', 'run-gates.sh');

const args = process.argv.slice(2);
const browser = args.includes('--browser');
const pdp = args.includes('--pdp');
const cart = args.includes('--cart');

if (isWindows) {
    const pwsh = spawnSync(
        'powershell',
        ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', script, ...(browser ? ['-Browser'] : []), ...(pdp ? ['-Pdp'] : []), ...(cart ? ['-Cart'] : [])],
        { stdio: 'inherit', cwd: root }
    );
    process.exit(pwsh.status ?? 1);
}

if (!existsSync(script)) {
    console.error(`Script não encontrado: ${script}`);
    process.exit(1);
}

const bash = spawnSync('bash', [script, ...(browser ? ['--browser'] : []), ...(pdp ? ['--pdp'] : []), ...(cart ? ['--cart'] : [])], {
    stdio: 'inherit',
    cwd: root,
});
process.exit(bash.status ?? 1);
