document.querySelectorAll('[data-capital-error]').forEach(error=>{
    const input=error.closest('.field').querySelector('input');
    const validate=()=>{
        const message=input.value!==input.value.toLocaleUpperCase('id-ID')?'Nama harus menggunakan huruf kapital, contoh: BUDI SANTOSO.':'';
        error.textContent=message; error.hidden=!message;
        input.setCustomValidity(message); input.setAttribute('aria-invalid',message?'true':'false');
    };
    input.addEventListener('input',validate); validate();
});
const themeButtons=[...document.querySelectorAll('[data-theme]')];
const refreshTheme=()=>themeButtons.forEach(button=>{
    const dark=document.body.classList.contains('dark');
    button.setAttribute('aria-pressed',String(dark));
    const label=button.querySelector('[data-theme-label]');
    if(label)label.textContent=dark?label.dataset.darkLabel:label.dataset.lightLabel;
});
try{if(localStorage.getItem('sdcn-theme')==='dark'&&document.querySelector('.sidebar'))document.body.classList.add('dark')}catch{}
themeButtons.forEach(button=>button.addEventListener('click',()=>{
    document.body.classList.toggle('dark');
    try{localStorage.setItem('sdcn-theme',document.body.classList.contains('dark')?'dark':'light')}catch{}
    refreshTheme();
}));
refreshTheme();

const accountMenu=document.querySelector('[data-account-menu]');
const accountSummary=accountMenu?.querySelector('summary');
accountMenu?.addEventListener('toggle',()=>accountSummary.setAttribute('aria-expanded',String(accountMenu.open)));
accountMenu?.addEventListener('focusout',event=>{if(event.relatedTarget&&!accountMenu.contains(event.relatedTarget))accountMenu.open=false});
document.addEventListener('pointerdown',event=>{if(accountMenu?.open&&!accountMenu.contains(event.target))accountMenu.open=false});

const sidebar=document.querySelector('#dashboard-sidebar');
const sidebarButton=document.querySelector('[data-sidebar]');
const sidebarBackdrop=document.querySelector('[data-sidebar-backdrop]');
const sidebarMedia=window.matchMedia('(max-width:1100px)');
let desktopSidebarCollapsed=document.body.classList.contains('sidebar-collapsed');
let previousSidebarFocus;
const sidebarIsOpen=()=>sidebarMedia.matches&&document.body.classList.contains('sidebar-open');
const syncSidebar=()=>{
    if(!sidebar||!sidebarButton)return;
    const open=sidebarIsOpen();
    document.body.classList.toggle('sidebar-collapsed',!sidebarMedia.matches&&desktopSidebarCollapsed);
    sidebar.inert=sidebarMedia.matches?!open:desktopSidebarCollapsed;
    document.querySelector('.workspace-header').inert=open;
    document.querySelector('.main').inert=open;
    sidebarBackdrop.hidden=!open;
    if(open){sidebar.setAttribute('role','dialog');sidebar.setAttribute('aria-modal','true')}
    else{sidebar.removeAttribute('role');sidebar.removeAttribute('aria-modal')}
    const expanded=sidebarMedia.matches?open:!desktopSidebarCollapsed;
    sidebarButton.setAttribute('aria-expanded',String(expanded));
    sidebarButton.setAttribute('aria-label',expanded?sidebarButton.dataset.closeLabel:sidebarButton.dataset.openLabel);
};
const closeSidebar=(restoreFocus=true)=>{
    document.body.classList.remove('sidebar-open');
    syncSidebar();
    if(restoreFocus&&previousSidebarFocus?.isConnected)previousSidebarFocus.focus();
};
sidebarButton?.addEventListener('click',()=>{
    if(accountMenu)accountMenu.open=false;
    if(sidebarMedia.matches){
        if(sidebarIsOpen())closeSidebar();
        else{
            previousSidebarFocus=document.activeElement;
            document.body.classList.add('sidebar-open');
            syncSidebar();
            sidebar.querySelector('[data-sidebar-close]').focus();
        }
    }else{desktopSidebarCollapsed=!desktopSidebarCollapsed;syncSidebar()}
});
document.querySelector('[data-sidebar-close]')?.addEventListener('click',()=>closeSidebar());
sidebarBackdrop?.addEventListener('click',()=>closeSidebar());
sidebarMedia.addEventListener('change',()=>{
    const focusWasInside=sidebar?.contains(document.activeElement);
    closeSidebar(false);
    if(focusWasInside&&sidebar.inert)sidebarButton.focus();
});
document.addEventListener('keydown',event=>{
    if(event.key==='Escape'){
        if(sidebarIsOpen()){event.preventDefault();closeSidebar()}
        else if(accountMenu?.open){event.preventDefault();accountMenu.open=false;accountSummary.focus()}
    }
    if(event.key==='Tab'&&sidebarIsOpen()){
        const controls=[...sidebar.querySelectorAll('a[href],button:not(:disabled),input,select,textarea,[tabindex="0"]')].filter(el=>el.getClientRects().length);
        const first=controls[0],last=controls.at(-1);
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    }
});
syncSidebar();
document.querySelectorAll('[data-dismiss]').forEach(b=>b.addEventListener('click',()=>b.closest('.flash').remove()));
document.querySelectorAll('.nav-group').forEach(d=>d.addEventListener('toggle',()=>{if(d.open)document.querySelectorAll('.nav-group').forEach(other=>{if(other!==d)other.open=false})}));
document.querySelectorAll('[data-auto-submit]').forEach(el=>el.addEventListener('change',()=>el.form.requestSubmit()));
document.querySelectorAll('[data-filter-form]').forEach(form=>{let timer;form.querySelector('input[name=q]')?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>form.requestSubmit(),650)})});
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
const confirmation=document.querySelector('#confirm-dialog');let pendingForm;
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(form.dataset.confirmed || (form.hasAttribute('data-activation-form') && event.submitter?.value!=='activate'))return;event.preventDefault();pendingForm={form,submitter:event.submitter};document.querySelector('#confirm-message').textContent=form.dataset.confirm;confirmation.showModal()}));
document.querySelector('[data-confirm-cancel]')?.addEventListener('click',()=>confirmation.close());
document.querySelector('[data-confirm-accept]')?.addEventListener('click',()=>{confirmation.close();pendingForm.form.dataset.confirmed='true';pendingForm.form.requestSubmit(pendingForm.submitter)});
document.querySelectorAll('[data-table-page]').forEach(wrapper=>{const rows=[...wrapper.querySelectorAll('tbody tr')],size=10;let page=1;const pages=Math.max(1,Math.ceil(rows.length/size));const render=()=>{rows.forEach((row,i)=>row.hidden=i<(page-1)*size||i>=page*size);wrapper.querySelector('[data-page-number]').textContent=page;wrapper.querySelector('[data-prev]').disabled=page===1;wrapper.querySelector('[data-next]').disabled=page===pages};wrapper.querySelector('[data-prev]')?.addEventListener('click',()=>{page--;render()});wrapper.querySelector('[data-next]')?.addEventListener('click',()=>{page++;render()});if(wrapper.querySelector('[data-page-number]'))render()});
document.querySelectorAll('[data-preview-field]').forEach(input=>input.addEventListener('input',()=>document.querySelectorAll(`[data-preview-target="${input.name}"]`).forEach(target=>target.textContent=input.value)));
document.querySelector('[data-activation-form]')?.addEventListener('change',event=>{const form=event.currentTarget;const ready=form.querySelectorAll('input[name="checks[]"]:checked').length===4&&form.querySelector('input[name=paid]').checked;form.querySelector('[value=activate]').disabled=!ready});
document.querySelectorAll('[data-select-media]').forEach(button=>button.addEventListener('click',()=>{const target=document.querySelector(`[name="${button.dataset.target}"]`);if(target){target.value=button.dataset.selectMedia;document.querySelector('#media-picker')?.close()}}));
document.querySelector('#report-form')?.addEventListener('submit',event=>{event.currentTarget.action='/admin/export/'+event.currentTarget.querySelector('[name=report]:checked').value});
document.querySelector('[data-open-media]')?.addEventListener('click',()=>document.querySelector('#media-picker').showModal());
document.querySelector('[data-close-media]')?.addEventListener('click',()=>document.querySelector('#media-picker').close());
document.querySelectorAll('[data-live-nominal]').forEach(input=>{
    const error=input.closest('.field').querySelector('#nominal-error');
    const validate=()=>{
        input.setCustomValidity('');
        let message='';
        if(input.validity.badInput) message='Masukkan nominal berupa angka bulat.';
        else if(input.value!=='') {
            if(input.validity.rangeOverflow) message='Nominal maksimal Rp1.000.000.000.';
            else if(input.validity.rangeUnderflow) message='Nominal minimal Rp0.';
            else if(input.validity.stepMismatch) message='Nominal harus berupa angka bulat tanpa desimal.';
        }
        error.textContent=message;
        error.hidden=!message;
        input.setAttribute('aria-invalid',message?'true':'false');
        input.setCustomValidity(message);
    };
    input.addEventListener('input',validate);
    input.addEventListener('change',validate);
    validate();
});
document.querySelectorAll('.hero-editor input,.hero-editor textarea').forEach(input=>input.addEventListener('input',()=>document.querySelectorAll(`[data-preview-target="${input.name}"]`).forEach(target=>target.textContent=input.value)));
document.querySelectorAll('[data-select-media]').forEach(button=>button.addEventListener('click',()=>{const image=document.querySelector('[data-hero-image]');if(image)image.src='/assets/'+button.dataset.selectMedia}));
