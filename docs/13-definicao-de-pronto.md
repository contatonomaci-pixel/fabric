# 13 — Definition of Done (DoD)

Uma tarefa só fica 'concluída' quando:
- Há migrations e rollback seguro quando pertinente.
- API implementa validação, autorização multiempresa e erros consistentes.
- UI funcional tem loading, vazio, erro, confirmação e labels pt-BR.
- Testes relevantes rodam e o resultado é registrado (não inventar resultados).
- Não há dados financeiros sensíveis em telas de operador.
- Dados transacionais não se perdem por concorrência ou requests repetidos.
- Histórico de alterações/estornos existe para mutações críticas.
- Documentação de API e uso da tela estão atualizados.
- O código está formatado, typescript sem erro e build passou.
- `docs/STATUS.md` contém implementado, pendente, comandos de teste e evidências.

## Checklist de homologação por usuário
- Cadastro: usuário cria coxinha frango 25g, SKU UN/PCT/KG, sem duplicatas.
- Estoque: material entra com lote; FEFO recomenda lote liberado correto.
- Produção: OP consome ingredientes, registra perdas, produz lote acabado ligado aos insumos.
- Qualidade: lote bloqueado não é separável/vendável.
- Vendas: unidade, quilo e pacote funcionam sem contagem duplicada.
- Código varejista: rótulo interno escaneável; RCN somente após homologação individual.
- Financeiro: títulos e recebimentos separados; DRE por competência; PE demonstra premissas.
- BI: filtros por dia/mês/ano e datas personalizadas; indicadores coerentes e com atualização visível.
- Segurança: usuário sem permissão não consulta recursos alheios.

## Go-live
Exige aprovação da direção + responsável técnico sanitário/rotulagem + contador/fiscal quando aplicável, restauração testada, backup, treinamento e homologação de equipamentos e PDVs.
