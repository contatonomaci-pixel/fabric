# 03 — Produtos, unidades, embalagens e preço

## Hierarquia
Família de produto → produto base → variante (sabor/peso/atributos) → SKU comercial (UN, PCT, CX, KG, peso fixo/variável) → código de barras. A variante identifica características de fabricação; SKU identifica o item comercial vendido. Lote identifica **uma fabricação**, não substitui SKU.

Exemplos de dados fictícios:
| Família | Variante | Apresentação comercial | Modo | SKU exemplo |
|---|---|---|---|---|
| Coxinha | Frango 25g | avulsa | UN | SAL-000001 |
| Coxinha | Frango 25g | pacote 20 un | PCT | SAL-000002 |
| Coxinha | Frango 25g | venda a granel | KG | SAL-000003 |
| Coxinha | Carne 25g | pacote 20 un | PCT | SAL-000004 |
| Bolinho | Carne 50g | pacote 10 un | PCT | SAL-000005 |
| Pizza | Frango 400g | embalagem 1 un | UN | PIZ-000001 |
| Pizza | Carne 400g | embalagem 1 un | UN | PIZ-000002 |

## Regras de UoM
- Campo `unit_dimension` (massa/contagem/volume futuro). Unidades `g`, `KG` fator 1000 em relação à base g; `UN` contagem.
- `PCT` e `CX` são **embalagens**, conversão por SKU (`units_per_pack`, `packs_per_case`) e vigência de embalagem. Nunca usar fator global `1 PCT = X UN`.
- Produzir em unidades e registrar total em gramas reais; venda por KG deduz massa medida, podendo registrar unidades estimadas apenas para planejamento.
- Permitir pesagem líquida (bruto - tara) com tolerância definida pelo responsável. Nunca preço baseado em peso bruto.
- Pré-embalados de peso fixo: quantidade nominal e tolerância conforme regra regulatória aplicável. Peso variável: etiqueta única para cada pesagem conforme perfil do varejo.
- Preço por UN não é automaticamente `preço/kg × peso nominal`; conversão pode ser **sugestão**, nunca alterar preços comerciais sem autorização.
- Devolução, quebra, descarte, amostras e reprocessamento precisam de motivos configurados e registros de lote.

## Estoque e custo
Controle de estoque por produto+local+lote+estado. Para embalagem de produto acabado, registrar transformação de saldo a granel em PCT/CX, sem duplicar estoque; retornar para saldo a granel somente por operação autorizada e auditável.

Custo de ficha técnica usa insumos efetivamente consumidos, rendimento, perdas e mão de obra/overhead alocado (quando disponível); custos por lote. Separar margem simulada do custo efetivo contabilizado.

## Critérios de aceite
1. Duas apresentações da mesma variante podem ter preços e códigos diferentes.
2. Não é possível criar dois SKU ativos/inativos com mesmo identificador reservado.
3. Venda 1,250 kg de peso variável desconta exatamente 1,250 kg do lote, se disponível.
4. Pacote 20 UN deduz 20 unidades do saldo correspondente ou conversão validada; não duplica saldo.
5. Históricos de venda preservam preço, peso e embalagem no momento da transação.
