# Registros de decisão

Esta pasta mantém decisões relevantes de arquitetura, produto, segurança, dados e operação. A decisão deve poder ser entendida sem consultar a conversa que a originou.

- Leia os registros existentes antes de propor uma decisão relacionada.
- Crie um arquivo por decisão, usando o próximo número sequencial: `NNNN-titulo-curto.md`.
- Registre o contexto, a escolha, o motivo e as consequências. Marque a decisão como `Proposed`, `Accepted`, `Rejected` ou `Superseded`.
- Não reescreva uma decisão aceita quando ela mudar: crie outro registro e marque o anterior como superado, com um link.
- Atualize `PROJECT_MEMORY.md` com um resumo da decisão vigente e um link para o registro.
- Use registros para escolhas com impacto futuro; não registre cada detalhe de implementação nem copie conversas, segredos ou resultados de testes.

## Modelo

```markdown
# ADR-NNNN: Título da decisão

- Data: YYYY-MM-DD
- Status: Proposed | Accepted | Rejected | Superseded
- Substitui: ADR-NNNN (se aplicável)

## Contexto

Qual problema ou necessidade motivou a decisão?

## Decisão

O que foi escolhido?

## Consequências

Quais são os benefícios, custos, limites e ações decorrentes?

## Referências

Links para documentação, código ou outros ADRs.
```

