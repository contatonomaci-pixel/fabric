<script setup lang="ts">
import FactoryIcon from "../components/FactoryIcon.vue";
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

import { clearSession, login } from "../lib/auth";
import { api, csrf, errorMessage } from "../lib/api";
const route = useRoute(),
  router = useRouter();
const mode = computed(() =>
  route.path === "/recuperar-senha"
    ? "forgot"
    : route.path === "/redefinir-senha"
      ? "reset"
      : "login",
);
const title = computed(() =>
  mode.value === "forgot"
    ? "Recupere seu acesso"
    : mode.value === "reset"
      ? "Defina uma nova senha"
      : "Acesse seu espaço de trabalho",
);
const email = ref(
  typeof route.query.email === "string" ? route.query.email : "",
);
const password = ref(""),
  confirmation = ref(""),
  busy = ref(false),
  error = ref(""),
  message = ref(""),
  resetComplete = ref(false);
watch(() => route.fullPath, () => {
  password.value = "";
  confirmation.value = "";
  // A retirada do token após sucesso mantém a confirmação visível.
  if (mode.value === "reset" && resetComplete.value && !route.query.token) return;
  error.value = "";
  message.value = "";
  resetComplete.value = false;
  if (typeof route.query.email === "string") email.value = route.query.email;
});
async function submit() {
  if (busy.value || resetComplete.value) return;
  const submittedPath = route.fullPath;
  const submittedMode = mode.value;
  const payload = submittedMode === "forgot" ? { email: email.value } : {
    email: email.value,
    password: password.value,
    password_confirmation: confirmation.value,
    token: typeof route.query.token === "string" ? route.query.token : "",
  };
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    if (submittedMode === "login") {
      await login(email.value, password.value);
      if (route.fullPath !== submittedPath) return;
      await router.replace("/");
    } else {
      await csrf();
      if (route.fullPath !== submittedPath) return;
      const endpoint =
        submittedMode === "forgot"
          ? "/auth/forgot-password"
          : "/auth/reset-password";
      const { data } = await api.post(endpoint, payload);
      if (route.fullPath !== submittedPath) return;
      message.value = data.data.message;
      if (submittedMode === "reset") {
        clearSession();
        resetComplete.value = true;
        await router.replace({ path: "/redefinir-senha", query: {} });
        password.value = "";
        confirmation.value = "";
      }
    }
  } catch (e) {
    if (route.fullPath === submittedPath) error.value = errorMessage(e);
  } finally {
    busy.value = false;
    if (mode.value === "login") password.value = "";
  }
}
</script>
<template>
  <div class="access-page">
    <aside class="access-aside">
      <div class="brand">
        <span class="brand-mark"><FactoryIcon filled :size="26" aria-hidden="true" /></span><span><strong>FactoryControl</strong> PRO</span>
      </div>
      <div class="access-note">
        <h2>A base para sua operação.</h2>
        <p>
          Um espaço para conectar sua equipe aos próximos passos da fábrica.
        </p>
      </div>
      <p>Fase 0 · Fundação</p>
    </aside>
    <main class="access-main">
      <form class="access-form" :aria-busy="busy" @submit.prevent="submit">
        <h1>{{ title }}</h1>
        <p class="intro">
          {{
            resetComplete ? "Sua senha foi atualizada. Entre para continuar." : mode === "login"
              ? "Entre com o e-mail e a senha da sua conta."
              : "Use o e-mail vinculado à sua conta."
          }}
        </p>
        <p v-if="error" class="feedback error" role="alert">{{ error }}</p>
        <p v-if="message" class="feedback success" role="status">
          {{ message }}
        </p>
        <template v-if="!resetComplete">
          <label for="email">E-mail</label><input
            id="email"
            v-model="email"
            name="email"
            type="email"
            autocomplete="username"
            required
            maxlength="254"
            :disabled="busy"
          >
          <template v-if="mode !== 'forgot'">
            <label for="password">{{
              mode === "reset" ? "Nova senha" : "Senha"
            }}</label><input
              id="password"
              v-model="password"
              name="password"
              type="password"
              :autocomplete="
                mode === 'login' ? 'current-password' : 'new-password'
              "
              required
              :minlength="mode === 'reset' ? 12 : undefined"
              maxlength="256"
              :disabled="busy"
              :aria-describedby="mode === 'reset' ? 'password-help' : undefined"
            >
          </template>
          <template v-if="mode === 'reset'">
            <p id="password-help" class="field-help">
              Use 12 ou mais caracteres, com maiúsculas, minúsculas, número e
              símbolo.
            </p>
            <label for="confirmation">Confirme a nova senha</label><input
              id="confirmation"
              v-model="confirmation"
              type="password"
              autocomplete="new-password"
              required
              minlength="12"
              maxlength="256"
              :disabled="busy"
            >
          </template>
          <button class="primary-button" :disabled="busy" type="submit">
            {{
              busy
                ? "Aguarde…"
                : mode === "login"
                  ? "Entrar"
                  : mode === "forgot"
                    ? "Enviar instruções"
                    : "Redefinir senha"
            }}
          </button>
        </template>
        <RouterLink v-if="mode === 'login'" to="/recuperar-senha">Esqueci minha senha</RouterLink><RouterLink v-else to="/entrar">Voltar para entrar</RouterLink>
      </form>
    </main>
  </div>
</template>
