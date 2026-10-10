<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;
use Dompdf\Dompdf;
use Dompdf\Options;

final class CompletionPdfController
{
    public function show(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        if (!class_exists(Dompdf::class)) { http_response_code(500); exit('Dependência de PDF não instalada. Execute composer install.'); }
        $user = Auth::user();
        $workId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT ct.*,u.name inspector_name,w.unit,w.owner_name,w.work_type,w.company_name,w.technical_name,w.technical_registry,c.id condominium_id,c.name condominium_name,c.cnpj,c.address,c.city,c.state FROM work_completion_terms ct JOIN works w ON w.id=ct.work_id JOIN condominiums c ON c.id=w.condominium_id JOIN users u ON u.id=ct.inspector_user_id WHERE ct.work_id=? AND ct.result="APPROVED"');
        $stmt->execute([$workId]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); exit('Termo de conclusão não disponível.'); }

        WorkAccess::load($workId, (int)$user['id']);

        $date = $row['completed_at'] ? date('d/m/Y H:i', strtotime($row['completed_at'])) : date('d/m/Y H:i');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:12px;line-height:1.55}h1{text-align:center;font-size:20px;margin:25px 0 4px}.meta{text-align:center;color:#666;margin-bottom:24px}.box{border:1px solid #bbb;padding:12px;margin:12px 0}.label{font-size:10px;color:#666;text-transform:uppercase}.value{font-size:13px;margin:3px 0 10px}.footer{margin-top:45px;border-top:1px solid #bbb;padding-top:12px;color:#555}</style></head><body>';
        $html .= '<h1>Termo de Conclusão da Obra</h1><div class="meta">Conclusão registrada em '.$date.'</div>';
        $html .= '<div class="box"><div class="label">Condomínio</div><div class="value">'.htmlspecialchars($row['condominium_name']).'</div><div class="label">Unidade / Proprietário</div><div class="value">'.htmlspecialchars($row['unit']).' · '.htmlspecialchars($row['owner_name']).'</div><div class="label">Tipo de obra</div><div class="value">'.htmlspecialchars($row['work_type'] ?: '-').'</div></div>';
        $html .= '<div class="box"><div class="label">Empresa responsável</div><div class="value">'.htmlspecialchars($row['company_name'] ?: '-').'</div><div class="label">Responsável técnico</div><div class="value">'.htmlspecialchars(($row['technical_name'] ?: '-') . ($row['technical_registry'] ? ' · '.$row['technical_registry'] : '')).'</div></div>';
        $html .= '<div class="box"><div class="label">Resultado</div><div class="value">A obra foi aprovada em vistoria final e registrada como concluída.</div><div class="label">Fiscal responsável</div><div class="value">'.htmlspecialchars($row['inspector_name']).'</div><div class="label">Observações</div><div class="value">'.nl2br(htmlspecialchars($row['notes'] ?: 'Sem observações adicionais.')).'</div></div>';
        $html .= '<div class="footer">Documento gerado eletronicamente pelo sistema Fiscaliza Obras e vinculado ao histórico da unidade '.htmlspecialchars($row['unit']).'.</div></body></html>';
        $options = new Options(); $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4'); $pdf->render();
        $pdf->stream('termo-conclusao-unidade-'.preg_replace('/[^0-9A-Za-z_-]/','-',$row['unit']).'.pdf',['Attachment'=>false]);
    }
}
