document.querySelectorAll('[data-capital-error]').forEach(error=>{
    const input=error.closest('.field').querySelector('input');
    const validate=()=>{
        const message=input.value!==input.value.toLocaleUpperCase('id-ID')?'Nama harus menggunakan huruf kapital, contoh: BUDI SANTOSO.':'';
        error.textContent=message; error.hidden=!message;
        input.setCustomValidity(message); input.setAttribute('aria-invalid',message?'true':'false');
    };
    input.addEventListener('input',validate); validate();
});
document.querySelectorAll('[data-student-photo]').forEach(editor=>{
    const find=name=>editor.querySelector(`[data-photo-${name}]`);
    const file=find('file'), result=find('result'), preview=find('preview'), crop=find('crop'), canvas=find('canvas'), error=find('error');
    const zoom=find('zoom'), x=find('x'), y=find('y');
    let source=null, version=0;
    if(result.value) preview.src=result.value;
    const draw=()=>{
        if(!source)return;
        const side=Math.min(source.width,source.height)/Number(zoom.value);
        const ctx=canvas.getContext('2d'); ctx.fillStyle='#fff'; ctx.fillRect(0,0,512,512);
        ctx.drawImage(source,(source.width-side)*Number(x.value)/100,(source.height-side)*Number(y.value)/100,side,side,0,0,512,512);
    };
    const cancel=()=>{version++; source=null; file.value=''; file.setCustomValidity(''); crop.hidden=true; error.textContent='';};
    file.addEventListener('change',async()=>{
        const selected=file.files[0], current=++version;
        crop.hidden=true; source=null; error.textContent=''; file.setCustomValidity('');
        if(!selected)return;
        if(!['image/jpeg','image/png','image/webp'].includes(selected.type)||selected.size>5*1024*1024){
            error.textContent='Pilih JPG, PNG, atau WebP maksimal 5 MB.'; file.value=''; return;
        }
        file.setCustomValidity('Selesaikan crop foto atau pilih Batal crop sebelum menyimpan.');
        const url=URL.createObjectURL(selected), image=new Image();
        try{
            image.src=url; await image.decode();
            if(current!==version)return;
            source=image; zoom.value=1; x.value=50; y.value=50; crop.hidden=false; draw();
        }catch{if(current===version){cancel(); error.textContent='Foto tidak dapat dibaca. Pilih berkas gambar lain.';}}
        finally{URL.revokeObjectURL(url);}
    });
    [zoom,x,y].forEach(control=>control.addEventListener('input',draw));
    find('apply').addEventListener('click',()=>{
        if(!source)return;
        result.value=canvas.toDataURL('image/jpeg',0.9); preview.src=result.value; cancel();
    });
    find('cancel').addEventListener('click',cancel);
});
document.querySelector('[data-sidebar]')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-collapsed'));
document.querySelector('[data-theme]')?.addEventListener('click',()=>{document.body.classList.toggle('dark');localStorage.setItem('sdcn-theme',document.body.classList.contains('dark')?'dark':'light')});
if(localStorage.getItem('sdcn-theme')==='dark' && document.querySelector('.sidebar'))document.body.classList.add('dark');
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
