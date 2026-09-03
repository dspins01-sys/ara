(function(){
  'use strict';
  function init(root){
    if(!root || root.dataset.sliderReady==='1') return;
    var slides=Array.prototype.slice.call(root.querySelectorAll('.hero-slide'));
    if(!slides.length) return;
    root.dataset.sliderReady='1';
    var count=slides.length;
    var autoplay=root.dataset.sliderAutoplay!=='0';
    var duration=Math.max(1,Math.min(20,parseFloat(root.dataset.sliderDuration||'4')||4));
    var transition=root.dataset.sliderTransition==='slide'?'slide':'fade';
    var dotsOn=root.dataset.sliderDots!=='0';
    var index=0, timer=null;

    root.classList.add('hero-slider-'+transition);
    function show(i,instant){
      index=(i+count)%count;
      slides.forEach(function(slide,n){
        slide.classList.toggle('is-active',n===index);
        slide.setAttribute('aria-hidden',n===index?'false':'true');
        if(instant) slide.style.transition='none';
      });
      var dots=root.querySelectorAll('.hero-slider-dot');
      dots.forEach(function(dot,n){dot.classList.toggle('is-active',n===index);dot.setAttribute('aria-current',n===index?'true':'false');});
      if(instant){
        requestAnimationFrame(function(){slides.forEach(function(slide){slide.style.transition='';});});
      }
    }
    function next(){show(index+1,false);}
    function stop(){if(timer){clearInterval(timer);timer=null;}}
    function start(){stop();if(autoplay && count>1) timer=setInterval(next,duration*1000);}

    if(dotsOn && count>1){
      var nav=document.createElement('div'); nav.className='hero-slider-dots'; nav.setAttribute('aria-label','Navigasi slider');
      slides.forEach(function(_,n){
        var b=document.createElement('button'); b.type='button'; b.className='hero-slider-dot'; b.setAttribute('aria-label','Slide '+(n+1));
        b.onclick=function(){show(n,false);start();}; nav.appendChild(b);
      });
      root.appendChild(nav);
    }
    show(0,true); start();
    root.addEventListener('mouseenter',stop);
    root.addEventListener('mouseleave',start);
    root.addEventListener('focusin',stop);
    root.addEventListener('focusout',function(e){if(!root.contains(e.relatedTarget)) start();});
  }
  function boot(){document.querySelectorAll('.hero-slider').forEach(init);}
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot); else boot();
})();
