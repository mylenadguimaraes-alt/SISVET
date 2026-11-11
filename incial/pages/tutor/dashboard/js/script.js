document.addEventListener("DOMContentLoaded", function () {
    fetch('../../../../bd_conexao/php/dados_dashbord_tutor.php') 
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("Não autorizado")) {
                const dados = resultado.split("|");
                const nomeCompleto = dados[0];

                const spanNome = document.getElementById('nome-tutor');
                if (spanNome) {
                    spanNome.textContent = nomeCompleto;
                }
            }
        })
        .catch(error => console.error('Erro:', error));
});