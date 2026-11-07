# 06 — Financeiro, metas, ponto de equilíbrio e Business Intelligence

## Livros/indicadores separados
- **DRE por competência** (receita líquida, CPV, despesas, resultado). Não contar compra de insumo como CPV antes do efetivo consumo/venda conforme política contábil aplicada.
- **Fluxo de caixa** por pagamento/recebimento com saldo previsto vs realizado e conciliação bancária.
- **Contas a pagar/receber** com títulos, parcelas, vencimentos, liquidações parciais, multa/juros e histórico auditado.
- **Custos industriais**: custo previsto de ficha técnica vs custo real por OP/lote vs CPV; centros de custo e rateios parametrizados.
- **Metas de vendas e produção**: valores/UN/KG/margem por empresa, fábrica, produto, família, vendedor, canal e cliente com vigência, revisão e aprovação.
- **Orçamento**: fixo/variável mensal, categoria, centro de custo, orçado x realizado; alertas de desvio.

## Ponto de equilíbrio (modelo gerencial inicial)
`MC% = (Receita líquida - Custos e despesas variáveis) / Receita líquida`.
`PE em R$ = Custos/despesas fixas / MC%` com MC% positiva e dados suficientes.
Em mix de produtos, usar **margem ponderada do mix real/projetado**, não média simples das margens individuais. Exibir período de competência, premissas, fontes, versão da fórmula, última atualização e confiabilidade. PE muda com mix, impostos, custo e preço.
- PE contábil: conforme classificação contábil validada.
- PE financeiro: ajustar itens sem efeito de caixa e compromissos relevantes, seguindo política aprovada; não confundir com simples contas a pagar.
- PE econômico opcional: lucro-alvo/custo de oportunidade configurado.
- Se `MC% <= 0`, exibir **não calculável / margem insuficiente**, nunca dividir por zero.

## Indicadores e gráficos
- Receita bruta, descontos, cancelamentos/devoluções, receita líquida, margem de contribuição, CPV, margem bruta, EBITDA gerencial (com definição), lucro operacional, saldo bancário, inadimplência, contas vencidas, estoque em valor, custo/kg, custo/UN, perdas, OEE (futuro), produtividade por máquina/turno.
- Atingimento: `realizado/meta`, com tratamento especial para metas de redução (`despesa <= teto`).
- Saldo para meta: `max(meta-realizado,0)`. Meta diária restante: `max(meta-realizado,0) / dias_operacionais_restantes`; se zero dias, marcar encerrado sem divisão.
- **Sinais:** vermelho = abaixo do mínimo/projeção crítica; amarelo = mínimo atingido porém meta pendente ou tendência de risco; verde = meta alcançada. Limiares configuráveis por KPI, sem hardcoding universal.
- Projeção: média histórica por dias úteis, sazonalidade futura opcional. Nunca chamar projeção de venda realizada.

## Filtros obrigatórios
Períodos: hoje, ontem, últimos 7/30/90 dias, semana, mês, trimestre, semestre, ano, **data inicial e final personalizadas**; intervalo calendário vs competência; anos anteriores e comparação equivalente; mês parcial identificado. Filtros por empresa, fábrica, canal, cliente, vendedor, família, variante, SKU, lotes/OP quando pertinentes. Exportar CSV/XLSX/PDF com mesmos filtros e permissão.

## Snapshot, auditoria e gráficos
Dados financeiros vêm de eventos/lançamentos persistidos. Snapshots de KPI não são fonte única de verdade, devem ser recalculáveis. Mostrar data/hora de atualização, status de ingestão e usuário da última revisão de meta. Charts line/bar/pie com navegação ao detalhe, links para títulos/lançamentos originadores.

## Controle de lançamentos
- Título não equivale a pagamento. Registrar vencimento, competência, liquidação e conciliação de forma independente.
- Dinheiro como DECIMAL; impostos parametrizados e validados por fiscal/contador; sem taxas automáticas inventadas.
- Movimentos contábeis e históricos financeiros via estorno, nunca delete silencioso.

## Exemplo demonstrativo — não dado da fábrica
Despesas fixas R$30.000; custo/despesa variável 60% da receita líquida; margem 40%; PE R$75.000. Receita de R$62.000 -> resultado operacional simplificado -R$5.200. Meta comercial R$110.000 -> 56,36% atingida. Ajustar DRE com tributos, despesas e políticas reais quando disponíveis.

## Casos de teste
- PE com receita 0, MC zero/negativa, períodos sem dados, lançamentos parcialmente pagos.
- Meta por vendedor/empresa agrega sem duplicação; comparação de período parcial não mostra como mês encerrado.
- Cenário com custo variável alterado recalcula PE sem alterar lançamentos reais.
- Filtragem de períodos inclusivos na UI e intervalos UTC consistentes no banco.
- Perfil de operador não pode ler margem/salário/conta bancária.
