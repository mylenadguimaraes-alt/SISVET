document.addEventListener("DOMContentLoaded", function () {
    fetch('../../../../bd_conexao/php/dados_dono.php')
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("Não autorizado")) {
                const dados = resultado.split("|");
                const spanNome = document.getElementById('nome_completo');
                if (spanNome) {
                    spanNome.textContent = dados[0]; // Nome completo
                }
            }
        })
        .catch(error => console.error('Erro ao carregar o nome do dono:', error));
});