(() => {
  function init() {
    if(window.CKEDITOR && !CKEDITOR.plugins.get('cpCaption')) CKEDITOR.plugins.add('cpCaption',{init(editor){
      editor.addCommand('cpCaption',{exec(ed){let node=ed.getSelection().getSelectedElement()||ed.getSelection().getStartElement();if(node&&!node.is('img'))node=node.getAscendant('img',true);if(!node){alert('Сначала выделите изображение.');return;}const caption=prompt('Подпись изображения','');if(caption===null)return;ed.fire('saveSnapshot');let figure=node.getAscendant('figure');if(!figure){figure=new CKEDITOR.dom.element('figure');figure.insertBefore(node);node.appendTo(figure);}let existing=figure.findOne('figcaption');if(!existing){existing=new CKEDITOR.dom.element('figcaption');existing.appendTo(figure);}existing.setText(caption);ed.fire('saveSnapshot');}});
      editor.ui.addButton('Caption',{label:'Подпись изображения',command:'cpCaption',toolbar:'insert',icon:'image'});
    }});
    if (window.CKEDITOR) document.querySelectorAll('textarea.cp-rich').forEach(el => {
      if(CKEDITOR.instances[el.id]) return;
      CKEDITOR.replace(el.id,{language:'ru',height:400,allowedContent:true,extraPlugins:'cpCaption',removePlugins:'iframe,forms,flash,smiley,save,newpage,print',format_tags:'p;h2;h3;h4',toolbar:[['Undo','Redo'],['Format','Bold','Italic'],['NumberedList','BulletedList','Blockquote'],['Link','Unlink','Image','Table'],['Caption','RemoveFormat','Maximize']],pasteFromWordRemoveFontStyles:true,pasteFromWordRemoveStyles:true});
    });
    document.querySelectorAll('select[multiple]').forEach(select=>{
      const search=document.createElement('input');search.type='search';search.className='form-control mb-2';search.placeholder='Найти в списке по названию или номеру';select.before(search);search.addEventListener('input',()=>{const value=search.value.toLocaleLowerCase('ru');for(const option of select.options)option.hidden=!!value&&!option.textContent.toLocaleLowerCase('ru').includes(value);});
    });
    document.querySelectorAll('[data-add-row]').forEach(button=>button.addEventListener('click',()=>{
      const template=document.getElementById(button.dataset.addRow);const container=document.getElementById(button.dataset.target);if(!template||!container)return;
      const count=Date.now();container.insertAdjacentHTML('beforeend',template.innerHTML.replaceAll('__INDEX__',String(count)));
    }));
    document.querySelectorAll('[data-remove-row]').forEach(button=>button.addEventListener('click',()=>button.closest('[data-cp-row]')?.remove()));
    const upload=document.getElementById('cp-image-upload');upload?.addEventListener('change',async()=>{
      const result=document.getElementById('cp-upload-result');const file=upload.files?.[0];if(!file)return;const data=new FormData();data.append('image',file);result.textContent='Сохраняем изображение…';
      try{const response=await fetch(upload.dataset.url,{method:'POST',body:data,credentials:'same-origin'});const payload=await response.json();if(!response.ok||payload.error)throw new Error(payload.error||'Не удалось загрузить изображение.');result.textContent='Изображение сохранено: '+payload.url+' — выберите его для обложки или вставьте через кнопку «Изображение».';const input=document.createElement('input');input.className='form-control';input.value=payload.url;input.readOnly=true;input.addEventListener('focus',()=>input.select());result.appendChild(input);document.querySelectorAll('[data-cp-image-target]').forEach(target=>target.value=payload.url);}catch(error){result.textContent=error.message;}
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init);else init();
})();
