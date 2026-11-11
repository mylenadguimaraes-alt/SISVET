
document.addEventListener("DOMContentLoaded", function () {
    const menus = document.querySelectorAll(".menu-dropdown");

    menus.forEach(function (menu) {
        const botao = menu.querySelector(".menu-botao");

        if (!botao) return;

        botao.addEventListener("click", function (evento) {
            evento.preventDefault();
            evento.stopPropagation();

            const abrir = !menu.classList.contains("aberto");

            menus.forEach(function (outroMenu) {
                outroMenu.classList.remove("aberto");

                const outroBotao =
                    outroMenu.querySelector(".menu-botao");

                if (outroBotao) {
                    outroBotao.setAttribute("aria-expanded", "false");
                }
            });

            if (abrir) {
                menu.classList.add("aberto");
                botao.setAttribute("aria-expanded", "true");
            }
        });
    });

    document.addEventListener("click", function (evento) {
        if (evento.target.closest(".menu-dropdown")) return;

        menus.forEach(function (menu) {
            menu.classList.remove("aberto");

            const botao = menu.querySelector(".menu-botao");

            if (botao) {
                botao.setAttribute("aria-expanded", "false");
            }
        });
    });

    document.querySelectorAll(".submenu a[target='frame_art']")
        .forEach(function (link) {
            link.addEventListener("click", function () {
                menus.forEach(function (menu) {
                    menu.classList.remove("aberto");

                    const botao = menu.querySelector(".menu-botao");

                    if (botao) {
                        botao.setAttribute("aria-expanded", "false");
                    }
                });
            });
        });
});
