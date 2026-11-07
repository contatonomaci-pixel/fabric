import { reactive } from "vue";
import { api, csrf } from "./api";
export interface User {
  public_id: string;
  name: string;
  email: string;
  is_demo: boolean;
}
export const auth = reactive<{
  user: User | null;
  environment: string;
  loaded: boolean;
}>({ user: null, environment: "", loaded: false });
export function clearSession() {
  auth.user = null;
  auth.environment = "";
  auth.loaded = true;
}
export async function loadSession() {
  try {
    const { data } = await api.get("/me");
    auth.user = data.data.user;
    auth.environment = data.data.environment;
  } catch (error) {
    clearSession();
    throw error;
  } finally {
    auth.loaded = true;
  }
}
export async function login(email: string, password: string) {
  await csrf();
  await api.post("/auth/login", { email, password });
  await loadSession();
}
export async function logout() {
  await api.post("/auth/logout");
  clearSession();
}
