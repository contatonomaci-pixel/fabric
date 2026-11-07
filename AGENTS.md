# Instruções obrigatórias para Codex — FactoryControl PRO

## Missão e fonte de verdade
Implemente o FactoryControl PRO **incrementalmente** seguindo este repositório. Leia `README.md`, `docs/00-visao-e-escopo.md`, `docs/01-arquitetura.md`, `docs/02-modelo-de-dados.md`, `docs/11-seguranca-qualidade.md`, `docs/12-plano-e-entregas.md`, `docs/STATUS.md` e a tarefa ativa em `tasks/`. Consulte documentos específicos antes de alterar seus módulos. Em caso de conflito, não assuma: documente em `docs/STATUS.md` e proponha decisão.

## Regras de execução
1. Execute **somente a fase solicitada**. Não implemente módulos futuros por antecipação. Evite placeholders que pareçam funcionais.
2. Antes de codar, descreva objetivo, arquivos pretendidos, riscos e testes. Depois implemente e rode testes. Mostre resumo, evidências e próximos passos.
3. Não apague trabalho existente nem reestruture o repositório sem necessidade. Verifique `git status`; não sobrescreva alterações alheias.
4. Não invente dados reais, credenciais, percentuais tributários, prazos de validade, temperaturas sanitárias, GTINs, integração de máquinas ou PDVs. Crie **configuração pendente de validação**.
5. Não faça deploy, não altere produção e não envie dados sensíveis para serviços externos sem autorização explícita.
6. Sempre mantenha `docs/STATUS.md` atualizado com entregas, testes executados, bloqueios e decisões.
7. Entregue código executável, migrations, seeders de demonstração identificados como fictícios, testes automatizados e documentação da operação. Evite lógica de negócio apenas no frontend.

## Stack aprovada
- API Laravel 13 / PHP >= 8.3, pasta `apps/api`.
- SPA Vue 3 + TypeScript, Vite, Tailwind, pasta `apps/web`.
- MySQL 8.4 LTS InnoDB (`utf8mb4`), Redis e filas/jobs.
- REST JSON `/api/v1`, respostas e erros consistentes, paginar listas, usar políticas de autorização.
- Monólito modular no backend: módulos de domínio + serviços/casos de uso; controllers finos; transações nos fluxos críticos.
- Auth Laravel Sanctum com cookies/CSRF para SPA first-party; não usar localStorage para tokens de longa duração por padrão.
- Adotar bibliotecas estáveis, compatíveis e mantidas; registrar decisões e versões verificadas no momento da instalação.

## Regras fundamentais do domínio
- `company_id`/`factory_id`/`warehouse_id` onde aplicável. Toda consulta e alteração respeitam contexto de empresa e autorização do usuário; sem vazamento entre empresas.
- Produto comercial/variante/lote são entidades distintas. Uma mudança de embalagem ou quantidade vendida pode exigir SKU/identificador diferente.
- Código interno não é GTIN oficial. Código EAN-13 restrito só com **perfil de varejista validado**, não existe aceitação universal. NF-e sem GTIN deve seguir regra fiscal vigente, não usar código restrito no campo GTIN.
- Venda por peso exige `DECIMAL` e peso líquido real; jamais calcular estoque só com número teórico de unidades; manter conversões e eventos de pesagem auditáveis.
- Movimentações de estoque e lançamentos financeiros não podem ser apagados ou alterados silenciosamente; usar estorno/eventos compensatórios. Operações concorrentes com transação e locks/controle de idempotência.
- Controle FEFO por lote liberado; quarentena/bloqueio de qualidade impede reserva, consumo e expedição.
- Registrar genealogia lote insumo -> consumo da OP -> lote acabado -> embalagem -> expedição -> cliente.
- Separar `data_competencia` de `data_pagamento` e `previsto` de `realizado`. Ponto de equilíbrio e DRE não devem misturar caixa e regime de competência.
- Processos térmicos e cadeia fria são configuráveis por produto/versão da rota; definição pela equipe técnica e validação antes de uso real.

## Padrões de engenharia
- Migrações reversíveis quando seguro; `foreign keys`, `unique indexes`, constraints; IDs estáveis (preferir ULID/UUID externo e PK interna uniforme conforme ADR).
- Dinheiro: DECIMAL(18,4) para cálculos/custos e DECIMAL(18,2) para valores finais, arredondamento explícito; peso/quantidade DECIMAL(18,6); não FLOAT para dados de negócio.
- Timestamps UTC; timezone de exibição por empresa; moeda BRL por padrão, arquitetura extensível.
- APIs: validação via FormRequest, Policies/Gates, rate limiting, logs sem segredos, testes de autorização e concorrência.
- Interface pt-BR, responsiva, acessível, com estados loading/vazio/erro/sucesso, confirmação para operações destrutivas.
- Nunca presumir que um scanner Code 128 se comporta igual em todo PDV; permitir perfil por cliente e testes de homologação.

## Comandos e qualidade
Depois de a Fase 0 criar a aplicação, usar, quando disponíveis:
- API: `composer test` ou `php artisan test`; `vendor/bin/pint --test`.
- Web: `npm run lint`, `npm run typecheck`, `npm run build`, testes unitários definidos pelo projeto.
- E2E para casos críticos quando estiverem implementados.
Se ferramentas não estiverem instaladas, registre a limitação; não afirme que os testes passaram.

## Conclusão de cada sessão
Responda em pt-BR com: (a) o que foi implementado; (b) arquivos alterados; (c) testes executados e resultados; (d) pendências e riscos; (e) próxima fase recomendada. Atualize `docs/STATUS.md`.
