<script setup lang="ts">
import CatalogPanel from "../components/CatalogPanel.vue";
import { companyFields } from "../lib/catalog";
</script>
<template>
  <section>
    <h1>Empresas e unidades</h1>
    <p class="intro">
      Cadastros e vínculos autorizados. Administradores gerenciam apenas as
      próprias empresas.
    </p>
    <CatalogPanel
      endpoint="/companies"
      scope=""
      resource="companies"
      title="Empresas"
      :fields="companyFields"
      company-mode
    />
    <p class="phase-notice">
      <RouterLink to="/estrutura">Gerenciar fábricas e depósitos</RouterLink> ·
      <RouterLink to="/catalogo">Abrir catálogo e identificadores</RouterLink>
    </p>
  </section>
</template>
