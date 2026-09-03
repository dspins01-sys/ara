(function(){
  'use strict';
  var CSRF = window.ARA_CSRF || '';
  var AJAX = window.ARA_AJAX_URL || 'ajax.php';
  var statusEl, mediaModal, mediaGrid, mediaTarget, libModal, libTarget, settingsModal, headerModal, sliderModal, draggedBlock, bgDebounce, logoResize;

  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];}); }

  function post(op, payload){
    payload = payload || {}; payload.op = op; payload.csrf = CSRF;
    return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
      .then(function(r){ return r.json(); })
      .then(function(j){ if(!j || !j.ok) throw new Error((j&&j.error)||'Gagal menyimpan'); return j; });
  }

  var pending=0;
  function setStatus(text,cls){ if(!statusEl) return; statusEl.textContent=text; statusEl.className='ce-status'+(cls?' '+cls:''); }
  function markSaving(){ pending++; setStatus('Menyimpan…','saving'); }
  function markSaved(){ pending=Math.max(0,pending-1); if(pending===0) setStatus('Semua perubahan tersimpan'); }
  function markError(msg){ pending=Math.max(0,pending-1); setStatus(msg||'Gagal menyimpan','error'); }
  /* V20.6.6: durable save queue. Keep the newest value per CMS key and
   * serialize writes so an older request can never overwrite a newer edit.
   * Pending values are also sent with fetch(keepalive) when the iframe/page is
   * being unloaded, preventing refresh/navigation from dropping the last edit.
   */
  var saveState={};
  function saveKV(key,value){
    key=String(key||''); value=value==null?'':String(value);
    if(!key) return Promise.reject(new Error('Missing save key'));
    var st=saveState[key];
    if(!st) st=saveState[key]={value:value,queued:false,inflight:false,waiters:[]};
    st.value=value;
    return new Promise(function(resolve,reject){
      st.waiters.push({resolve:resolve,reject:reject});
      if(st.queued || st.inflight) return;
      st.queued=true;
      flushKey(key);
    });
  }
  function sendKV(key,value,keepalive){
    var m=key.match(/^section\.(\d+)\.(.+)$/);
    var payload=m ? {op:'update_section',id:parseInt(m[1],10),field:m[2],value:value} : {op:'update_setting',key:key,value:value};
    payload.csrf=CSRF;
    return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),keepalive:!!keepalive})
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(!j || !j.ok) throw new Error((j&&j.error)||'Gagal menyimpan');
        if(j.field && j.value!=null && String(j.value)!==String(value)) throw new Error('Server menerima nilai berbeda');
        return j;
      });
  }
  function flushKey(key){
    var st=saveState[key]; if(!st) return Promise.resolve();
    st.queued=false; st.inflight=true;
    var value=st.value;
    return sendKV(key,value,false).then(function(j){
      st.inflight=false;
      /* If another edit arrived while this request was in flight, immediately
       * persist that newer value before resolving the callers. */
      if(st.value!==value){ st.queued=true; return flushKey(key); }
      var ws=st.waiters.splice(0); ws.forEach(function(w){w.resolve(j);});
      return j;
    }).catch(function(err){
      st.inflight=false;
      var ws=st.waiters.splice(0); ws.forEach(function(w){w.reject(err);});
      throw err;
    });
  }
  function beaconKV(key,value){
    if(!navigator.sendBeacon) return false;
    var m=String(key||'').match(/^section\.(\d+)\.(.+)$/);
    var payload=m ? {op:'update_section',id:parseInt(m[1],10),field:m[2],value:String(value==null?'':value),csrf:CSRF} : {op:'update_setting',key:String(key||''),value:String(value==null?'':value),csrf:CSRF};
    try{
      var blob=new Blob([JSON.stringify(payload)],{type:'application/json'});
      return navigator.sendBeacon(AJAX,blob);
    }catch(e){ return false; }
  }
  function flushAllSaves(){
    var jobs=[];
    Object.keys(saveState).forEach(function(key){
      var st=saveState[key];
      if(st && (st.inflight || st.queued) && st.value!=null){
        // sendBeacon hands the newest value to the browser networking stack
        // during refresh/navigation, where a normal fetch may be cancelled.
        if(!beaconKV(key,st.value)) jobs.push(sendKV(key,st.value,true).catch(function(){}));
      }
    });
    return Promise.all(jobs);
  }
  window.addEventListener('pagehide',flushAllSaves);
  window.addEventListener('beforeunload',flushAllSaves);

  function rgbToHex(rgb){
    if(!rgb) return '#ffffff';
    if(rgb.charAt(0)==='#') return rgb;
    var m=rgb.match(/\d+/g); if(!m) return '#ffffff';
    return '#'+m.slice(0,3).map(function(n){ var h=parseInt(n,10).toString(16); return h.length===1?'0'+h:h; }).join('');
  }

  /* ---------------- lightweight typography controls ----------------
   * Typography is stored as one small JSON setting, not in the section table.
   * This keeps the content model compatible with existing installs.
   */
  var typeToolbar, typeTarget, typographyState={};
  var FONT_CHOICES=[
    ['Inter','Inter'],['Arial','Arial'],['Helvetica','Helvetica'],['Georgia','Georgia'],
    ['Times New Roman','Times New Roman'],['Verdana','Verdana'],['Trebuchet MS','Trebuchet MS'],
    ['Courier New','Courier New'],['system-ui','System UI'],
    ['Archivo Black','Archivo Black (bold display)'],['Bebas Neue','Bebas Neue (condensed display)'],
    ['Caveat','Caveat (script)'],['Playfair Display','Playfair Display (serif display)'],
    ['Poppins','Poppins'],['Space Grotesk','Space Grotesk']
  ];
  function loadTypography(){
    try{ typographyState=JSON.parse(document.body.dataset.ceTypography||'{}')||{}; }catch(e){ typographyState={}; }
  }
  function typeFor(key){
    var t=typographyState[key];
    return t && typeof t==='object' ? t : {};
  }
  function buildTypeToolbar(){
    typeToolbar=document.createElement('div'); typeToolbar.className='ce-type-toolbar'; typeToolbar.contentEditable='false';
    var fontOpts=FONT_CHOICES.map(function(f){return '<option value="'+esc(f[0])+'" style="font-family:'+esc(f[0])+'">'+esc(f[1])+'</option>';}).join('');
    typeToolbar.innerHTML=
      '<span class="ce-type-label">Font</span><select id="ceTypeFont" title="Jenis font"><option value="">Bawaan</option>'+fontOpts+'</select>'+
      '<span class="ce-type-label">Ukuran</span><input id="ceTypeSize" type="number" min="8" max="120" step="1" placeholder="px" title="Ukuran font">'+
      '<select id="ceTypeWeight" title="Ketebalan"><option value="400">Regular</option><option value="500">Medium</option><option value="600">Semibold</option><option value="700">Bold</option><option value="800">Extra Bold</option><option value="900">Black</option></select>'+
      '<input id="ceTypeColor" type="color" value="#111111" title="Warna teks">'+
      '<span class="ce-type-align" title="Perataan"><button type="button" data-align="left">L</button><button type="button" data-align="center">C</button><button type="button" data-align="right">R</button><button type="button" data-align="justify">J</button></span>'+
      '<button type="button" id="ceTypeReset" title="Kembalikan font bawaan">↺</button>';
    document.body.appendChild(typeToolbar);
    typeToolbar.querySelector('#ceTypeFont').addEventListener('change',function(){ applyTypography('font',this.value); });
    typeToolbar.querySelector('#ceTypeSize').addEventListener('change',function(){ if(this.value) applyTypography('size',parseFloat(this.value)); });
    typeToolbar.querySelector('#ceTypeWeight').addEventListener('change',function(){ applyTypography('weight',this.value); });
    typeToolbar.querySelector('#ceTypeColor').addEventListener('input',function(){ applyTypography('color',this.value); });
    typeToolbar.querySelectorAll('[data-align]').forEach(function(b){ b.onclick=function(){ applyTypography('align',b.dataset.align); }; });
    typeToolbar.querySelector('#ceTypeReset').onclick=function(){ resetTypography(); };
    typeToolbar.addEventListener('mousedown',function(e){ if(e.target.tagName!=='INPUT' && e.target.tagName!=='SELECT') e.preventDefault(); });
  }
  function persistTypography(){
    if(!typeTarget) return Promise.resolve();
    var key=String(typeTarget.dataset.cmsKey||'');
    var m=key.match(/^section\.(\d+)\.([a-zA-Z0-9_]+)$/);
    var cfg=typeFor(key);
    markSaving();
    if(m){
      var id=parseInt(m[1],10), field=m[2];
      return post('update_section_typography',{id:id,field:field,value:cfg}).then(function(j){
        typographyState[key]=j.typography||{};
        document.body.dataset.ceTypography=JSON.stringify(typographyState);
        markSaved();
        return j;
      }).catch(function(err){ markError(err.message); throw err; });
    }
    return post('update_setting',{key:'typography',value:JSON.stringify(typographyState)}).then(function(j){
      document.body.dataset.ceTypography=JSON.stringify(typographyState);
      markSaved();
      return j;
    }).catch(function(err){ markError(err.message); throw err; });
  }
  var typeSaveTimer=null;
  function applyTypography(prop,value){
    if(!typeTarget) return;
    var key=typeTarget.dataset.cmsKey;
    var t=typeFor(key);
    if(value==='' || value==null) delete t[prop]; else t[prop]=value;
    typographyState[key]=t;
    if(prop==='font') typeTarget.style.fontFamily=value;
    if(prop==='size') typeTarget.style.fontSize=value+'px';
    if(prop==='weight') typeTarget.style.fontWeight=value;
    if(prop==='color') typeTarget.style.color=value;
    if(prop==='align') typeTarget.style.textAlign=value;
    clearTimeout(typeSaveTimer); typeSaveTimer=setTimeout(persistTypography,300);
    syncTypeToolbar();
  }
  function resetTypography(){
    if(!typeTarget) return;
    delete typographyState[typeTarget.dataset.cmsKey];
    ['fontFamily','fontSize','fontWeight','color','textAlign'].forEach(function(p){ typeTarget.style[p]=''; });
    clearTimeout(typeSaveTimer); typeSaveTimer=setTimeout(persistTypography,100);
    syncTypeToolbar();
  }
  function syncTypeToolbar(){
    if(!typeToolbar||!typeTarget) return;
    var t=typeFor(typeTarget.dataset.cmsKey);
    typeToolbar.querySelector('#ceTypeFont').value=t.font||'';
    typeToolbar.querySelector('#ceTypeSize').value=t.size||'';
    typeToolbar.querySelector('#ceTypeWeight').value=t.weight||'400';
    typeToolbar.querySelector('#ceTypeColor').value=/^#[0-9a-f]{6}$/i.test(t.color||'')?t.color:'#111111';
    typeToolbar.querySelectorAll('[data-align]').forEach(function(b){b.classList.toggle('active',(t.align||'')===b.dataset.align);});
  }
  function showTypeToolbar(el){
    if(!typeToolbar) buildTypeToolbar();
    typeTarget=el; syncTypeToolbar(); typeToolbar.classList.add('open');
    function position(){
      var r=el.getBoundingClientRect(), top=r.top-typeToolbar.offsetHeight-8;
      if(top<56) top=Math.min(r.bottom+8,window.innerHeight-typeToolbar.offsetHeight-8);
      typeToolbar.style.top=Math.max(56,top)+'px';
      typeToolbar.style.left=Math.max(8,Math.min(window.innerWidth-typeToolbar.offsetWidth-8,r.left))+'px';
    }
    position();
    typeToolbar._position=position;
    window.addEventListener('scroll',position,true); window.addEventListener('resize',position);
  }
  function hideTypeToolbar(){
    if(!typeToolbar) return;
    typeToolbar.classList.remove('open');
    if(typeToolbar._position){window.removeEventListener('scroll',typeToolbar._position,true);window.removeEventListener('resize',typeToolbar._position);typeToolbar._position=null;}
    typeTarget=null;
  }

  /* ---------------- top bar ---------------- */
  function buildTopbar(){
    var bar=document.createElement('div'); bar.className='ce-topbar';
    bar.innerHTML =
      '<span class="ce-logo">✎ Visual Editor</span>'+
      '<span class="ce-status" id="ceStatus">Semua perubahan tersimpan</span>'+
      '<div class="ce-devices"><button type="button" data-device="desktop" class="active">Desktop</button><button type="button" data-device="tablet">Tablet</button><button type="button" data-device="mobile">Mobile</button></div>'+
      '<button type="button" class="ce-btn ce-primary" id="ceAddTop">＋ Block</button>'+
      '<button type="button" class="ce-btn" id="ceMenuBtn">☰ Menu</button>'+
      '<button type="button" class="ce-btn" id="ceHeaderTopBtn">⚙ Header</button>'+
      '<button type="button" class="ce-btn" id="ceSliderBtn">🖼 Slider</button>'+
      '<button type="button" class="ce-btn" id="ceTemplateBtn">🎨 Template</button>'+
      '<button type="button" class="ce-btn" id="ceSettingsBtn">⚙ Site Settings</button>'+
      '<a class="ce-btn" id="ceViewSite" href="../public/index.php" target="_blank" rel="noopener">Lihat Situs ↗</a>';
    document.body.insertBefore(bar,document.body.firstChild);
    statusEl=bar.querySelector('#ceStatus');
    bar.querySelector('#ceAddTop').onclick=function(){ openBlockLibrary(null); };
    bar.querySelector('#ceMenuBtn').onclick=function(){ openMenuModal(); };
    bar.querySelector('#ceHeaderTopBtn').onclick=function(){ if(headerModal) headerModal.classList.add('open'); };
    bar.querySelector('#ceSliderBtn').onclick=function(){ openSliderModal(); };
    bar.querySelector('#ceTemplateBtn').onclick=function(){ templateModal.classList.add('open'); };
    bar.querySelector('#ceSettingsBtn').onclick=function(){ settingsModal.classList.add('open'); };
    bar.querySelectorAll('.ce-devices button').forEach(function(b){
      b.onclick=function(){
        bar.querySelectorAll('.ce-devices button').forEach(function(x){ x.classList.remove('active'); });
        b.classList.add('active');
        var device=b.dataset.device;
        var root=document.documentElement;
        root.classList.toggle('ce-mobile-preview',device==='mobile');
        root.classList.toggle('ce-tablet-preview',device==='tablet');
        root.dataset.ceDevice=device;
        // Responsive preview is intentionally Builder-only. The canvas gets a
        // deterministic viewport width while the real site CSS is mirrored by
        // device-specific rules in canvas-editor.css. No content/settings are
        // changed when switching devices.
        root.style.setProperty('--ce-device-width',device==='tablet'?'768px':'100%');
      };
    });
  }

  /* ---------------- editable text ---------------- */
  function setupEditableText(scope){
    (scope||document).querySelectorAll('[data-cms-key]').forEach(function(el){
      if(el.dataset.ceBound) return; el.dataset.ceBound='1';
      var isHtml=el.dataset.cmsHtml==='1';
      el.setAttribute('contenteditable','true');
      el.spellcheck=false;
      if(el.tagName==='A'||el.tagName==='BUTTON'){ el.addEventListener('click',function(e){ e.preventDefault(); }); }
      el.addEventListener('paste',function(e){
        if(isHtml) return;
        e.preventDefault();
        var text=(e.clipboardData||window.clipboardData).getData('text/plain');
        document.execCommand('insertText',false,text);
      });
      var t=null;
      function currentValue(){ return isHtml?el.innerHTML:el.innerText.replace(/\n+$/,''); }
      function queueSave(){
        var val=currentValue();
        if(!isHtml && !val.trim()){ el.innerHTML=''; val=''; }
        if(el.dataset.ceLastSavedValue===val) return;
        el.dataset.ceLastSavedValue=val;
        markSaving();
        saveKV(el.dataset.cmsKey,val).then(function(){ markSaved(); }).catch(function(err){ markError(err.message); });
      }
      function scheduleSave(){ clearTimeout(t); t=setTimeout(queueSave,300); }
      el.addEventListener('focus',function(){ showTypeToolbar(el); if(isHtml) showRichToolbar(el); });
      el.addEventListener('input',scheduleSave);
      el.addEventListener('blur',function(){
        clearTimeout(t); queueSave();
        setTimeout(function(){
          var a=document.activeElement;
          if(!(typeToolbar && typeToolbar.contains(a)) && a!==el) hideTypeToolbar();
          if(!(richToolbar && richToolbar.contains(a)) && a!==el) hideRichToolbar();
        },160);
      });
      if(!isHtml) el.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); el.blur(); } });
    });
  }

  /* V20.7.2.4: header brand text is a plain editable span in Builder.
   * Keep a dedicated save path so the global header label cannot be affected
   * by anchor navigation or other block autosave state. */
  function setupHeaderBrandText(){
    var el=document.querySelector('.ara-header [data-cms-key="site_name"]');
    if(!el || el.dataset.ceHeaderTextBound) return;
    el.dataset.ceHeaderTextBound='1';
    el.setAttribute('contenteditable','true');
    el.spellcheck=false;
    var timer=null;
    function read(){ return (el.innerText||el.textContent||'').replace(/\n+$/,'').trim(); }
    function save(){
      var value=read();
      if(!value) return;
      markSaving();
      post('update_setting',{key:'site_name',value:value}).then(function(j){
        if(String(j.value)!==value) throw new Error('Nama header belum tersimpan');
        el.textContent=value;
        el.dataset.ceLastSavedValue=value;
        markSaved();
      }).catch(function(err){ markError(err.message); });
    }
    el.addEventListener('keydown',function(e){
      if(e.key==='Enter'){ e.preventDefault(); el.blur(); }
    });
    el.addEventListener('input',function(){ clearTimeout(timer); timer=setTimeout(save,300); });
    el.addEventListener('blur',function(){ clearTimeout(timer); save(); });
  }

  /* ---------------- rich text formatting toolbar (for data-cms-html fields: body/paragraph content) ---------------- */
  var richToolbar, lastRichEl=null, lastRange=null;
  function trackRichSelection(){
    var active=document.activeElement;
    if(active && active.dataset && active.dataset.cmsHtml==='1' && active.dataset.cmsKey){
      var sel=window.getSelection();
      if(sel && sel.rangeCount){ lastRichEl=active; lastRange=sel.getRangeAt(0).cloneRange(); }
    }
  }
  document.addEventListener('selectionchange',trackRichSelection);
  function restoreRichSelection(){
    if(!lastRichEl||!lastRange) return false;
    lastRichEl.focus();
    var sel=window.getSelection(); sel.removeAllRanges();
    try{ sel.addRange(lastRange); }catch(e){ return false; }
    return true;
  }
  function afterRichChange(){
    if(lastRichEl) lastRichEl.dispatchEvent(new Event('input',{bubbles:true}));
  }
  function buildRichToolbar(){
    richToolbar=document.createElement('div'); richToolbar.className='ce-rich-toolbar'; richToolbar.contentEditable='false';
    richToolbar.innerHTML=
      '<button type="button" data-cmd="bold" title="Tebal"><b>B</b></button>'+
      '<button type="button" data-cmd="italic" title="Miring"><i>I</i></button>'+
      '<button type="button" data-cmd="underline" title="Garis bawah"><u>U</u></button>'+
      '<span class="ce-sep"></span>'+
      '<select id="ceRichSize" title="Ukuran teks">'+
        '<option value="">Ukuran</option>'+
        '<option value="2">Kecil</option>'+
        '<option value="3">Normal</option>'+
        '<option value="5">Besar</option>'+
        '<option value="6">Judul</option>'+
      '</select>'+
      '<input type="color" id="ceRichColor" title="Warna teks" value="#ffffff">'+
      '<span class="ce-sep"></span>'+
      '<button type="button" data-cmd="link" title="Sisipkan link">🔗</button>'+
      '<button type="button" data-cmd="unlink" title="Hapus link">🔗✕</button>'+
      '<button type="button" data-cmd="image" title="Sisipkan gambar">🖼</button>'+
      '<span class="ce-sep"></span>'+
      '<button type="button" data-cmd="clear" title="Bersihkan format">Tx</button>';
    document.body.appendChild(richToolbar);
    richToolbar.querySelectorAll('button[data-cmd]').forEach(function(btn){
      btn.addEventListener('click',function(e){
        e.preventDefault();
        var cmd=btn.dataset.cmd;
        if(!restoreRichSelection()) return;
        if(cmd==='bold'||cmd==='italic'||cmd==='underline'){ document.execCommand(cmd); afterRichChange(); }
        else if(cmd==='clear'){ document.execCommand('removeFormat'); afterRichChange(); }
        else if(cmd==='unlink'){ document.execCommand('unlink'); afterRichChange(); }
        else if(cmd==='link'){
          var url=window.prompt('Masukkan URL link:','https://');
          if(url && restoreRichSelection()){ document.execCommand('createLink',false,url); afterRichChange(); }
        } else if(cmd==='image'){
          openMediaModal(null,null,'insert');
        }
      });
    });
    richToolbar.querySelector('#ceRichSize').addEventListener('change',function(){
      var v=this.value; this.value='';
      if(v && restoreRichSelection()){ document.execCommand('fontSize',false,v); afterRichChange(); }
    });
    richToolbar.querySelector('#ceRichColor').addEventListener('input',function(){
      if(restoreRichSelection()){ document.execCommand('foreColor',false,this.value); afterRichChange(); }
    });
    richToolbar.addEventListener('mousedown',function(e){
      if(e.target.tagName!=='INPUT' && e.target.tagName!=='SELECT') e.preventDefault();
    });
  }
  var richToolbarReposition;
  function showRichToolbar(el){
    if(!richToolbar) buildRichToolbar();
    lastRichEl=el;
    richToolbar.classList.add('open');
    function position(){
      var r=el.getBoundingClientRect();
      var top=r.top-richToolbar.offsetHeight-8;
      if(top<56) top=Math.min(r.bottom+8,window.innerHeight-44);
      var left=Math.max(8,Math.min(window.innerWidth-richToolbar.offsetWidth-8,r.left));
      richToolbar.style.top=top+'px'; richToolbar.style.left=left+'px';
    }
    position();
    richToolbarReposition=position;
    window.addEventListener('scroll',position,true);
    window.addEventListener('resize',position);
  }
  function hideRichToolbar(){
    if(!richToolbar) return;
    richToolbar.classList.remove('open');
    if(richToolbarReposition){
      window.removeEventListener('scroll',richToolbarReposition,true);
      window.removeEventListener('resize',richToolbarReposition);
      richToolbarReposition=null;
    }
  }

  /* ---------------- button URL editing (popover w/ remove-link) ---------------- */
  var hrefPopover, hrefPopTarget;
  function buildHrefPopover(){
    hrefPopover=document.createElement('div'); hrefPopover.className='ce-href-pop'; hrefPopover.contentEditable='false';
    hrefPopover.innerHTML='<input type="text" id="ceHrefInput" placeholder="https://… atau #contact">'+
      '<div class="ce-href-pop-actions"><button type="button" class="ce-btn ce-primary" id="ceHrefSave">Simpan</button>'+
      '<button type="button" class="ce-btn ce-danger-outline" id="ceHrefClear">Hapus Link</button></div>'+
      '<button type="button" class="ce-btn ce-danger-outline ce-href-pop-full" id="ceHrefDeleteBtn">🗑 Hapus Tombol (teks + link)</button>';
    document.body.appendChild(hrefPopover);
    hrefPopover.querySelector('#ceHrefSave').onclick=function(e){
      e.preventDefault(); e.stopPropagation();
      applyHref(document.getElementById('ceHrefInput').value.trim()||'#');
    };
    hrefPopover.querySelector('#ceHrefClear').onclick=function(e){
      e.preventDefault(); e.stopPropagation();
      applyHref('#');
    };
    hrefPopover.querySelector('#ceHrefDeleteBtn').onclick=function(e){
      e.preventDefault(); e.stopPropagation();
      deleteButtonEntirely();
    };
    hrefPopover.addEventListener('mousedown',function(e){ e.stopPropagation(); });
    document.addEventListener('click',function(e){
      if(hrefPopover.classList.contains('open') && !hrefPopover.contains(e.target) && !e.target.classList.contains('ce-href-btn')) closeHrefPopover();
    });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeHrefPopover(); });
  }
  function applyHref(url){
    if(!hrefPopTarget) return;
    var el=hrefPopTarget;
    el.setAttribute('href',url);
    markSaving();
    saveKV(el.dataset.cmsHrefKey,url).then(markSaved).catch(function(err){ markError(err.message); });
    closeHrefPopover();
  }
  function closeHrefPopover(){ hrefPopover.classList.remove('open'); hrefPopTarget=null; }
  function deleteButtonEntirely(){
    if(!hrefPopTarget) return;
    var el=hrefPopTarget;
    var textKey=el.dataset.cmsKey, hrefKey=el.dataset.cmsHrefKey;
    el.innerHTML=''; el.setAttribute('href','#');
    markSaving();
    Promise.all([
      textKey?saveKV(textKey,''):Promise.resolve(),
      hrefKey?saveKV(hrefKey,'#'):Promise.resolve()
    ]).then(markSaved).catch(function(err){ markError(err.message); });
    closeHrefPopover();
  }
  function openHrefPopover(el,btn){
    hrefPopTarget=el;
    var r=btn.getBoundingClientRect();
    hrefPopover.style.left=Math.max(8,Math.min(window.innerWidth-260,r.left))+'px';
    hrefPopover.style.top=(r.bottom+window.scrollY+6)+'px';
    hrefPopover.classList.add('open');
    var input=document.getElementById('ceHrefInput');
    input.value=(el.getAttribute('href')||'').replace(/^#$/,'');
    setTimeout(function(){ input.focus(); input.select(); },10);
  }
  function setupHrefEdit(scope){
    (scope||document).querySelectorAll('[data-cms-href-key]').forEach(function(el){
      if(el.dataset.ceHrefBound) return; el.dataset.ceHrefBound='1';
      var btn=document.createElement('button');
      btn.type='button'; btn.className='ce-href-btn'; btn.textContent='🔗'; btn.title='Ubah / hapus URL tombol'; btn.contentEditable='false';
      btn.addEventListener('mousedown',function(e){ e.preventDefault(); e.stopPropagation(); });
      btn.addEventListener('click',function(e){ e.preventDefault(); e.stopPropagation(); openHrefPopover(el,btn); });
      el.insertAdjacentElement('afterend',btn);
    });
  }

  /* ---------------- images ---------------- */
  function setupImages(scope){
    (scope||document).querySelectorAll('[data-cms-image]').forEach(function(el){
      if(el.dataset.ceBound) return; el.dataset.ceBound='1';
      el.addEventListener('click',function(){ openMediaModal(el.dataset.cmsImage,el); });
    });
  }
  function buildMediaModal(){
    mediaModal=document.createElement('div'); mediaModal.className='ce-modal';
    mediaModal.innerHTML='<div class="ce-modal-box"><div class="ce-modal-head"><h3>Pilih / Upload Gambar</h3><button type="button" class="ce-modal-close">✕</button></div>'+
      '<div class="ce-dropzone" id="ceDrop"><label>Klik atau seret gambar ke sini<input type="file" id="ceUpload" accept="image/*"></label><small>JPG, PNG, WebP, GIF — maks 5MB</small></div>'+
      '<div class="ce-media-grid" id="ceMediaGrid"></div></div>';
    document.body.appendChild(mediaModal);
    mediaGrid=mediaModal.querySelector('#ceMediaGrid');
    mediaModal.querySelector('.ce-modal-close').onclick=function(){ mediaModal.classList.remove('open','ce-media-picker'); mediaTarget=null; };
    mediaModal.addEventListener('click',function(e){ if(e.target===mediaModal){ mediaModal.classList.remove('open','ce-media-picker'); mediaTarget=null; } });
    var drop=mediaModal.querySelector('#ceDrop');
    mediaModal.querySelector('#ceUpload').onchange=function(e){ uploadImage(e.target.files[0]); };
    ['dragenter','dragover'].forEach(function(x){ drop.addEventListener(x,function(e){ e.preventDefault(); drop.classList.add('drag-over'); }); });
    ['dragleave','drop'].forEach(function(x){ drop.addEventListener(x,function(e){ e.preventDefault(); drop.classList.remove('drag-over'); }); });
    drop.addEventListener('drop',function(e){ uploadImage(e.dataTransfer.files[0]); });
  }
  function openMediaModal(key,el,mode){
    mediaTarget={key:key,el:el,mode:mode||'block'};
    mediaModal.classList.toggle('ce-media-picker',!!(mode==='slider' || (sliderModal && sliderModal.classList.contains('open'))));
    mediaModal.classList.add('open'); loadMedia();
  }
  function loadMedia(){
    mediaGrid.textContent='Memuat…';
    fetch('media_api.php').then(function(r){ return r.json(); }).then(function(data){
      if(!Array.isArray(data)){ mediaGrid.textContent='Gagal memuat media.'; return; }
      if(!data.length){ mediaGrid.innerHTML='<p>Belum ada gambar. Upload dulu di atas.</p>'; return; }
      mediaGrid.innerHTML='';
      data.forEach(function(x){
        var item=document.createElement('div'); item.className='ce-media-item';
        var img=document.createElement('img'); img.src=x.url; item.appendChild(img);
        item.onclick=function(){ chooseImage(x.file); };
        mediaGrid.appendChild(item);
      });
    }).catch(function(){ mediaGrid.textContent='Gagal memuat media.'; });
  }
  function uploadImage(file){
    if(!file) return;
    var fd=new FormData(); fd.append('csrf',CSRF); fd.append('image',file);
    fetch('media_api.php',{method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){
      if(j.ok){ loadMedia(); chooseImage(j.file); } else alert(j.error||'Upload gagal');
    }).catch(function(){ alert('Upload gagal'); });
  }
  function chooseImage(file){
    if(!mediaTarget) return;
    if(mediaTarget.mode==='slider'){
      var si=mediaTarget.index;
      var row=sliderModal && sliderModal.querySelector('.ce-slider-row[data-index="'+si+'"]');
      if(row){
        row.dataset.image=file;
        var thumb=row.querySelector('.ce-slider-thumb');
        var im=row.querySelector('img');
        var empty=row.querySelector('.ce-slider-empty');
        var url='../public/uploads/'+encodeURIComponent(file);
        if(!im){
          im=document.createElement('img');
          im.alt='';
          if(thumb) thumb.insertBefore(im,thumb.firstChild);
        }
        im.src=url+'?v='+Date.now();
        im.style.display='block';
        if(empty) empty.style.display='none';
        row.classList.add('has-image');
        var status=row.querySelector('.ce-slider-image-status');
        if(!status){
          status=document.createElement('small');
          status.className='ce-slider-image-status';
          var actions=row.querySelector('.ce-slider-meta > div');
          if(actions) actions.appendChild(status);
        }
        status.textContent='✓ Gambar dipilih';
      }
      mediaModal.classList.remove('open','ce-media-picker'); mediaTarget=null; return;
    }
    if(mediaTarget.mode==='insert'){
      mediaModal.classList.remove('open','ce-media-picker');
      var url='../public/uploads/'+encodeURIComponent(file);
      if(restoreRichSelection()){
        document.execCommand('insertImage',false,url);
        afterRichChange();
      }
      mediaTarget=null;
      return;
    }
    var key=mediaTarget.key, el=mediaTarget.el;
    el.src='../public/uploads/'+encodeURIComponent(file);
    if(key==='logo_image'){
      var slot=el.closest('.ara-brand-logo-slot');
      if(slot) slot.classList.remove('is-placeholder'), slot.classList.add('has-logo');
    }
    mediaModal.classList.remove('open','ce-media-picker');
    markSaving();
    saveKV(key,file).then(markSaved).catch(function(err){ markError(err.message); });
    mediaTarget=null;
  }

  /* ---------------- global areas (hero) ---------------- */
  function setupGlobalAreas(){
    document.querySelectorAll('[data-global-area]').forEach(function(area){
      var btn=area.querySelector('[data-global-toggle]');
      if(!btn || btn.dataset.ceBound) return;
      btn.dataset.ceBound='1';
      btn.addEventListener('click',function(e){
        e.preventDefault(); e.stopPropagation();
        var visible=area.dataset.globalVisible!=='1';
        var name=area.dataset.globalArea;
        markSaving();
        post('update_setting',{key:name+'_visible',value:visible?'1':'0'}).then(function(){
          area.dataset.globalVisible=visible?'1':'0';
          window.location.reload();
        }).catch(function(err){ markError(err.message); });
      });
      var del=area.querySelector('[data-global-delete]');
      if(del && !del.dataset.ceBound){
        del.dataset.ceBound='1';
        del.addEventListener('click',function(e){
          e.preventDefault(); e.stopPropagation();
          if(!confirm('Hapus Hero dari halaman?')) return;
          markSaving();
          Promise.all([
            post('update_setting',{key:'hero_visible',value:'0'}),
            post('update_setting',{key:'hero_deleted',value:'1'})
          ]).then(function(){
            window.location.reload();
          }).catch(function(err){ markError(err.message); });
        });
      }
    });
  }

  /* ---------------- header logo controls ---------------- */
  function setupLogoControls(){
    var del=document.querySelector('.ce-logo-delete');
    if(del && !del.dataset.ceBound){
      del.dataset.ceBound='1';
      del.addEventListener('click',function(e){
        e.preventDefault(); e.stopPropagation();
        if(!confirm('Hapus logo dari header? File tetap tersimpan di Media Library.')) return;
        markSaving();
        post('update_setting',{key:'logo_image',value:''}).then(function(){
          markSaved();
          window.location.reload();
        }).catch(function(err){ markError(err.message); });
      });
    }
  }

  /* ---------------- header logo resize ---------------- */
  function setupLogoResize(){
    var handle=document.querySelector('.ce-logo-resize'), img=document.querySelector('.ara-brand-logo');
    if(!handle || !img || handle.dataset.ceBound) return;
    handle.dataset.ceBound='1';
    var startX=0, startY=0, startW=0, startH=0, dragging=false;
    handle.addEventListener('mousedown',function(e){
      e.preventDefault(); e.stopPropagation();
      dragging=true; startX=e.clientX; startY=e.clientY;
      var r=img.getBoundingClientRect();
      startW=Math.round(r.width)||52; startH=Math.round(r.height)||52;
      document.body.classList.add('ce-resizing-logo');
    });
    window.addEventListener('mousemove',function(e){
      if(!dragging) return;
      var w=Math.min(240,Math.max(28,startW+(e.clientX-startX)));
      var h=Math.min(180,Math.max(28,startH+(e.clientY-startY)));
      img.style.width=w+'px';
      img.style.height=h+'px';
      document.body.dataset.ceLogoWidth=String(w);
      document.body.dataset.ceLogoHeight=String(h);
    });
    window.addEventListener('mouseup',function(){
      if(!dragging) return;
      dragging=false; document.body.classList.remove('ce-resizing-logo');
      var r=img.getBoundingClientRect();
      var w=Math.min(240,Math.max(28,Math.round(r.width)||52));
      var h=Math.min(180,Math.max(28,Math.round(r.height)||52));
      applyHeaderPreview();
      markSaving();
      Promise.all([
        post('update_setting',{key:'logo_width',value:String(w)}),
        post('update_setting',{key:'logo_height',value:String(h)})
      ]).then(markSaved).catch(function(err){ markError(err.message); });
    });
  }

  /* ---------------- block toolbar / layout / reorder ---------------- */
  function cleanupBlockToolbars(scope){
    (scope||document).querySelectorAll('[data-block-id]').forEach(function(sec){
      var bars=sec.querySelectorAll(':scope > .ce-block-toolbar');
      for(var i=1;i<bars.length;i++) bars[i].remove();
    });
  }
  function setupBlocks(scope){
    cleanupBlockToolbars(scope);
    (scope||document).querySelectorAll('[data-block-id]').forEach(function(sec){
      if(sec.dataset.ceBlockBound){
        if(!sec.querySelector(':scope > .ce-block-toolbar')) buildBlockToolbar(sec);
        return;
      } sec.dataset.ceBlockBound='1';
      buildBlockToolbar(sec);
      sec.addEventListener('click',function(e){
        if(e.target.closest('[data-cms-key],[data-cms-image],.ce-block-toolbar,.ce-href-btn')) return;
        document.querySelectorAll('.dynamic-block.ce-selected').forEach(function(x){ x.classList.remove('ce-selected'); });
        sec.classList.add('ce-selected');
      });
    });
    var heroes=document.querySelectorAll('[data-block-id][data-block-type="hero"]');
    document.body.dataset.ceHeroCount=String(heroes.length);
    if(heroes.length>1) document.body.classList.add('ce-multiple-heroes'); else document.body.classList.remove('ce-multiple-heroes');
    refreshMoveButtons();
  }
  /* Grey out ▲ on the first block and ▼ on the last block, since dir would be a no-op there. */
  function refreshMoveButtons(){
    var blocks=Array.prototype.filter.call(document.querySelectorAll('[data-block-id]'),function(x){ return x.hasAttribute('data-block-id'); });
    blocks.forEach(function(sec,i){
      var bar=sec.querySelector(':scope > .ce-block-toolbar');
      if(!bar) return;
      var up=bar.querySelector('[data-act="move-up"]'), down=bar.querySelector('[data-act="move-down"]');
      if(up) up.disabled=(i===0);
      if(down) down.disabled=(i===blocks.length-1);
    });
  }
  function buildBlockToolbar(sec){
    var id=sec.dataset.blockId;
    var type=sec.dataset.blockType||'feature';
    var layout=sec.dataset.blockLayout||'image-right';
    var active=sec.dataset.active!=='0';
    if(active===false) sec.classList.add('cms-hidden-block');
    var bar=document.createElement('div'); bar.className='ce-block-toolbar'; bar.contentEditable='false';
    var layouts=[['image-right','⬜▪','Teks kiri, gambar kanan'],['image-left','▪⬜','Gambar kiri, teks kanan'],['center','▪▪','Tengah'],['full','▭▭','Full width']];
    var layoutBtns=layouts.map(function(l){ return '<button type="button" data-act="layout" data-v="'+l[0]+'" class="'+(l[0]===layout?'active':'')+'" title="'+l[2]+'">'+l[1]+'</button>'; }).join('');
    bar.innerHTML=
      '<span class="ce-drag" title="Seret untuk urutkan">⠿</span>'+
      '<button type="button" data-act="move-up" title="Pindah ke atas">▲</button>'+
      '<button type="button" data-act="move-down" title="Pindah ke bawah">▼</button>'+
      '<span class="ce-tag">'+esc(type)+'</span>'+
      '<span class="ce-sep"></span>'+
      layoutBtns+
      '<span class="ce-sep"></span>'+
      '<input type="color" data-act="bg" value="'+rgbToHex(sec.style.backgroundColor)+'" title="Warna latar">'+
      '<button type="button" data-act="toggle" title="'+(active?'Sembunyikan block':'Tampilkan block')+'">'+(active?'👁':'🚫')+'</button>'+
      '<button type="button" data-act="duplicate" title="Duplikat">⧉</button>'+
      '<button type="button" data-act="add" title="Tambah block di bawah">＋</button>'+
      '<button type="button" data-act="delete" class="ce-danger" title="Hapus">🗑</button>';
    sec.insertBefore(bar,sec.firstChild);

    bar.querySelectorAll('[data-act="layout"]').forEach(function(btn){
      btn.onclick=function(e){
        e.stopPropagation();
        var v=btn.dataset.v;
        bar.querySelectorAll('[data-act="layout"]').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        sec.className=sec.className.replace(/\bblock-(image-right|image-left|center|full)\b/,'block-'+v);
        sec.dataset.blockLayout=v;
        markSaving();
        post('update_section',{id:parseInt(id,10),field:'layout',value:v}).then(markSaved).catch(function(err){ markError(err.message); });
      };
    });
    bar.querySelector('[data-act="bg"]').addEventListener('input',function(e){
      e.stopPropagation();
      sec.style.background=e.target.value;
      clearTimeout(bgDebounce);
      bgDebounce=setTimeout(function(){
        markSaving();
        post('update_section',{id:parseInt(id,10),field:'bg_color',value:e.target.value}).then(markSaved).catch(function(err){ markError(err.message); });
      },400);
    });
    bar.querySelector('[data-act="toggle"]').onclick=function(e){
      e.stopPropagation();
      var nowActive=sec.dataset.active==='0';
      sec.dataset.active=nowActive?'1':'0';
      sec.classList.toggle('cms-hidden-block',!nowActive);
      e.currentTarget.textContent=nowActive?'👁':'🚫';
      e.currentTarget.title=nowActive?'Sembunyikan block':'Tampilkan block';
      markSaving();
      post('toggle',{id:parseInt(id,10),active:nowActive}).then(markSaved).catch(function(err){ markError(err.message); });
    };
    bar.querySelector('[data-act="duplicate"]').onclick=function(e){
      e.stopPropagation();
      markSaving();
      post('duplicate',{id:parseInt(id,10)}).then(function(j){ insertBlockHtml(j.html,sec,true); markSaved(); }).catch(function(err){ markError(err.message); });
    };
    bar.querySelector('[data-act="add"]').onclick=function(e){ e.stopPropagation(); openBlockLibrary(sec); };
    bar.querySelector('[data-act="delete"]').onclick=function(e){
      e.stopPropagation();
      if(!window.confirm('Hapus block ini?')) return;
      markSaving();
      post('delete',{id:parseInt(id,10)}).then(function(){ sec.remove(); markSaved(); refreshMoveButtons(); }).catch(function(err){ markError(err.message); });
    };
    bar.querySelector('[data-act="move-up"]').onclick=function(e){ e.stopPropagation(); moveBlockStep(sec,-1); };
    bar.querySelector('[data-act="move-down"]').onclick=function(e){ e.stopPropagation(); moveBlockStep(sec,1); };

    var dragHandle=bar.querySelector('.ce-drag');
    dragHandle.setAttribute('draggable','true');
    dragHandle.addEventListener('dragstart',function(e){
      draggedBlock=sec;
      sec.classList.add('ce-drag-ghost');
      e.dataTransfer.effectAllowed='move';
      e.dataTransfer.setData('text/plain',id);
      try{ e.dataTransfer.setDragImage(sec,Math.min(120,sec.offsetWidth/2),30); }catch(_){ }
    });
    dragHandle.addEventListener('dragend',function(){
      sec.classList.remove('ce-drag-ghost');
      draggedBlock=null;
      document.querySelectorAll('.ce-drop-target').forEach(function(x){x.classList.remove('ce-drop-target');});
      persistOrder();
    });
  }
  document.addEventListener('dragover',function(e){
    if(!draggedBlock) return;
    var over=e.target.closest && e.target.closest('[data-block-id]');
    if(!over || over===draggedBlock || !over.parentNode) return;
    e.preventDefault();
    var rect=over.getBoundingClientRect();
    var before=(e.clientY-rect.top)<rect.height/2;
    document.querySelectorAll('.ce-drop-target').forEach(function(x){x.classList.remove('ce-drop-target');});
    over.classList.add('ce-drop-target');
    over.parentNode.insertBefore(draggedBlock,before?over:over.nextSibling);
  });
  /* Move a block one step up/down among its data-block-id siblings (ignores
     the fixed hero/about/contact sections in between, since those never
     carry data-block-id) and reuses the same reorder endpoint as drag&drop. */
  function moveBlockStep(sec,dir){
    var siblings=Array.prototype.filter.call(sec.parentNode.children,function(x){ return x.hasAttribute('data-block-id'); });
    var idx=siblings.indexOf(sec);
    var targetIdx=idx+dir;
    if(idx===-1 || targetIdx<0 || targetIdx>=siblings.length) return;
    var target=siblings[targetIdx];
    if(dir<0) sec.parentNode.insertBefore(sec,target); else sec.parentNode.insertBefore(target,sec);
    sec.scrollIntoView({block:'nearest',behavior:'smooth'});
    persistOrder();
  }
  function persistOrder(){
    var ids=Array.prototype.map.call(document.querySelectorAll('[data-block-id]'),function(x){ return x.dataset.blockId; });
    markSaving();
    refreshMoveButtons();
    post('reorder',{order:ids}).then(markSaved).catch(function(err){ markError(err.message); });
  }

  /* ---------------- add block library ---------------- */
  function buildLibraryModal(){
    var registry={};
    try{ registry=JSON.parse(document.body.dataset.ceBlockRegistry||'{}'); }catch(e){}
    var types=Object.keys(registry).map(function(k){ var x=registry[k]||{}; return [k,x.icon||'▦',x.label||k,x.description||'Block']; });
    libModal=document.createElement('div'); libModal.className='ce-modal';
    libModal.innerHTML='<div class="ce-modal-box"><div class="ce-modal-head"><h3>Tambah Block</h3><button type="button" class="ce-modal-close">✕</button></div><div class="ce-library-grid">'+
      types.map(function(t){ return '<button type="button" class="ce-library-item" data-type="'+t[0]+'"><b>'+t[1]+' '+t[2]+'</b><small>'+t[3]+'</small></button>'; }).join('')+
      '</div></div>';
    document.body.appendChild(libModal);
    libModal.querySelector('.ce-modal-close').onclick=function(){ libModal.classList.remove('open'); };
    libModal.addEventListener('click',function(e){ if(e.target===libModal) libModal.classList.remove('open'); });
    libModal.querySelectorAll('.ce-library-item').forEach(function(btn){
      btn.onclick=function(){
        var type=btn.dataset.type;
        libModal.classList.remove('open');
        markSaving();
        post('add',{block_type:type,after_id:(libTarget&&libTarget.dataset.blockId)?parseInt(libTarget.dataset.blockId,10):null})
          .then(function(j){ insertBlockHtml(j.html,libTarget,true); markSaved(); })
          .catch(function(err){ markError(err.message); });
      };
    });
  }
  function openBlockLibrary(afterEl){ libTarget=afterEl; libModal.classList.add('open'); }
  function insertBlockHtml(html,afterEl,selectNew){
    var tmp=document.createElement('div'); tmp.innerHTML=html.trim();
    var node=tmp.firstElementChild;
    if(!node) return null;
    if(afterEl && afterEl.parentNode){ afterEl.parentNode.insertBefore(node,afterEl.nextSibling); }
    else {
      /* No target means the global "+ Add Block" action.
         New blocks must be appended after the last dynamic block so the
         visual builder order matches the DB sort_order immediately. */
      var blocks=document.querySelectorAll('[data-block-id]');
      var last=blocks.length?blocks[blocks.length-1]:null;
      if(last && last.parentNode) last.parentNode.insertBefore(node,last.nextSibling);
      else {
        var hero=document.querySelector('.ara-hero');
        if(hero) hero.insertAdjacentElement('afterend',node); else document.getElementById('top').appendChild(node);
      }
    }
    bindAll(node);
    if(selectNew){
      document.querySelectorAll('.dynamic-block.ce-selected').forEach(function(x){ x.classList.remove('ce-selected'); });
      node.classList.add('ce-selected');
      node.scrollIntoView({behavior:'smooth',block:'center'});
    }
    refreshMoveButtons();
    return node;
  }

  /* ---------------- global header settings (isolated) ---------------- */
  function applyHeaderPreview(){
    var b=document.body, h=document.querySelector('[data-ce-header]');
    if(!b || !h || !headerModal) return;
    var color=(headerModal.querySelector('#ceHeaderBg')||{}).value||'#1b1b1b';
    var mode=(headerModal.querySelector('#ceHeaderMode')||{}).value==='custom'?'custom':'auto';
    var height=Math.min(320,Math.max(48,parseInt((headerModal.querySelector('#ceHeaderHeight')||{}).value,10)||70));
    var pad=Math.min(80,Math.max(0,parseInt((headerModal.querySelector('#ceHeaderPadY')||{}).value,10)||0));
    var logoH=Math.min(180,Math.max(28,parseInt(b.dataset.ceLogoHeight||'52',10)||52));
    var effective=Math.max(48,logoH+(pad*2));
    if(mode==='custom') effective=Math.max(height,effective);
    h.style.setProperty('--ara-header-pad-y',pad+'px');
    h.style.setProperty('--ara-header-custom-height',height+'px');
    h.style.setProperty('--ara-header-logo-height',logoH+'px');
    h.style.setProperty('--ara-header-effective-height',effective+'px');
    h.style.setProperty('background',color,'important');
    h.style.setProperty('min-height',effective+'px','important');
    h.style.setProperty('height',effective+'px','important');
    h.style.setProperty('padding-top',pad+'px','important');
    h.style.setProperty('padding-bottom',pad+'px','important');
    b.style.setProperty('--ara-header-scroll',effective+'px');
    b.dataset.ceHeaderBackground=color; b.dataset.ceHeaderHeightMode=mode; b.dataset.ceHeaderHeight=String(height); b.dataset.ceHeaderPaddingY=String(pad);
  }
  function buildHeaderModal(){
    var b=document.body;
    if(!b || !document.querySelector('[data-ce-header]')) return;
    headerModal=document.createElement('div'); headerModal.className='ce-modal ce-header-settings-modal';
    var bgVal=b.dataset.ceHeaderBackground||'#1b1b1b';
    if(!/^#[0-9a-fA-F]{6}$/.test(bgVal)) bgVal='#1b1b1b';
    headerModal.innerHTML='<div class="ce-modal-box ce-header-modal"><div class="ce-modal-head"><h3>Global Header</h3><button type="button" class="ce-modal-close">✕</button></div>'+ 
      '<p class="ce-settings-hint">Header mengikuti ukuran logo secara otomatis. Padding memberi ruang tambahan agar logo tidak terpotong.</p>'+ 
      '<div class="ce-field"><label>Background</label><div class="ce-color-row"><input id="ceHeaderBg" type="text" value="'+esc(bgVal)+'" placeholder="#1b1b1b"><input id="ceHeaderBgColor" type="color" value="'+bgVal+'"></div></div>'+ 
      '<div class="ce-field"><label>Mode Tinggi</label><select id="ceHeaderMode"><option value="auto">Auto — mengikuti logo</option><option value="custom">Custom minimum</option></select></div>'+ 
      '<div class="ce-field"><label>Custom Height (px)</label><input id="ceHeaderHeight" type="number" min="48" max="320" value="'+esc(b.dataset.ceHeaderHeight||'70')+'"></div>'+ 
      '<div class="ce-field"><label>Vertical Padding (px)</label><input id="ceHeaderPadY" type="number" min="0" max="80" value="'+esc(b.dataset.ceHeaderPaddingY||'12')+'"></div>'+ 
      '<div class="ce-header-live-note">Logo boleh lebih besar dari tinggi default header. Header akan selalu menyediakan ruang minimum yang cukup.</div>'+ 
      '<button type="button" class="ce-btn ce-primary" id="ceSaveHeader">Simpan Header</button></div>';
    document.body.appendChild(headerModal);
    var mode=headerModal.querySelector('#ceHeaderMode');
    mode.value=b.dataset.ceHeaderHeightMode==='custom'?'custom':'auto';
    var bg=headerModal.querySelector('#ceHeaderBg'), bgc=headerModal.querySelector('#ceHeaderBgColor');
    bgc.addEventListener('input',function(){ bg.value=bgc.value; applyHeaderPreview(); });
    bg.addEventListener('input',function(){ if(/^#[0-9a-fA-F]{6}$/.test(bg.value.trim())) bgc.value=bg.value.trim(); applyHeaderPreview(); });
    mode.addEventListener('change',applyHeaderPreview);
    headerModal.querySelector('#ceHeaderHeight').addEventListener('input',applyHeaderPreview);
    headerModal.querySelector('#ceHeaderPadY').addEventListener('input',applyHeaderPreview);
    headerModal.querySelector('.ce-modal-close').onclick=function(){headerModal.classList.remove('open');};
    headerModal.addEventListener('click',function(e){if(e.target===headerModal) headerModal.classList.remove('open');});
    headerModal.querySelector('#ceSaveHeader').onclick=function(){
      var color=bg.value.trim(); if(!/^#[0-9a-fA-F]{6}$/.test(color)) color='#1b1b1b';
      var h=Math.min(320,Math.max(48,parseInt(headerModal.querySelector('#ceHeaderHeight').value,10)||70));
      var py=Math.min(80,Math.max(0,parseInt(headerModal.querySelector('#ceHeaderPadY').value,10)||0));
      var m=mode.value==='custom'?'custom':'auto';
      markSaving();
      Promise.all([
        post('update_setting',{key:'header_background',value:color}),
        post('update_setting',{key:'header_height_mode',value:m}),
        post('update_setting',{key:'header_height',value:String(h)}),
        post('update_setting',{key:'header_padding_y',value:String(py)})
      ]).then(function(){
        markSaved(); headerModal.classList.remove('open'); window.location.reload();
      }).catch(function(err){markError(err.message);});
    };
    var trigger=document.getElementById('ceHeaderEdit');
    if(trigger) trigger.onclick=function(){headerModal.classList.add('open');};
  }

  /* ---------------- site settings modal ---------------- */
  function buildSettingsModal(){
    var b=document.body;
    settingsModal=document.createElement('div'); settingsModal.className='ce-modal';
    settingsModal.innerHTML='<div class="ce-modal-box"><div class="ce-modal-head"><h3>Site Settings</h3><button type="button" class="ce-modal-close">✕</button></div>'+
      '<div class="ce-field"><label>Email Kontak (penerima form kontak)</label><input id="ceEmail" value="'+esc(b.dataset.ceContactEmail||'')+'"></div>'+
      '<div class="ce-field"><label>Meta Description (SEO)</label><textarea id="ceMeta" rows="3">'+esc(b.dataset.ceMetaDescription||'')+'</textarea></div>'+
      '<div class="ce-field"><label>Canonical URL (SEO)</label><input id="ceCanon" value="'+esc(b.dataset.ceCanonicalUrl||'')+'"></div>'+
      '<div class="ce-settings-divider">Social Media Footer</div>'+
      '<div class="ce-social-settings-grid">'+
      '<div class="ce-field"><label>Facebook</label><input id="ceSocialFacebook" placeholder="https://facebook.com/..." value="'+esc(b.dataset.ceSocialFacebook||'')+'"></div>'+
      '<div class="ce-field"><label>Instagram</label><input id="ceSocialInstagram" placeholder="https://instagram.com/..." value="'+esc(b.dataset.ceSocialInstagram||'')+'"></div>'+
      '<div class="ce-field"><label>X / Twitter</label><input id="ceSocialX" placeholder="https://x.com/..." value="'+esc(b.dataset.ceSocialX||'')+'"></div>'+
      '<div class="ce-field"><label>LinkedIn</label><input id="ceSocialLinkedin" placeholder="https://linkedin.com/in/..." value="'+esc(b.dataset.ceSocialLinkedin||'')+'"></div>'+
      '<div class="ce-field"><label>WhatsApp</label><input id="ceSocialWhatsapp" placeholder="https://wa.me/628..." value="'+esc(b.dataset.ceSocialWhatsapp||'')+'"></div>'+
      '</div>'+
      '<small class="ce-settings-hint">Klik icon sosial di footer juga akan membuka pengaturan ini.</small>'+
      '<button type="button" class="ce-btn ce-primary" id="ceSaveSettings">Simpan</button></div>';
    document.body.appendChild(settingsModal);
    settingsModal.querySelector('.ce-modal-close').onclick=function(){ settingsModal.classList.remove('open'); };
    settingsModal.addEventListener('click',function(e){ if(e.target===settingsModal) settingsModal.classList.remove('open'); });
    document.addEventListener('click',function(e){
      var social=e.target.closest&&e.target.closest('[data-cms-social]');
      if(!social || !document.body.classList.contains('ara-edit-mode')) return;
      e.preventDefault();
      var key=social.getAttribute('data-cms-social');
      var map={facebook:'ceSocialFacebook',instagram:'ceSocialInstagram',x:'ceSocialX',linkedin:'ceSocialLinkedin',whatsapp:'ceSocialWhatsapp'};
      settingsModal.classList.add('open');
      var target=document.getElementById(map[key]); if(target){ target.focus(); target.select(); }
    });
    settingsModal.querySelector('#ceSaveSettings').onclick=function(){
      markSaving();
      Promise.all([
        post('update_setting',{key:'contact_email',value:document.getElementById('ceEmail').value}),
        post('update_setting',{key:'meta_description',value:document.getElementById('ceMeta').value}),
        post('update_setting',{key:'canonical_url',value:document.getElementById('ceCanon').value}),
        post('update_setting',{key:'social_facebook',value:document.getElementById('ceSocialFacebook').value}),
        post('update_setting',{key:'social_instagram',value:document.getElementById('ceSocialInstagram').value}),
        post('update_setting',{key:'social_x',value:document.getElementById('ceSocialX').value}),
        post('update_setting',{key:'social_linkedin',value:document.getElementById('ceSocialLinkedin').value}),
        post('update_setting',{key:'social_whatsapp',value:document.getElementById('ceSocialWhatsapp').value})
      ]).then(function(){ markSaved(); settingsModal.classList.remove('open'); }).catch(function(err){ markError(err.message); });
    };
  }

  /* ---------------- menu manager ---------------- */
  var menuModal, menuDragRow=null;
  function getEditorSections(){
    var a=[]; try{ a=JSON.parse(document.body.dataset.ceSections||'[]'); }catch(e){ a=[]; }
    return Array.isArray(a)?a:[];
  }
  function menuTargetOptions(selectedType, selectedId){
    var sections=getEditorSections();
    var html='<option value="">Pilih section…</option>';
    sections.forEach(function(sec){
      var id=String(sec.id), title=sec.title||('Block #'+id), anchor=sec.anchor_id||('block-'+id);
      html+='<option value="'+esc(id)+'"'+(String(selectedId)===id?' selected':'')+'>'+esc(title)+'  —  #'+esc(anchor)+'</option>';
    });
    return html;
  }
  function buildMenuModal(){
    menuModal=document.createElement('div'); menuModal.className='ce-modal';
    var fontOpts=FONT_CHOICES.map(function(f){return '<option value="'+esc(f[0])+'">'+esc(f[1])+'</option>';}).join('');
    menuModal.innerHTML='<div class="ce-modal-box ce-menu-modal-box"><div class="ce-modal-head"><h3>Kelola Menu Navigasi</h3><button type="button" class="ce-modal-close">✕</button></div>'+ 
      '<p class="ce-theme-hint">Menu section terhubung ke <b>Block ID</b>, bukan URL manual. Jadi rename, drag, atau ganti template tidak memutus target. Menu lama dengan URL tetap didukung.</p>'+ 
      '<div id="ceMenuRows" class="ce-menu-rows"></div>'+ 
      '<button type="button" class="ce-btn" id="ceMenuAddRow">＋ Tambah Menu</button>'+ 
      '<div class="ce-menu-typography"><div class="ce-menu-typography-title">Typography Menu <span style="opacity:.6;font-size:11px">(tersimpan global)</span></div><div class="ce-menu-type-grid">'+
      '<label>Font<select id="ceNavFont"><option value="">Bawaan</option>'+fontOpts+'</select></label>'+
      '<label>Ukuran (px)<input id="ceNavSize" type="number" min="8" max="60" step="1" placeholder="13"></label>'+
      '<label>Ketebalan<select id="ceNavWeight"><option value="400">Regular</option><option value="500">Medium</option><option value="600">Semibold</option><option value="700">Bold</option><option value="800">Extra Bold</option><option value="900">Black</option></select></label>'+
      '<label>Warna<input id="ceNavColor" type="color" value="#e5e5e5"></label></div>'+
      '<small>Pengaturan ini khusus teks menu. Tidak mengubah font logo/site title.</small></div>'+
      '<div class="ce-modal-actions"><button type="button" class="ce-btn ce-primary" id="ceMenuSave">Simpan Menu</button></div></div>';
    document.body.appendChild(menuModal);
    menuModal.querySelector('.ce-modal-close').onclick=function(){ menuModal.classList.remove('open'); };
    menuModal.addEventListener('click',function(e){ if(e.target===menuModal) menuModal.classList.remove('open'); });
    menuModal.querySelector('#ceMenuAddRow').onclick=function(){ addMenuRow('Menu Baru','url',0,'#'); };
    menuModal.querySelector('#ceMenuSave').onclick=saveMenu;
    ['ceNavFont','ceNavSize','ceNavWeight','ceNavColor'].forEach(function(id){ var el=menuModal.querySelector('#'+id); if(el) el.addEventListener(el.type==='color'?'input':'change',applyNavTypographyPreview); });
    bindMenuDrag();
  }
  function addMenuRow(label,type,sectionId,url){
    var rows=menuModal.querySelector('#ceMenuRows');
    var row=document.createElement('div'); row.className='ce-menu-row'; row.dataset.targetType=type||'url'; row.dataset.sectionId=String(sectionId||'');
    row.innerHTML='<span class="ce-drag" title="Seret untuk urutkan">⠿</span>'+ 
      '<input type="text" class="ce-menu-label" placeholder="Label" value="'+esc(label)+'">'+
      '<select class="ce-menu-type"><option value="section">Section / Block</option><option value="url">URL manual</option></select>'+ 
      '<select class="ce-menu-section">'+menuTargetOptions(type,sectionId)+'</select>'+ 
      '<input type="text" class="ce-menu-url" placeholder="https://… atau /halaman" value="'+esc(url||'')+'">'+
      '<span class="ce-menu-status"></span><button type="button" class="ce-menu-remove" title="Hapus menu">🗑</button>';
    var typeEl=row.querySelector('.ce-menu-type'), secEl=row.querySelector('.ce-menu-section'), urlEl=row.querySelector('.ce-menu-url'), status=row.querySelector('.ce-menu-status');
    typeEl.value=(type==='section'?'section':'url');
    function sync(){
      var isSec=typeEl.value==='section'; secEl.style.display=isSec?'block':'none'; urlEl.style.display=isSec?'none':'block';
      row.dataset.targetType=typeEl.value; row.dataset.sectionId=isSec?(secEl.value||''):'';
      status.textContent=isSec && !secEl.value ? '⚠ Pilih target' : '';
      status.className='ce-menu-status'+(isSec&&!secEl.value?' orphan':'');
    }
    typeEl.onchange=sync; secEl.onchange=sync; row.querySelector('.ce-menu-remove').onclick=function(){ row.remove(); };
    sync(); rows.appendChild(row);
  }
  function bindMenuDrag(){
    if(!menuModal) return;
    var rows=menuModal.querySelector('#ceMenuRows');
    rows.addEventListener('dragover',function(e){
      if(!menuDragRow) return;
      var over=e.target.closest && e.target.closest('.ce-menu-row');
      if(!over || over===menuDragRow) return;
      e.preventDefault(); var rect=over.getBoundingClientRect();
      rows.insertBefore(menuDragRow,(e.clientY-rect.top)<rect.height/2?over:over.nextSibling);
    });
  }
  function wireMenuRowDrag(row){
    var handle=row.querySelector('.ce-drag'); if(!handle) return;
    handle.setAttribute('draggable','true');
    handle.addEventListener('dragstart',function(e){ menuDragRow=row; row.classList.add('ce-drag-ghost'); e.dataTransfer.effectAllowed='move'; });
    handle.addEventListener('dragend',function(){ row.classList.remove('ce-drag-ghost'); menuDragRow=null; });
  }
  // Patch row creation so each newly created row gets its drag handle wired.
  var _addMenuRow=addMenuRow;
  addMenuRow=function(){ _addMenuRow.apply(null,arguments); var rows=menuModal.querySelectorAll('.ce-menu-row'); wireMenuRowDrag(rows[rows.length-1]); };
  function loadNavTypographyControls(){
    var t=typeFor('nav_menu');
    var f=menuModal.querySelector('#ceNavFont'), sz=menuModal.querySelector('#ceNavSize'), w=menuModal.querySelector('#ceNavWeight'), c=menuModal.querySelector('#ceNavColor');
    if(f) f.value=t.font||''; if(sz) sz.value=t.size||''; if(w) w.value=t.weight||'500'; if(c) c.value=/^#[0-9a-f]{6}$/i.test(t.color||'')?t.color:'#e5e5e5';
  }
  function readNavTypographyControls(){
    var t={};
    var f=menuModal.querySelector('#ceNavFont'), sz=menuModal.querySelector('#ceNavSize'), w=menuModal.querySelector('#ceNavWeight'), c=menuModal.querySelector('#ceNavColor');
    if(f && f.value) t.font=f.value; if(sz && sz.value) t.size=Math.max(8,Math.min(60,parseFloat(sz.value)||13)); if(w && w.value) t.weight=w.value; if(c && c.value) t.color=c.value;
    return t;
  }
  function applyNavTypographyPreview(){
    var t=readNavTypographyControls(); typographyState.nav_menu=t;
    var nav=document.getElementById('araMainNav'); if(!nav) return;
    nav.querySelectorAll('a').forEach(function(a){ a.style.fontFamily=t.font||''; a.style.fontSize=t.size?(t.size+'px'):''; a.style.fontWeight=t.weight||''; a.style.color=t.color||''; });
  }
  function openMenuModal(){
    var rows=menuModal.querySelector('#ceMenuRows'); rows.innerHTML='';
    var current=[]; try{ current=JSON.parse(document.body.dataset.ceNavMenu||'[]'); }catch(e){ current=[]; }
    if(!current.length) current=[{label:'Beranda',target_type:'section',section_id:0,url:'#top'}];
    current.forEach(function(it){
      var type=(it.target_type==='section' && it.section_id)?'section':'url';
      var sid=type==='section'?it.section_id:0;
      addMenuRow(it.label||'Menu',type,sid,it.url||'#');
    });
    loadNavTypographyControls();
    menuModal.classList.add('open');
  }
  function saveMenu(){
    var rows=menuModal.querySelectorAll('.ce-menu-row'), items=[], bad=[];
    rows.forEach(function(row){
      var label=row.querySelector('.ce-menu-label').value.trim(); if(!label) return;
      var type=row.querySelector('.ce-menu-type').value;
      if(type==='section'){
        var sid=parseInt(row.querySelector('.ce-menu-section').value||'0',10);
        if(!sid){ bad.push(label); return; }
        items.push({label:label,target_type:'section',section_id:sid});
      } else {
        var url=row.querySelector('.ce-menu-url').value.trim()||'#';
        items.push({label:label,target_type:'url',url:url});
      }
    });
    if(bad.length){ alert('Target section belum dipilih: '+bad.join(', ')); return; }
    typographyState.nav_menu=readNavTypographyControls();
    markSaving();
    return Promise.all([
      post('save_nav_menu',{items:items}),
      post('save_nav_typography',{typography:typographyState.nav_menu})
    ]).then(function(){
      document.body.dataset.ceNavMenu=JSON.stringify(items);
      document.body.dataset.ceTypography=JSON.stringify(typographyState);
      var nav=document.getElementById('araMainNav');
      if(nav){ nav.innerHTML=''; var navT=typeFor('nav_menu'); items.forEach(function(it){ var a=document.createElement('a'); a.href=it.target_type==='section'?'#'+(getEditorSections().find(function(x){return Number(x.id)===Number(it.section_id);})||{}).anchor_id:(it.url||'#'); a.textContent=it.label; a.style.fontFamily=navT.font||''; a.style.fontSize=navT.size?(navT.size+'px'):''; a.style.fontWeight=navT.weight||''; a.style.color=navT.color||''; nav.appendChild(a); }); }
      markSaved(); menuModal.classList.remove('open');
    }).catch(function(err){ markError(err.message); });
  }

  /* ---------------- slider manager ---------------- */
  function sliderSlidesState(){
    var a=[]; try{ a=JSON.parse(document.body.dataset.ceSliderSlides||'[]'); }catch(e){ a=[]; }
    if(!Array.isArray(a)||!a.length) a=[{image:'',alt:'Slide 1'},{image:'',alt:'Slide 2'},{image:'',alt:'Slide 3'}];
    return a;
  }
  function buildSliderModal(){
    sliderModal=document.createElement('div'); sliderModal.className='ce-modal';
    sliderModal.innerHTML='<div class="ce-modal-box ce-slider-modal-box"><div class="ce-modal-head"><h3>Slider Manager</h3><button type="button" class="ce-modal-close">✕</button></div>'+
      '<p class="ce-theme-hint">Atur gambar slider secara dinamis. Tambah, hapus, dan urutkan slide tanpa dibatasi 3 gambar.</p>'+
      '<div id="ceSliderRows" class="ce-slider-rows"></div>'+
      '<div class="ce-slider-options"><label>Autoplay <select id="ceSliderAutoplay"><option value="1">ON</option><option value="0">OFF</option></select></label><label>Durasi / slide (detik) <input id="ceSliderDuration" type="number" min="1" max="20" value="4"></label><label>Transition <select id="ceSliderTransition"><option value="fade">Fade</option><option value="slide">Slide</option></select></label><label>Dots <select id="ceSliderDots"><option value="1">ON</option><option value="0">OFF</option></select></label></div>'+
      '<div class="ce-modal-actions"><button type="button" class="ce-btn" id="ceSliderAdd">＋ Tambah Slide</button><button type="button" class="ce-btn ce-primary" id="ceSliderSave">Simpan Slider</button></div></div>';
    document.body.appendChild(sliderModal);
    sliderModal.querySelector('.ce-modal-close').onclick=function(){sliderModal.classList.remove('open');};
    sliderModal.addEventListener('click',function(e){if(e.target===sliderModal) sliderModal.classList.remove('open');});
    sliderModal.querySelector('#ceSliderAdd').onclick=function(){ addSliderRow({image:'',alt:'Slide '+(sliderModal.querySelectorAll('.ce-slider-row').length+1)}); };
    sliderModal.querySelector('#ceSliderSave').onclick=saveSlider;
  }
  function addSliderRow(slide){
    var rows=sliderModal.querySelector('#ceSliderRows'), idx=rows.children.length;
    var row=document.createElement('div'); row.className='ce-slider-row'; row.dataset.index=String(idx); row.dataset.image=String(slide.image||'');
    row.innerHTML='<div class="ce-slider-thumb">'+(slide.image?'<img src="../public/uploads/'+encodeURIComponent(slide.image)+'?v='+Date.now()+'">':'<span class="ce-slider-empty">No Image</span>')+'</div><div class="ce-slider-meta"><b>Slide '+(idx+1)+'</b><input class="ce-slider-alt" value="'+esc(slide.alt||('Slide '+(idx+1)))+'" placeholder="Alt text"><div><button type="button" class="ce-btn ce-slider-image">Pilih Gambar</button> <button type="button" class="ce-btn ce-slider-remove">Hapus</button>'+(slide.image?'<small class="ce-slider-image-status">✓ Gambar dipilih</small>':'')+'</div></div><span class="ce-drag" title="Seret untuk urutkan">⠿</span>';
    row.querySelector('.ce-slider-image').onclick=function(){
      mediaTarget={mode:'slider',index:Number(row.dataset.index)};
      mediaModal.classList.add('ce-media-picker');
      mediaModal.classList.add('open'); loadMedia();
    };
    row.querySelector('.ce-slider-remove').onclick=function(){ if(sliderModal.querySelectorAll('.ce-slider-row').length<=1){alert('Slider minimal punya 1 slide.');return;} row.remove(); renumberSliderRows(); };
    rows.appendChild(row);
  }
  function renumberSliderRows(){ sliderModal.querySelectorAll('.ce-slider-row').forEach(function(row,i){row.dataset.index=String(i);var b=row.querySelector('.ce-slider-meta b');if(b)b.textContent='Slide '+(i+1);}); }
  function openSliderModal(){
    var rows=sliderModal.querySelector('#ceSliderRows'); rows.innerHTML=''; sliderSlidesState().forEach(addSliderRow);
    var a=document.getElementById('ceSliderAutoplay'), d=document.getElementById('ceSliderDuration'), t=document.getElementById('ceSliderTransition'), dots=document.getElementById('ceSliderDots');
    if(a) a.value=document.body.dataset.ceSliderAutoplay==='0'?'0':'1';
    if(d) d.value=Math.max(1,Math.min(20,parseInt(document.body.dataset.ceSliderDuration||'4',10)||4));
    if(t) t.value=document.body.dataset.ceSliderTransition==='slide'?'slide':'fade';
    if(dots) dots.value=document.body.dataset.ceSliderDots==='0'?'0':'1';
    sliderModal.classList.add('open');
  }
  function saveSlider(){
    var slides=[]; sliderModal.querySelectorAll('.ce-slider-row').forEach(function(row,i){slides.push({image:row.dataset.image||'',alt:(row.querySelector('.ce-slider-alt').value||'Slide '+(i+1)).trim()});});
    if(!slides.length){alert('Slider minimal punya 1 slide.');return;}
    var autoplay=(sliderModal.querySelector('#ceSliderAutoplay')||{}).value||'1';
    var duration=parseInt((sliderModal.querySelector('#ceSliderDuration')||{}).value||'4',10); duration=Math.max(1,Math.min(20,isNaN(duration)?4:duration));
    var transition=(sliderModal.querySelector('#ceSliderTransition')||{}).value==='slide'?'slide':'fade';
    var dots=(sliderModal.querySelector('#ceSliderDots')||{}).value==='0'?'0':'1';
    markSaving(); post('save_slider',{slides:slides,autoplay:autoplay,duration:duration,transition:transition,dots:dots}).then(function(res){
      document.body.dataset.ceSliderSlides=JSON.stringify(slides); document.body.dataset.ceSliderAutoplay=autoplay; document.body.dataset.ceSliderDuration=String(duration); document.body.dataset.ceSliderTransition=transition; document.body.dataset.ceSliderDots=dots;
      markSaved(); sliderModal.classList.remove('open'); window.location.reload();
    }).catch(function(err){markError(err.message);});
  }

  /* ---------------- template gallery (WordPress-style, real live thumbnails + preview/apply flow) ---------------- */
  var templateModal;
  var THEME_PRESETS=[
    ['default-stacked','Default','Terang & netral, hero di atas','default','stacked'],
    ['default-split','Default Split','Terang & netral, hero berdampingan','default','split'],
    ['default-grid','Default Grid','Galeri foto 2x2 di hero','default','grid'],
    ['default-slider','Default Slider','Foto hero bergonta-ganti otomatis','default','slider'],
    ['minimal-split','Minimal Light','Putih bersih, aksen biru, sudut membulat','minimal','split'],
    ['minimal-overlay','Minimal Overlay','Biru lembut, gambar jadi latar penuh','minimal','overlay'],
    ['minimal-grid','Minimal Grid','Kartu galeri rounded ala portofolio','minimal','grid'],
    ['bold-overlay','Bold Dark','Gelap tegas, aksen oranye, tanpa sudut bulat','bold','overlay'],
    ['bold-split','Bold Split','Gelap, oranye, teks & gambar berdampingan','bold','split'],
    ['bold-slider','Bold Slider','Slider gambar dramatis mode gelap','bold','slider']
  ];
  function getCustomTemplates(){
    var a=[]; try{ a=JSON.parse(document.body.dataset.ceCustomTemplates||'[]'); }catch(e){ a=[]; }
    return Array.isArray(a)?a:[];
  }
  function applyCustomTemplate(t,mode,card){
    var label='desain + layout';
    var count=parseInt(t.block_count||((t.blocks||[]).length),10)||0;
    var msg='Terapkan '+(t.name||t.slug)+' sebagai '+label+'?';
    if(mode==='full') msg+=' Konten yang sudah ada TIDAK akan dihapus. Sistem hanya menyesuaikan layout/template.';
    if(!confirm(msg)) return;
    var buttons=card?card.querySelectorAll('button'):[]; buttons.forEach(function(b){b.disabled=true;});
    markSaving();
    post('apply_template',{slug:t.slug,mode:mode}).then(function(){
      markSaved(); window.location.href='canvas.php';
    }).catch(function(err){
      markError(err.message); buttons.forEach(function(b){b.disabled=false;}); alert(err.message);
    });
  }
  function renderCustomTemplates(grid, templates){
    var old=grid.querySelector('.ce-custom-template-section');
    if(old) old.remove();
    if(!templates.length) return;

    var activeCss=document.body.dataset.ceTemplateCss||'';
    var activeName=document.body.dataset.ceTemplateName||'';
    var wrap=document.createElement('div'); wrap.className='ce-custom-template-section';
    wrap.innerHTML='<div class="ce-template-section-title">Template Custom</div>'+
      '<div class="ce-theme-hint">Template mengatur <b>desain + layout</b>. Konten website Anda tetap aman. Jika ingin menambah demo content, gunakan Import Starter Content.</div>'+
      '<div class="ce-theme-grid ce-custom-template-grid"></div>';
    var cards=wrap.querySelector('.ce-custom-template-grid');

    templates.forEach(function(t){
      var isActive=(activeCss && t.css===activeCss) || (activeName && t.name===activeName);
      var count=parseInt(t.block_count||((t.blocks||[]).length),10)||0;
      var preview=t.preview?('../public/assets/images/templates/'+encodeURIComponent(t.preview)):('../public/assets/images/templates/'+encodeURIComponent(t.slug+'.svg'));
      var card=document.createElement('div'); card.className='ce-theme-card ce-custom-theme-card'+(isActive?' active':'');
      card.innerHTML='<div class="ce-theme-preview">'+
        '<img src="'+preview+'" alt="'+esc(t.name||'Template')+'" class="ce-custom-thumb" onerror="this.onerror=null;this.src=\'../public/assets/images/templates/default-template.svg\';">'+
        (isActive?'<div class="ce-theme-hover ce-theme-hover-static"><span class="ce-theme-active-badge">Sedang Aktif</span></div>':'')+
        '</div><div class="ce-theme-meta"><b>'+esc(t.name||t.slug)+'</b>'+
        (isActive?'<span class="ce-theme-active-badge">Aktif</span>':'')+
        '<small>'+esc(t.description||'Template custom')+'</small>'+
        '<small class="ce-template-block-count">'+(count?count+' block siap diimpor':'Skin saja — tanpa struktur block')+'</small>'+
        '<div class="ce-template-actions">'+
        '<button type="button" class="ce-btn ce-primary ce-template-design">Gunakan Template</button>'+
        (count?'<button type="button" class="ce-btn ce-template-import">＋ Import Demo</button>':'')+
        '<button type="button" class="ce-btn ce-danger-outline ce-template-delete">🗑 Hapus</button>'+
        '</div></div>';
      card.querySelector('.ce-template-design').onclick=function(e){e.stopPropagation();applyCustomTemplate(t,'design',card);};
      var imp=card.querySelector('.ce-template-import');
      if(imp) imp.onclick=function(e){e.stopPropagation(); if(!confirm('Import starter content dari template ini ke BAWAH content yang ada? Content sekarang tidak akan dihapus.')) return; imp.disabled=true; markSaving(); post('import_template_content',{slug:t.slug}).then(function(j){ markSaved(); alert((j.count||0)+' starter block ditambahkan.'); window.location.href='canvas.php'; }).catch(function(err){ markError(err.message); imp.disabled=false; alert(err.message); }); };
      card.querySelector('.ce-template-delete').onclick=function(e){
        e.stopPropagation();
        var msg='Hapus template \"'+(t.name||t.slug)+'\" secara permanen? File CSS, preview, dan asset template juga akan dihapus.';
        if(isActive) msg+='\n\nTemplate ini sedang aktif. Jika dihapus, tampilan akan kembali ke Default. Konten/block tidak akan dihapus.';
        if(!confirm(msg)) return;
        var btn=e.currentTarget; btn.disabled=true; btn.textContent='Menghapus…'; markSaving();
        post('delete_template',{slug:t.slug}).then(function(j){
          document.body.dataset.ceCustomTemplates=JSON.stringify(j.templates||[]);
          markSaved();
          if(j.was_active){ window.location.href='canvas.php'; return; }
          renderCustomTemplates(grid,j.templates||[]);
          alert('Template berhasil dihapus.');
        }).catch(function(err){ markError(err.message); btn.disabled=false; btn.textContent='🗑 Hapus'; alert(err.message); });
      };
      cards.appendChild(card);
    });
    grid.appendChild(wrap);
  }
  function buildTemplateUploadUI(grid){
    var bar=document.createElement('div'); bar.className='ce-template-upload';
    bar.innerHTML='<div><b>Upload Template</b><small>ZIP maksimal 5 MB. Struktur sederhana, tanpa framework.</small></div><label class="ce-btn" for="ceTemplateFile">＋ Upload ZIP</label><input id="ceTemplateFile" type="file" accept=".zip,application/zip" hidden>';
    grid.parentNode.insertBefore(bar,grid);
    bar.querySelector('#ceTemplateFile').addEventListener('change',function(){
      var file=this.files&&this.files[0]; if(!file) return;
      var fd=new FormData(); fd.append('template',file); fd.append('csrf',CSRF);
      markSaving();
      fetch('template_upload.php',{method:'POST',body:fd}).then(async function(r){
        var raw=await r.text();
        var j=null;
        try{ j=JSON.parse(raw); }catch(parseErr){
          var clean=raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
          throw new Error('Server tidak mengembalikan JSON. '+(clean||('HTTP '+r.status)).slice(0,500));
        }
        if(!r.ok || !j.ok) throw new Error(j.error||('Upload gagal (HTTP '+r.status+')'));
        return j;
      }).then(function(j){
        document.body.dataset.ceCustomTemplates=JSON.stringify(j.templates||[]);
        markSaved(); renderCustomTemplates(grid,j.templates||[]);
        alert('Template berhasil ditambahkan.');
      }).catch(function(err){ markError(err.message); alert(err.message); });
      this.value='';
    });
  }

  function buildTemplateModal(){
    templateModal=document.createElement('div'); templateModal.className='ce-modal ce-modal-wide';
    templateModal.innerHTML='<div class="ce-modal-box"><div class="ce-modal-head"><h3>Pilih Template</h3><div style="display:flex;align-items:center;gap:8px"><button type="button" class="ce-btn ce-danger-outline" id="ceTemplateRestore" style="display:none">↶ Pulihkan Layout Sebelumnya</button><button type="button" class="ce-btn ce-danger-outline" id="ceTemplateReset" style="display:none">↺ Kembali ke Default</button><button type="button" class="ce-modal-close">✕</button></div></div>'+
      '<p class="ce-theme-hint">Template hanya mengubah presentasi/layout. <b>Konten Anda tidak dihapus saat ganti template.</b> Starter/demo content tersedia sebagai aksi terpisah.</p>'+
      '<div class="ce-field" style="display:flex;align-items:center;gap:10px;background:#0b1120;border:1px solid #263252;border-radius:10px;padding:10px 12px;margin-bottom:14px">'+
        '<input type="color" id="ceAccentColor" style="width:34px;height:34px;padding:2px;border:0;border-radius:6px;background:#1c2542;cursor:pointer" value="'+esc(document.body.dataset.ceAccentColor||'#7b5cff')+'">'+
        '<div style="flex:1"><b style="font-size:12.5px;color:#eef2fb">Warna Aksen Template</b><br><small style="color:#8390ab">Mengubah warna tombol, link aktif, dan aksen di template yang sedang dipakai — tanpa mengganti seluruh template.</small></div>'+
        '<button type="button" class="ce-btn" id="ceAccentReset">Reset</button>'+
      '</div>'+
      '<div class="ce-theme-grid" id="ceThemeGrid"></div></div>';
    document.body.appendChild(templateModal);
    templateModal.querySelector('.ce-modal-close').onclick=function(){ templateModal.classList.remove('open'); };
    templateModal.addEventListener('click',function(e){ if(e.target===templateModal) templateModal.classList.remove('open'); });

    var accentInput=templateModal.querySelector('#ceAccentColor');
    var accentTimer=null;
    function applyAccentLive(v){ document.documentElement.style.setProperty('--ara-accent',v); }
    if(document.body.dataset.ceAccentColor) applyAccentLive(document.body.dataset.ceAccentColor);
    accentInput.addEventListener('input',function(){
      applyAccentLive(accentInput.value);
      clearTimeout(accentTimer);
      accentTimer=setTimeout(function(){
        markSaving();
        saveKV('accent_color',accentInput.value).then(function(){ markSaved(); document.body.dataset.ceAccentColor=accentInput.value; }).catch(function(err){ markError(err.message); });
      },400);
    });
    templateModal.querySelector('#ceAccentReset').onclick=function(){
      accentInput.value='#7b5cff';
      document.documentElement.style.removeProperty('--ara-accent');
      markSaving();
      saveKV('accent_color','').then(function(){ markSaved(); document.body.dataset.ceAccentColor=''; }).catch(function(err){ markError(err.message); });
    };

    var grid=templateModal.querySelector('#ceThemeGrid');
    buildTemplateUploadUI(grid);
    var currentTheme=document.body.dataset.ceSiteTheme||'default';
    var currentLayout=document.body.dataset.ceHeroLayout||'stacked';
    var previewing=document.body.dataset.cePreviewing==='1';
    var currentKey=(previewing?document.body.dataset.cePreviewTheme+'-'+document.body.dataset.cePreviewLayout:currentTheme+'-'+currentLayout);
    // A custom uploaded template is the active template source. Built-in
    // theme cards must not also show as active when custom CSS/name is set.
    // Built-in cards can still show as 'Pratinjau' while a custom template
    // is temporarily being previewed.
    var activeCustomCss=document.body.dataset.ceTemplateCss||'';
    var activeCustomName=document.body.dataset.ceTemplateName||'';
    var customTemplateActive=!previewing && !!(activeCustomCss || activeCustomName);
    var hiddenTemplates=[];
    try{ hiddenTemplates=JSON.parse(document.body.dataset.ceHiddenTemplates||'[]'); }catch(e){ hiddenTemplates=[]; }

    var restoreBtn=templateModal.querySelector('#ceTemplateRestore');
    if(parseInt(document.body.dataset.ceLastTemplateRevision||'0',10)>0){
      restoreBtn.style.display='inline-flex';
      restoreBtn.onclick=function(){
        if(!confirm('Pulihkan layout/template sebelum perubahan terakhir? Konten tidak akan dihapus.')) return;
        restoreBtn.disabled=true; restoreBtn.textContent='Memulihkan…'; markSaving();
        post('restore_template_backup',{}).then(function(){markSaved();window.location.href='canvas.php';}).catch(function(err){markError(err.message);restoreBtn.disabled=false;restoreBtn.textContent='↶ Pulihkan Layout Sebelumnya';alert(err.message);});
      };
    }

    var resetBtn=templateModal.querySelector('#ceTemplateReset');
    var hasAccent=!!(document.body.dataset.ceAccentColor);
    var hasCustomTemplate=!!(document.body.dataset.ceTemplateCss);
    if(currentTheme!=='default'||currentLayout!=='stacked'||hasAccent||hasCustomTemplate){
      resetBtn.style.display='inline-flex';
      resetBtn.onclick=function(){
        if(!confirm('Hapus template yang sedang dipakai (termasuk warna aksen custom) dan kembali ke tampilan Default bawaan? Konten tulisan/gambar Anda tidak akan hilang, hanya tampilan/skin-nya yang direset.')) return;
        resetBtn.disabled=true; resetBtn.textContent='Menghapus…';
        markSaving();
        post('reset_template',{})
          .then(function(){ markSaved(); window.location.href='canvas.php'; })
          .catch(function(err){ markError(err.message); resetBtn.disabled=false; resetBtn.textContent='↺ Hapus Template (Kembali ke Default)'; });
      };
    }

    function saveHidden(){
      return post('update_setting',{key:'hidden_templates',value:JSON.stringify(hiddenTemplates)});
    }

    function renderGrid(){
      grid.innerHTML='';
      var visible=THEME_PRESETS.filter(function(t){ return hiddenTemplates.indexOf(t[0])===-1; });
      visible.forEach(function(t){
        var key=t[0],name=t[1],desc=t[2],theme=t[3],layout=t[4];
        var isActive=!customTemplateActive && (key===currentKey);
        var card=document.createElement('div'); card.className='ce-theme-card'+(isActive?' active':'');
        var thumbSrc='theme-thumb.php?theme='+encodeURIComponent(theme)+'&layout='+encodeURIComponent(layout);
        card.innerHTML=(isActive?'':'<button type="button" class="ce-theme-remove" title="Hapus template ini dari daftar">✕</button>')+
          '<div class="ce-theme-preview">'+
            '<iframe class="ce-theme-frame" src="'+thumbSrc+'" tabindex="-1" loading="lazy"></iframe>'+
            (isActive?'<div class="ce-theme-hover ce-theme-hover-static"><span class="ce-theme-active-badge">'+(previewing?'Sedang Dipratinjau':'Sedang Aktif')+'</span></div>':'<div class="ce-theme-hover"><button type="button" class="ce-btn ce-primary">Pratinjau Template</button></div>')+
          '</div>'+
          '<div class="ce-theme-meta"><b>'+esc(name)+'</b>'+(isActive?'<span class="ce-theme-active-badge">'+(previewing?'Pratinjau':'Aktif')+'</span>':'')+'<small>'+esc(desc)+'</small></div>';
        if(!isActive){
          card.onclick=function(){
            templateModal.classList.remove('open');
            window.location.href='canvas.php?preview_theme='+encodeURIComponent(theme)+'&preview_layout='+encodeURIComponent(layout);
          };
          card.querySelector('.ce-theme-remove').onclick=function(e){
            e.stopPropagation();
            if(!confirm('Hapus "'+name+'" dari daftar template? Template ini tidak akan muncul lagi, tapi bisa dipulihkan lewat tautan di bawah daftar.')) return;
            hiddenTemplates.push(key);
            markSaving();
            saveHidden().then(function(){ markSaved(); document.body.dataset.ceHiddenTemplates=JSON.stringify(hiddenTemplates); renderGrid(); })
              .catch(function(err){ markError(err.message); hiddenTemplates.pop(); });
          };
        }
        grid.appendChild(card);
      });

      // Custom uploaded templates live in the same standard-width grid,
      // but must be rendered AFTER grid.innerHTML='' so they are not wiped.
      renderCustomTemplates(grid,getCustomTemplates());

      var oldNote=templateModal.querySelector('.ce-hidden-templates-note');
      if(oldNote) oldNote.remove();
      if(hiddenTemplates.length){
        var note=document.createElement('p'); note.className='ce-hidden-templates-note';
        note.innerHTML=hiddenTemplates.length+' template disembunyikan. <button type="button">Tampilkan lagi semua</button>';
        note.querySelector('button').onclick=function(){
          hiddenTemplates=[];
          markSaving();
          saveHidden().then(function(){ markSaved(); document.body.dataset.ceHiddenTemplates='[]'; renderGrid(); })
            .catch(function(err){ markError(err.message); });
        };
        templateModal.querySelector('.ce-modal-box').appendChild(note);
      }
    }
    renderGrid();
  }

  /* ---------------- live preview confirmation bar (shown after clicking a template) ---------------- */
  function buildPreviewBar(){
    if(document.body.dataset.cePreviewing!=='1') return;
    var themeNames={default:'Default',minimal:'Minimal Light',bold:'Bold Dark'};
    var layoutNames={stacked:'Bertumpuk',split:'Berdampingan',overlay:'Gambar Latar'};
    var theme=document.body.dataset.cePreviewTheme||'default';
    var layout=document.body.dataset.cePreviewLayout||'stacked';
    var label=(themeNames[theme]||theme)+' · '+(layoutNames[layout]||layout);
    var bar=document.createElement('div'); bar.className='ce-preview-bar';
    bar.innerHTML='<span class="ce-preview-bar-icon">👁</span>'+
      '<span class="ce-preview-bar-text">Pratinjau template: <b>'+esc(label)+'</b> — perubahan ini <u>belum tersimpan</u>.</span>'+
      '<span class="ce-preview-bar-actions">'+
        '<button type="button" class="ce-btn" id="cePreviewCancel">Batal</button>'+
        '<button type="button" class="ce-btn ce-primary" id="cePreviewApply">✓ Terapkan Template</button>'+
      '</span>';
    document.body.appendChild(bar);
    document.body.classList.add('ce-has-preview-bar');
    bar.querySelector('#cePreviewCancel').onclick=function(){
      window.location.href='canvas.php';
    };
    bar.querySelector('#cePreviewApply').onclick=function(){
      var btn=bar.querySelector('#cePreviewApply'); btn.disabled=true; btn.textContent='Menerapkan…';
      markSaving();
      post('apply_builtin_template',{theme:theme,layout:layout})
        .then(function(){ markSaved(); window.location.href='canvas.php'; })
        .catch(function(err){ markError(err.message); btn.disabled=false; btn.textContent='✓ Terapkan Template'; });
    };
  }

  function bindAll(scope){
    setupEditableText(scope); setupHeaderBrandText(); setupHrefEdit(scope); setupImages(scope); setupLogoControls(); setupLogoResize(); setupGlobalAreas(); setupBlocks(scope);
  }

  function init(){
    loadTypography();
    buildTopbar();
    buildHrefPopover();
    buildMediaModal();
    buildSliderModal();
    buildLibraryModal();
    buildSettingsModal();
    buildMenuModal();
    bindMenuDrag();
    buildTemplateModal();
    buildPreviewBar();
    bindAll(document);
    try{ buildHeaderModal(); }catch(err){ console.error('Ara Header UI:',err); }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
