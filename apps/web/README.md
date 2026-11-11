# FactoryControl PRO — SPA

Vue 3, TypeScript, Vite e Tailwind. Interface pt-BR, login/recuperação, empresas/estrutura e catálogo da Fase 1 com SKUs, preços, histórico e arquivos Code128 internos. Sessão HttpOnly e CSRF via proxy local, sem tokens em localStorage. [Uso do catálogo](../../docs/19-catalogo-fase-1.md).

Veja [Execução Windows/Unix e testes](../../docs/17-executar-localmente.md). `npm ci`, `npm run dev`; navegue em `http://localhost:5173`. API precisa estar em `127.0.0.1:8000`.

Qualidade: `npm run lint`, `npm run typecheck`, `npm test`, `npm run build`. E2E: `npx playwright install chromium` e `npm run test:e2e`; inicia API/SPA e base fictícia efêmera, portas 8000/5173 livres.

Fase 2: `/compras` e `/estoque`, com empresa de trabalho e seletor de seção. [Uso e decisões](../../docs/21-compras-estoque-fase-2.md). Valores físicos/monetários são enviados como texto decimal; não há cálculo de massa pela gramatura nominal no frontend.
