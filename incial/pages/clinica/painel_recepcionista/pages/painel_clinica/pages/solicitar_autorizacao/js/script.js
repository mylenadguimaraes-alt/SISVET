
const urlPHP = "../../../../../../../../bd_conexao/php/solicitar_autorizacao.php";

const cpf = document.getElementById("cpf");
const buscarTutor = document.getElementById("buscarTutor");

const dadosTutor = document.getElementById("dadosTutor");
const formAutorizacao = document.getElementById("formAutorizacao");
const formSolicitacao = document.getElementById("formSolicitacao");

const pet = document.getElementById("pet");
const mensagem = document.getElementById("mensagem");

const estadoAutorizacao = document.getElementById("estadoAutorizacao");
const botaoSolicitar = document.getElementById("botaoSolicitar");

let idTutorAtual = null;
let statusAtual = null;


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
// MÁSCARA CPF
// ========================================

cpf.addEventListener("input", function () {

    let valor = this.value.replace(/\D/g, "").slice(0, 11);

    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d{1,2})$/, "$1-$2");

    this.value = valor;

    idTutorAtual = null;

    dadosTutor.hidden = true;
    formAutorizacao.hidden = true;

    limparMensagem();

});


// ========================================
// BUSCAR TUTOR
// ========================================

buscarTutor.addEventListener("click", carregarTutor);

cpf.addEventListener("keydown", function (e) {

    if (e.key === "Enter") {

        e.preventDefault();

        carregarTutor();

    }

});


async function carregarTutor() {

    limparMensagem();

    const cpfLimpo = cpf.value.replace(/\D/g, "");

    if (cpfLimpo.length !== 11) {

        mostrarMensagem("Informe um CPF válido com 11 números.");

        cpf.focus();

        return;
    }

    buscarTutor.disabled = true;
    buscarTutor.textContent = "Buscando...";

    try {

        const formData = new FormData();

        formData.append("cpf", cpfLimpo);

        const resposta = await fetch(
            urlPHP + "?acao=buscar_tutor",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(resultado.mensagem || "Erro ao buscar tutor.");

        }

        idTutorAtual = resultado.tutor.id_tutor;

        document.getElementById("nomeTutor").textContent =
            resultado.tutor.nome_completo;

        document.getElementById("telefoneTutor").textContent =
            resultado.tutor.telefone;

        document.getElementById("emailTutor").textContent =
            resultado.tutor.email;


        // ========================================
        // CARREGAR PETS
        // ========================================

        pet.innerHTML = "";

        const opcaoInicial = document.createElement("option");

        opcaoInicial.value = "";
        opcaoInicial.textContent = "Selecione um animal";

        pet.appendChild(opcaoInicial);

        resultado.pets.forEach(animal => {

            const opcao = document.createElement("option");

            opcao.value = animal.id_pet;

            opcao.textContent =
                animal.nome + " - " +
                animal.especie +
                (animal.raca ? " (" + animal.raca + ")" : "");

            pet.appendChild(opcao);

        });

        dadosTutor.hidden = false;

        if (resultado.pets.length === 0) {

            formAutorizacao.hidden = true;

            mostrarMensagem(
                "Tutor encontrado, mas não existem animais cadastrados.",
                "aviso"
            );

            return;
        }

        formAutorizacao.hidden = false;

        estadoAutorizacao.hidden = true;
        botaoSolicitar.hidden = true;

        mostrarMensagem(
            "Tutor localizado! Selecione o animal.",
            "sucesso"
        );

    } catch (erro) {

        idTutorAtual = null;

        dadosTutor.hidden = true;
        formAutorizacao.hidden = true;

        mostrarMensagem(erro.message);

    } finally {

        buscarTutor.disabled = false;
        buscarTutor.textContent = "Buscar tutor";

    }

}


// ========================================
// CONSULTAR AUTORIZAÇÃO DO PET
// ========================================

pet.addEventListener("change", consultarAutorizacao);


async function consultarAutorizacao() {

    limparMensagem();

    estadoAutorizacao.hidden = true;
    botaoSolicitar.hidden = true;

    statusAtual = null;

    if (!pet.value || !idTutorAtual) {
        return;
    }

    pet.disabled = true;

    try {

        const formData = new FormData();

        formData.append("id_tutor", idTutorAtual);
        formData.append("id_pet", pet.value);

        const resposta = await fetch(
            urlPHP + "?acao=consultar_autorizacao",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(resultado.mensagem || "Erro ao consultar autorização.");

        }

        if (!resultado.existe) {

            statusAtual = "novo";

            mostrarEstado(
                "novo",
                "Nenhuma autorização encontrada. Você pode solicitar uma nova autorização."
            );

            botaoSolicitar.hidden = false;
            botaoSolicitar.textContent = "Solicitar autorização";

            return;
        }

        statusAtual = resultado.status;

        switch (resultado.status) {

            case "espera":

                mostrarEstado(
                    "espera",
                    "O pedido de autorização já foi enviado e está aguardando análise da clínica."
                );

                break;

            case "confirmado":

                mostrarEstado(
                    "confirmado",
                    "A autorização deste animal já está confirmada pela clínica."
                );

                break;

            case "negado":

                mostrarEstado(
                    "negado",
                    "O pedido anterior foi negado. Se desejar, você pode solicitar novamente."
                );

                botaoSolicitar.hidden = false;
                botaoSolicitar.textContent = "Solicitar novamente";

                break;

            case "revogado":

                mostrarEstado(
                    "revogado",
                    "Esta autorização foi revogada. Entre em contato com o responsável pela clínica."
                );

                break;

        }

    } catch (erro) {

        mostrarMensagem(erro.message);

    } finally {

        pet.disabled = false;

    }

}


// ========================================
// MOSTRAR ESTADO
// ========================================

function mostrarEstado(status, texto) {

    estadoAutorizacao.textContent = texto;

    estadoAutorizacao.className = "estado-autorizacao " + status;

    estadoAutorizacao.hidden = false;

}


// ========================================
// SOLICITAR AUTORIZAÇÃO
// ========================================

formSolicitacao.addEventListener("submit", async function (e) {

    e.preventDefault();

    limparMensagem();

    if (!idTutorAtual || !pet.value) {

        mostrarMensagem("Localize o tutor e selecione um animal.");

        return;
    }

    if (
        statusAtual === "espera" ||
        statusAtual === "confirmado" ||
        statusAtual === "revogado"
    ) {

        mostrarMensagem(
            "Não é possível enviar uma nova solicitação nesta situação.",
            "aviso"
        );

        return;
    }

    const formData = new FormData();

    formData.append("id_tutor", idTutorAtual);
    formData.append("id_pet", pet.value);

    botaoSolicitar.disabled = true;
    botaoSolicitar.textContent = "Enviando...";

    try {

        const resposta = await fetch(
            urlPHP + "?acao=solicitar",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(resultado.mensagem || "Erro ao enviar solicitação.");

        }

        mostrarMensagem(resultado.mensagem, "sucesso");

        // Atualiza a situação sem precisar buscar o tutor novamente.
        await consultarAutorizacao();

    } catch (erro) {

        mostrarMensagem(erro.message);

    } finally {

        botaoSolicitar.disabled = false;

        if (statusAtual === "negado") {
            botaoSolicitar.textContent = "Solicitar novamente";
        } else {
            botaoSolicitar.textContent = "Solicitar autorização";
        }

    }

});
