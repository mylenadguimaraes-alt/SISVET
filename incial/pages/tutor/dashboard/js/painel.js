
document.addEventListener("DOMContentLoaded", function () {

    // ========================================
    // SELECIONAR O IFRAME E OS LINKS DO MENU
    // ========================================

    const iframe = document.getElementById("conteudo-frame");

    const links = document.querySelectorAll(
        '.menu-lateral nav a[target="conteudo-frame"], ' +
        '.card-rodape a[target="conteudo-frame"]'
    );

    // ========================================
    // DESTACAR A PÁGINA SELECIONADA
    // ========================================

    function atualizarMenu(linkSelecionado) {

        document.querySelectorAll(".menu-lateral nav a").forEach(function (link) {
            link.classList.remove("ativo");
        });

        if (
            linkSelecionado &&
            linkSelecionado.closest(".menu-lateral nav")
        ) {
            linkSelecionado.classList.add("ativo");
        }
    }

    // ========================================
    // CARREGAR A PÁGINA NO IFRAME
    // ========================================

    links.forEach(function (link) {

        link.addEventListener("click", function (evento) {

            const destino = link.getAttribute("href");

            if (!destino || !iframe) {
                return;
            }

            // Impede a navegação da página principal.
            evento.preventDefault();

            // Abre o destino dentro do iframe.
            iframe.src = destino;

            // Atualiza o item selecionado no menu.
            atualizarMenu(link);

        });

    });

    // ========================================
    // DESTACAR A PÁGINA INICIAL
    // ========================================

    const linkInicial = document.querySelector(
        '.menu-lateral nav a[target="conteudo-frame"][href="pages/iframe/index.html"]'
    );

    atualizarMenu(linkInicial);

});
