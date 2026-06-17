
(function(){
  const body=document.body;
  const hamburger=document.getElementById('hamburger');
  const mobileNav=document.getElementById('mobileNav');
  const overlay=document.querySelector('.nav-overlay');
  const openState=()=>body.classList.contains('menu-open');
  window.toggleMenu=function(){
    const shouldOpen=!openState();
    body.classList.toggle('menu-open',shouldOpen);
    hamburger?.classList.toggle('active',shouldOpen);
    mobileNav?.classList.toggle('active',shouldOpen);
    overlay?.classList.toggle('active',shouldOpen);
    hamburger?.setAttribute('aria-expanded',String(shouldOpen));
  };
  window.closeMenu=function(){
    body.classList.remove('menu-open');
    hamburger?.classList.remove('active');
    mobileNav?.classList.remove('active');
    overlay?.classList.remove('active');
    hamburger?.setAttribute('aria-expanded','false');
  };
  hamburger?.addEventListener('click',window.toggleMenu);
  overlay?.addEventListener('click',window.closeMenu);
  document.querySelectorAll('.mobile-nav a,.mobile-nav button').forEach(el=>el.addEventListener('click',window.closeMenu));
  window.scrollToBooking=function(){
    window.closeMenu();
    const target=document.querySelector('#booking, #contact, .appointment-section, .booking-section');
    if(target) target.scrollIntoView({behavior:'smooth',block:'start'});
    else window.location.href='index.html#booking';
  };
  const revealObserver=new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  },{threshold:.14,rootMargin:'0px 0px -60px 0px'});
  document.querySelectorAll('.reveal,.glass-card,.image-split,.team-grid,.blog-grid,.location-wrap,.contact-grid').forEach((el,i)=>{
    el.classList.add('reveal');
    el.style.transitionDelay=`${Math.min(i*55,260)}ms`;
    revealObserver.observe(el);
  });
  document.querySelectorAll('.blog-card,.stylist-card,.glass-card').forEach(card=>{
    card.addEventListener('mousemove',e=>{
      const r=card.getBoundingClientRect();
      card.style.setProperty('--mx',`${e.clientX-r.left}px`);
      card.style.setProperty('--my',`${e.clientY-r.top}px`);
    });
  });
})();
