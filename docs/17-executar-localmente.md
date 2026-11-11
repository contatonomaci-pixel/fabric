# Executar localmente no Windows / VS Code e Unix

A aplicação contém autenticação, recuperação de senha, estrutura/catálogo da Fase 1 e compras/estoque da Fase 2. Uso e atualização: [Catálogo](19-catalogo-fase-1.md) e [Compras/estoque](21-compras-estoque-fase-2.md). Produção, vendas e financeiro não estão implementados. Todo dado de demonstração é fictício e opcional.

## Ambiente ativado nesta máquina — 2026-11-10

Disponível em **http://localhost:5173/entrar**, no mesmo computador. Conta `demo@example.test`, proprietária de **Fábrica Demonstração — FICTÍCIA**. A senha exclusiva foi entregue ao usuário no chat; não consta neste guia nem nos seeders. Catálogo, fornecedor e local fictícios foram criados; não foram inventados saldos, custos, política de validade ou aprovação técnica.

API, Vite e worker foram iniciados em segundo plano. O Vite acompanha alterações da interface; a API lê o código local. MySQL 8.4/Redis/Mailpit usam o Compose original e volumes persistentes, nas portas 3307/6380/8025 (SMTP 1025). O MySQL preexistente da porta 3306 foi preservado. Configurações próprias em `infra/.env` e `apps/api/.env`, ignoradas pelo Git. Não copie essas credenciais para ambientes remotos.

Após reiniciar o computador, suba os serviços novamente. Nesta instalação macOS, a partir da raiz:

```bash
PATH="/opt/homebrew/opt/docker/bin:/opt/homebrew/opt/colima/bin:$PATH" /opt/homebrew/opt/colima/bin/colima start --profile factorycontrol-phase0 --activate=false
DOCKER_HOST=unix:///Users/mauriciosilva/.colima/factorycontrol-phase0/docker.sock /opt/homebrew/opt/docker-compose/bin/docker-compose -f infra/compose.yaml --env-file infra/.env up -d --wait
```

Depois, em terminais separados, use `/opt/homebrew/opt/php@8.3/bin/php artisan serve --host=127.0.0.1 --port=8000` e `/opt/homebrew/opt/php@8.3/bin/php artisan queue:work redis --tries=3 --timeout=60` em `apps/api`; `npm run dev` em `apps/web`. Não inicie cópias adicionais enquanto os processos atuais estiverem ativos. Os comandos gerais abaixo atendem outras instalações.

Para recuperar o acesso, use **Esqueci minha senha** e consulte o Mailpit em **http://localhost:8025**. O DemoSeeder preserva a senha de uma conta já existente. Os dados deste ambiente são de navegação e não devem ser usados como banco dos testes; a suíte padrão continua isolada em SQLite. Não execute `migrate:fresh` nem remova os volumes para reiniciar a aplicação.

## Pré-requisitos

- PHP 8.3–8.5 e Composer 2. Extensões: ctype, curl, dom, fileinfo, filter, hash, intl, mbstring, openssl, pcre, PDO, pdo_mysql, session, tokenizer, xml, bcmath e **gd** (PNG/PDF). Para testes rápidos/E2E: pdo_sqlite/sqlite3.
- Node.js 22.13+ com npm (recomendado Node 22 LTS para reproduzir esta entrega).
- Docker Desktop com plugin Compose (ou Docker/Colima no macOS) para os serviços fornecidos. Sem Docker, disponibilize **MySQL 8.4** e Redis localmente e ajuste somente seus `.env`.
- VS Code com Vue Official, PHP Intelephense e ESLint; sugestões em `.vscode/extensions.json`.
- Git é recomendado. A Fase 1 está na `main` pelo [PR #1](https://github.com/contatonomaci-pixel/fabric/pull/1). A Fase 2 está na branch `feat/phase-2-inventory`, [PR #2 em rascunho](https://github.com/contatonomaci-pixel/fabric/pull/2); para essa entrega em revisão, use `git clone --branch feat/phase-2-inventory https://github.com/contatonomaci-pixel/fabric.git`. Após sua integração, o clone normal da main também a conterá. Não sobrescreva alterações locais ao mudar de branch. A pasta original desta sessão foi recebida sem `.git`; a publicação usa checkout separado, sem substituir seus arquivos locais.

Abra a pasta raiz no VS Code. Use terminais separados para API, SPA e worker. Os comandos PHP/npm são iguais em PowerShell e Unix; os comandos de cópia e variáveis diferem, conforme abaixo.

## 1. Serviços locais — terminal na raiz

PowerShell:

```powershell
Copy-Item infra/.env.example infra/.env
```

Unix:

```bash
cp infra/.env.example infra/.env
```

Edite `infra/.env` no VS Code. Defina senhas **locais próprias** para `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`. Depois:

```bash
docker compose -f infra/compose.yaml --env-file infra/.env up -d
docker compose -f infra/compose.yaml --env-file infra/.env ps
```

O `infra/compose.yaml` original foi preservado. Portas: MySQL 3307, Redis 6380, SMTP 1025 e Mailpit http://localhost:8025. As portas estão vinculadas ao loopback.

## 2. API — terminal em apps/api

```bash
composer install
```

Copie `.env.example` para `.env` (`Copy-Item .env.example .env` no PowerShell; `cp .env.example .env` no Unix). Preencha `DB_PASSWORD` com o mesmo valor local de `MYSQL_PASSWORD` da infraestrutura. Mantenha `DB_PORT=3307`, `REDIS_PORT=6380`, `REDIS_CLIENT=predis` e `SESSION_DRIVER=database`.

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

`db:seed` instala papéis/permissões e unidades padrão das empresas existentes. Proprietário gerencia sua estrutura/catálogo; auditor consulta; operador não recebe administração. Não cria usuários nem senha padrão. Não há registro público ou edição administrativa de papéis nesta fase.

Para acessar com dados fictícios, pare temporariamente o servidor ou use outro terminal em `apps/api`, defina `DEMO_PASSWORD` no `.env` com uma senha local de pelo menos 12 caracteres e execute:

```bash
php artisan db:seed --class=DemoSeeder
```

Conta: `demo@example.test`, empresa: `Fábrica Demonstração — FICTÍCIA`. A senha é a que você definiu, armazenada com hash. O seeder é idempotente e não redefine a senha de um usuário existente; somente funciona em `local/testing`. Remova `DEMO_PASSWORD` do `.env` depois de criar a conta. A Fase 1 acrescenta fábrica/depósito e os exemplos de catálogo fictícios, com preços não definidos.

## 3. SPA — outro terminal em apps/web

```bash
npm ci
npm run dev
```

Abra **http://localhost:5173**. Use sempre `localhost` no navegador; não misture com `127.0.0.1`, pois os cookies pertencem ao host. O Vite encaminha `/api` e `/sanctum` à API local. Os servidores recusam ocupar uma porta já usada no E2E; pare outra execução antes dos testes.

Fluxo: entrar → painel sem dados → consultar empresas autorizadas → sair. Recuperação: `Esqueci minha senha` → informar e-mail → abrir e-mail no Mailpit → definir nova senha. Tokens expiram em 60 minutos, são de uso único e o reset invalida sessões armazenadas no banco. Nenhum token de acesso é armazenado em localStorage.

O reset funciona pelo Mailpit com a conta fictícia: o destinatário `.test` não recebe correio externo. Se o SMTP estiver desligado, a API retorna erro e o log deve ser inspecionado localmente. Nunca use `MAIL_MAILER=log` com dados reais.

## 4. Worker e diagnóstico

Em outro terminal em `apps/api`:

```bash
php artisan queue:work redis --tries=3 --timeout=60
php artisan queue:failed
```

Não foram criados jobs de negócio antecipados. Filas/cache apontam para Redis; sessões ficam no MySQL para revogação no reset. O limitador usa arquivo local para continuar respondendo mesmo quando Redis está indisponível (`CACHE_LIMITER=file`); rever o armazenamento compartilhado antes de escalar a aplicação.

- `GET http://localhost:8000/up`: liveness do processo Laravel.
- `GET http://localhost:8000/api/v1/health`: readiness real, retorna 200 somente com banco **e** Redis acessíveis, 503 caso contrário; nunca retorna credenciais ou erro da conexão.
- Logs locais: `apps/api/storage/logs/laravel.log`; correlacione o `request_id` do JSON com `X-Request-ID` e o contexto do log. Não compartilhar `.env`, senha ou token de reset.
- `GET /api/v1/me`: 401 sem sessão; 403 para usuário desativado; empresas alheias ou sem permissão retornam 403.
- Contrato: [OpenAPI](api/openapi.json). Toda API usa `Accept: application/json`. Cookies de sessão HttpOnly, SameSite=Lax e sessão regenerada no login. O cliente inicializa `/sanctum/csrf-cookie` e envia `X-XSRF-TOKEN` nos POSTs. Os endpoints de sessão estão no grupo web uma única vez para evitar duplicar middleware de cookies/sessão.

## 5. Qualidade

Em `apps/api`:

```bash
composer validate --strict
composer test
vendor/bin/pint --test
```

No Windows, se o shell não resolver o proxy do Pint, use `php vendor/laravel/pint/builds/pint --test`.

A suíte padrão utiliza SQLite em memória e não acessa o banco de desenvolvimento. Os três testes de infraestrutura real e os cinco de concorrência MySQL (catálogo e estoque) são marcados **skipped** nessa execução; não confundir com homologação MySQL.

Em `apps/web`:

```bash
npm run lint
npm run typecheck
npm test
npm run build
npx playwright install chromium
npm run test:e2e
```

E2E inicia seus próprios servidores nas portas 8000/5173, gera senha/chave efêmeras e SQLite em diretório temporário, usa dados fictícios e limpa o banco ao encerrar. Nunca utiliza o banco de desenvolvimento. Os testes verificam login, cookies HttpOnly/CSRF, persistência após reload, empresas, logout, recuperação e páginas de erro em desktop/mobile. Chromium emula celular; isso não comprova Safari/iOS real. O build gera `apps/web/dist`; não publica nada. O worker não é necessário para E2E.

## 6. Homologação em MySQL 8.4 de teste

Crie **um banco local separado**, por exemplo `factorycontrol_pro_test`, com charset `utf8mb4`, e conceda acesso ao usuário local configurado. O usuário criado pelo Compose recebe acesso inicialmente apenas ao banco de desenvolvimento; a concessão do banco de testes precisa ser feita pelo administrador local. Não usar o banco de desenvolvimento: `RefreshDatabase` apaga/recria as tabelas do banco selecionado.

No PowerShell, em `apps/api`:

```powershell
$env:TEST_DB_CONNECTION="mysql"
$env:TEST_DB_DATABASE="factorycontrol_pro_test"
composer test
Remove-Item Env:TEST_DB_CONNECTION
Remove-Item Env:TEST_DB_DATABASE
```

Unix:

```bash
TEST_DB_CONNECTION=mysql TEST_DB_DATABASE=factorycontrol_pro_test composer test
```

Host/porta/usuário/senha continuam vindo do `.env` local. O runner recusa nomes de banco de teste que não terminam em `_test` e ignora `DB_URL` herdada para impedir que uma URL sobreponha o banco isolado. Redis deve estar acessível para o teste real de infraestrutura. Verifique ainda up/down das migrations **somente** no banco separado, definindo `DB_DATABASE=factorycontrol_pro_test` temporariamente:

```bash
php artisan migrate
php artisan migrate:rollback
php artisan migrate
```

Restaure `DB_DATABASE` ao valor de desenvolvimento antes de subir a API. O CI fornecido automatiza up/down/up e a suíte real em MySQL 8.4/Redis, PHP 8.3 e 8.5, além de lint/build/testes da SPA e E2E. O workflow está publicado em `.github/workflows/quality.yml` e executa em eventos `push`/`pull_request`; resultados verificáveis em [GitHub Actions](https://github.com/contatonomaci-pixel/fabric/actions), vinculados ao SHA de cada commit.

## 7. Repetir a homologação nativa descartável (macOS)

A sessão de 2026-11-07 executou a matriz PHP 8.3.33/8.5.5 com MySQL 8.4.11, Redis 8.10.1 e Mailpit 1.31.0. O roteiro não instala ferramentas: informe caminhos de binários já disponíveis. Serviços existentes não são reiniciados; as portas de teste precisam estar livres.

Com os caminhos instalados nesta máquina, a partir da raiz:

```bash
python3 scripts/homologar-fundacao.py \
  --php /opt/homebrew/opt/php@8.3/bin/php \
  --php /opt/homebrew/bin/php \
  --mysql-bin /opt/homebrew/opt/mysql@8.4/bin \
  --redis-server /opt/homebrew/opt/redis/bin/redis-server \
  --mailpit /opt/homebrew/opt/mailpit/bin/mailpit \
  --mysql-library-path /opt/homebrew/Cellar/protobuf/36.0/lib:/opt/homebrew/Cellar/abseil/20260817.0/lib
```

O último argumento é específico das bibliotecas desta instalação Homebrew e afeta somente os processos MySQL do roteiro. Ajuste/remova conforme a instalação. Não altere os links globais de bibliotecas para reproduzir um teste; valide a compatibilidade de outros serviços antes de instalar/atualizar dependências.

O roteiro cria MySQL em 33307, Redis em 26380, Mailpit SMTP em 11025/HTTP em 18025 e API de smoke em 18000, sempre no loopback, além do E2E 8000/5173. Bancos e senhas são descartáveis. Ele executa migrations up/down/up, API nos dois runtimes, worker real, envio/captura do reset pelo Mailpit, revogação da sessão, token de uso único, rejeição da senha anterior, login com nova senha, readiness 503 com Redis desligado e os comandos web/E2E. Ao terminar, remove o ambiente e grava a evidência sem credenciais em `/private/tmp/factorycontrol-homologation.json`.

O E2E usa MySQL somente quando recebe `FACTORYCONTROL_E2E_PROFILE` criado pelo roteiro; por padrão continua SQLite. Não apontar perfis ao banco de desenvolvimento. Até a Fase 2, SQLite deixa skipped os três testes de infraestrutura e os cinco de concorrência; na matriz MySQL todos os testes devem passar. Contagens atuais no STATUS.

Essa execução nativa é distinta da homologação adicional do Compose com Redis 7 descrita abaixo. Nenhuma delas substitui a execução remota do GitHub Actions. Veja os resultados mais recentes e limites no STATUS. Para validar o contrato sem alterar dependências da aplicação:

```bash
npx --yes @redocly/cli@2.76.0 lint docs/api/openapi.json --extends minimal
```

Dois avisos sobre URLs localhost são esperados na documentação do ambiente local.

## 8. Repetir a homologação descartável com Compose (macOS)

Com Docker local ativo e as dependências PHP/npm já instaladas, o mesmo roteiro usa o Compose original. Informe explicitamente o socket Unix do seu runtime local e os binários PHP, incluindo obrigatoriamente PHP 8.3. Exemplo com Docker Desktop e caminhos PHP desta máquina; ajuste o socket se o runtime usar outro caminho:

```bash
python3 scripts/homologar-fundacao.py \
  --compose \
  --docker-host unix:///var/run/docker.sock \
  --php /opt/homebrew/opt/php@8.3/bin/php \
  --php /opt/homebrew/bin/php \
  --result /private/tmp/factorycontrol-compose-homologation.json
```

Se o CLI Docker não estiver no PATH, informe `--docker /caminho/para/docker`. Se usar configuração privada para localizar o plugin Compose, informe `--docker-config /caminho/da/configuracao`. Não são necessários MySQL, Redis ou Mailpit nativos neste modo. O roteiro cria um projeto com nome aleatório, volumes novos e as portas de teste da seção 7; verifica MySQL 8.4 e Redis 7 efetivos antes da matriz. Nenhum `.env` do projeto é alterado. Ao encerrar, remove **somente** os containers, rede e volumes que acabou de criar, e registra o resultado da limpeza.

A homologação desta máquina usou Colima com socket `unix:///Users/mauriciosilva/.colima/factorycontrol-phase0/docker.sock`, Docker `/opt/homebrew/opt/docker/bin/docker` e configuração privada `/private/tmp/factorycontrol-docker-config`. O perfil Colima `factorycontrol-phase0` foi desligado ao concluir; requer inicialização antes de reutilizar seu socket. A configuração em `/private/tmp` é temporária e pode precisar ser recriada; não é requisito para Docker Desktop. Nenhum contexto global foi ativado.

Em 2026-11-07, foram executados Docker 29.7.2/Compose 5.5.0, MySQL 8.4.13, Redis 7.4.12 e Mailpit 1.33.0: **35 verificações passaram**, incluindo 23 API/111 assertions em cada runtime PHP, 8 SPA e 10 E2E. Tags móveis podem resolver versões diferentes no futuro; o roteiro registra as versões/IDs realmente utilizados. O guia normal do Windows permanece nas seções 1–6; este roteiro de automação usa caminhos e comandos do macOS.

CI remota: após autorização explícita, o pacote da Fase 0 foi publicado em `https://github.com/contatonomaci-pixel/fabric.git`, branch `main`. A primeira execução identificou que `tests/Unit` estava vazia e não entrou no Git; `.gitkeep` preserva agora essa pasta em clones. A [execução do commit corrigido `10c54a5`](https://github.com/contatonomaci-pixel/fabric/actions/runs/42339096676) passou nos quatro jobs: API PHP 8.3/8.5 com MySQL/Redis (23 testes em cada), web (8 testes) e E2E (10 testes em SQLite descartável). Veja no STATUS a distinção entre CI e homologação local com Mailpit/Compose.

## Limites desta entrega

Disponíveis os cadastros da Fase 1 e compras/estoque da Fase 2, conforme os guias específicos e a branch em revisão indicada acima. Sem produção, vendas, financeiro, GTIN/RCN ou BI. A navegação não apresenta módulos futuros como funcionais. Marca, regras técnicas e homologação física continuam pendentes; a convenção de IDs da fundação foi mantida. Nenhum deploy foi realizado.

Em eventual ambiente HTTPS futuro, `SESSION_SECURE_COOKIE=true`, domínios first-party e `FRONTEND_URL` devem corresponder à topologia validada. A configuração HTTP local não é uma configuração de produção; produção permanece fora desta fase.
