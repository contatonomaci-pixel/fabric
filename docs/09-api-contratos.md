# 09 — Contratos REST sugeridos (evolutivos)

Base `/api/v1`; autenticação Sanctum, JSON; `Accept: application/json`. Contratos finais com OpenAPI versionado ao implementar. Deixar a validação nos FormRequests/Policies, paginação e documentação de erros.

| Recurso | Métodos | Fase |
|---|---|---|
| /auth/login, /auth/logout, /me | POST/GET | 0 |
| /companies, /factories, /warehouses | GET/POST/PATCH | 1 |
| /product-families, /products, /product-variants | GET/POST/PATCH | 1 |
| /commercial-skus, /units, /price-lists | GET/POST/PATCH | 1/5 |
| /barcodes/reserve, /barcodes/validate | POST | 1 |
| /retailer-barcode-profiles | GET/POST/PATCH | 7 |
| /suppliers, /purchase-orders, /receipts | CRUD + ações | 2 |
| /lots, /stock/movements, /stock/positions, /stock/counts | GET/POST | 2 |
| /recipes, /process-routes, /machines/tooling | CRUD versionado | 3 |
| /production-orders, /production-orders/{id}/release | GET/POST | 3 |
| /production-orders/{id}/consume, /complete | POST | 3 |
| /quality/inspections, /lots/{id}/release, /block | GET/POST | 4 |
| /customers, /sales-orders, /shipments | CRUD + ações | 5 |
| /financial/payables, /receivables, /settlements | GET/POST | 6 |
| /reports/dre, /reports/cashflow, /reports/break-even | GET | 8 |
| /targets, /kpis, /dashboards/summary | GET/POST | 8 |
| /users, /roles, /audit-logs | GET/POST/PATCH | 0/1 |

## Exemplo de request de cadastro de SKU (proposta)
```json
{
  "product_variant_id": "id_da_variante",
  "sku": null,
  "sale_unit": "PCT",
  "units_per_package": 20,
  "variable_weight": false,
  "nominal_net_weight_g": "500.000",
  "generate_internal_barcode": true
}
```
Código gerado somente no backend em transação. `null` indica pedido de autogeração.

## Envelope de erro proposto
```json
{
  "message": "Não foi possível concluir a operação.",
  "code": "INVENTORY_CONFLICT",
  "errors": {"quantity": ["Saldo do lote insuficiente."]},
  "request_id": "id-de-correlacao"
}
```
Não devolver stacktrace em produção, mensagens técnicas em logs protegidos.

## Idempotência
Operações como baixa de estoque, liquidação, emissão de etiqueta e conclusão de OP aceitam `Idempotency-Key`. Em caso de repetição segura retornam mesma operação, não criam novo evento.

## Filtros e datas
`?start_date=2026-11-01&end_date=2026-11-30&period=day&factory_id=...` e paginação via `page/per_page`, limites de volume. Datas na API são `YYYY-MM-DD` com regra de timezone documental.
