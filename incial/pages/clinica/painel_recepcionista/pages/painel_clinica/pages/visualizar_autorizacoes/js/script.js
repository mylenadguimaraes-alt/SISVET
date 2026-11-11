const urlPHP = "../../../../../../../../bd_conexao/php/visualizar_autorizacoes.php";

const mensagem = document.getElementById("mensagem");
const pesquisa = document.getElementById("pesquisa");

const listas = {
    espera: document.getElementById("listaEspera"),
    confirmado: document.getElementById("listaConfirmadas"),
    negado: document.getElementById("listaNegadas"),
    revogado: document.getElementById("listaRevogadas")
};

let dadosAutorizacoes = [];


// ========================================
// ESCAPAR HTML
// ========================================

function escaparHTML(valor) {

    return String(valor ?? "").replace(/[&<>"']/g, caractere => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[caractere]);

}


// ========================================
// FORMATAR DATA
// ========================================

function formatarData(data) {

    if (!data) return "Não informada";

    const dataObj = new Date(data.replace(" ", "T"));

    if (isNaN(dataObj.getTime())) {
        return "Não informada";
    }

    return dataObj.toLocaleString("pt-BR");

}


// ========================================
// NORMALIZAR TEXTO DA PESQUISA
// ========================================

function normalizarTexto(texto) {

    return String(texto ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();

}


// ========================================
// CRIAR CARD
// ========================================

function criarCard(item) {

    return `
        <article class="autorizacao">

            <div class="autorizacao-topo">

                <h3>${escaparHTML(item.nome_pet)}</h3>

                <span class="badge badge-${escaparHTML(item.status)}">
                    ${escaparHTML(item.status_formatado)}
                </span>

            </div>

            <div class="dados">

                <div class="dado">
                    <strong>Tutor</strong>
                    <span>${escaparHTML(item.nome_tutor)}</span>
                </div>

                <div class="dado">
                    <strong>CPF</strong>
                    <span>${escaparHTML(item.cpf || "Não informado")}</span>
                </div>

                <div class="dado">
                    <strong>Espécie</strong>
                    <span>${escaparHTML(item.especie)}</span>
                </div>

                <div class="dado">
                    <strong>Raça</strong>
                    <span>${escaparHTML(item.raca || "Não informada")}</span>
                </div>

                <div class="dado">
                    <strong>Data da solicitação</strong>
                    <span>${escaparHTML(formatarData(item.data_solicitacao))}</span>
                </div>

                <div class="dado">
                    <strong>Última atualização</strong>
                    <span>${escaparHTML(formatarData(item.data_atualizacao))}</span>
                </div>

            </div>

        </article>
    `;

}


// ========================================
// FILTRAR AUTORIZAÇÕES
// ========================================

function filtrarAutorizacoes() {

    const termo = normalizarTexto(pesquisa.value);

    const filtrados = dadosAutorizacoes.filter(item => {

        const campos = [
            item.nome_tutor,
            item.cpf,
            item.nome_pet,
            item.especie,
            item.raca,
            item.status_formatado
        ];

        return campos.some(campo =>
            normalizarTexto(campo).includes(termo)
        );

    });

    renderizarListas(filtrados);

}


// ========================================
// RENDERIZAR LISTAS
// ========================================

function renderizarListas(dados) {

    Object.keys(listas).forEach(status => {

        const itens = dados.filter(item => item.status === status);

        if (itens.length === 0) {

            listas[status].innerHTML = `
                <div class="vazio">
                    Nenhuma autorização encontrada.
                </div>
            `;

            return;
        }

        listas[status].innerHTML = itens
            .map(criarCard)
            .join("");

    });

}


// ========================================
// CARREGAR AUTORIZAÇÕES
// ========================================

async function carregarAutorizacoes() {

    try {

        const resposta = await fetch(
            urlPHP + "?acao=consultar",
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(
                resultado.mensagem ||
                "Não foi possível carregar as autorizações."
            );

        }

        dadosAutorizacoes = resultado.autorizacoes || [];

        filtrarAutorizacoes();

    } catch (erro) {

        console.error("Erro ao carregar autorizações:", erro);

        mensagem.textContent = erro.message;
        mensagem.className = "mensagem erro";

        Object.values(listas).forEach(lista => {

            lista.innerHTML = `
                <div class="vazio">
                    Não foi possível carregar os dados.
                </div>
            `;

        });

    }

}


// ========================================
// EVENTO DA PESQUISA
// ========================================

pesquisa.addEventListener("input", filtrarAutorizacoes);


// ========================================
// INICIALIZAÇÃO
// ========================================

carregarAutorizacoes();
