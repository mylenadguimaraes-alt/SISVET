/* SISVET - Página "Como funciona": menu recolhível em telas pequenas.
   O FAQ usa <details>/<summary> nativo e não depende de JavaScript. */
(function () {
  var botao = document.querySelector('.menu-toggle');
  var menu = document.getElementById('menu-principal');
  if (!botao || !menu) return;

  function alternar(abrir) {
    menu.classList.toggle('aberto', abrir);
    botao.setAttribute('aria-expanded', String(abrir));
    botao.setAttribute('aria-label', abrir ? 'Fechar menu' : 'Abrir menu');
    botao.querySelector('i').className = abrir ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
  }

  botao.addEventListener('click', function () {
    alternar(!menu.classList.contains('aberto'));
  });

  menu.addEventListener('click', function (e) {
    if (e.target.closest('a')) alternar(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('aberto')) {
      alternar(false);
      botao.focus();
    }
  });

  window.matchMedia('(min-width: 1081px)').addEventListener('change', function (e) {
    if (e.matches) alternar(false);
  });
})();
