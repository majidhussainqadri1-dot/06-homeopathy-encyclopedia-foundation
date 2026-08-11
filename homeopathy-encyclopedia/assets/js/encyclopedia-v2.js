(function(){
  'use strict';
  const config=window.heV2||{};
  const api=(config.api||'').replace(/\/$/,'');
  const esc=s=>String(s==null?'':s);
  const make=(tag,attrs={},children=[])=>{
    const el=document.createElement(tag);
    Object.entries(attrs).forEach(([k,v])=>{
      if(k==='class')el.className=v;
      else if(k==='text')el.textContent=v;
      else if(k.startsWith('aria-'))el.setAttribute(k,v);
      else if(k==='href')el.href=v;
      else el.setAttribute(k,v);
    });
    [].concat(children).filter(Boolean).forEach(c=>el.append(c));
    return el;
  };
  const icon=()=>{
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('class','he-icon');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','1.8');
    const path=document.createElementNS('http://www.w3.org/2000/svg','path');path.setAttribute('d','M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Zm16 0A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5v-16Z');svg.append(path);return svg;
  };
  async function get(url,signal){
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal});
    if(!response.ok)throw new Error('http-'+response.status);
    return response.json();
  }
  function knowledgeCard(item){
    const link=make('a',{href:item.url||'#',text:esc(item.title)});
    const title=make('h2',{},[link]);
    const meta=make('div',{class:'he-v2__card-meta'},[
      make('span',{class:'he-v2__badge',text:esc(item.type)}),
      item.body_system?make('span',{class:'he-v2__badge',text:esc(item.body_system)}):null,
      make('span',{class:'he-v2__badge',text:'v'+esc(item.version)}),
      make('span',{class:'he-v2__badge',text:esc(item.language)})
    ]);
    return make('article',{class:'he-v2__card'},[icon(),title,make('p',{text:esc(item.summary)}),meta]);
  }
  function researchCard(item){
    return make('article',{class:'he-v2__card'},[
      icon(),make('h2',{},[make('a',{href:item.canonical_url||'#',text:esc(item.title)})]),
      make('p',{text:esc(item.question)}),
      make('div',{class:'he-v2__card-meta'},[
        make('span',{class:'he-v2__badge',text:esc(item.record_type)}),
        item.case_tag?make('span',{class:'he-v2__badge',text:esc(item.case_tag)}):null
      ])
    ]);
  }
  function skeletons(container,count=6){container.replaceChildren(...Array.from({length:count},()=>make('div',{class:'he-v2__card he-v2__skeleton','aria-hidden':'true'})));}
  document.querySelectorAll('[data-he-encyclopedia]').forEach(root=>{
    const form=root.querySelector('[data-he-filters]'),results=root.querySelector('[data-he-results]'),status=root.querySelector('[data-he-status]'),more=root.querySelector('[data-he-more]'),suggestions=root.querySelector('[data-he-suggestions]');
    let cursor=0,controller=null,letter='',suggestTimer=null;
    const params=()=>{
      const fd=new FormData(form),p=new URLSearchParams();
      ['q','type','body_system','language'].forEach(k=>{const v=fd.get(k);if(v)p.set(k,v)});
      if(letter)p.set('letter',letter);p.set('limit',root.dataset.limit||'20');if(cursor)p.set('cursor',String(cursor));return p;
    };
    const load=async append=>{
      if(controller)controller.abort();controller=new AbortController();
      if(!append){cursor=0;skeletons(results);}
      results.setAttribute('aria-busy','true');status.textContent=(config.i18n&&config.i18n.loading)||'Loading…';more.hidden=true;
      try{
        const data=await get(api+'/entries?'+params().toString(),controller.signal);
        if(!append)results.replaceChildren();
        (data.items||[]).forEach(item=>results.append(knowledgeCard(item)));
        cursor=data.next_cursor||0;more.hidden=!cursor;
        status.textContent=(data.items||[]).length?((append?'More results loaded. ':'')+(cursor?'More results are available.':'End of results.')):((config.i18n&&config.i18n.noResults)||'No results.');
      }catch(error){if(error.name!=='AbortError'){if(!append)results.replaceChildren();status.textContent=(config.i18n&&config.i18n.error)||'Could not load.';}}
      finally{results.setAttribute('aria-busy','false');}
    };
    form.addEventListener('submit',e=>{e.preventDefault();load(false)});
    form.querySelectorAll('select').forEach(el=>el.addEventListener('change',()=>load(false)));
    root.querySelectorAll('[data-letter]').forEach(btn=>btn.addEventListener('click',()=>{
      root.querySelectorAll('[data-letter]').forEach(b=>b.setAttribute('aria-pressed','false'));btn.setAttribute('aria-pressed','true');letter=btn.dataset.letter||'';load(false);
    }));
    more.addEventListener('click',()=>load(true));
    const q=form.querySelector('input[name="q"]');
    q.addEventListener('input',()=>{
      clearTimeout(suggestTimer);const value=q.value.trim();if(value.length<2){suggestions.hidden=true;suggestions.replaceChildren();return;}
      suggestTimer=setTimeout(async()=>{
        try{const items=await get(api+'/autocomplete?q='+encodeURIComponent(value)+'&limit=8');suggestions.replaceChildren(...items.map(item=>make('a',{href:item.url,text:item.label})));suggestions.hidden=!items.length;}catch(e){suggestions.hidden=true;}
      },220);
    });
    document.addEventListener('click',e=>{if(!suggestions.contains(e.target)&&e.target!==q)suggestions.hidden=true});
    if(root.dataset.type)form.elements.type.value=root.dataset.type;if(root.dataset.system)form.elements.body_system.value=root.dataset.system;
    load(false);
  });
  document.querySelectorAll('[data-he-research]').forEach(async root=>{
    const results=root.querySelector('[data-he-research-results]');skeletons(results,3);
    try{const data=await get(api+'/research?limit=30');results.replaceChildren(...(data.items||[]).map(researchCard));if(!(data.items||[]).length)results.append(make('p',{class:'he-v2__notice',text:'No approved public research records are available yet.'}));}
    catch(e){results.replaceChildren(make('p',{class:'he-v2__notice he-v2__notice--warning',text:(config.i18n&&config.i18n.error)||'Could not load.'}));}
    results.setAttribute('aria-busy','false');
  });
  document.querySelectorAll('[data-he-correction]').forEach(button=>button.addEventListener('click',()=>{
    const article=button.closest('[data-he-entry-id]');const reason=window.prompt('Describe the correction and include the source or evidence.');if(!reason)return;
    const key=(window.crypto&&crypto.randomUUID)?crypto.randomUUID():String(Date.now())+'-'+Math.random();
    fetch(api+'/entries/'+encodeURIComponent(article.dataset.heEntryId)+'/integrity',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce||'','Idempotency-Key':key},body:JSON.stringify({type:'correction',reason:reason,evidence:reason})}).then(r=>r.json().then(j=>({ok:r.ok,j}))).then(({ok,j})=>window.alert(ok?'Correction proposal submitted.':(j.message||'Could not submit.'))).catch(()=>window.alert('Could not submit.'));
  }));
})();
