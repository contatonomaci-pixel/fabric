<script setup lang="ts">
import { computed, ref, useId } from "vue";
import CompanyPicker from "../components/CompanyPicker.vue";
import CatalogPanel from "../components/CatalogPanel.vue";
import InventoryPanel from "../components/InventoryPanel.vue";
import type { Company } from "../lib/catalog";
import {
  directoryFields,
  resourceNames,
  resourcePaths,
} from "../lib/inventory";
const props = defineProps<{ area: "purchasing" | "inventory" }>();
const sectionId = useId();
const company = ref<Company | null>(null),
  selected = ref("");
const resources = computed(() =>
  props.area === "purchasing"
    ? ["orders", "order-items", "receipts", "suppliers"]
    : [
        "positions",
        "lots",
        "reservations",
        "allocations",
        "movements",
        "counts",
        "weighings",
        "operations",
        "policies",
        "locations",
        "events",
      ],
);
const permitted = computed(() =>
  resources.value.filter((resource) =>
    company.value?.permissions.includes(
      resource === "events"
        ? "inventory.audit"
        : props.area === "purchasing"
          ? "purchasing.view"
          : "inventory.view",
    ),
  ),
);
const tab = computed(() =>
  permitted.value.includes(selected.value)
    ? selected.value
    : permitted.value[0] || "",
);
const scope = computed(() => `/companies/${company.value?.public_id}`);
</script>
<template>
  <section>
    <h1>
      {{ area === "purchasing" ? "Compras e recebimento" : "Estoque e lotes" }}
    </h1>
    <p class="intro">
      {{
        area === "purchasing"
          ? "Fornecedores, pedidos e recebimentos parciais com origem por lote."
          : "Posições, reservas FEFO, movimentações e medições reais por lote/local."
      }}
    </p>
    <p class="field-help">
      Fase 2: sem produção, vendas ou lançamento financeiro. Validade e política
      física precisam ser declaradas por usuário autorizado; nenhuma massa vem
      do peso nominal.
    </p>
    <CompanyPicker @selected="company = $event" />
    <template v-if="company">
      <p v-if="company.is_demo" class="demo-note">
        Ambiente de demonstração FICTÍCIO. Configure políticas e informe
        medições/datas de teste; não utilizar dados como parâmetros técnicos
        reais.
      </p>
      <div v-if="permitted.length" class="operations-selector">
        <label :for="sectionId">Seção do módulo</label>
        <select
          :id="sectionId"
          :value="tab"
          @change="selected = ($event.target as HTMLSelectElement).value"
        >
          <option
            v-for="resource in permitted"
            :key="resource"
            :value="resource"
          >
            {{ resourceNames[resource] }}
          </option>
        </select>
      </div>
      <CatalogPanel
        v-if="tab && directoryFields[tab]"
        :key="`${company.public_id}/${tab}`"
        :endpoint="`${scope}/${resourcePaths[tab]}`"
        :scope="scope"
        :resource="tab"
        :title="resourceNames[tab]!"
        :fields="directoryFields[tab]!"
        :can-manage="
          company.permissions.includes(
            tab === 'suppliers' ? 'purchasing.manage' : 'inventory.configure',
          )
        "
        :hide-toggle="true"
        :timezone="company.timezone"
      />
      <InventoryPanel
        v-else-if="tab"
        :key="`${company.public_id}/${tab}`"
        :company="company"
        :resource="tab"
      />
      <p v-else class="empty-message">
        Seu usuário não tem permissão para este módulo nesta empresa.
      </p>
    </template>
  </section>
</template>
