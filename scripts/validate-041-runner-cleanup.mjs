import assert from 'node:assert/strict';
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import vm from 'node:vm';

const runner = fs.readFileSync(new URL('./run-gates.sh', import.meta.url), 'utf8');
const extract = (name) => {
  const pattern = new RegExp(`^( +)${name}\\(\\) \\{[\\s\\S]*?^\\1\\}`, 'm');
  const match = runner.match(pattern);
  assert(match, `Actual runner function ${name} must exist`);
  return match[0];
};
const functions = ['restore_urls', 'finish_browser_gate', 'cleanup_plan041_fixture'].map(extract).join('\n');
for (const [originalStatus, failCleanup, failRestore, expected] of [
  [7, true, false, 7], [0, true, false, 11], [0, false, true, 13], [0, false, false, 0],
]) {
  const script = `set -euo pipefail
original_home='http://example.test'
original_siteurl='http://example.test'
run_eval_file() {
  echo "CLEANUP $1" >&2
  if ${failCleanup}; then
    if [[ "$1" == *customer* ]]; then return 9; fi
    return 11
  fi
}
rm() { echo "FILE $*" >&2; }
run_wp() {
  echo "RESTORE $*" >&2
  if ${failRestore} && [[ "$*" == 'option update home '* ]]; then return 13; fi
}
${functions}
trap 'finish_browser_gate cleanup_plan041_fixture' EXIT
exit ${originalStatus}
`;
  const result = spawnSync('bash', ['-c', script], { encoding: 'utf8' });
  assert.equal(result.status, expected, result.stderr || result.error?.message);
  for (const marker of ['cleanup-041-browser-customer.php', 'cleanup-041-editor-fixture.php',
    '041-browser-fixture.json', '041-editor-fixture.json', 'option update home', 'option update siteurl', 'cache flush']) {
    assert(result.stderr.includes(marker), `Cleanup failure must not prevent ${marker}`);
  }
}
const syntax = spawnSync('bash', ['-n'], { input: runner, encoding: 'utf8' });
assert.equal(syntax.status, 0, syntax.stderr);
const changedRunner = fs.readFileSync(new URL('./run-gates.mjs', import.meta.url), 'utf8');
const changedFunction = changedRunner.match(/^const runChangedValidation = \(\) => \{[\s\S]*?^\};/m)?.[0];
assert(changedFunction);
for (const skipProvision of [false, true]) {
  let cleanupCalled = false;
  const context = vm.createContext({ skipProvision, browser: false, console: { log() {} },
    changedFiles: () => ['synthetic.php'], syncChangedRuntimeFiles() {}, lintChangedFiles() {},
    classifySuites: () => ({ suites: new Set(['shipping-integrity-041']), browserScripts: new Set() }),
    runFocusedProvision: () => { throw Error('Synthetic failure after fixture creation'); },
    runFocusedCleanup: () => { cleanupCalled = true; },
  });
  assert.throws(() => vm.runInContext(`${changedFunction}\nrunChangedValidation();`, context), /Synthetic failure/);
  assert.equal(cleanupCalled, !skipProvision, 'Partial provisioning must arm cleanup unless provisioning was skipped');
}
const cleanupFunction = changedRunner.match(/^const runFocusedCleanup = \(suites\) => \{[\s\S]*?^\};/m)?.[0];
assert(cleanupFunction);
const attempts = [];
const cleanupContext = vm.createContext({ root: '/synthetic',
  evalFile: (file) => { attempts.push(file); if (file.includes('041')) throw Error('Synthetic customer cleanup failure'); },
  join: (...parts) => parts.join('/'), existsSync: () => true, unlinkSync: (file) => attempts.push(file),
});
assert.throws(() => vm.runInContext(`${cleanupFunction}\nrunFocusedCleanup(new Set(['shipping-integrity-041', 'product-card-031']));`, cleanupContext), /Synthetic customer cleanup failure/);
assert(attempts.includes('cleanup-031-product-card-fixture.php'), 'Failed customer cleanup must not skip product fixture cleanup');
assert(attempts.some((file) => file.endsWith('041-browser-fixture.json')), 'Temporary credentials must be removed after cleanup failure');
console.log('041 runners: Bash failure/cleanup/restore and JS partial-provision cleanup passed');
