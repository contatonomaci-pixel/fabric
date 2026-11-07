# 00 — Visão, contexto e escopo

## Objetivo
Centralizar processos de fábrica de alimentos, da compra à cobrança, com rastreabilidade e indicadores de gestão. Operação inicial: salgados moldados, processados e congelados, destinados ao aquecimento pelo consumidor final, com processo de produção especificado e validado por responsável técnico. Máquina inicial: Consultomaq Maqturbo 8.5 MAX (capacidades, formatos e acessórios a conferir no equipamento efetivo).

## Princípios
- Um catálogo genérico de **famílias, variantes, apresentações comerciais e versões de receitas/rotas**, não tabela especial `coxinhas`.
- Preparado para várias empresas, unidades, almoxarifados, equipamentos e linhas produtivas, sem ativar complexidade multitenant além do necessário no MVP.
- Rastreamento de origem/destino de cada lote por quantidade, data e responsável.
- Controle financeiro, comercial e industrial integrado; análise com período customizado e indicadores auditáveis.
- Idoneidade: códigos de barras internos e restritos não se passam por GTIN; cálculos financeiros com premissas explícitas; validade e parâmetros térmicos validados.

## Personas e responsabilidades
Proprietário/diretoria; gerente geral; compras; almoxarifado; PCP; operador de produção; qualidade/responsável técnico; expedição/logística; comercial; financeiro; RH; manutenção; auditor/consulta. Ver `docs/10-permissoes.md`.

## Módulos previstos
1. Cadastro estrutural e administração (empresa, fábrica, depósitos, usuários, unidades, tributos configuráveis).
2. Catálogo (produto, família, sabor, gramatura, variantes, apresentações UN/KG/g/PCT/CX, preços).
3. Codificação/etiquetagem (SKU, Code 128, RCN EAN-13 mediante perfil, GS1 oficial futuro, impressão).
4. Compras e fornecedores (cotação, pedido, entrega, divergência e contas a pagar).
5. Estoque (lote, validade, localização, FEFO, quarentena, inventário, transferências, pesagem).
6. Engenharia de produto (fichas técnicas, receitas versionadas, alergênicos, rota de produção, rendimento).
7. PCP e produção (planejamento, OP, reserva, consumo, perdas, operadores, metas, conclusão).
8. Qualidade/segurança alimentar (plano de inspeção, temperaturas, limpeza, liberações, não conformidade, recolhimento).
9. Máquinas e manutenção (cadastro de máquinas, moldes, setup, horas paradas, OS de manutenção).
10. Comercial/CRM (clientes, canais, vendedores, tabelas e metas, pedidos, cobrança).
11. Expedição (separação por lote, cadeia fria, volume, rastreabilidade por cliente e devolução).
12. Finanças (contas, caixa, bancos, conciliação, orçamento, centros de custo, DRE e provisões).
13. RH operacional (cadastro, turno, escala, atividades, treinamento, absenteísmo; folha avançada futura).
14. BI e alertas (KPIs, ponto de equilíbrio, metas, previsões, relatórios, exportação, permissões).

## Fronteiras de escopo
**Primeiro MVP:** autenticação e autorização, catálogo + unidade + código interno, fornecedor, estoque com lote, receita, OP simples, etiqueta interna, registros essenciais de qualidade, pedidos básicos, relatórios básicos. O financeiro entra com fundação e fases específicas, sem fingir cálculo antes de lançamentos confiáveis.
**Planejado depois:** integração oficial GS1, NF-e/NFC-e, bancos/PIX/TEF, balança industrial, impressoras via driver/agente local, PDVs de terceiros, integração direta com máquina, folha de pagamento e BI preditivo. Todas dependem de contratos, APIs, drivers e homologação.

## Premissas pendentes de validação
Produtos e moldes instalados; etapas térmicas específicas; capacidade e localização de câmaras frias; fluxo de expedição e transporte; rótulo legal; mix de canais varejistas; modelo de preço por quilo; modelo e protocolo da balança/impressora; número de fábricas; legislação fiscal e sanitária aplicável; equipe e turnos.
