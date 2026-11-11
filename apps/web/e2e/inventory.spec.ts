import { expect, test, type Page } from "@playwright/test";
import { join } from "node:path";
import { mkdirSync } from "node:fs";

async function post(page: Page, path: string, payload: unknown) {
  const cookie = (await page.context().cookies()).find(
    (c) => c.name === "XSRF-TOKEN",
  )!;
  const response = await page.request.post(`/api/v1${path}`, {
    headers: {
      "X-XSRF-TOKEN": decodeURIComponent(cookie.value),
      Accept: "application/json",
    },
    data: payload,
  });
  expect(response.status(), await response.text()).toBe(201);
  return (await response.json()).data as { public_id: string };
}
async function saved(
  page: Page,
  button = "Registrar operação",
  confirmation = false,
) {
  if (confirmation) page.once("dialog", (dialog) => dialog.accept());
  await page
    .locator(".catalog-form")
    .getByRole("button", { name: button, exact: true })
    .click();
  await expect(
    page.getByText(
      button === "Salvar cadastro"
        ? "Cadastro salvo com sucesso."
        : "Operação registrada com sucesso.",
      { exact: true },
    ),
  ).toBeVisible();
  await expect(page.locator(".catalog-form")).toHaveCount(0);
}
async function chooseCompany(page: Page, name: string) {
  await expect(page.getByLabel("Empresa de trabalho")).toBeEnabled();
  await page.getByLabel("Empresa de trabalho").selectOption({ label: name });
}
async function option(page: Page, label: string, value: string) {
  const select = page
    .locator(".catalog-form")
    .getByLabel(label, { exact: true });
  await expect(select).toBeEnabled();
  await select.selectOption(value);
}

async function switchTab(page: Page, value: string) {
  await page.getByLabel("Seção do módulo", { exact: true }).selectOption(value);
}

test("compras, massa real, FEFO, transferência, contagem, estorno e bloqueio", async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto("/entrar");
  await page
    .getByLabel("E-mail", { exact: true })
    .fill(`inventory-${info.project.name}@example.test`);
  await page
    .getByLabel("Senha", { exact: true })
    .fill(process.env.E2E_PASSWORD!);
  const response = page.waitForResponse(
    (r) =>
      r.url().endsWith("/api/v1/auth/login") && r.request().method() === "POST",
  );
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  expect((await response).status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "Seu espaço de trabalho" }),
  ).toBeVisible();
  const companyName = `Estoque QA ${info.project.name} FICTÍCIO`;
  const company = await post(page, "/companies", {
    trade_name: companyName,
    legal_name: "Empresa de teste FICTÍCIA",
    timezone: "America/Bahia",
  });
  const scope = `/companies/${company.public_id}`;
  const family = await post(page, `${scope}/product-families`, {
    name: "Insumos QA FICTÍCIOS",
  });
  const product = await post(page, `${scope}/products`, {
    name: "Matéria-prima FICTÍCIA",
    type: "ingredient",
    family_id: family.public_id,
  });
  const variant = await post(page, `${scope}/product-variants`, {
    name: "Insumo QA FICTÍCIO",
    product_id: product.public_id,
    nominal_unit_weight_g: "25",
  });
  const factory = await post(page, `${scope}/factories`, {
    name: "Fábrica QA FICTÍCIA",
    timezone: "America/Bahia",
  });
  const warehouse = await post(page, `${scope}/warehouses`, {
    name: "Depósito QA FICTÍCIO",
    factory_id: factory.public_id,
    kind: "general",
  });
  const source = await post(page, `${scope}/inventory/locations`, {
    name: "Origem QA FICTÍCIA",
    code: "SRC",
    warehouse_id: warehouse.public_id,
    reason: "Configuração de teste FICTÍCIA",
  });
  const destination = await post(page, `${scope}/inventory/locations`, {
    name: "Destino QA FICTÍCIO",
    code: "DST",
    warehouse_id: warehouse.public_id,
    reason: "Configuração de teste FICTÍCIA",
  });
  await page
    .getByRole("link", { name: "Estoque e lotes", exact: true })
    .click();
  await chooseCompany(page, companyName);
  await switchTab(page, "policies");
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  const form = page.locator(".catalog-form");
  await option(page, "Variante", variant.public_id);
  await form
    .getByLabel("Grandezas acompanhadas", { exact: true })
    .selectOption("mass");
  await form
    .getByLabel("Motivo do cadastro/alteração", { exact: true })
    .fill("Política de teste FICTÍCIA, não homologação real");
  await saved(page, "Salvar cadastro");
  await page
    .getByRole("link", { name: "Compras e recebimento", exact: true })
    .click();
  await chooseCompany(page, companyName);
  await switchTab(page, "suppliers");
  await page
    .getByRole("button", { name: "Novo cadastro", exact: true })
    .click();
  await form
    .getByLabel("Nome do fornecedor", { exact: true })
    .fill("Fornecedor QA FICTÍCIO");
  await form
    .getByLabel("Motivo do cadastro/alteração", { exact: true })
    .fill("Fornecedor fictício sem documento real");
  await saved(page, "Salvar cadastro");
  await switchTab(page, "orders");
  await page.getByRole("button", { name: "Novo pedido", exact: true }).click();
  const supplier = (
    await (
      await page.request.get(`/api/v1${scope}/purchasing/suppliers`)
    ).json()
  ).data[0];
  await option(page, "Fornecedor do pedido", supplier.public_id);
  await option(page, "Variante do item 1", variant.public_id);
  const kg = (
    await (await page.request.get(`/api/v1${scope}/units?q=KG`)).json()
  ).data[0];
  await option(page, "Unidade do item 1", kg.public_id);
  await form.getByLabel("Quantidade do item 1", { exact: true }).fill("2");
  await form
    .getByLabel("Custo unitário do item 1 (R$)", { exact: true })
    .fill("12,3456");
  await form
    .getByLabel("Motivo / referência da compra", { exact: true })
    .fill("Compra de teste FICTÍCIA");
  await saved(page, "Emitir pedido");
  await switchTab(page, "order-items");
  await expect(page.locator(".catalog-record")).toContainText("R$ 12,3456");
  await page.getByRole("button", { name: "Receber item", exact: true }).click();
  const item = (
    await (
      await page.request.get(`/api/v1${scope}/purchasing/order-items`)
    ).json()
  ).data[0];
  await option(page, "Item a receber", item.public_id);
  await option(page, "Local de recebimento", source.public_id);
  await form
    .getByLabel("Quantidade na unidade do pedido", { exact: true })
    .fill("1,25");
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "America/Bahia",
  }).format(new Date());
  const expiry = new Date(`${today}T12:00:00Z`);
  expiry.setUTCDate(expiry.getUTCDate() + 10);
  await form
    .getByLabel("Data real do recebimento", { exact: true })
    .fill(today);
  await form
    .getByLabel("Validade declarada", { exact: true })
    .fill(expiry.toISOString().slice(0, 10));
  await form
    .getByLabel("Lote do fornecedor", { exact: true })
    .fill("LOTE-TESTE-FICTICIO");
  await form
    .getByLabel("Estado inicial do lote", { exact: true })
    .selectOption("released");
  await form.getByLabel("Peso bruto real (g)", { exact: true }).fill("1260");
  await form.getByLabel("Tara real (g)", { exact: true }).fill("10");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Recebimento e medição de teste FICTÍCIOS");
  await saved(page);
  await page
    .getByRole("link", { name: "Estoque e lotes", exact: true })
    .click();
  await chooseCompany(page, companyName);
  await expect(page.locator(".catalog-record")).toContainText("1,250000000");
  const initialPosition = (
    await (await page.request.get(`/api/v1${scope}/inventory/positions`)).json()
  ).data[0];
  await page.getByRole("button", { name: "Registrar pesagem", exact: true }).click();
  await option(page, "Posição pesada", initialPosition.public_id);
  await expect(form.getByLabel("Quantidade real (UN)", { exact: true })).toHaveCount(0);
  await form.getByLabel("Peso bruto real (g)", { exact: true }).fill("1260");
  await form.getByLabel("Tara real (g)", { exact: true }).fill("10");
  await form.getByLabel("Motivo / referência da operação", { exact: true }).fill("Pesagem isolada FICTÍCIA sem alterar saldo");
  await saved(page);
  const afterWeighing = (
    await (await page.request.get(`/api/v1${scope}/inventory/positions`)).json()
  ).data[0];
  expect(afterWeighing.grams).toBe(initialPosition.grams);
  expect(afterWeighing.units).toBe(initialPosition.units);
  await page
    .getByRole("button", { name: "Reservar por FEFO", exact: true })
    .click();
  await option(page, "Variante", variant.public_id);
  await option(page, "Depósito", warehouse.public_id);
  await form.getByLabel("Reservar massa (g)", { exact: true }).fill("500");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Reserva de teste FICTÍCIA");
  await saved(page);
  await switchTab(page, "reservations");
  const reservation = (
    await (
      await page.request.get(`/api/v1${scope}/inventory/reservations`)
    ).json()
  ).data[0];
  await page
    .getByRole("button", { name: "Consumir reserva", exact: true })
    .click();
  await option(page, "Reserva a consumir", reservation.public_id);
  await form
    .getByLabel("Bruto desta alocação (g)", { exact: true })
    .fill("510");
  await form.getByLabel("Tara desta alocação (g)", { exact: true }).fill("10");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Consumo físico de teste FICTÍCIO");
  await saved(page, "Registrar operação", true);
  await switchTab(page, "movements");
  const consumptionMovement = page.locator(".catalog-record").filter({ hasText: "Consumo de reserva" });
  await expect(consumptionMovement).toContainText("-500,000000");
  if (process.env.QA_SCREENSHOT_DIR) {
    mkdirSync(process.env.QA_SCREENSHOT_DIR, { recursive: true });
    await consumptionMovement.scrollIntoViewIfNeeded();
    await page.screenshot({ path: join(process.env.QA_SCREENSHOT_DIR, `movement-${info.project.name}.png`) });
  }
  await switchTab(page, "positions");
  await expect(page.locator(".catalog-record")).toContainText("0,750000000");
  let positions = (
    await (await page.request.get(`/api/v1${scope}/inventory/positions`)).json()
  ).data;
  const sourceBalance = positions.find(
    (p: { location_id: string }) => p.location_id === source.public_id,
  );
  await page
    .getByRole("button", { name: "Transferir estoque", exact: true })
    .click();
  await option(page, "Posição de origem", sourceBalance.public_id);
  await option(page, "Local de destino", destination.public_id);
  await form.getByLabel("Peso bruto real (g)", { exact: true }).fill("135");
  await form.getByLabel("Tara real (g)", { exact: true }).fill("10");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Transferência FICTÍCIA");
  await saved(page, "Registrar operação", true);
  positions = (
    await (await page.request.get(`/api/v1${scope}/inventory/positions`)).json()
  ).data;
  const destBalance = positions.find(
    (p: { location_id: string }) => p.location_id === destination.public_id,
  );
  await page
    .getByRole("button", { name: "Registrar contagem", exact: true })
    .click();
  await option(page, "Posição contada", destBalance.public_id);
  await form.getByLabel("Peso bruto real (g)", { exact: true }).fill("110");
  await form.getByLabel("Tara real (g)", { exact: true }).fill("10");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Contagem FICTÍCIA");
  await saved(page, "Registrar operação", true);
  const countOp = (
    await (
      await page.request.get(`/api/v1${scope}/inventory/operations`)
    ).json()
  ).data.find((op: { kind: string }) => op.kind === "count");
  await switchTab(page, "operations");
  await page
    .getByRole("button", { name: "Estornar operação", exact: true })
    .click();
  await option(page, "Operação a estornar", countOp.public_id);
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Estorno da contagem FICTÍCIA");
  await saved(page, "Registrar operação", true);
  await switchTab(page, "positions");
  await expect(page.locator(".catalog-record")).toHaveCount(2);
  positions = (
    await (await page.request.get(`/api/v1${scope}/inventory/positions`)).json()
  ).data;
  expect(positions.map((p: { grams: string }) => p.grams).sort()).toEqual([
    "125.000000",
    "625.000000",
  ]);
  const lot = (
    await (await page.request.get(`/api/v1${scope}/inventory/lots`)).json()
  ).data[0];
  await switchTab(page, "lots");
  await page
    .getByRole("button", { name: "Alterar estado do lote", exact: true })
    .click();
  await option(page, "Lote", lot.public_id);
  await form
    .getByLabel("Novo estado do lote", { exact: true })
    .selectOption("blocked");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Bloqueio FICTÍCIO de teste");
  await saved(page, "Registrar operação", true);
  await switchTab(page, "positions");
  await page
    .getByRole("button", { name: "Reservar por FEFO", exact: true })
    .click();
  await option(page, "Variante", variant.public_id);
  await option(page, "Depósito", warehouse.public_id);
  await form.getByLabel("Reservar massa (g)", { exact: true }).fill("1");
  await form
    .getByLabel("Motivo / referência da operação", { exact: true })
    .fill("Tentativa bloqueada FICTÍCIA");
  await form
    .getByRole("button", { name: "Registrar operação", exact: true })
    .click();
  await expect(form.getByRole("alert")).toContainText(
    "Saldo liberado disponível insuficiente",
  );
  await form.getByRole("button", { name: "Cancelar", exact: true }).click();
  await expect(page.locator(".catalog-record .state-tag")).toHaveText([
    "Bloqueado",
    "Bloqueado",
  ]);
  expect(await page.title()).toContain("FactoryControl");
  expect(await page.locator("vite-error-overlay").count()).toBe(0);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  expect(errors).toEqual([]);
  if (process.env.QA_SCREENSHOT_DIR) {
    mkdirSync(process.env.QA_SCREENSHOT_DIR, { recursive: true });
    await page.screenshot({
      path: join(
        process.env.QA_SCREENSHOT_DIR,
        `inventory-${info.project.name}.png`,
      ),
    });
    await page.locator(".catalog-record").first().scrollIntoViewIfNeeded();
    await page.screenshot({path:join(process.env.QA_SCREENSHOT_DIR,`inventory-values-${info.project.name}.png`)});
  }
});
