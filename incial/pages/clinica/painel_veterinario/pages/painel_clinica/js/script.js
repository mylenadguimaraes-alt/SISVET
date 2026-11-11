
const urlPHP = "../../../../../../bd_conexao/php/dados_painel_veterinario.php";

const nomeClinica = document.getElementById("nomeClinica");
const mensagem = document.getElementById("mensagem");
const botaoSair = document.getElementById("botaoSair");


// ========================================
// MENSAGEM
// ========================================

function mostrarMensagem(texto) {

    mensagem.textContent = texto;
    mensagem.className = "mensagem visivel";

}


// ========================================
// CARREGAR CLÍNICA
// ========================================

async function carregarClinica() {

    try {

        const resposta = await fetch(
            urlPHP + "?acao=consultar",
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem || "Não foi possível carregar a clínica."
            );

        }

        nomeClinica.textContent = dados.nome_clinica;

    } catch (erro) {

        console.error(erro);

        mostrarMensagem(erro.message);

        nomeClinica.textContent = "Clínica indisponível";

    }

}


// ========================================
// SAIR DA CLÍNICA
// ========================================

async function sairDaClinica() {

    const confirmar = confirm(
        "Deseja realmente sair desta clínica?"
    );

    if (!confirmar) {
        return;
    }

    botaoSair.disabled = true;
    botaoSair.textContent = "Saindo...";

    try {

        const resposta = await fetch(
            urlPHP + "?acao=sair",
            {
                method: "POST",
                credentials: "same-origin"
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem || "Não foi possível sair da clínica."
            );

        }

        window.location.href =
            "../../../../../../bd_conexao/php/entrar_clinica_veterinario.php";

    } catch (erro) {

        mostrarMensagem(erro.message);

        botaoSair.disabled = false;
        botaoSair.textContent = "Sair da clínica";

    }

}


// ========================================
// EVENTOS
// ========================================

botaoSair.addEventListener("click", sairDaClinica);


// ========================================
// INICIALIZAÇÃO
// ========================================

carregarClinica();
