# ADR-0001: Manter decisões importantes em registros próprios

- Data: 2026-10-19
- Status: Accepted

## Contexto

Conversas com uma IA podem ser resumidas ou não estar disponíveis para outra IA ou pessoa que continue o projeto. Decisões importantes precisam conservar o contexto e o motivo de forma consultável no próprio projeto.

## Decisão

Manter cada decisão relevante de arquitetura, produto, segurança, dados ou operação em um arquivo Markdown numerado nesta pasta. A memória do projeto continua como resumo do estado atual e aponta para os registros. A IA deve criar ou atualizar os registros relevantes ao concluir o trabalho, sem exigir um pedido separado para “guardar”.

## Consequências

- Pessoas e ferramentas que consultem o repositório podem entender decisões sem acessar a conversa original.
- A memória geral permanece curta; decisões aceitas são mantidas separadamente e superadas por novos registros quando necessário.
- A implantação registra o contexto disponível agora; conversas antigas não são reconstruídas integralmente.

## Referências

- [AGENTS.md](../../AGENTS.md)
- [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md)

