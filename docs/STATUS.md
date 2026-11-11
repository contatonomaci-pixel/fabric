# STATUS — controle de progresso do projeto

**Data de criação:** 2026-11-06.
**Ambiente local:** ativado em 2026-11-10 em **http://localhost:5173/entrar**; conta fictícia `demo@example.test`, senha exclusiva entregue no chat. API/SPA/worker e MySQL 8.4/Redis/Mailpit mantidos ativos, com volumes persistentes. Retomada em [Executar localmente](17-executar-localmente.md). Ajustes de ativação e revisão técnica incorporados à branch da Fase 2; publicação/CI acompanhadas no registro final abaixo.
**Situação:** Fase 2 implementada, revisada e homologada localmente e na CI em PHP 8.3/8.5 + MySQL 8.4/Redis: **63 testes API/606 assertions por runtime, 20 SPA e 16 E2E**. Inclui compras/recebimentos, lotes/locais, políticas físicas, FEFO, reservas, baixas/transferências/contagens, estornos e pesagens manuais. [PR #2 em rascunho](https://github.com/contatonomaci-pixel/fabric/pull/2), branch `feat/phase-2-inventory`, com [quatro jobs da CI aprovados](https://github.com/contatonomaci-pixel/fabric/actions/runs/42948312256); correções das duas revisões técnicas aprovadas, integração pendente. Main permanece na Fase 1 integrada pelo [PR #1](https://github.com/contatonomaci-pixel/fabric/pull/1). Fases 3–10 não autorizadas. Sem deploy; homologação Windows/Safari/iOS/hardware e parâmetros técnicos/fiscais permanecem pendentes.

**Revisão técnica de 2026-11-08:** três achados P2 reproduzidos e corrigidos, com regressões incorporadas e aprovadas localmente e na CI. Limites de família coerentes; características de variante editáveis antes do primeiro SKU; estado próprio e disponibilidade apresentados separadamente. Evidências completas no final deste documento.

| Fase | Status | Observação |
|---|---|---|
| 0 — Fundação técnica | HOMOLOGADA LOCALMENTE E NA CI | Código publicado; quatro jobs aprovados após corrigir a preservação de tests/Unit em clones |
| 1 — Catálogo/identificadores | INTEGRADA À MAIN; CI APROVADA | Três achados P2 corrigidos; 42 API por runtime, 16 SPA e 14 E2E; PR #1 integrado, sem deploy |
| 2 — Compras/estoque | SEGUNDA REVISÃO E CI CONCLUÍDAS; PR #2 EM RASCUNHO | Novo P2/P3 corrigidos; 63 API/606 assertions por runtime, 20 SPA e 16 E2E; integração pendente, sem merge/deploy |
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

### 2026-11-08 — Revisão técnica solicitada pelo usuário

**Escopo e método**
- Revisada a entrega da Fase 1 contra a base `59d0b0b`, snapshot publicado `d54fed03067f3dd4333c67e6cffca66f4b80e050`: serviços, autorização, migrations/constraints, reservas, preços, exportações, SPA, testes e documentação. Documentos obrigatórios, tarefa ativa e prompt de revisão consultados. Checkout isolado estava limpo e a pasta da IDE não tinha alterações em relação a esse snapshot antes da revisão.
- Objetivo: identificar problemas reproduzíveis sem iniciar fases futuras. Plano executado: inspeção dos fluxos por empresa e regras de cadastro; reexecução das suítes disponíveis; reproduções adicionais em SQLite descartável e Chromium. Arquivo da entrega alterado nesta revisão: somente `docs/STATUS.md`. Código da aplicação e CI não foram corrigidos, publicados ou integrados nesta sessão de revisão.

**Achados confirmados — P2 (severidade média)**

1. **Hierarquia válida torna uma variante nova indisponível.** `apps/api/app/Domain/Catalog/CatalogService.php:112` aplica o limite de 20 ao caminho inteiro de referências (SKU → variante → produto → famílias), enquanto a validação de famílias em `:218` conta apenas a hierarquia de famílias. Reprodução: criar 20 famílias encadeadas pela API, todas ativas; criar produto na última família (201, `effective_active=true`); criar variante desse produto (201, mas `effective_active=false`). Nenhum ancestral foi desativado. Essa variante não pode ser usada em novo SKU, pois a verificação do pai utiliza a mesma disponibilidade. **Correção recomendada:** usar um limite coerente para a hierarquia de famílias e detectar ciclos separadamente das referências de outros recursos; cobrir os limites com produto/variante/SKU e reparentamento de subárvore.
2. **SPA impede corrigir características de variante ainda sem SKU.** `apps/web/src/lib/catalog.ts:89`–`:102` define produto, sabor, peso e características como `lockedOnEdit` incondicionalmente. Reprodução visual: criar variante nova sem SKU, editar; sabor/peso ficam desabilitados. Na mesma sessão, PATCH direto de sabor retornou 200, confirmando a autorização/regra do backend antes do primeiro SKU. A imutabilidade condicional do serviço (`CatalogService.php:231`) e a documentação não correspondem ao formulário. **Correção recomendada:** refletir o bloqueio por existência de SKU na resposta e na UI, mantendo a validação no servidor; verificar também a edição de variantes existentes sem alterar identificadores usados.
3. **Filtro de estado contradiz o indicador exibido.** `apps/web/src/components/CatalogPanel.vue:172` oferece “Ativos/Inativos”, mas `:228`–`:231` classifica também `effective_active=false` como “Inativo / indisponível”; a consulta em `CatalogService.php:30`–`:31` filtra somente `active`. Reprodução: desativar o produto Coxinha; seus quatro SKUs continuam no filtro “Ativos”, apesar do indicador “Inativo / indisponível”, e não entram no filtro “Inativos”. O problema é a divergência da interface, não um bypass da indisponibilidade no backend. **Correção recomendada:** distinguir estado próprio e disponibilidade nos indicadores/filtros, ou adicionar filtro explícito de disponibilidade efetiva, preservando paginação e totais.

Nenhum achado P0/P1 foi confirmado nesta revisão. Isso não equivale a auditoria exaustiva de segurança. Estoque, FEFO, lançamentos financeiros e regras fiscais de fases futuras não foram implementados nem homologados nesta revisão.

**Resultados executados nesta revisão**

| Comando/cenário | Resultado efetivo |
|---|---|
| PHP 8.3.33 `php artisan test`, SQLite em memória | **34 passaram / 225 assertions; 5 skipped** esperados de MySQL/Redis/concorrência |
| `npm test -- --run` | **12 passaram / 5 arquivos** |
| `php vendor/bin/pint --test` e `npm run lint` | Passaram |
| `npm run build` (inclui `vue-tsc -b`) | Typecheck e build passaram |
| `npm run test:e2e`, Chromium desktop/mobile, SQLite descartável | **14 passaram**, 13,1 s |
| PHPUnit adicional `/private/tmp/FactoryControlPhase1ReviewTest.php` | **2 falhas / 49 assertions** reproduzindo hierarquia e divergência do filtro; fora da suíte versionada |
| Playwright adicional, variante sem SKU | **1 falha esperada** em `toBeEnabled()` de Sabor; PATCH anterior retornou 200 e novo sabor correto |
| `python3 scripts/validar-pacote.py` | Passou, 11 documentos essenciais |

- Reproduções adicionais ficam fora do repositório para não alterar a suíte da entrega durante a revisão. API: executar de `apps/api` com PHP 8.3, `vendor/bin/phpunit --configuration phpunit.xml /private/tmp/FactoryControlPhase1ReviewTest.php`. UI: de `apps/web`, `npx playwright test --config /private/tmp/factorycontrol-phase1-review-e2e/playwright.config.ts`. São evidências locais temporárias, não dependências de execução do projeto.
- QA visual: `http://localhost:5173`, Chromium 1487×1058, fluxo login → catálogo → criar variante sem SKU → editar → confirmar campos bloqueados e PATCH permitido. Página correta, conteúdo renderizado, sem overlay ou erro JavaScript; screenshot inspecionado: `/private/tmp/factorycontrol-phase1-review-e2e/variant-locked-desktop.png`. Browser integrado sem ferramenta de execução disponível após descoberta; fallback Playwright utilizado no fluxo de testes autorizado. Primeira inicialização sob sandbox falhou; reprodução e E2E existentes executados após liberação dos servidores locais/Chromium.
- MySQL/Redis e os dois runtimes da CI não foram reexecutados nesta revisão; os cinco skipped não foram contados como aprovados. Evidências históricas dessas matrizes permanecem acima. Windows/Safari/iOS e hardware físicos continuam sem homologação. Servidores e bancos E2E desta revisão são descartáveis e foram encerrados pelo runner; MySQL preexistente e Compose preservados.
- **Próximo passo:** corrigir os três achados exclusivamente na Fase 1, incorporar testes de regressão e repetir as verificações afetadas antes de integrar o PR. Fase 2 continua dependente de liberação explícita.

### 2026-11-08 — Correções dos três achados da Fase 1

**Autorização e implementação**
- Usuário solicitou “CONTINUE” após a revisão: corrigidos exclusivamente os três achados, preservando o registro anterior e o trabalho local. Antes de editar, status conferido com checkout Git isolado: somente `docs/STATUS.md` estava alterado pela revisão. Plano anunciado: corrigir domínio/UI, acrescentar regressões, verificar qualidade e atualizar este documento. Riscos tratados: ciclos e profundidade de subárvore, identidade usada por SKU e mudanças de estado confundidas com disponibilidade.
- Hierarquia: limite único de **20 famílias**, incluindo raiz/folha. Produto/variante/SKU não consomem níveis; leitura detecta ciclos por registros visitados. A carga dos ancestrais inclui os níveis de referências adicionais. Criação rejeita nível 21; mudança de família superior considera também a altura dos descendentes dentro da transação com lock da empresa.
- Variantes: resposta inclui `identity_locked` em criação, consulta, edição e listagem; listagem usa EXISTS com escopo da empresa. É verdadeiro se existir qualquer SKU, inclusive inativo. O formulário libera características somente com valor explícito falso; a autorização e a regra transacional do servidor continuam responsáveis pela integridade. PATCH envia somente alterações, preservando atributos nulos e evitando reenvio de dados antigos inalterados.
- Estado: mantido o contrato de `active`/paginação da API. Filtro renomeado para **Estado do cadastro**, com explicação de que considera estado próprio. Indicadores separados: **Ativo/Inativo** e, quando aplicável, **Indisponível por dependência**. Não foi adicionado filtro que carregue todas as páginas no frontend.
- Sem migration, dependência nova, mudança de Compose ou módulo de fase futura. OpenAPI e guia do catálogo documentam o novo campo e as regras.

**Regressões e verificações**
- Três testes novos da API falharam antes da correção: nível 21 indevidamente aceito, reparentamento sem verificar descendentes e ausência de `identity_locked`. Após corrigir: **3 passaram / 37 assertions**. Cobrem 20 níveis com produto/variante/SKU e desativação/reativação da raiz, rejeição do nível 21, ciclos externos simulados, reparentamento permitido/proibido e bloqueio mesmo com SKU inativo.
- Componentes: edição de sabor/peso antes de SKU; preservação de atributos nulos inalterados; bloqueio com SKU/flag ausente; alteração apenas de nome após bloqueio; estado próprio e dependência exibidos separadamente. Vitest: **16 passaram / 5 arquivos**.
- E2E ampliado nos cenários existentes: cria variante, corrige sabor/peso/característica, cria SKU, confirma bloqueio, edita nome, desativa produto e verifica os dois indicadores com filtros Ativos/Inativos. Primeira execução teve dois timeouts no seletor textual do filtro; DOM confirmou o nome acessível e o seletor foi ajustado para combobox. Execução final: **14 passaram, 15,2 s**, sem relaxar regras, cotas ou timeout.

| Verificação local após correções | Resultado |
|---|---|
| PHP 8.3.33 `php artisan test`, SQLite em memória | **37 passaram / 262 assertions; 5 skipped** de MySQL/Redis/concorrência |
| Pint e Composer validate `--strict` | Passaram |
| Web lint, typecheck, build e Vitest | Passaram; lint final sem warnings; **16 testes** |
| E2E Chromium desktop/mobile, SQLite descartável | **14 passaram** |
| OpenAPI JSON | Válido; campo de leitura `identity_locked` documentado e obrigatório na resposta de variante |
| Redocly CLI 2.76.0 `--extends minimal` | Válido, zero erros; dois avisos esperados dos servidores localhost |

**QA e arquivos**
- URL `http://localhost:5173`; desktop 1487×1058 e mobile Chromium 390×844. Skill frontend-testing-debugging já aplicada; Browser integrado sem ferramenta de execução disponível, mantido Playwright do projeto para o fluxo autorizado. Fluxos reais de cadastro/edição/estado exercitados em ambas as dimensões; verificações existentes de conteúdo, overlay/erros e overflow preservadas. Capturas `dependency-desktop.png` e `dependency-mobile.png` inspecionadas com `view_image`, em `/private/var/folders/9v/c9y9j1gx7_b9h0y3jvhfww_80000gn/T/factorycontrol-qa/`.
- Arquivos alterados: `apps/api/app/Domain/Catalog/{CatalogService,ProductVariant}.php`, `apps/api/tests/Feature/CatalogTest.php`; `apps/web/src/lib/catalog.ts`, `apps/web/src/components/{CatalogForm,CatalogPanel}.vue`, seus dois testes e `apps/web/e2e/catalog.spec.ts`; `docs/api/openapi.json`, `docs/19-catalogo-fase-1.md` e `docs/STATUS.md`.
- Publicação das correções no PR #1 e CI MySQL/Redis/PHP 8.3/8.5 serão acompanhadas; os cinco skipped locais não representam aprovação dessa matriz. Main e MySQL preexistente preservados. Sem deploy ou autorização para a Fase 2.

**CI das correções aprovada e fechamento**
- Correções publicadas no commit [`1b6234dc65e1a9ad15142b1643165b855a1c293a`](https://github.com/contatonomaci-pixel/fabric/commit/1b6234dc65e1a9ad15142b1643165b855a1c293a), no PR #1 existente, sem force/merge. [Run do PR 42490071950](https://github.com/contatonomaci-pixel/fabric/actions/runs/42490071950) concluído com `success`; os quatro jobs e os logs finais foram conferidos.

| Job remoto das correções | Evidência extraída dos logs |
|---|---|
| API PHP 8.3 | **42 passaram / 324 assertions**, sem skipped; MySQL 8.4/Redis, concorrência e leitura independente com ZBar |
| API PHP 8.5 | **42 passaram / 324 assertions**, sem skipped; mesma matriz |
| Web | **16 passaram / 5 arquivos**, lint, typecheck e build aprovados |
| E2E PHP 8.3 | **14 passaram**, 24,6 s; Chromium desktop/mobile e SQLite descartável, incluindo os fluxos de regressão |

- Evidências fora do projeto: `/private/tmp/factorycontrol-phase1-fixes-local.json`, `factorycontrol-phase1-fixes-ci-result.json`, `factorycontrol-phase1-fixes-ci-jobs.json` e `factorycontrol-phase1-fixes-{api83,api85,web,e2e}.log`.
- Revisão do diff final, validação documental e hashes concluídos: **202 arquivos locais idênticos à branch**; Compose original SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f`. Nenhum ambiente local, credencial, dependência instalada ou artefato de QA versionado. README também atualizado no fechamento documental, totalizando 13 arquivos alterados nesta continuação; manifesto global da Fase 1 permanece com os mesmos 77 arquivos.
- Os três achados P2 desta revisão estão corrigidos e cobertos por regressões; isso não elimina os limites de homologação Windows/Safari/iOS/hardware nem os avisos transitivos anteriores. PR #1 permanece em rascunho para revisão humana. Próximo passo: revisar/integrar a Fase 1; Fase 2 somente após liberação explícita. Nenhum deploy foi realizado.

### 2026-11-08 — Integração da Fase 1 à main

**Escopo, conferência e integração**
- Usuário solicitou “continue” após a conclusão das correções e a recomendação de revisar/integrar o PR. Escopo anunciado desta continuação: conferência final e integração da Fase 1; Fase 2 permanece sem liberação. Objetivo/arquivos: registrar a integração em `docs/STATUS.md`, README e guias 17/19. Riscos verificados: head/base diferentes dos revisados, conflitos, checks reprovados/pendentes, discussões de revisão abertas e divergência dos arquivos locais.
- Antes da integração, workspace e checkout isolado estavam sem alterações. Conferidos **202 arquivos idênticos**, nenhum `.env`/dependência/build/relatório versionado; SHA-256 do Compose original mantido. `git diff --check main...HEAD` e `python3 scripts/validar-pacote.py` passaram (11 documentos essenciais). A pasta da IDE continua sem `.git` e não foi substituída.
- Inspeção remota: PR #1 aberto, head `a369fbaec48ede7d82814530ee816bc45109a558`, base `59d0b0be8152d9c8d0a08603d82a2b305ebd6075`, `mergeable=true`, `mergeable_state=clean`, **oito checks aprovados** (push/PR). Nenhuma revisão formal registrada e nenhuma discussão não resolvida; não foi criado parecer humano fictício nem contornada proteção de branch.
- Conector GitHub novamente retornou `Unknown tool`; usado transporte Git/API já autenticado. Revalidados head, base, checks e discussões antes de cada mudança. PR retirado do rascunho e integrado com **merge commit**, preservando o histórico e exigindo o SHA revisado no pedido de merge. Sem force, exclusão de branch ou mudanças de configuração do repositório.
- GitHub confirmou `merged=true`, PR fechado e main no commit [`f35ff654dbb0899b2eb218dab7f1ad7cb0d4e6d9`](https://github.com/contatonomaci-pixel/fabric/commit/f35ff654dbb0899b2eb218dab7f1ad7cb0d4e6d9), em **2026-11-08 14:39:45 UTC**. [PR #1 integrado](https://github.com/contatonomaci-pixel/fabric/pull/1).
- Checkout isolado sincronizado por fetch e fast-forward da main. `git diff --exit-code a369fba origin/main` retornou zero: árvore integrada idêntica ao snapshot aprovado, sem alteração funcional nova. Guias atuais passam a orientar clone/atualização da `main`.
- Evidências locais temporárias: `/private/tmp/factorycontrol-phase1-premerge.json`, `factorycontrol-phase1-ready.json`, `factorycontrol-phase1-merge-result.json` e `factorycontrol-phase1-postmerge.json`; credenciais somente em memória, sem impressão/versionamento.

**CI da main e documentação**
- [Run 42490900510 da main](https://github.com/contatonomaci-pixel/fabric/actions/runs/42490900510), commit de merge `f35ff65`: `completed/success`. Quatro jobs aprovados, com resultados conferidos nos logs: **42 testes / 324 assertions em cada PHP (8.3/8.5)**, sem skipped, em MySQL 8.4/Redis com concorrência/ZBar; **16 testes SPA / 5 arquivos**, lint/typecheck/build; **14 E2E** Chromium desktop/mobile com SQLite descartável.
- Evidências: `/private/tmp/factorycontrol-phase1-merge-ci-result.json`, `factorycontrol-phase1-merge-ci-jobs.json` e `factorycontrol-phase1-merge-{api83,api85,web,e2e}.log`. Não foi necessário repetir manualmente as suítes locais: a integração manteve a mesma árvore, e a CI executou novamente a matriz completa na main.
- Publicado fechamento exclusivamente documental para refletir o estado integrado e orientar clone/atualização da main. Validação documental e `git diff --check` passaram. CI automática desse fechamento também acompanhada; sem mudanças adicionais de aplicação.

**Limites e próximo passo**
- Esta continuação altera somente quatro arquivos documentais: `README.md`, `docs/17-executar-localmente.md`, `docs/19-catalogo-fase-1.md` e `docs/STATUS.md`. Sem deploy, migrations em banco existente, alteração de Compose ou início de módulo futuro. Revalidação automática da main acompanhada antes de concluir a entrega.
- Windows/Safari/iOS real, equipamentos físicos e avisos transitivos de desenvolvimento mantêm os limites anteriores. Próxima fase funcional recomendada: **Fase 2 — fornecedores, compras, recebimento, estoque/lotes, FEFO e pesagens básicas**, somente após autorização específica.

### 2026-11-08 — Início da Fase 2

- Usuário solicitou “continue” após a Fase 1 integrada e a indicação da Fase 2 como próxima etapa. Escopo adotado e anunciado: exclusivamente compras/estoque da tarefa `tasks/02-compras-estoque.md`. Leitura dos documentos obrigatórios e de produtos/unidades, fluxos e permissões concluída. Sem início de produção, vendas ou financeiro.
- Workspace e checkout isolado estavam limpos na base `c8dc2008e9ebc4e8de48bd66e77aefff999cc4e9`. Criada branch local `feat/phase-2-inventory`; a pasta da IDE permanece sem `.git`.
- Plano: migrations/modelos de Purchasing/Inventory, serviços transacionais com idempotência e autorização, telas no layout existente, testes de FEFO/precisão/estorno/concorrência e documentação. Riscos prioritários: saldo negativo, dupla baixa/recebimento, lote indisponível e vazamento de custos.
- Decisões explícitas registradas em `docs/21-compras-estoque-fase-2.md`: políticas físicas por variante, nenhuma massa derivada do nominal, estado inicial/liberação com permissão, estoque append-only e custos segregados. Implementação e validação em andamento; ainda sem afirmação de entrega da Fase 2.

### 2026-11-10 — Entrega local da Fase 2

**Implementação**
- Módulos `Purchasing`/`Inventory`, migration reversível com 15 tabelas InnoDB/utf8mb4, PK BIGINT/ULID público e FKs compostas por empresa. Fornecedores, locais, política por variante, pedidos/itens imutáveis após emissão, recebimentos parciais com lote/datas/quantidade/custo e consulta paginada por contexto. Código do local/depósito e política usada preservados; variante com lote também bloqueia características mesmo sem SKU.
- Livro físico append-only e saldos como projeções transacionais por lote/local. Transferência pareada; contagem com fotografia anterior/atual; estorno único compensatório, sem apagar origem. Estorno de recebimento restaura o restante do pedido; estorno de consumo não reabre reserva. Reservas e baixas validam disponibilidade/qualidade/validade dentro da transação. Lock e reautorização da empresa serializam operações nesta fase e impedem saldo/reserva negativos.
- Idempotência por empresa/chave não nula e hash do comando/payload. Replay retorna o mesmo identificador sem repetir efeitos; conteúdo diferente é rejeitado. Campos físicos/monetários chegam como texto decimal. Pesagem manual bruto/tara/líquido, autor e UTC; registro isolado não altera estoque. Valores em KG vêm de massa real em gramas dividida por 1000, nunca do peso nominal. Políticas UN/massa/dupla não recebem fatores de embalagem globais.
- FEFO automático somente para lotes liberados/não vencidos, por validade e ID; ausência de validade requer política/justificativa explícita e fica após lotes datados. Política dupla exige posição e duas quantidades declaradas, sem rateio teórico. Consumo confirma cada alocação de massa; limite de 100 alocações por reserva. Estado básico de lote exige concessão própria; não implementa inspeções avançadas/rotulagem da Fase 4.
- Papéis compras/almoxarifado/qualidade, permissões atomizadas e custos protegidos por `purchasing.costs`. Auditor/almoxarifado não recebem custos por padrão; qualidade não acessa compras/contatos por eventos/operações, inclusive acesso direto. Operador de produção não recebe administração. Sem endpoint cliente para conceder papéis.
- Telas `/compras` e `/estoque` no layout existente, com empresa de trabalho, seletor compacto de seção, formulários, motivos/confirmações, filtros de variante/depósito/qualidade/validade, paginação e estados de carga/vazio/erro/sucesso. Moeda, números e datas em pt-BR; saldos sem reserva são físicos e não equivalem a liberação de qualidade. Demo acrescenta fornecedor/local fictícios, sem política, saldo, custo ou prazo sanitário inventado.
- Nenhuma dependência nova, nenhuma alteração do Compose e nenhum módulo de produção, vendas, financeiro, GTIN/RCN ou BI. Guia de uso/decisões em [Compras e estoque](21-compras-estoque-fase-2.md); relação de arquivos em `docs/22-arquivos-fase-2.md`.

**Testes e evidências locais**

| Verificação | Resultado efetivo |
|---|---|
| Matriz MySQL 8.4.13 / Redis 7.4.12, PHP 8.3.33 e 8.5.5 | **58 passaram / 535 assertions em cada runtime**, sem skipped; inclui fases anteriores |
| Concorrência entre dois processos/atores | Disputa por reserva/baixa teve só um vencedor; replay simultâneo da mesma chave teve só um efeito físico |
| Migration up/down/up | Passou nos dois runtimes em bancos descartáveis; nenhuma tabela de operação real usada |
| Integridade física/schema | 15 tabelas conferidas InnoDB/utf8mb4, quantidades DECIMAL(18,6); FK rejeitou posição ligando lote/local de empresas diferentes diretamente no banco |
| SQLite isolado, `composer test` com PHP 8.3 | **50 passaram / 407 assertions; 8 skipped** esperados de infraestrutura/concorrência, não contados como aprovados |
| Vitest | **19 passaram / 7 arquivos**, incluindo privacidade de custos e retry de comandos/contexto durante CSRF |
| Web lint, typecheck e build | Passaram; apresentação final usa moeda/datas pt-BR e esconde grandezas não acompanhadas pela política |
| E2E PHP 8.3 + MySQL/Redis reais | **16 passaram**, 56,9 s, Chromium desktop/mobile |
| E2E padrão SQLite após ajustes de apresentação | **16 passaram**, 22,4 s; os dois cenários novos foram repetidos após ajuste visual e passaram |
| Pint, Composer validate e extensões | Passaram na matriz; Composer test local avisou cache pessoal sem permissão e prosseguiu sem cache, exit 0 |
| SMTP/reset/sessão/worker/readiness | Smoke herdado da fundação passou; Redis desligado retornou 503 e reset usou somente destinatário fictício no Mailpit |
| OpenAPI | Redocly CLI 2.76.0 minimal: zero erros, dois avisos esperados de localhost; **91 operações/69 caminhos**, 90 rotas v1 conferidas integralmente contra `route:list --json` mais CSRF |

- Matriz final: `/private/tmp/factorycontrol-phase2-verified.json`, **35 verificações com exit 0**, `success=true`, `temporary_services_stopped=true`. Primeira matriz anterior, com 57 testes antes da verificação adicional de FK/schema: `factorycontrol-phase2-matrix.json`. A apresentação pt-BR/seleção de seção foi ajustada depois da matriz; lint/build/unit e E2E foram repetidos para esses ajustes. CI da branch conferirá o snapshot completo publicado.
- Comando real da matriz: `BARCODE_DECODER=/private/tmp/factorycontrol-phase1-decoder/read-code128 python3 scripts/homologar-fundacao.py --compose --docker /opt/homebrew/opt/docker/bin/docker --docker-host unix:///Users/mauriciosilva/.colima/factorycontrol-phase0/docker.sock --docker-config /private/tmp/factorycontrol-docker-config --php /opt/homebrew/opt/php@8.3/bin/php --php /opt/homebrew/bin/php --result /private/tmp/factorycontrol-phase2-verified.json`.
- Inicialização local: Colima/Docker não estavam no PATH e o primeiro caminho de Colima não existia. Resolvido usando binários em `/opt/homebrew/opt/{colima,docker}/bin` e PATH somente do processo, sem instalação ou alteração global. Perfil local reutilizado com `--activate=false`. Containers/volumes temporários removidos pelo runner; VM desligada ao concluir. MySQL preexistente PID 950 confirmado ativo por inspeção de processo, sem conexão ao banco. Compose original preservado.

**QA visual e limites**
- Skill frontend-testing-debugging aplicada ao fluxo login → empresa → política/fornecedor → pedido → recebimento parcial → massa real → reserva/consumo → transferência → contagem/estorno → bloqueio e tentativa de nova reserva. Browser integrado sem ferramenta de execução disponível nesta sessão; mantido Playwright autorizado do projeto.
- `http://localhost:5173`, Chromium desktop 1487×1058 e mobile 390×844. Página identificada, conteúdo real, sem overlay/erro JavaScript relevante e sem overflow horizontal no fluxo. Primeiro layout comprimia dados na grade e tinha muitas abas no celular; seletor de seção e cards ajustados após inspeção de screenshots, sem mudar regras de negócio. Telas usam apenas as grandezas da política, mostram validade/estado e distinguem quantidade sem reserva de liberação operacional.
- Capturas fora do repositório: `/private/var/folders/9v/c9y9j1gx7_b9h0y3jvhfww_80000gn/T/factorycontrol-qa/inventory-{desktop,mobile}.png` e capturas focadas `inventory-values-{desktop,mobile}.png`. Evidências temporárias contêm somente dados de teste fictícios.
- Windows nativo, Safari/iOS real, balança/leitor/impressora físicos e parâmetros sanitários/fiscais continuam sem homologação. Os avisos transitivos de desenvolvimento anteriores não foram forçados/atualizados nesta fase. A Fase 2 não autoriza operação comercial real nem deploy.
- Próximo passo: preparar PR em rascunho, acompanhar a CI e revisar a Fase 2 antes de qualquer integração. Fase 3 somente após autorização específica.


### 2026-11-10 — Publicação e CI da Fase 2

- Entrega publicada no commit [`4c9960792a202f36cd0ad3624e9844c08caa382d`](https://github.com/contatonomaci-pixel/fabric/commit/4c9960792a202f36cd0ad3624e9844c08caa382d), branch `feat/phase-2-inventory`. [PR #2](https://github.com/contatonomaci-pixel/fabric/pull/2) aberto em **rascunho**, base `main`. Nenhum merge, deploy, force-push ou mudança de configuração remota.
- [CI da branch 42842848944](https://github.com/contatonomaci-pixel/fabric/actions/runs/42842848944) e [CI do PR 42844158905](https://github.com/contatonomaci-pixel/fabric/actions/runs/42844158905) concluídas com **success**, quatro jobs cada, no mesmo SHA. Contagens conferidas nos logs da branch:

| Job remoto | Resultado |
|---|---|
| API PHP 8.3 | **58 passaram / 535 assertions**, sem skipped; MySQL 8.4/Redis, concorrência, migration up/down/up e leitura independente de Code128 com ZBar |
| API PHP 8.5 | **58 passaram / 535 assertions**, sem skipped; mesma matriz |
| Web | **19 passaram / 7 arquivos**, lint/typecheck/build aprovados |
| E2E PHP 8.3 | **16 passaram**, 31,7 s; Chromium desktop/mobile e SQLite descartável. E2E MySQL/Redis foi validado localmente, conforme a matriz acima |

- Evidências temporárias: `/private/tmp/factorycontrol-phase2-pr-result.json`, `factorycontrol-phase2-ci-result.json`, `factorycontrol-phase2-{push-ci,ci}-jobs.json` e `factorycontrol-phase2-push-{api83,api85,web,e2e}.log`. Conector GitHub indisponível nesta sessão; publicação pelo transporte Git/API já autenticado. Credencial somente em memória, sem impressão/versionamento.
- Conferência final: **63 arquivos** no [manifesto da Fase 2](22-arquivos-fase-2.md), sendo 42 novos e 21 alterados; **244 arquivos versionados idênticos à pasta local** antes do fechamento documental. Pint final, `git diff --check` e `python3 scripts/validar-pacote.py` passaram (12 documentos essenciais). OpenAPI JSON válido, 91 operações/69 caminhos. Compose original SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f` preservado. Captura focada mobile inspecionada: grandezas, validade e bloqueio legíveis, sem overflow.
- Main remota conferida em `c8dc2008e9ebc4e8de48bd66e77aefff999cc4e9`, ainda na Fase 1. Fechamento documental publicado na própria branch; a CI automática desse fechamento será acompanhada antes da resposta final, sem nova alteração funcional.
- Pendência atual: revisão do PR #2 antes de integração. Limites Windows/Safari/iOS, equipamentos físicos e parâmetros técnicos/fiscais permanecem. Próxima fase funcional recomendada: **Fase 3 — fabricação/OP**, somente após autorização específica; não iniciada nesta entrega.


### 2026-11-10 — Ambiente local persistente para acompanhamento

**Objetivo e operação**
- Usuário solicitou ativar o sistema e entregar login/senha para acompanhar a implementação. Escopo desta continuação: disponibilização local das fases 0–2, verificação de acesso e correções encontradas na ativação. Não iniciado módulo da Fase 3, não integrado PR nem realizado deploy.
- Conferidos `AGENTS.md`, documentos obrigatórios, tarefa 02 e guia de execução. Pasta da IDE continua sem `.git`; comparação com o checkout isolado da branch `feat/phase-2-inventory`, HEAD `0bc9b1301f224409069d4c047fc7b0084155b03a`, estava limpa antes desta sessão. A CI final anterior também foi confirmada no [run 42846680057](https://github.com/contatonomaci-pixel/fabric/actions/runs/42846680057).
- Colima `factorycontrol-phase0` iniciado com `--activate=false`; Compose original `factorycontrol-pro-dev` iniciou MySQL/Redis/Mailpit em loopback. Nenhum volume anterior desse projeto existia. Criados volumes persistentes `factorycontrol-pro-dev_mysql_data` e `factorycontrol-pro-dev_redis_data`; permanecem ligados, pois este é o ambiente solicitado para navegação, diferente dos bancos descartáveis da matriz.
- Preparados `infra/.env` e `apps/api/.env` com acesso exclusivo local, APP_KEY gerada e permissões 0600. `.env` anterior preservado em backup privado fora do projeto. Aplicadas as seis migrations no banco novo `factorycontrol_pro`, porta 3307, seguido de `db:seed` e `DemoSeeder` com senha fornecida somente ao processo. `DEMO_PASSWORD` permanece vazio no arquivo de ambiente; senha da conta é hash no banco e não foi incluída na documentação/código.
- Login: `demo@example.test`, proprietário da empresa **Fábrica Demonstração — FICTÍCIA**. Senha exclusiva entregue no chat a pedido do usuário. Demo contém fábrica/depósito, catálogo, fornecedor/local fictícios; não cria estoque, política física, custo nem validade. API em `127.0.0.1:8000`, SPA em `localhost:5173`, Mailpit em `localhost:8025`; worker Redis mantido ativo. MySQL nativo da porta 3306 não recebeu conexão/migration.
- Processos iniciados em segundo plano, com logs e PIDs em `/private/tmp/factorycontrol-local-runtime/processes.json`; não requerem terminal do usuário aberto. Continuam dependentes do computador/VM ligados e não constituem autostart após reboot. Guia 17 documenta como retomar sem remover dados.

**Correções verificadas**
- Entrada, visão geral e estrutura ainda continham textos de fases anteriores. Corrigidos para compras/estoque disponíveis e adicionados atalhos no painel; sem métricas fictícias ou módulo futuro apresentado como funcional.
- Diagnóstico de `GET /api/v1/me` sem Accept JSON retornou 500, pois o middleware tentava resolver rota Laravel `login` inexistente. Reproduzido em regressão antes da correção (**1 falhou**, 500 em vez de 401). Configurado o destino de convidados para a entrada da SPA; o renderer existente mantém o contrato JSON 401 dos caminhos `/api/*`, mesmo com Accept HTML/genérico. Novo teste cobre `/me` e `/companies` nos dois formatos e cabeçalhos JSON/correlação; passou na suíte final. Não alterada autenticação/permissão nem criado redirecionamento externo fornecido pelo cliente.

**Testes desta ativação**

| Verificação | Resultado |
|---|---|
| Readiness via Vite, liveness da API e Mailpit | HTTP 200; banco/Redis `true`; migrations todas `Ran`; nenhum job falho |
| `php artisan test` com PHP 8.3.33 | **51 passaram / 431 assertions; 8 skipped** esperados do perfil SQLite; não representa nova matriz MySQL/concorrência |
| Pint `--test` | Passou |
| Web lint, typecheck e build | Passaram após ajustes de texto/atalhos |
| Vitest | **19 passaram / 7 arquivos** |
| Smoke Chromium contra banco persistente | **2 fluxos passaram**, desktop 1487×1058 e mobile 390×844, sem movimentos físicos criados |

**QA da navegação**
- Fluxo: entrada → autenticação real com CSRF/cookie → painel → catálogo fictício → fornecedor em compras → posições vazias e local de recebimento → reload autenticado → sair. Browser integrado sem ferramenta `node_repl js` disponível após descoberta; mantido Playwright do projeto, fallback já autorizado para a validação local.

| Checagem visual/funcional | Resultado |
|---|---|
| URL/título e conteúdo | `localhost:5173`, FactoryControl PRO; telas reais renderizadas |
| Framework overlay / console | Ausente; zero erros relevantes nos dois fluxos |
| Sessão | Login HTTP 200, cookie de sessão HttpOnly, reload autenticado e logout verificados |
| Navegação/contexto | Catálogo, fornecedor/local e estado vazio vinculados à empresa de demonstração |
| Responsividade / armazenamento | Sem overflow horizontal; localStorage vazio |
| Evidência visual | Capturas desktop/mobile salvas fora do repositório; painel desktop e estoque mobile inspecionados |

- Script, JSON e capturas temporários em `/private/tmp/factorycontrol-local-runtime/{smoke.mjs,smoke-result.json,dashboard-desktop.png,dashboard-mobile.png,stock-desktop.png,stock-mobile.png}`. Testes de navegador não reaproveitaram o runner que recria banco E2E nem alteraram os saldos de navegação.
- Arquivos versionáveis desta continuação: `apps/api/bootstrap/app.php`, `apps/api/tests/Feature/FoundationTest.php`, `apps/web/src/views/{AccessView,DashboardView,StructureView}.vue`, `docs/17-executar-localmente.md`, `docs/22-arquivos-fase-2.md` e `docs/STATUS.md`. Configurações/credenciais/runtime são locais e ignorados. Alterações ainda não publicadas no PR; o manifesto passa a distinguir esse complemento local da entrega publicada.
- Pendências: revisar/publicar estas correções e acompanhar a nova CI antes de integrar o PR #2. O ambiente permanece ativo para o usuário; acessível somente no mesmo computador. Windows/Safari/iOS real e equipamentos continuam sem homologação; Fase 3 depende de autorização específica.

- Fechamento da ativação: health HTTP 200 com banco/Redis `true`; consultas anônimas reais com Accept `*/*` e `text/html` retornaram JSON 401 após a correção. API/Vite/worker ativos; Compose healthy nas portas previstas e MySQL nativo PID 950 preservado. Comando macOS de Compose direto do guia conferido com `ps`. Evidência: `/private/tmp/factorycontrol-local-runtime/activation-result.json`. `git diff --check` e validação de 12 documentos passaram; `.env` de API/infra confirmados ignorados. SHA-256 original do Compose preservado. Oito arquivos versionáveis alterados nesta sessão; nenhum novo segredo/artefato de runtime entrou no manifesto. Serviços intencionalmente mantidos ligados.


### 2026-11-10 — Revisão técnica da Fase 2

**Escopo e resultado da inspeção**
- Revisão solicitada pelo usuário, seguida de pedido de status. Consultado o roteiro `prompts/REVISAO-TECNICA.md`, tarefa 02, domínio/permissões e código completo de Purchasing/Inventory e seus formulários. Base funcional: main `c8dc2008e9ebc4e8de48bd66e77aefff999cc4e9`; branch publicada até `0bc9b1301f224409069d4c047fc7b0084155b03a`, mais oito arquivos locais da ativação anterior. Esses ajustes foram preservados.
- Analisados: escopo das consultas/vínculos, FKs compostas, índices com NULL, permissões/custos, transações/idempotência, FEFO/qualidade, conversão/precisão, reservas/baixas, estornos, auditoria e formulários. Nenhum novo achado P0/P1 confirmado no escopo; dois achados P2 reproduzidos/corrigidos abaixo. Não se trata de certificação ou revisão de módulos futuros.

| Severidade | Problema reproduzido | Correção e evidência |
|---|---|---|
| **P2** | Dimensão/regra de validade da política podiam mudar com pedido já emitido, enquanto ainda não havia lote. Um pedido em KG podia ficar impossível de receber quando a política passava a contagem e era usada por outro pedido. | `InventoryPolicy::identityLocked()` passa a considerar item de pedido emitido ou lote, inclusive pedido cancelado. `InventoryDirectory` aplica o bloqueio sob o mesmo lock da empresa usado por emissão/recebimento; leitura expõe `identity_locked` para o formulário. Alterar apenas estado ativo continua permitido. Regressão rejeita PATCH após emissão e confirma o indicador, mesmo sem lote. |
| **P2** | Pesagem isolada em política dupla exigia UN para registrar bruto/tara; essa quantidade de unidades não era gravada. Sem UN, retornava 422; com UN, aceitava um valor descartado. | Pesagem isolada acompanha somente massa, não pede/aceita UN e não altera saldos. `InventoryService`, `InventoryWriteRequest` e campos Vue alinhados. Regressão registra massa sem UN, confere saldo/livro inalterados e rejeita UN sem efeito parcial. |

- Correção da hipótese inicial: **a API já impedia converter KG em UN**. O teste de pedido histórico incompatível confirmou 422 no campo quantidade e nenhuma entrada/lote. O achado é a alteração posterior do significado da política/pedido, não estoque criado por conversão indevida. Preservado esse bloqueio, sem migração automática de políticas ou dados de negócio.
- Duas falhas funcionais foram reproduzidas antes da correção. A primeira versão do teste de pedido legado também esperava erro em `unit_id`; a resposta real já era 422 em `quantity`. Essa expectativa foi corrigida para verificar o comportamento existente, sem tratar a diferença de campo como nova vulnerabilidade.
- Três regressões adicionadas ao `InventoryTest`; contrato anterior de pesagem dupla atualizado para não enviar UN. Suíte específica final: **16 passaram / 172 assertions**. Contrato OpenAPI e guia 21 atualizados; regra `prohibited` ganhou mensagem pt-BR. E2E de estoque ampliado para pesagem isolada pelo formulário, ausência de UN e saldo preservado em desktop/mobile.

**Cobertura de integridade revisada**
- Leituras e referências resolvem empresa autorizada; API serializa somente ULIDs públicos e campos permitidos. FKs compostas foram exercitadas diretamente no MySQL. Custos permanecem segregados; histórico de qualidade não expõe compras/contatos. Sem endpoint de concessão de papéis.
- Operações da mesma empresa se serializam por lock transacional; reservas/baixas mantêm saldo e disponível não negativos. Chave/hash por empresa evita efeito duplicado, inclusive com dois atores. Unicidade de estorno tem NULL intencional apenas nos originais, sem impedir múltiplas operações originais.
- Quantidades/custos usam BCMath/DECIMAL; massa real vem de bruto menos tara, sem inferência pelo nominal. FEFO exclui vencidos/bloqueados/quarentena e revalida antes de consumo. Estorno acrescenta compensação; originais preservados, sem reabrir reserva consumida. Contagem administrativa não libera qualidade.

**Validação em andamento e preservação do ambiente**
- Lint/typecheck/build e Vitest passaram; **19 testes SPA / 7 arquivos**. Pint passou. Uma chamada inicial de `artisan test` a partir da raiz não localizou o PHPUnit; repetida corretamente em `apps/api`, sem alteração de aplicação por esse erro de execução.
- Primeira matriz real: **62 testes / 586 assertions em PHP 8.3.33 e 8.5.5**, sem skipped; MySQL 8.4.13/Redis 7.4.12, migrations up/down/up, concorrência, SMTP/reset/worker/readiness aprovados. E2E: **15/16 passaram**; mobile do catálogo perdeu o formulário após abrir depósito. DOM final mostrou retorno à seção de fábricas. Nenhum achado de estoque decorreu dessa falha.
- O teste mobile passou isolado em SQLite. Cópia temporária de testes compartilhava `node_modules` com o Vite ativo; isolado também o cache Vite e habilitado trace em caso de falha. Causa exata da perda de estado inicial não conclusiva; não foi alterado código de catálogo nem relaxados timeouts/retries. Matriz completa repetida com cache/portas separados; resultado final será registrado após conclusão.
- Runner/SPA de teste ficam em `/private/tmp/factorycontrol-phase2-review`; somente adaptados na cópia temporária os endereços/portas 15173/18001 e cache. Código funcional da API é o da pasta principal, com ambiente/processos de teste apontando exclusivamente aos bancos `_test` do projeto Compose aleatório na porta 33307/Redis 26380. Compose original inalterado. Resultados: `matrix-first.json`, `catalog-mobile-recheck.json`, `rerun.log` e `matrix-final.json` nessa pasta.
- Sistema do usuário mantido em `localhost:5173`/API 8000, banco 3307 e worker originais. Nenhum teste usa o banco persistente de navegação, reseta a senha entregue ou remove seus volumes. Nenhum merge, deploy ou início da Fase 3.


**Fechamento local da revisão**
- Matriz final `/private/tmp/factorycontrol-phase2-review/matrix-final.json`: **success=true**, **35 verificações com exit 0**, `temporary_services_stopped=true`. PHP 8.3.33 e 8.5.5: **62 passaram / 586 assertions em cada runtime**, sem skipped. SPA: **19 passaram / 7 arquivos**. E2E MySQL/Redis: **16 passaram**, 1,2 min, sem retries ou aumento de timeout. A falha mobile inicial não se repetiu após o isolamento do cache; causa original da perda de estado não foi demonstrada conclusivamente e permanece registrada acima.
- Incluídos migrations up/down/up em cada runtime, integridade/FKs, concorrência com dois atores, idempotência, leitura independente de código, SMTP/reset/revogação, worker e readiness 503 quando somente o Redis de teste é desligado. Lint/typecheck/build, Pint e Composer validate aprovados. OpenAPI JSON e seus campos de política/pesagem conferidos; mantidas 91 operações/69 caminhos. Validação documental (12 arquivos essenciais) e diff sem whitespace inválido aprovados.
- QA no endereço temporário `localhost:15173`, desktop 1487×1058 e mobile Chromium 390×844. Fluxos reais de pedido/recebimento/pesagem isolada/reserva/consumo/transferência/contagem/estorno/bloqueio. Capturas `qa/inventory-values-{desktop,mobile}.png` inspecionadas; saldos, validade, estado e layout legíveis. Asserção E2E confirma ausência do campo UN na pesagem, registro bem-sucedido e saldos idênticos antes/depois. Sem overlay, tela vazia ou erro JavaScript relevante no fluxo. Browser integrado continua sem ferramenta de execução disponível; usado Playwright já autorizado.
- A revisão altera **19 arquivos** desde o último commit publicado, incluindo os oito da ativação anterior. [Manifesto global](22-arquivos-fase-2.md): **69 arquivos** contra a base da Fase 1, 42 criados/27 alterados. Sem nova dependência, migration, mudança do Compose ou do banco persistente. Senhas/ambiente/logs/capturas permanecem fora dos arquivos versionados.
- Próximo passo nesta entrega: publicar o snapshot revisado no PR #2, manter o rascunho e conferir a nova CI. Main permanece na Fase 1. Nenhum módulo de produção/financeiro/GTIN/RCN/BI ou Fase 3 foi iniciado; homologação Windows/Safari/iOS e hardware mantém os limites anteriores.


**Publicação da revisão e CI aprovada**
- Correções e oito arquivos anteriores da ativação publicados juntos no commit [`59a9739e407347648b20161f09208665d184908c`](https://github.com/contatonomaci-pixel/fabric/commit/59a9739e407347648b20161f09208665d184908c), **19 arquivos alterados**, no [PR #2 em rascunho](https://github.com/contatonomaci-pixel/fabric/pull/2). Nenhum merge/deploy. Main conferida em `c8dc2008e9ebc4e8de48bd66e77aefff999cc4e9`.
- [CI do PR 42943822617](https://github.com/contatonomaci-pixel/fabric/actions/runs/42943822617): **completed/success**, head `59a9739e407347648b20161f09208665d184908c`, quatro jobs aprovados. Logs conferidos: **62 testes / 586 assertions em PHP 8.3 e em PHP 8.5**, sem skipped, MySQL 8.4/Redis; **19 testes SPA / 7 arquivos**, lint/typecheck/build; **16 E2E**, 38,8 s, Chromium desktop/mobile no SQLite descartável da CI. E2E MySQL/Redis consta da matriz local final, não do job remoto.
- Evidências em `/private/tmp/factorycontrol-phase2-review/{ci-result.json,ci-jobs.json,ci-api83.log,ci-api85.log,ci-web.log,ci-e2e.log}`. Descrição do PR reescrita para o comportamento final e os limites. Transporte Git/API já autenticado, pois o conector estava indisponível; nenhuma credencial impressa, versionada ou enviada ao repositório.
- Conferidos **244 arquivos versionados idênticos** à pasta local e ausência das senhas/APP_KEY locais em todos eles; checkout isolado limpo. Manifesto global: 69 arquivos contra a Fase 1. Compose original SHA-256 `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f` preservado. Fechamento exclusivamente documental publicado na mesma branch, com sua CI automática acompanhada antes da resposta final.
- Ambiente do usuário revalidado: `http://localhost:5173/api/v1/health` HTTP 200, banco e Redis saudáveis; API PID 698, Vite 699 e worker 700 ativos. Os três containers `factorycontrol-pro-dev` continuam ligados; somente os projetos/volumes descartáveis dos testes foram removidos. Login e senha entregues anteriormente não foram redefinidos. Evidência adicional: `live-health.json` na pasta temporária da revisão.
- **Conclusão:** dois achados P2 desta revisão corrigidos e cobertos por regressões; nenhum bloqueio técnico adicional confirmado no escopo examinado. Próximo passo: revisão humana/integração do PR #2. Fase 3 recomendada somente após autorização específica. Windows/Safari/iOS real, equipamentos e parâmetros técnicos/fiscais permanecem sem homologação. Main/produção preservadas.


### 2026-11-10 — Segunda revisão da Fase 2

**Escopo e achados reproduzidos**
- Nova revisão solicitada pelo usuário. Snapshot inicial `08ba8683b9f2b795bc3541525c4380afdfc17bc5`, sem alterações locais, main preservada. Reexaminados limites por política física, entradas incompatíveis, preservação de saldos/auditoria e apresentação de movimentos. Correções anteriores de política após pedido e pesagem isolada permanecem presentes. Sistema persistente do usuário mantido ativo.

| Severidade | Reprodução antes da correção | Correção aplicada |
|---|---|---|
| **P2** | `consume` de reserva com política de contagem aceitava `measurements` da própria alocação, inclusive bruto menor que tara. Respondia **201**, consumia UN e descartava os pesos, sem registro de pesagem. | `InventoryService::finishReservation` rejeita pesagens de alocações de contagem com **422**. Reserva/saldo/livro/chave de idempotência não são alterados pela falha. A solicitação corrigida, sem pesos, pode usar a mesma chave e consumir normalmente. |
| **P3** | Colunas `units_delta` e `grams_delta` escapavam da formatação pt-BR: exibiam `-1.000000`/`-1250.500000`, enquanto outras quantidades usavam vírgula. | `InventoryPanel` também formata os campos com sufixo `_delta`, preservando sinal/casas e sem converter o valor para float. |

- Regressões reproduziram **uma falha de API (201 em vez de 422)** e **uma falha de componente (ponto em vez de vírgula)** antes das alterações. Depois da correção: suíte de estoque **17 testes / 192 assertions**; Vitest **20 testes / 7 arquivos**; Pint, lint, typecheck e build passaram.
- Teste de API cobre pesos incompatíveis com tara maior ou menor que o bruto, ausência de efeitos parciais, reserva ativa/saldo retido e uso posterior da mesma chave com payload corrigido. Teste de componente verifica valores negativos em UN e gramas, com seis casas. E2E existente ampliado para abrir Movimentos após consumo, conferir `-500,000000` e capturar a linha nos dois viewports.
- Arquivos de código: `apps/api/app/Domain/Inventory/InventoryService.php`, `apps/api/tests/Feature/InventoryTest.php`, `apps/web/src/components/InventoryPanel.vue`, `apps/web/src/components/InventoryPanel.test.ts` e `apps/web/e2e/inventory.spec.ts`. Guia 21 e OpenAPI passaram a explicitar que alocação de contagem não aceita pesagem. Sem mudança de schema, dependência, permissão, Compose ou módulo futuro.
- Matriz completa MySQL/Redis/PHP 8.3/8.5 e E2E iniciada em bancos/portas/cache isolados, sem usar nem reiniciar o ambiente do usuário. Evidências desta rodada: `/private/tmp/factorycontrol-phase2-review/{matrix-second-review.json,second-review.log,qa-second/}`. Contagens da matriz/CI serão registradas após a conclusão; resultados anteriores não substituem esses checks.


**Fechamento local da segunda revisão**
- Matriz `matrix-second-review.json`: **success=true**, **35 checks com exit 0**, `temporary_services_stopped=true`. MySQL 8.4.13/Redis 7.4.12 com **63 testes / 606 assertions em cada PHP (8.3.33 e 8.5.5)**, sem skipped; **20 SPA / 7 arquivos** e **16 E2E**, 1,3 min. Inclui concorrência, migrations up/down/up, integridade, idempotência e smokes herdados de SMTP/reset/worker/readiness. Pint e Composer validate passaram.
- QA da apresentação em `localhost:15173`, desktop 1487×1058 e mobile Chromium 390×844. Fluxo de consumo → Movimentos → Posições: variação `-500,000000` exibida e saldo físico correto preservado. Capturas `qa-second/movement-{desktop,mobile}.png` inspecionadas; sem overlay/erro JavaScript ou overflow no fluxo. Browser integrado sem ferramenta de execução disponível; mantido Playwright autorizado com portas/cache isolados.
- OpenAPI JSON atualizado e válido; 91 operações/69 caminhos preservados. Manifesto global continua com 69 arquivos contra a Fase 1 (42 criados/27 alterados), pois esta revisão altera arquivos já presentes. Oito arquivos alterados desde `08ba868`: cinco de código/testes, guia 21, OpenAPI e STATUS. Sem segredos/artefatos de QA no diff; Compose e dados de navegação preservados.
- Próximo passo desta revisão: publicar as correções no PR #2 em rascunho e conferir a CI do novo snapshot. Sem merge, deploy ou autorização/início de Fase 3. As limitações Windows/Safari/iOS real e equipamentos permanecem.


**Publicação e CI da segunda revisão**
- Snapshot [`6f2ed4c73521a974a5cbf466b59246bb07f0b8a2`](https://github.com/contatonomaci-pixel/fabric/commit/6f2ed4c73521a974a5cbf466b59246bb07f0b8a2) publicado no PR #2 existente, ainda em rascunho. Oito arquivos alterados; nenhum arquivo novo. [CI do PR 42948312256](https://github.com/contatonomaci-pixel/fabric/actions/runs/42948312256) e [CI do push 42948305148](https://github.com/contatonomaci-pixel/fabric/actions/runs/42948305148) concluídas com success no mesmo SHA.
- Quatro jobs e logs do PR conferidos: **63 testes / 606 assertions em cada PHP (8.3/8.5)** com MySQL 8.4/Redis, sem skipped; **20 testes SPA / 7 arquivos**, lint/typecheck/build; **16 E2E**, 35,4 s, Chromium desktop/mobile em SQLite descartável. Matriz local final também executou os 16 E2E com MySQL/Redis reais. Nenhum retry ou timeout foi ampliado.
- Evidências em `/private/tmp/factorycontrol-phase2-review/{ci-second-runs.json,ci-second-jobs.json,ci-second-api83.log,ci-second-api85.log,ci-second-web.log,ci-second-e2e.log}`. Descrição do PR atualizada para o comportamento final. Fechamento documental publicado na mesma branch e sua CI acompanhada antes da resposta final, sem nova alteração funcional.
- Confirmados 244 arquivos locais idênticos ao snapshot, manifesto global exato de 69 arquivos e checkout Git isolado limpo. `python3 scripts/validar-pacote.py` e `git diff --check` passaram; Compose SHA-256 original `51696b75bdf9bbe66af4e54e62acf8b4df50936fcbe5e8d867bd35bcf53dd50f` preservado. Credenciais, ambientes e artefatos de QA não foram versionados.
- Readiness do sistema do usuário retornou HTTP 200 com banco/Redis saudáveis; API/Vite/worker continuam ativos. Banco persistente e senha não foram redefinidos; somente serviços/volumes descartáveis foram removidos ao terminar os testes. Evidência: `live-second-health.json` na pasta temporária da revisão.
- **Resultado:** um novo achado P2 de validação/auditoria e um P3 de apresentação corrigidos e cobertos por regressões; correções anteriores preservadas. Nenhum novo bloqueio confirmado nas áreas reexaminadas. Main permanece em `c8dc2008e9ebc4e8de48bd66e77aefff999cc4e9`; próximo passo é revisão humana/integração do PR #2. Sem merge/deploy/Fase 3 nesta sessão. Limites Windows/Safari/iOS, equipamentos e parâmetros técnicos/fiscais mantidos.
