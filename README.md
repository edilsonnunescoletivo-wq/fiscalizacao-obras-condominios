# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, desde documentação até conclusão, com perfis de acesso, fiscalização, notificações, relatórios e auditoria.

## Stack

- PHP 8+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- PDO
- Dompdf para documentos PDF
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
- evidências fotográficas vinculadas às fiscalizações;
- não conformidades com gravidade, prazo e encerramento;
- notificações numeradas de irregularidade, advertência e adequação;
- suspensão, embargo e liberação com controle de perfil e status;
- PDF formal de notificações;
- convite seguro do Responsável da Obra com token expirável;
- vistoria final e fluxo de conclusão da obra;
- bloqueio da conclusão enquanto houver não conformidade aberta;
- Termo de Conclusão da Obra em PDF;
- Dossiê Digital da Obra em PDF com documentos, fiscalizações, evidências, não conformidades, notificações, histórico e conclusão;
- relatórios operacionais com KPIs por condomínio e status;
- histórico/linha do tempo unificado;
- instalador seguro de homologação de uso único;
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
10. Fiscalizações operacionais podem ser registradas com fotos/evidências.
11. Irregularidades podem virar não conformidades com prazo de correção.
12. Notificações podem alterar o estado para `NOTIFIED`, `SUSPENDED` ou `EMBARGOED`.
13. Após regularização, uma liberação retorna a obra para `IN_PROGRESS`.
14. Sem não conformidades abertas, a obra pode entrar em `COMPLETION_INSPECTION`.
15. A vistoria final aprovada altera a obra para `COMPLETED` e libera o Termo de Conclusão e o Dossiê Digital.
16. Todas as movimentações relevantes ficam registradas na linha do tempo.

## Regras operacionais

- Fiscalização e não conformidade só podem ser registradas após o início da obra.
- Suspensão e embargo exigem perfil administrativo e obra em andamento ou notificada.
- Liberação exige perfil administrativo e obra previamente suspensa, embargada ou notificada.
- Fiscal pode emitir notificações operacionais, mas não suspender, embargar ou liberar.
- A vistoria final não pode ser iniciada enquanto houver não conformidade aberta.
- O perfil `WORK_RESPONSIBLE` permanece restrito às obras explicitamente vinculadas, inclusive nos relatórios.

## Homologação Locaweb

Ambiente planejado:

- subdomínio: `fiscalizacao-homolog.sindicosgestao.com.br`;
- document root: `/public_html/fiscalizacao-homolog/public`;
- banco exclusivo: `fiscalizacao1`;
- PHP 8.3;
- banco e credenciais não são versionados no GitHub.

O arquivo `public/install.php` é um instalador de uso único para a homologação. Ele cria a estrutura inicial, registra o primeiro administrador e grava o `.env` diretamente na hospedagem. Após a criação do `.env`, o instalador fica indisponível.

## Segurança

Nunca comite `.env`, senhas, credenciais da Locaweb, dados reais de condomínio ou segredos de implantação no repositório. Os uploads e evidências são armazenados fora da pasta pública da aplicação e servidos apenas por rotas autenticadas com validação de acesso.
