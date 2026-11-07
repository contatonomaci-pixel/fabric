# 08 — Mapa de telas e UX (desktop/tablet/mobile)

## Navegação
Menu lateral por função e permissões; busca global; centro de notificações; fábrica/empresa atual; ações rápidas `Nova OP`, `Receber estoque`, `Registrar pesagem`, `Nova venda`, `Lançar despesa`; trilha de navegação; ajuda contextual.

## Telas obrigatórias por área
**Diretoria/BI**: dashboard por período, ponto de equilíbrio e metas, DRE, fluxo de caixa, contas a pagar/receber, orçamento, rentabilidade por SKU e família, custo por KG/UN, alerta de risco, exportações.
**Catálogo**: família, produto, variante sabor/tamanho, SKU/apresentação, unidade de venda, regra de peso, barcode, preço, status e histórico.
**Etiquetas**: perfis varejistas, homologação de código, modelos, pré-visualização e fila de impressão; leitura e validação de códigos.
**Estoque/compras**: fornecedores, cotações, pedidos, recebimentos, inspeção, posição por lote/local/status, inventário, transferências, vencimentos, FEFO.
**PCP/produção**: calendário/kanban de OPs, detalhe da OP, reserva de lote, ficha/roteiro, painel do operador tablet, apontamento, pesagem, perdas, paradas, conclusões.
**Qualidade**: inspeções, limites técnicos, temperatura, registro, quarentena/liberação, não conformidade, rastreabilidade/recolhimento.
**Comercial/expedição**: clientes e tabelas, pedidos, separação por lote, etiqueta/volumes, expedição, devoluções.
**Financeiro**: plano de contas, centros de custo, títulos, pagamentos, contas bancárias, fluxo caixa, DRE, orçamento, fechamento.
**Pessoas/manutenção**: colaboradores, turnos, metas operacionais, máquinas, moldes, manutenção e ocorrências.
**Administração**: usuários e papéis, parâmetros da empresa, logs, integrações, notificações.

## Componentes transversais
- `DataRangePicker` com início/fim inclusivos por timezone da empresa; `PeriodSelector` dia/semana/mês/ano; filtros salvos por usuário.
- Tabelas paginadas/ordenáveis, busca com debounce, colunas configuráveis, exportação do filtro corrente.
- Gráficos com legenda, data sources e opção tabela acessível. Não confiar somente na cor: mostrar textos/ícones/limiares.
- Formulários com validação inline, campos claros para UN/KG, peso bruto/tara/líquido e comparação peso nominal vs real.
- Telas operacionais touch-friendly, offline **não presumido**; conexão perdida deve sinalizar e impedir confirmação duplicada.
- Ações críticas pedem confirmação/contexto: baixa, estorno, bloqueio de lote, expedição, aprovação de rótulo.
- Expor estados: sem dados, carregando, permissão negada, conflito de estoque, erro de integração.

## Exemplo visual de dashboard
4-8 indicadores no topo com tendências e período; gráfico acumulado de vendas x meta x PE; grade `despesas por centro de custo`, `vendas por SKU`, `produção UN/KG`, `lotes próximos do vencimento`; listagem de alertas acionáveis. Em mobile cards empilhados e tabelas adaptadas.

## Roteiro primeiro produto
Tela de catálogo: criar produto Coxinha -> variante Frango 25g -> SKUs `UN`, `PCT 20`, `KG` -> código interno único, preço por SKU -> versão de receita -> rota aprovada -> OP -> lote -> estoque liberado -> venda e expedição. Mostrar nomenclatura pt-BR consistente.
