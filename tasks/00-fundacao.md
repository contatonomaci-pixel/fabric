# Fase 0 — Fundação executável


## Objetivo
Criar o monorepo executável sem implementar o ERP de uma vez: Laravel 13 em `apps/api`, Vue 3 + TypeScript/Vite/Tailwind em `apps/web`, Docker local de MySQL 8.4/Redis e setup de autenticação e permissões de exemplo. Revisar primeiro o repositório e AGENTS.md.

## Trabalho
1. Verificar PHP >=8.3/Composer/Node/npm e Docker; documentar faltantes, não inventar sucesso de execução.
2. Criar projeto Laravel 13 no diretório `apps/api` por ferramenta oficial quando disponível; usar PHP 8.3+.
3. Criar Vue 3/TS/Vite/Tailwind no diretório `apps/web` com lint/typecheck/build. Proxy/sessões Sanctum first-party com CSRF.
4. Conectar MySQL 8.4/Redis local por `.env` próprio (não versionar senha real), migrations base e seeds **fictícios**.
5. Auth login/logout/me, reset seguro (se possível), roles base e proteções multiempresa mínimas, endpoint health.
6. Layout com login, menu, dashboard vazio informativo, empresas/unidades e 404/403.
7. Testes mínimos auth, 401/403, 1 seed company, build frontend; documentar instalação Windows/Unix.
8. API versionada e primeira documentação OpenAPI ou coleção de endpoints.

## Critérios
- `docker compose -f infra/compose.yaml --env-file infra/.env up -d` sobe serviços (quando Docker disponível).
- Laravel migrações e teste auth passam em MySQL de teste; login/logout funciona na SPA com CSRF.
- Dashboard explica que não existem dados; **nenhum faturamento fictício exibido como real**.
- `git status` e changelog de fase relatados; status atualizado.

## Não fazer
Não desenvolver receitas, OPs, financeiro, GTINs, etiquetas ou BI na Fase 0.
