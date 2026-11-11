document.addEventListener("DOMContentLoaded", function () {

    // ========================================
    // SELECIONAR O IFRAME E OS LINKS DO MENU
    // ========================================

    // No seu HTML o iframe tem o nome 'conteudo'
    const iframe = document.querySelector('iframe[name="conteudo"]');

    // Seleciona todos os links do menu lateral que apontam para o iframe
    const links = document.querySelectorAll('.menu-lateral .menu a[target="conteudo"]');

    // ========================================
    // DESTACAR A OPÇÃO SELECIONADA
    // ========================================

    function atualizarMenu(linkSelecionado) {
        // Remove a classe 'ativo' de todos os itens do menu
        document.querySelectorAll('.menu-lateral .menu-item').forEach(function (link) {
            link.classList.remove("ativo");
        });

        // Adiciona a classe 'ativo' ao link clicado
        if (linkSelecionado) {
            linkSelecionado.classList.add("ativo");
        }
    }

    // ========================================
    // ABRIR AS PÁGINAS DENTRO DO IFRAME
    // ========================================

    links.forEach(function (link) {
        link.addEventListener("click", function (evento) {
            const destino = link.getAttribute("href");

            if (!destino || !iframe) {
                return;
            }

            // Impede o comportamento padrão do link de recarregar a página inteira
            evento.preventDefault();

            // Altera a página carregada dentro do iframe
            iframe.src = destino;

            // Atualiza o destaque visual no menu lateral
            atualizarMenu(link);
        });
    });

    // ========================================
    // DESTACAR A PÁGINA INICIAL AO CARREGAR
    // ========================================

    const linkInicial = document.querySelector('.menu-lateral .menu a[target="conteudo"].ativo');
    if (linkInicial) {
        atualizarMenu(linkInicial);
    }

});