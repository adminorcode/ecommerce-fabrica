# Operação — fila de personalizações

Público: equipe da loja e responsável técnico. Requer a capacidade
`manage_petshop_personalizations` (atribuída a `administrator` e `shop_manager`).

## 1. Fila de produção

**WooCommerce → Personalizações**

- Filtros por estado no topo (Todas, Fila ativa e cada estado individual).
- Busca por identificador público ou pelo resumo da arte.
- Colunas: prévia, arte, produto, pedido, cliente, estado e data de atualização.
- Clique no resumo para abrir o detalhe.

Somente pedidos pagos (processando/concluído) entram automaticamente em **Para revisar**.
Pedidos sem pagamento ficam em **Aguardando pagamento**.

## 2. Estados

```
draft → cart → awaiting_payment → review → approved → in_production → completed
                     │                │
                     └────────────────┴──────────→ cancelled
```

| Estado | Significado |
| --- | --- |
| Rascunho | Arte confirmada, ainda sem carrinho. |
| No carrinho | Item no carrinho do cliente. |
| Aguardando pagamento | Pedido criado, pagamento não confirmado. |
| Para revisar | Pedido pago; conferir arquivo. |
| Aprovado | Arquivo conferido internamente. |
| Em produção | Impressão/produção iniciada. |
| Concluído | Estado final. |
| Cancelado | Pedido cancelado, falho, reembolsado ou carrinho abandonado além do prazo. |

Estados finais (Concluído e Cancelado) não aceitam novas transições. Cada mudança grava
usuário, data, estado anterior e observação no histórico do detalhe.

## 3. Detalhe da personalização

- Prévia grande e lista de arquivos: **prévia**, **imagem original do cliente** e **PNG de produção**
  (com bytes e dimensões).
- Ficha com produto, pedido, área de impressão em mm/DPI/px e hash do snapshot.
- **Baixar pacote do pedido (ZIP)** reúne todos os arquivos daquele pedido.
- Bloco **Mover estado** com observação interna opcional.
- Histórico completo abaixo.

Todo download exige capacidade e nonce. Os arquivos não têm URL pública: são transmitidos por
endpoint autorizado e o caminho real no servidor nunca aparece.

## 4. Pedido

Na tela do pedido (HPOS) existe o card **Personalizações** com prévia, resumo, estado, link para a
fila e o ZIP do pedido. O item do pedido guarda ID público, resumo, hash e versão do schema, então
o pedido continua compreensível mesmo sem a tela operacional.

Alterar o produto, o mockup, a máscara ou o preço depois **não** muda pedidos antigos.

## 5. Retenção

**WooCommerce → Configurações → Produtos → Personalizações**

| Configuração | Padrão | Efeito |
| --- | --- | --- |
| Retenção de rascunhos | 7 dias | Rascunhos sem pedido são excluídos (registro + arquivos). |
| Retenção de carrinhos abandonados | 14 dias | Item vai para Cancelado; arquivos preservados. |
| Retenção de pedidos cancelados | 90 dias | Prazo documentado para revisão manual. |
| Retenção de pedidos concluídos | 365 dias | Guarda de originais e PNG de produção. |
| Tamanho máximo de upload | 8 MB | Recusa antes de gravar. |
| Máximo de megapixels | 40 MP | Recusa antes de gravar. |

A mesma tela mostra a saúde do storage privado. Desativar o plugin **não** apaga nada; a
desinstalação preserva os dados por padrão.

## 6. Cron e WP-CLI

A limpeza roda diariamente pelo cron (`petshop_personalization_cleanup`) e é idempotente.

```bash
# Diagnóstico: schema, storage, GD/ZIP, capacidades, cron, retenção e contagem por estado
docker compose --profile tools run --rm --no-deps cli wp petshop personalization doctor

# Simulação (não exclui nada) — padrão
docker compose --profile tools run --rm --no-deps cli wp petshop personalization cleanup

# Execução real
docker compose --profile tools run --rm --no-deps cli wp petshop personalization cleanup --execute

# Banco x arquivos: presença e hash SHA-256
docker compose --profile tools run --rm --no-deps cli wp petshop personalization integrity
```

`integrity` nunca exclui nada: apenas relata arquivos ausentes, hashes divergentes e registros
órfãos para decisão manual.

## 7. Storage privado, backup e restore

| Ambiente | Caminho no contêiner | Volume Compose |
| --- | --- | --- |
| Runtime / CLI / backup / restore | `/var/petshop-personalizations` | `petshop_personalization_data` |
| Testes | `/var/petshop-personalizations` | `test_petshop_personalization_data` |

O caminho fica fora do document root e pode ser sobrescrito pela constante
`PETSHOP_PERSONALIZATION_STORAGE`. Se o diretório estiver sob o webroot, dentro de `uploads` ou
não for gravável, o módulo recusa gravar.

Em uma stack que já estava rodando antes deste plano, o volume novo só passa a existir depois de
recriar os contêineres (`docker compose up -d wordpress`); o script de init cria o diretório e
ajusta o dono para `www-data`. Sem isso, `doctor` acusa storage saudável pelo CLI (root) enquanto o
editor falha com “Não foi possível criar o diretório de storage” no navegador.

Um backup consistente precisa de **duas** partes:

1. dump SQL das tabelas `wp_petshop_personalizations`, `wp_petshop_personalization_files` e
   `wp_petshop_personalization_status_history`;
2. cópia do volume `petshop_personalization_data`.

Depois de restaurar, rode `integrity` para confirmar que hashes e arquivos batem. Se banco e
storage vierem de momentos diferentes, o comando aponta as divergências sem excluir nada.

## 8. Diagnóstico rápido

| Sintoma | Verificar |
| --- | --- |
| Botão “Personalizar produto” não aparece | Personalização habilitada? Largura/altura/DPI válidos? Produto comprável e publicado? |
| Editor não abre | `assets/personalizer/vendor/fabric.min.js` presente; console do navegador. |
| Erro ao confirmar a arte | `doctor` (storage e GD); dimensões do PNG precisam bater com mm/DPI do produto. |
| Prévia não carrega para o cliente | Pedido pertence à conta logada? Em e-mail, o link usa a chave do pedido. |
| Upload recusado | Formato (JPEG/PNG/WebP), bytes e megapixels nas Configurações. |
| ZIP indisponível | Extensão ZIP do PHP ausente (`doctor` informa). |

## 9. Limitações conhecidas do MVP

- A área imprimível é um retângulo centralizado no mockup; o recorte irregular vem da máscara
  aplicada ao arquivo final.
- Prévia e PNG de produção são gerados no navegador e validados no servidor (PNG real,
  dimensões exatas, bytes e megapixels). Sem GD instalado o arquivo é aceito sem reprocessamento
  e o `doctor` registra o aviso.
- O Cart Block exibe o resumo textual da personalização; a miniatura aparece no carrinho clássico,
  no admin, no e-mail e em Minha conta.
- Uma superfície, um upload de imagem por arte e produtos simples no lançamento.
