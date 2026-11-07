# 15 — Referências oficiais e ADRs

A documentação foi elaborada como planejamento de software em 2026-11-06. Confirmar atualizações ao iniciar implementação, especialmente regras fiscais e sanitárias.

## Fontes técnicas
- Codex e instruções de repositório: https://developers.openai.com/codex/
- Codex com VS Code: https://help.openai.com/pt-br/articles/11369540-using-codex-with-your-chatgpt-plan
- Laravel 13 release/compatibilidade PHP: https://laravel.com/docs/13.x/releases
- MySQL 8.4: https://dev.mysql.com/doc/refman/8.4/en/
- GS1 Brasil: https://www.gs1br.org/ (conferir normas de circulação restrita por canal/cliente)
- Anvisa: https://www.gov.br/anvisa/pt-br (conferir normas aplicáveis por produto)

## ADR — decisões iniciais
**ADR-001**: MySQL 8.4 LTS e InnoDB; Laravel 13/PHP 8.3+; Vue 3/TS; Redis. Motivo: suporte empresarial, familiaridade, transações e modularidade.
**ADR-002**: aplicação monorepo monólito modular; evitar microsserviços na abertura da fábrica.
**ADR-003**: código de barras comercial e lote separados; RCN com perfil homologado individual, GTIN futuro oficial.
**ADR-004**: unidade de medida e pesagem são dimensão explícita; pacote/caixa por SKU e peso variável por pesagem real.
**ADR-005**: DRE e PE por competência; caixa separado; snapshots financeiros com proveniência.
**ADR-006**: nenhuma operação sanitária, validade, temperatura ou tributação automatizada sem validação especializada.
**ADR-007 PENDENTE**: convenção de PK (BIGINT internamente + ULID público recomendado, requer confirmação na Fase 0).
