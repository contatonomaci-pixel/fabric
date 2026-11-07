import axios from "axios";
import { createRouter, createWebHistory } from "vue-router";
import { auth, loadSession } from "./lib/auth";
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/entrar", component: () => import("./views/AccessView.vue") },
    {
      path: "/recuperar-senha",
      component: () => import("./views/AccessView.vue"),
    },
    {
      path: "/redefinir-senha",
      component: () => import("./views/AccessView.vue"),
    },
    {
      path: "/",
      component: () => import("./components/AppShell.vue"),
      meta: { protected: true },
      children: [
        {
          path: "",
          component: () => import("./views/DashboardView.vue"),
          meta: { title: "Visão geral" },
        },
        {
          path: "empresas",
          component: () => import("./views/CompaniesView.vue"),
          meta: { title: "Empresas e unidades" },
        },
      ],
    },
    {
      path: "/acesso-negado",
      component: () => import("./views/ErrorView.vue"),
      props: { status: "403" },
    },
    {
      path: "/indisponivel",
      component: () => import("./views/ErrorView.vue"),
      props: { status: "503" },
    },
    {
      path: "/:pathMatch(.*)*",
      component: () => import("./views/ErrorView.vue"),
      props: { status: "404" },
    },
  ],
});
router.beforeEach(async (to) => {
  if (!to.meta.protected) return true;
  if (!auth.loaded || !auth.user) {
    try {
      await loadSession();
    } catch (error) {
      if (axios.isAxiosError(error)) {
        if (error.response?.status === 401) return "/entrar";
        if (error.response?.status === 403) return "/acesso-negado";
      }
      return "/indisponivel";
    }
  }
  return auth.user ? true : "/entrar";
});
