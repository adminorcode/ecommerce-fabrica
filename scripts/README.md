# Scripts

Scripts repetíveis de bootstrap, validação, importação de dados de demonstração e automação local serão armazenados aqui.

Scripts devem ser:

- idempotentes quando possível;
- documentados;
- seguros para execução local;
- independentes de segredos versionados.

## Catálogo demonstrativo do Plano 004

A ordem segura é taxonomia → seed → Home → validação. No runtime Docker do projeto:

1. execute `Petshop\Core\StorefrontCatalog::maybeEnsureCategories()` por WP-CLI;
2. copie `seed-storefront-placeholders.php` e `data/004b-products.json` para o mesmo diretório no contêiner e execute o seed com `wp eval-file`;
3. execute `Petshop\Core\StorefrontExperience::maybeEnsureStorefront()` por WP-CLI;
4. execute `validate-004b.php` e `validate-storefront.php`.

O seed preserva qualquer SKU já existente. Produtos criados por ele recebem `_petshop_placeholder_004b=1`; imagens recebem chave, fonte, autor, licença e alt próprios. Reruns não duplicam nem restauram campos removidos deliberadamente no painel.
