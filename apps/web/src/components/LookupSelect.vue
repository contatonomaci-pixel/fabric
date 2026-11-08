<script setup lang="ts">
import { onBeforeUnmount, ref, useId, watch } from "vue";
import { api, errorMessage } from "../lib/api";
import type { CatalogRow } from "../lib/catalog";
const props = defineProps<{
  endpoint: string;
  modelValue: string;
  label: string;
  required?: boolean;
  disabled?: boolean;
}>();
const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const id = useId(),
  query = ref(""),
  rows = ref<CatalogRow[]>([]),
  error = ref(""),
  busy = ref(false),
  total = ref(0);
let generation = 0;
async function load() {
  const version = ++generation;
  const endpoint = props.endpoint;
  busy.value = true;
  error.value = "";
  try {
    const { data } = await api.get(endpoint, {
      params: { q: query.value || undefined, per_page: 20, active: 1 },
    });
    if (version !== generation) return;
    let options = data.data as CatalogRow[];
    if (
      props.modelValue &&
      !options.some((row) => row.public_id === props.modelValue)
    ) {
      const selected = await api.get(`${endpoint}/${props.modelValue}`);
      if (version !== generation) return;
      options = [selected.data.data as CatalogRow, ...options];
    }
    rows.value = options;
    total.value = data.meta.total;
  } catch (e) {
    if (version === generation) error.value = errorMessage(e);
  } finally {
    if (version === generation) busy.value = false;
  }
}
watch(
  () => [props.endpoint, props.modelValue],
  () => {
    rows.value = [];
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  generation++;
});
</script>
<template>
  <div class="lookup-field">
    <label :for="id">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      :required="required"
      :disabled="disabled || busy"
      @change="
        emit('update:modelValue', ($event.target as HTMLSelectElement).value)
      "
    >
      <option value="">{{ busy ? "Carregando…" : "Selecione" }}</option>
      <option v-for="row in rows" :key="row.public_id" :value="row.public_id">
        {{ row.name || row.trade_name }}{{ row.sku ? ` · ${row.sku}` : "" }}
      </option>
    </select>
    <div v-if="!disabled" class="lookup-search">
      <input
        v-model="query"
        :aria-label="`Buscar opções: ${label}`"
        maxlength="100"
        placeholder="Buscar opções"
        @keydown.enter.prevent="load"
      >
      <button
        type="button"
        :disabled="busy"
        :aria-label="`Buscar opções de ${label}`"
        @click="load"
      >
        Buscar
      </button>
    </div>
    <small v-if="total > 20" class="field-help">Mostrando até 20 opções; refine a busca.</small>
    <p v-if="error" role="alert" class="feedback error">{{ error }}</p>
  </div>
</template>
