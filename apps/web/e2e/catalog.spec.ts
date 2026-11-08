import { expect, test, type Page } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { join } from "node:path";

async function enter(page: Page, project: string) {
  await page.goto("/entrar");
  await page
    .getByLabel("E-mail", { exact: true })
    .fill(`catalog-${project}@example.test`);
  await page
    .getByLabel("Senha", { exact: true })
    .fill(process.env.E2E_PASSWORD!);
  const loginResponse = page.waitForResponse(
    (response) =>
      response.url().endsWith("/api/v1/auth/login") &&
      response.request().method() === "POST",
  );
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  expect((await loginResponse).status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "Seu espaço de trabalho" }),
  ).toBeVisible();
  expect(
    (await page.context().cookies()).some(
      (cookie) => cookie.httpOnly && cookie.name.includes("session"),
    ),
  ).toBe(true);
}
async function saved(page: Page) {
  await page
    .locator(".catalog-form")
    .getByRole("button", { name: "Salvar cadastro", exact: true })
    .click();
  await expect(
    page.getByText("Cadastro salvo com sucesso.", { exact: true }),
  ).toBeVisible();
  await expect(page.locator(".catalog-form")).toHaveCount(0);
}
async function selectCompany(page: Page, label: string) {
  await expect(page.getByLabel("Empresa de trabalho")).toBeEnabled();
  await page.getByLabel("Empresa de trabalho").selectOption({ label });
}

test("SKU, preço, histórico, Code128 e reserva após desativação", async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await enter(page, info.project.name);
  await page
    .getByRole("link", { name: "Catálogo e identificadores", exact: true })
    .click();
  await selectCompany(page, "Fábrica Demonstração — FICTÍCIA");
  await expect(
    page.getByRole("heading", { name: "Catálogo e identificadores" }),
  ).toBeVisible();
  const name = `Apresentação QA ${info.project.name} FICTÍCIA`;
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  const form = page.locator(".catalog-form");
  await form.getByLabel("Nome", { exact: true }).fill(name);
  await expect(form.getByLabel("Variante", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Variante", { exact: true })
    .selectOption({ label: "Coxinha Frango 25g" });
  await expect(
    form.getByLabel("Unidade de venda", { exact: true }),
  ).toBeEnabled();
  await form
    .getByLabel("Unidade de venda", { exact: true })
    .selectOption({ label: "Unidade" });
  await form
    .getByLabel("Preço desta apresentação (R$)", { exact: true })
    .fill("12,3456");
  await saved(page);
  const row = page
    .locator(".catalog-record")
    .filter({ has: page.getByRole("heading", { name, exact: true }) });
  await expect(row).toBeVisible();
  await expect(row).toContainText("R$ 12,3456");
  const originalSku = (
    await row.locator(".record-main strong").innerText()
  ).trim();
  const pngUrl = await row
    .getByRole("link", { name: "PNG de teste", exact: true })
    .getAttribute("href");
  const png = await page.request.get(pngUrl!);
  expect(png.status()).toBe(200);
  expect(
    (await png.body())
      .subarray(0, 8)
      .equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])),
  ).toBe(true);
  await row.getByRole("button", { name: "Editar", exact: true }).click();
  await expect(form.getByLabel("SKU", { exact: true })).toBeDisabled();
  await form
    .getByLabel("Preço desta apresentação (R$)", { exact: true })
    .fill("13,0001");
  await saved(page);
  await expect(row).toContainText("R$ 13,0001");
  await row.getByRole("button", { name: "Histórico", exact: true }).click();
  await expect(
    page.getByRole("region", { name: "Histórico do cadastro" }),
  ).toContainText("R$ 12,3456 → R$ 13,0001");
  await expect(
    page.getByRole("region", { name: "Histórico do cadastro" }),
  ).toContainText(`Usuário catálogo ${info.project.name} FICTÍCIO`);
  await page.getByRole("button", { name: "Fechar histórico" }).click();
  if (process.env.QA_SCREENSHOT_DIR) {
    mkdirSync(process.env.QA_SCREENSHOT_DIR, { recursive: true });
    await page
      .getByRole("heading", { name: "Catálogo e identificadores" })
      .scrollIntoViewIfNeeded();
    await page.screenshot({
      path: join(
        process.env.QA_SCREENSHOT_DIR,
        `catalog-${info.project.name}.png`,
      ),
    });
    await row.scrollIntoViewIfNeeded();
    await page.screenshot({
      path: join(process.env.QA_SCREENSHOT_DIR, `sku-${info.project.name}.png`),
    });
  }
  page.once("dialog", (dialog) => dialog.accept());
  await row.getByRole("button", { name: "Desativar", exact: true }).click();
  await expect(row.locator(".state-tag")).toHaveText("Inativo");
  await expect(row.locator(".record-main strong")).toHaveText(originalSku);
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("Duplicação FICTÍCIA");
  await form.getByLabel("SKU", { exact: true }).fill(originalSku);
  await expect(form.getByLabel("Variante", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Variante", { exact: true })
    .selectOption({ label: "Coxinha Frango 25g" });
  await expect(
    form.getByLabel("Unidade de venda", { exact: true }),
  ).toBeEnabled();
  await form
    .getByLabel("Unidade de venda", { exact: true })
    .selectOption({ label: "Unidade" });
  await form
    .getByRole("button", { name: "Salvar cadastro", exact: true })
    .click();
  await expect(form.getByRole("alert")).toContainText("já está reservado");
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await form.getByRole("button", { name: "Cancelar", exact: true }).click();
  await page
    .locator("details")
    .getByText("Filtros avançados", { exact: true })
    .click();
  await expect(
    page.getByLabel("Filtrar por família", { exact: true }),
  ).toBeEnabled();
  await page
    .getByLabel("Filtrar por família", { exact: true })
    .selectOption({ label: "Pizza" });
  await expect(page.locator(".catalog-record")).toHaveCount(2);
  await expect(
    page.getByRole("heading", { name: "Pizza Frango 400g UN", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByLabel("Filtrar por variante", { exact: true }),
  ).toBeEnabled();
  await page
    .getByLabel("Filtrar por variante", { exact: true })
    .selectOption({ label: "Coxinha Frango 25g" });
  await expect(
    page.getByText("Nenhum cadastro encontrado.", { exact: false }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test("empresa, fábrica, depósito, família, produto e variante ficam no contexto escolhido", async ({
  page,
}, info) => {
  await enter(page, info.project.name);
  await page.goto("/empresas");
  await expect(
    page.getByRole("heading", { name: "Empresas e unidades", exact: true }),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  const company = `Empresa QA ${info.project.name} FICTÍCIA`;
  const form = page.locator(".catalog-form");
  await form.getByLabel("Nome fantasia").fill(company);
  await form.getByLabel("Razão social").fill("Empresa de teste FICTÍCIA");
  await saved(page);
  await page
    .getByRole("link", { name: "Gerenciar fábricas e depósitos" })
    .click();
  await selectCompany(page, company);
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("Fábrica QA FICTÍCIA");
  await saved(page);
  await page.getByRole("button", { name: "Depósitos", exact: true }).click();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("Depósito QA FICTÍCIO");
  await expect(form.getByLabel("Fábrica", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Fábrica", { exact: true })
    .selectOption({ label: "Fábrica QA FICTÍCIA" });
  await saved(page);
  await page.goto("/catalogo");
  await selectCompany(page, company);
  await page.getByRole("button", { name: "Famílias", exact: true }).click();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("Família QA FICTÍCIA");
  await saved(page);
  await page.getByRole("button", { name: "Produtos", exact: true }).click();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("Produto QA FICTÍCIO");
  await expect(form.getByLabel("Família", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Família", { exact: true })
    .selectOption({ label: "Família QA FICTÍCIA" });
  await saved(page);
  await page.getByRole("button", { name: "Variantes", exact: true }).click();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form
    .getByLabel("Nome", { exact: true })
    .fill("Variante QA 30g FICTÍCIA");
  await expect(form.getByLabel("Produto", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Produto", { exact: true })
    .selectOption({ label: "Produto QA FICTÍCIO" });
  await form
    .getByLabel("Peso nominal da unidade (g)", { exact: true })
    .fill("30");
  await form.getByRole("button", { name: "Adicionar característica" }).click();
  await form.getByLabel("Característica 1", { exact: true }).fill("Formato");
  await form.getByLabel("Valor 1", { exact: true }).fill("Fictício");
  await saved(page);
  await expect(
    page.getByRole("heading", {
      name: "Variante QA 30g FICTÍCIA",
      exact: true,
    }),
  ).toBeVisible();
  const variantRow = page
    .locator(".catalog-record")
    .filter({
      has: page.getByRole("heading", {
        name: "Variante QA 30g FICTÍCIA",
        exact: true,
      }),
    });
  await variantRow.getByRole("button", { name: "Editar", exact: true }).click();
  await expect(form.getByLabel("Sabor", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Sabor", { exact: true })
    .fill("Sabor corrigido FICTÍCIO");
  await form
    .getByLabel("Peso nominal da unidade (g)", { exact: true })
    .fill("35");
  await form.getByLabel("Valor 1", { exact: true }).fill("Corrigido FICTÍCIO");
  await saved(page);
  await page.getByRole("button", { name: "SKUs", exact: true }).click();
  await expect(
    page.getByText("Nenhum cadastro encontrado.", { exact: false }),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form.getByLabel("Nome", { exact: true }).fill("SKU regressão FICTÍCIO");
  await expect(form.getByLabel("Variante", { exact: true })).toBeEnabled();
  await form
    .getByLabel("Variante", { exact: true })
    .selectOption({ label: "Variante QA 30g FICTÍCIA" });
  await expect(
    form.getByLabel("Unidade de venda", { exact: true }),
  ).toBeEnabled();
  await form
    .getByLabel("Unidade de venda", { exact: true })
    .selectOption({ label: "Unidade" });
  await saved(page);
  await page.getByRole("button", { name: "Variantes", exact: true }).click();
  await variantRow.getByRole("button", { name: "Editar", exact: true }).click();
  await expect(form.getByLabel("Sabor", { exact: true })).toBeDisabled();
  await expect(form.getByLabel("Sabor", { exact: true })).toHaveValue(
    "Sabor corrigido FICTÍCIO",
  );
  await expect(
    form.getByLabel("Peso nominal da unidade (g)", { exact: true }),
  ).toHaveValue("35.000000");
  await expect(form.getByLabel("Valor 1", { exact: true })).toHaveValue(
    "Corrigido FICTÍCIO",
  );
  await form
    .getByLabel("Nome", { exact: true })
    .fill("Variante revisada FICTÍCIA");
  await saved(page);
  await page.getByRole("button", { name: "Produtos", exact: true }).click();
  page.once("dialog", (dialog) => dialog.accept());
  await page
    .locator(".catalog-record")
    .filter({
      has: page.getByRole("heading", {
        name: "Produto QA FICTÍCIO",
        exact: true,
      }),
    })
    .getByRole("button", { name: "Desativar", exact: true })
    .click();
  await expect(page.locator(".catalog-record .state-tag")).toHaveText(
    "Inativo",
  );
  await page.getByRole("button", { name: "SKUs", exact: true }).click();
  await page
    .getByRole("combobox", { name: "Estado do cadastro", exact: true })
    .selectOption("1");
  await page.getByRole("button", { name: "Buscar", exact: true }).click();
  const dependent = page
    .locator(".catalog-record")
    .filter({
      has: page.getByRole("heading", {
        name: "SKU regressão FICTÍCIO",
        exact: true,
      }),
    });
  await expect(dependent.locator(".state-tag")).toHaveText([
    "Ativo",
    "Indisponível por dependência",
  ]);
  if (process.env.QA_SCREENSHOT_DIR) {
    await dependent.scrollIntoViewIfNeeded();
    await page.screenshot({
      path: join(
        process.env.QA_SCREENSHOT_DIR,
        `dependency-${info.project.name}.png`,
      ),
    });
  }
  await page
    .getByRole("combobox", { name: "Estado do cadastro", exact: true })
    .selectOption("0");
  await page.getByRole("button", { name: "Buscar", exact: true }).click();
  await expect(
    page.getByText("Nenhum cadastro encontrado.", { exact: false }),
  ).toBeVisible();
  await selectCompany(page, "Fábrica Demonstração — FICTÍCIA");
  await expect(
    page.getByRole("heading", {
      name: "Coxinha Frango 25g PCT20",
      exact: true,
    }),
  ).toBeVisible();
  await expect(
    page.getByText("Variante QA 30g FICTÍCIA", { exact: true }),
  ).toHaveCount(0);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
});
