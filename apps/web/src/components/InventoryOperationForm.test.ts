import { mount, flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import InventoryOperationForm from "./InventoryOperationForm.vue";
import LookupSelect from "./LookupSelect.vue";
import { api, csrf } from "../lib/api";
vi.mock("../lib/api", () => ({
  api: { post: vi.fn(), get: vi.fn() },
  csrf: vi.fn(),
  errorMessage: () => "Falha de conexão.",
}));
describe("Comandos de estoque", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, "confirm").mockReturnValue(true);
  });
  it("repete a mesma chave após falha de conexão e cria outra ao mudar o pedido", async () => {
    vi.mocked(api.post)
      .mockRejectedValueOnce(new Error("timeout"))
      .mockResolvedValue({});
    const wrapper = mount(InventoryOperationForm, {
      global: { stubs: { LookupSelect: true } },
      props: { scope: "/companies/A", kind: "quality", canRelease: true },
    });
    wrapper.findComponent(LookupSelect).vm.$emit("update:modelValue", "lot");
    await wrapper.get("select").setValue("released");
    await wrapper.get("textarea").setValue("Conferência FICTÍCIA");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain("Falha");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    const first = vi.mocked(api.post).mock.calls[0]![1] as {
      idempotency_key: string;
    };
    expect(vi.mocked(api.post).mock.calls[1]![1]).toEqual(
      vi.mocked(api.post).mock.calls[0]![1],
    );
    await wrapper.get("select").setValue("blocked");
    await wrapper.get("form").trigger("submit");
    await flushPromises();
    expect(
      (vi.mocked(api.post).mock.calls[2]![1] as { idempotency_key: string })
        .idempotency_key,
    ).not.toBe(first.idempotency_key);
  });
  it("trocar de empresa durante CSRF não envia operação antiga", async () => {
    let finish!: () => void;
    vi.mocked(csrf).mockReturnValueOnce(
      new Promise((resolve) => {
        finish = resolve;
      }),
    );
    const wrapper = mount(InventoryOperationForm, {
      global: { stubs: { LookupSelect: true } },
      props: { scope: "/companies/A", kind: "quality", canRelease: true },
    });
    await wrapper.get("form").trigger("submit");
    wrapper.unmount();
    finish();
    await flushPromises();
    expect(api.post).not.toHaveBeenCalled();
  });
});
