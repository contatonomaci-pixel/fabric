# Fase 2 — Compras e estoque

Continuação solicitada após a integração da Fase 1. Escopo: fornecedores, pedidos/recebimentos, lotes/locais, reservas/baixas, transferências, contagens, estornos, FEFO e pesagem manual. Produção, vendas, financeiro e inspeções avançadas permanecem nas fases próprias.

Entrega na branch `feat/phase-2-inventory`, [PR #2 em rascunho](https://github.com/contatonomaci-pixel/fabric/pull/2), com homologação local e CI aprovada. Main permanece na Fase 1 até revisão/integração. Evidências em [STATUS](STATUS.md) e arquivos no [manifesto](22-arquivos-fase-2.md).

## Decisões de domínio

- Política explícita por variante: contagem em UN, massa em gramas ou ambas, sem conversão pelo peso nominal. Dimensão/regra de validade não podem mudar após o primeiro pedido emitido ou lote, inclusive quando o pedido é cancelado, preservando o significado histórico. Exigência de validade é configurada; ausência de validade requer justificativa quando permitida. Não há prazo sanitário calculado.
- Pedidos usam unidades de massa/contagem com fator explícito; PCT/CX não possuem conversão global. Itens e preços são preservados após emissão; cancelamento somente antes de recebimentos. Recebimentos parciais não excedem o pedido; custo vem do item, sem lançamento financeiro.
- Lote tem identidade independente do SKU. Recebimento informa lote do fornecedor, datas declaradas, quantidades reais, local e estado inicial. Liberação exige permissão específica e motivo; não representa inspeção técnica completa da Fase 4.
- Movimento físico é append-only. Saldos são projeções transacionais por lote/local; correção gera movimento compensatório vinculado ao original. Estorno exige saldo disponível e não reabre reserva consumida. Reservas não são lançamentos físicos.
- Cada comando tem chave de idempotência por empresa e hash da solicitação; repetir não duplica efeitos e reutilizar a chave com conteúdo diferente é rejeitado. Empresa é bloqueada/recarregada e a permissão revalidada dentro da transação, serializando operações da mesma empresa nesta fase. Nenhum saldo/reserva pode ficar negativo.
- FEFO considera empresa, depósito/local/catálogo ativos, lote liberado e validade no fuso da empresa. Lote sem validade só entra quando a política permite e há justificativa; fica após lotes datados. Reservas em massa/contagem podem dividir lotes; política dupla exige posição explícita e duas quantidades declaradas, evitando distribuir massa por uma proporção teórica de unidades.
- Pesagem manual registra bruto, tara, líquido, lote, local, autor e UTC. Não cria saldo por si só. Entradas/saídas de massa registram pesagem; transferências preservam identidade e medição declarada. Reserva usa quantidade pretendida; consumo confirma a massa de cada alocação. Não há integração com balança ou etiqueta comercial.
- Valores físicos: DECIMAL(18,6); custo unitário/total: DECIMAL(18,4), arredondamento decimal explícito. Nenhum cálculo físico/monetário utiliza float. Conversões que excedam a precisão são rejeitadas.
- Proprietário gerencia a fase; compras e almoxarifado têm concessões próprias; liberação básica de lote é separada. Custos exigem `purchasing.costs`; auditor/almoxarifado não os recebem por padrão. Operador de produção não ganha administração de estoque.

## Instalação e atualização

Use os serviços originais de `infra/compose.yaml` e o guia [Windows/VS Code e Unix](17-executar-localmente.md). Não execute migrations/testes em banco existente de operação. A migration da Fase 2 cria 15 tabelas; seu rollback remove os dados da fase e requer revisão antes de qualquer uso fora de banco descartável.

```bash
# apps/api — PHP 8.3+ com extensões da fundação, inclusive BCMath/GD
composer install
php artisan migrate
php artisan db:seed
# Somente local/testing, com DEMO_PASSWORD local configurada:
php artisan db:seed --class=DemoSeeder

# apps/web
npm ci
npm run dev
```

Não há dependência nova nem alteração do Compose. `db:seed` instala as permissões novas; o seed de demonstração acrescenta fornecedor e local explicitamente fictícios. Não cria política, saldo, custo ou validade. Repetir a demonstração não substitui configurações existentes. Contas extras de estoque existem somente no runner E2E descartável, com senha efêmera.

## Operação das telas

Escolha a empresa de trabalho e use **Seção do módulo** para abrir cada tela. O seletor evita uma faixa extensa de abas no celular. Saldos, massas e valores monetários são apresentados em pt-BR; custos continuam segregados por permissão. Quantidade **sem reserva** é física: não autoriza saída de lote vencido, bloqueado ou em quarentena.

1. **Estoque e lotes → Locais:** cadastre código/nome e depósito. Código e depósito do local são preservados após criação; desativação é feita pelo formulário de edição com motivo/confirmação. Depósito com locais não muda de fábrica.
2. **Políticas de estoque:** selecione variante, grandezas acompanhadas e regra de validade com motivo. Nenhum valor deriva da gramatura nominal. Antes do primeiro pedido emitido ou lote, dimensão/validade podem ser corrigidas; após uso ficam bloqueadas. Desativação/reativação preserva histórico. Características da variante também ficam protegidas após o primeiro lote, mesmo sem SKU. Pedidos históricos incompatíveis com uma política alterada antes desta correção permanecem impedidos de receber; devem ser revisados/cancelados e reemitidos conforme o contexto, sem conversão entre massa e contagem.
3. **Compras e recebimento → Fornecedores:** cadastre nome/contato/documento opcional; essa identificação não homologa situação fiscal. Compras pode emitir pedido com até 50 itens, quantidade/unidade e custo informado por item. Itens emitidos são preservados; cancelar exige estornar todos os recebimentos antes.
4. **Itens dos pedidos → Receber item:** escolha item com restante e local. Informe quantidade na unidade do pedido, data real, lote do fornecedor e validade declarada. Para massa, bruto/tara devem resultar na quantidade convertida por g/KG; para contagem, informe UN. Política dupla exige ambas, sem rateio pelo nominal. Recebimento parcial não excede o pedido. Código interno de lote pode ser gerado ou informado; não pode ser reutilizado. Para associar a lote existente, variante/fornecedor/política/datas/estado e identificação declarada devem coincidir.
5. **Estado inicial e qualidade básica:** quarentena é padrão; outro estado inicial exige `inventory.release`. A alteração posterior exige a mesma permissão e motivo. Lote vencido não pode ser liberado. Bloqueio/quarentena impede reserva/baixa/transferência operacional; reservas existentes ficam retidas até cancelamento ou liberação, e consumo revalida o estado. Esta aprovação básica não substitui inspeções/processos da Fase 4.
6. **Posições:** consulte saldo físico, reservado e disponível por variante/lote/local. Massa é mostrada em gramas e KG reais, sem conversão por unidades estimadas. Filtros de variante, depósito e qualidade são aplicados na API com paginação. **Lotes e vencimentos** permite filtrar validade até uma data e destaca vencidos, sem calcular prazo de produto.
7. **Reservar por FEFO:** escolha variante/depósito e quantidade pretendida. Para massa/contagem, o servidor distribui entre lotes liberados por validade, depois ID; lotes sem validade justificada ficam por último. Na política dupla, escolha uma posição e as duas quantidades declaradas. Uma reserva admite até 100 alocações; solicitações maiores precisam ser divididas. Falta de saldo reverte todas as alocações.
8. **Reservas → Consumir:** escolha reserva ativa e confirme bruto/tara de cada alocação de massa. O líquido precisa corresponder à massa reservada; discrepância exige cancelar/refazer com a medição correta. Cancelar reserva devolve disponibilidade, sem gerar movimento físico. Consumo e cancelamento não podem se repetir com outra chave após finalização.
9. **Saída/transferência:** selecione posição e medição; somente saldo livre, liberado e não vencido. Transferência usa dois movimentos atômicos, preserva lote e exige destino ativo diferente. Não existe pedido de venda, OP ou expedição fictícia vinculado à saída manual.
10. **Contagens:** registre quantidade efetivamente observada e motivo; gera fotografia anterior/atual e movimento de ajuste. Zero é aceito. A contagem administrativa não libera qualidade nem pode baixar abaixo do reservado.
11. **Pesagens:** registro manual de bruto/tara/líquido vinculado a lote/local, autor e UTC. Consulta mostra o autor da operação. Pesagem isolada pede apenas massa, inclusive na política dupla; não aceita UN nem altera saldo. Contagem de unidades deve usar o comando próprio. Não há integração com equipamento.
12. **Operações e estornos:** selecione operação física original e motivo. Estorno único exige disponibilidade para compensar; não apaga movimento/recebimento/lote nem reabre reserva consumida. Estornar recebimento devolve a quantidade ao restante do pedido. Correção adicional exige nova operação auditada, não estorno de estorno.

Formulários preservam a chave de idempotência ao repetir o mesmo payload após falha de conexão. Alterar os dados inicia uma solicitação com outra chave; consulte o histórico antes de mudar uma operação cujo resultado seja desconhecido. Respostas atrasadas da empresa anterior são descartadas e navegação durante CSRF pendente não envia comando antigo.

## API e permissões

[OpenAPI](api/openapi.json): `/api/v1/companies/{company}`, somente ULIDs públicos. Métodos de leitura paginam até 100 registros, padrão 20. Todos os comandos exigem sessão/CSRF, `reason` (3–500 caracteres) e `idempotency_key` (8–100 caracteres ASCII alfanuméricos/`:_-`). A mesma chave por empresa com outro comando/payload retorna 422. Movimento/reserva/recebimento não tem endpoint DELETE/PATCH.

| Caminho relativo à empresa | Métodos / finalidade |
|---|---|
| `purchasing/suppliers` | GET/POST; GET/PATCH `/{record}` |
| `inventory/locations`, `inventory/policies` | GET/POST; GET/PATCH `/{record}` |
| `purchasing/orders`, `purchasing/order-items`, `purchasing/receipts` | GET; GET `/{record}` |
| `inventory/lots`, `positions`, `reservations`, `allocations`, `movements`, `weighings`, `counts`, `operations`, `events` | GET; GET `/{record}` em cada recurso sob `inventory/` |
| `inventory/actions/order`, `cancel-order`, `receive` | POST; pedido/cancelamento/recebimento |
| `inventory/actions/reserve`, `consume`, `cancel-reservation` | POST; reserva/consumo/cancelamento |
| `inventory/actions/issue`, `transfer`, `count`, `weigh`, `quality`, `reverse` | POST; demais comandos |

As respostas de comando retornam a operação e IDs públicos de pedido/recebimento/reserva criados. Repetição retorna o mesmo identificador sem repetir efeitos. Custos de itens/recebimentos são omitidos sem `purchasing.costs`; custos totais usam arredondamento decimal half-up em quatro casas. Datas de validade são datas declaradas; vencido é apurado pelo dia no fuso da empresa. Timestamps de captura permanecem UTC.

| Papel padrão | Concessões desta fase |
|---|---|
| Proprietário | Todas, incluindo configuração, custos, liberação e estorno |
| Compras (`buyer`) | Fornecedores/pedidos e custos; sem operação de estoque/liberação |
| Almoxarifado (`warehouse`) | Consulta de compras sem custos, recebimento em quarentena, estoque/reservas/baixa/transferência/contagem/pesagem; sem configuração, liberação ou estorno |
| Qualidade (`quality`) | Consulta de estoque e histórico permitido; estado básico do lote; sem compras/custos/contatos de fornecedor |
| Auditor | Consulta de compras/estoque/histórico; sem custos ou mutação |
| Operador de produção | Nenhuma concessão nova |

Logs de configuração/qualidade são separados do histórico de catálogo. Histórico de estoque filtra os recursos de compras quando o usuário não tem `purchasing.view`, incluindo consulta direta de um evento/operação. Não há endpoint de concessão de papéis pelo cliente nesta fase; vínculos continuam administrados pelo responsável pelo ambiente.

## Verificação e limites

```bash
# apps/api
composer test
vendor/bin/pint --test
# apps/web
npm run lint
npm run typecheck
npm test
npm run build
npm run test:e2e
```

SQLite é somente perfil rápido/descartável de testes. A matriz real usa `scripts/homologar-fundacao.py --compose` com MySQL 8.4/Redis e bancos `_test`, conforme o guia. Os testes concorrentes usam dois atores/processos sincronizados: disputa por reservas, duas baixas e repetição simultânea da mesma chave. Ver [STATUS](STATUS.md) para comandos, runtimes, contagens efetivamente executadas e CI.

Sem processamento industrial, fornecedores reais, taxas, prazo sanitário, integração fiscal, contas a pagar, reserva de venda/OP ou impressão de varejo. Aprovação técnica de parâmetros, balança física, Windows/Safari/iOS, rotinas de produção e go-live continuam pendentes nas etapas próprias.
