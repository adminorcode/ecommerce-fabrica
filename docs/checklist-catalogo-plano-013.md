# Checklist de cadastro do catálogo — Plano 013

Edite todos os itens em **Produtos → Todos os produtos** e as mídias na **Biblioteca**. O provisionamento não corrige nem sobrescreve automaticamente o catálogo real.

## Por produto

- nome, SKU único, status e categoria corretos;
- preço normal/promocional e estoque coerentes;
- descrição curta e completa sem texto provisório;
- fotografia principal e galeria com texto alternativo útil;
- materiais, conteúdo da embalagem, cuidados, medidas e prazo de produção;
- produto variável: Cor e Tamanho globais, combinações válidas, preço, estoque, SKU, imagem e prazo por variação;
- guia de medidas selecionado quando aplicável;
- sinalização para personalização somente nos produtos aprovados para o Plano 012.

## Auditoria somente leitura

```powershell
docker compose --profile tools run --rm --no-deps cli wp eval-file /var/www/html/scripts/audit-013-catalog.php
```

O relatório lista inconsistências, mas não altera produtos. Corrija cada item no painel e execute novamente.
