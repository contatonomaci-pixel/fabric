# 04 — Códigos de barras, GS1, circulação restrita e etiquetas

## Identificadores distintos
1. **SKU interno (texto)**: `SAL-000001` etc., nunca reutilizar.
2. **Code 128 interno**: gerar símbolo escaneável para fábrica e estoque; pode codificar SKU/lote/etiqueta interna, conforme tipo de etiqueta.
3. **RCN/EAN-13 de circulação restrita**: não é GTIN global. Pode ter prefixos de circulação restrita, mas a composição, uso de peso/preço e aceitação dependem das regras aplicáveis e do **PDV do varejista**. Não escolher prefixos/estrutura como se fossem universalmente aceitos. **Gerar apenas após configurar e homologar perfil por varejista.**
4. **GTIN oficial (EAN-13/GTIN-13)**: somente número válido atribuído/aceito pelo titular autorizado (GS1/registro aplicável). A imagem EAN-13 pode ser gerada, porém isso sozinho não faz o número oficial.
5. **Identificação de lote/expedição**: etiqueta interna QR/Code 128 inicialmente; futuramente GS1-128/DataMatrix quando necessário e autorizado.

## Cadastro de perfis de varejistas
Campos: cliente/loja, `symbology`, formato aprovado, composição de dígitos, prefixo/reserva local, indicação variável (peso ou valor), escala decimal, dígito verificador, conversão, largura de impressão, responsável da homologação, data do teste e evidência. Fluxo: rascunho -> teste em PDV real -> aprovado -> habilitado. Código provisório **não** garante leitura/aprovação em todos os estabelecimentos; cada cliente pode exigir seu próprio identificador local.

## Algoritmo e unicidade
- Sequência interna transacional com `UNIQUE`. Código gerado em backend; nunca confiar apenas na verificação do frontend.
- Reservar namespaces: `internal_code128`, `rcn_retailer:{id}`, `gtin_official`. Estratégia de índice único com scope normalizado não nulo (ver modelo MySQL).
- Para EAN-13, calcular dígito verificador módulo 10 e testar vetor de referência; isso valida **formato**, não atribuição oficial.
- Bloquear alterações de identificador após uso comercial, salvo versionamento e trilha; reservar históricos de códigos desativados.
- Configurar inclusão de SKU/lote/validade por etiqueta sem alterar GTIN comercial a cada lote.

## Venda de peso variável
No varejo compatível, gerar código com informação de peso ou valor **seguindo o layout homologado pelo PDV**. Etiqueta pode variar por pesagem. Armazenar peso líquido exato, preço/kg utilizado, momento e lote de origem; não misturar as regras de EAN comercial com as de etiquetas de balança.

## Impostos/faturamento
Código interno/RCN **não é GTIN** para campos fiscais. Quando sem GTIN, tratar conforme documentação fiscal vigente (ex.: indicação `SEM GTIN` onde aplicável); validar as regras com contador e emissor fiscal antes de implementar emissão real.

## Modelos de impressão
- Etiqueta interna de insumo/lote; etiqueta de unidade/pacote; etiqueta de peso variável; etiqueta de caixa/expedição; rótulo completo.
- Componentes editáveis: empresa/marca, descrição, SKU, lote, fabricação, vencimento validado, peso líquido, instruções, ingredientes, alergênicos, dados nutricionais conforme regras aplicáveis e código escaneável.
- Impressão em PDF/SVG no MVP; ZPL/ESC/POS/agente de impressão, conforme equipamento, em fases posteriores.
- Validar tamanho mínimo, quiet zones, contraste e qualidade por leitura em leitor físico; gerar **código de barras realmente escaneável**, não imitação visual.

## Critérios de aceite
- Falha concorrente cria apenas um código por sequência; teste de unicidade com importação duplicada.
- EAN-13 teste de check-digit com vetores conhecidos.
- Sem perfil de varejista aprovado, API recusa `RCN` para uso comercial.
- GTIN oficial não pode ser inventado pelo gerador interno.
- Um lote novo altera lote/validade da etiqueta, não altera SKU/GTIN comercial.
- Pesagens diferentes geram registros e etiquetas distintos, segundo perfil aprovado.
