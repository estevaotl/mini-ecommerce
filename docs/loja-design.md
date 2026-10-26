# Nuvix: direção da loja

Marca de roupas e acessórios autorais para uma experiência de compra direta e visual.

Paleta: tinta #152c47, azul #294cc8, amarelo #f5edaa, lilás #e7e5f4, papel #f6f7f9 e coral #ef755d. Syne nos títulos e na marca; Manrope no texto e nos controles.

Abertura assimétrica: texto grande alinhado à esquerda em uma superfície amarela, fotografia de campanha à direita; catálogo limpo abaixo. O gesto característico é o recorte curvo da campanha, repetido discretamente na história da marca.

    marca           coleção / nossa ideia        busca / sacola
    [ frase grande e ação ][ fotografia editorial ]
    categorias                           ordenação
    [ peça ][ peça ][ peça ][ peça ]
    [ ideia da marca                 ][ detalhe ]

Revisão: descartados dashboard, cartões de indicadores e gradientes. A composição vem de uma campanha de moda, com os produtos e suas variações como conteúdo principal. Fotografias demonstrativas não representam um estoque comercial real.

Interações: detalhe acessível com cores e tamanhos reais do cadastro; sacola com quantidade e remoção; CEP, cupom e resumo antes do pedido. Foco visível, dialog nativo, Escape, mensagens de estado, movimento reduzido e layout móvel.

Imagens criadas com a ferramenta integrada imagegen. Prompts finais em imagens-prompts.md; arquivos finais em app/public/assets/images/.

## Refinamentos da loja

A paleta e as fontes continuam guiando a composição. Os cartões exibem cores reais do cadastro e levam à variante escolhida. A página de peça dá mais espaço à fotografia, oferece ampliação de detalhes e separa seleção, descrição e frete.

No celular, o cabeçalho usa uma segunda linha para a navegação, com busca, favoritos e sacola disponíveis na primeira. A entrega tem uma sequência real de peças, endereço e revisão; no desktop o resumo acompanha a rolagem, e no celular uma barra mantém total e acesso à revisão visíveis. A barra se recolhe ao focar os campos e abrir diálogos.

Revisão da direção: a numeração representa etapas de compra; as cores são opções reais de produto; a ampliação usa a imagem existente. Nenhum desses elementos é decoração sem função. A gestão será tratada em uma etapa posterior.

## Acabamento antes da gestão

No celular, textos de categorias, ordenação, preços e opções ganharam mais corpo. Os principais controles de toque têm ao menos 44 pixels; as ações da sacola podem quebrar de linha em telas estreitas. A ação dos cartões usa “Ver opções” para caber sem ocupar a fotografia.

A galeria das quatro peças de demonstração acompanha a cor escolhida e oferece frente e costas; a ecobag também tem vistas em outros ângulos. A foto da sacola corresponde à variante selecionada. A página completa preserva a cor escolhida no detalhe rápido. As novas imagens continuam explicitamente ilustrativas.

Na entrega, cada campo inválido tem mensagem própria e associação acessível. Um resumo recebe foco após uma confirmação inválida e leva aos campos, sem apagar os demais dados. O servidor também devolve os campos que precisam de correção.

O rodapé separa exploração e ajuda. A página de ajuda concentra medidas, frete, cupons, favoritos e condições do ambiente de demonstração. Não foram inventados contato comercial, prazo de entrega ou política de trocas.
