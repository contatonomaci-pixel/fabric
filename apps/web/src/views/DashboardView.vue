<script setup lang="ts">
import FactoryIcon from "../components/FactoryIcon.vue";

</script>
<template>
  <section>
    <h1>Seu espaço de trabalho</h1>
    <p class="intro">Organize a estrutura, o catálogo, as compras e o estoque da sua empresa.</p>
    <div class="empty-state">
      <FactoryIcon :size="78" aria-hidden="true" />
      <h2>Acompanhe sua operação</h2>
      <p>
        Cadastre produtos, registre recebimentos e acompanhe lotes e movimentos
        de estoque. Produção e vendas serão habilitadas nas próximas fases.
      </p>
      <RouterLink to="/empresas">Consultar empresas e unidades</RouterLink>
      <RouterLink to="/catalogo">Abrir catálogo e identificadores</RouterLink>
      <RouterLink to="/compras">Abrir compras e recebimento</RouterLink>
      <RouterLink to="/estoque">Abrir estoque e lotes</RouterLink>
    </div>
    <section class="phase-section" aria-labelledby="phase-heading">
      <h2 id="phase-heading">Nesta fase</h2>
      <dl class="phase-list">
        <div>
          <dt>Acesso seguro</dt>
          <dd>Sessão protegida e permissões por empresa</dd>
        </div>
        <div>
          <dt>Contexto da empresa</dt>
          <dd>Consulte os vínculos do seu usuário</dd>
        </div>
        <div>
          <dt>Catálogo disponível</dt>
          <dd>Produtos, variantes, SKUs e códigos internos</dd>
        </div>
        <div>
          <dt>Compras e estoque</dt>
          <dd>Recebimentos, lotes, movimentos e pesagens</dd>
        </div>
      </dl>
    </section>
  </section>
</template>
