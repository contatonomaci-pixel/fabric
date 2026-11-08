<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { api, errorMessage } from "../lib/api";
import { money } from "../lib/catalog";
const props = defineProps<{
  scope: string;
  resourceId: string;
  timezone?: string;
}>();
const emit = defineEmits<{ close: [] }>();
interface Event {
  public_id: string;
  action: string;
  created_at: string;
  before: Record<string, unknown> | null;
  after: Record<string, unknown>;
  actor?: { name: string };
}
const rows = ref<Event[]>([]),
  error = ref(""),
  busy = ref(false),
  page = ref(1),
  last = ref(1);
let generation = 0;
async function load(next = 1) {
  const version = ++generation;
  busy.value = true;
  error.value = "";
  try {
    const { data } = await api.get(`${props.scope}/catalog-history`, {
      params: { resource_id: props.resourceId, page: next, per_page: 10 },
    });
    if (version !== generation) return;
    rows.value = data.data;
    page.value = data.meta.current_page;
    last.value = data.meta.last_page;
  } catch (e) {
    if (version === generation) error.value = errorMessage(e);
  } finally {
    if (version === generation) busy.value = false;
  }
}
function date(value: string) {
  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: props.timezone || "UTC",
  }).format(new Date(value));
}
onMounted(() => load());
onBeforeUnmount(() => {
  generation++;
});
</script>
<template>
  <section
    class="catalog-history"
    aria-label="Histórico do cadastro"
    :aria-busy="busy"
  >
    <div class="catalog-heading">
      <h3>Histórico do cadastro</h3>
      <button @click="emit('close')">Fechar histórico</button>
    </div>
    <p v-if="busy" role="status">Carregando histórico…</p>
    <p v-if="error" role="alert" class="feedback error">{{ error }}</p>
    <ol v-if="rows.length" class="history-list">
      <li v-for="event in rows" :key="event.public_id">
        <strong>{{
          event.action === "created"
            ? "Cadastro criado"
            : event.action === "reserved"
              ? "Código reservado"
              : "Cadastro atualizado"
        }}</strong>
        · {{ date(event.created_at) }}
        <p>Por {{ event.actor?.name || "Usuário registrado na auditoria" }}</p>
        <p v-if="event.before && event.before.price !== event.after.price">
          Preço: {{ money(event.before.price as string | null) }} →
          {{ money(event.after.price as string | null) }}
        </p>
        <p v-if="event.before && event.before.active !== event.after.active">
          Estado: {{ event.before.active ? "Ativo" : "Inativo" }} →
          {{ event.after.active ? "Ativo" : "Inativo" }}
        </p>
      </li>
    </ol>
    <p v-else-if="!busy && !error">Nenhuma alteração registrada.</p>
    <nav class="pagination" aria-label="Paginação do histórico">
      <button :disabled="page <= 1 || busy" @click="load(page - 1)">
        Anterior
      </button><span>{{ page }} / {{ last }}</span><button :disabled="page >= last || busy" @click="load(page + 1)">
        Próxima
      </button>
    </nav>
  </section>
</template>
