
const urlPHP = "../../../../../../../../bd_conexao/php/cadastrar_atendimento.php";

const cpf = document.getElementById("cpf");
const buscarTutor = document.getElementById("buscarTutor");

const dadosTutor = document.getElementById("dadosTutor");
const formAtendimento = document.getElementById("formAtendimento");

const pet = document.getElementById("pet");
const data = document.getElementById("data");
const hora = document.getElementById("hora");

const mensagem = document.getElementById("mensagem");
const botaoCadastrar = document.getElementById("botaoCadastrar");

let idTutorAtual = null;


// ========================================
// HORÁRIO DE FUNCIONAMENTO
// ========================================

hora.min = "08:00";
hora.max = "21:00";

// ========================================
// MENSAGENS
// ========================================

function mostrarMensagem(texto, tipo = "erro") {

    mensagem.textContent = texto;
    mensagem.className = "mensagem visivel " + tipo;

    mensagem.scrollIntoView({
        behavior: "smooth",
        block: "nearest"
    });
}

function limparMensagem() {

    mensagem.textContent = "";
    mensagem.className = "mensagem";
}


// ========================================
// DATA MÍNIMA: HOJE
// ========================================

function atualizarDataMinima() {

    const agora = new Date();

    const ano = agora.getFullYear();
    const mes = String(agora.getMonth() + 1).padStart(2, "0");
    const dia = String(agora.getDate()).padStart(2, "0");

    data.min = `${ano}-${mes}-${dia}`;
}

atualizarDataMinima();


// ========================================
// MÁSCARA DO CPF
// ========================================

cpf.addEventListener("input", function () {

    let valor = this.value.replace(/\D/g, "").slice(0, 11);

    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d{1,2})$/, "$1-$2");

    this.value = valor;

    // Invalida os dados anteriores quando o CPF mudar.
    idTutorAtual = null;

    dadosTutor.hidden = true;
    formAtendimento.hidden = true;

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

            throw new Error(
                resultado.mensagem ||
                "Não foi possível localizar o tutor."
            );
        }

        idTutorAtual = resultado.tutor.id_tutor;

        // Dados do tutor
        document.getElementById("nomeTutor").textContent =
            resultado.tutor.nome_completo;

        document.getElementById("telefoneTutor").textContent =
            resultado.tutor.telefone;

        document.getElementById("emailTutor").textContent =
            resultado.tutor.email;

        // Limpa o seletor
        pet.innerHTML = "";

        const opcaoInicial = document.createElement("option");

        opcaoInicial.value = "";
        opcaoInicial.textContent = "Selecione um animal";

        pet.appendChild(opcaoInicial);

        // Adiciona os animais
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

            formAtendimento.hidden = true;

            mostrarMensagem(
                "Tutor encontrado, mas não existem animais cadastrados para ele.",
                "aviso"
            );

            return;
        }

        formAtendimento.hidden = false;

        mostrarMensagem(
            "Tutor localizado! Selecione o animal para continuar.",
            "sucesso"
        );

        pet.focus();

    } catch (erro) {

        idTutorAtual = null;

        dadosTutor.hidden = true;
        formAtendimento.hidden = true;

        mostrarMensagem(erro.message);

    } finally {

        buscarTutor.disabled = false;
        buscarTutor.textContent = "Buscar tutor";

    }
}


// ========================================
// VALIDAR HORÁRIO NO NAVEGADOR
// ========================================

function validarHorario() {

    if (!hora.value) {
        return false;
    }

    if (hora.value < "08:00" || hora.value > "21:00") {

        mostrarMensagem(
            "A clínica funciona das 08:00 às 22:00. O último atendimento pode começar às 21:00."
        );

        hora.focus();

        return false;
    }

    return true;
}

hora.addEventListener("change", function () {

    if (hora.value && !validarHorario()) {
        hora.value = "";
    }

});


// ========================================
// CADASTRAR ATENDIMENTO
// ========================================

formAtendimento.addEventListener("submit", async function (e) {

    e.preventDefault();

    limparMensagem();

    if (!idTutorAtual) {

        mostrarMensagem("Primeiro localize um tutor pelo CPF.");

        return;
    }

    if (!pet.value) {

        mostrarMensagem("Selecione um animal.");

        pet.focus();

        return;
    }

    if (!data.value || !hora.value) {

        mostrarMensagem("Informe a data e o horário do atendimento.");

        return;
    }

    if (!validarHorario()) {
        return;
    }

    const motivo = document.getElementById("motivo").value.trim();

    if (!motivo) {

        mostrarMensagem("Informe o motivo do atendimento.");

        return;
    }

    if (data.value < data.min) {

        mostrarMensagem("Não é possível marcar atendimentos em datas passadas.");

        return;
    }

    const formData = new FormData();

    formData.append("id_tutor", idTutorAtual);
    formData.append("id_pet", pet.value);
    formData.append("data", data.value);
    formData.append("hora", hora.value);
    formData.append("motivo", motivo);

    formData.append(
        "observacoes",
        document.getElementById("observacoes").value.trim()
    );

    botaoCadastrar.disabled = true;
    botaoCadastrar.textContent = "Verificando horário...";

    try {

        const resposta = await fetch(
            urlPHP + "?acao=cadastrar",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {

            throw new Error(
                resultado.mensagem ||
                "Não foi possível cadastrar o atendimento."
            );
        }

        mostrarMensagem(
            resultado.mensagem,
            "sucesso"
        );

        // Limpa os dados do atendimento.
        // O tutor continua selecionado.

        pet.value = "";
        data.value = "";
        hora.value = "";

        document.getElementById("motivo").value = "";
        document.getElementById("observacoes").value = "";

    } catch (erro) {

        mostrarMensagem(erro.message);

    } finally {

        botaoCadastrar.disabled = false;
        botaoCadastrar.textContent = "Cadastrar atendimento";

    }

});
