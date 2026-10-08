# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, da documentação à conclusão, com RBAC, fiscalização configurável, evidências, notificações, correções, auditoria e área própria do responsável pela obra.

## Stack
- PHP 8.3+
- MySQL 8 / MariaDB compatível
- PDO
- HTML/CSS
- Dompdf
- Apache/mod_rewrite
- GitHub Actions
- Hospedagem alvo: Locaweb

## Perfis de acesso
- `ADMIN`
- `SYNDIC`
- `MANAGER`
- `INSPECTOR`
- `WORK_RESPONSIBLE`
- `VIEWER`

O responsável pela obra possui portal simplificado e, quando esse é seu único perfil, não acessa os painéis administrativos. A autorização de recursos sensíveis é validada também no nível da obra, não apenas do condomínio.

## Funcionalidades implementadas
- autenticação, sessão, CSRF e logout via POST;
- dashboard multi-condomínio;
- RBAC e política centralizada de acesso por obra;
- cadastro de obras e linha do tempo;
- documentos obrigatórios por condomínio, versionamento, análise e download autenticado;
- aprovação técnica, autorização administrativa e início da obra;
- fiscalizações com checklist dinâmico e evidências;
- fotos de fiscalização protegidas por autorização e contenção de caminho;
- Painel do Fiscal configurável por condomínio;
- não conformidades com gravidade, prazo e foto obrigatória configurável;
- sugestão de ação por gravidade sem aplicação automática de restrições;
- fluxo de correção com descrição/evidência e validação/devolução pelo fiscal;
- notificações de irregularidade, advertência, adequação, suspensão, embargo e liberação;
- modelos e prazos de notificação configuráveis por condomínio;
- numeração sequencial de notificações por condomínio e ano;
- central de notificações com entrega, resolução e cancelamento;
- suspensão/embargo só são encerrados após liberação formal da obra;
- PDFs formais de notificação;
- convite de uso único por 72 horas para o Responsável da Obra;
- portal do Responsável da Obra com documentos, correções, prazos, notificações e conclusão;
- vistoria final configurável por condomínio;
- bloqueio de conclusão por pendência não encerrada, quando configurado;
- conclusão administrativa quando o condomínio dispensa vistoria final;
- Termo de Conclusão em PDF;
- Dossiê Digital Final em PDF somente para obra concluída;
- relatórios e KPIs por condomínio/status;
- trilha de auditoria para operações e configurações relevantes.

## Painel do Fiscal
Cada condomínio pode configurar independentemente:
- prazo padrão de notificação;
- prazo de advertência, adequação, suspensão e embargo;
- permissão do fiscal para advertir ou solicitar adequação;
- foto obrigatória na fiscalização;
- foto obrigatória na não conformidade;
- vistoria final obrigatória ou dispensada;
- bloqueio de conclusão com não conformidade pendente;
- modelos de motivo/texto por tipo de notificação;
- documentos exigidos;
- checklist de fiscalização, ordem, obrigatoriedade e ativação;
- ação sugerida para gravidade leve, moderada, grave e crítica.

## Fluxo operacional
1. Cadastrar a obra.
2. Enviar e analisar documentos obrigatórios.
3. Aprovar tecnicamente.
4. Autorizar administrativamente.
5. Registrar início da obra.
6. Fiscalizar usando o checklist do condomínio.
7. Registrar não conformidades e evidências quando necessário.
8. Consultar a ação sugerida pela regra de gravidade.
9. Emitir notificações ou restrições conforme permissão.
10. Responsável informa a correção e anexa evidência.
11. Fiscal valida ou devolve a correção.
12. Encerrar pendências.
13. Realizar vistoria final, quando exigida, ou conclusão administrativa, quando dispensada.
14. Gerar Termo de Conclusão e Dossiê Digital Final.

## Banco e migrations
As migrations ficam em `database/migrations/` e são executadas em ordem natural na instalação inicial.

Baseline atual:
- `001_initial.sql`
- `002_workflow.sql`
- `003_operations.sql`
- `004_media_invites.sql`
- `005_completion.sql`
- `006_condominium_rules.sql`
- `006_fiscal_settings.sql`
- `007_fiscal_workflow_rules.sql`
- `008_notification_sequences.sql`
- `009_schema_migrations.sql`

A migration `009` cria `schema_migrations` e registra a baseline. Em instalações já inicializadas com essa baseline, atualizações futuras podem ser aplicadas de forma controlada por CLI:

```bash
php scripts/migrate.php
```

O runner interrompe no primeiro erro e não deve ser reexecutado às cegas após falha de DDL parcial.

## Validação contínua
O workflow `.github/workflows/validate.yml` executa a cada alteração da branch/PR:
- `composer validate`;
- instalação das dependências;
- lint de PHP 8.3 em controllers, configuração, scripts, rotas e views;
- MySQL 8 temporário;
- aplicação real de todas as migrations;
- validação das tabelas, perfis, índices e histórico de migrations.

## Homologação Locaweb
Ambiente isolado deste projeto:
- banco: `fiscalizacao1`;
- host MySQL configurado no instalador: `fiscalizacao1.mysql.dbaas.com.br`;
- pasta: `/public_html/fiscalizacao-homolog`;
- document root: `/public_html/fiscalizacao-homolog/public`;
- subdomínio: `fiscalizacao-homolog.sindicosgestao.com.br`;
- PHP alvo: 8.3;
- instalador de uso único: `public/install.php`.

A versão atualmente presente na Locaweb pode ficar defasada durante o desenvolvimento. Antes da primeira instalação do banco, publicar o snapshot mais recente da branch para garantir que todas as migrations da baseline sejam executadas.

## Segurança
- nunca comitar `.env`, senhas, tokens ou credenciais da Locaweb;
- uploads ficam fora do document root público;
- documentos, fotos e evidências são entregues por rotas autenticadas;
- leitura de arquivos usa contenção por `realpath` e validação de MIME;
- usuários `WORK_RESPONSIBLE` são limitados à obra vinculada;
- operações críticas usam CSRF, autorização de função/obra e trilha de auditoria;
- PDFs também validam acesso à obra;
- o instalador se bloqueia automaticamente após a criação do `.env`.

## Pendências antes de uso com dados reais
- validar o fluxo ponta a ponta na homologação Locaweb após a primeira instalação;
- configurar envio automático de convite por e-mail/SMTP sem versionar credenciais;
- manter o repositório sem dados reais ou segredos; avaliar torná-lo privado antes de operar com dados de clientes.
