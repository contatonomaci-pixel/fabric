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
  it("distingue cadastro ativo de indisponibilidade por dependência no filtro", async () => {
    const result = response("Dependente FICTÍCIO");
    vi.mocked(api.get).mockResolvedValue({
      ...result,
      data: {
        ...result.data,
        data: [
          {
            public_id: "dependent",
            name: "Dependente FICTÍCIO",
            active: true,
            effective_active: false,
          },
        ],
      },
    });
    const wrapper = mount(CatalogPanel, {
      props: {
        endpoint: "/companies/A/units",
        scope: "/companies/A",
        resource: "units",
        title: "Unidades",
        fields: [],
      },
    });
    await flushPromises();
    await wrapper.get("select").setValue("1");
    await wrapper.get('form[role="search"]').trigger("submit");
    await flushPromises();
    expect(api.get).toHaveBeenLastCalledWith(
      "/companies/A/units",
      expect.objectContaining({
        params: expect.objectContaining({ active: "1" }),
      }),
    );
    expect(wrapper.findAll(".state-tag").map((tag) => tag.text())).toEqual([
      "Ativo",
      "Indisponível por dependência",
    ]);
    expect(wrapper.text()).toContain(
      "O filtro considera o estado próprio do cadastro.",
    );
  });
});
