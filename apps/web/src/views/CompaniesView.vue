<script setup lang="ts">
import { onMounted, ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { api, errorMessage } from "../lib/api";
interface Company {
  public_id: string;
  trade_name: string;
  legal_name: string;
  timezone: string;
  is_demo: boolean;
}
const companies = ref<Company[]>([]),
  busy = ref(false),
  error = ref(""),
  page = ref(1),
  lastPage = ref(1),
  total = ref(0);
const router = useRouter();
async function load(next = 1) {
  busy.value = true;
  error.value = "";
  companies.value = [];
  try {
    const { data } = await api.get("/companies", {
      params: { page: next, per_page: 10 },
    });
    companies.value = data.data;
    page.value = data.meta.current_page;
    lastPage.value = data.meta.last_page;
    total.value = data.meta.total;
  } catch (e) {
    if (axios.isAxiosError(e) && e.response?.status === 401) {
      await router.replace("/entrar");
      return;
    }
    if (axios.isAxiosError(e) && e.response?.status === 403) {
      await router.replace("/acesso-negado");
      return;
    }
    error.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
onMounted(() => load());
</script>
<template>
  <section :aria-busy="busy">
    <h1>Empresas e unidades</h1>
    <p class="intro">Empresas que você tem permissão para consultar.</p>
    <p v-if="busy" role="status">Carregando empresas…</p>
    <div v-else-if="error" class="feedback error" role="alert">
      <p>{{ error }}</p>
      <button class="text-button" @click="load(page)">Tentar novamente</button>
    </div>
    <p v-else-if="!companies.length" class="empty-message">
      Nenhuma empresa disponível para seu usuário. Solicite ao administrador a
      revisão dos seus vínculos e permissões.
    </p>
    <template v-else>
      <div class="company-list">
        <article v-for="company in companies" :key="company.public_id">
          <div>
            <h2>{{ company.trade_name }}</h2>
            <p>{{ company.legal_name }}</p>
            <p v-if="company.is_demo" class="demo-note">
              Demonstração fictícia
            </p>
          </div>
          <div>
            <span class="field-help">Fuso horário</span>
            <p>{{ company.timezone }}</p>
          </div>
        </article>
      </div>
      <nav class="pagination" aria-label="Paginação de empresas">
        <button :disabled="page <= 1 || busy" @click="load(page - 1)">
          Anterior
        </button><span>Página {{ page }} de {{ lastPage }} · {{ total }} empresa(s)</span><button :disabled="page >= lastPage || busy" @click="load(page + 1)">
          Próxima
        </button>
      </nav>
    </template>
    <div class="phase-notice">
      <h2>Unidades na próxima etapa</h2>
      <p>
        O cadastro de empresas, fábricas e depósitos faz parte da Fase 1. Nesta
        fase, você consulta apenas os vínculos autorizados.
      </p>
    </div>
  </section>
</template>
