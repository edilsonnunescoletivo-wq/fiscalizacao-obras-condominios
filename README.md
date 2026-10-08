# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, desde documentação até conclusão, com perfis de acesso, fiscalização, notificações e auditoria.

## Stack

- PHP 8+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- PDO
- Hospedagem alvo: Locaweb

## Implementado

- autenticação com sessão e proteção CSRF;
- acesso ao banco via PDO;
- dashboard multi-condomínio;
- perfis e vínculos por condomínio;
- listagem e cadastro de obras;
- proprietário, empresa executora e responsável técnico;
- restrição do Responsável da Obra às obras vinculadas ao seu usuário;
- tela de detalhes da obra;
- documentos obrigatórios configuráveis;
- upload de PDF/JPG/PNG com limite de 10 MB;
- versionamento de documentos;
- análise do fiscal com aprovação, correção ou reprovação;
- bloqueio da aprovação técnica enquanto houver documento obrigatório pendente;
- aprovação técnica, autorização administrativa e início da obra;
- fiscalizações com etapa, resultado, observações e checklist;
- não conformidades com gravidade, prazo e encerramento;
- notificações numeradas de irregularidade, advertência e adequação;
- suspensão, embargo e liberação com controle de perfil e status;
- histórico/linha do tempo unificado;
- interface responsiva para desktop e celular.

## Fluxo atual

1. Usuário entra no painel geral e seleciona um condomínio.
2. Visualiza as obras do empreendimento.
3. Perfis autorizados cadastram uma nova obra.
4. A obra entra em `WAITING_DOCUMENTS`.
5. Documentos são enviados e versionados.
6. O fiscal aprova, solicita correção ou reprova cada documento.
7. Com todos os documentos obrigatórios aprovados, a obra pode ir para `TECHNICALLY_APPROVED`.
8. Síndico/Gerente/Administrador pode autorizar a obra (`AUTHORIZED`).
9. O início é registrado como `IN_PROGRESS`.
10. Fiscalizações operacionais podem ser registradas.
11. Irregularidades podem virar não conformidades com prazo de correção.
12. Notificações podem alterar o estado para `NOTIFIED`, `SUSPENDED` ou `EMBARGOED`.
13. Após regularização, uma liberação retorna a obra para `IN_PROGRESS`.
14. Todas as movimentações relevantes ficam registradas na linha do tempo.

## Regras operacionais

- Fiscalização e não conformidade só podem ser registradas após o início da obra.
- Suspensão e embargo exigem perfil administrativo e obra em andamento ou notificada.
- Liberação exige perfil administrativo e obra previamente suspensa, embargada ou notificada.
- Fiscal pode emitir notificações operacionais, mas não suspender, embargar ou liberar.

## Segurança

Nunca comite `.env`, senhas, credenciais da Locaweb, dados reais de condomínio ou segredos de implantação no repositório. Os uploads são armazenados fora da pasta pública da aplicação.
