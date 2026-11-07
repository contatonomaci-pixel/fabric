<script setup lang="ts">
import FactoryIcon from "../components/FactoryIcon.vue";

</script>
<template>
  <section>
    <h1>Seu espaço de trabalho</h1>
    <p class="intro">Organize a estrutura e o catálogo da sua empresa.</p>
    <div class="empty-state">
      <FactoryIcon :size="78" aria-hidden="true" />
      <h2>A operação começa por aqui</h2>
      <p>
        Os módulos operacionais serão habilitados nas próximas fases. Ainda não
        existem dados de produção, estoque ou vendas.
      </p>
      <RouterLink to="/empresas">Consultar empresas e unidades</RouterLink>
      <RouterLink to="/catalogo">Abrir catálogo e identificadores</RouterLink>
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
      </dl>
    </section>
  </section>
</template>
