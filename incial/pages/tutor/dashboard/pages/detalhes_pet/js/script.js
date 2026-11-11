document.addEventListener("DOMContentLoaded", () => {

    // ========================================
    // ELEMENTOS DA PÁGINA
    // ========================================

    const parametros = new URLSearchParams(window.location.search);
    const idPet = parametros.get("id_pet");

    const campos = [
        "nome",
        "especie",
        "raca",
        "sexo",
        "data_nascimento",
        "peso",
        "cor",
        "porte",
        "microchip",
        "observacoes"
    ];

    const formulario = document.getElementById("formPet");
    const botaoEditar = document.getElementById("btnEditar");
    const botaoCancelar = document.getElementById("btnCancelar");
    const botaoSalvar = document.getElementById("btnSalvar");
    const botaoFoto = document.getElementById("btnFoto");
    const campoCastrado = document.getElementById("castrado");

    const mensagem = document.getElementById("mensagem");

    const urlPHP = "../../../../../../bd_conexao/php/detalhes_pet.php";

    let dadosOriginais = null;
    let modoEdicao = false;

    // ========================================
    // VERIFICAR ID
    // ========================================

    if (!idPet || isNaN(Number(idPet))) {
        mostrarMensagem("Identificador do pet inválido.", "erro");
        return;
    }

    // ========================================
    // MOSTRAR MENSAGEM
    // ========================================

    function mostrarMensagem(texto, tipo) {

        if (!mensagem) {
            alert(texto);
            return;
        }

        mensagem.textContent = texto;
        mensagem.className = "mensagem mensagem-" + tipo;
        mensagem.style.display = "block";

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    }

    // ========================================
    // CARREGAR DADOS DO PET
    // ========================================

    async function carregarPet() {

        try {

            const resposta = await fetch(
                `${urlPHP}?id_pet=${encodeURIComponent(idPet)}`
            );

            const resultado = await resposta.json();

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem || "Não foi possível carregar o pet."
                );
            }

            // Os dados estão dentro de resultado.dados
            const pet = resultado.dados;

            if (!pet || typeof pet !== "object") {
                throw new Error("O PHP não retornou os dados do pet.");
            }

            dadosOriginais = { ...pet };

            preencherCampos(pet);

            controlarBotaoFoto(pet.tem_foto);

        } catch (erro) {

            console.error("Erro ao carregar pet:", erro);

            mostrarMensagem(
                erro.message || "Erro ao carregar os dados do pet.",
                "erro"
            );

        }
    }

    // ========================================
    // PREENCHER CAMPOS
    // ========================================

    function preencherCampos(pet) {

        campos.forEach(nome => {

            const campo = document.getElementById(nome);

            if (campo) {
                campo.value = pet[nome] ?? "";
            }

        });

        if (campoCastrado) {
            campoCastrado.checked = Number(pet.castrado) === 1;
        }

        const nomePet = document.getElementById("nomePet");
        const especiePet = document.getElementById("especiePet");

        if (nomePet) {
            nomePet.textContent = pet.nome || "";
        }

        if (especiePet) {
            especiePet.textContent = pet.especie || "";
        }

        const dataCadastro = document.getElementById("dataCadastro");

        if (dataCadastro) {
            dataCadastro.textContent = pet.data_cadastro
                ? formatarData(pet.data_cadastro)
                : "Não informado";
        }

    }

    // ========================================
    // FORMATAR DATA
    // ========================================

    function formatarData(data) {

        const partes = data.split(" ")[0].split("-");

        if (partes.length !== 3) {
            return data;
        }

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    // ========================================
    // CONTROLAR VISUALIZAÇÃO DA FOTO
    // ========================================

    function controlarBotaoFoto(temFoto) {

        if (botaoFoto) {
            botaoFoto.style.display = temFoto ? "inline-block" : "none";
        }

    }

    // ========================================
    // ATIVAR EDIÇÃO
    // ========================================

    function ativarEdicao() {

        modoEdicao = true;

        campos.forEach(nome => {

            const campo = document.getElementById(nome);

            if (campo) {
                campo.disabled = false;
            }

        });

        if (campoCastrado) {
            campoCastrado.disabled = false;
        }

        if (botaoEditar) {
            botaoEditar.style.display = "none";
        }

        if (botaoCancelar) {
            botaoCancelar.style.display = "inline-block";
        }

        if (botaoSalvar) {
            botaoSalvar.style.display = "inline-block";
        }

    }

    // ========================================
    // CANCELAR EDIÇÃO
    // ========================================

    function cancelarEdicao() {

        if (dadosOriginais) {
            preencherCampos(dadosOriginais);
        }

        modoEdicao = false;

        campos.forEach(nome => {

            const campo = document.getElementById(nome);

            if (campo) {
                campo.disabled = true;
            }

        });

        if (campoCastrado) {
            campoCastrado.disabled = true;
        }

        if (botaoEditar) {
            botaoEditar.style.display = "inline-block";
        }

        if (botaoCancelar) {
            botaoCancelar.style.display = "none";
        }

        if (botaoSalvar) {
            botaoSalvar.style.display = "none";
        }

    }

    // ========================================
    // SALVAR ALTERAÇÕES
    // ========================================

    async function salvarAlteracoes(evento) {

        evento.preventDefault();

        if (!modoEdicao) {
            return;
        }

        const dados = {
            id_pet: Number(idPet)
        };

        campos.forEach(nome => {

            const campo = document.getElementById(nome);

            dados[nome] = campo ? campo.value.trim() : "";

        });

        dados.castrado = campoCastrado && campoCastrado.checked ? 1 : 0;

        if (!dados.nome || !dados.especie) {
            mostrarMensagem(
                "Preencha o nome e a espécie do pet.",
                "erro"
            );
            return;
        }

        try {

            if (botaoSalvar) {
                botaoSalvar.disabled = true;
                botaoSalvar.textContent = "Salvando...";
            }

            const resposta = await fetch(urlPHP, {

                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify(dados)

            });

            const resultado = await resposta.json();

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem || "Não foi possível salvar as alterações."
                );
            }

            dadosOriginais = { ...resultado.dados };

            preencherCampos(resultado.dados);

            modoEdicao = false;

            campos.forEach(nome => {
                const campo = document.getElementById(nome);

                if (campo) {
                    campo.disabled = true;
                }
            });

            if (campoCastrado) {
                campoCastrado.disabled = true;
            }

            if (botaoEditar) {
                botaoEditar.style.display = "inline-block";
            }

            if (botaoCancelar) {
                botaoCancelar.style.display = "none";
            }

            if (botaoSalvar) {
                botaoSalvar.style.display = "none";
            }

            mostrarMensagem(resultado.mensagem, "sucesso");

        } catch (erro) {

            console.error("Erro ao salvar:", erro);

            mostrarMensagem(
                erro.message || "Erro ao salvar as alterações.",
                "erro"
            );

        } finally {

            if (botaoSalvar) {
                botaoSalvar.disabled = false;
                botaoSalvar.textContent = "Salvar alterações";
            }

        }

    }

    // ========================================
    // VISUALIZAR FOTO
    // ========================================

    if (botaoFoto) {

        botaoFoto.addEventListener("click", () => {

            const urlVisualizar =
                "../../../../../../../../bd_conexao/php/visualizar_documentos.php";

            window.location.href =
                `${urlVisualizar}?tipo=foto_pet&id_pet=${encodeURIComponent(idPet)}`;

        });

    }

    // ========================================
    // EVENTOS DOS BOTÕES
    // ========================================

    if (botaoEditar) {
        botaoEditar.addEventListener("click", ativarEdicao);
    }

    if (botaoCancelar) {
        botaoCancelar.addEventListener("click", cancelarEdicao);
    }

    if (formulario) {
        formulario.addEventListener("submit", salvarAlteracoes);
    }

    // ========================================
    // INICIAR PÁGINA
    // ========================================

    carregarPet();

});