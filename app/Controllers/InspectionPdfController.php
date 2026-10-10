<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;
use Dompdf\Dompdf;
use Dompdf\Options;

final class InspectionPdfController
{
    public function show(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            exit('Dependência de PDF não instalada.');
        }

        $user = Auth::user();
        $inspectionId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT i.*,u.name inspector_name,w.id work_id,w.unit,w.owner_name,w.work_type,w.company_name,w.technical_name,w.technical_registry,w.condominium_id,c.name condominium_name,c.address,c.city,c.state
             FROM inspections i
             JOIN users u ON u.id=i.inspector_user_id
             JOIN works w ON w.id=i.work_id
             JOIN condominiums c ON c.id=w.condominium_id
             WHERE i.id=?'
        );
        $stmt->execute([$inspectionId]);
        $inspection = $stmt->fetch();
        if (!$inspection) {
            http_response_code(404);
            exit('Fiscalização não encontrada.');
        }

        WorkAccess::load((int)$inspection['work_id'], (int)$user['id']);

        $photoStmt = $pdo->prepare('SELECT id,original_name,stored_path,caption,created_at FROM inspection_photos WHERE inspection_id=? ORDER BY id');
        $photoStmt->execute([$inspectionId]);
        $photos = $photoStmt->fetchAll();

        $acceptances = [];
        if ($pdo->query("SHOW TABLES LIKE 'inspection_acceptances'")->fetchColumn()) {
            $acceptanceStmt = $pdo->prepare('SELECT signer_type,account_name_snapshot,typed_name,declaration,signature_method,signature_hash,signed_at FROM inspection_acceptances WHERE inspection_id=? ORDER BY signed_at,id');
            $acceptanceStmt->execute([$inspectionId]);
            $acceptances = $acceptanceStmt->fetchAll();
        }

        $checklist = json_decode((string)($inspection['checklist_json'] ?? ''), true);
        if (!is_array($checklist)) $checklist = [];

        $resultLabels = [
            'COMPLIANT'=>'Conforme',
            'WITH_ISSUES'=>'Com apontamentos',
            'CRITICAL'=>'Crítica',
        ];
        $typeLabels = [
            'INSPECTOR'=>'Fiscal',
            'WORK_RESPONSIBLE'=>'Responsável pela obra',
            'MANAGEMENT'=>'Gestão',
        ];
        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;color:#1d2939;font-size:10px;line-height:1.45;margin:26px}'
            . 'h1{font-size:20px;margin:0 0 2px}.sub{color:#667085;margin-bottom:16px}.box{border:1px solid #d0d5dd;border-radius:7px;padding:10px;margin:8px 0}.grid{width:100%;border-collapse:collapse}.grid td{width:50%;vertical-align:top;padding:5px 8px;border-bottom:1px solid #eaecf0}.label{font-size:8px;text-transform:uppercase;color:#667085}.value{font-size:10px;font-weight:700}.result{display:inline-block;padding:5px 8px;border:1px solid #98a2b3;border-radius:10px;font-weight:700}.section{font-size:13px;border-bottom:1px solid #d0d5dd;padding-bottom:4px;margin:18px 0 8px}.check{width:100%;border-collapse:collapse}.check th,.check td{border:1px solid #e4e7ec;padding:6px;text-align:left}.check th{background:#f9fafb}.ok{font-weight:700}.notes{white-space:pre-wrap}.photos{width:100%;border-collapse:separate;border-spacing:6px}.photo-cell{width:50%;vertical-align:top;border:1px solid #e4e7ec;padding:6px}.photo-cell img{display:block;max-width:100%;max-height:250px;margin:0 auto 5px}.acceptance{border:1px solid #d0d5dd;border-radius:7px;padding:9px;margin:7px 0}.acceptance-title{font-weight:700;font-size:11px}.acceptance-meta{color:#667085;font-size:8px}.acceptance-hash{font-family:DejaVu Sans Mono,monospace;font-size:7px;word-break:break-all;background:#f8fafc;padding:5px;margin-top:5px}.footer{margin-top:20px;border-top:1px solid #d0d5dd;padding-top:7px;color:#667085;font-size:8px}'
            . '</style></head><body>';
        $html .= '<h1>Relatório de Fiscalização de Obra</h1>';
        $html .= '<div class="sub">Fiscaliza Obras · Vistoria #'.$inspectionId.' · Emitido em '.date('d/m/Y H:i').'</div>';
        $html .= '<div class="box"><table class="grid">'
            . '<tr><td><div class="label">Condomínio</div><div class="value">'.$e($inspection['condominium_name']).'</div></td><td><div class="label">Unidade</div><div class="value">'.$e($inspection['unit']).'</div></td></tr>'
            . '<tr><td><div class="label">Proprietário</div><div class="value">'.$e($inspection['owner_name']).'</div></td><td><div class="label">Tipo de obra</div><div class="value">'.$e($inspection['work_type'] ?: '-').'</div></td></tr>'
            . '<tr><td><div class="label">Empresa</div><div class="value">'.$e($inspection['company_name'] ?: '-').'</div></td><td><div class="label">Responsável técnico</div><div class="value">'.$e($inspection['technical_name'] ?: '-').' '.$e($inspection['technical_registry'] ?: '').'</div></td></tr>'
            . '<tr><td><div class="label">Fiscal</div><div class="value">'.$e($inspection['inspector_name']).'</div></td><td><div class="label">Data da vistoria</div><div class="value">'.$e($inspection['inspected_at']).'</div></td></tr>'
            . '<tr><td><div class="label">Etapa</div><div class="value">'.$e($inspection['stage'] ?: '-').'</div></td><td><div class="label">Resultado</div><div class="value"><span class="result">'.$e($resultLabels[$inspection['result']] ?? $inspection['result']).'</span></div></td></tr>'
            . '</table></div>';

        $html .= '<div class="section">Checklist</div>';
        if (!$checklist) {
            $html .= '<div class="box">Nenhum item de checklist estava configurado nesta vistoria.</div>';
        } else {
            $html .= '<table class="check"><thead><tr><th>Categoria</th><th>Item</th><th>Obrigatório</th><th>Resultado</th></tr></thead><tbody>';
            foreach ($checklist as $item) {
                $html .= '<tr><td>'.$e($item['category'] ?? '-').'</td><td>'.$e($item['label'] ?? '-').'</td><td>'.(!empty($item['required'])?'Sim':'Não').'</td><td class="ok">'.(!empty($item['checked'])?'Conforme':'Não confirmado').'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="section">Observações</div><div class="box notes">'.($inspection['notes'] ? nl2br($e($inspection['notes'])) : 'Sem observações registradas.').'</div>';
        $html .= '<div class="section">Evidências fotográficas</div>';
        if (!$photos) {
            $html .= '<div class="box">Nenhuma evidência fotográfica anexada a esta vistoria.</div>';
        } else {
            $html .= '<table class="photos">';
            foreach (array_chunk($photos, 2) as $row) {
                $html .= '<tr>';
                foreach ($row as $photo) {
                    $dataUri = $this->photoDataUri((string)$photo['stored_path']);
                    $html .= '<td class="photo-cell">';
                    if ($dataUri !== null) $html .= '<img src="'.$dataUri.'">';
                    $html .= '<b>'.$e($photo['caption'] ?: 'Evidência').'</b><br><span>'.$e($photo['original_name']).'</span>';
                    $html .= '</td>';
                }
                if (count($row) === 1) $html .= '<td class="photo-cell"></td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        if ($acceptances) {
            $html .= '<div class="section">Aceites eletrônicos</div>';
            foreach ($acceptances as $acceptance) {
                $html .= '<div class="acceptance">'
                    . '<div class="acceptance-title">'.$e($typeLabels[$acceptance['signer_type']] ?? $acceptance['signer_type']).' · '.$e($acceptance['typed_name']).'</div>'
                    . '<div class="acceptance-meta">Conta identificada como '.$e($acceptance['account_name_snapshot']).' · Registrado em '.$e($acceptance['signed_at']).'</div>'
                    . '<div>'.$e($acceptance['declaration']).'</div>'
                    . '<div class="acceptance-hash">'.$e($acceptance['signature_method']).': '.$e($acceptance['signature_hash']).'</div>'
                    . '</div>';
            }
        }

        $html .= '<div class="footer">Relatório gerado automaticamente a partir dos registros da vistoria. O histórico e os arquivos originais permanecem armazenados no sistema conforme as permissões da obra. Aceites eletrônicos simples servem à rastreabilidade interna e não são apresentados pelo sistema como assinatura qualificada ICP-Brasil.</div></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $name = 'fiscalizacao-'.$inspectionId.'-unidade-'.preg_replace('/[^0-9A-Za-z_-]/','-', (string)$inspection['unit']).'.pdf';
        $pdf->stream($name, ['Attachment'=>false]);
    }

    private function photoDataUri(string $storedPath): ?string
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) return null;
        $path = realpath($root . '/' . ltrim($storedPath, '/'));
        if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) return null;
        $bytes = file_get_contents($path);
        if ($bytes === false) return null;
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
