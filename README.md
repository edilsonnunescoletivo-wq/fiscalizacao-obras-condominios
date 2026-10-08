# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, desde documentação até conclusão, com perfis de acesso, fiscalização, notificações, correções e auditoria.

## Stack
- PHP 8+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- PDO
- Dompdf
- Hospedagem alvo: Locaweb

## Implementado
- autenticação, sessão, CSRF e PDO;
- dashboard multi-condomínio e RBAC;
- cadastro e acompanhamento de obras;
- documentos obrigatórios e versionamento;
- aprovação técnica, autorização administrativa e início;
- fiscalizações operacionais com checklist e evidências;
- Painel do Fiscal configurável por condomínio;
- prazos de notificação por tipo;
- modelos de motivo/texto de notificações;
- documentos obrigatórios específicos por condomínio;
- checklist de fiscalização configurável, ordenável, obrigatório/opcional e ativável/desativável;
- exigência configurável de foto em fiscalização;
- regras sugeridas por gravidade: nenhuma, advertência, adequação, suspensão ou embargo;
- regras por gravidade são apenas sugestões e não executam restrições automaticamente;
- não conformidades com prazo e gravidade;
- fluxo de correção pelo Responsável da Obra com descrição e evidência JPG/PNG/PDF;
- validação da correção pelo fiscal, com aprovação ou devolução para novo ajuste;
- notificações, advertência, adequação, suspensão, embargo e liberação;
- PDFs formais de notificação;
- fotos de fiscalização com acesso autenticado;
- convite seguro para Responsável da Obra;
- conclusão, vistoria final, Termo de Conclusão e Dossiê Digital em PDF;
- relatórios e KPIs por condomínio/status;
- linha do tempo unificada da obra.

## Fluxo operacional
1. Cadastro da obra.
2. Envio e aprovação dos documentos obrigatórios.
3. Aprovação técnica.
4. Autorização administrativa.
5. Início da obra.
6. Fiscalização com checklist configurado para o condomínio.
7. Registro de não conformidade quando necessário.
8. O sistema consulta a regra de gravidade e apresenta a ação sugerida.
9. Responsável da Obra informa a correção e anexa evidências.
10. Fiscal valida a correção ou devolve para novo ajuste.
11. Notificações e restrições seguem as permissões e regras do condomínio.
12. Vistoria final e conclusão.
13. Geração do Termo de Conclusão e Dossiê Digital.

## Painel do Fiscal
Cada condomínio possui configuração independente para:
- prazo padrão de notificação;
- prazo de advertência, adequação, suspensão e embargo;
- autorização do fiscal para advertir ou solicitar adequação;
- foto obrigatória na fiscalização;
- foto obrigatória em não conformidade;
- vistoria final obrigatória;
- bloqueio da conclusão com não conformidade aberta;
- modelos de notificação;
- documentos exigidos;
- checklist de fiscalização;
- ação sugerida por gravidade.

## Homologação Locaweb
- PHP 8.3 identificado;
- banco isolado `fiscalizacao1` ativo;
- arquivos em `/public_html/fiscalizacao-homolog`;
- subdomínio alvo `fiscalizacao-homolog.sindicosgestao.com.br`;
- document root planejado: `/public_html/fiscalizacao-homolog/public`;
- instalador de uso único: `public/install.php`;
- validação ponta a ponta das últimas migrations e telas ainda depende da ativação completa do subdomínio.

## Segurança
Nunca comitar `.env`, senhas, credenciais da Locaweb, dados reais de condomínio ou segredos de implantação. Uploads permanecem fora da pasta pública e são entregues por rotas autenticadas.
