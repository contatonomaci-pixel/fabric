# 10 — Papéis, permissões e segregação

Permissões atomizadas em backend (`resource.action`) e escopo empresa/fábrica. Usuários podem acumular papéis por empresa. Negação explícita por padrão.

| Papel | Pode | Não deve poder por padrão |
|---|---|---|
| Proprietário | Todos os módulos da própria empresa, aprovações | Acessar empresas não vinculadas |
| Administrador geral | Cadastros, operação, relatórios gerais | Alterar auditoria ou obter segredos sem motivo |
| Gerente PCP | OP, metas de produção, estoque e custos autorizados | Ver saldos bancários/salários sem permissão |
| Operador produção | Apontar etapas, perdas e pesagens atribuídas | Liberar lote de qualidade ou ver lucro global |
| Qualidade/RT | Inspecionar, bloquear, liberar lotes, aprovar processos | Alterar liquidação financeira sem permissão |
| Almoxarifado | Receber, localizar, reservar e contar estoque | Pagar fornecedor sem permissão |
| Compras | Cotação, fornecedores, pedidos | Alterar ordens já pagas sem autorização |
| Comercial | Clientes, pedidos e metas comerciais designadas | Ver CPV/margens confidenciais sem permissão |
| Expedição | Picking, conferência e envio | Liberar lote bloqueado |
| Financeiro | Contas, caixa, relatórios financeiros | Alterar rastreabilidade de produção |
| RH | Equipes, turnos e dados de pessoal autorizados | Ver finanças gerais sem permissão |
| Manutenção | Máquina, OS, paradas | Alterar receita aprovada |
| Auditor | Consulta auditável limitada | Mutação |

Casos críticos: testes de IDOR/BOLA (usuário da empresa A tenta recuperar SKU/OP/financeiro da B); operador tenta liberar lote; vendedor tenta ler DRE; desabilitado tenta login. Logar autorizações de alteração de preço, estorno, liberação de qualidade, mudança de meta e importação de código.
