# Infraestrutura local (MySQL / Redis / Mailpit)

1. Copie `infra/.env.example` para `infra/.env` e altere as senhas (apenas desenvolvimento).
2. Na **raiz do repositório**, rode:
   ```bash
   docker compose -f infra/compose.yaml --env-file infra/.env up -d
   docker compose -f infra/compose.yaml --env-file infra/.env ps
   ```
3. Configuração para o Laravel local: `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=factorycontrol_pro`, `DB_USERNAME=factorycontrol`, `DB_PASSWORD=<MYSQL_PASSWORD>`; `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6380`; `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`.
4. Acesse o Mailpit local por `http://127.0.0.1:8025` se disponível.
5. Para desligar sem perder dados:
   ```bash
   docker compose -f infra/compose.yaml --env-file infra/.env down
   ```
**Atenção:** `down -v` apaga volumes e dados locais; use apenas se tiver certeza. Não expor portas de serviços na internet; credenciais do compose são só de desenvolvimento.

Guia completo da aplicação, autenticação e testes: [Executar localmente](../docs/17-executar-localmente.md). O Compose original não foi alterado pela Fase 0.
