const urlPHP =
    "../../../../../../../../bd_conexao/php/animais_autorizados_veterinario.php";

const urlCadastrarDocumento =
    "../../../../../../../../bd_conexao/php/cadastrar_documento_vet.php";

const urlVisualizarFoto =
    "../../../../../../../../bd_conexao/php/visualizar_documentos.php";

const urlListaDocumentos =
    "../../../../../../../../bd_conexao/php/visualizar_documento_pet.php";

const cpf = document.getElementById("cpf");
const buscarTutor = document.getElementById("buscarTutor");

const dadosTutor = document.getElementById("dadosTutor");
const selecaoPet = document.getElementById("selecaoPet");
const dadosPet = document.getElementById("dadosPet");

const pet = document.getElementById("pet");
const formPet = document.getElementById("formPet");

const mensagem = document.getElementById("mensagem");
const botaoSalvar = document.getElementById("botaoSalvar");

const linkFoto = document.getElementById("linkFoto");
const estadoFoto = document.getElementById("estadoFoto");

const areaDocumento = document.getElementById("areaDocumento");
const formDocumento = document.getElementById("formDocumento");

const arquivo = document.getElementById("arquivo");
const nomeArquivo = document.getElementById("nomeArquivo");

const botaoCadastrarDocumento =
    document.getElementById("botaoCadastrarDocumento");

const secaoListaDocumentos = document.getElementById("secaoListaDocumentos");
const containerTabelaDocumentos = document.getElementById("containerTabelaDocumentos");

let idTutorAtual = null;
let idPetAtual = null;

function mostrarMensagem(texto, tipo = "erro") {
    mensagem.textContent = texto;
    mensagem.className = "mensagem " + tipo;

    mensagem.scrollIntoView({
        behavior: "smooth",
        block: "nearest"
    });
}

function limparMensagem() {
    mensagem.textContent = "";
    mensagem.className = "mensagem";
}

async function requisitarPHP(acao, dados = {}) {
    const formData = new FormData();

    Object.entries(dados).forEach(([chave, valor]) => {
        formData.append(chave, valor ?? "");
    });

    const resposta = await fetch(
        urlPHP + "?acao=" + encodeURIComponent(acao),
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
            resultado.mensagem || "Erro ao realizar a operação."
        );
    }

    return resultado;
}

function limparPet() {
    idPetAtual = null;

    dadosPet.hidden = true;
    areaDocumento.hidden = true;

    if (secaoListaDocumentos) secaoListaDocumentos.hidden = true;
    if (containerTabelaDocumentos) containerTabelaDocumentos.innerHTML = "";

    linkFoto.removeAttribute("href");
    estadoFoto.textContent = "";
}

cpf.addEventListener("input", function () {
    let valor = this.value.replace(/\D/g, "").slice(0, 11);

    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
    valor = valor.replace(/(\d{3})(\d{1,2})$/, "$1-$2");

    this.value = valor;

    idTutorAtual = null;

    dadosTutor.hidden = true;
    selecaoPet.hidden = true;

    pet.innerHTML = '<option value="">Selecione um animal</option>';

    limparPet();
    limparMensagem();
});

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
        const resultado = await requisitarPHP(
            "buscar_tutor",
            { cpf: cpfLimpo }
        );

        idTutorAtual = resultado.tutor.id_tutor;

        document.getElementById("nomeTutor").value =
            resultado.tutor.nome_completo ?? "";

        document.getElementById("emailTutor").value =
            resultado.tutor.email ?? "";

        document.getElementById("telefoneTutor").value =
            resultado.tutor.telefone ?? "";

        pet.innerHTML = "";

        const opcaoInicial = document.createElement("option");
        opcaoInicial.value = "";
        opcaoInicial.textContent = "Selecione um animal";
        pet.appendChild(opcaoInicial);

        resultado.pets.forEach(animal => {
            const opcao = document.createElement("option");
            opcao.value = animal.id_pet;
            opcao.textContent = animal.nome + " - " + animal.especie;
            pet.appendChild(opcao);
        });

        dadosTutor.hidden = false;

        if (resultado.pets.length === 0) {
            selecaoPet.hidden = true;
            limparPet();
            mostrarMensagem(
                "Tutor encontrado, mas não possui animais com autorização confirmada para esta clínica.",
                "aviso"
            );
            return;
        }

        selecaoPet.hidden = false;
        limparPet();

        mostrarMensagem(
            "Tutor localizado! Selecione um animal autorizado.",
            "sucesso"
        );

    } catch (erro) {
        idTutorAtual = null;
        dadosTutor.hidden = true;
        selecaoPet.hidden = true;
        limparPet();
        mostrarMensagem(erro.message);
    } finally {
        buscarTutor.disabled = false;
        buscarTutor.textContent = "Buscar tutor";
    }
}

pet.addEventListener("change", carregarPet);

async function carregarPet() {
    limparMensagem();
    limparPet();

    if (!pet.value || !idTutorAtual) {
        return;
    }

    try {
        const resultado = await requisitarPHP(
            "buscar_pet",
            {
                id_tutor: idTutorAtual,
                id_pet: pet.value
            }
        );

        const animal = resultado.pet;

        idPetAtual = Number(animal.id_pet);

        document.getElementById("idPet").value = idPetAtual;
        document.getElementById("nomePet").value = animal.nome ?? "";
        document.getElementById("especie").value = animal.especie ?? "";
        document.getElementById("raca").value = animal.raca ?? "";
        document.getElementById("sexo").value = animal.sexo ?? "Não informado";
        document.getElementById("dataNascimento").value = animal.data_nascimento ?? "";
        document.getElementById("peso").value = animal.peso ?? "";
        document.getElementById("cor").value = animal.cor ?? "";
        document.getElementById("porte").value = animal.porte ?? "";
        document.getElementById("microchip").value = animal.microchip ?? "";
        document.getElementById("castrado").value = animal.castrado ?? "0";
        document.getElementById("observacoes").value = animal.observacoes ?? "";

        // ========================================
        // FOTO DO ANIMAL (CONFIGURAÇÃO ORIGINAL)
        // ========================================
        const parametrosFoto = new URLSearchParams({
            tipo: "foto_pet",
            id_pet: idPetAtual
        });

        linkFoto.href = urlVisualizarFoto + "?" + parametrosFoto.toString();
        linkFoto.style.display = "inline-flex";

        if (animal.foto) {
            estadoFoto.textContent = "Foto cadastrada.";
        } else {
            estadoFoto.textContent = "A foto será consultada pelo PHP ao clicar no botão.";
        }

        dadosPet.hidden = false;

        carregarDocumentosNaMesmaPagina(idPetAtual);

        mostrarMensagem(
            "Informações do animal carregadas.",
            "sucesso"
        );

    } catch (erro) {
        limparPet();
        mostrarMensagem(erro.message);
    }
}

async function carregarDocumentosNaMesmaPagina(idPet) {
    try {
        const resposta = await fetch(urlListaDocumentos + "?id_pet=" + idPet, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        });

        const resultado = await resposta.json();

        if (!resultado.sucesso) {
            throw new Error(resultado.mensagem || "Erro ao carregar documentos.");
        }

        containerTabelaDocumentos.innerHTML = "";

        if (resultado.documentos.length === 0) {
            containerTabelaDocumentos.innerHTML = "<p class='texto-suave'>Nenhum documento cadastrado para este animal.</p>";
            secaoListaDocumentos.hidden = false;
            return;
        }

        const tabela = document.createElement("table");
        tabela.className = "tabela-dados";
        tabela.style.width = "100%";
        tabela.style.borderCollapse = "collapse";

        tabela.innerHTML = `
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px;">Documento</th>
                    <th style="padding: 10px;">Pet</th>
                    <th style="padding: 10px;">Tutor</th>
                    <th style="padding: 10px;">Clínica</th>
                    <th style="padding: 10px;">Veterinário</th>
                    <th style="padding: 10px; text-align: center;">Ação</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;

        const tbody = tabela.querySelector("tbody");

        resultado.documentos.forEach(doc => {
            const tr = document.createElement("tr");
            tr.style.borderBottom = "1px solid #eee";

            const urlVisualizacaoSupabase = "../../../../../../../../bd_conexao/php/visualizar_documentos.php?tipo=documento_individual&id_documento=" + doc.id_documento;

            tr.innerHTML = `
                <td style="padding: 10px;"><strong>${doc.nome_documento}</strong><br><small class="texto-suave">${doc.data_cadastro}</small></td>
                <td style="padding: 10px;">${doc.nome_pet}</td>
                <td style="padding: 10px;">${doc.nome_tutor}</td>
                <td style="padding: 10px;">${doc.nome_clinica}</td>
                <td style="padding: 10px;">${doc.nome_veterinario}</td>
                <td style="padding: 10px; text-align: center;">
                    <a href="${urlVisualizacaoSupabase}" target="_blank" rel="noopener noreferrer" class="botao-secundario" style="padding: 6px 12px; font-size: 0.9rem; text-decoration: none; display: inline-block;">
                        Visualizar documento
                    </a>
                </td>
            `;
            tbody.appendChild(tr);
        });

        containerTabelaDocumentos.appendChild(tabela);
        secaoListaDocumentos.hidden = false;

    } catch (erro) {
        console.error(erro);
        containerTabelaDocumentos.innerHTML = `<p class="mensagem erro">Não foi possível carregar os documentos: ${erro.message}</p>`;
        secaoListaDocumentos.hidden = false;
    }
}

formPet.addEventListener("submit", async function (e) {
    e.preventDefault();
    limparMensagem();

    if (!idTutorAtual || !idPetAtual) {
        mostrarMensagem("Selecione um animal antes de salvar.");
        return;
    }

    const dados = {
        id_tutor: idTutorAtual,
        id_pet: idPetAtual,
        nome: document.getElementById("nomePet").value.trim(),
        especie: document.getElementById("especie").value.trim(),
        raca: document.getElementById("raca").value.trim(),
        sexo: document.getElementById("sexo").value,
        data_nascimento: document.getElementById("dataNascimento").value,
        peso: document.getElementById("peso").value,
        cor: document.getElementById("cor").value.trim(),
        porte: document.getElementById("porte").value.trim(),
        microchip: document.getElementById("microchip").value.trim(),
        castrado: document.getElementById("castrado").value,
        observacoes: document.getElementById("observacoes").value.trim()
    };

    if (!dados.nome || !dados.especie) {
        mostrarMensagem("Nome e espécie são obrigatórios.");
        return;
    }

    botaoSalvar.disabled = true;
    botaoSalvar.textContent = "Salvando...";

    try {
        const resultado = await requisitarPHP(
            "atualizar_pet",
            dados
        );

        mostrarMensagem(
            resultado.mensagem,
            "sucesso"
        );

    } catch (erro) {
        mostrarMensagem(erro.message);
    } finally {
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = "Salvar alterações";
    }
});

document.getElementById("abrirCadastroDocumento")
    .addEventListener("click", function () {
        if (!idPetAtual) {
            mostrarMensagem("Selecione um animal primeiro.");
            return;
        }

        areaDocumento.hidden = false;

        areaDocumento.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    });

document.getElementById("fecharCadastroDocumento")
    .addEventListener("click", function () {
        areaDocumento.hidden = true;
    });

arquivo.addEventListener("change", function () {
    nomeArquivo.textContent = this.files.length
        ? "Arquivo selecionado: " + this.files[0].name
        : "Nenhum arquivo selecionado.";
});

formDocumento.addEventListener("submit", async function (e) {
    e.preventDefault();
    limparMensagem();

    if (!idPetAtual) {
        mostrarMensagem("Selecione um animal antes de cadastrar o documento.");
        return;
    }

    if (!arquivo.files.length) {
        mostrarMensagem("Selecione um arquivo.");
        return;
    }

    const formData = new FormData();
    formData.append("id_pet", idPetAtual);
    formData.append(
        "nome_documento",
        document.getElementById("nome_documento").value.trim()
    );
    formData.append("arquivo", arquivo.files[0]);

    botaoCadastrarDocumento.disabled = true;
    botaoCadastrarDocumento.textContent = "Enviando documento...";

    try {
        const resposta = await fetch(urlCadastrarDocumento, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
            cache: "no-store"
        });

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                "Não foi possível cadastrar o documento."
            );
        }

        mostrarMensagem(
            resultado.mensagem,
            "sucesso"
        );

        formDocumento.reset();
        nomeArquivo.textContent = "Nenhum arquivo selecionado.";
        areaDocumento.hidden = true;

        carregarDocumentosNaMesmaPagina(idPetAtual);

    } catch (erro) {
        mostrarMensagem(erro.message);
    } finally {
        botaoCadastrarDocumento.disabled = false;
        botaoCadastrarDocumento.textContent = "Cadastrar documento";
    }
});