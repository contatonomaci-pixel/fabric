<script setup lang="ts">
import { nextTick, onBeforeUnmount, reactive, ref, useId } from "vue";
import { api, csrf, errorMessage } from "../lib/api";
import type { CatalogRow, Field } from "../lib/catalog";
import LookupSelect from "./LookupSelect.vue";
const props = defineProps<{
  endpoint: string;
  scope: string;
  title: string;
  fields: Field[];
  item: CatalogRow | null;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const id = useId(),
  busy = ref(false),
  error = ref(""),
  heading = ref<HTMLElement>(),
  form = reactive<Record<string, string | boolean>>({});
const attributes = ref<{ key: string; value: string }[]>(
  Object.entries(props.item?.attributes || {}).map(([key, value]) => ({
    key,
    value: value ?? "",
  })),
);
for (const field of props.fields)
  form[field.key] = (props.item?.[field.key] ?? field.default ?? "") as
    string | boolean;
const locked = (field: Field) =>
  !!props.item &&
  (field.lockedOnEdit ||
    (field.lockedWhenUsed && props.item.identity_locked !== false));
function fieldValue(field: Field): unknown {
  if (field.type === "attributes")
    return Object.fromEntries(
      attributes.value.map(({ key, value }) => [key.trim(), value.trim()]),
    );
  if (field.type === "checkbox") return Boolean(form[field.key]);
  let value = String(form[field.key] ?? "").trim();
  if (field.type === "decimal") value = value.replace(",", ".");
  return value || null;
}
const initialValues = Object.fromEntries(
  props.fields.map((field) => [field.key, fieldValue(field)]),
);
let alive = true;
onBeforeUnmount(() => {
  alive = false;
});
void nextTick(() => heading.value?.focus());
async function save() {
  busy.value = true;
  error.value = "";
  const payload: Record<string, unknown> = {};
  try {
    for (const field of props.fields) {
      if (locked(field)) continue;
      if (field.type === "attributes") {
        if (
          new Set(attributes.value.map((a) => a.key.trim())).size !==
          attributes.value.length
        )
          throw new Error("attribute_duplicate");
      }
      const value = fieldValue(field);
      if (
        !props.item ||
        JSON.stringify(value) !== JSON.stringify(initialValues[field.key])
      )
        payload[field.key] = value;
    }
    const endpoint = props.item
      ? `${props.endpoint}/${props.item.public_id}`
      : props.endpoint;
    const editing = !!props.item;
    if (props.item?.active && payload.active === false && !window.confirm(`Desativar ${props.item.name}? Confira o motivo; o histórico será preservado.`)) return;
    await csrf();
    if (!alive) return;
    if (editing) await api.patch(endpoint, payload);
    else await api.post(endpoint, payload);
    if (alive) emit("saved");
  } catch (e) {
    if (alive)
      error.value =
        e instanceof Error && e.message === "attribute_duplicate"
          ? "Use nomes diferentes para as características."
          : errorMessage(e);
  } finally {
    if (alive) busy.value = false;
  }
}
</script>
<template>
  <form class="catalog-form" :aria-busy="busy" @submit.prevent="save">
    <h3 ref="heading" tabindex="-1">
      {{ item ? "Editar" : "Novo cadastro" }} · {{ title }}
    </h3>
    <p v-if="item && fields.some(field => field.key === 'sku')" class="field-help">
      Identificadores e apresentações reservados são preservados. Para outra
      apresentação, crie um novo SKU.
    </p>
    <p v-if="item?.identity_locked === false" class="field-help">
      Este cadastro ainda não foi utilizado. Suas características podem ser
      corrigidas.
    </p>
    <p v-else-if="item?.identity_locked === true" class="field-help">
      Este cadastro já foi utilizado. Para mudar suas características, crie outro cadastro.
    </p>
    <div class="catalog-form-grid">
      <div
        v-for="field in fields"
        :key="field.key"
        :class="{ 'full-width': field.type === 'attributes' }"
      >
        <LookupSelect
          v-if="field.type === 'lookup'"
          :model-value="String(form[field.key] || '')"
          :endpoint="`${scope}/${field.resource}`"
          :label="field.label"
          :required="field.required"
          :disabled="busy || locked(field)"
          @update:model-value="form[field.key] = $event"
        />
        <fieldset
          v-else-if="field.type === 'attributes'"
          :disabled="busy || locked(field)"
        >
          <legend>{{ field.label }}</legend>
          <div
            v-for="(attribute, index) in attributes"
            :key="index"
            class="attribute-row"
          >
            <label :for="`${id}-key-${index}`">Característica {{ index + 1 }}</label><input
              :id="`${id}-key-${index}`"
              v-model="attribute.key"
              required
              maxlength="40"
            >
            <label :for="`${id}-value-${index}`">Valor {{ index + 1 }}</label><input
              :id="`${id}-value-${index}`"
              v-model="attribute.value"
              maxlength="200"
            >
            <button type="button" @click="attributes.splice(index, 1)">
              Remover
            </button>
          </div>
          <button
            v-if="attributes.length < 20"
            type="button"
            @click="attributes.push({ key: '', value: '' })"
          >
            Adicionar característica
          </button>
        </fieldset>
        <label v-else-if="field.type === 'checkbox'" class="checkbox-field"><input
          v-model="form[field.key]"
          type="checkbox"
          :disabled="busy || locked(field)"
        >{{ field.label }}</label>
        <template v-else>
          <label :for="`${id}-${field.key}`">{{ field.label }}</label>
          <select
            v-if="field.type === 'select'"
            :id="`${id}-${field.key}`"
            v-model="form[field.key]"
            :required="field.required"
            :disabled="busy || locked(field)"
          >
            <option
              v-for="option in field.options"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <input
            v-else
            :id="`${id}-${field.key}`"
            v-model="form[field.key]"
            :inputmode="field.type === 'decimal' ? 'decimal' : 'text'"
            :required="field.required"
            :disabled="busy || locked(field)"
            :maxlength="field.type === 'decimal' ? 24 : 200"
          >
        </template>
        <small v-if="field.help" class="field-help">{{ field.help }}</small>
      </div>
    </div>
    <p v-if="error" class="feedback error" role="alert">{{ error }}</p>
    <div class="catalog-actions">
      <button class="primary-button" :disabled="busy">
        {{ busy ? "Salvando…" : "Salvar cadastro" }}
      </button><button type="button" :disabled="busy" @click="emit('cancel')">
        Cancelar
      </button>
    </div>
  </form>
</template>
