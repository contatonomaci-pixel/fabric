#!/usr/bin/env python3
"""Verifica integridade mínima do pacote documental; não testa o sistema Laravel."""
from pathlib import Path
root = Path(__file__).resolve().parent.parent
required = ["README.md","AGENTS.md","docs/00-visao-e-escopo.md","docs/02-modelo-de-dados.md","docs/04-codigos-etiquetas.md","docs/06-financeiro-metas-bi.md","docs/12-plano-e-entregas.md","docs/19-catalogo-fase-1.md","docs/21-compras-estoque-fase-2.md","prompts/INICIAR-CODEX.md","tasks/00-fundacao.md","infra/compose.yaml"]
missing = [name for name in required if not (root / name).is_file()]
if missing:
    raise SystemExit("Arquivos ausentes: " + ", ".join(missing))
print(f"Pacote documental OK: {len(required)} arquivos essenciais presentes.")
