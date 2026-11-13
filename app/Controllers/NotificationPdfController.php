<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;
use Dompdf\Dompdf;
use Dompdf\Options;

final class NotificationPdfController
{
    public function show(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            exit('Dependência de PDF não instalada. Execute composer install.');
        }

        $user = Auth::user();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT n.*, w.unit, w.owner_name, w.work_type, c.id condominium_id, c.name condominium_name, c.cnpj, c.address, c.city, c.state FROM notifications n JOIN works w ON w.id=n.work_id JOIN condominiums c ON c.id=w.condominium_id WHERE n.id=?');
        $stmt->execute([$id]);
        $n = $stmt->fetch();
        if (!$n) {
            http_response_code(404);
            exit('Notificação não encontrada.');
        }

        WorkAccess::load((int)$n['work_id'], (int)$user['id']);

        $typeLabels = [
            'IRREGULARITY'=>'Notificação de Irregularidade','WARNING'=>'Advertência','ADJUSTMENT'=>'Solicitação de Adequação',
            'SUSPENSION'=>'Suspensão de Obra','EMBARGO'=>'Embargo de Obra','RELEASE'=>'Liberação de Obra'
        ];
        $title = $typeLabels[$n['type']] ?? 'Notificação';
        $deadline = $n['deadline'] ? date('d/m/Y H:i', strtotime($n['deadline'])) : 'Sem prazo definido';
        $issued = $n['issued_at'] ? date('d/m/Y H:i', strtotime($n['issued_at'])) : date('d/m/Y H:i');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:12px}h1{text-align:center;font-size:20px;margin:28px 0 6px}.meta{color:#555;text-align:center;margin-bottom:24px}.box{border:1px solid #bbb;border-radius:6px;padding:12px;margin:12px 0}.label{font-size:10px;color:#666;text-transform:uppercase}.value{font-size:13px;margin-top:3px}.body{line-height:1.55;white-space:pre-wrap}.footer{margin-top:50px;border-top:1px solid #bbb;padding-top:12px;color:#555}</style></head><body>';
        $html .= '<h1>'.htmlspecialchars($title).'</h1><div class="meta">Nº '.htmlspecialchars($n['number']).' · Emitida em '.$issued.'</div>';
        $html .= '<div class="box"><div class="label">Condomínio</div><div class="value">'.htmlspecialchars($n['condominium_name']).'</div><div class="label" style="margin-top:8px">Unidade / Proprietário</div><div class="value">'.htmlspecialchars($n['unit']).' · '.htmlspecialchars($n['owner_name']).'</div></div>';
        $html .= '<div class="box"><div class="label">Motivo</div><div class="value">'.htmlspecialchars($n['reason']).'</div><div class="label" style="margin-top:8px">Prazo</div><div class="value">'.$deadline.'</div></div>';
        $html .= '<div class="box body">'.nl2br(htmlspecialchars($n['body'])).'</div>';
        $html .= '<div class="footer">Documento gerado eletronicamente pelo sistema Fiscaliza Obras. Registro vinculado à obra da unidade '.htmlspecialchars($n['unit']).'.</div></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->stream('notificacao-' . preg_replace('/[^0-9A-Za-z_-]/', '-', $n['number']) . '.pdf', ['Attachment' => false]);
    }
}
