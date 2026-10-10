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
$sidebarCanManageUsers = (bool)array_intersect($sidebarRoles, ['SUPER_ADMIN','ADMIN','SYNDIC']);
$sidebarIsResponsible = in_array('WORK_RESPONSIBLE', $sidebarRoles, true) && count(array_diff($sidebarRoles, ['WORK_RESPONSIBLE'])) === 0;

if (!function_exists('sidebarClass')) {
    function sidebarClass(string $key, string $active): string {
        return $key === $active ? 'active' : '';
    }
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

        <?php if($sidebarCondoId > 0 && $sidebarCanInspect): ?>
        <div class="sidebar-group">
            <span class="sidebar-group-title">Fiscalização</span>
            <a class="<?=sidebarClass('inspections',$sidebarActive)?>" href="/condominium/inspections?condo=<?=$sidebarCondoId?>">Fiscalizações</a>
            <a class="<?=sidebarClass('pending',$sidebarActive)?>" href="/condominium/pending?condo=<?=$sidebarCondoId?>">Central de Pendências</a>
            <a class="<?=sidebarClass('corrections',$sidebarActive)?>" href="/condominium/non-conformities?condo=<?=$sidebarCondoId?>">Não conformidades / Correções</a>
            <a class="<?=sidebarClass('notifications',$sidebarActive)?>" href="/condominium/notifications?condo=<?=$sidebarCondoId?>">Notificações</a>
            <?php if($sidebarWorkId > 0): ?><a class="<?=sidebarClass('diary',$sidebarActive)?>" href="/work/diary?id=<?=$sidebarWorkId?>">Diário da Obra</a><?php endif; ?>
            <?php if($sidebarCanManage): ?>
                <a class="<?=sidebarClass('checklist-preset',$sidebarActive)?>" href="/settings/checklist-preset?condo=<?=$sidebarCondoId?>">Checklist padrão</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($sidebarCondoId > 0): ?>
        <div class="sidebar-group">
            <span class="sidebar-group-title">Documentação</span>
            <?php if($sidebarCanInspect): ?>
                <a class="<?=sidebarClass('documents',$sidebarActive)?>" href="/condominium/documents?condo=<?=$sidebarCondoId?>">Documentos</a>
                <a class="<?=sidebarClass('completion',$sidebarActive)?>" href="/condominium/completions?condo=<?=$sidebarCondoId?>">Conclusões</a>
                <a class="<?=sidebarClass('dossier',$sidebarActive)?>" href="/condominium/dossiers?condo=<?=$sidebarCondoId?>">Dossiês digitais</a>
            <?php elseif($sidebarWorkId > 0): ?>
                <a class="<?=sidebarClass('documents',$sidebarActive)?>" href="/work?id=<?=$sidebarWorkId?>#documentos">Documentos</a>
                <a class="<?=sidebarClass('diary',$sidebarActive)?>" href="/work/diary?id=<?=$sidebarWorkId?>">Diário da Obra</a>
            <?php endif; ?>
        </div>

        <?php if($sidebarCanInspect): ?>
        <div class="sidebar-group">
            <span class="sidebar-group-title">Gestão</span>
            <a class="<?=sidebarClass('reports',$sidebarActive)?>" href="/reports?condo=<?=$sidebarCondoId?>">Relatórios</a>
            <a class="<?=sidebarClass('fiscal',$sidebarActive)?>" href="/fiscal-panel?condo=<?=$sidebarCondoId?>">Painel do Fiscal</a>
            <?php if($sidebarCanManageUsers): ?>
                <a class="<?=sidebarClass('users',$sidebarActive)?>" href="/users?condo=<?=$sidebarCondoId?>">Usuários / Permissões</a>
            <?php endif; ?>
            <?php if($sidebarCanManage): ?>
                <a class="<?=sidebarClass('settings',$sidebarActive)?>" href="/settings?condo=<?=$sidebarCondoId?>">Configurações do condomínio</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="sidebar-group sidebar-bottom-group">
            <span class="sidebar-group-title">Navegação</span>
            <?php if(!$sidebarIsResponsible): ?><a href="/">Trocar condomínio</a><?php endif; ?>
            <form method="post" action="/logout" class="sidebar-logout">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <button type="submit">Sair</button>
            </form>
        </div>
    </nav>
</aside>
