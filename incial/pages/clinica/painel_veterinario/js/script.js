document.addEventListener("DOMContentLoaded", function () {
    // Faz a requisição ao ficheiro PHP através do caminho absoluto do servidor
    fetch('../../../../bd_conexao/php/endpoint_veterinario.php')
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("Não autorizado")) {
                const dados = resultado.split("|");
                const nomeCompleto = dados[0]; // Nome completo vindo do PHP

                // Procura o elemento com o id="nome" no cabeçalho ou perfil
                const spanNome = document.getElementById('nome');
                if (spanNome) {
                    spanNome.textContent = nomeCompleto;
                }
            } else {
                console.warn("Sessão do veterinário não encontrada ou não autorizada.");
            }
        })
        .catch(error => console.error('Erro ao carregar o nome do veterinário:', error));
});