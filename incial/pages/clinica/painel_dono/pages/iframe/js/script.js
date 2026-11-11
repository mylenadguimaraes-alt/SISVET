document.addEventListener("DOMContentLoaded", function () {
    fetch('/sisvet/bd_conexao/php/dados_dono.php')
        .then(response => response.text())
        .then(resultado => {
            if (!resultado.startsWith("Erro") && !resultado.includes("N�o autorizado")) {
                const dados = resultado.split("|");
                const primeiroNome = dados[1];
                const totalEspera = dados[2];
                const totalAutorizadas = dados[3];
                const totalSuspensas = dados[4];

                // Atualiza sauda��o
                const h1Saudacao = document.getElementById('saudacao-dono');
                if (h1Saudacao) {
                    h1Saudacao.textContent = `Olá, ${primeiroNome}!`; 
                }

                // Atualiza os contadores num�ricos dos cards
                const elEspera = document.getElementById('quantidade-espera');
                const elAutorizadas = document.getElementById('quantidade-autorizacoes'); // ou id correspondente
                const elSuspensas = document.getElementById('quantidade-suspensas');

                if (elEspera) elEspera.textContent = totalEspera;

                const elAuth = document.getElementById('quantidade-autorizadas');
                if (elAuth) elAuth.textContent = totalAutorizadas;

                if (elSuspensas) elSuspensas.textContent = totalSuspensas;
            }
        })
        .catch(error => console.error('Erro ao carregar os dados da p�gina inicial:', error));
});