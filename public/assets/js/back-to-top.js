(function(){
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }
  ready(function(){
    var btn=document.getElementById('araBackToTop');
    if(!btn) return;
    var threshold=300, ticking=false;
    function check(){
      ticking=false;
      var y=window.pageYOffset||document.documentElement.scrollTop||0;
      btn.classList.toggle('is-visible',y>threshold);
    }
    function onScroll(){ if(ticking) return; ticking=true; requestAnimationFrame(check); }
    window.addEventListener('scroll',onScroll,{passive:true});
    window.addEventListener('resize',check,{passive:true});
    check();
    btn.addEventListener('click',function(){ window.scrollTo({top:0,behavior:'smooth'}); });
  });
})();
