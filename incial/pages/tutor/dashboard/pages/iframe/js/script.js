document.addEventListener("DOMContentLoaded", function () {
    fetch('../../../../../../bd_conexao/php/dados_dashbord_tutor.php') // Ajuste o caminho relativo conforme a profundidade do iframe
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("Não autorizado")) {
                const dados = resultado.split("|");
                const primeiroNome = dados[1];
                const totalPets = dados[2];
                const totalConsultas = dados[3];
                const totalAutorizacoes = dados[4];

                // Saudação
                const h1Saudacao = document.getElementById('saudacao-tutor');
                if (h1Saudacao) {
                    h1Saudacao.textContent = `Olá, ${primeiroNome}!`;
                }

                // Cards numéricos
                document.getElementById('quantidade-pets').textContent = totalPets;
                document.getElementById('quantidade-consultas').textContent = totalConsultas;
                document.getElementById('quantidade-autorizacoes').textContent = totalAutorizacoes;
            }
        })
        .catch(error => console.error('Erro:', error));
});