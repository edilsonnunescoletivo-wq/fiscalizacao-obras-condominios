<?php
use App\Core\Csrf;
$resultLabels=['COMPLIANT'=>'Conforme','WITH_ISSUES'=>'Com apontamentos','CRITICAL'=>'Crítica'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aceite eletrônico · Fiscalização #<?=(int)$inspection['id']?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/ui-v2.css">
    <style>
        .acceptance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.acceptance-card{border:1px solid #e4e7ec;border-radius:12px;padding:14px;background:#fff}.acceptance-card small,.acceptance-meta{color:#667085}.acceptance-hash{font-family:monospace;font-size:11px;word-break:break-all;background:#f8fafc;border-radius:8px;padding:8px;margin-top:8px}.acceptance-form{border:1px solid #d0d5dd;border-radius:12px;padding:16px;margin-top:12px}.acceptance-form .declaration{background:#f8fafc;border-radius:9px;padding:12px;margin:10px 0}.acceptance-form label{display:block;margin-top:10px}.acceptance-form input[type=text]{width:100%}.acceptance-warning{font-size:12px;color:#667085;margin-top:8px}
    </style>
</head>
<body>
<main class="container" style="max-width:980px;padding-top:34px;padding-bottom:50px">
<header class="topbar"><div><p class="eyebrow"><?=htmlspecialchars($inspection['condominium_name'])?></p><h1>Aceite eletrônico · Fiscalização #<?=(int)$inspection['id']?></h1><p class="muted">Unidade <?=htmlspecialchars($inspection['unit'])?> · <?=htmlspecialchars($resultLabels[$inspection['result']]??$inspection['result'])?></p></div><div class="inline-actions"><a class="button secondary" href="<?=htmlspecialchars($backUrl)?>">← Voltar</a><a class="button primary" target="_blank" href="/inspection/pdf?id=<?=(int)$inspection['id']?>">Relatório PDF</a></div></header>

<?php if(isset($_GET['signed'])):?><div class="alert success">Aceite eletrônico registrado com sucesso e incluído no histórico da obra.</div><?php endif;?>
<?php if(isset($_GET['signed_existing'])):?><div class="alert success">Este aceite já estava registrado para seu usuário.</div><?php endif;?>

<section class="panel"><div class="panel-header"><div><h2>Resumo da vistoria</h2><p>Confira o registro antes de formalizar seu aceite.</p></div></div><div class="info-grid"><div><small>Fiscal</small><strong><?=htmlspecialchars($inspection['inspector_name'])?></strong></div><div><small>Data da vistoria</small><strong><?=htmlspecialchars($inspection['inspected_at'])?></strong></div><div><small>Etapa</small><strong><?=htmlspecialchars($inspection['stage']?:'-')?></strong></div><div><small>Resultado</small><strong><?=htmlspecialchars($resultLabels[$inspection['result']]??$inspection['result'])?></strong></div><div><small>Proprietário</small><strong><?=htmlspecialchars($inspection['owner_name'])?></strong></div><div><small>Tipo de obra</small><strong><?=htmlspecialchars($inspection['work_type']?:'-')?></strong></div></div><?php if(!empty($inspection['notes'])):?><div class="description-box"><?=nl2br(htmlspecialchars($inspection['notes']))?></div><?php endif;?></section>

<?php if(!$featureReady):?>
<section class="panel"><div class="alert warning"><strong>Recurso preparado, mas ainda não ativado no banco deste ambiente.</strong><br>Assim que a migration correspondente for aplicada, os aceites poderão ser registrados nesta tela.</div></section>
<?php else:?>
<section class="panel"><div class="panel-header"><div><h2>Aceites registrados</h2><p>Os registros abaixo são imutáveis pelo sistema e ficam vinculados à vistoria.</p></div><span class="badge"><?=count($acceptances)?> registro(s)</span></div>
<?php if(!$acceptances):?><div class="empty-state"><p>Nenhum aceite eletrônico registrado nesta vistoria.</p></div><?php else:?><div class="acceptance-grid"><?php foreach($acceptances as $acceptance):?><article class="acceptance-card"><div class="row-gap"><strong><?=htmlspecialchars($typeLabels[$acceptance['signer_type']]??$acceptance['signer_type'])?></strong><span class="badge"><?=htmlspecialchars($acceptance['signed_at'])?></span></div><p><b>Nome informado:</b> <?=htmlspecialchars($acceptance['typed_name'])?></p><p class="acceptance-meta">Conta: <?=htmlspecialchars($acceptance['account_name_snapshot'])?><?=!empty($acceptance['email_snapshot'])?' · '.htmlspecialchars($acceptance['email_snapshot']):''?></p><div class="description-box"><?=htmlspecialchars($acceptance['declaration'])?></div><div class="acceptance-hash"><b><?=htmlspecialchars($acceptance['signature_method'])?></b><br><?=htmlspecialchars($acceptance['signature_hash'])?></div></article><?php endforeach;?></div><?php endif;?>
</section>

<?php if($availableTypes):?>
<section class="panel"><div class="panel-header"><div><h2>Registrar meu aceite</h2><p>O aceite registra identidade da conta, nome digitado, data/hora, trilha de auditoria e impressão digital SHA-256/HMAC quando configurada.</p></div></div>
<?php foreach($availableTypes as $type):?><form class="acceptance-form" method="post" action="/inspection/acceptance/sign"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="inspection_id" value="<?=(int)$inspection['id']?>"><input type="hidden" name="signer_type" value="<?=htmlspecialchars($type)?>"><input type="hidden" name="return_to" value="<?=htmlspecialchars($returnTo)?>"><h3><?=htmlspecialchars($typeLabels[$type]??$type)?></h3><div class="declaration"><?=htmlspecialchars($declarations[$type])?></div><label>Digite seu nome completo<input type="text" name="typed_name" maxlength="150" required autocomplete="name" value="<?=htmlspecialchars((string)($user['name']??''))?>"></label><label><input type="checkbox" name="accept" value="1" required> Confirmo que li a declaração acima e desejo registrar este aceite eletrônico.</label><p class="acceptance-warning">Este aceite eletrônico simples serve para rastreabilidade interna. Ele não é apresentado pelo sistema como assinatura qualificada ICP-Brasil.</p><div class="form-actions"><button class="button primary" type="submit">Registrar aceite como <?=htmlspecialchars($typeLabels[$type]??$type)?></button></div></form><?php endforeach;?>
</section>
<?php elseif($acceptances):?><section class="panel"><div class="alert success">Você não possui outro tipo de aceite pendente para esta vistoria.</div></section><?php endif;?>
<?php endif;?>
</main>
</body></html>
