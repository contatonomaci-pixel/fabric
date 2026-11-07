# 02 — Modelagem MySQL (contrato lógico inicial)

**Não executar este documento como DDL literal.** O Codex produzirá migrations Laravel versionadas por fase. IDs internos BIGINT UNSIGNED auto-increment ou ULID seguem uma convenção única após ADR; `company_id` obrigatoriamente filtra recursos multiempresa; índices compostos respeitam escopo. Timestamps UTC, soft delete apenas em cadastros quando apropriado, jamais em histórico contábil sem trilha.

## Base e catálogo
| Tabela | Campos principais | Regras |
|---|---|---|
| companies | id, legal_name, trade_name, timezone, active | tenant/contexto |
| factories | id, company_id, name, timezone | unidade fabril |
| warehouses | id, factory_id, name, kind, active | ambiente físico, câmara fria/almoxarifado |
| locations | id, warehouse_id, code, type | endereço de estocagem |
| users | id, name, email, password, active | autenticação; associação a empresas em `company_users` |
| company_users | company_id, user_id, role_id | UNIQUE(company_id,user_id,role_id) |
| roles / permissions / role_permissions | company_id quando customizado, name, code | política server-side |
| units_of_measure | id, code, dimension, ratio_to_base | `UN`, `g`, `KG`, `PCT`, `CX`; conversões de conteúdo por apresentação, não genéricas para pacote |
| product_families | id, company_id, name, parent_id | coxinha/pizza/massa etc. |
| products | id, company_id, name, type, category_id, active, quality_profile_id | tipo: insumo/semielaborado/acabado/embalagem |
| product_variants | id, product_id, flavor, nominal_unit_weight_g, attributes_json, active | sabor/tamanho/processo; campo flavor opcional para outros alimentos |
| commercial_skus | id, product_variant_id, sku, sale_unit, packaging_qty, fixed_net_weight_g, variable_weight, active | UNIQUE company+sku; pacote diferente -> outro SKU |
| product_barcodes | id, commercial_sku_id, kind, code, symbology, retailer_profile_id, status, issued_at | UNIQUE por namespace+scope; não reutilizar |
| price_lists / price_list_items | company_id, client_id, sku_id, sale_unit, price | preço com vigência e histórico |

**SKU e variação:** coxinha frango 25g, coxinha frango 50g, bolinho carne 25g, pizza carne 400g e pizza frango 400g são combinações distintas. Pacote 10 un e 20 un são apresentações comerciais distintas. O código do produto não varia por lote de fabricação.

## Compras / estoque / pesos
| Tabela | Campos principais | Regras |
|---|---|---|
| suppliers | company_id, tax_id, name, contact, status | validar no backend |
| purchase_orders / purchase_order_items | supplier_id, expected_date, status; product_id, qty, unit_cost | histórico de preços |
| inventory_lots | product_variant_id ou product_id, supplier_lot_code, internal_lot_code, manufactured_at, expires_at, quality_status | restrição única company+lot_code interno |
| stock_movements | company_id, lot_id, product_id, location_id, direction, qty_base, base_unit, reason, source_type, source_id, created_by | append-only + estorno |
| inventory_reservations | source_type, source_id, lot_id, qty_base, status | bloqueia dupla alocação |
| weighings | product_variant_id, lot_id, gross_g, tare_g, net_g, scale_id, operator_id, captured_at, origin | net_g calculado e auditável |
| stock_counts / stock_count_items | warehouse_id, counted_qty, approved_by, differences | ajuste auditado |
| warehouse_temperature_readings | warehouse_id, sensor_id, recorded_at, value, status, recorded_by | limites por perfil técnico, não hardcoded |

**Quantidade canônica:** por produto, definir dimensão base (`g` para insumos e produtos a peso; `UN` para embalagens e unidades quando apropriado); controlar estoques por lote/local e duas medições quando necessárias (unidades + massa líquida). `PCT`/`CX` resolvem conteúdo via SKU/revisão de embalagem. Nunca converter peso variável apenas pelo nominal.

## Engenharia / produção / qualidade
| Tabela | Campos principais | Regras |
|---|---|---|
| recipe_versions | product_variant_id, version, yield_target, status, approved_by | imutável após uso em OP |
| recipe_ingredients | recipe_version_id, ingredient_id, qty, unit, expected_loss | alergênicos via ingredients/regra |
| process_routes / process_route_steps | product_variant_id, version, step_type, sequence, qc_requirements | variável por linha |
| machines / tooling | factory_id, asset_code, model, compatibility | máquina inicial 8.5 MAX, acessórios a conferir |
| production_orders | factory_id, variant_id, recipe_version_id, route_version_id, target_qty, target_unit, status, scheduled_at | estados e rastreio |
| production_consumptions | production_order_id, input_lot_id, qty_base, posted_by | só lote liberado |
| production_outputs | production_order_id, output_lot_id, good_qty, waste_qty, weight_g | balanço de massa |
| production_events | production_order_id, step_id, event_type, occurred_at, operator_id, payload | início/fim/parada/setup |
| quality_inspections / quality_results | lot_id, specification_version, inspector_id, outcome, value | aprovar/bloquear/quarentena |
| nonconformities / corrective_actions | lot_id, cause, action, owner, closure | trilha de qualidade |
| lot_genealogy | input_lot_id, output_lot_id, production_order_id, qty_used | rastreabilidade fim a fim |

## Comercial, pessoas, manutenção e finanças
| Tabela | Campos principais | Regras |
|---|---|---|
| customers / retailer_barcode_profiles | company_id, customer_id, barcode_rule, approved_by, tested_at | compatibilidade varejo por perfil |
| sales_orders / sales_order_items | customer_id, order_date, items with sku, qty, unit_price, requested_lot? | saldo condicionado a estoque |
| shipments / shipment_items | sales_order_id, lot_id, packed_qty, shipped_weight_g, carrier | origem por lote/cliente |
| employees / shifts / attendance_events | factory_id, role, team, start/end, recorded_at | dados pessoais minimizados |
| maintenance_orders / machine_downtimes | machine_id, status, scheduled_at, completed_at | preventiva/corretiva |
| financial_accounts | company_id, account_code, type, active | plano de contas |
| cost_centers | company_id, code, name, parent_id | análise por setor |
| payables / receivables | document, due_date, amount, competence_date, status | separar título do pagamento |
| financial_settlements | payable_id ou receivable_id, paid_at, amount, account_id, reference | parciais, estornos |
| cash_bank_accounts / cash_bank_entries | company_id, account, posted_at, amount, direction | conciliação auditável |
| budgets / budget_lines | company_id, year, month, account, cost_center, planned_amount | orçamento por competência |
| targets / target_allocations | company_id, target_type, start/end, value, scope | metas por período, canal, produto etc. |
| kpi_definitions / kpi_snapshots | code, formula_version, period_start/end, result, computed_at | rastro da apuração |
| audit_logs / outbox_events | actor, object, before_after_safe, occurred_at | logs sem segredo |

## Integridade essencial
- Índices UNIQUE compostos para escopos: `products(company_id, external_code?)`, `commercial_skus(company_id, sku)` via redundância verificada ou índice/solução equivalente; `inventory_lots(company_id, internal_lot_code)`; `product_barcodes(namespace,code,retailer_profile_id)` com estratégia para `NULL` (índice gerado/escopo normalizado).
- Não usar unique ingênuo em `(code, retailer_profile_id)` quando `retailer_profile_id IS NULL`, porque MySQL permite múltiplos NULL em UNIQUE.
- FOREIGN KEY para referências; auditoria de todas as operações críticas; índice por data, empresa, status e produto nos relatórios.
- Planejar arquivamento e volume dos lançamentos sem apagar trilhas legais.
