import { mkdirSync } from "node:fs";
import { join } from "node:path";
import { expect, test } from "@playwright/test";
test("login por cookie, consulta de vínculos, navegação e logout", async ({
  page,
  context,
}, testInfo) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("console", (message) => {
    if (["error", "warning"].includes(message.type()) && !/status of (401|419)/.test(message.text())) errors.push(message.text());
  });
  await page.goto("/");
  await expect(page).toHaveURL("/entrar");
  await expect(page).toHaveTitle("FactoryControl PRO");
  await expect(page.locator("vite-error-overlay")).toHaveCount(0);
  await page.getByLabel("E-mail", { exact: true }).fill("demo@example.test");
  await page
    .getByLabel("Senha", { exact: true })
    .fill(process.env.E2E_PASSWORD!);
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Seu espaço de trabalho" }),
  ).toBeVisible();
  await expect(page.getByText("Dados de demonstração fictícios")).toBeVisible();
  expect(
    (await context.cookies()).find((c) => c.name.includes("session"))?.httpOnly,
  ).toBe(true);
  expect(
    (await context.cookies()).find((c) => c.name === "XSRF-TOKEN"),
  ).toBeTruthy();
  if (process.env.QA_SCREENSHOT_DIR) {
    mkdirSync(process.env.QA_SCREENSHOT_DIR, { recursive: true });
    await page.screenshot({ path: join(process.env.QA_SCREENSHOT_DIR, `${testInfo.project.name}.png`), fullPage: true });
  }
  await page.reload();
  await expect(
    page.getByRole("heading", { name: "Seu espaço de trabalho" }),
  ).toBeVisible();
  expect(await page.evaluate(() => Object.keys(localStorage))).toEqual([]);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page
    .getByRole("link", { name: "Consultar empresas e unidades" })
    .click();
  await expect(
    page.getByText("Fábrica Demonstração — FICTÍCIA", { exact: true }),
  ).toBeVisible();
  await expect(page.getByText("Unidades na próxima etapa")).toBeVisible();
  await page.getByRole("button", { name: "Sair", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Acesse seu espaço de trabalho" }),
  ).toBeVisible();
  await page.goto("/empresas");
  await expect(page).toHaveURL("/entrar");
  expect(errors).toEqual([]);
});
test("CSRF rejeita POST sem token e recuperação não enumera conta", async ({
  page,
}) => {
  const response = await page.request.post("/api/v1/auth/login", {
    headers: { Accept: "application/json" },
    data: { email: "demo@example.test", password: process.env.E2E_PASSWORD },
  });
  expect(response.status()).toBe(419);
  await page.goto("/recuperar-senha");
  await page
    .getByLabel("E-mail", { exact: true })
    .fill("naoexiste@example.test");
  await page.getByRole("button", { name: "Enviar instruções" }).click();
  await expect(page.getByRole("status")).toContainText(
    "Se houver uma conta ativa",
  );
});
test("404 e 403 são legíveis e acessíveis por teclado", async ({ page }) => {
  await page.goto("/pagina-inexistente");
  await expect(
    page.getByRole("heading", { name: "Página não encontrada" }),
  ).toBeVisible();
  await page.keyboard.press("Tab");
  await expect(
    page.getByRole("link", { name: "Voltar ao início" }),
  ).toBeFocused();
  await page.goto("/acesso-negado");
  await expect(
    page.getByRole("heading", { name: "Acesso não permitido" }),
  ).toBeVisible();
});

test("trocar de tela de acesso limpa erro e senha", async ({ page }, testInfo) => {
  await page.goto("/entrar");
  await page.getByLabel("E-mail", { exact: true }).fill("ficticio@example.test");
  await page.getByLabel("Senha", { exact: true }).fill("Senha-InvalidaFicticia123!");
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  await expect(page.getByRole("alert")).toBeVisible();
  await page.getByLabel("Senha", { exact: true }).fill("Outra-Ficticia123!");
  await page.getByRole("link", { name: "Esqueci minha senha" }).click();
  await expect(page.getByRole("heading", { name: "Recupere seu acesso" })).toBeVisible();
  await expect(page.getByRole("alert")).toHaveCount(0);
  if (process.env.QA_SCREENSHOT_DIR) {
    await page.screenshot({ path: join(process.env.QA_SCREENSHOT_DIR, `access-${testInfo.project.name}.png`), fullPage: true });
  }
  await page.getByRole("link", { name: "Voltar para entrar" }).click();
  await expect(page.getByLabel("Senha", { exact: true })).toHaveValue("");
});

test("403 na consulta da sessão mostra acesso negado", async ({ page }) => {
  await page.route("**/api/v1/me", (route) => route.fulfill({
    status: 403,
    contentType: "application/json",
    body: JSON.stringify({ message: "Acesso não permitido.", code: "FORBIDDEN" }),
  }));
  await page.goto("/");
  await expect(page).toHaveURL("/acesso-negado");
  await expect(page.getByRole("heading", { name: "Acesso não permitido" })).toBeVisible();
});
