/* SISVET - Página "Ajuda": menu móvel e pesquisa nas perguntas frequentes.
   O accordion usa <details>/<summary> nativo e não depende de JavaScript. */
(function () {
  /* ---------- Menu móvel (mesmo comportamento de como-funciona.js) ---------- */
  var botao = document.querySelector('.menu-toggle');
  var menu = document.getElementById('menu-principal');

  function alternarMenu(abrir) {
    menu.classList.toggle('aberto', abrir);
    botao.setAttribute('aria-expanded', String(abrir));
    botao.setAttribute('aria-label', abrir ? 'Fechar menu' : 'Abrir menu');
    botao.querySelector('i').className = abrir ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
  }

  if (botao && menu) {
    botao.addEventListener('click', function () { alternarMenu(!menu.classList.contains('aberto')); });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) alternarMenu(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('aberto')) { alternarMenu(false); botao.focus(); }
    });
    window.matchMedia('(min-width: 1081px)').addEventListener('change', function (e) {
      if (e.matches) alternarMenu(false);
    });
  }

  /* ---------- Pesquisa ---------- */
  var campo = document.getElementById('ajuda-campo');
  var limpar = document.getElementById('ajuda-limpar');
  var contagem = document.getElementById('ajuda-contagem');
  var verResultados = document.getElementById('ajuda-ver');
  var vazio = document.getElementById('ajuda-vazio');
  var itens = Array.prototype.slice.call(document.querySelectorAll('.ajuda-item'));
  var grupos = Array.prototype.slice.call(document.querySelectorAll('.ajuda-grupo'));
  if (!campo) return;

  // Minúsculas e sem acentos: "Autorização" encontra "autorizacao" e vice-versa.
  function normalizar(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }

  // Texto pesquisável = pergunta + resposta de cada item (calculado uma vez).
  var textos = itens.map(function (item) { return normalizar(item.textContent); });

  function filtrar() {
    var termos = normalizar(campo.value).split(/\s+/).filter(Boolean);
    var total = 0;

    itens.forEach(function (item, i) {
      var corresponde = termos.every(function (t) { return textos[i].indexOf(t) !== -1; });
      item.hidden = !corresponde;
      if (corresponde) total++;
    });

    grupos.forEach(function (grupo) {
      grupo.hidden = !grupo.querySelector('.ajuda-item:not([hidden])');
    });

    limpar.hidden = campo.value === '';
    vazio.hidden = !(termos.length && total === 0);
    verResultados.hidden = !termos.length;
    contagem.textContent = !termos.length ? '' :
      total === 0 ? 'Nenhum resultado encontrado.' :
      total + (total === 1 ? ' pergunta encontrada.' : ' perguntas encontradas.');
  }

  function limparBusca(devolverFoco) {
    campo.value = '';
    filtrar();
    if (devolverFoco) campo.focus();
  }

  campo.addEventListener('input', filtrar);
  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && campo.value) { e.preventDefault(); limparBusca(false); }
  });
  limpar.addEventListener('click', function () { limparBusca(true); });

  // Sugestões rápidas
  document.querySelectorAll('[data-busca]').forEach(function (b) {
    b.addEventListener('click', function () {
      campo.value = b.getAttribute('data-busca');
      filtrar();
      campo.focus();
    });
  });

  // Ícone de lupa do cabeçalho: leva ao campo de pesquisa
  document.querySelectorAll('[data-ir-busca]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (menu) alternarMenu(false);
      campo.scrollIntoView({ block: 'center' });
      campo.focus();
    });
  });

  // Ao escolher uma categoria, a pesquisa é limpa para o grupo não ficar oculto
  document.addEventListener('click', function (e) {
    if (campo.value && e.target.closest('.ajuda-categorias a, #ajuda-ver-categorias')) limparBusca(false);
  });

  filtrar();
})();
