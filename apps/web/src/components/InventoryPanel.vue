<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { api, errorMessage } from "../lib/api";
import {
  columns,
  commandNames,
  commandPermissions,
  resourceNames,
  resourcePaths,
  statusNames,
} from "../lib/inventory";
import { money, type Company, type CatalogRow } from "../lib/catalog";
import LookupSelect from "./LookupSelect.vue";
import InventoryOperationForm from "./InventoryOperationForm.vue";
import PurchaseOrderForm from "./PurchaseOrderForm.vue";
const props = defineProps<{ company: Company; resource: string }>();
const scope = computed(() => `/companies/${props.company.public_id}`),
  rows = ref<CatalogRow[]>([]),
  page = ref(1),
  last = ref(1),
  total = ref(0),
  busy = ref(false),
  error = ref(""),
  notice = ref(""),
  action = ref(""),
  query = ref(""),
  variant = ref(""),
  date = ref(""),
  warehouse = ref(""),
  quality = ref("");
const actions = computed(() =>
  (
    ({
      orders: ["order", "cancel-order"],
      "order-items": ["receive"],
      receipts: ["receive"],
      positions: ["reserve", "issue", "transfer", "count", "weigh"],
      lots: ["quality"],
      reservations: ["reserve", "consume", "cancel-reservation"],
      allocations: [],
      movements: [],
      weighings: ["weigh"],
      counts: ["count"],
      operations: ["reverse"],
      events: [],
    })[props.resource] || []
  ).filter((kind) =>
    props.company.permissions.includes(commandPermissions[kind]!),
  ),
);
const visibleColumns = computed(() =>
  (columns[props.resource] || []).filter(
    ([key]) =>
      !key.includes("cost") ||
      props.company.permissions.includes("purchasing.costs"),
  ),
);
let alive = true,
  generation = 0;
async function load(next = 1) {
  const version = ++generation;
  busy.value = true;
  error.value = "";
  rows.value = [];
  try {
    const { data } = await api.get(
      `${scope.value}/${resourcePaths[props.resource]}`,
      {
        params: {
          page: next,
          per_page: 10,
          q: query.value || undefined,
          variant_id: variant.value || undefined,
          expires_before: date.value || undefined,
          warehouse_id: warehouse.value || undefined,
          quality_status: quality.value || undefined,
        },
      },
    );
    if (!alive || version !== generation) return;
    rows.value = data.data;
    page.value = data.meta.current_page;
    last.value = data.meta.last_page;
    total.value = data.meta.total;
  } catch (e) {
    if (alive && version === generation) error.value = errorMessage(e);
  } finally {
    if (alive && version === generation) busy.value = false;
  }
}
function saved() {
  action.value = "";
  notice.value = "Operação registrada com sucesso.";
  void load(page.value);
}
function value(row: CatalogRow, key: string) {
  const raw = row[key];
  if (raw == null) return "Não informado";
  if (key.includes("cost")) return money(String(raw));
  if (key.endsWith("_on")) return String(raw).split("-").reverse().join("/");
  if (/(units|grams|quantity|kilograms|_g)(?:_delta)?$/.test(key))
    return String(raw).replace(".", ",");
  return statusNames[String(raw)] || String(raw);
}
function qualityChange(row: CatalogRow) {
  const after = row.after as Record<string, unknown> | undefined;
  const before = row.before as Record<string, unknown> | undefined;
  return after?.quality_status
    ? `Qualidade: ${statusNames[String(before?.quality_status)] || "Estado inicial"} → ${statusNames[String(after.quality_status)]}`
    : "";
}
function rowColumns(row: CatalogRow) {
  return visibleColumns.value.filter(([key]) =>
    row.mode === "mass"
      ? !key.includes("units")
      : row.mode === "count"
        ? !/grams|kilograms/.test(key)
        : true,
  );
}
function dateTime(raw: unknown) {
  return raw
    ? new Intl.DateTimeFormat("pt-BR", {
        dateStyle: "short",
        timeStyle: "short",
        timeZone: props.company.timezone,
      }).format(new Date(String(raw)))
    : "";
}
watch(
  () => [props.resource, props.company.public_id],
  () => {
    action.value = "";
    variant.value = "";
    warehouse.value = "";
    quality.value = "";
    query.value = "";
    date.value = "";
    notice.value = "";
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  alive = false;
  generation++;
});
</script>
<template>
  <section
    class="catalog-panel"
    :aria-label="resourceNames[resource]"
    :aria-busy="busy"
  >
    <h2>{{ resourceNames[resource] }}</h2>
    <p v-if="notice" class="feedback success" role="status">{{ notice }}</p>
    <div v-if="!action" class="catalog-actions">
      <button v-for="kind in actions" :key="kind" @click="action = kind">
        {{ commandNames[kind] }}
      </button>
    </div>
    <PurchaseOrderForm
      v-if="action === 'order'"
      :scope="scope"
      @saved="saved"
      @cancel="action = ''"
    />
    <InventoryOperationForm
      v-else-if="action"
      :key="action"
      :scope="scope"
      :kind="action"
      :can-release="company.permissions.includes('inventory.release')"
      @saved="saved"
      @cancel="action = ''"
    />
    <form class="catalog-toolbar" role="search" @submit.prevent="load()">
      <label>Buscar identificador<input v-model="query" maxlength="100"></label><label v-if="resource === 'lots'">Validade até<input v-model="date" type="date"></label><button :disabled="busy">Buscar</button>
    </form>
    <details
      v-if="
        [
          'lots',
          'positions',
          'order-items',
          'policies',
          'reservations',
        ].includes(resource)
      "
    >
      <summary>Filtros avançados</summary>
      <LookupSelect
        v-model="variant"
        :endpoint="`${scope}/product-variants`"
        label="Filtrar variante"
        @update:model-value="load()"
      />
      <LookupSelect
        v-if="resource === 'positions'"
        v-model="warehouse"
        :endpoint="`${scope}/warehouses`"
        label="Filtrar depósito"
        @update:model-value="load()"
      />
      <label v-if="['lots', 'positions'].includes(resource)">Estado de qualidade<select v-model="quality" @change="load()">
        <option value="">Todos</option>
        <option
          v-for="status in ['released', 'quarantine', 'blocked', 'rejected']"
          :key="status"
          :value="status"
        >
          {{ statusNames[status] }}
        </option>
      </select></label>
    </details>
    <p v-if="busy" role="status">Carregando registros…</p>
    <div v-if="error" class="feedback error" role="alert">
      <p>{{ error }}</p>
      <button @click="load(page)">Tentar novamente</button>
    </div>
    <p v-else-if="!busy && !rows.length" class="empty-message">
      Nenhum registro encontrado para este contexto e filtros.
    </p>
    <div class="catalog-list">
      <article
        v-for="row in rows"
        :key="row.public_id"
        class="catalog-record inventory-record"
      >
        <h3>
          {{
            row.variant_name
              ? `${row.variant_name} · ${row.lot_code}`
              : row.name
          }}
        </h3>
        <p class="field-help">
          {{ row.public_id }} · {{ dateTime(row.created_at) }}
        </p>
        <p v-if="row.variant_name">Local: {{ row.location_name }}</p>
        <dl class="inventory-values">
          <div v-for="[key, label] in rowColumns(row)" :key="key">
            <dt>{{ label }}</dt>
            <dd>{{ value(row, key) }}</dd>
          </div>
        </dl>
        <p
          v-if="row.quality_status"
          class="state-tag"
          :class="{ inactive: row.quality_status !== 'released' }"
        >
          {{ statusNames[String(row.quality_status)] }}
        </p>
        <p v-if="row.expired === true" class="feedback error">
          Lote vencido: não elegível ao FEFO.
        </p>
        <p v-if="row.actor">Por {{ (row.actor as { name: string }).name }}</p>
        <p v-if="resource === 'events' && qualityChange(row)">
          {{ qualityChange(row) }}
        </p>
      </article>
    </div>
    <nav
      v-if="total"
      class="pagination"
      :aria-label="`Paginação de ${resourceNames[resource]}`"
    >
      <button :disabled="busy || page <= 1" @click="load(page - 1)">
        Anterior
      </button><span>Página {{ page }} de {{ last }} · {{ total }} registro(s)</span><button :disabled="busy || page >= last" @click="load(page + 1)">
        Próxima
      </button>
    </nav>
  </section>
</template>
