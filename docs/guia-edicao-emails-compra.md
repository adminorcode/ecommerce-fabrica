# Edição dos e-mails de compra

Os e-mails HTML do WooCommerce usam o casco visual da Auteliê Moda Pet como padrão global. Quando o e-mail possui pedido, os avisos de compra exibem também status, resumo, rastreio, CTA e caixas de apoio.

## Onde editar

| Conteúdo | Origem |
| --- | --- |
| Logo | **Aparência → Personalizar → Identidade do site** |
| Assunto, heading e conteúdo adicional de cada e-mail | **WooCommerce → Configurações → E-mails** |
| Caixa **Informação importante** | **Aparência → Personalizar → Conteúdo da loja** |
| Caixa **Precisa de ajuda?** | **Aparência → Personalizar → Conteúdo da loja** |
| WhatsApp e e-mail da caixa de ajuda | **Aparência → Personalizar → Rodapé da loja** |
| Tagline do rodapé do e-mail | **Aparência → Personalizar → Rodapé da loja → Descrição curta no rodapé** |
| Nome da loja e URL | Ajustes gerais do WordPress |
| Itens, total, entrega, pagamento e rastreio | Dados reais do pedido WooCommerce |

## Regras

- A caixa **Informação importante** some quando título e corpo ficam vazios.
- A caixa **Precisa de ajuda?** permanece como apoio funcional e exibe WhatsApp ou e-mail somente quando esses campos existem no rodapé.
- O botão dos e-mails de fatura e pedido em espera é **Pagar agora**; os demais usam **Acompanhar meu pedido**.
- O e-mail de pagamento confirmado mostra o primeiro passo atual da linha do tempo; o e-mail de pedido concluído mostra os quatro passos preenchidos.
- O casco de marca se aplica aos e-mails HTML; o layout dos e-mails em texto puro não foi alterado.
