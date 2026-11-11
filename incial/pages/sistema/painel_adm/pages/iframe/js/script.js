// Arquivo: js/numeros_admin.js
document.addEventListener("DOMContentLoaded", function () {
    fetch("../../../../../../bd_conexao/php/dados_admin.php")
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                // Atualiza o nome caso necessário
                const spanNome = document.getElementById("nome");
                if (spanNome) {
                    spanNome.textContent = data.nome;
                }

                // Preenche os cartões de resumo com base nos IDs do HTML do painel
                const elAutorizadas = document.getElementById("quantidade-autorizadas");
                const elEspera = document.getElementById("quantidade-espera");
                const elSuspensas = document.getElementById("quantidade-suspensas");
                const elDenuncias = document.getElementById("quantidade-denuncias");

                if (elAutorizadas) elAutorizadas.textContent = data.autorizadas;
                if (elEspera) elEspera.textContent = data.espera;
                if (elSuspensas) elSuspensas.textContent = data.suspensas;
                if (elDenuncias) elDenuncias.textContent = data.denuncias;
            }
        })
        .catch(error => console.error("Erro ao carregar os dados numéricos do painel:", error));
});