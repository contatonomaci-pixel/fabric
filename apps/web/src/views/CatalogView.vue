<script setup lang="ts">
import { computed, ref } from "vue";
import CompanyPicker from "../components/CompanyPicker.vue";
import CatalogPanel from "../components/CatalogPanel.vue";
import { catalogDefinitions, type Company } from "../lib/catalog";
const company = ref<Company | null>(null),
  tab = ref("commercial-skus");
const definition = computed(() =>
  catalogDefinitions.find((d) => d.resource === tab.value)!,
);
</script>
<template>
  <section>
    <h1>Catálogo e identificadores</h1>
    <p class="intro">
      Produtos, variantes e apresentações comerciais com identificadores
      permanentes.
    </p>
    <CompanyPicker @selected="company = $event" />
    <template v-if="company">
      <p v-if="company.is_demo" class="demo-note">
        Catálogo de demonstração FICTÍCIO. Pesos nominais não são especificações
        validadas; preços precisam ser definidos.
      </p>
      <p class="field-help">
        Disponibilidade depende também dos cadastros superiores. Uma nova
        embalagem, gramatura ou sabor pode exigir outro SKU. Venda por peso
        exige pesagem real nas fases operacionais.
      </p>
      <nav class="catalog-tabs" aria-label="Seções do catálogo">
        <button
          v-for="item in catalogDefinitions"
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
        :can-manage="company.permissions.includes('catalog.manage')"
        :can-audit="company.permissions.includes('catalog.audit')"
        :timezone="company.timezone"
      />
    </template>
  </section>
</template>
