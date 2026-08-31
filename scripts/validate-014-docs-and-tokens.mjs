import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const failures = [];
const read = (path) => readFileSync(join(root, path), 'utf8');

const themeCss = read('wp-content/themes/petshop-theme/style.css').toLowerCase();
const functionsPhp = read('wp-content/themes/petshop-theme/functions.php');
const homeGuide = read('docs/guia-edicao-home.md');
const identityGuidePath = join(root, 'docs/guia-identidade-visual-autelie.md');

const tokens = [
  '--brand-teal-900: #004f50',
  '--brand-teal-700: #126e70',
  '--brand-teal-500: #2b9292',
  '--brand-aqua-400: #58c2c7',
  '--brand-orange-600: #e9530d',
  '--brand-orange-500: #f47721',
  '--brand-orange-action: #c94b0b',
  '--neutral-950: #252426',
  '--neutral-700: #5e5d61',
  '--neutral-300: #d8d9db',
  '--neutral-100: #f2f3f4',
  '--cream-50: #faf7f1',
];

for (const token of tokens) {
  if (!themeCss.includes(token)) {
    failures.push(`Token ausente no tema: ${token}`);
  }
}

if (!functionsPhp.includes('fonts.googleapis.com/css2?family=Nunito+Sans')) {
  failures.push('Nunito Sans nao esta enfileirada no tema.');
}

if (!functionsPhp.includes('enqueue_block_editor_assets')) {
  failures.push('Nunito Sans nao esta enfileirada para o editor Gutenberg.');
}

if (!existsSync(identityGuidePath)) {
  failures.push('docs/guia-identidade-visual-autelie.md nao existe.');
}

for (const term of ['Campanha editorial', 'Arte final', 'Texto alternativo', 'CTA']) {
  if (!homeGuide.includes(term)) {
    failures.push(`docs/guia-edicao-home.md nao documenta: ${term}`);
  }
}

if (!themeCss.includes('.petshop-home-campaigns__slide--editorial')) {
  failures.push('CSS da campanha editorial nao encontrado.');
}

if (!themeCss.includes('background: #373435;')) {
  failures.push('Rodape institucional nao voltou ao tom escuro anterior aprovado.');
}

if (failures.length > 0) {
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('validate-014-docs-and-tokens: passed');
