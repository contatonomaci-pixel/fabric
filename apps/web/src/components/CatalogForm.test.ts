import { mount, flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CatalogForm from "./CatalogForm.vue";
import { api, csrf } from "../lib/api";
import { catalogDefinitions, money } from "../lib/catalog";
vi.mock("../lib/api", () => ({
  api: { post: vi.fn(), patch: vi.fn() },
  csrf: vi.fn(),
  errorMessage: () => "Falha ao salvar.",
}));
describe("Edição comercial", () => {
  beforeEach(() => vi.clearAllMocks());
  it("envia preço exato como texto e preserva identificadores bloqueados", async () => {
    const wrapper = mount(CatalogForm, {
      props: {
        endpoint: "/companies/A/commercial-skus",
        scope: "/companies/A",
        title: "SKUs",
        fields: [
          { key: "sku", label: "SKU", lockedOnEdit: true },
          { key: "price", label: "Preço", type: "decimal" },
        ],
        item: {
          public_id: "sku",
          name: "Teste",
          active: true,
          sku: "FIXO",
          price: "1.0000",
        },
      },
    });
    await wrapper
      .get('input[inputmode="decimal"]')
      .setValue("99999999999999,9999");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(api.patch).toHaveBeenCalledWith("/companies/A/commercial-skus/sku", {
      price: "99999999999999.9999",
    });
    expect(money("99999999999999.9999")).toBe("R$ 99.999.999.999.999,9999");
  });
  it("navegar durante CSRF pendente não grava na empresa anterior", async () => {
    let finish!: () => void;
    vi.mocked(csrf).mockReturnValueOnce(
      new Promise((resolve) => {
        finish = resolve;
      }),
    );
    const wrapper = mount(CatalogForm, {
      props: {
        endpoint: "/companies/A/products",
        scope: "/companies/A",
        title: "Produtos",
        fields: [{ key: "name", label: "Nome" }],
        item: null,
      },
    });
    await wrapper.get("form").trigger("submit");
    wrapper.unmount();
    finish();
    await flushPromises();
    expect(api.post).not.toHaveBeenCalled();
  });
  it("corrige variante sem SKU sem reenviar características nulas inalteradas", async () => {
    const wrapper = mount(CatalogForm, {
      global: { stubs: { LookupSelect: true } },
      props: {
        endpoint: "/companies/A/product-variants",
        scope: "/companies/A",
        title: "Variantes",
        fields: catalogDefinitions.find(
          (d) => d.resource === "product-variants",
        )!.fields,
        item: {
          public_id: "variant",
          name: "Original",
          active: true,
          identity_locked: false,
          flavor: "Frango",
          nominal_unit_weight_g: "30.000000",
          attributes: { Formato: null },
        },
      },
    });
    const flavor = wrapper.get('input[id$="-flavor"]');
    expect(flavor.attributes("disabled")).toBeUndefined();
    await flavor.setValue("Carne");
    await wrapper
      .get('input[id$="-nominal_unit_weight_g"]')
      .setValue("35,125000");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(api.patch).toHaveBeenCalledWith(
      "/companies/A/product-variants/variant",
      { flavor: "Carne", nominal_unit_weight_g: "35.125000" },
    );
  });
  it.each([true, undefined])(
    "preserva características quando bloqueio é %s",
    async (identity_locked) => {
      const wrapper = mount(CatalogForm, {
        global: { stubs: { LookupSelect: true } },
        props: {
          endpoint: "/companies/A/product-variants",
          scope: "/companies/A",
          title: "Variantes",
          fields: catalogDefinitions.find(
            (d) => d.resource === "product-variants",
          )!.fields,
          item: {
            public_id: "variant",
            name: "Original",
            active: true,
            identity_locked,
            flavor: "Frango",
          },
        },
      });
      expect(
        wrapper.get('input[id$="-flavor"]').attributes("disabled"),
      ).toBeDefined();
      await wrapper.get('input[id$="-name"]').setValue("Nome corrigido");
      await wrapper.get("form").trigger("submit");
      await flushPromises();
      expect(api.patch).toHaveBeenCalledWith(
        "/companies/A/product-variants/variant",
        { name: "Nome corrigido" },
      );
    },
  );
});
