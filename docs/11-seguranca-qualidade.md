# 11 — Qualidade de engenharia, segurança e operação

## Segurança
- Autenticação Sanctum apropriada para SPA first-party, proteção CSRF, sessões seguras; política de senhas e recuperação.
- RBAC no backend e checagem de escopo por empresa/fábrica em queries; nunca confiar em permissões de botões frontend.
- Validação central de inputs, prepared statements/ORM, escape de HTML, arquivos em storage privado, upload com validação de MIME/tamanho.
- Rate limiting, auditoria de ações sensíveis, TLS obrigatório em produção, headers seguros e gestão de segredos em variáveis de ambiente.
- LGPD: minimização de dados, finalidades e perfis de acesso, rotina de retenção/eliminação quando legalmente aplicável.
- Separar backups, restaurar em teste, monitorar jobs falhos e espaço em disco; definir RPO/RTO com a operação antes do go-live.

## Testes obrigatórios por camada
- Backend: PHPUnit/Pest conforme instalação, feature HTTP, unit para regras de domínio, transação/concorrência e autorização.
- Frontend: unit com Vitest (ou alternativa justificada), typecheck/ESLint, componente e estado; e2e Playwright depois da infraestrutura estável.
- Dados: migrations up/down em MySQL teste, índices UNIQUE, FK, casos de `NULL` em unicidade, deadlocks/retry.
- Cenários críticos: código duplicado, venda simultânea, lote bloqueado/expirado, emissão duplicada de etiqueta, pesagem com tara inválida, OP usando receita errada, estorno financeiro, meta com MC% <= 0, período parcial e timezone.
- Testar UI pt-BR, datas, BRL, acessibilidade (labels e teclado), filtros customizados e exportações.

## Operação
- Logs com correlation id e sem senha/token; métricas de API, jobs e filas; healthcheck real.
- Restore testado antes da implantação. A produção exige backup automático, alertas, retenção e política de recuperação.
- `staging` usa dados artificiais ou anonimizados; não restaurar dados pessoais reais sem necessidade.
- Processo de deploy não pode executar migrations destrutivas automaticamente sem revisão.

## Observabilidade de negócio
Dashboard mostra origem e última atualização dos dados; toda edição de preço, meta e ficha tem autor/data/versão. Relatórios e importações devem indicar erros e não falhar silenciosamente.
