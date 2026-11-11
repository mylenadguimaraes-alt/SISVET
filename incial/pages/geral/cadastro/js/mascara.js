
document.addEventListener("DOMContentLoaded", function () {

    // CPF: 000.000.000-00
    const cpf = document.querySelector('input[name="cpf"]');

    if (cpf) {
        cpf.addEventListener("input", function () {
            let valor = cpf.value.replace(/\D/g, "").slice(0, 11);

            valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
            valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
            valor = valor.replace(/(\d{3})(\d{1,2})$/, "$1-$2");

            cpf.value = valor;
        });
    }

  
const telefone = document.querySelector('input[name="telefone"]');

if (telefone) {
    telefone.addEventListener("input", function () {
        let numeros = telefone.value.replace(/\D/g, "").slice(0, 11);

        if (numeros.length <= 2) {
            telefone.value = numeros.length
                ? "(" + numeros
                : "";
        } else if (numeros.length <= 6) {
            telefone.value = "(" + numeros.slice(0, 2) + ") " +
                numeros.slice(2);
        } else if (numeros.length <= 10) {
            telefone.value = "(" + numeros.slice(0, 2) + ") " +
                numeros.slice(2, 6) + "-" + numeros.slice(6);
        } else {
            telefone.value = "(" + numeros.slice(0, 2) + ") " +
                numeros.slice(2, 7) + "-" + numeros.slice(7);
        }
    });
}

    // CEP: 00000-000
    const cep = document.querySelector('input[name="cep"]');

    if (cep) {
        cep.addEventListener("input", function () {
            let valor = cep.value.replace(/\D/g, "").slice(0, 8);

            if (valor.length > 5) {
                valor = valor.replace(/^(\d{5})(\d)/, "$1-$2");
            }

            cep.value = valor;
        });
    }

    // Estado: somente duas letras maiúsculas
    const estado = document.querySelector('input[name="estado"]');

    if (estado) {
        estado.addEventListener("input", function () {
            estado.value = estado.value
                .replace(/[^a-zA-Z]/g, "")
                .toUpperCase()
                .slice(0, 2);
        });
    }

});