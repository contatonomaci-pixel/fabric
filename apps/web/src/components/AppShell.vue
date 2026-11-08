<script setup lang="ts">
import FactoryIcon from "./FactoryIcon.vue";
import { ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { House, Building2, Boxes, Package, LogOut, UserRound } from "@lucide/vue";
import { auth, logout } from "../lib/auth";
import { errorMessage } from "../lib/api";
const route = useRoute(),
  router = useRouter();
const busy = ref(false),
  error = ref("");
async function signOut() {
  busy.value = true;
  error.value = "";
  try {
    await logout();
    await router.replace("/entrar");
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <div class="app-layout">
    <a class="skip-link" href="#main">Pular para o conteúdo</a>
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-mark"><FactoryIcon filled :size="24" aria-hidden="true" /></span><span><strong>FactoryControl</strong> PRO</span>
      </div>
      <nav aria-label="Navegação principal">
        <RouterLink to="/" exact-active-class="selected"><House :size="20" aria-hidden="true" />Visão geral</RouterLink>
        <RouterLink to="/empresas" active-class="selected"><Building2 :size="20" aria-hidden="true" />Empresas e
          unidades</RouterLink>
        <RouterLink to="/estrutura" active-class="selected"><Boxes :size="20" aria-hidden="true" />Fábricas e depósitos</RouterLink>
        <RouterLink to="/catalogo" active-class="selected"><Package :size="20" aria-hidden="true" />Catálogo e identificadores</RouterLink>
      </nav>
      <div class="sidebar-footer">Fase 1 · Catálogo</div>
    </aside>
    <div class="workspace">
      <header class="topbar">
        <div class="breadcrumb">
          Workspace <span>/</span> <strong>{{ route.meta.title }}</strong>
        </div>
        <div class="user-menu">
          <UserRound :size="20" aria-hidden="true" /><span>{{
            auth.user?.name
          }}</span><button class="text-button" :disabled="busy" @click="signOut">
            <LogOut :size="18" aria-hidden="true" />{{
              busy ? "Saindo…" : "Sair"
            }}
          </button>
        </div>
      </header>
      <main id="main" tabindex="-1">
        <p v-if="error" class="feedback error" role="alert">{{ error }}</p>
        <RouterView />
      </main>
      <footer class="workspace-footer">
        <span v-if="auth.environment === 'development'">Ambiente de desenvolvimento</span><span v-if="auth.user?.is_demo">{{ auth.environment === "development" ? " · " : "" }}Dados de
          demonstração fictícios</span>
      </footer>
    </div>
  </div>
</template>
