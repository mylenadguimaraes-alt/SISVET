
const urlPHP = "../../../../../../../../bd_conexao/php/visualizar_atendimentos_veterinario.php";

const listaConsultas = document.getElementById("listaConsultas");
const pesquisa = document.getElementById("pesquisa");
const mensagem = document.getElementById("mensagem");

const totalConsultas = document.getElementById("totalConsultas");
const totalRealizadas = document.getElementById("totalRealizadas");
const totalPendentes = document.getElementById("totalPendentes");

const dataAtual = document.getElementById("dataAtual");
const botaoAtualizar = document.getElementById("botaoAtualizar");

let consultas = [];


// ========================================
// MENSAGENS
// ========================================

function mostrarMensagem(texto, tipo = "erro") {

    mensagem.textContent = texto;
    mensagem.className = "mensagem " + tipo;

}

function limparMensagem() {

    mensagem.textContent = "";
    mensagem.className = "mensagem";

}


// ========================================
// DATA ATUAL
// ========================================

function mostrarDataAtual() {

    const hoje = new Date();

    dataAtual.textContent = hoje.toLocaleDateString("pt-BR", {
        weekday: "long",
        day: "2-digit",
        month: "long",
        year: "numeric"
    });

}


// ========================================
// FORMATAÇÃO
// ========================================

function formatarHora(dataHora) {

    const partes = dataHora.split(" ");

    if (partes.length < 2) {
        return "--:--";
    }

    return partes[1].substring(0, 5);

}


function formatarStatus(status) {

    const estados = {

        pendente: {
            texto: "A realizar",
            classe: "status-pendente"
        },

        confirmada: {
            texto: "Confirmada - a realizar",
            classe: "status-confirmada"
        },

        realizada: {
            texto: "Realizada",
            classe: "status-realizada"
        },

        cancelada: {
            texto: "Cancelada",
            classe: "status-cancelada"
        }

    };

    return estados[status] || {
        texto: status,
        classe: "status-pendente"
    };

}


// ========================================
// CRIAR ELEMENTO DE INFORMAÇÃO
// ========================================

function criarInformacao(titulo, valor, classe = "") {

    const div = document.createElement("div");

    div.className = "informacao " + classe;

    const label = document.createElement("label");
    label.textContent = titulo;

    const p = document.createElement("p");

    p.textContent =
        valor && String(valor).trim() !== ""
            ? valor
            : "Não informado";

    div.appendChild(label);
    div.appendChild(p);

    return div;

}


// ========================================
// RENDERIZAR CONSULTAS
// ========================================

function renderizarConsultas(dados) {

    listaConsultas.innerHTML = "";

    if (dados.length === 0) {

        const estado = document.createElement("p");

        estado.className = "estado-lista";

        estado.textContent = consultas.length === 0
            ? "Não existem consultas agendadas para hoje."
            : "Nenhuma consulta encontrada com essa pesquisa.";

        listaConsultas.appendChild(estado);

        return;
    }


    dados.forEach(consulta => {

        const card = document.createElement("article");

        card.className = "consulta";


        // Cabeçalho

        const cabecalho = document.createElement("div");

        cabecalho.className = "consulta-cabecalho";


        const horario = document.createElement("div");

        horario.className = "horario";

        horario.textContent = formatarHora(consulta.data_hora);


        const status = formatarStatus(consulta.status);

        const badge = document.createElement("span");

        badge.className = "status " + status.classe;

        badge.textContent = status.texto;


        cabecalho.appendChild(horario);
        cabecalho.appendChild(badge);


        // Informações

        const dadosDiv = document.createElement("div");

        dadosDiv.className = "dados-consulta";


        dadosDiv.appendChild(
            criarInformacao(
                "Tutor",
                consulta.nome_tutor
            )
        );

        dadosDiv.appendChild(
            criarInformacao(
                "E-mail",
                consulta.email
            )
        );

        dadosDiv.appendChild(
            criarInformacao(
                "Animal",
                consulta.nome_pet
            )
        );

        dadosDiv.appendChild(
            criarInformacao(
                "Espécie",
                consulta.especie
            )
        );

        dadosDiv.appendChild(
            criarInformacao(
                "Motivo da consulta",
                consulta.motivo,
                "informacao-larga"
            )
        );

        dadosDiv.appendChild(
            criarInformacao(
                "Observações",
                consulta.observacoes,
                "informacao-larga"
            )
        );


        card.appendChild(cabecalho);
        card.appendChild(dadosDiv);

        listaConsultas.appendChild(card);

    });

}


// ========================================
// ATUALIZAR RESUMO
// ========================================

function atualizarResumo() {

    totalConsultas.textContent = consultas.length;

    totalRealizadas.textContent = consultas.filter(
        consulta => consulta.status === "realizada"
    ).length;

    totalPendentes.textContent = consultas.filter(
        consulta =>
            consulta.status === "pendente" ||
            consulta.status === "confirmada"
    ).length;

}


// ========================================
// PESQUISA
// ========================================

pesquisa.addEventListener("input", function () {

    const termo = this.value.toLowerCase().trim();

    const filtradas = consultas.filter(consulta => {

        const conteudo = [

            consulta.nome_tutor,
            consulta.email,
            consulta.nome_pet,
            consulta.especie,
            consulta.motivo,
            consulta.observacoes,
            consulta.status,
            formatarHora(consulta.data_hora)

        ].join(" ").toLowerCase();

        return conteudo.includes(termo);

    });

    renderizarConsultas(filtradas);

});


// ========================================
// CARREGAR CONSULTAS DO DIA
// ========================================

async function carregarConsultas() {

    limparMensagem();

    botaoAtualizar.disabled = true;
    botaoAtualizar.textContent = "Carregando...";

    listaConsultas.innerHTML = `
        <p class="estado-lista">Buscando consultas do dia...</p>
    `;

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
                "Não foi possível carregar as consultas."
            );

        }

        consultas = resultado.consultas || [];

        atualizarResumo();

        renderizarConsultas(consultas);

    } catch (erro) {

        console.error("Erro ao carregar consultas:", erro);

        consultas = [];

        atualizarResumo();

        listaConsultas.innerHTML = `
            <p class="estado-lista">
                Não foi possível carregar os atendimentos.
            </p>
        `;

        mostrarMensagem(erro.message);

    } finally {

        botaoAtualizar.disabled = false;
        botaoAtualizar.textContent = "Atualizar";

    }

}


// ========================================
// EVENTOS
// ========================================

botaoAtualizar.addEventListener("click", carregarConsultas);


// ========================================
// INICIALIZAÇÃO
// ========================================

mostrarDataAtual();
carregarConsultas();
