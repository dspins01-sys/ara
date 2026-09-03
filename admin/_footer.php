</main><script>
(function(){
  var btn=document.getElementById('adminMobileMenu'), side=document.getElementById('adminSidebar'), veil=document.getElementById('adminMobileOverlay');
  var collapseBtn=document.getElementById('adminSidebarCollapse');
  if(!side) return;

  /* Desktop sidebar collapse state. Mobile always uses the drawer and ignores this state. */
  var storageKey='ara_admin_sidebar_collapsed';
  function setCollapsed(collapsed, persist){
    if(window.innerWidth <= 700) return;
    document.body.classList.toggle('admin-sidebar-collapsed', !!collapsed);
    if(collapseBtn){
      collapseBtn.setAttribute('aria-label', collapsed ? 'Buka sidebar' : 'Ciutkan sidebar');
      collapseBtn.title=collapsed ? 'Buka sidebar' : 'Ciutkan sidebar';
      collapseBtn.innerHTML='<span>'+ (collapsed ? '›' : '‹') +'</span>';
    }
    if(persist){ try{ localStorage.setItem(storageKey, collapsed ? '1' : '0'); }catch(e){} }
  }
  var saved=false;
  try{ saved=localStorage.getItem(storageKey)==='1'; }catch(e){}
  setCollapsed(saved,false);
  if(collapseBtn) collapseBtn.addEventListener('click',function(){ setCollapsed(!document.body.classList.contains('admin-sidebar-collapsed'),true); });

  function close(){ if(!btn||!veil) return; side.classList.remove('is-open'); veil.classList.remove('is-visible'); btn.setAttribute('aria-expanded','false'); document.body.classList.remove('admin-menu-open'); }
  function toggle(){ if(!btn||!veil) return; var open=!side.classList.contains('is-open'); side.classList.toggle('is-open',open); veil.classList.toggle('is-visible',open); btn.setAttribute('aria-expanded',open?'true':'false'); document.body.classList.toggle('admin-menu-open',open); }
  if(btn&&veil){
    btn.addEventListener('click',toggle); veil.addEventListener('click',close);
    side.querySelectorAll('a').forEach(function(a){ a.addEventListener('click',close); });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape') close(); });
  }
  window.addEventListener('resize',function(){
    if(window.innerWidth <= 700){
      document.body.classList.remove('admin-sidebar-collapsed');
    }else{
      var c=false; try{ c=localStorage.getItem(storageKey)==='1'; }catch(e){}
      setCollapsed(c,false);
      close();
    }
  });
})();
</script>
</body></html>
