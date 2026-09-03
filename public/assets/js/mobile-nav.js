/* Ara CMS V20.5 — lightweight responsive mobile navigation */
(function(){
  'use strict';
  function init(){
    var toggle=document.querySelector('.ara-menu-toggle');
    var nav=document.getElementById('araMainNav');
    if(!toggle || !nav || toggle.dataset.araBound) return;
    toggle.dataset.araBound='1';
    function close(){
      toggle.setAttribute('aria-expanded','false');
      toggle.setAttribute('aria-label','Buka menu');
      nav.classList.remove('ara-nav-open');
    }
    toggle.addEventListener('click',function(e){
      e.preventDefault();
      e.stopPropagation();
      var open=toggle.getAttribute('aria-expanded')==='true';
      if(open) close();
      else {
        toggle.setAttribute('aria-expanded','true');
        toggle.setAttribute('aria-label','Tutup menu');
        nav.classList.add('ara-nav-open');
      }
    });
    nav.addEventListener('click',function(e){
      if(e.target.closest('a')) close();
    });
    document.addEventListener('click',function(e){
      if(!nav.contains(e.target) && !toggle.contains(e.target)) close();
    });
    window.addEventListener('resize',function(){
      if(window.innerWidth>700) close();
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init);
  else init();
})();
