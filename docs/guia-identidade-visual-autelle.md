# Guia de Identidade Visual — AUTellê Moda Pet

> Documento oficial proposto para agentes de IA, designers e desenvolvedores responsáveis por criar interfaces, banners, anúncios, componentes e materiais visuais da AUTellê Moda Pet.

> **Validação de marca antes da publicação:** usar sempre o logotipo original aprovado. A grafia textual da marca deve ser confirmada contra esse ativo antes de alterar menus, SEO, embalagens ou peças públicas. Este guia não autoriza redesenhar, vetorizar ou criar variações do logo.

## 1. Direção da marca

A identidade deve transmitir:

- cuidado e proximidade;
- acabamento profissional;
- alegria sem aparência infantil;
- confiança para tutores e lojistas;
- comunicação comercial clara, acessível e objetiva.

O resultado esperado é uma estética de **pet shop moderno, acolhedor e profissional**, com predominância de verde-petróleo, detalhes em laranja e áreas claras para preservar leveza e legibilidade.

## 2. Paleta de cores

### Cores principais

| Token | Hex | Uso recomendado |
|---|---:|---|
| `brand-teal-900` | `#004F50` | Fundos escuros, banners, rodapé e áreas institucionais |
| `brand-teal-700` | `#126E70` | Cor principal de interface, ícones, links e elementos de destaque |
| `brand-teal-500` | `#2B9292` | Bordas, detalhes decorativos e estados validados |
| `brand-aqua-400` | `#58C2C7` | Apoio à marca, ilustrações e elementos leves |
| `brand-orange-600` | `#E9530D` | Chamadas promocionais, superfícies de destaque e ícones; não usar com texto branco pequeno |
| `brand-orange-500` | `#F47721` | Destaques, selos, preços e elementos gráficos |
| `brand-orange-action` | `#C94B0B` | CTA preenchido com texto branco; contraste AA para texto normal |

### Cores neutras

| Token | Hex | Uso recomendado |
|---|---:|---|
| `neutral-950` | `#252426` | Títulos e textos de maior contraste |
| `neutral-700` | `#5E5D61` | Textos secundários |
| `neutral-300` | `#D8D9DB` | Bordas e divisores |
| `neutral-100` | `#F2F3F4` | Fundos alternativos e campos |
| `cream-50` | `#FAF7F1` | Fundo acolhedor para banners e campanhas |
| `white` | `#FFFFFF` | Cards, cabeçalho e áreas de respiro |

### Cores funcionais

| Token | Hex | Uso recomendado |
|---|---:|---|
| `whatsapp` | `#25D366` | Exclusivamente em ícones ou referências oficiais ao WhatsApp |
| `focus-ring` | `#005FCC` | Foco de teclado; cor funcional independente da paleta comercial |

### Proporção recomendada

- 55% a 70% de tons claros e neutros;
- 20% a 35% de verde-petróleo;
- até 10% de laranja;
- aqua apenas como apoio.

O laranja deve chamar atenção. Evitar usá-lo em grandes áreas simultaneamente.

## 3. Combinações aprovadas e contraste

### Interface principal

- fundo: `#FFFFFF` ou `#FAF7F1`;
- título: `#252426`;
- texto: `#5E5D61`;
- botão principal: `#C94B0B` com texto `#FFFFFF`;
- botão secundário: fundo transparente, borda e texto `#126E70`.

### Banner escuro

- fundo: gradiente entre `#004F50` e `#126E70`;
- título: `#FFFFFF`;
- texto: `#F2F3F4`;
- CTA preenchido: `#C94B0B` com texto `#FFFFFF`;
- `#F47721` e `#E9530D`: selos, ícones ou superfícies cuja combinação de contraste tenha sido validada;
- ornamentos: `#58C2C7` com baixa opacidade.

### Banner claro

- fundo: `#FAF7F1`;
- título: `#004F50` ou `#252426`;
- texto: `#5E5D61`;
- CTA: `#C94B0B` com texto `#FFFFFF`;
- elementos decorativos: `#58C2C7` e `#F47721`.

### Regras obrigatórias

- Texto normal precisa alcançar contraste mínimo de 4,5:1; texto grande e componentes não textuais seguem os limiares WCAG aplicáveis.
- `#E9530D` e `#F47721` não devem receber texto branco em tamanho normal: seus contrastes são insuficientes.
- Foco de teclado deve ser sempre visível e não depende de hover ou cor comercial.
- Nenhuma informação essencial pode ser transmitida apenas por cor.

## 4. Tipografia

Usar fontes sem serifa, amigáveis e de alta legibilidade.

### Família

1. **Nunito Sans** — família principal da interface e campanhas;
2. **Inter** — alternativa quando Nunito Sans não estiver disponível;
3. fallback: `system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif`.

Usar uma família principal por superfície. Poppins é permitida apenas em campanha excepcional aprovada, nunca como dependência padrão do storefront.

### Hierarquia

| Elemento | Peso | Características |
|---|---:|---|
| Título de campanha | 700–800 | Grande, compacto e com forte contraste |
| Título de seção | 700 | Objetivo e curto |
| Subtítulo | 600–700 | Pode usar verde-petróleo ou laranja validado |
| Texto corrido | 400–500 | Entrelinha confortável |
| Botão | 700 | Texto curto, nunca em caixa alta integral |
| Microtexto | 400–600 | Apenas para informações auxiliares |

Evitar fontes manuscritas, condensadas ou excessivamente infantis.

## 5. Formas e componentes

### Bordas e sombras

- cantos arredondados;
- raio padrão: `10px`, `16px` e `24px`;
- botões em cápsula podem usar `999px`;
- cards promocionais podem usar até `24px`;
- sombra padrão: `0 8px 24px rgba(0, 79, 80, 0.10)`;
- sombra de destaque: `0 8px 24px rgba(0, 79, 80, 0.14)`.

Evitar sombras pretas pesadas, efeitos metálicos, brilho neon ou profundidade exagerada.

Em banners escuros, é permitido usar borda externa em aqua, filete interno sutil, divisores finos e ícones decorativos entre 8% e 20% de opacidade.

## 6. Botões e chamadas para ação

### CTA principal acessível

```css
background: #C94B0B;
color: #FFFFFF;
border-radius: 999px;
font-weight: 700;
```

Usar para ações comerciais: comprar agora, ver produtos, falar no WhatsApp, conhecer kits e aproveitar oferta.

### CTA secundário

```css
background: transparent;
color: #126E70;
border: 2px solid #126E70;
border-radius: 999px;
font-weight: 700;
```

Evitar mais de dois CTAs concorrentes no mesmo bloco.

## 7. Fotografia

### Estilo aprovado

- cães e gatos bem cuidados;
- interação natural entre pessoas e pets;
- enquadramento luminoso e emocional;
- acessórios visíveis e coerentes com os produtos da loja;
- ambientes limpos, profissionais ou acolhedores;
- aparência realista.

### Direção de cor

- luz quente e natural;
- verde-petróleo pode estar no cenário, roupa ou acessório, sem manipular a cor real do produto;
- detalhes em laranja aplicados com moderação, sem tingir fotos de catálogo;
- fundo desfocado quando houver texto sobreposto.

Evitar animais artificiais, anatomia incorreta, saturação excessiva, cenários luxuosos desconectados da marca, imagens genéricas sem relação com acessórios pet e estética infantilizada.

## 8. Padrão de mídia para o ecommerce

### Matriz de proporções

| Mídia | Desktop | Mobile | Uso e composição |
|---|---:|---:|---|
| Secao de atendimento da Home | 1920 x 640 px (3:1) | 1080 x 1350 px (4:5) | Usar no grupo `petshop-support-banner`. O CSS coloca copy em coluna propria e a imagem ocupa somente o painel de midia. Em desktop largo a imagem renderiza em 3:1; em tablet a secao empilha e preserva 3:1; em mobile renderiza 4:5. |
| Hero institucional | 2400 × 900 px (8:3) | 1080 × 1350 px (4:5) | Copy é HTML/Gutenberg: ocupar até 40% da largura no desktop, à esquerda. Pet/pessoa/produto ocupa o lado direito. Nunca gerar texto ou CTA na foto. |
| Banner promocional ou sazonal editorial | 1920 × 640 px (3:1) | 1080 × 1350 px (4:5) | Reservar cerca de 40% para copy da interface e 60% para foto/produto. Usar uma ação principal. |
| Banner de arte final | 1920 × 640 px (3:1) | 1080 × 1350 px (4:5) | Permitido para peça fechada com texto incorporado; exigir as duas versões e área segura de 8% em todos os lados. |
| Imagem principal do produto | 1600 × 1600 px (1:1) | mesma mídia | Fundo limpo, produto em 75–85% do quadro, cor e material fiéis; sem texto, selo ou marca d’água. |
| Galeria/detalhe de produto | 1600 × 1600 px (1:1) | mesma mídia | Mostrar escala, fecho, acabamento, aplicação ou variação. Manter iluminação e fundo coerentes com a foto principal. |
| Categoria ou coleção | 1600 × 900 px (16:9) | 1080 × 1350 px (4:5), se houver copy sobreposta | Posicionar o assunto no centro ou no lado oposto à copy. |
| Foto editorial de apoio | 1600 × 1200 px (4:3) | 1080 × 1350 px (4:5), quando necessário | Uso em blocos Gutenberg, páginas institucionais e conteúdo de escolha. |
| Avatar/rede social | 1080 × 1080 px (1:1) | mesma mídia | Não substitui logo de navegação. |
| Favicon/ícone de app | 512 × 512 px (1:1) | mesma mídia | Fornecer a partir de ativo oficial, sem recriar o logo. |

As dimensões são de entrega, não de exibição literal. O WordPress gera versões responsivas; não criar cópias manuais para cada largura.

### Áreas seguras e ponto focal

- Hero e banner editorial desktop: não posicionar rosto, pet, produto ou detalhe essencial nos 40% reservados à copy; manter margem de 64 px na composição de 1920 px.
- Hero e banner editorial mobile: usar arte vertical própria. Manter o assunto na faixa central/superior e deixar a copy fora da imagem, em blocos Gutenberg.
- Arte final: manter texto, logo, preço e CTA a pelo menos 8% das quatro bordas; não presumir que o corte desktop funciona em mobile.
- Produto: enquadrar o item inteiro, sem corte em laços, gravatas, fechos ou acabamento. Usar o mesmo ângulo/fundo na imagem principal de uma mesma variação.
- Secao de atendimento da Home: nao aplicar a regra de reservar 40% para copy dentro da imagem. Essa secao usa o CSS `petshop-support-banner`: copy em coluna separada, midia em painel proprio, desktop 3:1 e mobile 4:5 com `object-fit: cover`.
- Secao de atendimento da Home desktop: preencher o quadro 1920 x 640 px com assunto no centro ou levemente a direita; nao usar faixa vazia, degrade reservado para texto, telefone, CTA, logo do WhatsApp ou copy dentro da arte.
- Secao de atendimento da Home mobile: criar arte vertical propria em 1080 x 1350 px; nao reaproveitar corte horizontal.
- Banner promocional/sazonal full-width: a altura renderizada deve seguir 3:1. Em container de 1280 px, a altura esperada e aproximadamente 427 px; nao limitar para 320 px quando a fonte for 1920 x 640 px.
- Antes de publicar, revisar o ponto focal no Gutenberg e nos breakpoints de 390, 768, 1024 e 1440 px.

### Formatos, otimização e acessibilidade

- Preferir **WebP** para fotos e banners; usar **PNG** apenas para transparência ou gráficos que necessitem dela; JPEG é aceito como fonte/fallback.
- Não exigir AVIF enquanto o ambiente WordPress não demonstrar suporte e geração responsiva confiáveis.
- Entregar imagem no tamanho da matriz, sem ampliar arquivo pequeno. Evitar arquivos acima de 500 KB em banners e 300 KB em fotos de produto quando qualidade aceitável permitir; medir a página final em vez de confiar apenas no tamanho do arquivo.
- Usar nomes descritivos, por exemplo `laco-pet-cetim-rosa-frente.webp` e `campanha-inverno-2026-desktop.webp`.
- Todo arquivo exibido precisa de texto alternativo contextual na Biblioteca de mídia. Alt descreve o que a imagem comunica; não repetir “imagem de”, não incluir preço/CTA e usar alt vazio apenas quando a imagem for puramente decorativa.
- Toda foto deve ser enviada e substituída pela Biblioteca de mídia. Produtos e categorias são administrados no WooCommerce; hero, campanhas e fotos editoriais são administrados no Gutenberg.

## 9. Ilustrações e ícones

Usar ícones lineares, arredondados e simples: patas, corações, caixas, etiquetas, caminhões, presentes, laços e atendimento.

- traço uniforme e poucos detalhes;
- uso preferencial de verde-petróleo;
- laranja apenas para chamar atenção;
- elementos decorativos entre 8% e 20% de opacidade.

## 10. Banners promocionais e conteúdo editável

### Estrutura recomendada

1. título curto e dominante;
2. mensagem complementar de uma ou duas linhas;
3. benefício comercial claro;
4. CTA destacado;
5. fotografia ou produto como apoio;
6. elementos decorativos discretos.

Em desktop, reservar aproximadamente 40% da largura para texto e 60% para imagem, com margem interna mínima de 48px. Manter textos afastados das bordas e de áreas de corte responsivo.

### Regra de edição no WordPress

Para campanhas recorrentes, título, texto, benefício, CTA, destino, imagem e texto alternativo devem ser editáveis em **Páginas → Home**, no canvas Gutenberg. A imagem é apoio visual; não deve ser o único lugar da oferta quando a campanha precisar de atualização rápida, SEO ou leitura assistiva.

Artes finais com texto incorporado continuam permitidas quando forem uma peça fechada. Nesse caso, imagem desktop/mobile, alt contextual e link continuam obrigatoriamente editáveis pela Biblioteca de mídia e pelo bloco de campanha.

## 11. Tom de comunicação

A linguagem deve ser clara, acolhedora, comercial sem agressividade, profissional e próxima de tutores e donos de pet shop.

Expressões adequadas:

- “Acessórios que valorizam cada banho e tosa”;
- “Acabamento cuidadoso para tutores e profissionais”;
- “Encontre a melhor opção para seu pet ou negócio”;
- “Produtos à pronta entrega”;
- “Condições especiais para pet shops”.

Evitar diminutivos excessivos, linguagem infantilizada, promessas absolutas, muitos pontos de exclamação, urgência falsa, textos longos em banners e frases sem benefício concreto.

## 12. Regras para geração de imagem

```text
Identidade visual da AUTellê Moda Pet. Utilizar verde-petróleo profundo,
verde médio, aqua, laranja como detalhe, creme e branco. Estética moderna,
acolhedora e profissional para ecommerce pet. Cantos arredondados, formas
orgânicas, tipografia Nunito Sans ou sans-serif humanista, ícones lineares de
patas e corações com baixa opacidade. Alto contraste e composição limpa, sem
aparência infantil, sem excesso de efeitos 3D e sem poluição visual. Preservar
a cor real de acessórios e produtos. Não inserir textos, preços, CTA ou
logotipo na imagem; reservar área segura para conteúdo da interface.
```

### Restrições obrigatórias

- não alterar o nome da marca;
- não inventar variações do logotipo;
- não aplicar laranja como cor dominante do fundo;
- não misturar cores adicionais sem motivo;
- não usar fontes manuscritas;
- não gerar textos pequenos ou excessivos dentro da imagem;
- não usar efeitos de vidro, metal ou neon;
- não confundir a marca com clínica veterinária ou loja de ração genérica.

## 13. Tokens CSS oficiais

```css
:root {
  --brand-teal-900: #004F50;
  --brand-teal-700: #126E70;
  --brand-teal-500: #2B9292;
  --brand-aqua-400: #58C2C7;
  --brand-orange-600: #E9530D;
  --brand-orange-500: #F47721;
  --brand-orange-action: #C94B0B;
  --neutral-950: #252426;
  --neutral-700: #5E5D61;
  --neutral-300: #D8D9DB;
  --neutral-100: #F2F3F4;
  --cream-50: #FAF7F1;
  --white: #FFFFFF;
  --whatsapp: #25D366;
  --focus-ring: #005FCC;
  --radius-sm: 10px;
  --radius-md: 16px;
  --radius-lg: 24px;
  --radius-pill: 999px;
  --shadow-card: 0 8px 24px rgba(0, 79, 80, 0.10);
  --shadow-featured: 0 8px 24px rgba(0, 79, 80, 0.14);
}
```

## 14. Checklist antes de aprovar uma peça

- A peça usa predominantemente verde-petróleo, branco ou creme?
- O laranja está reservado para destaque e CTA?
- O CTA preenchido usa `brand-orange-action` ou outra combinação AA validada?
- A leitura continua clara em tamanho reduzido?
- Existe apenas uma ação principal evidente?
- A peça parece profissional sem ficar fria?
- Pets e produtos estão relacionados ao contexto da loja?
- Cantos, ícones e formas seguem linguagem arredondada?
- Há espaço suficiente entre elementos?
- O material evita aparência infantil, genérica ou artificial?
- Texto, imagem, alt e CTA são editáveis no WordPress quando a peça for campanha recorrente?
- A proporção, área segura e ponto focal correspondem ao tipo de mídia da seção 8?
- Existe uma versão mobile própria quando a mídia contém arte final ou depende de composição horizontal?

## 15. Regra de consistência

Quando houver dúvida, priorizar nesta ordem:

1. legibilidade;
2. acessibilidade e edição administrativa do conteúdo;
3. coerência com a paleta;
4. clareza comercial;
5. aparência profissional;
6. elementos decorativos.

Elementos decorativos nunca devem competir com produto, mensagem ou CTA.

> Este guia consolida a direção visual proposta para o storefront. Antes da publicação definitiva, validar grafia da marca e cores contra os arquivos originais do logotipo e eventual manual oficial. Em conflito, os ativos originais aprovados pela marca prevalecem.
