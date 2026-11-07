import { mount, flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CompaniesView from "./CompaniesView.vue";
import { api } from "../lib/api";
vi.mock("../lib/api", () => ({
  api: { get: vi.fn() },
  errorMessage: () => "Falha de conexão.",
}));
vi.mock("vue-router", () => ({ useRouter: () => ({ replace: vi.fn() }) }));
describe("Empresas autorizadas", () => {
  beforeEach(() => vi.clearAllMocks());
  it("exibe estado vazio sem oferecer cadastro futuro", async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } },
    });
    const wrapper = mount(CompaniesView);
    await flushPromises();
    expect(wrapper.text()).toContain("Nenhuma empresa disponível");
    expect(wrapper.text()).toContain("Fase 1");
    expect(wrapper.findAll("button")).toHaveLength(0);
  });
  it("mostra erro e permite tentar novamente", async () => {
    vi.mocked(api.get)
      .mockRejectedValueOnce(new Error("offline"))
      .mockResolvedValueOnce({
        data: {
          data: [
            {
              public_id: "ficticio",
              trade_name: "Empresa FICTÍCIA",
              legal_name: "Teste",
              timezone: "America/Bahia",
              is_demo: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, total: 1 },
        },
      });
    const wrapper = mount(CompaniesView);
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain("Falha de conexão");
    await wrapper.get("button").trigger("click");
    await flushPromises();
    expect(wrapper.text()).toContain("Empresa FICTÍCIA");
    expect(wrapper.text()).toContain("Demonstração fictícia");
  });
});
