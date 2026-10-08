# Fiscalização de Obras Condominiais

Sistema web multi-condomínio para controlar o ciclo completo de obras, desde documentação até conclusão, com perfis de acesso, fiscalização, notificações e auditoria.

## Stack

- PHP 8+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- PDO
- Hospedagem alvo: Locaweb

## Implementado na base inicial

- autenticação com sessão;
- proteção CSRF;
- acesso ao banco via PDO;
- dashboard multi-condomínio;
- perfis e vínculos por condomínio;
- listagem de obras por condomínio;
- cadastro de nova obra;
- cadastro de proprietário, empresa executora e responsável técnico;
- tipo, descrição e período previsto da obra;
- status inicial `WAITING_DOCUMENTS` após cadastro;
- restrição do Responsável da Obra às obras vinculadas ao seu usuário;
- estrutura de documentos versionados, fiscalizações, notificações e auditoria;
- interface responsiva para desktop e celular.

## Fluxo atual

1. O usuário entra no painel geral.
2. Seleciona um condomínio.
3. Visualiza as obras do empreendimento.
4. Administrador, Síndico, Gerente e Fiscal podem cadastrar uma nova obra.
5. A nova obra entra em `WAITING_DOCUMENTS`.
6. O Responsável da Obra visualiza apenas registros vinculados ao seu usuário.

## Segurança

Nunca comite `.env`, senhas, credenciais da Locaweb, dados reais de condomínio ou segredos de implantação no repositório.
