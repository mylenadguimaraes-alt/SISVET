
document.addEventListener("DOMContentLoaded", function () {

    const formulario = document.querySelector("form");

    const cnpj = document.getElementById("cnpj");
    const telefone = document.getElementById("telefone");
    const cep = document.getElementById("cep");

    const campoArquivo = document.getElementById("documento_licenca");
    const nomeArquivo = document.getElementById("nome-arquivo");

    // =====================================
    // MÁSCARA DE CNPJ
    // 00.000.000/0000-00
    // =====================================

    if (cnpj) {
        cnpj.addEventListener("input", function () {
            let valor = this.value.replace(/\D/g, "").slice(0, 14);

            valor = valor.replace(/^(\d{2})(\d)/, "$1.$2");
            valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
            valor = valor.replace(/\.(\d{3})(\d)/, ".$1/$2");
            valor = valor.replace(/(\d{4})(\d)/, "$1-$2");

            this.value = valor;
        });
    }

    // =====================================
    // MÁSCARA DE TELEFONE
    // (00) 0000-0000 ou (00) 00000-0000
    // =====================================

    if (telefone) {
        telefone.addEventListener("input", function () {
            let valor = this.value.replace(/\D/g, "").slice(0, 11);

            if (valor.length > 10) {
                valor = valor.replace(
                    /^(\d{2})(\d{5})(\d{4})$/,
                    "($1) $2-$3"
                );
            } else if (valor.length > 6) {
                valor = valor.replace(
                    /^(\d{2})(\d{4})(\d{1,4})$/,
                    "($1) $2-$3"
                );
            } else if (valor.length > 2) {
                valor = valor.replace(
                    /^(\d{2})(\d+)$/,
                    "($1) $2"
                );
            }

            this.value = valor;
        });
    }

    // =====================================
    // MÁSCARA DE CEP
    // 00000-000
    // =====================================

    if (cep) {
        cep.addEventListener("input", function () {
            let valor = this.value.replace(/\D/g, "").slice(0, 8);

            if (valor.length > 5) {
                valor = valor.replace(
                    /^(\d{5})(\d{1,3})$/,
                    "$1-$2"
                );
            }

            this.value = valor;
        });
    }

    // =====================================
    // MOSTRAR NOME DO DOCUMENTO
    // =====================================

    if (campoArquivo && nomeArquivo) {
        campoArquivo.addEventListener("change", function () {
            nomeArquivo.textContent = this.files.length > 0
                ? this.files[0].name
                : "Nenhum arquivo selecionado";
        });
    }

    // =====================================
    // CANCELAR CADASTRO
    // =====================================

    window.cancelarCadastro = function () {
        const confirmar = window.confirm(
            "Deseja cancelar o cadastro da clínica? Os dados preenchidos serão perdidos."
        );

        if (confirmar) {
            window.history.back();
        }
    };

    // =====================================
    // VOLTAR AO PAINEL / HISTÓRICO
    // =====================================

    window.voltarPainel = function () {
        window.history.back();
    };

});