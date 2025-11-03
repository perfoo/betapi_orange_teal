
(function(){
  // Smooth scroll for on-page anchors
  document.addEventListener('click', function(e){
    var a = e.target.closest('a[href^="#"]');
    if(!a) return;
    var id = a.getAttribute('href').slice(1);
    var el = document.getElementById(id);
    if(el){
      e.preventDefault();
      el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  });

  // Close mobile nav after clicking a link
  var nav = document.getElementById('navbarSupportedContent');
  if(nav){
    nav.addEventListener('click', function(e){
      if(e.target.matches('.nav-link')){
        var btn = document.querySelector('.navbar-toggler[aria-expanded="true"]');
        if(btn) btn.click();
      }
    });
  }
})();
