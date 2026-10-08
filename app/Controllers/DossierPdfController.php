<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class DossierPdfController
{
    public function show(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        if (!class_exists(Dompdf::class)) { http_response_code(500); exit('Dependência de PDF não instalada. Execute composer install.'); }
        $user = Auth::user();
        $workId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT w.*,c.name condominium_name,c.cnpj,c.address,c.city,c.state FROM works w JOIN condominiums c ON c.id=w.condominium_id WHERE w.id=?');
        $stmt->execute([$workId]);
        $work = $stmt->fetch();
        if (!$work) { http_response_code(404); exit('Obra não encontrada.'); }
        $stmt = $pdo->prepare('SELECT 1 FROM condominium_user WHERE condominium_id=? AND user_id=? AND active=1 LIMIT 1');
        $stmt->execute([$work['condominium_id'],$user['id']]);
        if (!$stmt->fetchColumn()) { http_response_code(403); exit('Acesso não autorizado.'); }

        $stmt = $pdo->prepare('SELECT dt.name,wd.version,wd.original_name,wd.status,wd.uploaded_at,wd.reviewed_at FROM work_documents wd JOIN document_types dt ON dt.id=wd.document_type_id WHERE wd.work_id=? ORDER BY dt.name,wd.version');
        $stmt->execute([$workId]); $documents = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT i.*,u.name inspector_name,(SELECT COUNT(*) FROM inspection_photos p WHERE p.inspection_id=i.id) photo_count FROM inspections i JOIN users u ON u.id=i.inspector_user_id WHERE i.work_id=? ORDER BY i.inspected_at');
        $stmt->execute([$workId]); $inspections = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT nc.* FROM non_conformities nc WHERE nc.work_id=? ORDER BY nc.created_at');
        $stmt->execute([$workId]); $ncs = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT n.* FROM notifications n WHERE n.work_id=? ORDER BY n.created_at');
        $stmt->execute([$workId]); $notifications = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT we.*,u.name user_name FROM work_events we LEFT JOIN users u ON u.id=we.user_id WHERE we.work_id=? ORDER BY we.created_at');
        $stmt->execute([$workId]); $events = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT ct.*,u.name inspector_name FROM work_completion_terms ct JOIN users u ON u.id=ct.inspector_user_id WHERE ct.work_id=?');
        $stmt->execute([$workId]); $completion = $stmt->fetch() ?: null;

        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:10px;line-height:1.45}h1{font-size:20px;text-align:center;margin:18px 0 4px}h2{font-size:14px;border-bottom:1px solid #bbb;padding-bottom:5px;margin-top:20px}.meta{text-align:center;color:#666;margin-bottom:18px}.box{border:1px solid #ccc;padding:9px;margin:8px 0}.row{margin:4px 0}.muted{color:#666}.item{border-bottom:1px solid #e5e5e5;padding:7px 0}.footer{margin-top:28px;border-top:1px solid #bbb;padding-top:8px;color:#666}</style></head><body>';
        $html .= '<h1>Dossiê Digital da Obra</h1><div class="meta">Gerado em '.date('d/m/Y H:i').'</div>';
        $html .= '<div class="box"><div class="row"><b>Condomínio:</b> '.$e($work['condominium_name']).'</div><div class="row"><b>Unidade:</b> '.$e($work['unit']).' · <b>Proprietário:</b> '.$e($work['owner_name']).'</div><div class="row"><b>Tipo:</b> '.$e($work['work_type'] ?: '-').' · <b>Status:</b> '.$e($work['status']).'</div><div class="row"><b>Empresa:</b> '.$e($work['company_name'] ?: '-').' · <b>Responsável técnico:</b> '.$e($work['technical_name'] ?: '-').'</div></div>';

        $html .= '<h2>Documentos</h2>';
        if (!$documents) $html .= '<div class="muted">Nenhum documento registrado.</div>';
        foreach ($documents as $d) $html .= '<div class="item"><b>'.$e($d['name']).'</b> · v'.$e($d['version']).' · '.$e($d['status']).'<br>'.$e($d['original_name']).' <span class="muted">· enviado em '.$e($d['uploaded_at']).'</span></div>';

        $html .= '<h2>Fiscalizações e evidências</h2>';
        if (!$inspections) $html .= '<div class="muted">Nenhuma fiscalização registrada.</div>';
        foreach ($inspections as $i) $html .= '<div class="item"><b>Fiscalização #'.$e($i['id']).'</b> · '.$e($i['result']).' · '.$e($i['inspected_at']).'<br>Fiscal: '.$e($i['inspector_name']).' · Etapa: '.$e($i['stage'] ?: '-').' · Fotos: '.$e($i['photo_count']).'<br>'.$e($i['notes'] ?: '').'</div>';

        $html .= '<h2>Não conformidades</h2>';
        if (!$ncs) $html .= '<div class="muted">Nenhuma não conformidade registrada.</div>';
        foreach ($ncs as $n) $html .= '<div class="item"><b>'.$e($n['title']).'</b> · '.$e($n['severity']).' · '.$e($n['status']).'<br>'.$e($n['description']).'</div>';

        $html .= '<h2>Notificações</h2>';
        if (!$notifications) $html .= '<div class="muted">Nenhuma notificação registrada.</div>';
        foreach ($notifications as $n) $html .= '<div class="item"><b>'.$e($n['number']).'</b> · '.$e($n['type']).' · '.$e($n['status']).'<br>'.$e($n['reason']).'</div>';

        $html .= '<h2>Histórico</h2>';
        if (!$events) $html .= '<div class="muted">Nenhum evento registrado.</div>';
        foreach ($events as $ev) $html .= '<div class="item"><b>'.$e($ev['title']).'</b> · '.$e($ev['created_at']).'<br><span class="muted">'.$e($ev['user_name'] ?: 'Sistema').'</span>'.($ev['description'] ? '<br>'.$e($ev['description']) : '').'</div>';

        $html .= '<h2>Conclusão</h2>';
        if ($completion) $html .= '<div class="box"><b>Resultado:</b> '.$e($completion['result']).'<br><b>Fiscal:</b> '.$e($completion['inspector_name']).'<br><b>Data:</b> '.$e($completion['completed_at'] ?: $completion['updated_at']).'<br>'.$e($completion['notes'] ?: 'Sem observações adicionais.').'</div>'; else $html .= '<div class="muted">Obra ainda sem termo de conclusão.</div>';
        $html .= '<div class="footer">Dossiê consolidado automaticamente pelo sistema Fiscaliza Obras. Os arquivos originais permanecem armazenados no sistema e sujeitos às permissões de acesso da obra.</div></body></html>';

        $options = new Options(); $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4'); $pdf->render();
        $pdf->stream('dossie-obra-unidade-'.preg_replace('/[^0-9A-Za-z_-]/','-',$work['unit']).'.pdf',['Attachment'=>false]);
    }
}
