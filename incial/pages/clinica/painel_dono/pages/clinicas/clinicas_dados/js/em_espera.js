document.addEventListener("DOMContentLoaded", function () {

    const listaClinicas = document.getElementById("listaClinicas");
    const campoPesquisa = document.getElementById("pesquisaClinica");

    // Use aqui o caminho do mesmo PHP que funcionou
    // na página de clínicas autorizadas.
    const caminhoPHP = "../../../../../../../bd_conexao/php/dono_clinicas_dados.php";

    // Carregar somente as clínicas em espera
    function carregarClinicas() {

        if (!listaClinicas) {
            console.error("Elemento #listaClinicas não encontrado no HTML.");
            return;
        }

        listaClinicas.innerHTML =
            '<p class="carregando">Carregando clínicas em espera...</p>';

        fetch(caminhoPHP + "?situacao=espera", {
            method: "GET",
            credentials: "same-origin"
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

            // Aplicar a pesquisa depois de carregar as clínicas
            filtrarClinicas();
        })
        .catch(function (erro) {

            console.error(
                "Erro ao carregar clínicas em espera:",
                erro
            );

            listaClinicas.innerHTML = "";

            const mensagem = document.createElement("p");
            mensagem.className = "erro-carregamento";
            mensagem.textContent =
                "Não foi possível carregar as clínicas em espera. " +
                "Verifique o caminho do PHP e se você está logado.";

            listaClinicas.appendChild(mensagem);
        });
    }

    // Pesquisar clínicas pelo nome
    function filtrarClinicas() {

        if (!listaClinicas) {
            return;
        }

        const termo = campoPesquisa
            ? campoPesquisa.value.trim().toLocaleLowerCase("pt-BR")
            : "";

        const blocos = listaClinicas.querySelectorAll(".bloco-clinica");

        let encontrados = 0;

        blocos.forEach(function (bloco) {

            const elementoNome = bloco.querySelector(".nome-clinica");

            const nome = elementoNome
                ? elementoNome.textContent.trim().toLocaleLowerCase("pt-BR")
                : "";

            const corresponde = nome.includes(termo);

            bloco.hidden = !corresponde;

            if (corresponde) {
                encontrados++;
            }
        });

        // Criar mensagem caso a pesquisa não encontre resultados
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
                "Nenhuma clínica em espera corresponde à pesquisa.";

            mensagem.hidden = false;

        } else {

            mensagem.hidden = true;
        }
    }

    // Pesquisar enquanto a pessoa digita
    if (campoPesquisa) {
        campoPesquisa.addEventListener("input", filtrarClinicas);
    }

    // Iniciar o carregamento
    carregarClinicas();

});
