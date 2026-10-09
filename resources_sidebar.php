<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\WorkAccess;

$sidebarCondoId = (int)($sidebarCondoId ?? ($condominium['id'] ?? ($work['condominium_id'] ?? 0)));
$sidebarWorkId = (int)($sidebarWorkId ?? ($work['id'] ?? 0));
$sidebarWorkStatus = (string)($sidebarWorkStatus ?? ($work['status'] ?? ''));
$sidebarActive = (string)($sidebarActive ?? '');
$sidebarUserId = Auth::check() ? (int)(Auth::user()['id'] ?? 0) : 0;
$sidebarRoles = $sidebarRoles ?? ($sidebarCondoId > 0 && $sidebarUserId > 0 ? WorkAccess::rolesForCondo($sidebarCondoId, $sidebarUserId) : []);
$sidebarCanInspect = WorkAccess::canInspect($sidebarRoles);
$sidebarCanManage = WorkAccess::canManage($sidebarRoles);

function sidebarClass(string $key, string $active): string {
    return $key === $active ? 'active' : '';
}
?>
<button type="button" class="sidebar-mobile-toggle" aria-label="Abrir menu" onclick="document.body.classList.toggle('sidebar-open')">☰ Menu</button>
<aside class="sidebar">
    <div class="sidebar-head">
        <div class="brand">Fiscaliza Obras</div>
        <button type="button" class="sidebar-close" aria-label="Fechar menu" onclick="document.body.classList.remove('sidebar-open')">×</button>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-group">
            <span class="sidebar-group-title">Visão geral</span>
            <a class="<?=sidebarClass('dashboard',$sidebarActive)?>" href="/">Todos os condomínios</a>
            <?php if($sidebarCondoId > 0): ?>
                <a class="<?=sidebarClass('condo',$sidebarActive)?>" href="/works?condo=<?=$sidebarCondoId?>">Painel do condomínio</a>
                <a class="<?=sidebarClass('works',$sidebarActive)?>" href="/works?condo=<?=$sidebarCondoId?>#obras">Obras</a>
            <?php endif; ?>
        </div>

        <?php if($sidebarCondoId > 0): ?>
        <div class="sidebar-group">
            <span class="sidebar-group-title">Fiscalização</span>
            <?php if($sidebarWorkId > 0 && $sidebarCanInspect): ?>
                <a class="<?=sidebarClass('inspections',$sidebarActive)?>" href="/inspection/new?work=<?=$sidebarWorkId?>">Fiscalizações</a>
            <?php else: ?>
                <a class="<?=sidebarClass('inspections',$sidebarActive)?>" href="/reports?condo=<?=$sidebarCondoId?>">Fiscalizações</a>
            <?php endif; ?>
            <a class="<?=sidebarClass('corrections',$sidebarActive)?>" href="<?=$sidebarWorkId > 0 ? '/corrections?work='.$sidebarWorkId : '/reports?condo='.$sidebarCondoId?>">Não conformidades / Correções</a>
            <a class="<?=sidebarClass('notifications',$sidebarActive)?>" href="<?=$sidebarWorkId > 0 ? '/notifications?work='.$sidebarWorkId : '/reports?condo='.$sidebarCondoId?>">Notificações</a>
        </div>

        <div class="sidebar-group">
            <span class="sidebar-group-title">Documentação</span>
            <a class="<?=sidebarClass('documents',$sidebarActive)?>" href="<?=$sidebarWorkId > 0 ? '/work?id='.$sidebarWorkId.'#documentos' : '/works?condo='.$sidebarCondoId.'#obras'?>">Documentos</a>
            <a class="<?=sidebarClass('completion',$sidebarActive)?>" href="<?=$sidebarWorkId > 0 ? '/work/completion?id='.$sidebarWorkId : '/reports?condo='.$sidebarCondoId.'&status=COMPLETED'?>">Conclusões</a>
            <?php if($sidebarWorkId > 0 && $sidebarWorkStatus === 'COMPLETED'): ?>
                <a class="<?=sidebarClass('dossier',$sidebarActive)?>" target="_blank" href="/work/dossier?id=<?=$sidebarWorkId?>">Dossiê digital</a>
            <?php else: ?>
                <a class="<?=sidebarClass('dossier',$sidebarActive)?>" href="/reports?condo=<?=$sidebarCondoId?>&status=COMPLETED">Dossiês digitais</a>
            <?php endif; ?>
        </div>

        <div class="sidebar-group">
            <span class="sidebar-group-title">Gestão</span>
            <a class="<?=sidebarClass('reports',$sidebarActive)?>" href="/reports?condo=<?=$sidebarCondoId?>">Relatórios</a>
            <?php if($sidebarCanManage): ?>
                <a class="<?=sidebarClass('users',$sidebarActive)?>" href="/settings?condo=<?=$sidebarCondoId?>#acessos">Usuários / Permissões</a>
            <?php endif; ?>
            <?php if($sidebarCanInspect): ?>
                <a class="<?=sidebarClass('settings',$sidebarActive)?>" href="/settings?condo=<?=$sidebarCondoId?>">Configurações do condomínio</a>
                <a class="<?=sidebarClass('fiscal',$sidebarActive)?>" href="/settings?condo=<?=$sidebarCondoId?>#fiscalizacao">Painel do Fiscal</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="sidebar-group sidebar-bottom-group">
            <span class="sidebar-group-title">Navegação</span>
            <a href="/">Trocar condomínio</a>
            <form method="post" action="/logout" class="sidebar-logout">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <button type="submit">Sair</button>
            </form>
        </div>
    </nav>
</aside>
