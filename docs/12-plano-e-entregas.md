# 12 — Roadmap para Codex: fases incrementais

Cada fase contém migração, API, UI, testes e documentação compatíveis com seu escopo. **Começar apenas na Fase 0**.

| Fase | Entrega | Critério central |
|---|---|---|
| 0 | Scaffold monorepo, auth, infraestrutura, CI e layout base | Rodar local com login, build e testes básicos |
| 1 | Empresas, fábricas, catálogo, unidades, SKUs, Code128 interno | Não duplicar SKU/código; tela de catálogo funcional |
| 2 | Fornecedores, compras, recebimento, estoque/lotes, FEFO, pesagens básicas | Nenhum saldo negativo concorrente; rastreio de lote |
| 3 | Receitas/rotas versionadas, máquina, OP, apontamento, consumo | OP produz lote com genealogia correta |
| 4 | Qualidade, bloqueios, temperaturas, lote liberado, rastreabilidade | Lote bloqueado não é expedido, consulta trace-back/forward |
| 5 | Clientes, vendas UN/KG/PCT/CX, expedição, tabelas de preço | Pedido reserva lotes sem duplicar estoque |
| 6 | Financeiro: títulos, liquidações, caixa, orçamento, centros de custo | Previsto x realizado, conciliação e estornos auditáveis |
| 7 | Perfis de varejistas, RCN homologado, etiquetas, impressão, GTIN manual | Código restrito só com perfil aprovado, leitor validado |
| 8 | BI, metas, ponto de equilíbrio, DRE, gráficos e filtros | Valores batem com lançamentos; filtros customizados |
| 9 | RH operacional, manutenção, métricas de produção | Produtividade e registros vinculados a turnos/máquinas |
| 10 | Integrações futuras, hardening, homologações, implantação | Go-live só com checklist e aprovação especializada |

## Cadência
1. Selecionar uma tarefa de `tasks/` e preparar branch/commit.
2. Codex examina contexto e apresenta plano curto.
3. Codex implementa somente a fase.
4. Executar testes, lint, revisão de migração e demonstração.
5. Atualizar `docs/STATUS.md`, registrar problemas e concluir commit.
6. Só então autorizar próxima fase.

## Marco de primeira operação real
Pelo menos fases 0–5, controles mínimos de qualidade/rotulagem e homologação de venda/etiqueta/fiscal precisam funcionar antes de operar comercialmente. Um dashboard bonito não substitui inventário confiável e auditoria.
