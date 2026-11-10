<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch, useId } from "vue";
import { api, csrf, errorMessage } from "../lib/api";
import { commandFields, commandNames } from "../lib/inventory";
import LookupSelect from "./LookupSelect.vue";
import type { CatalogRow } from "../lib/catalog";
const props = defineProps<{
  scope: string;
  kind: string;
  canRelease: boolean;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const id = useId(),
  fields = commandFields[props.kind],
  values = reactive<Record<string, string>>({}),
  reason = ref(""),
  busy = ref(false),
  error = ref(""),
  allocations = ref<CatalogRow[]>([]),
  measurements = reactive<Record<string, { gross_g: string; tare_g: string }>>(
    {},
  );
for (const field of fields) values[field.key] = String(field.default ?? "");
let alive = true,
  generation = 0,
  lastPayload = "",
  key = crypto.randomUUID();
onBeforeUnmount(() => {
  alive = false;
  generation++;
});
watch(
  () => values.reservation_id,
  async (value) => {
    const version = ++generation;
    allocations.value = [];
    if (props.kind !== "consume" || !value) return;
    try {
      const { data } = await api.get(`${props.scope}/inventory/allocations`, {
        params: { reservation_id: value, per_page: 100 },
      });
      if (!alive || version !== generation) return;
      allocations.value = data.data;
      for (const row of allocations.value)
        measurements[row.public_id] = { gross_g: "", tare_g: "" };
    } catch (e) {
      if (alive && version === generation) error.value = errorMessage(e);
    }
  },
);
async function save() {
  busy.value = true;
  error.value = "";
  try {
    const payload: Record<string, unknown> = { reason: reason.value.trim() };
    for (const field of fields) {
      let value = values[field.key]?.trim() || null;
      if (field.type === "decimal" && value) value = value.replace(",", ".");
      payload[field.key] = value;
    }
    if (props.kind === "consume")
      payload.measurements = allocations.value
        .filter((row) => row.mode !== "count")
        .map((row) => ({
          allocation_id: row.public_id,
          gross_g: measurements[row.public_id]!.gross_g.trim().replace(
            ",",
            ".",
          ),
          tare_g: measurements[row.public_id]!.tare_g.trim().replace(",", "."),
        }));
    const fingerprint = JSON.stringify(payload);
    if (lastPayload && lastPayload !== fingerprint) key = crypto.randomUUID();
    lastPayload = fingerprint;
    if (
      [
        "issue",
        "consume",
        "transfer",
        "count",
        "quality",
        "reverse",
        "cancel-order",
        "cancel-reservation",
      ].includes(props.kind) &&
      !window.confirm(
        `${commandNames[props.kind]}? Confira os lotes, quantidades e o motivo. O histórico será preservado.`,
      )
    )
      return;
    await csrf();
    if (!alive) return;
    await api.post(`${props.scope}/inventory/actions/${props.kind}`, {
      ...payload,
      idempotency_key: key,
    });
    if (alive) emit("saved");
  } catch (e) {
    if (alive) error.value = errorMessage(e);
  } finally {
    if (alive) busy.value = false;
  }
}
</script>
<template>
  <form class="catalog-form" :aria-busy="busy" @submit.prevent="save">
    <h3>{{ commandNames[kind] }}</h3>
    <p class="field-help">
      Informe apenas as grandezas da política: UN, massa ou ambas. Massa exige
      bruto/tara reais; não use peso nominal. Pesagem isolada não altera saldo.
    </p>
    <div class="catalog-form-grid">
      <div v-for="field in fields" :key="field.key">
        <LookupSelect
          v-if="field.type === 'lookup'"
          v-model="values[field.key]!"
          :endpoint="`${scope}/${field.resource}`"
          :label="field.label"
          :required="field.required"
          :disabled="busy"
          :filters="field.filters"
        />
        <template v-else>
          <label :for="`${id}-${field.key}`">{{ field.label }}</label>
          <select
            v-if="field.type === 'select'"
            :id="`${id}-${field.key}`"
            v-model="values[field.key]"
            :disabled="busy"
            :required="field.required"
          >
            <option value="">Selecione</option>
            <option
              v-for="option in field.options"
              :key="option.value"
              :value="option.value"
              :disabled="
                kind === 'receive' &&
                  !canRelease &&
                  option.value !== 'quarantine'
              "
            >
              {{ option.label }}
            </option>
          </select>
          <input
            v-else
            :id="`${id}-${field.key}`"
            v-model="values[field.key]"
            :type="field.date ? 'date' : 'text'"
            :inputmode="field.type === 'decimal' ? 'decimal' : 'text'"
            :maxlength="field.type === 'decimal' ? 24 : 500"
            :required="field.required"
            :disabled="busy"
          >
        </template>
        <small v-if="field.help" class="field-help">{{ field.help }}</small>
      </div>
    </div>
    <fieldset
      v-for="row in allocations.filter((row) => row.mode !== 'count')"
      :key="row.public_id"
      :disabled="busy"
    >
      <legend>Confirmar {{ row.name }} · {{ row.grams }} g reservados</legend>
      <label :for="`${id}-${row.public_id}-gross`">Bruto desta alocação (g)</label><input
        :id="`${id}-${row.public_id}-gross`"
        v-model="measurements[row.public_id]!.gross_g"
        inputmode="decimal"
        required
        maxlength="24"
      >
      <label :for="`${id}-${row.public_id}-tare`">Tara desta alocação (g)</label><input
        :id="`${id}-${row.public_id}-tare`"
        v-model="measurements[row.public_id]!.tare_g"
        inputmode="decimal"
        required
        maxlength="24"
      >
    </fieldset>
    <label :for="`${id}-reason`">Motivo / referência da operação</label><textarea
      :id="`${id}-reason`"
      v-model="reason"
      required
      minlength="3"
      maxlength="500"
      :disabled="busy"
    />
    <p v-if="error" role="alert" class="feedback error">{{ error }}</p>
    <div class="catalog-actions">
      <button class="primary-button" :disabled="busy">
        {{ busy ? "Registrando…" : "Registrar operação" }}
      </button><button type="button" :disabled="busy" @click="emit('cancel')">
        Cancelar
      </button>
    </div>
  </form>
</template>
