# Plano 024 — Carrossel do banner promocional

**Status:** Concluído  
**Data:** 2026-08-19  
**Branch sugerida:** `024-carrossel-banner-promocional`  
**Dependências:** [011-banners-gerenciaveis-home.md](./011-banners-gerenciaveis-home.md) e [014-evolucao-identidade-visual-autelle.md](./014-evolucao-identidade-visual-autelle.md)  
**Origem:** pedido de transformar o banner promocional da Home em carrossel com até 3 imagens, tempo de visualização configurável (padrão 10 s) e controles alinhados à referência (setas laterais circulares + indicadores inferiores).

## 1. Objetivo

Evoluir a faixa `petshop/home-campaigns` para um carrossel promocional: o cliente cadastra até **3** banners no Gutenberg, define o **tempo de visualização de cada imagem** (padrão 10 segundos) e a loja troca os slides automaticamente, com setas e indicadores visíveis sobre a arte.

Uma campanha válida continua sendo um banner estático. Duas ou três habilitam o carrossel. Não haverá shortcode, CPT, painel paralelo nem texto/imagem comercial fixos no código.

## 2. Decisão de produto

- Reutilizar os blocos existentes `petshop/home-campaigns` (contêiner) e `petshop/home-campaign` (filho). Não criar um segundo sistema de banners.
- Limite de **3** campanhas-filhas no editor e no render da loja. O quarto banner não é publicado.
- Tempo de visualização **por imagem**, no inspector do banner-filho, intervalo **3–60 s**, padrão **10 s**.
- Autoplay somente com 2 ou 3 slides válidos. Pausa ao passar o mouse, ao focar um controle e quando a aba fica oculta. `prefers-reduced-motion: reduce` desliga a troca automática; setas e indicadores continuam operáveis.
- Controles sobrepostos à arte, como na referência: botões circulares teal às laterais (chevrons branco) e indicadores no centro inferior (ativo branco, inativo teal). Alvo de toque mínimo 44 × 44 px.
- Navegação manual (setas, indicadores, teclado, swipe) permanece. Troca automática não anuncia cada slide para leitores de tela; a troca manual continua anunciada.
- Modalidades **arte final** e **campanha editorial** entram no mesmo carrossel.

### Fora de escopo

- agendamento por banner;
- mais de 3 slides;
- autoplay com som, parallax ou fade essencial para compreender a oferta;
- alterar hero, header, vitrines, WooCommerce, Blocksy ou WordPress Core.

## 3. Conteúdo administrável por rota

### Rota `/` — Home

| Item | Onde o cliente edita | Regra de exibição |
| --- | --- | --- |
| Posição da faixa | **Páginas → Home**, bloco `Banners de campanha` | Após benefícios e antes de “Compre por categoria”. |
| Imagens (até 3) | Blocos-filho `Banner de campanha` → Biblioteca de mídia | Desktop obrigatória; mobile opcional. |
| Texto alternativo | Mesmo bloco → painel lateral | Obrigatório para publicar. |
| Link / CTA | Mesmo bloco → URL | Obrigatório. |
| Tempo de visualização | Mesmo bloco → **Tempo de visualização (segundos)** | Padrão 10 s; vale no carrossel (2 ou 3 banners). |
| Ordem | Controles nativos do Gutenberg | Os 3 primeiros válidos na ordem salva. |
| Setas e indicadores | Gerados pelo bloco | Só com 2 ou 3 banners válidos. |

## 4. Arquitetura e arquivos

| Área | Arquivos | Responsabilidade |
| --- | --- | --- |
| Plugin | `blocks/home-campaigns/`, `blocks/home-campaign/`, `includes/class-home-campaign-blocks.php` | Limite 3, duração, render, autoplay, editor. |
| Tema | `petshop-theme/style.css` | Controles sobrepostos, tokens, foco, mobile. |
| Docs | `docs/guia-edicao-home.md` | Instruções não técnicas. |
| Validação | `scripts/validate-024-*.php`, `scripts/validate-024-*-browser.mjs` | Limite, duração, persistência, overlay e a11y. |

Atributos novos serializam em `post_content`. Migrações existentes não podem alterar campanhas já salvas. Banners antigos sem duração usam 10 s.

## 5. Sessão única — Carrossel, limite, tempo e controles

- [x] Limitar o contêiner a 3 filhos no editor e no PHP.
- [x] Atributo `durationSeconds` (padrão 10) no banner-filho, editável no inspector.
- [x] Autoplay com pausa em hover/foco/aba oculta e sem autoplay em movimento reduzido.
- [x] Controles sobrepostos alinhados à referência; alvo ≥ 44 px.
- [x] Documentar em `docs/guia-edicao-home.md`.
- [x] Gates PHP/browser e persistência após reprovisionamento.

**Gate verificável**

- [x] 1 banner válido: estático, sem controles.
- [x] 2 ou 3 banners: carrossel com setas laterais, indicadores inferiores e autoplay no tempo cadastrado (padrão 10 s).
- [x] 4º banner não aparece na loja; o editor impede o appender após 3.
- [x] Duração 3–60 s persiste após salvar, recarregar e reprovisionar.
- [x] `prefers-reduced-motion` desliga autoplay; teclado e clique continuam.
- [x] Desktop/mobile sem overflow; hero permanece acima da faixa.

## 6. Riscos

| Risco | Mitigação |
| --- | --- |
| Autoplay atrapalha leitura | Pausa em hover/foco; movimento reduzido; tempo mínimo 3 s. |
| Controles ilegíveis sobre a arte | Botões teal 900, chevron branco, sombra; dots com alvo 44 px. |
| Bloco inválido após novo atributo | Default 10; atributo só no comentário do bloco; sem mudança de `save()` incompatível. |
| Quarto banner silencioso | Appender oculto + aviso se houver mais de 3 + corte no render. |
