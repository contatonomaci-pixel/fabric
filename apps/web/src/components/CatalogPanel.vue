<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { api, csrf, errorMessage } from "../lib/api";
import {
  money,
  productTypes,
  type CatalogRow,
  type Field,
} from "../lib/catalog";
import CatalogForm from "./CatalogForm.vue";
import CatalogHistory from "./CatalogHistory.vue";
import LookupSelect from "./LookupSelect.vue";
const props = withDefaults(
  defineProps<{
    endpoint: string;
    scope: string;
    resource: string;
    title: string;
    fields: Field[];
    canManage?: boolean;
    canAudit?: boolean;
    companyMode?: boolean;
    timezone?: string;
    hideToggle?: boolean;
  }>(),
  { canManage: false, canAudit: false, companyMode: false, timezone: "UTC" },
);
const rows = ref<CatalogRow[]>([]),
  busy = ref(false),
  error = ref(""),
  notice = ref(""),
  page = ref(1),
  last = ref(1),
  total = ref(0),
  canCreateCompany = ref(false),
  query = ref(""),
  active = ref("");
const family = ref(""),
  variant = ref(""),
  type = ref(""),
  formOpen = ref(false),
  editing = ref<CatalogRow | null>(null),
  history = ref<string | null>(null),
  changing = ref("");
const filteredCatalog = computed(() =>
  ["products", "product-variants", "commercial-skus"].includes(props.resource),
);
const canCreate = computed(() =>
  props.companyMode ? canCreateCompany.value : props.canManage,
);
const mayEdit = (row: CatalogRow) =>
  props.companyMode
    ? row.permissions?.includes("companies.manage")
    : props.canManage;
let generation = 0,
  alive = true;
async function load(next = 1) {
  const version = ++generation;
  busy.value = true;
  error.value = "";
  rows.value = [];
  try {
    const { data } = await api.get(props.endpoint, {
      params: {
        page: next,
        per_page: 10,
        q: query.value || undefined,
        active: active.value || undefined,
        family_id: family.value || undefined,
        variant_id: variant.value || undefined,
        type: type.value || undefined,
        include_inactive: props.companyMode ? 1 : undefined,
      },
    });
    if (version !== generation || !alive) return;
    rows.value = data.data;
    page.value = data.meta.current_page;
    last.value = data.meta.last_page;
    total.value = data.meta.total;
    canCreateCompany.value = !!data.meta.can_create;
  } catch (e) {
    if (version === generation && alive) error.value = errorMessage(e);
  } finally {
    if (version === generation && alive) busy.value = false;
  }
}
function edit(item: CatalogRow | null) {
  editing.value = item;
  formOpen.value = true;
  history.value = null;
  notice.value = "";
}
function saved() {
  formOpen.value = false;
  notice.value = "Cadastro salvo com sucesso.";
  void load(page.value);
}
async function toggle(row: CatalogRow) {
  if (
    !window.confirm(
      `${row.active ? "Desativar" : "Reativar"} ${row.name}? O histórico e os códigos reservados serão mantidos.`,
    )
  )
    return;
  changing.value = row.public_id;
  error.value = "";
  const endpoint = `${props.endpoint}/${row.public_id}`;
  try {
    await csrf();
    if (!alive) return;
    await api.patch(endpoint, { active: !row.active });
    if (alive) {
      notice.value =
        "Estado atualizado; histórico e identificadores preservados.";
      await load(page.value);
    }
  } catch (e) {
    if (alive) error.value = errorMessage(e);
  } finally {
    if (alive) changing.value = "";
  }
}
watch(
  () => props.endpoint,
  () => {
    formOpen.value = false;
    history.value = null;
    query.value = "";
    active.value = "";
    family.value = "";
    variant.value = "";
    type.value = "";
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
  <section class="catalog-panel" :aria-label="title" :aria-busy="busy">
    <div class="catalog-heading">
      <h2>{{ title }}</h2>
      <button
        v-if="canCreate && !formOpen"
        class="primary-button"
        @click="edit(null)"
      >
        Novo cadastro
      </button>
    </div>
    <p v-if="notice" class="feedback success" role="status">{{ notice }}</p>
    <CatalogForm
      v-if="formOpen"
      :key="editing?.public_id || 'new'"
      :endpoint="endpoint"
      :scope="scope"
      :title="title"
      :fields="fields"
      :item="editing"
      @saved="saved"
      @cancel="formOpen = false"
    />
    <form class="catalog-toolbar" role="search" @submit.prevent="load()">
      <label>Buscar em {{ title
      }}<input
        v-model="query"
        maxlength="100"
        placeholder="Nome ou identificador"
      ></label><label v-if="!companyMode">Estado do cadastro<select v-model="active">
        <option value="">Todos</option>
        <option value="1">Ativos</option>
        <option value="0">Inativos</option>
      </select></label><button :disabled="busy">Buscar</button>
    </form>
    <p v-if="!companyMode" class="field-help">
      O filtro considera o estado próprio do cadastro. A disponibilidade também
      depende dos cadastros superiores.
    </p>
    <details v-if="filteredCatalog" class="catalog-filter-details">
      <summary>Filtros avançados</summary>
      <div class="catalog-filters">
        <LookupSelect
          :endpoint="`${scope}/product-families`"
          :model-value="family"
          label="Filtrar por família"
          @update:model-value="
            family = $event;
            load();
          "
        /><LookupSelect
          v-if="resource === 'commercial-skus'"
          :endpoint="`${scope}/product-variants`"
          :model-value="variant"
          label="Filtrar por variante"
          @update:model-value="
            variant = $event;
            load();
          "
        /><label>Filtrar por tipo<select v-model="type" @change="load()">
          <option value="">Todos</option>
          <option
            v-for="option in productTypes"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select></label>
      </div>
    </details>
    <p v-if="busy" role="status">Carregando {{ title.toLowerCase() }}…</p>
    <div v-if="error" class="feedback error" role="alert">
      <p>{{ error }}</p>
      <button @click="load(page)">Tentar novamente</button>
    </div>
    <p v-else-if="!busy && !rows.length" class="empty-message">
      {{
        companyMode
          ? "Nenhuma empresa disponível para seu usuário."
          : "Nenhum cadastro encontrado. Ajuste os filtros ou crie um cadastro autorizado."
      }}
    </p>
    <div class="catalog-list">
      <article v-for="row in rows" :key="row.public_id" class="catalog-record">
        <div class="record-main">
          <h3>{{ row.name || row.trade_name }}</h3>
          <span class="state-tag" :class="{ inactive: !row.active }">{{
            row.active ? "Ativo" : "Inativo"
          }}</span>
          <span
            v-if="row.active && row.effective_active === false"
            class="state-tag inactive"
          >Indisponível por dependência</span>
          <p v-if="row.is_demo" class="demo-note">Demonstração fictícia</p>
          <p v-if="row.legal_name">{{ row.legal_name }}</p>
          <p v-if="row.sku">
            <strong>{{ row.sku }}</strong> · {{ money(row.price) }}
          </p>
          <p v-if="row.product_id_label">{{ row.product_id_label }}</p>
          <p v-if="row.variant_id_label">
            {{ row.variant_id_label }} · {{ row.unit_id_label }}
          </p>
          <p v-if="row.family_id_label">Família: {{ row.family_id_label }}</p>
          <p v-if="row.factory_id_label">Fábrica: {{ row.factory_id_label }}</p>
          <p v-if="row.code">
            {{ row.code }} ·
            {{
              row.dimension === "package"
                ? "Conteúdo por SKU"
                : `Fator base: ${row.ratio_to_base}`
            }}
          </p>
          <p v-if="row.timezone">{{ row.timezone }}</p>
        </div>
        <div class="catalog-actions">
          <button v-if="mayEdit(row)" :disabled="!!changing" @click="edit(row)">
            Editar
          </button><button
            v-if="mayEdit(row) && !hideToggle"
            :disabled="!!changing"
            @click="toggle(row)"
          >
            {{
              changing === row.public_id
                ? "Atualizando…"
                : row.active
                  ? "Desativar"
                  : "Reativar"
            }}
          </button><button v-if="canAudit" @click="history = row.public_id">
            Histórico
          </button>
        </div>
        <div v-if="row.barcode" class="barcode-preview">
          <img
            :src="`/api/v1${endpoint}/${row.public_id}/barcode.svg`"
            :alt="`Code128 interno ${row.barcode.code}`"
            loading="lazy"
          >
          <p>
            {{ row.barcode.code }} · Uso interno, sem GTIN ou homologação de PDV
          </p>
          <div class="catalog-actions">
            <a
              v-for="format in ['svg', 'png', 'pdf']"
              :key="format"
              :href="`/api/v1${endpoint}/${row.public_id}/barcode.${format}`"
            >{{ format.toUpperCase() }} de teste</a>
          </div>
        </div>
      </article>
    </div>
    <nav v-if="total" class="pagination" :aria-label="`Paginação de ${title}`">
      <button :disabled="page <= 1 || busy" @click="load(page - 1)">
        Anterior
      </button><span>Página {{ page }} de {{ last }} · {{ total }} cadastro(s)</span><button :disabled="page >= last || busy" @click="load(page + 1)">
        Próxima
      </button>
    </nav>
    <CatalogHistory
      v-if="history"
      :key="history"
      :scope="scope"
      :resource-id="history"
      :timezone="timezone"
      @close="history = null"
    />
  </section>
</template>
