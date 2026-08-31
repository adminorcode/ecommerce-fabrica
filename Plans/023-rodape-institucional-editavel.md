# Plano 023 — Rodapé institucional editável

**Status:** Concluído  
**Data:** 2026-08-19  
**Branch sugerida:** `023-rodape-institucional-editavel` / `codex/023-rodape-institucional-editavel`  
**Dependências:** [007-refatoracao-petshop-core.md](./007-refatoracao-petshop-core.md) e [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md) (tokens e fundo `#373435`); não depende do Plano 012.  
**Origem:** pedido de rodapé administrável com **mockup visual** de composição (não só inventário de campos).  
**Referência visual:** 4 colunas (marca+redes, atendimento com ícones, categorias, institucional), faixa de selos com pictogramas distintos e barra legal compacta.  
**Entrega:** Customizer `petshop_footer`, render no tema com ocultação de vazios, composição alinhada à referência, docs em `docs/guia-edicao-rodape.md`, gates `validate-023-footer.php` / `validate-023-footer-browser.mjs`.

**Ajuste visual posterior:** a aproximação ao print de 2026-08-24 (filetes na faixa de selos, sublinhado dos títulos, fundo carvão) está no [033](./033-rodape-aproximacao-mockup.md). Este plano (023) permanece concluído em campos e markup.

## 1. Objetivo

Tornar o rodapé institucional da loja **completamente administrável** e **visualmente alinhado à referência**: redes, atendimento, menus, selos e dados legais editáveis no painel, com a estrutura e a iconografia do mockup.

O cliente altera URLs, textos e links no Customizer e nos menus, sem editar PHP/CSS/JS.

## 2. Regra de design (obrigatória)

| A referência define | A referência **não** exige |
| --- | --- |
| Composição: 4 colunas no desktop; redes **sob a marca**; atendimento em linhas ícone + título + apoio; chevron nas listas; faixa de 4 selos; legal compacto | Pixel-perfect de tipografia de um PDF externo |
| Iconografia: círculos sociais outline; círculos teal no atendimento; um pictograma **distinto** por selo | Upload de SVG custom por rede |
| Tokens já aprovados (fundo `#373435`, teal, Nunito Sans) | Nova paleta, nova família tipográfica ou Elementor |

**Não** tratar o mockup só como lista de campos. Campos sem a composição da referência são entrega incompleta.

Preservar o tom escuro do Plano 014. Laranja não entra no rodapé.

## 3. Baseline atual

Implementação em `wp-content/themes/petshop-theme/inc/institutional-footer.php` + settings em `Petshop\Core\Settings\DefaultSettings` / Customizer `petshop_footer`.

| Área | Esperado na referência | Regra |
| --- | --- | --- |
| Marca | Logo colorido (sem invert) + tagline | Logo em Identidade do site; tagline no Customizer |
| Redes | Título “Siga-nos”; Instagram, Facebook, TikTok, WhatsApp em círculos | URLs no Customizer; vazio = ícone oculto |
| Atendimento | 5 linhas com ícone teal: WhatsApp, e-mail, atendimento, horário, FAQ | Título funcional traduzido + subtítulo/URL editáveis |
| Categorias | Menu `petshop-primary` com chevron | **Aparência → Menus** |
| Institucional | Menu `petshop-footer` com chevron; **sem** Minha conta/Meus pedidos (já no header) | **Aparência → Menus** |
| Selos | 4 slots; ícones fixos por posição (escudo, medalha, cadeado, caminhão) | Título+texto no Customizer; vazio = slot oculto |
| Legal | Ícone de loja + copyright/razão/CNPJ numa linha + endereço | Customizer; vazio = trecho oculto |
| Pagamento | Sem coluna própria | Se houver texto, vai para a faixa legal |

## 4. Inventário administrável (por bloco)

### 4.1 Marca

| Item | Origem de edição |
| --- | --- |
| Logo | Aparência → Personalizar → Identidade do site |
| Descrição / tagline | Personalizar → Rodapé da loja |

### 4.2 Redes sociais (“Siga-nos”)

| Item | Origem | Regra |
| --- | --- | --- |
| Título da seção | Tradução (“Siga-nos”) | |
| URL Instagram / Facebook / TikTok / WhatsApp (rede) | Customizer | Vazio = ícone oculto |
| Rótulo acessível | Tradução / `aria-label` | |

WhatsApp de **rede** e WhatsApp de **atendimento** são campos distintos.

### 4.3 Atendimento

Cada linha visível: ícone estático + título funcional + apoio editável.

| Item | Origem | Regra |
| --- | --- | --- |
| WhatsApp — URL | Customizer | |
| WhatsApp — apoio (“Fale conosco”) | Customizer | Vazio = só o título |
| E-mail | Customizer (`mailto:`) | Vazio = oculto |
| Atendimento ao cliente | Página de atendimento do cabeçalho + texto auxiliar | Sem página = oculto |
| Horário | Customizer (textarea, uma linha por período) | Vazio = oculto |
| FAQ — URL + apoio | Customizer | Sem URL = oculto |

### 4.4 Categorias e Institucional

| Item | Origem |
| --- | --- |
| Lista Categorias | Menu `petshop-primary` |
| Lista Institucional | Menu `petshop-footer` |

Não gravar essas listas como strings no Customizer.

### 4.5 Selos de confiança

Quatro slots. Ícone **não** é editável (ordem fixa da referência). Título e descrição são.

Campo vazio (título e descrição) = slot oculto.

### 4.6 Faixa legal

Copyright, razão social/MEI, CNPJ e endereço no Customizer. Não fabricar CNPJ/endereço no código.

## 5. Fora de escopo

- Upload de ícones SVG custom por rede ou selo
- CMS Gutenberg no rodapé (permanece superfície global → Customizer + menus)
- Integração automática com APIs de redes
- Alterar Blocksy footer builder (já desligado)
- Conteúdo das páginas institucionais — só **links** no menu
- Nova paleta ou troca de Nunito Sans

## 6. Arquitetura

| Peça | Onde |
| --- | --- |
| Settings + sanitize | `petshop-core` → `Settings\DefaultSettings` + `Admin\Customizer` (seção **Rodapé da loja**) |
| Render + ícones SVG | Child theme `inc/institutional-footer.php` |
| Menus | Locations `petshop-primary` e `petshop-footer` |
| Defaults | Provisionar se `theme_mod` ainda for `null`; nunca sobrescrever edição salva |

## 7. Sessões

### Sessão 01 — Inventário e Customizer

- [x] Todo campo do inventário §4 tem origem no Customizer ou Menu.
- [x] Nenhum URL/texto comercial novo fica hardcoded no PHP além de defaults de primeira instalação.

### Sessão 02 — Render mínimo (campos)

- [x] Preencher todos os campos no Customizer → rodapé exibe conteúdo.
- [x] Esvaziar campo → trecho some sem erro.

### Sessão 03 — Persistência, docs e validação de campos

- [x] Alterar Instagram/e-mail/selo/CNPJ no painel persiste após reload.
- [x] Scripts `validate-023-footer.php` / `validate-023-footer-browser.mjs` existem.

### Sessão 04 — Composição visual da referência

Corrigir a regra falha da v1 do plano (mockup tratado só como wireframe de campos).

- [x] Grade de 4 colunas no desktop; redes na coluna da marca.
- [x] Atendimento em linhas com círculos teal e ícones reconhecíveis.
- [x] Listas com chevron teal; institucional sem Minha conta/Meus pedidos.
- [x] Quatro pictogramas de selo distintos; faixa em teal-900.
- [x] Logo sem filtro invert; ícones sociais circulares (TikTok/Facebook/Instagram/WhatsApp corretos).
- [x] Legal compacto com ícone de loja.
- [x] Gate PHP exige composição (não só strings).
- [x] Gate browser: fundo escuro, redes na marca, ícones distintos, sem overflow 1440/390.

## 8. Critérios de aceite

- [x] Redes Instagram, Facebook, TikTok e WhatsApp editáveis por URL; item oculto se vazio.
- [x] Atendimento com WhatsApp, e-mail, horário e FAQ (quando preenchidos) editáveis no Customizer.
- [x] Categorias e institucional editáveis via menus WordPress.
- [x] Até 4 selos de confiança com título+texto editáveis; slots vazios ocultos.
- [x] Copyright/nome fantasia, razão social, CNPJ e endereço editáveis.
- [x] Composição e iconografia alinhadas à referência (sessão 04).
- [x] Sem textos comerciais novos dependentes de alteração de código após a entrega.
- [x] Documentação indica **Personalizar → Rodapé da loja** e **Aparência → Menus**.

## 9. Validação sugerida

```bash
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/validate-023-footer.php
docker compose --profile tools run --rm node node /workspace/scripts/validate-023-footer-browser.mjs
```

## 10. Critério de conclusão

O plano só fecha quando o lojista monta o rodapé **em conteúdo** só pelo painel **e** o storefront reproduz a composição da referência (colunas, ícones, selos, legal), usando os tokens do Plano 014.
