# 05 — Fluxos operacionais e estados

## 1. Compra até insumo liberado
Requisição -> cotação -> pedido -> recebimento -> conferência de quantidades/nota/temperatura quando aplicável -> geração/associação de lote de insumo -> quarentena (se requerido) -> inspeção -> `liberado` -> alocação em local de estoque. Reprovações impedem consumo/transferência não autorizada.

## 2. Engenharia e planejamento
Cadastro de variante -> ficha técnica versão `rascunho` -> aprovação -> rota de produção versionada -> previsão de materiais -> OP `planejada` -> reserva lotes FEFO apenas liberados -> `liberada`.

## 3. Produção de salgados congelados
OP -> separação/pesagem insumos -> preparo de massa e recheio -> modelagem na 8.5 MAX (moldes e setups cadastrados) -> **etapas de cocção/finalização térmica conforme processo validado do produto** -> resfriamento quando aplicável -> congelamento controlado -> embalagem -> inspeção -> lote acabado liberado -> câmara fria -> expedição. **Não presumir** que a modeladora executa cocção/congelamento nem definir prazos/temperaturas sem responsável técnico.

## 4. Estados e transições sugeridas
- OP: `draft -> planned -> released -> in_progress -> awaiting_quality -> completed`; `cancelled` apenas sem consumo ou com compensações rastreadas.
- Lote: `quarantine -> released | rejected | blocked`; lote expedido pode entrar em `recall` com rastreio downstream.
- Pedido: `draft -> confirmed -> picking -> packed -> dispatched -> delivered`, `cancelled` conforme regras de reserva e expedição.
- Recebível: `open -> partially_paid -> paid`, `overdue` calculado por data, `reversed` auditado.

## 5. Rastreabilidade de ponta a ponta
```mermaid
flowchart LR
 A[Lote de matéria-prima] --> B[Consumo em OP]
 B --> C[Lote de produto acabado]
 C --> D[Pacote / caixa / pesagem]
 D --> E[Expedição]
 E --> F[Cliente / pedido]
```
Dois percursos obrigatórios: trace-back (produto -> insumos e fornecedor) e trace-forward (insumo -> OPs -> produto -> estoque/expedições/clientes). Registre quantidade afetada e bloqueio preventivo quando aplicável.

## 6. Pesagem e venda KG
Operador seleciona produto + lote liberado -> pesagem bruto/tara/líquido -> embalagem -> associação SKU KG -> emissão etiqueta compatível -> reserva/baixa exata de gramas -> expedição. Prevenir dupla emissão/baixa pela mesma pesagem.

## 7. Correções e auditoria
Movimento consolidado não pode ser apagado; criar reversão vinculada. Mudança de receita aplicada somente a OPs futuras; produtos fabricados mantém receita/rota original. Dados sensíveis exibidos só conforme permissões.
