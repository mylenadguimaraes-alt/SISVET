document.addEventListener("DOMContentLoaded", function () {

    // ========================================
    // MÁSCARA DE CPF
    // Formato: 000.000.000-00
    // ========================================

    const campoCPF = document.getElementById("cpf_recepcionista");

    if (campoCPF) {
        campoCPF.addEventListener("input", function () {

            let cpf = this.value.replace(/\D/g, "");

            cpf = cpf.substring(0, 11);

            cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2");
            cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2");
            cpf = cpf.replace(/(\d{3})(\d{1,2})$/, "$1-$2");

            this.value = cpf;
        });
    }

    // ========================================
    // MÁSCARA DE CNPJ
    // Formato: 00.000.000/0000-00
    // ========================================

    const campoCNPJ = document.getElementById("cnpj_clinica");

    if (campoCNPJ) {
        campoCNPJ.addEventListener("input", function () {

            let cnpj = this.value.replace(/\D/g, "");

            cnpj = cnpj.substring(0, 14);

            cnpj = cnpj.replace(/^(\d{2})(\d)/, "$1.$2");
            cnpj = cnpj.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
            cnpj = cnpj.replace(/\.(\d{3})(\d)/, ".$1/$2");
            cnpj = cnpj.replace(/(\d{4})(\d)/, "$1-$2");

            this.value = cnpj;
        });
    }

});
