# STATUS — controle de progresso do projeto

**Data de criação:** 2026-11-06.
**Situação:** Fase 1 autorizada e implementada em 2026-11-07, homologada localmente e na [CI do PR #1](https://github.com/contatonomaci-pixel/fabric/actions/runs/42387827301): **39 testes API/287 assertions em cada PHP (8.3/8.5), 12 SPA e 14 E2E**. Inclui empresas/fábricas/depósitos, catálogo, unidades, SKUs, preços com histórico e Code128 SVG/PNG/PDF. Entrega na branch `feat/phase-1-catalog`, [PR #1 em rascunho para revisão](https://github.com/contatonomaci-pixel/fabric/pull/1); integração à main pendente. Fases 2–10 não autorizadas. Windows nativo/Safari/iOS real, homologação física e avisos moderados de desenvolvimento permanecem com os limites registrados.

| Fase | Status | Observação |
|---|---|---|
| 0 — Fundação técnica | HOMOLOGADA LOCALMENTE E NA CI | Código publicado; quatro jobs aprovados após corrigir a preservação de tests/Unit em clones |
| 1 — Catálogo/identificadores | HOMOLOGADA LOCALMENTE E NA CI; EM REVISÃO | 39 API por runtime, 12 SPA e 14 E2E; PR #1 em rascunho, sem merge/deploy |
| 2 — Compras/estoque | PENDENTE | |
| 3 — Fabricação/OP | PENDENTE | |
| 4 — Qualidade/rastreabilidade | PENDENTE | |
| 5 — Comercial/expedição | PENDENTE | |
| 6 — Financeiro | PENDENTE | |
| 7 — Varejistas/etiquetas | PENDENTE | |
| 8 — BI/metas | PENDENTE | |
| 9 — RH/manutenção | PENDENTE | |
| 10 — Integrações/hardening | PENDENTE | |

## Pendências que exigem validação humana
- Marca final e identidade visual.
- Equipamentos reais (máquina, acessórios, balança, impressora, leitor).
- Regras do processo térmico, congelamento e validade por produto.
- Dados da empresa para documentação fiscal/sanitária e integração com varejistas.
- Revisão da proposta ADR-007 (BIGINT interno + ULID público) e do modelo de tenant antes de expandir a fundação. Stack de testes já adotada: PHPUnit, Vitest e Playwright.
- Infraestrutura de hospedagem, subdomínios e backups.

## Diário de decisões e testes
_Inserir registros com data, fase, alterações, comandos, resultados e bloqueios. Não apagar histórico._

### 2026-11-07 — início da Fase 0
- Documentos obrigatórios lidos integralmente. PHP 8.5.5, Composer 2.9.7, Node 22.23.0 e npm 10.9.8 disponíveis; Docker ausente.
- `git status --short`: falhou porque a pasta recebida não contém `.git`. Não inicializado repositório nem sobrescrito trabalho prévio.
- Plano: scaffold oficial Laravel/Vite, auth sessão/CSRF, escopo mínimo empresa/papel, layout informativo, testes/CI/documentação Windows e Unix.
- Riscos e decisões propostos: `docs/16-fundacao-decisoes.md`. Contrato de empresas/unidades: apenas leitura de vínculos nesta fase; CRUD na Fase 1.

### 2026-11-07 — entrega da Fase 0

**Implementado**
- Monorepo executável em `apps/api` (Laravel 13/PHP 8.3+) e `apps/web` (Vue 3/TypeScript/Vite/Tailwind), com locks reproduzíveis. Versões e fontes primárias em `docs/16-fundacao-decisoes.md`.
- Sanctum first-party por sessão HttpOnly/CSRF, login com regeneração de sessão, logout, `/api/v1/me`, rate limiting, bloqueio de usuário desativado, recuperação de senha com resposta genérica, token expirável/de uso único e revogação das sessões de banco.
- Migrations reversíveis de usuários/empresa mínima/papéis/permissões/vínculos; PK BIGINT + ULID público, unicidade composta e FKs. Policy e consulta de empresas filtradas pelo usuário e permissão; múltiplos papéis não duplicam resultados.
- Seeder base sem usuário/senha padrão e DemoSeeder fictício, optativo, idempotente, restrito a local/testing e com senha fornecida localmente. Nenhum dado sanitário/fiscal/financeiro inventado.
- API `/api/v1` com validação FormRequest, erros JSON pt-BR, `request_id`, headers, paginação e readiness real de banco/Redis. `/up` é apenas liveness. Sessões no banco, Redis para cache/filas via Predis.
- SPA com login/recuperação/reset, navegação responsiva, painel sem métricas, consulta dos vínculos, estados loading/vazio/erro, 403/404 e navegação por teclado. Empresas/fábricas/depósitos sem CRUD nesta fase.
- CI localmente definido para PHP 8.3/8.5 + MySQL 8.4/Redis (migrate/rollback/migrate e testes), SPA e E2E. O workflow **não foi executado remotamente**.
- Guia Windows/VS Code, PowerShell e Unix em `docs/17-executar-localmente.md`; contrato em `docs/api/openapi.json`; manifesto em `docs/18-arquivos-fase-0.md`.

**Evidências finais executadas nesta máquina**

| Comando/verificação | Resultado |
|---|---|
| `composer test` em apps/api | **19 passaram, 90 assertions; 1 skipped**: integração real MySQL/Redis |
| `composer validate --strict` | Passou |
| `vendor/bin/pint --test` | Passou; formatação aplicada previamente com Pint |
| Migrations `migrate --force` → `migrate:rollback --force` → `migrate --force` | Passaram em SQLite temporário isolado; corrigida remoção do índice antes da coluna no rollback |
| `npm run lint` | Passou sem erros/avisos |
| `npm run typecheck` | Passou; inclui SPA, configuração e testes E2E |
| `npm test` | 2 arquivos, **5 testes passaram** |
| `npm run build` | Passou; gera dist local, sem publicação |
| `npm run test:e2e` | **6 passaram** em Chromium desktop 1487×1058 e emulação mobile 390×844; SQLite e senha/chave efêmeros |
| Fluxo E2E | Redirecionamento 401 → login com CSRF → cookie HttpOnly → painel → reload autenticado → empresas vinculadas → logout → bloqueio de rota sem sessão; localStorage vazio |
| Segurança/erros E2E | POST sem token rejeitado com 419, recuperação sem enumeração, páginas 403/404, teclado, sem overlay Vite/erros JS ou avisos relevantes no console; 401/419 esperados filtrados do console |
| Verificação visual | Referência Image Gen e screenshots finais inspecionados com `view_image`; texto, lateral, hierarquia, cores, ícones e responsividade conferidos; detalhes em docs/16 |
| JSON/OpenAPI | JSON válido; todos os caminhos/métodos documentados conferidos contra `php artisan route:list --json`; não foi executado validador externo completo de OpenAPI |
| `python3 scripts/validar-pacote.py` | Passou: 10 arquivos documentais essenciais presentes |
| Composer ao resolver dependências | Sem advisories conhecidos reportados |
| `npm audit fix` (sem force) | Aplicação de correções compatíveis executada; **15 avisos moderados transitivos de desenvolvimento permanecem** |
| `npm audit --omit=dev --json` | Zero vulnerabilidades reportadas nas dependências do produto |
| `git status --short` no início/fim | Não executável: pasta recebida sem `.git`; sem branch/commit/diff |

**Limitações e pendências**
- Docker não instalado. `docker compose ... up -d`, MySQL **8.4**, Redis real, worker e SMTP/Mailpit não executados nesta sessão. O teste de infraestrutura foi explicitamente skipped. SQLite/E2E não comprovam comportamento MySQL/Redis.
- CI implementado, ainda sem resultado remoto. Runtime PHP 8.3 resolvido no lock e previsto na matriz CI; execução local ocorreu em PHP 8.5.5.
- Windows documentado, não executado neste macOS. Mobile emulado em Chromium, sem comprovação em Safari/iOS real.
- Avisos moderados de desenvolvimento: `glob`/`minimatch` e dependentes (ESLint/typescript-eslint/Test Utils). Reavaliar correções upstream; não forçado downgrade de ferramenta mantida ou override de major incompatível. Detalhes em docs/16.
- Confirmar proposta ADR-007 (BIGINT interno + ULID público) e marca antes de expandir a Fase 1. RBAC é mínimo e só autoriza leitura de vínculos; administração granular futura ainda não existe.
- `infra/compose.yaml` preservado, SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f`. Sem deploy, alterações remotas ou credenciais reais.

**Próximo passo recomendado:** disponibilizar serviços locais, executar a homologação MySQL 8.4/Redis e recuperação por Mailpit conforme o guia, revisar a proposta de IDs e então obter liberação explícita para a Fase 1. A Fase 1 não foi iniciada.

### 2026-11-07 — revisão de possíveis erros da Fase 0

**Escopo e plano:** revisar a fundação existente, reproduzir falhas antes das correções quando possível, ajustar apenas configuração/autenticação/navegação e registrar evidências. Não autorizada nem iniciada a Fase 1. Principais riscos avaliados: compatibilidade PHP mínimo, revogação de sessão, isolamento do banco de testes e reutilização de estado na SPA.

**Problemas encontrados e corrigidos**

| Problema | Reprodução/evidência | Correção |
|---|---|---|
| Configuração do scaffold acessava `Pdo\Mysql::ATTR_SSL_CA` diretamente, classe ausente em PHP 8.3 | Leitura de `config/database.php` e conferência no [manual oficial do PHP 8.4](https://www.php.net/manual/en/migration84.new-features.php). PHP 8.3 não está instalado localmente. | Seleção condicional da constante: classe específica quando disponível e constante PDO compatível no PHP 8.3. Mantido suporte a MySQL e MariaDB da configuração padrão. |
| Reset de senha removia sessões do banco, mas mantinha o usuário autenticado na sessão em memória da própria requisição | Novo teste `test_password_reset_also_ends_the_requesting_authenticated_session` **falhou antes**: usuário continuava autenticado. | Após reset válido, logout do guard web, invalidação da sessão e regeneração de CSRF. O estado de autenticação da SPA também é limpo após sucesso. Reset inválido não encerra a sessão. |
| `DB_URL` herdada podia sobrepor o banco isolado escolhido pelo runner | Configuração de conexão aceita `url`, e o XML não forçava a limpeza de variável externa. | `DB_URL` vazia forçada no PHPUnit; URLs de conexão SQLite/MySQL removidas na configuração do TestCase. Mantida a exigência de banco `_test` para MySQL. |
| Telas de acesso reutilizavam mensagens/senhas/e-mail anterior; reset alterava `history.state` fora do Vue Router | Dois testes de componente **falharam antes**: alerta persistente ao trocar de tela e e-mail do link não sincronizado. | Limpeza de estado ao navegar, sincronização do e-mail do link, payload de recuperação somente com e-mail, remoção do token via `router.replace` e confirmação de reset sem formulário reenviável. Operações pendentes não enviam payload para outra tela nem sobrescrevem mensagens após navegação. |
| Resposta 403 ao carregar a sessão era exibida como indisponibilidade (503) | Inspeção do guard de navegação; novo E2E simula `/me` com 403. | Redirecionamento para a tela de acesso negado; falhas de sessão também limpam o contexto de ambiente anterior. |

**Arquivos alterados nesta revisão**
- API: `apps/api/config/database.php`, `apps/api/app/Http/Controllers/Api/V1/PasswordController.php`, `apps/api/phpunit.xml`, `apps/api/tests/TestCase.php`, `apps/api/tests/Feature/FoundationTest.php`.
- SPA: `apps/web/src/lib/auth.ts`, `apps/web/src/router.ts`, `apps/web/src/views/AccessView.vue`, novo `apps/web/src/views/AccessView.test.ts`, `apps/web/e2e/foundation.spec.ts`.
- Documentação: `docs/STATUS.md`, `docs/16-fundacao-decisoes.md`, `docs/17-executar-localmente.md`, `docs/18-arquivos-fase-0.md`.
- Sem alteração de dependências/lockfiles, migrations, Compose ou módulos de domínio futuros. Hash do Compose reconferido e idêntico ao registrado na entrega.

**Verificações executadas após as correções**

| Verificação | Resultado |
|---|---|
| `composer test` | **20 passaram / 94 assertions; 1 skipped** (MySQL/Redis reais) |
| `vendor/bin/pint --test` e `composer validate --strict` | Passaram |
| `php artisan test --filter=FoundationTest` com `DB_URL` externa fictícia apontando a loopback/porta inválida | **20 passaram / 94 assertions**; URL ignorada e suíte executada no SQLite isolado |
| Migrations up/down/up em SQLite temporário separado | Passaram (3 comandos com exit 0) |
| `npm run lint`, `npm run typecheck`, `npm run build` | Passaram |
| `npm test` | **3 arquivos / 8 testes passaram**; inclui estado das telas, remoção do token e navegação durante CSRF pendente |
| `npm run test:e2e` | **10 passaram** em desktop 1487×1058 e mobile Chromium 390×844 |
| E2E focado `--grep 'trocar de tela'` | 2 passaram; capturas de recuperação desktop/mobile fora do repositório, inspecionadas com `view_image` |
| `python3 scripts/validar-pacote.py` | Passou: 10 arquivos documentais essenciais |
| `git status --short` | Continua indisponível: pasta sem `.git`; não inicializado repositório |

**QA do navegador**

Fluxos exercitados: entrar → painel → empresas → sair; login inválido → recuperação sem erro anterior → voltar à entrada com senha vazia; `/me` 403 → acesso negado. O 403 desse último cenário é simulado no E2E; os controles de autorização reais continuam cobertos pela API. Reset bem-sucedido e remoção do token foram verificados em testes de componente com resposta simulada e, separadamente, pelo teste real da API que encerra a sessão.

| Checagem | Resultado |
|---|---|
| URL/título e conteúdo não vazio | Passou; `http://localhost:5173`, título FactoryControl PRO e headings esperados |
| Overlay do framework | Ausente no fluxo de autenticação |
| Console/erros JS | Nenhum erro relevante no fluxo principal; 401/419 esperados filtrados. 422 no cenário de senha inválida e 403 simulado são respostas intencionais |
| Capturas/legibilidade | Recuperação desktop/mobile inspecionada com `view_image`; sem alerta herdado, conteúdo legível e controles visíveis |
| Interações | Login, reload de sessão, consulta, logout, teclado, troca de telas e redirecionamento 403 passaram |

Browser integrado: ferramenta de controle indisponível após consultar a skill e descobrir ferramentas; utilizado Playwright já instalado. Capturas temporárias: `.../T/factorycontrol-qa/access-desktop.png` e `access-mobile.png`, mesmo diretório completo registrado em docs/16. Relatórios locais do Playwright removidos após a revisão.

**Limites mantidos e próximos passos**
- PHP local **8.5.5**, Node **22.23.0**. O ajuste para PHP 8.3 foi conferido em código e fonte oficial, mas **não executado em runtime 8.3**; validar na matriz CI já fornecida.
- Docker continua ausente. Sem MySQL 8.4/Redis reais, SMTP/Mailpit ou CI remoto nesta revisão. Windows e Safari/iOS real não executados.
- Avisos de dependências registrados na entrega anterior não foram reauditados; nenhuma atualização de pacote foi realizada nesta revisão.
- Nenhuma falha restante nos comandos executados. Isso não substitui a homologação pendente dos serviços.
- Próxima etapa: homologar MySQL 8.4/Redis e recuperação via Mailpit, executar CI PHP 8.3/8.5 e só depois liberar explicitamente a Fase 1.

### 2026-11-07 — execução das pendências com infraestrutura real

**Objetivo:** executar as pendências técnicas da Fase 0 e corrigir problemas revelados pela execução. Nenhum módulo futuro, deploy, alteração de banco existente ou envio de mensagem a destinatário real.

**Ambiente provisionado e isolamento**
- Instalados via Homebrew, com aprovação, PHP **8.3.33**, MySQL **8.4.11**, Redis **8.10.1** e Mailpit **1.31.0**. PHP padrão continua **8.5.5**. Foram usados os binários por caminho explícito, sem habilitar serviços no login.
- Homebrew também atualizou dependências compartilhadas. Isso deixou o executável MySQL 9.6 sem localizar bibliotecas antigas de protobuf/abseil. **Correção concluída:** referências `opt/protobuf` e `opt/abseil` restauradas às versões anteriores; MySQL 8.4 recebeu as bibliotecas novas apenas pelo ambiente de seu processo. `mysqld --version` padrão voltou a carregar **9.6.0**. O processo original permaneceu com PID **950**, ouvindo em **3306**, sem reinício ou acesso ao seu diretório de dados.
- MySQL 8.4 subiu exclusivamente em **127.0.0.1:33307**, com diretório recém-criado em `/private/tmp/fc0-*`, senha root aleatória e bancos `factorycontrol_pro_test` / `factorycontrol_pro_e2e_test`. Credenciais somente em memória/arquivo temporário privado; não registradas no repositório nem nos resultados.
- Redis de teste em **127.0.0.1:26380**; Mailpit HTTP **127.0.0.1:18025** e SMTP **127.0.0.1:11025**. SMTP limitado a destinatários `@example.test`, sem relay/forwarding. API de smoke em **127.0.0.1:18000**; E2E em 8000/5173.
- Todos os processos iniciados pela homologação foram encerrados e os bancos/diretórios temporários removidos. Conferência por `lsof`: só permaneceu o MySQL 9.6 original em 3306.
- Docker continua ausente. A execução foi **nativa**, permitida pelo guia de desenvolvimento, e não comprova a execução do Compose. Redis nativo 8.10.1 difere do Redis 7 definido no Compose/CI; manter validação específica desse ambiente pendente.

**Correções e entregas desta execução**
- Corrigido `apps/api/config/mail.php`: `MAIL_URL` vazia passa a ser `null`. A tentativa SMTP real reproduziu HTTP 500 / `Unsupported mail transport []` antes dessa normalização; envio e reset completos passaram depois.
- Ampliado `InfrastructureTest` para executar sem depender da ordem de outros testes: MySQL 8.4/InnoDB/utf8mb4, worker Redis processando job real e revogação de sessão real em `sessions`. Não foram criados jobs de negócio ou módulos antecipados.
- Criado `scripts/homologar-fundacao.py`, roteiro reproduzível que valida portas/versões, cria serviços descartáveis, executa a matriz e remove os dados ao encerrar. Não instala ferramentas nem toca serviços existentes.
- E2E aceita perfil local opcional `FACTORYCONTROL_E2E_PROFILE`, com nome de banco exclusivo e validação de host/driver; continua usando SQLite por padrão quando o perfil não é fornecido.
- OpenAPI recebeu `operationId` em todas as operações para corrigir avisos do validador.
- Na primeira execução do novo roteiro, o ambiente local herdado ativou CSRF nos testes HTTP sem token. Corrigido **o roteiro de testes**, passando explicitamente `APP_ENV=testing` e os drivers de teste, conforme a CI; a proteção CSRF da aplicação não foi relaxada.

**Evidências executadas**

| Verificação | Resultado real |
|---|---|
| PHP 8.3.33 com SQLite (comando padrão `composer test`) | **20 passaram / 94 assertions; 3 skipped** de infraestrutura real, como esperado; Pint passou em seguida |
| MySQL 8.4 up/down/up em PHP 8.3.33 | Passou |
| API PHP 8.3.33 + MySQL 8.4.11 + Redis real | **23 passaram, 111 assertions, nenhum skipped** |
| MySQL 8.4 up/down/up em PHP 8.5.5 | Passou |
| API PHP 8.5.5 + MySQL 8.4.11 + Redis real | **23 passaram, 111 assertions, nenhum skipped** |
| `composer check-platform-reqs` em PHP 8.3 e 8.5 | Todos os requisitos do runtime/extensões passaram |
| Worker `queue:work redis --once` | Processou job real e gravou resultado no cache Redis; teste passou nos dois runtimes |
| Sessão em banco real | Login, leitura de `/me`, reset e rejeição do cookie antigo passaram nos dois runtimes |
| Mailpit SMTP + HTTP | E-mail fictício recebido; link extraído sem imprimir token; reset passou; sessão anterior retornou 401; token reutilizado retornou 422; senha anterior rejeitada; nova senha aceita; logout passou |
| Readiness com banco/Redis acessíveis | 200 com os dois checks verdadeiros |
| Readiness após desligar Redis real | 503, sem simulação da dependência |
| `npm run lint`, `npm run typecheck`, `npm run build` | Passaram |
| `npm test` | **8 passaram / 3 arquivos** |
| E2E PHP 8.3 + MySQL 8.4 + Redis reais | **10 passaram** em desktop/mobile (9,4 s), incluindo sessão após reload, empresas autorizadas e logout |
| Pint e Composer validate em PHP 8.3 | Passaram |
| Redocly CLI **2.76.0** — OpenAPI `--extends minimal` | Documento válido, **zero erros / 2 avisos** referentes a `localhost`, intencionais para documentação local. Nove avisos de operationId foram corrigidos |
| `npm audit --json` | Reavaliado: **15 moderados**, todos em ferramentas de desenvolvimento; nenhum alto/crítico |
| `npm audit --omit=dev --json` | **Zero avisos** nas dependências do produto |
| `python3 scripts/validar-pacote.py` | Passou; 10 documentos essenciais presentes |
| Compose SHA-256 | Mantido `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f` |

Evidência detalhada e sem senhas: `/private/tmp/factorycontrol-homologation.json`, 27 checks com exit 0 e `success=true`, `temporary_services_stopped=true`. É arquivo local temporário, não fonte permanente de operação. O roteiro e este diário permitem reproduzir os resultados.

**QA da SPA:** utilizada a skill frontend-testing-debugging e Playwright já instalado, pois o controle do navegador integrado segue indisponível. URL `http://localhost:5173`, desktop 1487×1058 e mobile Chromium 390×844. Identidade, conteúdo não vazio, ausência de overlay, console do fluxo principal, interações e capturas conferidos; imagens desktop/recuperação mobile inspecionadas com `view_image`. Nenhuma alteração visual foi necessária.

**Arquivos alterados/criados:** `apps/api/config/mail.php`, `apps/api/tests/Feature/InfrastructureTest.php`, `apps/web/scripts/e2e-api.mjs`, `scripts/homologar-fundacao.py`, `docs/api/openapi.json`, `README.md`, `docs/16-fundacao-decisoes.md`, `docs/17-executar-localmente.md`, `docs/18-arquivos-fase-0.md` e `docs/STATUS.md`. Lockfiles e migrations preservados.

**Pendências restantes e decisão:**
- **CI remota:** não executada. A pasta não possui `.git`, origin ou repositório remoto identificado, e não foi criado/publicado repositório nem enviada alteração. A matriz PHP/MySQL e comandos de qualidade equivalentes foram executados localmente; isso não equivale a um resultado do GitHub Actions.
- **Docker/Compose + Redis 7:** ainda não executados por ausência de Docker; Compose original preservado. O ambiente nativo demonstrou MySQL 8.4, Redis 8.10.1 e Mailpit, com as versões explicitadas.
- Windows nativo e Safari/iOS real não disponíveis neste macOS. Mobile foi emulação Chromium; documentação Windows mantida.
- Avisos moderados transitivos de desenvolvimento continuam conhecidos; não forçados overrides incompatíveis nem downgrades. Sem avisos no runtime do produto.
- Proposta ADR-007 e marca continuam para revisão humana. Fase 1 permanece não iniciada; próxima fase recomendada após liberação explícita e revisão dos limites acima.

### 2026-11-07 — conclusão das pendências locais: Docker/Compose e Redis 7

**Autorização e plano:** usuário confirmou concluir somente as pendências da Fase 0. Objetivo: executar o Compose fornecido, a matriz de qualidade e consultar a CI do repositório informado. Arquivos previstos: roteiro de homologação, README e documentos 16/17/18/STATUS. Riscos controlados: uso de portas/volumes exclusivos, credenciais efêmeras, preservação do MySQL preexistente e limpeza somente dos recursos novos. Sem implementação da Fase 1.

**Executado e alterado**
- Instalados com aprovação Docker CLI **29.7.2**, Compose **5.5.0**, Colima **0.10.3** e Lima **2.2.0**, sem atualização das bibliotecas existentes nesta instalação. Criada VM exclusiva `factorycontrol-phase0`, 2 CPUs/4 GiB/20 GiB, sem montagens de diretórios do host e sem ativar contexto Docker global; configuração própria em `/private/tmp/factorycontrol-docker-config`.
- `scripts/homologar-fundacao.py` ampliado com `--compose`, `--docker`, `--docker-host` e `--docker-config`. Mantido o modo nativo. Exige socket Unix explícito; cria projeto aleatório, `.env` temporário privado, volumes novos e bancos terminados em `_test`. A limpeza com `down --volumes --remove-orphans` é restrita ao projeto recém-criado e seu resultado é registrado.
- Após a matriz, removida `DOCKER_CONTEXT` herdada somente do ambiente dos subprocessos Docker, pois ela pode sobrepor `DOCKER_HOST`. Verificação específica com contexto fictício confirmou a remoção, a preservação do socket explícito e a ausência de alteração no ambiente pai. Não houve nova execução integral da matriz após esse ajuste de isolamento.
- `infra/compose.yaml` executado **sem alteração**. Serviços efetivos: MySQL **8.4.13**, Redis **7.4.12**, Mailpit **1.33.0**, Linux ARM64. As versões diferem das ferramentas nativas anteriores porque as tags do Compose são móveis; ambas as execuções permanecem identificadas no histórico.
- README e guias atualizados; corrigida a contagem documental dos testes skipped no modo SQLite (três). Sem alteração adicional na aplicação, migrations, dependências/locks ou workflow nesta execução.

**Comando de homologação executado, a partir da raiz**

```bash
python3 scripts/homologar-fundacao.py --compose \
  --docker /opt/homebrew/opt/docker/bin/docker \
  --docker-host unix:///Users/mauriciosilva/.colima/factorycontrol-phase0/docker.sock \
  --docker-config /private/tmp/factorycontrol-docker-config \
  --php /opt/homebrew/opt/php@8.3/bin/php \
  --php /opt/homebrew/bin/php \
  --result /private/tmp/factorycontrol-compose-homologation.json
```

| Verificação | Resultado |
|---|---|
| Compose `config --quiet`, `up --detach --wait`, `ps` e versões efetivas | Passaram; MySQL 8.4 e Redis 7 confirmados |
| Migrations up/down/up em MySQL 8.4.13 | Passaram em PHP 8.3.33 e 8.5.5 |
| API PHP 8.3.33 + MySQL/Redis do Compose | **23 passaram / 111 assertions, nenhum skipped** |
| API PHP 8.5.5 + MySQL/Redis do Compose | **23 passaram / 111 assertions, nenhum skipped** |
| `composer check-platform-reqs` nos dois runtimes | Passou |
| Worker Redis 7 e revogação de sessão MySQL | Passaram nos dois runtimes |
| SMTP/Mailpit + reset pelo e-mail fictício | Passou; sessão anterior 401, token reutilizado 422, senha antiga rejeitada, senha nova e logout aceitos |
| Readiness real | 200 com serviços disponíveis; 503 após parar o container Redis; Redis religado antes do E2E |
| `npm run lint`, `npm run typecheck`, `npm run build` | Passaram |
| `npm test` | **8 passaram / 3 arquivos** |
| `npm run test:e2e` com PHP 8.3 + MySQL/Redis reais | **10 passaram** em Chromium desktop/mobile, 10,0 s |
| Pint e `composer validate --strict` com PHP 8.3 | Passaram |
| Remoção dos recursos Compose descartáveis | Exit 0; nenhum container permaneceu na VM |
| Verificações finais do roteiro/documentação | `--help`, parsing AST Python e `python3 scripts/validar-pacote.py` passaram; 10 documentos essenciais presentes |
| `git status --short` | Continua indisponível: diretório sem `.git`; nenhum diff/commit afirmado |

Evidência local detalhada: `/private/tmp/factorycontrol-compose-homologation.json`, **35 verificações com exit 0**, `success=true`, `temporary_services_stopped=true`, `service_mode=compose`. IDs das imagens nesta execução: MySQL `sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902`, Redis `sha256:c5d34a98396a4775e223c74e929162c35c5a2182901ba26b50ade939941ce14b`, Mailpit `sha256:9b3a463be15354c0f0eba91697d80e3e0d8a5623ef0e627cacc836bc17fd5231`. O arquivo é temporário; roteiro e diário permitem reproduzir a homologação.

**Encerramento e limites**
- VM de teste desligada ao concluir, sem iniciar serviço no login. Perfil Colima e cache de imagens mantidos para repetição; containers/volumes de teste removidos. MySQL original **9.6.0/PID 950** permaneceu ativo em **3306**, sem reinício ou acesso ao seu banco.
- Compose preservado: SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f`.
- **CI remota ainda não verificável:** URL fornecida pelo usuário: `https://github.com/contatonomaci-pixel/fabric.git`. `GET /repos/contatonomaci-pixel/fabric` e `GET /repos/contatonomaci-pixel/fabric/actions/runs` retornaram HTTP **404** na API pública. O conector GitHub retornou `Unknown tool`; CLI `gh` indisponível. Não é possível concluir se o repositório é privado ou indisponível. Falta acesso autenticado para consultar a CI e comparar o código remoto com esta pasta. Não houve inicialização de Git, push ou alteração remota.
- Windows nativo e Safari/iOS real continuam sem execução; testes mobile usam Chromium emulado. Permanecem os 15 avisos moderados de ferramentas de desenvolvimento registrados na auditoria anterior; dependências não foram alteradas nem auditadas novamente nesta etapa.
- **Arquivos alterados nesta etapa:** `scripts/homologar-fundacao.py`, `README.md`, `docs/STATUS.md`, `docs/16-fundacao-decisoes.md`, `docs/17-executar-localmente.md`, `docs/18-arquivos-fase-0.md`.
- **Próximo passo:** obter acesso ao repositório informado e comprovar a CI remota. Fase 1 recomendada somente após revisão das pendências e liberação explícita; não iniciada nesta sessão.

### 2026-11-07 — retomada: repositório público vazio e homologação final

**Objetivo e plano:** continuar somente a Fase 0, conferir novamente o repositório, encerrar a validação integral após o ajuste de `DOCKER_CONTEXT` e preparar uma publicação revisável. Arquivos alterados nesta retomada: `README.md`, `docs/STATUS.md`, `docs/16-fundacao-decisoes.md`, `docs/17-executar-localmente.md` e `docs/18-arquivos-fase-0.md`. Sem alterações adicionais na aplicação, roteiro, dependências ou infraestrutura declarada.

**Nova evidência remota**
- A API pública agora retorna HTTP **200** para `contatonomaci-pixel/fabric`: `private=false`, branch padrão `main`, tamanho informado `0`.
- `/contents` retorna HTTP 404 com mensagem explícita **`This repository is empty.`**; `/actions/workflows` retorna HTTP 200 com `total_count=0`; `/actions/runs` retorna HTTP 200 com `total_count=0`.
- O impedimento atual é **ausência do código/workflow no repositório público**. A leitura pública está disponível; a integração GitHub continua retornando `Unknown tool`, sem comprovação de acesso de escrita. Os registros anteriores de 404 permanecem como histórico, não como situação atual.
- Nenhum resultado remoto de CI foi encontrado ou afirmado. Publicar o código e o workflow dispara os eventos `push` da configuração existente; o envio aguarda autorização explícita do usuário porque tornará públicos o código e a documentação.

**Matriz final executada após o último ajuste do roteiro**

Repetido o comando Compose da seção anterior, agora com `DOCKER_CONTEXT=factorycontrol-contexto-herdado-inexistente` no ambiente pai e `--result /private/tmp/factorycontrol-compose-final-homologation.json`. O contexto fictício não impediu a execução no socket local explícito, comprovando o isolamento real. Resultado: **35 verificações com exit 0**, `success=true`, `temporary_services_stopped=true`.

| Verificação | Resultado da retomada |
|---|---|
| PHP 8.3.33 + MySQL 8.4.13 / Redis 7.4.12 | **23 testes / 111 assertions passaram**, nenhum skipped |
| PHP 8.5.5 + MySQL 8.4.13 / Redis 7.4.12 | **23 testes / 111 assertions passaram**, nenhum skipped |
| Migrations up/down/up, plataforma/extensões e worker Redis | Passaram nos dois runtimes |
| SMTP Mailpit 1.33.0, reset, revogação e token de uso único | Passaram; nova senha aceita, senha anterior rejeitada |
| Readiness com Redis real disponível/desligado | 200 / 503, respectivamente |
| Web lint, typecheck, build e unitários | Passaram; **8 testes / 3 arquivos** |
| E2E Chromium desktop/mobile com banco e Redis reais | **10 passaram**; login → painel → empresas → logout, reload, CSRF, recuperação e erros |
| Pint e Composer validate | Passaram |
| Limpeza Compose e encerramento da VM | Projeto/volumes removidos, nenhum container restante e VM desligada |

E2E em `http://localhost:5173`, desktop 1487×1058 e mobile 390×844; usado Playwright existente porque o controle do navegador integrado continua sem ferramenta de execução disponível. Não houve alteração da UI nem nova avaliação visual por capturas nesta retomada. Windows e Safari/iOS real permanecem sem execução. MySQL preexistente PID 950/porta 3306 preservado. SHA-256 do Compose permanece `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f`.

**Publicação preparada localmente para revisão**
- Pacote: `/private/tmp/factorycontrol-phase0-review.zip`; inventário e SHA-256 por arquivo: `/private/tmp/factorycontrol-phase0-review-manifest.json`.
- Seleção de **154 arquivos** conforme os `.gitignore` existentes, conferida contra exclusões adicionais de `.env` locais, chaves, bancos, logs, `vendor`, `node_modules`, `dist` e relatórios/cache de testes. Exemplos `.env.example` mantidos, com valores locais demonstrativos/pendentes.
- Verificados symlinks e padrões de chaves privadas/tokens conhecidos; nenhuma ocorrência identificada. Essa checagem objetiva não equivale a uma auditoria de segurança completa.
- Inclui código da Fase 0, locks, `.github/workflows/quality.yml` e documentação/planejamento já existente. O pacote e seu manifesto ficam fora do projeto; nenhum conteúdo foi enviado ao GitHub e a pasta principal permanece sem `.git`.
- **Próximo passo dependente do usuário:** autorizar a publicação desse conteúdo no repositório público e disponibilizar acesso de escrita. Depois, acompanhar a execução remota da CI. Fase 1 continua não autorizada e não iniciada; permanece como próxima fase funcional recomendada após essa revisão.

### 2026-11-07 — publicação autorizada e correção revelada pela CI

**Autorização e escopo:** usuário respondeu “sim” à publicação do pacote aprovado de 154 arquivos em `contatonomaci-pixel/fabric`. Conferidos o SHA-256 do ZIP (`aa60090e835cea523c3e9ff71d155a5740301f43b2ded13ef86a2ffc917a47cc`) e todos os hashes do manifesto; nenhum arquivo local divergia do pacote. Plano: publicar por checkout isolado, conferir a árvore remota, acompanhar a CI e corrigir apenas problemas da Fase 0. Risco de publicação pública explicitado e autorizado; sem deploy ou alteração de serviços remotos.

**Publicação realizada**
- Checkout Git separado: `/private/tmp/factorycontrol-phase0-publish-8ytej_lm`; pasta principal preservada sem `.git`. Autenticação Git existente no macOS usada sem exibir, gravar ou versionar credenciais. Autor técnico: `Codex <codex@example.invalid>`, domínio reservado para identificar a automação sem inventar identidade do usuário.
- Primeiro commit: [`07e6d36decf81b0cd4e108167693cff35bf6bbd1`](https://github.com/contatonomaci-pixel/fabric/commit/07e6d36decf81b0cd4e108167693cff35bf6bbd1), branch `main`, push sem force. API confirmou **154 blobs idênticos** ao pacote aprovado. `.env` locais, bancos, logs, dependências instaladas e builds não enviados.
- O conector GitHub continuou indisponível. Descoberta uma opção de integração via skill plugin-management, mas a publicação não dependeu de nova conexão porque o transporte Git já estava autenticado. Leitura de logs autenticada pelo mesmo mecanismo; credencial somente em memória e cabeçalho removido antes de redirecionar para outro host.

**Primeira execução remota e correção**
- [Run 42338793688](https://github.com/contatonomaci-pixel/fabric/actions/runs/42338793688), evento `push`: jobs **web** e **e2e** concluídos com sucesso; **api (8.5)** falhou em `composer test`; **api (8.3)** cancelado pela matriz após a falha do outro runtime.
- Causa confirmada no log: `Test directory ".../apps/api/tests/Unit" not found`. O `phpunit.xml` do scaffold referencia a pasta, existente mas vazia na máquina de desenvolvimento; Git não versiona diretórios vazios. As migrations up/down/up e demais passos anteriores ao teste passaram no job PHP 8.5.
- Correção mínima: adicionado `apps/api/tests/Unit/.gitkeep`, preservando a pasta em clones sem criar teste fictício ou alterar a configuração da suíte. README e documentos 16/17/18/STATUS atualizados com a publicação e as evidências. Nova execução remota será acompanhada antes de afirmar aprovação da CI.
- Validação local após a correção: `composer test` executado com PHP 8.3.33, **20 passaram / 94 assertions; 3 skipped** esperados no perfil SQLite; `python3 scripts/validar-pacote.py` passou, blocos Markdown e `git diff --cached --check` válidos. O arquivo `.gitkeep` está na seleção do commit; Compose preservado.

**CI remota aprovada após a correção**
- Correção publicada no commit [`10c54a53e678c0e9c05621e25fe42d208e13e784`](https://github.com/contatonomaci-pixel/fabric/commit/10c54a53e678c0e9c05621e25fe42d208e13e784), sem force e sem alteração de regras da aplicação ou workflow.
- [Run 42339096676](https://github.com/contatonomaci-pixel/fabric/actions/runs/42339096676): `status=completed`, `conclusion=success`. Estados finais dos quatro jobs conferidos pela API; contagens abaixo extraídas dos logs autenticados, sem imprimir credenciais.

| Job remoto | Resultado confirmado |
|---|---|
| `api (8.3)` | **Sucesso: 23 testes / 111 assertions**, sem skipped; MySQL 8.4/Redis 7, migrations up/down/up, Composer validate e Pint |
| `api (8.5)` | **Sucesso: 23 testes / 111 assertions**, sem skipped; mesma matriz de infraestrutura e qualidade |
| `web` | **Sucesso:** npm ci, lint, typecheck, **8 testes / 3 arquivos** e build |
| `e2e` | **Sucesso: 10 testes**, 8,5 s; Chromium desktop/mobile, PHP 8.3 e SQLite descartável, conforme perfil padrão do workflow |

A CI remota complementa a homologação local com Compose/Mailpit. SMTP/reset e E2E local em MySQL/Redis continuam comprovados nas evidências anteriores; o E2E remoto padrão usa SQLite. Nenhum deploy foi realizado.

**Fechamento da publicação**
- Atualizados README e documentos 16/17/18/STATUS com o resultado aprovado. A atualização final é exclusivamente documental e será publicada com `[skip ci]`, evitando repetir a matriz já aprovada; o commit de aplicação validado permanece `10c54a53e678c0e9c05621e25fe42d208e13e784`.
- Arquivos alterados nesta sessão: `apps/api/tests/Unit/.gitkeep`, `README.md`, `docs/STATUS.md`, `docs/16-fundacao-decisoes.md`, `docs/17-executar-localmente.md`, `docs/18-arquivos-fase-0.md`. Compose original preservado; o pacote aprovado inicial permanece como evidência histórica, anterior à correção do diretório vazio.
- Pendências e riscos mantidos: Windows nativo/Safari/iOS real não executados; avisos moderados de desenvolvimento ainda sem atualização compatível aplicada; ADR-007/marca para revisão antes da expansão. Não há pendência de acesso/publicação/execução da CI nesta sessão.
- Próxima fase funcional recomendada: **Fase 1**, somente após liberação explícita do usuário. A autorização recebida nesta sessão cobriu a publicação e validação da Fase 0; não autorizou iniciar catálogo, produção, financeiro, GTIN/RCN ou BI.

### 2026-11-07 — Fase 1 autorizada: estrutura, catálogo e Code128 interno

**Autorização, objetivo e plano**
- Usuário respondeu **“autorizado”** à pergunta sobre iniciar a Fase 1. Documentos obrigatórios, tarefa `tasks/01-catalogo-codigos.md` e especificações de produtos/unidades, códigos, API e permissões lidos antes de implementar. Mantido o escopo das fases 0–1.
- Plano executado: migration/serviços transacionais e permissões por empresa; formulários de estrutura/catálogo na SPA existente; exemplos fictícios; arquivos Code128; testes de isolamento, concorrência, qualidade e documentação. Riscos priorizados: vínculos entre empresas, reutilização de códigos, peso nominal confundido com estoque, preço sem histórico e resultados atrasados da empresa anterior.
- `git status --short` na pasta principal continua sem `.git`. Usado checkout isolado, branch `feat/phase-1-catalog` a partir do commit de documentação `59d0b0b`, preservando os arquivos de trabalho da IDE. Status/diff também conferidos com `GIT_DIR` isolado e `GIT_WORK_TREE` apontando para esta pasta, sem criar `.git` nela.

**Implementado**
- Empresas próprias: criação, edição, desativação e reativação com Policy; criação associa proprietário e unidades padrão na mesma transação. Fábricas/depósitos com escopo de empresa e tipo configurável, sem controles sanitários/estoque fictícios.
- Famílias com proteção contra ciclos, produtos por tipo, variantes com sabor/peso nominal/características, unidades por dimensão e SKUs por apresentação. UI com seletores pesquisáveis, filtros família/variante/tipo/estado, paginação, loading/vazio/erro/sucesso, formulários e confirmação de desativação.
- PK BIGINT/ULID público mantidos. Foreign keys compostas incluem empresa; testes rejeitam vínculo cruzado também diretamente no banco. Quantidades/pesos DECIMAL(18,6), preço unitário opcional DECIMAL(18,4), payloads decimais como texto, sem preço automático por peso nominal.
- Conteúdo de PCT/CX por SKU, sem fator global de embalagem. g/KG exigem peso variável; pesagem real e baixa de estoque ficam para fases operacionais. Identidade/apresentação de SKU, código/dimensão/fator de unidade e características de variante já usada não podem ser reescritos silenciosamente.
- Reserva de SKU e Code128 dentro de transação, contador técnico global com lock, namespaces/escopos não nulos e índices únicos; códigos ativos/inativos não são reutilizados. Reserva repetida por SKU retorna o mesmo código. Busca de SKUs aceita também o valor do Code128; validação retorna o SKU público associado e disponibilidade efetiva, sem declarar GTIN/PDV aprovado.
- Histórico append-only com autor, data, antes/depois e mudanças de preço. Desativar pai torna filhos indisponíveis sem apagar seu histórico. Gravações relêem/bloqueiam a empresa e revalidam sua autorização dentro da transação.
- SVG/PNG reais com checksum Code128 e quiet zones de 10 módulos; PDF interno de teste, sem conteúdo legal fictício. Nomes extensos abreviados somente na legenda; SKU integral com fonte ajustada. Recursos remotos/PHP/JavaScript desabilitados no Dompdf; textos escapados. RCN/EAN/GTIN rejeitados na reserva desta fase.
- Demonstração idempotente: Coxinha Frango 25g UN/PCT20/KG, Coxinha Carne 25g, Bolinho Carne 50g e Pizza Frango/Carne 400g; fábrica/depósito fictícios; preços não definidos. Contas extras de catálogo só no runner E2E, com banco/senha efêmeros e proteção contra uso fora dele.
- Permissões: proprietário gerencia sua empresa/estrutura/catálogo; auditor consulta; operador sem nova concessão administrativa. Mantidos Sanctum/CSRF e limites de login/recuperação. Orçamentos da área autenticada separados por usuário: leituras 600/min e gravações 60/min, configuráveis e com teste de isolamento/429.
- Instalados e fixados no lock: Picqer **3.3.0**, Dompdf **3.1.6** e quatro dependências, sem atualizar pacotes preexistentes. GD/BCMath explícitos; Composer não reportou advisories na instalação. Nenhuma dependência nova no frontend.

**Problemas reproduzidos e corrigidos durante a implementação**
- Um objeto de empresa carregado antes de sua desativação permitia passar pela autorização inicial do serviço. Teste específico **falhou antes** e passou após rechecagem dentro da transação com lock da empresa. Não foi relaxada a autorização.
- A suíte E2E ampliada atingiu as cotas compartilhadas de leituras/login. Confirmados 429 nos estados de erro; separados os orçamentos de leitura/gravação e isoladas contas fictícias de catálogo por projeto, mantendo o limite original de login. Teste confirma que leituras não consomem gravações e que ambos continuam limitados.
- Cenário inicial de navegação completa após login apresentou cookie ausente. O helper E2E passou a aguardar explicitamente POST 200 e presença de cookie HttpOnly antes da navegação; não foi alterado o contrato de autenticação para esse ajuste de sincronização.
- PDF com nome de 150 caracteres/SKU de 40 caracteres ocupava duas páginas e depois apresentou sobreposição no teste visual. Ajustados legenda, dimensões e fonte do SKU; arquivo final em uma página, sem sobreposição, decodificado por leitor independente e coberto pelo teste de exportação.

**Verificações locais efetivamente executadas**

| Verificação | Resultado |
|---|---|
| Migration da fase up/down/up em MySQL 8.4.13 | Passou em PHP 8.3.33 e 8.5.5 |
| Matriz MySQL/Redis com leitura independente de PNG | **39 testes / 283 assertions passaram em cada runtime**, sem skipped, na evidência `/private/tmp/factorycontrol-phase1-verified.json` |
| Dois processos/atores simultâneos, MySQL real | Autogeração retornou dois identificadores distintos; tentativa manual duplicada teve exatamente um vencedor; reservas preservadas |
| Schema MySQL | Dez tabelas novas conferidas InnoDB/utf8mb4; preço/quantidades/pesos conferidos como DECIMAL com precisão/escala previstas |
| Ajustes finais de exportação/consulta por Code128 | Suíte SQLite posterior com leitor: **34 passaram / 226 assertions; 5 skipped** esperados de infraestrutura/concorrência; exportação focada passou com 18 assertions |
| SMTP, sessão, worker e readiness | Smoke real herdado da fundação passou; Redis desligado retornou 503; Mailpit recebeu somente mensagem fictícia |
| Web lint, typecheck e build | Passaram sem erro/aviso de lint |
| Vitest | **12 testes / 5 arquivos passaram**, incluindo preço textual, identidade preservada e descarte de resposta da empresa anterior |
| E2E em PHP 8.3 + MySQL/Redis reais | **14 passaram**, desktop 1487×1058 e mobile Chromium 390×844; 29,7 s na matriz registrada |
| E2E padrão SQLite | **14 passaram**, 13,3 s na execução registrada após separar contas/limites |
| Pint, Composer validate e requisitos de plataforma | Passaram; GD/BCMath e extensões/runtime PHP 8.3 disponíveis |
| OpenAPI Redocly CLI 2.76.0 `--extends minimal` | Válido, zero erros; dois avisos de localhost intencionais. 43 operações documentadas, incluindo CSRF; 42 rotas API v1 conferidas integralmente contra `route:list --json` |
| Pacote documental | `python3 scripts/validar-pacote.py`: passou, agora 11 documentos essenciais |

A matriz detalhada contém **35 verificações com exit 0**, `success=true`, `temporary_services_stopped=true`. A contagem de assertions da matriz precede as últimas verificações de PDF/consulta por código; os testes posteriores estão discriminados acima e a CI da branch validará o snapshot publicado. Evidências temporárias não contêm credenciais reais nem são banco de operação.

**QA visual e artefatos de teste**
- Skill frontend-testing-debugging aplicada ao fluxo login → empresa → estrutura/catálogo → cadastro → preço/histórico → desativação/duplicidade, com troca de empresa e filtros. Controle do Browser integrado sem ferramenta de execução disponível; utilizado Playwright existente, permitido pelo fluxo de testes já adotado.
- Screenshots desktop/mobile e de SKU em `/private/var/folders/9v/c9y9j1gx7_b9h0y3jvhfww_80000gn/T/factorycontrol-qa/`: `catalog-desktop.png`, `catalog-mobile.png`, `sku-desktop.png`, `sku-mobile.png`. Inspecionados com `view_image`; ajustado botão de cadastro herdado do login que ocupava toda a largura. Conteúdo legível, estados claros e sem overflow horizontal nas interações; overlays/erros do fluxo principal cobertos pelo E2E. 401/419/422 intencionais não são falhas da aplicação.
- Skill PDF aplicada: Poppler ausente, renderização por PDFium **5.15.0** isolado em venv temporário; PNG e PDF renderizado decodificados com ZXing-C++ **2.3.0**/Pillow **11.3.0**. Normal e caso máximo em uma página, imagens finais abertas com `view_image`. Arquivos: `/private/tmp/factorycontrol-phase1-barcode.svg/.png/.pdf`, `factorycontrol-phase1-label-long.pdf`, `factorycontrol-phase1-label-normal.png` e `factorycontrol-phase1-label-long.png`.

**Arquivos e limites**
- Relação completa em [Arquivos da Fase 1](20-arquivos-fase-1.md); uso, contratos e decisões em [Catálogo da Fase 1](19-catalogo-fase-1.md). README e guia Windows/Unix atualizados.
- Compose preservado: SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f`. Projeto/volumes descartáveis removidos após cada matriz; nenhuma conexão ao banco MySQL 9.6 preexistente ou infraestrutura remota foi usada para os testes.
- Windows nativo, Safari/iOS real, scanner/impressora físicos e parâmetros técnicos/fiscais continuam fora da homologação desta sessão. Os avisos transitivos de desenvolvimento anteriores não foram reauditados/forçados nesta fase. Leitura por software não equivale a homologação de PDV.
- Preparar branch de revisão e CI da Fase 1; sem deploy ou implementação de fases futuras. Próxima fase funcional recomendada: **Fase 2**, somente após revisão da Fase 1 e liberação explícita.

**Branch de revisão e CI remota da Fase 1**
- Implementação publicada no commit [`60eb3566e9bbee5194e41e70aae79b59d89164df`](https://github.com/contatonomaci-pixel/fabric/commit/60eb3566e9bbee5194e41e70aae79b59d89164df), branch `feat/phase-1-catalog`. Aberto [PR #1 em rascunho](https://github.com/contatonomaci-pixel/fabric/pull/1), com 77 arquivos alterados (47 criados / 30 adaptados); 202 arquivos no snapshot, sem ambientes locais/dependências instaladas/artefatos de QA.
- [Run de push 42387386551](https://github.com/contatonomaci-pixel/fabric/actions/runs/42387386551) e [run do PR 42387827301](https://github.com/contatonomaci-pixel/fabric/actions/runs/42387827301) concluíram com `success`. Os quatro jobs e passos de Pint, migration rollback, instalação do leitor independente e testes foram conferidos pela API.

| Job remoto no PR | Resultado extraído dos logs |
|---|---|
| API PHP 8.3 | **39 passaram / 287 assertions**, sem skipped; MySQL 8.4/Redis 7, concorrência e leitor ZBar |
| API PHP 8.5 | **39 passaram / 287 assertions**, sem skipped; mesma matriz |
| Web | **12 passaram / 5 arquivos**, lint/typecheck/build aprovados |
| E2E PHP 8.3 | **14 passaram**, 23,2 s, Chromium desktop/mobile e SQLite descartável |

- Logs e JSONs de evidência fora do projeto: `/private/tmp/factorycontrol-phase1-ci-jobs.json` e `factorycontrol-phase1-ci-{api83,api85,web,e2e}.log`. Nenhuma credencial impressa/versionada; autenticação Git existente utilizada para push e API do repositório.
- Conferência final de portabilidade do runner: normalizados os separadores de caminhos na proteção do SQLite temporário, mantendo o confinamento ao diretório de E2E. Isso não comprova execução Windows; o runtime nativo continua pendente de homologação.
- VM local desligada após a remoção dos containers/volumes de teste. MySQL preexistente **PID 950**, porta **3306**, confirmado ativo. A main permanece na entrega da Fase 0; esta fase fica disponível para revisão na branch/PR, sem integração automática.
- Próximos passos: revisar/integrar o PR da Fase 1 e, depois, liberar explicitamente a Fase 2 se desejado. Nenhum módulo futuro foi iniciado.

### 2026-11-08 — Conferência final da Fase 1

- O ajuste de portabilidade do runner foi publicado no commit [`9cd528d91825dfa77f0fa9ba10e0bec38c2c625a`](https://github.com/contatonomaci-pixel/fabric/commit/9cd528d91825dfa77f0fa9ba10e0bec38c2c625a). [CI do PR 42389603780](https://github.com/contatonomaci-pixel/fabric/actions/runs/42389603780) e [CI de push 42389599246](https://github.com/contatonomaci-pixel/fabric/actions/runs/42389599246) concluídas com sucesso; os quatro jobs finais foram conferidos: API PHP 8.3, API PHP 8.5, web e E2E.
- Reexecutados `python3 scripts/validar-pacote.py` (11 documentos essenciais presentes), `git diff --check` da entrega e SHA-256 do Compose; todos aprovados. Nenhuma mudança funcional adicional neste fechamento documental.
- Evidências finais da API do GitHub: `/private/tmp/factorycontrol-phase1-final-runs.json` e `/private/tmp/factorycontrol-phase1-final-ci-jobs.json`. PR #1 permanece em rascunho para revisão; main preservada na Fase 0. Sem deploy e sem autorização para a Fase 2.
