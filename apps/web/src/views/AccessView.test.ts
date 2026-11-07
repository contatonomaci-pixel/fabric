import { mount, flushPromises } from "@vue/test-utils";
import { createMemoryHistory, createRouter } from "vue-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import AccessView from "./AccessView.vue";
import { api, csrf } from "../lib/api";
import { auth } from "../lib/auth";

vi.mock("../lib/api", () => ({
  api: { post: vi.fn() },
  csrf: vi.fn().mockResolvedValue(undefined),
  errorMessage: () => "Falha de conexão.",
}));

afterEach(() => {
  vi.clearAllMocks();
  auth.user = null;
  auth.environment = "";
});

async function setup(path = "/entrar") {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ["/entrar", "/recuperar-senha", "/redefinir-senha"].map((path) => ({
      path,
      component: AccessView,
    })),
  });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(AccessView, { global: { plugins: [router] } });
  return { wrapper, router };
}

describe("Navegação entre telas de acesso", () => {
  it("não envia uma operação de outra tela quando a navegação ocorre durante o CSRF", async () => {
    let release: () => void = () => {};
    vi.mocked(csrf).mockImplementationOnce(() => new Promise<void>((resolve) => { release = resolve; }));
    const { wrapper, router } = await setup("/recuperar-senha");
    await wrapper.get("#email").setValue("ficticio@example.test");
    await wrapper.get("form").trigger("submit");
    await router.push("/entrar");
    release();
    await flushPromises();
    expect(api.post).not.toHaveBeenCalled();
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    wrapper.unmount();
  });

  it("limpa erro e senha ao trocar de tela e envia somente o e-mail na recuperação", async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error("offline"));
    const { wrapper, router } = await setup();
    await wrapper.get("#email").setValue("ficticio@example.test");
    await wrapper.get("#password").setValue("Ficticia-Segura123!");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    await wrapper.get("#password").setValue("Outra-SenhaFicticia123!");
    await router.push("/recuperar-senha");
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    vi.mocked(api.post).mockResolvedValueOnce({ data: { data: { message: "Instruções enviadas." } } });
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(api.post).toHaveBeenLastCalledWith("/auth/forgot-password", { email: "ficticio@example.test" });
    await router.push("/entrar");
    expect((wrapper.get("#password").element as HTMLInputElement).value).toBe("");
    wrapper.unmount();
  });

  it("sincroniza o e-mail do link e retira o token pelo roteador após sucesso", async () => {
    auth.user = { public_id: "fictitious", name: "Demonstração", email: "destino@example.test", is_demo: true };
    auth.environment = "development";
    const { wrapper, router } = await setup("/recuperar-senha");
    await wrapper.get("#email").setValue("anterior@example.test");
    await router.push("/redefinir-senha?email=destino%40example.test&token=fictitious-token");
    expect((wrapper.get("#email").element as HTMLInputElement).value).toBe("destino@example.test");
    await wrapper.get("#password").setValue("Nova-SenhaFicticia123!");
    await wrapper.get("#confirmation").setValue("Nova-SenhaFicticia123!");
    vi.mocked(api.post).mockResolvedValueOnce({ data: { data: { message: "Senha redefinida. Entre novamente." } } });
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(router.currentRoute.value.query).toEqual({});
    expect(wrapper.get('[role="status"]').text()).toContain("Senha redefinida");
    expect(wrapper.find("#password").exists()).toBe(false);
    expect(auth.user).toBeNull();
    expect(auth.environment).toBe("");
    wrapper.unmount();
  });
});
