import { mount, flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CatalogPanel from "./CatalogPanel.vue";
import { api } from "../lib/api";
vi.mock("../lib/api", () => ({
  api: { get: vi.fn(), patch: vi.fn() },
  csrf: vi.fn(),
  errorMessage: () => "Falha de conexão.",
}));
const response = (name: string) => ({
  data: {
    data: [{ public_id: name, name, active: true }],
    meta: { current_page: 1, last_page: 1, total: 1 },
  },
});
describe("Consulta do catálogo por empresa", () => {
  beforeEach(() => vi.clearAllMocks());
  it("ignora resposta atrasada da empresa anterior", async () => {
    let finish!: (value: ReturnType<typeof response>) => void;
    vi.mocked(api.get)
      .mockReturnValueOnce(
        new Promise((resolve) => {
          finish = resolve;
        }),
      )
      .mockResolvedValueOnce(response("Empresa B"));
    const wrapper = mount(CatalogPanel, {
      props: {
        endpoint: "/companies/A/units",
        scope: "/companies/A",
        resource: "units",
        title: "Unidades",
        fields: [],
      },
    });
    await wrapper.setProps({
      endpoint: "/companies/B/units",
      scope: "/companies/B",
    });
    await flushPromises();
    expect(wrapper.text()).toContain("Empresa B");
    finish(response("Empresa A"));
    await flushPromises();
    expect(wrapper.text()).not.toContain("Empresa A");
    expect(wrapper.text()).toContain("Empresa B");
  });
  it("consulta de auditor não apresenta edição ou desativação", async () => {
    vi.mocked(api.get).mockResolvedValue(response("Unidade FICTÍCIA"));
    const wrapper = mount(CatalogPanel, {
      props: {
        endpoint: "/companies/A/units",
        scope: "/companies/A",
        resource: "units",
        title: "Unidades",
        fields: [],
        canManage: false,
      },
    });
    await flushPromises();
    const actions = wrapper.findAll("button").map((button) => button.text());
    expect(actions).not.toContain("Editar");
    expect(actions).not.toContain("Desativar");
    expect(actions).not.toContain("Novo cadastro");
  });
});
