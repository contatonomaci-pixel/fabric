# FactoryControl PRO — SPA

Vue 3, TypeScript, Vite e Tailwind. Interface pt-BR, login/recuperação, painel informativo e consulta de empresas autorizadas. Sessão HttpOnly e CSRF via proxy local, sem tokens em localStorage.

Veja [Execução Windows/Unix e testes](../../docs/17-executar-localmente.md). `npm ci`, `npm run dev`; navegue em `http://localhost:5173`. API precisa estar em `127.0.0.1:8000`.

Qualidade: `npm run lint`, `npm run typecheck`, `npm test`, `npm run build`. E2E: `npx playwright install chromium` e `npm run test:e2e`; inicia API/SPA e base fictícia efêmera, portas 8000/5173 livres.
