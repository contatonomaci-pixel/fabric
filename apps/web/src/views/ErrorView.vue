<script setup lang="ts">
import { computed } from "vue";
const props = defineProps<{ status: string }>();
const title = computed(() =>
  props.status === "403"
    ? "Acesso não permitido"
    : props.status === "503"
      ? "Não foi possível conectar"
      : "Página não encontrada",
);
</script>
<template>
  <main class="error-page">
    <p class="error-number">{{ status }}</p>
    <h1>{{ title }}</h1>
    <p>
      {{
        status === "403"
          ? "Seu usuário não tem permissão para acessar este recurso."
          : status === "503"
            ? "Verifique a conexão com a API e tente novamente."
            : "Confira o endereço ou volte ao seu espaço de trabalho."
      }}
    </p>
    <RouterLink to="/">Voltar ao início</RouterLink><RouterLink to="/entrar">Entrar novamente</RouterLink>
  </main>
</template>
