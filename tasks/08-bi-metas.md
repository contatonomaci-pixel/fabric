# Fase 8 — BI, DRE, metas e PE


## Trabalho
- Dashboard com faturamento, DRE, fluxo de caixa, contas, orçamento, metas diária/mensal/anual, PE e projeções.
- Filtros por dia/semana/mês/ano e intervalo personalizado, período anterior, produto, cliente, vendedor, canal, unidade; exportação respeita mesma consulta.
- Configuração de limites mínimo/meta/supermeta, alertas, dias úteis e projeção; dados com fonte e atualização.
- PE por margem de contribuição ponderada e período de competência; proteção quando margem <= 0; simulador cenário sem alterar dado real.

## Aceite
- Testes fixtures: fixos 30000, variável 60%, PE 75000 e receita 62000 -> -5200 em resultado simplificado.
- Filtro parcial não confunde mês aberto com concluído; perfis respeitam segregação.
