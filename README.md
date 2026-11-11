# FactoryControl PRO — Projeto mestre para desenvolvimento com Codex

**Status:** Fase 2 implementada e homologada localmente e na CI: fornecedores, pedidos/recebimentos, políticas físicas, lotes/locais, FEFO, reservas, movimentos/estornos, contagens e pesagens manuais. Entrega na branch `feat/phase-2-inventory`, [PR #2 em rascunho para revisão](https://github.com/contatonomaci-pixel/fabric/pull/2), com quatro jobs aprovados. Evidências e limites em [STATUS](docs/STATUS.md). Fase 1 integrada à main pelo [PR #1](https://github.com/contatonomaci-pixel/fabric/pull/1). **Produção, vendas, financeiro, RCN/GTIN e BI ainda não implementados.**
**Revisão:** 2026-11-10. **Idioma do produto:** pt-BR; moeda BRL; datas ISO no armazenamento e pt-BR na UI.

## Executar a fundação

Consulte [instalação Windows/VS Code e Unix, login fictício e testes](docs/17-executar-localmente.md), [catálogo da Fase 1](docs/19-catalogo-fase-1.md), [compras/estoque da Fase 2](docs/21-compras-estoque-fase-2.md), [contrato OpenAPI](docs/api/openapi.json) e [evidências/pendências](docs/STATUS.md). O Compose fornecido permanece em `infra/compose.yaml`.

## Visão do produto
ERP industrial expansível para alimentos, começando com salgados congelados prontos para **aquecer e consumir**, produzidos inicialmente em modeladora Consultomaq Maqturbo 8.5 MAX. Suporta futuramente pizzas, massas, marmitas, panificação, sobremesas, outras máquinas, depósitos e fábricas. Gestão integrada de produtos, produção, qualidade, rastreabilidade, estoque, compras, vendas, expedição, funcionários, finanças, metas e BI.

### Decisões já aprovadas
- **Backend:** Laravel 13 com PHP >= 8.3; **frontend:** Vue 3 + TypeScript + Vite + Tailwind; **banco:** MySQL 8.4 LTS/InnoDB; **filas/cache:** Redis; **API:** REST /api/v1; **auth:** Sanctum com proteção CSRF e cookies seguros quando em mesmo domínio; monorepo.
- Venda em **UN, g, KG, PCT, CX**, com conversões explícitas, preço por modalidade e pesos efetivamente medidos para produtos de peso variável.
- Cada sabor/tamanho/apresentação comercial = **SKU distinto**, com código exclusivo. Um lote de fabricação tem identificação própria sem alterar o SKU.
- Geração de Code 128 interno; EAN-13 **de circulação restrita** apenas em formato acordado com varejista e seu PDV, sem fingir ser GTIN; GTIN oficial somente quando fornecido/validado pela GS1. Ver `docs/04-codigos-etiquetas.md`.
- Controle sanitário e rotulagem dependem de validação do responsável técnico. Não inventar temperaturas, prazos de validade ou requisitos fiscais.
- BI com ponto de equilíbrio, metas por dia/mês/ano e intervalo personalizado, DRE gerencial, fluxo de caixa e alertas.

## Primeiros passos no VS Code
1. Descompacte o ZIP em uma pasta como `C:\Projetos\FactoryControl_PRO` (Windows) ou `~/projetos/FactoryControl_PRO`.
2. Abra a **pasta raiz** no VS Code: **Arquivo > Abrir Pasta** (não abra apenas um arquivo).
3. Instale/abra a extensão oficial **Codex** e autentique-se conforme as opções disponíveis.
4. Abra `AGENTS.md`, `docs/STATUS.md` e `docs/17-executar-localmente.md`.
5. Siga o guia de execução da fundação. Para novas alterações, selecione somente a fase autorizada em `tasks/`.
6. Revise as alterações sugeridas pelo Codex e valide testes antes de prosseguir. Faça commit por fase.
7. Se Docker Desktop estiver disponível, execute `docker compose -f infra/compose.yaml --env-file infra/.env up -d` para subir **apenas** MySQL, Redis e Mailpit (após copiar `infra/.env.example` para `infra/.env`). O Laravel e o Vue estão em `apps/api` e `apps/web`.

### Pré-requisitos de desenvolvimento
- VS Code, Git, PHP >= 8.3 com extensões Laravel, Composer 2, Node.js LTS + npm, Docker Desktop (opcional, recomendado para MySQL/Redis locais).
- Não executar deploy automático, não inserir credenciais reais no chat ou em commits.

### Links internos
- `AGENTS.md`: regras persistentes para o Codex.
- `docs/00-visao-e-escopo.md` a `docs/13-definicao-de-pronto.md`: especificação funcional, técnica, segurança, operação, API e testes.
- `docs/STATUS.md`: progresso e pendências.
- `tasks/`: tarefas por fase com entregáveis e critérios de aceite.
- `prompts/`: mensagens prontas para as sessões do Codex.
- `infra/compose.yaml`: serviços locais; `docker compose -f infra/compose.yaml --env-file infra/.env up -d` após copiar `infra/.env.example` para `infra/.env`.

### Aviso de escopo
Este repositório contém uma **fundação executável e um plano incremental**. Valores fiscais, legislação sanitária aplicável, integrações com PDVs, GS1, balanças, impressoras e especificações reais da máquina precisam ser conferidos antes do uso em produção. Os exemplos de preço, peso, meta, prazos e etiquetas não são parâmetros regulatórios.
