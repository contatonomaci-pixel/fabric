import { describe, expect, it } from "vitest";
import { AxiosError, AxiosHeaders } from "axios";
import { errorMessage } from "./api";
describe("Mensagens da API", () => {
  it("explica falha de conexão sem revelar detalhes técnicos", () => {
    expect(errorMessage(new AxiosError("internal hostname"))).toContain(
      "Não foi possível conectar",
    );
  });
  it("prioriza validação retornada pelo backend", () => {
    const error = new AxiosError("validation", "422", undefined, undefined, {
      status: 422,
      statusText: "",
      headers: {},
      config: { headers: new AxiosHeaders() },
      data: { errors: { email: ["E-mail inválido."] } },
    });
    expect(errorMessage(error)).toBe("E-mail inválido.");
  });
  it("explica expiração de CSRF", () => {
    const error = new AxiosError("expired", "419", undefined, undefined, {
      status: 419,
      statusText: "",
      headers: {},
      config: { headers: new AxiosHeaders() },
      data: {},
    });
    expect(errorMessage(error)).toContain("Sua sessão expirou");
  });
});
