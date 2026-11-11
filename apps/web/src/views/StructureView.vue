<script setup lang="ts">
import { computed, ref } from "vue";
import CompanyPicker from "../components/CompanyPicker.vue";
import CatalogPanel from "../components/CatalogPanel.vue";
import { structureDefinitions, type Company } from "../lib/catalog";
const company = ref<Company | null>(null),
  tab = ref("factories");
const definition = computed(() =>
  structureDefinitions.find((d) => d.resource === tab.value)!,
);
</script>
<template>
  <section>
    <h1>Fábricas e depósitos</h1>
    <p class="intro">
      Estrutura física vinculada à empresa. Os locais de armazenagem e os
      saldos por lote estão em Estoque e lotes.
    </p>
    <CompanyPicker @selected="company = $event" /><template v-if="company">
      <nav class="catalog-tabs" aria-label="Seções da estrutura">
        <button
          v-for="item in structureDefinitions"
          :key="item.resource"
          :aria-current="tab === item.resource ? 'page' : undefined"
          @click="tab = item.resource"
        >
          {{ item.title }}
        </button>
      </nav>
      <CatalogPanel
        :key="`${company.public_id}/${tab}`"
        :endpoint="`/companies/${company.public_id}/${tab}`"
        :scope="`/companies/${company.public_id}`"
        :resource="tab"
        :title="definition.title"
        :fields="definition.fields"
        :can-manage="company.permissions.includes('companies.manage')"
        :timezone="company.timezone"
      />
    </template>
  </section>
</template>
