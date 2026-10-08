# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, desde documentação até conclusão, com perfis de acesso, fiscalização, notificações, regras configuráveis e auditoria.

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
- fotos de evidência vinculadas às fiscalizações;
- não conformidades com gravidade, prazo e encerramento;
- notificações numeradas de irregularidade, advertência e adequação;
- suspensão, embargo e liberação com controle de perfil e status;
- modelos de notificação configuráveis por condomínio;
- prazo padrão por tipo de notificação;
- regras por condomínio para atuação do fiscal;
- checklist personalizado por condomínio;
- documentos adicionais obrigatórios por condomínio;
- convite seguro para responsável pela obra;
- vistoria final e conclusão da obra;
- Termo de Conclusão em PDF;
- Dossiê Digital da Obra em PDF;
- relatórios operacionais e KPIs;
- histórico/linha do tempo unificado;
- instalador de homologação de uso único;
- interface responsiva para desktop e celular.

## Painel do Fiscal

Cada condomínio pode possuir regras próprias, sem alterar código-fonte. O painel permite configurar:

- prazo padrão de notificação;
- prazo de advertência;
- prazo de adequação;
- prazo de suspensão;
- prazo de embargo;
- exigência de fotos em fiscalização e não conformidade;
- exigência de vistoria final;
- bloqueio de conclusão quando houver não conformidade aberta;
- permissão para fiscal emitir advertência e solicitação de adequação;
- modelos de texto, motivo e prazo para cada tipo de notificação;
- itens personalizados do checklist de fiscalização;
- documentos adicionais e obrigatórios do condomínio.

As regras configuradas são aplicadas pelo backend na emissão das notificações e nos fluxos operacionais.

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
14. A obra passa por vistoria de conclusão e pode ser encerrada como `COMPLETED`.
15. Termo de Conclusão e Dossiê Digital ficam disponíveis em PDF.
16. Todas as movimentações relevantes ficam registradas na linha do tempo.

## Segurança

Nunca comite `.env`, senhas, credenciais da Locaweb, dados reais de condomínio ou segredos de implantação no repositório. Os uploads são armazenados fora da pasta pública da aplicação.
