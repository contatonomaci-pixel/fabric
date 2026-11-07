import axios from "axios";
export const api = axios.create({
  baseURL: "/api/v1",
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
});
export async function csrf() {
  await axios.get("/sanctum/csrf-cookie", {
    withCredentials: true,
    headers: { Accept: "application/json" },
  });
}
export function errorMessage(error: unknown): string {
  if (!axios.isAxiosError(error))
    return "Não foi possível concluir a operação.";
  if (!error.response)
    return "Não foi possível conectar. Verifique sua conexão e tente novamente.";
  if (error.response.status === 419)
    return "Sua sessão expirou. Atualize a página e tente novamente.";
  const errors = error.response.data?.errors as
    Record<string, string[]> | undefined;
  return (
    (errors && Object.values(errors).flat()[0]) ||
    error.response.data?.message ||
    "Não foi possível concluir a operação."
  );
}
