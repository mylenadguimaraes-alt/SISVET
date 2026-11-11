
const urlPHP = "../../../../../../bd_conexao/php/dados_painel_recepcionista.php";

const nomeClinica = document.getElementById("nomeClinica");
const mensagem = document.getElementById("mensagem");

const botaoSair = document.getElementById("botaoSair");
const botaoSairCard = document.getElementById("botaoSairCard");


// ========================================
// EXIBIR MENSAGEM
// ========================================

function mostrarMensagem(texto, tipo = "erro") {

    if (!mensagem) {
        console.error(texto);
        return;
    }

    mensagem.textContent = texto;
    mensagem.className = "mensagem visivel " + tipo;
}


// ========================================
// CARREGAR NOME DA CLÍNICA
// ========================================

async function carregarClinica() {

    if (!nomeClinica) {
        console.error("Elemento nomeClinica não encontrado no HTML.");
        return;
    }

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

        console.error("Erro ao carregar clínica:", erro);

        nomeClinica.textContent = "Clínica indisponível";

        mostrarMensagem(erro.message);
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

    // Guarda somente os botões que realmente existem
    const botoes = [botaoSair, botaoSairCard].filter(Boolean);

    // Desabilita os botões existentes
    botoes.forEach(botao => {
        botao.disabled = true;
    });

    try {

        const resposta = await fetch(
            urlPHP + "?acao=sair",
            {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem || "Não foi possível sair da clínica."
            );
        }

        // Retorna à seleção de clínicas
        window.location.href =
            "../../../../../../bd_conexao/php/entrar_clinica_recepcionista.php";

    } catch (erro) {

        console.error("Erro ao sair da clínica:", erro);

        mostrarMensagem(erro.message);

        // Reabilita os botões existentes
        botoes.forEach(botao => {
            botao.disabled = false;
        });
    }
}


// ========================================
// EVENTOS DOS BOTÕES
// ========================================

if (botaoSair) {

    botaoSair.addEventListener("click", sairDaClinica);

}

if (botaoSairCard) {

    botaoSairCard.addEventListener("click", sairDaClinica);

}


// ========================================
// INICIALIZAÇÃO
// ========================================

carregarClinica();
