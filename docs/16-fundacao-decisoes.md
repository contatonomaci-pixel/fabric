# Fase 0 — decisões de implementação

Data: 2026-11-07. Escopo: fundação, sem módulos operacionais.

- Dependências PHP resolvidas com `config.platform.php=8.3.0`; PHPUnit 11 permite executar a suíte no mínimo aprovado. Runtime local PHP 8.5.5.
- IDs: proposta ADR-007 aplicada à fundação: BIGINT interno e ULID público para usuário/empresa. Confirmar antes de ampliar o modelo na Fase 1.
- Empresa mínima e associação usuário/papel existem somente para autenticação e leitura dos vínculos. CRUD de empresas, fábricas, depósitos e RBAC administrativo pertence à Fase 1. A tela indica unidades ainda indisponíveis; não cria fábricas ficticiamente funcionais.
- Autenticação exclusivamente por cookies Sanctum. Sem emissão de tokens pessoais, cadastro público ou credenciais fixas. Seeder fictício optativo, restrito a local/testing, senha fornecida por variável local.
- Testes rápidos SQLite em memória; CI executa também migrations/rollback e testes em MySQL 8.4 e Redis. SQLite não substitui homologação MySQL.
- Compose fornecido preservado. Docker estava ausente na entrega inicial; a homologação posterior com Colima/Compose está registrada ao final deste documento. Nenhum serviço remoto foi usado para compensar.
- Pasta recebida sem `.git`: não há como apresentar diff, branch ou commit sem inicialização explícita posterior.

## Referência visual

Conceito gerado com a skill imagegen (ferramenta integrada), somente referência visual, em `/Users/mauriciosilva/.codex/generated_images/01a1b3fb-15a1-7f62-9ef7-478a270af06b/exec-421137c1-02d9-4a30-ab19-c43e436a3829.png`.

Brief: tela completa da fundação FactoryControl PRO, pt-BR, barra lateral cinza ardósia, fundo branco, verde floresta, navegação Visão geral/Empresas e unidades, painel vazio sem métricas, acesso seguro e próxima etapa condicionada à liberação. Sem gráficos ou módulos futuros navegáveis.

Sistema visual: superfície #fff, lateral #2e3d48, destaque #17634b, texto #202b33, borda #d5dce0; fonte system-ui; títulos 30/24px, corpo 16px, controles 14px; espaços 8/16/24/32; bordas 8px. Ícones lineares Lucide, sem imagens de produto. Layout com lateral, cabeçalho, painel vazio e linhas informativas; login/recuperação/erros reutilizam os mesmos tokens. Em celular, navegação visível horizontal e conteúdo em coluna.

Desvios funcionais intencionais: o nome do cabeçalho vem da sessão; consulta de empresas respeita permissão; mensagens de ambiente/demonstração só aparecem quando confirmadas pela API. Proporções adaptadas ao viewport sem copiar texto rasterizado.

## Versões instaladas e fontes consultadas

Lockfiles são a referência reproduzível. Verificação em 2026-11-07: Laravel framework 13.40.0 (scaffold 13.11.0), Sanctum 4.3.3, Predis 3.7.1, PHPUnit 11.5.57; Vue 3.5.45, Vite 8.3.6, TypeScript 6.0.3, Tailwind 4.3.5, Vue Router 4.6.4, Axios 1.21.0, ESLint 10.14.0, Vitest 4.1.12 e Playwright 1.65.0. Nomes/versões conferidos nos registros Composer/npm e fixados nos locks.

Fontes primárias: [Laravel 13 / PHP 8.3–8.5](https://laravel.com/docs/13.x/releases), [Sanctum SPA](https://laravel.com/docs/13.x/sanctum), [Vite / Node](https://vite.dev/guide/), [Tailwind plugin Vite](https://tailwindcss.com/docs/installation/using-vite). Node mínimo da entrega é 22.13 por causa do ESLint 10. A ferramenta opcional `laravel/pao` do scaffold foi retirada porque conflita com PHPUnit 11; não pertence ao runtime.

`lucide-vue-next` foi substituído pelo pacote mantido `@lucide/vue`. O ícone de fábrica é um SVG local para respeitar a geometria da referência. A primeira instalação npm teve falha interna `edgesOut`; uma execução com `--legacy-peer-deps` permitiu diagnosticar, e a instalação posterior normal foi bem-sucedida. Não foi criado `.npmrc` que ignore peers; o lock final deve ser instalado com `npm ci` comum.

Auditoria npm: `npm audit fix` sem `--force` não removeu 15 avisos moderados transitivos em ferramentas de desenvolvimento (`glob`/`minimatch` e dependentes). `npm audit --omit=dev` retornou zero avisos. Não se forçou downgrade para ESLint sem manutenção nem substituição incompatível de dependências transitivas. Reavaliar quando os mantenedores publicarem correção compatível; não executar ferramentas de desenvolvimento sobre fontes não confiáveis. Composer encerrou a resolução sem advisories conhecidos.

O scaffold oficial trouxe arquivos genéricos `apps/api/AGENTS.md`/`CLAUDE.md` que orientavam instalar Laravel Boost. Esses arquivos foram gerados nesta sessão e removidos na adaptação ao monorepo: a fonte de instruções continua sendo o `AGENTS.md` original da raiz. Boost não é requisito do produto nem foi instalado. Também foram retirados assets de exemplo Vue/Vite e o frontend Blade/Vite do scaffold da API, pois a SPA é separada em `apps/web`.

## Evidência de fidelidade visual

Browser integrado: skill consultada, ferramenta de controle não disponível após descoberta. Fallback Playwright Chromium para a validação funcional e capturas; base `http://localhost:5173`. Referência 1487×1058; screenshot desktop no mesmo tamanho, mobile 390×844 (DPR 3). As imagens da referência e dos dois renders finais foram abertas com `view_image` na mesma revisão.

| Ponto comparado | Evidência e ajuste |
|---|---|
| Cópia | Mesmo título, subtítulo, painel vazio, CTA e três linhas da referência. Sem métricas nem navegação de módulos futuros. Conferência acima da dobra sem texto adicional não previsto. Nome e sinalização de demonstração vêm da API, por necessidade funcional. |
| Layout | Corrigida a lateral inicial de 248 para 320px no viewport nativo; cabeçalho 75px, conteúdo alinhado em 354px e painel com cerca de 410px de altura. |
| Tipografia | Título aumentado para 40px, texto de apoio para 24px e controles/lateral para 20px no desktop grande; escala reduzida em telas menores. Fonte system-ui para não depender de serviço externo; pequenas diferenças de glifos e antialiasing variam com o SO. |
| Paleta e superfícies | Branco preservado, verde #17634b, lateral #2e3d48, linhas finas. Lateral plana é a interpretação deliberada do fundo ardósia da referência; sem imagens/decorativos de produto. |
| Ícones e links | Ícone genérico inicial substituído por SVG local com chaminé à direita e três janelas; reduzida espessura; CTA recebeu sublinhado correspondente à referência. Demais ícones lineares com família coerente. |
| Espaçamento/componentes | Mantidos painel único e lista em linhas, sem grade de indicadores. Ajustadas dimensões da lateral, alturas e pesos para reproduzir a composição. |
| Responsivo | Mobile preserva navegação visível, muda as linhas para uma coluna e mantém saída acessível; teste verifica ausência de overflow horizontal. Não havia referência mobile separada: extensão deliberada do mesmo sistema visual. |

Implementação visual verificada fielmente à composição da referência nos pontos acima, sem divergência material de conteúdo ou fluxo. Desvios intencionais: fonte nativa, sinalização condicionada aos dados da sessão, lateral plana e adaptação mobile. Fluxo testado: entrar → painel → consulta → sair; sem telas vazias acidentais, overlay ou erros de aplicação no console.

Capturas de QA temporárias (fora do repositório): `/private/var/folders/9v/c9y9j1gx7_b9h0y3jvhfww_80000gn/T/factorycontrol-qa/desktop.png` e `mobile.png`. A referência é somente especificação visual, não um asset consumido pela aplicação.

## Revisão corretiva da fundação — 2026-11-07

- Configuração de SSL MySQL escolhe a constante por disponibilidade de `Pdo\Mysql`; utiliza a constante PDO anterior quando a classe não existe, para permitir bootstrap no PHP 8.3. As subclasses específicas de PDO foram introduzidas no [PHP 8.4](https://www.php.net/manual/en/migration84.new-features.php). Execução local segue em PHP 8.5; runtime 8.3 pendente na CI.
- Reset válido encerra também a sessão em memória da requisição e limpa a autenticação da SPA. Apagar somente as linhas de `sessions` permitia que o middleware persistisse novamente a sessão atual.
- O runner ignora URLs de conexão herdadas; MySQL de testes continua exigindo nome terminado em `_test`. Não usar o banco de desenvolvimento na homologação.
- Formulário de acesso sincroniza estado com o roteador e ignora resultados de uma tela anterior após navegação. Token de reset é retirado pelo Vue Router e o formulário é substituído pela confirmação após sucesso.
- Histórico anterior de testes preservado em STATUS. Resultado desta revisão: 20 API, 8 SPA e 10 E2E passaram; integração MySQL/Redis skipped.

## Homologação real concluída — 2026-11-07

A matriz nativa executou PHP 8.3.33/8.5.5, MySQL 8.4.11/InnoDB/utf8mb4, Redis 8.10.1 e Mailpit 1.31.0. Foram 23 testes API em cada runtime, 8 unitários da SPA e 10 E2E com MySQL/Redis, sem skipped nas suítes MySQL. Worker Redis, sessões de banco e SMTP/reset completos foram exercitados. A validação adicional do Redis 7 no Compose está registrada abaixo. CI remota continua pendente.

O teste SMTP revelou e corrigiu configuração vazia de `MAIL_URL` (normalizada a null), que impedia construir o transporte. O roteiro `scripts/homologar-fundacao.py` usa diretórios/bancos/portas exclusivos e encerra os serviços ao sair; o E2E padrão continua SQLite sem o perfil opcional.

Homebrew instalou os runtimes com dependências. Os links de protobuf/abseil do MySQL 9.6 preexistente foram restaurados após detectar incompatibilidade de carregamento; MySQL 8.4 usa as bibliotecas novas somente em seu ambiente de processo. O serviço 9.6/PID 950 não foi reiniciado e seu datadir não foi usado.

OpenAPI validado com Redocly CLI 2.76.0: operationIds acrescentados; dois avisos intencionais de servidores localhost. Auditoria de dependências reexecutada: 15 moderados de desenvolvimento e zero no runtime. Resultados completos e limites no STATUS.

## Compose original homologado — 2026-11-07

Docker CLI 29.7.2, Compose 5.5.0, Colima 0.10.3 e Lima 2.2.0 instalados com aprovação, sem substituir o runtime padrão. Usada VM exclusiva `factorycontrol-phase0`, 2 CPUs/4 GiB/20 GiB, sem compartilhar diretórios do host nem ativar o contexto Docker global. Configuração Docker própria em `/private/tmp/factorycontrol-docker-config`. A VM foi desligada após os testes; o perfil e o cache de imagens foram mantidos para repetição.

O roteiro aceita `--compose` e exige socket Unix local explícito; remove `DOCKER_CONTEXT` herdada apenas do ambiente dos subprocessos, preservando a seleção local. Usa `infra/compose.yaml` sem modificações, projeto com nome aleatório, volumes novos, portas exclusivas e credenciais efêmeras. `down --volumes --remove-orphans` atua somente nesse projeto descartável; o resultado da limpeza compõe o resultado final da homologação. O modo nativo continua disponível.

Imagens efetivamente executadas em Linux ARM64: MySQL **8.4.13**, Redis **7.4.12** e Mailpit **1.33.0**. Tags móveis do Compose original foram preservadas; IDs das imagens e resultados constam da evidência local. Os 23 testes API/111 assertions passaram em cada PHP, além de 8 SPA e 10 E2E, SMTP/reset, worker e readiness após desligar Redis. Foram **35 verificações com exit 0**, incluindo remoção dos containers/volumes. Nenhum ajuste adicional na aplicação foi necessário.

Repositório informado pelo usuário: `https://github.com/contatonomaci-pixel/fabric.git`. As primeiras consultas públicas retornaram HTTP 404. Na retomada de 2026-11-07, metadados e Actions passaram a retornar HTTP 200: repositório **público**, vazio (`This repository is empty`), zero workflows e zero execuções. O conector GitHub continua indisponível; a API pública permitiu essa conferência. A CI remota depende de publicar o código/workflow, ação que requer autorização explícita por tornar o conteúdo público. A pasta continua sem `.git`; não houve push ou alteração remota.
