# Fase 1 — Estrutura, catálogo e identificadores internos

Autorizada pelo usuário em 2026-11-07. Inclui empresas, fábricas, depósitos, famílias, produtos, variantes, unidades, SKUs, preços e Code128 interno. Não movimenta estoque, registra pesagens, produz, vende, emite documento fiscal ou gera RCN/GTIN. Resultados de qualidade atualizados em [STATUS](STATUS.md).

Entrega em revisão no [PR #1](https://github.com/contatonomaci-pixel/fabric/pull/1), branch `feat/phase-1-catalog`. [CI da implementação aprovada](https://github.com/contatonomaci-pixel/fabric/actions/runs/42387827301): 39 testes/287 assertions em cada PHP, 12 SPA e 14 E2E; ZBar verificou o PNG na CI. Integração à main pendente de revisão, sem deploy.

## Atualizar uma instalação local da Fase 0

Em `apps/api`, com as extensões **GD** e **BCMath** habilitadas no PHP 8.3+:

```bash
composer install
php artisan migrate
php artisan db:seed
```

O seeder base instala as permissões e unidades padrão por empresa, sem criar usuário ou preço. Para repetir a demonstração, mantenha `DEMO_PASSWORD` local conforme o guia e execute:

```bash
php artisan db:seed --class=DemoSeeder
```

Somente `local/testing`: uma empresa e conta fictícias, uma fábrica/depósito fictícios, três famílias/produtos, cinco variantes e sete SKUs. Coxinha Frango 25g tem UN, PCT20 e KG; há também Coxinha Carne 25g, Bolinho Carne 50g e Pizza Frango/Carne 400g. Pesos são exemplos nominais fictícios; todos os preços começam **não definidos**. Repetir o seed não duplica nem altera preços/cadastros existentes. As contas extras `catalog-desktop@example.test` e `catalog-mobile@example.test` existem apenas no banco descartável do runner E2E, com senha efêmera, nunca no seeder normal.

Em `apps/web`: `npm ci` e `npm run dev`. Acesso em `http://localhost:5173`, com API/worker/serviços conforme o guia da fundação. O Compose original permanece byte a byte intacto.

## Uso das telas

1. **Empresas e unidades**: proprietário pode criar uma empresa própria, editar, desativar e reativar; o vínculo proprietário e as cinco unidades padrão são criados em transação. Fuso horário deve ser válido na base IANA. Uma conta precisa ter `companies.create` em empresa ativa existente para criar outra; não existe cadastro público ou concessão de papel pelo cliente.
2. **Fábricas e depósitos**: selecione a empresa e cadastre fábricas e seus depósitos. Tipo câmara fria é apenas classificação de cadastro, sem inventar limites sanitários, sensores ou controle de estoque.
3. **Catálogo e identificadores**: selecione empresa, família/produto, variante e apresentação comercial. Busca e paginação são feitas na API. Filtros avançados cobrem família, variante quando aplicável, tipo e estado. Seletores buscam até 20 opções por vez; refine o nome para localizar registros adicionais.
4. **SKUs**: preço é independente por apresentação, opcional e informado em texto decimal. `12,3456` no formulário vira `12.3456` na API, sem cálculo com ponto flutuante. Histórico mostra autor, data no fuso da empresa e preços anteriores/novos. Desativação exige confirmação e preserva os registros e códigos.
5. **Code128**: criação de SKU gera e reserva o código interno por padrão. Links SVG/PNG/PDF de teste ficam no cadastro. Não representam GTIN nem homologação de varejista ou impressora.

## Regras adotadas

- Mantida a convenção da fundação: BIGINT interno e ULID público. `company_id` é obrigatório nos cadastros; FKs compostas incluem empresa para impedir vínculos cruzados também no banco. IDs numéricos não são expostos nas respostas de cadastro.
- Unidades padrão por empresa: `UN` (contagem, fator 1), `g` (massa, fator 1), `KG` (massa, fator 1000), `PCT`/`CX` (embalagem, fator global nulo). Conteúdo de PCT/CX é declarado diretamente em unidades por SKU (`packaging_qty`), não como fator universal de pacote/caixa. Conversões e transformações reais de estoque não estão implementadas nesta fase.
- Quantidades/pesos: `DECIMAL(18,6)`. Preço unitário configurável: `DECIMAL(18,4)`, retornado como texto; valores finais de operações futuras terão arredondamento próprio. Não há preço automático por peso nominal. Venda por `g`/`KG` é cadastrada como peso variável; apresentações fixas usam UN/PCT/CX. Não há saldo calculado nem pesagem simulada.
- Identificador, variante, unidade, conteúdo, peso nominal da apresentação e modo de peso de um SKU são imutáveis. Outra embalagem/quantidade exige novo SKU. A variante não permite mudar produto/sabor/peso/características após possuir SKU; nome/estado continuam editáveis. Código, dimensão e fator de unidade são imutáveis.
- Variantes retornam `identity_locked`: antes do primeiro SKU, a tela permite corrigir produto, sabor, peso e características; depois, preserva esses campos, mesmo se todos os SKUs forem desativados. PATCH envia somente campos alterados; o backend revalida a regra dentro da transação, inclusive se um SKU tiver sido criado enquanto o formulário estava aberto.
- Famílias aceitam até **20 níveis**, contando a raiz e a própria família. Produto, variante e SKU não consomem níveis de família. Mudar a família superior também considera a altura de todos os descendentes; ciclos são rejeitados na gravação e tornam a leitura indisponível caso sejam introduzidos fora da API.
- A disponibilidade efetiva considera estado dos cadastros superiores e unidade. Desativar produto não apaga nem reescreve silenciosamente os filhos; eles ficam indisponíveis por dependência. Nova referência a pai inativo é rejeitada. Reativação de empresa pode ser feita por seu gestor, sem reabrir consultas de catálogo enquanto ela estiver inativa.
- O filtro **Estado do cadastro** considera apenas o campo `active` do próprio registro, mantendo paginação e totais da API. Um filho ativo de pai desativado mantém o indicador **Ativo** e recebe separadamente **Indisponível por dependência** (`effective_active=false`). Esse registro aparece em Ativos; Inativos seleciona somente registros desativados diretamente.
- Não existem endpoints DELETE. `catalog_events` e `code_reservations` são imutáveis no modelo e só possuem leitura/reserva via API. Alterações e preços são gravados em eventos dentro da mesma transação do cadastro.

## Reserva e concorrência

O contador técnico global `internal_code_sequences` não contém dados de empresas. Cada gravação bloqueia/recarrega a empresa e revalida sua autorização dentro da transação, evitando usar um objeto anterior à desativação. A emissão também bloqueia o contador (`SELECT ... FOR UPDATE`), gera `FC-` + 12 dígitos e grava os identificadores e reservas com índices únicos. Existe uma reserva de namespace `sku` por empresa e outra `internal_code128` de escopo global, sempre com scope preenchido. O conteúdo numérico interno não é EAN/GTIN. Contador único prioriza consistência nesta fase; eventual particionamento exige preservar a unicidade global e o histórico.

SKU manual é normalizado para maiúsculas. Código de SKU ativo/inativo não pode ser reutilizado. A reserva de Code128 é idempotente por SKU, protegida também por índice único e lock da linha de SKU. Solicitar novamente retorna o mesmo código; não emite outro. Falha de validação reverte a transação inteira. Os testes MySQL usam dois processos e dois atores independentes, sincronizados por barreira: códigos automáticos distintos e exatamente um vencedor para SKU manual duplicado.

## Contrato e permissões

O [OpenAPI](api/openapi.json) é a referência dos payloads e erros. Base de cada recurso: `/api/v1/companies/{company}`; `company` e `record` são ULIDs públicos.

| Recurso | Métodos |
|---|---|
| `/api/v1/companies` | GET/POST |
| `/api/v1/companies/{company}` | GET/PATCH |
| `/factories`, `/warehouses`, `/units`, `/product-families`, `/products`, `/product-variants`, `/commercial-skus` | GET/POST na coleção; GET/PATCH em `/{record}` |
| `/barcodes/reserve` | POST com `sku_id` e `kind=internal_code128` |
| `/barcodes/validate` | POST com `code` e `kind=internal_code128`; reconhecimento restrito à empresa |
| `/commercial-skus/{record}/barcode.{format}` | GET; `format` svg/png/pdf, cookie de sessão obrigatório |
| `/catalog-history` | GET paginado; filtro `resource_id` opcional |

Proprietário: `companies.view/create/manage` e `catalog.view/manage/audit` na empresa vinculada. Auditor: consulta de empresas/catálogo/histórico, sem mutação. Operador: sem nova concessão administrativa nesta fase. FormRequests, Policies/Gates e serviços autorizam no backend; botões são somente conveniência.

Sanctum, cookies HttpOnly e CSRF permanecem. Login conserva os limites de 20/min por IP e 5/min por e-mail/IP; recuperação conserva 5/min. A área autenticada usa orçamentos separados por usuário: **600 leituras/min** e **60 gravações/min**, configuráveis por `WORKSPACE_READ_PER_MINUTE`/`WORKSPACE_WRITE_PER_MINUTE`. Imagens/seletores não consomem a cota de gravação. Ambos os limites e seu isolamento são testados; não desabilitar o limitador para executar E2E.

## Arquivos de barras

Bibliotecas fixadas em Composer em 2026-11-07: [Picqer php-barcode-generator 3.3.0](https://github.com/picqer/php-barcode-generator) e [Dompdf 3.1.6](https://github.com/dompdf/dompdf), compatíveis com o PHP mínimo escolhido. Composer resolveu seis instalações, sem atualizar dependências preexistentes, e não reportou advisories. GD/BCMath agora são requisitos explícitos.

Picqer calcula o checksum de Code128. SVG/PNG acrescentam fundo branco e quiet zones de 10 módulos (20 px para módulo de 2 px) em ambos os lados. PDF de teste de 100 × 60 mm inclui imagem, nome/SKU e indicação de uso interno; nomes longos são abreviados com reticências somente na legenda, e o SKU completo recebe fonte ajustada ao espaço. O cadastro mantém o nome integral. Recursos remotos, PHP e JavaScript do Dompdf estão desabilitados, e os textos de cadastro são escapados. Não há ingredientes, validade, informação nutricional ou rótulo legal fictícios. Tamanho é somente um modelo de teste; imprimir e homologar equipamento/leitor/perfil físico antes de uso real.

O teste de exportação verifica bytes reais, margens brancas, barras e permissões. Com `BARCODE_DECODER` definido, executa um leitor externo sobre o PNG e compara o valor decodificado com o código reservado. A CI instala ZBar; a homologação local usou ZXing-C++ 2.3.0/Pillow 11.3.0 em venv temporário. PNG e PDF renderizado foram decodificados corretamente; PDFium 5.15.0 gerou a imagem inspecionada, com uma página e textos legíveis. Esses leitores de software não substituem homologação de hardware.

## Qualidade e limites

```bash
# apps/api
composer validate --strict
composer test
vendor/bin/pint --test

# apps/web
npm run lint
npm run typecheck
npm test
npm run build
npm run test:e2e
```

O perfil SQLite é rápido e deixa skipped os testes de MySQL/Redis e concorrência entre processos. A matriz real é executada com `scripts/homologar-fundacao.py --compose` e os caminhos locais documentados no guia, gravando resultado separado da evidência histórica da Fase 0. Use exclusivamente bancos descartáveis terminados em `_test`; rollback da migration remove as tabelas da fase e seus dados.

Windows nativo, Safari/iOS real, leitor físico e impressora não foram homologados. Avisos transitivos de desenvolvimento da fundação permanecem até nova auditoria. Nenhum dado de domínio de fases futuras foi implementado, nenhuma infraestrutura remota ou banco existente foi alterado e nenhum deploy foi realizado.
