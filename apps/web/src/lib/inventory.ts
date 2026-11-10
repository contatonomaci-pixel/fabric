import type { Field } from "./catalog";
export interface OperationField extends Field {
  date?: boolean;
  filters?: Record<string, string | number>;
}
const lookup = (
  key: string,
  label: string,
  resource: string,
  required = true,
): OperationField => ({ key, label, resource, required, type: "lookup" });
const decimal = (key: string, label: string): OperationField => ({
  key,
  label,
  type: "decimal",
});
const reason: Field = {
  key: "reason",
  label: "Motivo do cadastro/alteração",
  required: true,
};
const active: Field = {
  key: "active",
  label: "Cadastro ativo",
  type: "checkbox",
  default: true,
};
const quality = [
  { value: "quarantine", label: "Quarentena" },
  { value: "released", label: "Liberado" },
  { value: "blocked", label: "Bloqueado" },
  { value: "rejected", label: "Rejeitado" },
];
const amounts = [
  decimal("units", "Quantidade real (UN)"),
  decimal("gross_g", "Peso bruto real (g)"),
  decimal("tare_g", "Tara real (g)"),
];
export const directoryFields: Record<string, Field[]> = {
  suppliers: [
    { key: "name", label: "Nome do fornecedor", required: true },
    {
      key: "tax_id",
      label: "Documento cadastral",
      help: "Opcional; não representa validação fiscal.",
    },
    { key: "email", label: "E-mail de contato" },
    { key: "phone", label: "Telefone" },
    active,
    reason,
  ],
  locations: [
    { key: "name", label: "Nome do local", required: true },
    {
      key: "code",
      label: "Código do local",
      required: true,
      lockedOnEdit: true,
    },
    { ...lookup("warehouse_id", "Depósito", "warehouses"), lockedOnEdit: true },
    active,
    reason,
  ],
  policies: [
    {
      ...lookup("variant_id", "Variante", "product-variants"),
      lockedOnEdit: true,
    },
    {
      key: "mode",
      label: "Grandezas acompanhadas",
      type: "select",
      required: true,
      lockedWhenUsed: true,
      options: [
        { value: "count", label: "Contagem em UN" },
        { value: "mass", label: "Massa real em gramas" },
        { value: "dual", label: "UN e massa real, separadamente" },
      ],
    },
    {
      key: "requires_expiry",
      label: "Exigir validade declarada no recebimento",
      type: "checkbox",
      default: true,
      lockedWhenUsed: true,
    },
    active,
    reason,
  ],
};
export const commandFields: Record<string, OperationField[]> = {
  "cancel-order": [lookup("order_id", "Pedido de compra", "purchasing/orders")],
  receive: [
    {
      ...lookup("order_item_id", "Item a receber", "purchasing/order-items"),
      filters: { receivable: 1 },
    },
    lookup("location_id", "Local de recebimento", "inventory/locations"),
    decimal("quantity", "Quantidade na unidade do pedido"),
    {
      key: "received_on",
      label: "Data real do recebimento",
      date: true,
      required: true,
    },
    { key: "supplier_lot_code", label: "Lote do fornecedor", required: true },
    {
      key: "lot_code",
      label: "Código interno do lote",
      help: "Opcional; deixe vazio para emissão no servidor.",
    },
    lookup(
      "lot_id",
      "Associar a lote existente (opcional)",
      "inventory/lots",
      false,
    ),
    { key: "manufactured_on", label: "Fabricação declarada", date: true },
    { key: "expires_on", label: "Validade declarada", date: true },
    {
      key: "no_expiry_reason",
      label: "Justificativa sem validade",
      help: "Somente se a política permitir. Nenhum prazo é calculado.",
    },
    {
      key: "quality_status",
      label: "Estado inicial do lote",
      type: "select",
      default: "quarantine",
      options: quality,
    },
    ...amounts,
  ],
  reserve: [
    lookup("variant_id", "Variante", "product-variants"),
    lookup("warehouse_id", "Depósito", "warehouses"),
    lookup(
      "balance_id",
      "Posição explícita (obrigatória em política dupla)",
      "inventory/positions",
      false,
    ),
    decimal("units", "Reservar quantidade (UN)"),
    decimal("grams", "Reservar massa (g)"),
  ],
  consume: [
    lookup("reservation_id", "Reserva a consumir", "inventory/reservations"),
  ],
  "cancel-reservation": [
    lookup("reservation_id", "Reserva a cancelar", "inventory/reservations"),
  ],
  issue: [
    lookup("balance_id", "Posição de origem", "inventory/positions"),
    ...amounts,
  ],
  transfer: [
    lookup("balance_id", "Posição de origem", "inventory/positions"),
    lookup("to_location_id", "Local de destino", "inventory/locations"),
    ...amounts,
  ],
  count: [
    lookup("balance_id", "Posição contada", "inventory/positions"),
    ...amounts,
  ],
  weigh: [
    lookup("balance_id", "Posição pesada", "inventory/positions"),
    ...amounts,
  ],
  quality: [
    lookup("lot_id", "Lote", "inventory/lots"),
    {
      key: "quality_status",
      label: "Novo estado do lote",
      type: "select",
      options: quality,
      required: true,
    },
  ],
  reverse: [
    lookup("operation_id", "Operação a estornar", "inventory/operations"),
  ],
};
export const commandNames: Record<string, string> = {
  order: "Novo pedido",
  "cancel-order": "Cancelar pedido",
  receive: "Receber item",
  reserve: "Reservar por FEFO",
  consume: "Consumir reserva",
  "cancel-reservation": "Cancelar reserva",
  issue: "Saída de estoque",
  transfer: "Transferir estoque",
  count: "Registrar contagem",
  weigh: "Registrar pesagem",
  quality: "Alterar estado do lote",
  reverse: "Estornar operação",
};
export const commandPermissions: Record<string, string> = {
  order: "purchasing.manage",
  "cancel-order": "purchasing.manage",
  receive: "purchasing.receive",
  reserve: "inventory.reserve",
  consume: "inventory.issue",
  "cancel-reservation": "inventory.reserve",
  issue: "inventory.issue",
  transfer: "inventory.manage",
  count: "inventory.count",
  weigh: "inventory.weigh",
  quality: "inventory.release",
  reverse: "inventory.reverse",
};
export const resourcePaths: Record<string, string> = Object.fromEntries(
  [
    "suppliers",
    "orders",
    "order-items",
    "receipts",
    "policies",
    "locations",
    "lots",
    "positions",
    "reservations",
    "allocations",
    "movements",
    "weighings",
    "counts",
    "operations",
    "events",
  ].map((resource) => [
    resource,
    `${["suppliers", "orders", "order-items", "receipts"].includes(resource) ? "purchasing" : "inventory"}/${resource}`,
  ]),
);
export const resourceNames: Record<string, string> = {
  suppliers: "Fornecedores",
  orders: "Pedidos de compra",
  "order-items": "Itens dos pedidos",
  receipts: "Recebimentos",
  policies: "Políticas de estoque",
  locations: "Locais",
  lots: "Lotes e vencimentos",
  positions: "Posições de estoque",
  reservations: "Reservas",
  allocations: "Alocações",
  movements: "Movimentos",
  weighings: "Pesagens",
  counts: "Contagens",
  operations: "Operações e estornos",
  events: "Histórico de alterações",
};
export const statusNames: Record<string, string> = {
  open: "Aberto",
  partially_received: "Parcialmente recebido",
  received: "Recebido",
  cancelled: "Cancelado",
  active: "Ativa",
  consumed: "Consumida",
  quarantine: "Quarentena",
  released: "Liberado",
  blocked: "Bloqueado",
  rejected: "Rejeitado",
};
export const columns: Record<string, [string, string][]> = {
  orders: [
    ["status", "Estado"],
    ["expected_on", "Previsão"],
  ],
  "order-items": [
    ["quantity", "Pedido"],
    ["received_quantity", "Recebido"],
    ["remaining_quantity", "Restante"],
    ["unit_id_label", "Unidade"],
    ["unit_cost", "Custo unitário"],
    ["total_cost", "Custo total"],
  ],
  receipts: [
    ["quantity", "Recebido"],
    ["received_on", "Data"],
    ["total_cost", "Custo total"],
  ],
  lots: [
    ["supplier_lot_code", "Lote do fornecedor"],
    ["variant_id_label", "Variante"],
    ["quality_status", "Qualidade"],
    ["expires_on", "Validade"],
  ],
  positions: [
    ["units", "Saldo UN"],
    ["grams", "Saldo g"],
    ["kilograms", "Saldo KG real"],
    ["reserved_units", "Reservado UN"],
    ["reserved_grams", "Reservado g"],
    ["available_units", "Sem reserva UN"],
    ["available_grams", "Sem reserva g"],
    ["expires_on", "Validade"],
  ],
  reservations: [
    ["status", "Estado"],
    ["variant_id_label", "Variante"],
    ["warehouse_id_label", "Depósito"],
  ],
  allocations: [
    ["units", "UN"],
    ["grams", "g"],
  ],
  movements: [
    ["units_delta", "Variação UN"],
    ["grams_delta", "Variação g"],
    ["operation_id_label", "Operação"],
  ],
  weighings: [
    ["gross_g", "Bruto g"],
    ["tare_g", "Tara g"],
    ["net_g", "Líquido g"],
  ],
  counts: [
    ["expected_units", "Anterior UN"],
    ["actual_units", "Contado UN"],
    ["expected_grams", "Anterior g"],
    ["actual_grams", "Contado g"],
  ],
  operations: [
    ["reason", "Motivo"],
    ["reversed_by", "Estorno"],
  ],
  events: [
    ["resource", "Cadastro"],
    ["reason", "Motivo"],
  ],
};
