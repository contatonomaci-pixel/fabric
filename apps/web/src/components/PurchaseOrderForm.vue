<script setup lang="ts">
import { onBeforeUnmount, ref, useId } from "vue";
import { api, csrf, errorMessage } from "../lib/api";
import LookupSelect from "./LookupSelect.vue";
const props = defineProps<{ scope: string }>(),
  emit = defineEmits<{ saved: []; cancel: [] }>();
const id = useId(),
  supplier = ref(""),
  reference = ref(""),
  expected = ref(""),
  reason = ref(""),
  busy = ref(false),
  error = ref("");
const items = ref([
  {
    key: crypto.randomUUID(),
    variant_id: "",
    unit_id: "",
    quantity: "",
    unit_cost: "",
  },
]);
let alive = true,
  key = crypto.randomUUID(),
  lastPayload = "";
onBeforeUnmount(() => {
  alive = false;
});
function addItem() {
  items.value.push({
    key: crypto.randomUUID(),
    variant_id: "",
    unit_id: "",
    quantity: "",
    unit_cost: "",
  });
}
async function save() {
  busy.value = true;
  error.value = "";
  try {
    const payload = {
      supplier_id: supplier.value,
      reference: reference.value.trim() || null,
      expected_on: expected.value || null,
      reason: reason.value.trim(),
      items: items.value.map((item) => ({
        variant_id: item.variant_id,
        unit_id: item.unit_id,
        quantity: item.quantity.trim().replace(",", "."),
        unit_cost: item.unit_cost.trim().replace(",", "."),
      })),
    };
    const fingerprint = JSON.stringify(payload);
    if (lastPayload && lastPayload !== fingerprint) key = crypto.randomUUID();
    lastPayload = fingerprint;
    await csrf();
    if (!alive) return;
    await api.post(`${props.scope}/inventory/actions/order`, {
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
    <h3>Novo pedido de compra</h3>
    <p class="field-help">
      Emissão preserva itens e custos. Recebimentos podem ser parciais;
      cancelamento exige ausência de recebimentos. Não gera título financeiro.
    </p>
    <LookupSelect
      v-model="supplier"
      :endpoint="`${scope}/purchasing/suppliers`"
      label="Fornecedor do pedido"
      required
      :disabled="busy"
    />
    <label :for="`${id}-reference`">Referência do pedido</label><input
      :id="`${id}-reference`"
      v-model="reference"
      maxlength="100"
      :disabled="busy"
    >
    <label :for="`${id}-expected`">Previsão de entrega</label><input
      :id="`${id}-expected`"
      v-model="expected"
      type="date"
      :disabled="busy"
    >
    <fieldset v-for="(item, index) in items" :key="item.key" :disabled="busy">
      <legend>Item {{ index + 1 }}</legend>
      <LookupSelect
        v-model="item.variant_id"
        :endpoint="`${scope}/product-variants`"
        :label="`Variante do item ${index + 1}`"
        required
        :disabled="busy"
      /><LookupSelect
        v-model="item.unit_id"
        :endpoint="`${scope}/units`"
        :label="`Unidade do item ${index + 1}`"
        required
        :disabled="busy"
      />
      <label :for="`${id}-qty-${item.key}`">Quantidade do item {{ index + 1 }}</label><input
        :id="`${id}-qty-${item.key}`"
        v-model="item.quantity"
        inputmode="decimal"
        required
        maxlength="24"
      >
      <label :for="`${id}-cost-${item.key}`">Custo unitário do item {{ index + 1 }} (R$)</label><input
        :id="`${id}-cost-${item.key}`"
        v-model="item.unit_cost"
        inputmode="decimal"
        required
        maxlength="24"
      >
      <button
        v-if="items.length > 1"
        type="button"
        @click="items.splice(index, 1)"
      >
        Remover item {{ index + 1 }}
      </button>
    </fieldset>
    <button
      v-if="items.length < 50"
      type="button"
      :disabled="busy"
      @click="addItem"
    >
      Adicionar item
    </button>
    <label :for="`${id}-reason`">Motivo / referência da compra</label><textarea
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
        {{ busy ? "Emitindo…" : "Emitir pedido" }}
      </button><button type="button" :disabled="busy" @click="emit('cancel')">
        Cancelar
      </button>
    </div>
  </form>
</template>
