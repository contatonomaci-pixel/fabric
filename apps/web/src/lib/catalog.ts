export interface Company {
  public_id: string;
  name: string;
  trade_name: string;
  legal_name: string;
  timezone: string;
  active: boolean;
  is_demo: boolean;
  permissions: string[];
}
export interface CatalogRow {
  public_id: string;
  name: string;
  active: boolean;
  effective_active?: boolean;
  identity_locked?: boolean;
  permissions?: string[];
  is_demo?: boolean;
  sku?: string;
  price?: string | null;
  barcode?: { code: string; kind: string } | null;
  attributes?: Record<string, string | null>;
  [key: string]: unknown;
}
export interface Field {
  key: string;
  label: string;
  type?: "text" | "decimal" | "checkbox" | "select" | "lookup" | "attributes";
  required?: boolean;
  help?: string;
  options?: { value: string; label: string }[];
  resource?: string;
  default?: string | boolean;
  lockedOnEdit?: boolean;
  lockedWhenUsed?: boolean;
}
export interface CatalogDefinition {
  resource: string;
  title: string;
  fields: Field[];
}
const name: Field = { key: "name", label: "Nome", required: true };
const active: Field = {
  key: "active",
  label: "Cadastro ativo",
  type: "checkbox",
  default: true,
};
const lookup = (
  key: string,
  label: string,
  resource: string,
  lockedOnEdit = false,
): Field => ({
  key,
  label,
  resource,
  type: "lookup",
  required: true,
  lockedOnEdit,
});
export const productTypes = [
  { value: "finished", label: "Acabado" },
  { value: "ingredient", label: "Insumo" },
  { value: "intermediate", label: "Semielaborado" },
  { value: "packaging", label: "Embalagem" },
];
export const catalogDefinitions: CatalogDefinition[] = [
  {
    resource: "products",
    title: "Produtos",
    fields: [
      name,
      lookup("family_id", "Família", "product-families"),
      {
        key: "type",
        label: "Tipo",
        type: "select",
        required: true,
        options: productTypes,
        default: "finished",
      },
      active,
    ],
  },
  {
    resource: "product-variants",
    title: "Variantes",
    fields: [
      name,
      { ...lookup("product_id", "Produto", "products"), lockedWhenUsed: true },
      { key: "flavor", label: "Sabor", lockedWhenUsed: true },
      {
        key: "nominal_unit_weight_g",
        label: "Peso nominal da unidade (g)",
        type: "decimal",
        lockedWhenUsed: true,
        help: "Referência de cadastro; não substitui pesagem real.",
      },
      {
        key: "attributes",
        label: "Características",
        type: "attributes",
        lockedWhenUsed: true,
      },
      active,
    ],
  },
  {
    resource: "commercial-skus",
    title: "SKUs",
    fields: [
      name,
      lookup("variant_id", "Variante", "product-variants", true),
      lookup("unit_id", "Unidade de venda", "units", true),
      {
        key: "sku",
        label: "SKU",
        help: "Deixe vazio para gerar no servidor. O identificador permanece reservado após desativação.",
        lockedOnEdit: true,
      },
      {
        key: "packaging_qty",
        label: "Unidades na embalagem",
        type: "decimal",
        lockedOnEdit: true,
      },
      {
        key: "nominal_net_weight_g",
        label: "Peso líquido nominal fixo (g)",
        type: "decimal",
        lockedOnEdit: true,
      },
      {
        key: "variable_weight",
        label: "Peso variável — exige pesagem real",
        type: "checkbox",
        default: false,
        lockedOnEdit: true,
      },
      {
        key: "price",
        label: "Preço desta apresentação (R$)",
        type: "decimal",
        help: "Opcional, até 4 casas decimais. Não é calculado por peso nominal.",
      },
      active,
    ],
  },
  {
    resource: "product-families",
    title: "Famílias",
    fields: [
      name,
      {
        ...lookup("parent_id", "Família superior", "product-families"),
        required: false,
      },
      active,
    ],
  },
  {
    resource: "units",
    title: "Unidades",
    fields: [
      { key: "code", label: "Sigla", required: true, lockedOnEdit: true },
      name,
      {
        key: "dimension",
        label: "Dimensão",
        type: "select",
        required: true,
        default: "count",
        lockedOnEdit: true,
        options: [
          { value: "count", label: "Contagem (base UN)" },
          { value: "mass", label: "Massa (base g)" },
          { value: "package", label: "Embalagem" },
        ],
      },
      {
        key: "ratio_to_base",
        label: "Fator para a unidade base",
        type: "decimal",
        lockedOnEdit: true,
        help: "Para embalagens, deixe vazio. PCT/CX têm conteúdo definido por SKU.",
      },
      active,
    ],
  },
];
export const structureDefinitions: CatalogDefinition[] = [
  {
    resource: "factories",
    title: "Fábricas",
    fields: [
      name,
      {
        key: "timezone",
        label: "Fuso horário",
        required: true,
        default: "America/Bahia",
      },
      active,
    ],
  },
  {
    resource: "warehouses",
    title: "Depósitos",
    fields: [
      name,
      lookup("factory_id", "Fábrica", "factories"),
      {
        key: "kind",
        label: "Tipo de depósito",
        required: true,
        type: "select",
        default: "general",
        options: [
          { value: "general", label: "Geral" },
          { value: "cold_room", label: "Câmara fria" },
          { value: "raw_material", label: "Insumos" },
          { value: "finished_goods", label: "Produtos acabados" },
        ],
      },
      active,
    ],
  },
];
export const companyFields: Field[] = [
  { key: "trade_name", label: "Nome fantasia", required: true },
  { key: "legal_name", label: "Razão social", required: true },
  {
    key: "timezone",
    label: "Fuso horário",
    required: true,
    default: "America/Bahia",
  },
  active,
];
export function money(value: string | null | undefined): string {
  if (value == null) return "Preço não definido";
  const [whole = "0", fraction = ""] = value.split(".");
  return `R$ ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ".")},${fraction.padEnd(2, "0")}`;
}
