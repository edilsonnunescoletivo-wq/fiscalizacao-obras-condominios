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
<svg class="sidebar-icon-sprite" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><symbol id="fo-building-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="10" y="8" width="11" height="13" rx="1"/><path d="M6 7h1M6 11h1M6 15h1M14 12h1M18 12h1M14 16h1M18 16h1"/></symbol><symbol id="fo-layout-dashboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></symbol><symbol id="fo-hard-hat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17h18M5 17v-3a7 7 0 0 1 14 0v3M9 10V5h6v5M12 5V3"/></symbol><symbol id="fo-chart-no-axes-column" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 20V11M10 20V4M15 20v-7M20 20V8"/></symbol><symbol id="fo-clipboard-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></symbol><symbol id="fo-list-todo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="4" height="4" rx="1"/><path d="M11 6h10M3 14l2 2 3-4M11 15h10M11 20h7"/></symbol><symbol id="fo-alert-triangle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.8 2 18.2A2 2 0 0 0 3.7 21h16.6a2 2 0 0 0 1.7-2.8L13.7 3.8a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></symbol><symbol id="fo-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol><symbol id="fo-book-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7c-3-2-6-2-10-1v14c4-1 7-1 10 1 3-2 6-2 10-1V6c-4-1-7-1-10 1ZM12 7v14"/></symbol><symbol id="fo-list-checks" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 2 2 3-4M11 6h10M3 14l2 2 3-4M11 14h10M3 21h18"/></symbol><symbol id="fo-file-text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M13 2v7h7M8 13h8M8 17h8"/></symbol><symbol id="fo-badge-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"/><path d="M12 2 15 4l4-.2.8 4L22 12l-2.2 4.2-.8 4-4-.2-3 2-3-2-4 .2-.8-4L2 12l2.2-4.2.8-4 4 .2Z"/></symbol><symbol id="fo-folders" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2ZM5 4h6l2 2h8"/></symbol><symbol id="fo-chart-column" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V4M4 20h17M8 16v-5h3v5M14 16V7h3v9"/></symbol><symbol id="fo-shield-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 20 6v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6Z"/><path d="m8 12 3 3 5-6"/></symbol><symbol id="fo-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/></symbol><symbol id="fo-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="m10 2-.7 2.4-2 .8-2.2-1.2-2 3.4 1.8 1.8-.3 2.2L2 12l2.6.6.3 2.2-1.8 1.8 2 3.4 2.2-1.2 2 .8L10 22h4l.7-2.4 2-.8 2.2 1.2 2-3.4-1.8-1.8.3-2.2L22 12l-2.6-.6-.3-2.2 1.8-1.8-2-3.4-2.2 1.2-2-.8L14 2Z"/></symbol><symbol id="fo-arrow-left-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h14m-4-4 4 4-4 4M17 17H3m4-4-4 4 4 4"/></symbol><symbol id="fo-log-out" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol></svg>
<aside class="sidebar" aria-label="Menu principal">
    <div class="sidebar-head">
        <div class="brand"><span class="brand-primary">fiscaliza</span><span class="brand-secondary">obras</span></div>
        <button type="button" class="sidebar-close" aria-label="Fechar menu" onclick="document.body.classList.remove('sidebar-open')">×</button>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title sidebar-group-toggle" aria-expanded="true">Visão geral<span class="sidebar-chevron" aria-hidden="true">⌃</span></button>
            <a class="<?=sidebarClass('dashboard',$sidebarActive)?>" href="/"><svg class="nav-icon" aria-hidden="true"><use href="#fo-building-2"></use></svg><span class="nav-label">Todos os condomínios</span></a>
            <?php if($sidebarCondoId > 0): ?>
                <a class="<?=sidebarClass('condo',$sidebarActive)?>" href="/works?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-layout-dashboard"></use></svg><span class="nav-label">Painel do condomínio</span></a>
                <a class="<?=sidebarClass('works',$sidebarActive)?>" href="/works?condo=<?=$sidebarCondoId?>#obras"><svg class="nav-icon" aria-hidden="true"><use href="#fo-hard-hat"></use></svg><span class="nav-label">Obras</span></a>
                <?php if(!$sidebarIsResponsible):?><a class="<?=sidebarClass('indices',$sidebarActive)?>" href="/indices?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-chart-no-axes-column"></use></svg><span class="nav-label">Índices</span></a><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if($sidebarCondoId > 0 && $sidebarCanInspect): ?>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title sidebar-group-toggle" aria-expanded="true">Fiscalização<span class="sidebar-chevron" aria-hidden="true">⌃</span></button>
            <a class="<?=sidebarClass('inspections',$sidebarActive)?>" href="/condominium/inspections?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-clipboard-check"></use></svg><span class="nav-label">Fiscalizações</span></a>
            <a class="<?=sidebarClass('pending',$sidebarActive)?>" href="/condominium/pending?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-list-todo"></use></svg><span class="nav-label">Central de Pendências</span></a>
            <a class="<?=sidebarClass('corrections',$sidebarActive)?>" href="/condominium/non-conformities?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-alert-triangle"></use></svg><span class="nav-label">Não conformidades / Correções</span></a>
            <a class="<?=sidebarClass('notifications',$sidebarActive)?>" href="/condominium/notifications?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-bell"></use></svg><span class="nav-label">Notificações</span></a>
            <?php if($sidebarWorkId > 0): ?><a class="<?=sidebarClass('diary',$sidebarActive)?>" href="/work/diary?id=<?=$sidebarWorkId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-book-open"></use></svg><span class="nav-label">Diário da Obra</span></a><?php endif; ?>
            <?php if($sidebarCanManage): ?>
                <a class="<?=sidebarClass('checklist-preset',$sidebarActive)?>" href="/settings/checklist-preset?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-list-checks"></use></svg><span class="nav-label">Checklist padrão</span></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($sidebarCondoId > 0): ?>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title sidebar-group-toggle" aria-expanded="true">Documentação<span class="sidebar-chevron" aria-hidden="true">⌃</span></button>
            <?php if($sidebarCanInspect): ?>
                <a class="<?=sidebarClass('documents',$sidebarActive)?>" href="/condominium/documents?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-file-text"></use></svg><span class="nav-label">Documentos</span></a>
                <a class="<?=sidebarClass('completion',$sidebarActive)?>" href="/condominium/completions?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-badge-check"></use></svg><span class="nav-label">Conclusões</span></a>
                <a class="<?=sidebarClass('dossier',$sidebarActive)?>" href="/condominium/dossiers?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-folders"></use></svg><span class="nav-label">Dossiês digitais</span></a>
            <?php elseif($sidebarWorkId > 0): ?>
                <a class="<?=sidebarClass('documents',$sidebarActive)?>" href="/work?id=<?=$sidebarWorkId?>#documentos"><svg class="nav-icon" aria-hidden="true"><use href="#fo-file-text"></use></svg><span class="nav-label">Documentos</span></a>
                <a class="<?=sidebarClass('diary',$sidebarActive)?>" href="/work/diary?id=<?=$sidebarWorkId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-book-open"></use></svg><span class="nav-label">Diário da Obra</span></a>
            <?php endif; ?>
        </div>

        <?php if($sidebarCanInspect): ?>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title sidebar-group-toggle" aria-expanded="true">Gestão<span class="sidebar-chevron" aria-hidden="true">⌃</span></button>
            <a class="<?=sidebarClass('reports',$sidebarActive)?>" href="/reports?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-chart-column"></use></svg><span class="nav-label">Relatórios</span></a>
            <a class="<?=sidebarClass('fiscal',$sidebarActive)?>" href="/fiscal-panel?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-shield-check"></use></svg><span class="nav-label">Painel do Fiscal</span></a>
            <?php if($sidebarCanManageUsers): ?>
                <a class="<?=sidebarClass('users',$sidebarActive)?>" href="/users?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-users"></use></svg><span class="nav-label">Usuários / Permissões</span></a>
            <?php endif; ?>
            <?php if($sidebarCanManage): ?>
                <a class="<?=sidebarClass('settings',$sidebarActive)?>" href="/settings?condo=<?=$sidebarCondoId?>"><svg class="nav-icon" aria-hidden="true"><use href="#fo-settings"></use></svg><span class="nav-label">Configurações do condomínio</span></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="sidebar-group sidebar-bottom-group">
            <button type="button" class="sidebar-group-title sidebar-group-toggle" aria-expanded="true">Navegação<span class="sidebar-chevron" aria-hidden="true">⌃</span></button>
            <?php if(!$sidebarIsResponsible): ?><a href="/"><svg class="nav-icon" aria-hidden="true"><use href="#fo-arrow-left-right"></use></svg><span class="nav-label">Trocar condomínio</span></a><?php endif; ?>
            <form method="post" action="/logout" class="sidebar-logout">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <button type="submit"><svg class="nav-icon" aria-hidden="true"><use href="#fo-log-out"></use></svg><span class="nav-label">Sair</span></button>
            </form>
        </div>
    </nav>
</aside>
<script>
(function(){
  var sidebar=document.querySelector('.sidebar');
  if(!sidebar)return;
  sidebar.querySelectorAll('.sidebar-group-toggle').forEach(function(toggle){
    toggle.addEventListener('click',function(){
      var group=toggle.closest('.sidebar-group');
      var collapsed=group.classList.toggle('is-collapsed');
      toggle.setAttribute('aria-expanded',String(!collapsed));
    });
  });
  sidebar.addEventListener('click',function(event){
    if(event.target.closest('a'))document.body.classList.remove('sidebar-open');
  });
  document.addEventListener('keydown',function(event){
    if(event.key==='Escape')document.body.classList.remove('sidebar-open');
  });
})();
</script>
