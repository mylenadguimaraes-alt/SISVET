
document.addEventListener("DOMContentLoaded", function () {

    // ========================================
    // 1. ELEMENTOS DO HTML
    // ========================================

    const listaClinicas = document.getElementById("listaClinicas");
    const campoPesquisa = document.getElementById("pesquisaClinica");

    // ========================================
    // 2. CAMINHO DO PHP
    // ========================================

    // Substitua pelo caminho que já funcionou no seu projeto.
    const caminhoPHP =
        "../../../../../../../bd_conexao/php/dono_clinicas_dados.php";

    // ========================================
    // 3. CARREGAR CLÍNICAS AUTORIZADAS
    // ========================================

    function carregarClinicas() {

        if (!listaClinicas) {
            console.error(
                "Elemento #listaClinicas não encontrado no HTML."
            );
            return;
        }

        listaClinicas.innerHTML =
            "<p>Carregando clínicas autorizadas...</p>";

        fetch(caminhoPHP + "?situacao=autorizadas", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })

        .then(async function (resposta) {

            const conteudo = await resposta.text();

            if (!resposta.ok) {
                throw new Error(
                    "Erro HTTP " + resposta.status + ": " + conteudo
                );
            }

            return conteudo;
        })

        .then(function (html) {

            listaClinicas.innerHTML = html;

            // Aplicar a pesquisa depois de carregar o conteúdo.
            filtrarClinicas();

        })

        .catch(function (erro) {

            console.error(
                "Erro ao carregar clínicas autorizadas:",
                erro
            );

            listaClinicas.innerHTML = "";

            const mensagem = document.createElement("p");

            mensagem.className = "erro-carregamento";

            mensagem.textContent =
                "Não foi possível carregar as clínicas autorizadas. " +
                "Verifique o caminho do PHP e se você está logado.";

            listaClinicas.appendChild(mensagem);
        });
    }

    // ========================================
    // 4. PESQUISAR CLÍNICAS PELO NOME
    // ========================================

    function filtrarClinicas() {

        if (!listaClinicas) {
            return;
        }

        const termo = campoPesquisa
            ? campoPesquisa.value
                .trim()
                .toLocaleLowerCase("pt-BR")
            : "";

        const blocos = listaClinicas.querySelectorAll(
            ".bloco-clinica"
        );

        let encontrados = 0;

        blocos.forEach(function (bloco) {

            const elementoNome =
                bloco.querySelector(".nome-clinica");

            const nome = elementoNome
                ? elementoNome.textContent
                    .trim()
                    .toLocaleLowerCase("pt-BR")
                : "";

            const corresponde = nome.includes(termo);

            bloco.hidden = !corresponde;

            if (corresponde) {
                encontrados++;
            }
        });

        // ========================================
        // 5. MENSAGEM PARA PESQUISA SEM RESULTADOS
        // ========================================

        let mensagem = document.getElementById("semResultados");

        if (!mensagem) {

            mensagem = document.createElement("p");

            mensagem.id = "semResultados";
            mensagem.className = "sem-resultados";
            mensagem.hidden = true;

            listaClinicas.appendChild(mensagem);
        }

        if (blocos.length > 0 && encontrados === 0) {

            mensagem.textContent =
                "Nenhuma clínica autorizada corresponde à pesquisa.";

            mensagem.hidden = false;

        } else {

            mensagem.hidden = true;
        }
    }

    // ========================================
    // 6. EVENTO DA PESQUISA
    // ========================================

    if (campoPesquisa) {

        campoPesquisa.addEventListener(
            "input",
            filtrarClinicas
        );
    }

    // ========================================
    // 7. INICIAR
    // ========================================

    carregarClinicas();

});
