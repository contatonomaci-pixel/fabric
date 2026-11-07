# Fase 2 — Compras, lotes e estoque


## Trabalho
- Cadastro fornecedor e pedidos; recebimento com lote, data, validade, custo, quantidade, aprovação inicial de qualidade.
- Estoque por local/lote/status, movimentos append-only, correção por estorno, reservas, baixa transacional, FEFO liberado.
- Pesagem manual auditada (bruto/tara/líquido), saldos em UN e/ou gramas conforme política de produto.
- Tela posições, entradas, transferências, contagens e alertas de vencimento; testes concorrentes.

## Aceite
- Lote vencido/bloqueado/quarentena não sai por FEFO automático.
- 2 pedidos concorrentes não geram estoque negativo.
- Saldo por KG representa massa real, não aproximação de gramatura.
