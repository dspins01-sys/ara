(function(){
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }
  ready(function(){
    var footer=document.querySelector('.ara-footer');
    var btn=document.getElementById('araBackToTop');
    if(!footer || !btn) return;

    if('IntersectionObserver' in window){
      var obs=new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          btn.classList.toggle('is-visible',entry.isIntersecting);
        });
      },{threshold:0.15});
      obs.observe(footer);
    } else {
      /* Fallback for very old browsers without IntersectionObserver support. */
      var check=function(){
        var r=footer.getBoundingClientRect();
        btn.classList.toggle('is-visible', r.top < (window.innerHeight||document.documentElement.clientHeight));
      };
      window.addEventListener('scroll',check,{passive:true});
      check();
    }

    btn.addEventListener('click',function(){
      var top=document.getElementById('top');
      if(top && top.scrollIntoView) top.scrollIntoView({behavior:'smooth',block:'start'});
      else window.scrollTo({top:0,behavior:'smooth'});
    });
  });
})();
