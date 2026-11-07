# Arquivos da Fase 0

Sem `.git` na pasta recebida, esta relação descreve os arquivos criados/adaptados nesta sessão; não é um `git diff`. `.env`, dependências e artefatos locais são ignorados. `infra/compose.yaml` preservado.

## Aplicação e CI

- `.github/workflows/quality.yml`
- `apps/api/.editorconfig`
- `apps/api/.env.example`
- `apps/api/.gitattributes`
- `apps/api/.gitignore`
- `apps/api/README.md`
- `apps/api/app/Domain/Identity/AuthenticateUser.php`
- `apps/api/app/Http/Controllers/Api/V1/AuthController.php`
- `apps/api/app/Http/Controllers/Api/V1/CompanyController.php`
- `apps/api/app/Http/Controllers/Api/V1/HealthController.php`
- `apps/api/app/Http/Controllers/Api/V1/PasswordController.php`
- `apps/api/app/Http/Controllers/Controller.php`
- `apps/api/app/Http/Middleware/EnsureActiveUser.php`
- `apps/api/app/Http/Middleware/RequestContext.php`
- `apps/api/app/Http/Requests/CompanyListRequest.php`
- `apps/api/app/Http/Requests/LoginRequest.php`
- `apps/api/app/Http/Requests/PasswordRequest.php`
- `apps/api/app/Models/Company.php`
- `apps/api/app/Models/User.php`
- `apps/api/app/Policies/CompanyPolicy.php`
- `apps/api/app/Providers/AppServiceProvider.php`
- `apps/api/app/Support/ApiResponse.php`
- `apps/api/artisan`
- `apps/api/bootstrap/app.php`
- `apps/api/bootstrap/cache/.gitignore`
- `apps/api/bootstrap/providers.php`
- `apps/api/composer.json`
- `apps/api/composer.lock`
- `apps/api/config/app.php`
- `apps/api/config/auth.php`
- `apps/api/config/cache.php`
- `apps/api/config/cors.php`
- `apps/api/config/database.php`
- `apps/api/config/demo.php`
- `apps/api/config/filesystems.php`
- `apps/api/config/logging.php`
- `apps/api/config/mail.php`
- `apps/api/config/queue.php`
- `apps/api/config/services.php`
- `apps/api/config/session.php`
- `apps/api/database/.gitignore`
- `apps/api/database/factories/UserFactory.php`
- `apps/api/database/migrations/0001_01_01_000000_create_users_table.php`
- `apps/api/database/migrations/0001_01_01_000001_create_cache_table.php`
- `apps/api/database/migrations/0001_01_01_000002_create_jobs_table.php`
- `apps/api/database/migrations/2026_11_07_000001_create_identity_foundation.php`
- `apps/api/database/seeders/DatabaseSeeder.php`
- `apps/api/database/seeders/DemoSeeder.php`
- `apps/api/database/seeders/RoleSeeder.php`
- `apps/api/lang/pt_BR.json`
- `apps/api/lang/pt_BR/passwords.php`
- `apps/api/lang/pt_BR/validation.php`
- `apps/api/phpunit.xml`
- `apps/api/public/.htaccess`
- `apps/api/public/favicon.ico`
- `apps/api/public/index.php`
- `apps/api/public/robots.txt`
- `apps/api/routes/api.php`
- `apps/api/routes/auth.php`
- `apps/api/routes/console.php`
- `apps/api/routes/web.php`
- `apps/api/storage/app/.gitignore`
- `apps/api/storage/app/private/.gitignore`
- `apps/api/storage/app/public/.gitignore`
- `apps/api/storage/framework/.gitignore`
- `apps/api/storage/framework/cache/.gitignore`
- `apps/api/storage/framework/cache/data/.gitignore`
- `apps/api/storage/framework/cache/locks/.gitignore`
- `apps/api/storage/framework/sessions/.gitignore`
- `apps/api/storage/framework/testing/.gitignore`
- `apps/api/storage/framework/views/.gitignore`
- `apps/api/storage/logs/.gitignore`
- `apps/api/tests/Feature/FoundationTest.php`
- `apps/api/tests/Feature/InfrastructureTest.php`
- `apps/api/tests/TestCase.php`
- `apps/web/.env.example`
- `apps/web/.gitignore`
- `apps/web/.vscode/extensions.json`
- `apps/web/README.md`
- `apps/web/e2e/foundation.spec.ts`
- `apps/web/eslint.config.js`
- `apps/web/index.html`
- `apps/web/package-lock.json`
- `apps/web/package.json`
- `apps/web/playwright.config.ts`
- `apps/web/public/favicon.svg`
- `apps/web/scripts/e2e-api.mjs`
- `apps/web/src/App.vue`
- `apps/web/src/components/AppShell.vue`
- `apps/web/src/components/FactoryIcon.vue`
- `apps/web/src/lib/api.test.ts`
- `apps/web/src/lib/api.ts`
- `apps/web/src/lib/auth.ts`
- `apps/web/src/main.ts`
- `apps/web/src/router.ts`
- `apps/web/src/style.css`
- `apps/web/src/views/AccessView.test.ts`
- `apps/web/src/views/AccessView.vue`
- `apps/web/src/views/CompaniesView.test.ts`
- `apps/web/src/views/CompaniesView.vue`
- `apps/web/src/views/DashboardView.vue`
- `apps/web/src/views/ErrorView.vue`
- `apps/web/tsconfig.app.json`
- `apps/web/tsconfig.json`
- `apps/web/tsconfig.node.json`
- `apps/web/vite.config.ts`

## Documentação e configuração raiz

- `.gitignore`
- `README.md`
- `infra/README.md`
- `docs/STATUS.md`
- `docs/16-fundacao-decisoes.md`
- `docs/17-executar-localmente.md`
- `docs/18-arquivos-fase-0.md`
- `docs/api/openapi.json`

`AGENTS.md`, documentos de domínio, tarefas e Compose não foram alterados.

Arquivos `.gitkeep` preexistentes foram preservados. Exemplos gerados nesta sessão sem uso (assets Vite, boilerplate Blade/API frontend e orientações genéricas de agentes do scaffold) foram removidos antes da entrega.

Revisão corretiva de 2026-11-07: lista dos arquivos efetivamente alterados e resultados novos registrados na seção de revisão em `docs/STATUS.md`.

Homologação de serviços reais em 2026-11-07: criado `scripts/homologar-fundacao.py`; adaptados `apps/api/config/mail.php`, `apps/api/tests/Feature/InfrastructureTest.php`, `apps/web/scripts/e2e-api.mjs` e o OpenAPI, além da documentação indicada no STATUS. Sem alteração de migrations/locks/Compose.

Conclusão das pendências locais da Fase 0 em 2026-11-07: `scripts/homologar-fundacao.py` ampliado com modo Compose descartável; atualizados `README.md`, `docs/STATUS.md` e os documentos 16/17/18. O Compose original e a aplicação permaneceram intactos nesta execução; matriz real MySQL 8.4/Redis 7/Mailpit passou.

Retomada de 2026-11-07: repetida a matriz após o ajuste de isolamento do Docker, com 35 verificações aprovadas. Alterados somente `README.md`, `docs/STATUS.md` e os documentos 16/17/18 para registrar o repositório agora público/vazio e a publicação pendente de autorização. Pacote local de 154 arquivos e manifesto preparados fora do projeto em `/private/tmp/factorycontrol-phase0-review.zip` e `/private/tmp/factorycontrol-phase0-review-manifest.json`; sem push ou mudança remota.
