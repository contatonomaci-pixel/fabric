<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId } from "vue";
import { api, errorMessage } from "../lib/api";
import type { Company } from "../lib/catalog";
const emit = defineEmits<{ selected: [company: Company | null] }>();
const id = useId(),
  companies = ref<Company[]>([]),
  query = ref(""),
  selected = ref(""),
  busy = ref(false),
  error = ref("");
let generation = 0;
async function load() {
  const version = ++generation;
  busy.value = true;
  error.value = "";
  emit("selected", null);
  selected.value = "";
  try {
    const { data } = await api.get("/companies", {
      params: { per_page: 100, q: query.value || undefined },
    });
    if (version !== generation) return;
    companies.value = data.data;
    selected.value = companies.value[0]?.public_id || "";
    changed();
  } catch (e) {
    if (version === generation) error.value = errorMessage(e);
  } finally {
    if (version === generation) busy.value = false;
  }
}
function changed() {
  emit(
    "selected",
    companies.value.find((c) => c.public_id === selected.value) || null,
  );
}
onMounted(load);
onBeforeUnmount(() => {
  generation++;
});
</script>
<template>
  <div class="company-picker" :aria-busy="busy">
    <div>
      <label :for="id">Empresa de trabalho</label><select :id="id" v-model="selected" :disabled="busy" @change="changed">
        <option v-if="!companies.length" value="">
          {{ busy ? "Carregando…" : "Nenhuma empresa disponível" }}
        </option>
        <option
          v-for="company in companies"
          :key="company.public_id"
          :value="company.public_id"
        >
          {{ company.trade_name }}
        </option>
      </select>
    </div>
    <div class="lookup-search">
      <input
        v-model="query"
        aria-label="Buscar empresa"
        placeholder="Buscar empresa pelo nome"
        maxlength="100"
        @keydown.enter.prevent="load"
      ><button :disabled="busy" @click="load">Buscar empresa</button>
    </div>
    <small class="field-help">Somente empresas ativas autorizadas. Refine a busca para localizar outra
      empresa.</small>
    <p v-if="error" role="alert" class="feedback error">{{ error }}</p>
  </div>
</template>
