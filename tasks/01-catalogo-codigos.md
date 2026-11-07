# Fase 1 — Produtos, unidades, SKUs e códigos internos


## Trabalho
- Implementar famílias, produtos, variantes, SKUs e UoM com preço configurável, peso nominal e atributos extensíveis.
- Permitir Coxinha Frango 25g com SKU UN, PCT20 e KG; Coxinha Carne; Bolinho Carne; Pizza Frango e Pizza Carne como seeds fictícios.
- Gerador de SKU/Code128 **único e transacional** com reserva de código e histórico, PNG/SVG/PDF de teste com quiet zone e checksum quando pertinente.
- Cadastro/listagem/edição/desativação e permissão por empresa; filtros família/variante/tipo e componente seleção UN/KG/PCT/CX.

## Aceite
- Duas sessões simultâneas não criam código repetido.
- Dois sabores, gramaturas ou apresentações recebem SKUs distintos.
- Sem GTIN oficial não exibir código restrito como homologado.
- Produto desativado conserva histórico e código reservado.
