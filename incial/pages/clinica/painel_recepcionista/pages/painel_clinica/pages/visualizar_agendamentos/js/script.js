const urlPHP = "../../../../../../../../bd_conexao/php/visualizar_atendimentos.php";

const pesquisa = document.getElementById("pesquisa");
const mensagem = document.getElementById("mensagem");

const listaHoje = document.getElementById("listaHoje");
const listaProximos = document.getElementById("listaProximos");
const listaAusentes = document.getElementById("listaAusentes");
const listaPassados = document.getElementById("listaPassados");

let atendimentos = [];
let horarioServidor = new Date();


// ========================================
// MENSAGENS
// ========================================

function mostrarMensagem(texto, tipo = "erro") {
    mensagem.textContent = texto;
    mensagem.className = "mensagem visivel " + tipo;
}

function limparMensagem() {
    mensagem.textContent = "";
    mensagem.className = "mensagem";
}


// ========================================
// DATAS
// ========================================

function converterData(data) {
    return new Date(data.replace(" ", "T"));
}

function formatarData(data) {
    return converterData(data).toLocaleDateString("pt-BR");
}

function formatarHora(data) {
    return converterData(data).toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit"
    });
}

function mesmoDia(data1, data2) {
    return data1.getFullYear() === data2.getFullYear() &&
        data1.getMonth() === data2.getMonth() &&
        data1.getDate() === data2.getDate();
}


// ========================================
// CLASSIFICAR ATENDIMENTOS
// ========================================

function classificarAtendimentos() {

    const hoje = horarioServidor;

    const categorias = {
        hoje: [],
        proximos: [],
        ausentes: [],
        passados: []
    };

    atendimentos.forEach(item => {

        const dataConsulta = converterData(item.data_hora);

        const horarioPassado = dataConsulta < hoje;

        const statusFinal =
            item.status === "realizada" ||
            item.status === "cancelada";

        // Histórico de realizados e cancelados
        if (statusFinal) {
            categorias.passados.push(item);
            return;
        }

        // Atendimento cujo horário passou sem finalização
        if (horarioPassado) {
            categorias.ausentes.push(item);
            return;
        }

        // Atendimento de hoje
        if (mesmoDia(dataConsulta, hoje)) {
            categorias.hoje.push(item);
            return;
        }

        // Atendimento em data futura
        categorias.proximos.push(item);

    });

    return categorias;
}


// ========================================
// STATUS
// ========================================

function formatarStatus(item, ausente = false) {

    if (ausente) {
        return {
            texto: "Não compareceu",
            classe: "status-ausente"
        };
    }

    const status = {
        pendente: {
            texto: "Pendente",
            classe: "status-pendente"
        },

        confirmada: {
            texto: "Confirmada",
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

    return status[item.status] || {
        texto: item.status,
        classe: ""
    };
}


// ========================================
// CRIAR ELEMENTOS
// ========================================

function criarDado(titulo, valor, largo = false) {

    const div = document.createElement("div");

    div.className = largo ? "dado dado-largo" : "dado";

    const strong = document.createElement("strong");
    strong.textContent = titulo;

    const span = document.createElement("span");
    span.textContent = valor || "Não informado";

    div.appendChild(strong);
    div.appendChild(span);

    return div;
}


function criarBotao(texto, classe, id, status) {

    const botao = document.createElement("button");

    botao.type = "button";
    botao.className = "botao " + classe;
    botao.textContent = texto;

    botao.addEventListener("click", () => {
        alterarStatus(id, status, botao);
    });

    return botao;
}


// ========================================
// CRIAR CARD DO ATENDIMENTO
// ========================================

function criarCard(item, ausente = false, historico = false) {

    const card = document.createElement("article");
    card.className = "atendimento";

    // Cabeçalho
    const cabecalho = document.createElement("div");
    cabecalho.className = "cabecalho-atendimento";

    const identificacao = document.createElement("div");

    const nomePet = document.createElement("h3");
    nomePet.className = "nome-pet";
    nomePet.textContent = item.nome_pet;

    const nomeTutor = document.createElement("div");
    nomeTutor.className = "nome-tutor";
    nomeTutor.textContent = "Tutor: " + item.nome_tutor;

    identificacao.appendChild(nomePet);
    identificacao.appendChild(nomeTutor);

    const status = formatarStatus(item, ausente);

    const badge = document.createElement("span");
    badge.className = "status " + status.classe;
    badge.textContent = status.texto;

    cabecalho.appendChild(identificacao);
    cabecalho.appendChild(badge);

    card.appendChild(cabecalho);


    // Dados
    const dados = document.createElement("div");
    dados.className = "dados-atendimento";

    dados.appendChild(
        criarDado("CPF do tutor", item.cpf)
    );

    dados.appendChild(
        criarDado("Espécie", item.especie)
    );

    dados.appendChild(
        criarDado("Raça", item.raca)
    );

    dados.appendChild(
        criarDado("Data", formatarData(item.data_hora))
    );

    dados.appendChild(
        criarDado("Horário", formatarHora(item.data_hora))
    );

    dados.appendChild(
        criarDado("Motivo", item.motivo, true)
    );

    if (item.observacoes) {
        dados.appendChild(
            criarDado("Observações", item.observacoes, true)
        );
    }

    card.appendChild(dados);


    // Botões
    if (!historico) {

        const acoes = document.createElement("div");
        acoes.className = "acoes-atendimento";

        acoes.appendChild(
            criarBotao(
                "Compareceu",
                "botao-compareceu",
                item.id_consulta,
                "realizada"
            )
        );

        acoes.appendChild(
            criarBotao(
                "Cancelar",
                "botao-cancelar",
                item.id_consulta,
                "cancelada"
            )
        );

        card.appendChild(acoes);
    }

    return card;
}


// ========================================
// RENDERIZAR LISTAS
// ========================================

function renderizarLista(elemento, dados, ausente = false, historico = false) {

    elemento.innerHTML = "";

    if (dados.length === 0) {

        const vazio = document.createElement("div");

        vazio.className = "vazio";
        vazio.textContent = "Nenhum atendimento encontrado.";

        elemento.appendChild(vazio);

        return;
    }

    dados.forEach(item => {
        elemento.appendChild(
            criarCard(item, ausente, historico)
        );
    });
}


// ========================================
// PESQUISA
// ========================================

function pesquisarAtendimentos() {

    const termo = pesquisa.value.toLowerCase().trim();

    const categorias = classificarAtendimentos();

    function filtrar(lista) {

        return lista.filter(item => {

            const conteudo = [
                item.nome_pet,
                item.nome_tutor,
                item.cpf,
                item.especie,
                item.raca,
                item.motivo,
                item.observacoes,
                item.status,
                formatarData(item.data_hora),
                formatarHora(item.data_hora)
            ].join(" ").toLowerCase();

            return conteudo.includes(termo);
        });
    }

    const hoje = filtrar(categorias.hoje);
    const proximos = filtrar(categorias.proximos);
    const ausentes = filtrar(categorias.ausentes);
    const passados = filtrar(categorias.passados);

    renderizarLista(listaHoje, hoje);
    renderizarLista(listaProximos, proximos);
    renderizarLista(listaAusentes, ausentes, true);
    renderizarLista(listaPassados, passados, false, true);

    document.getElementById("contadorHoje").textContent = hoje.length;
    document.getElementById("contadorProximos").textContent = proximos.length;
    document.getElementById("contadorAusentes").textContent = ausentes.length;
    document.getElementById("contadorPassados").textContent = passados.length;
}


// ========================================
// CARREGAR ATENDIMENTOS
// ========================================

async function carregarAtendimentos() {

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
                resultado.mensagem || "Erro ao carregar atendimentos."
            );
        }

        atendimentos = resultado.atendimentos || [];

        horarioServidor = new Date(resultado.data_hora_servidor);

        pesquisarAtendimentos();

        limparMensagem();

    } catch (erro) {

        mostrarMensagem(erro.message);

        listaHoje.innerHTML = "";
        listaProximos.innerHTML = "";
        listaAusentes.innerHTML = "";
        listaPassados.innerHTML = "";

    }
}


// ========================================
// ALTERAR STATUS
// ========================================

async function alterarStatus(idConsulta, novoStatus, botao) {

    const confirmacao = novoStatus === "realizada"
        ? "Confirma que o animal compareceu ao atendimento?"
        : "Deseja realmente cancelar este atendimento?";

    if (!confirm(confirmacao)) {
        return;
    }

    limparMensagem();

    botao.disabled = true;

    const textoOriginal = botao.textContent;

    botao.textContent = "Atualizando...";

    try {

        const formData = new FormData();

        formData.append("id_consulta", idConsulta);
        formData.append("status", novoStatus);

        const resposta = await fetch(
            urlPHP + "?acao=alterar_status",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(
                resultado.mensagem || "Não foi possível atualizar o atendimento."
            );
        }

        mostrarMensagem(resultado.mensagem, "sucesso");

        await carregarAtendimentos();

    } catch (erro) {

        mostrarMensagem(erro.message);

        botao.disabled = false;
        botao.textContent = textoOriginal;

    }
}


// ========================================
// EVENTOS
// ========================================

pesquisa.addEventListener("input", pesquisarAtendimentos);


// ========================================
// INICIALIZAÇÃO
// ========================================

carregarAtendimentos();
