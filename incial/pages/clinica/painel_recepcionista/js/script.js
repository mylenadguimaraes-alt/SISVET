document.addEventListener("DOMContentLoaded", function () {
    // Faz a requisição ao ficheiro PHP que criámos na raiz ou pasta de conexões
    fetch('../../../../bd_conexao/php/endpoint_recepcionista.php')
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("Não autorizado")) {
                const dados = resultado.split("|");
                const nomeCompleto = dados[0]; // Nome completo vindo do PHP

                const spanNome = document.getElementById('nome');
                if (spanNome) {
                    spanNome.textContent = nomeCompleto;
                }
            } else {
                console.warn("Sessão da recepcionista não encontrada ou não autorizada.");
            }
        })
        .catch(error => console.error('Erro ao carregar o nome da recepcionista:', error));
});