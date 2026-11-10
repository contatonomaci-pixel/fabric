import { mount, flushPromises } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import InventoryPanel from "./InventoryPanel.vue";
import { api } from "../lib/api";
import type { Company } from "../lib/catalog";
vi.mock("../lib/api", () => ({
  api: { get: vi.fn() },
  errorMessage: () => "Falha",
}));
describe("Consulta de compras", () => {
  it("não renderiza custos sem concessão específica, mesmo se recebidos", async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: {
        data: [
          {
            public_id: "item",
            name: "Item FICTÍCIO",
            created_at: "2026-11-08T12:00:00Z",
            unit_cost: "99.9999",
            total_cost: "199.9998",
          },
        ],
        meta: { current_page: 1, last_page: 1, total: 1 },
      },
    });
    const company: Company = {
      public_id: "A",
      name: "Teste",
      trade_name: "Teste",
      legal_name: "Teste",
      timezone: "America/Bahia",
      active: true,
      is_demo: true,
      permissions: ["purchasing.view"],
    };
    const wrapper = mount(InventoryPanel, {
      global: { stubs: { LookupSelect: true } },
      props: { company, resource: "order-items" },
    });
    await flushPromises();
    expect(wrapper.text()).not.toContain("R$ 99,9999");
    expect(wrapper.text()).not.toContain("Custo unitário");
    expect(wrapper.text()).not.toContain("Receber item");
    await wrapper.setProps({
      company: {
        ...company,
        permissions: [...company.permissions, "purchasing.costs"],
      },
    });
    await flushPromises();
    expect(wrapper.text()).toContain("R$ 99,9999");
    expect(wrapper.text()).toContain("Custo unitário");
  });
});
