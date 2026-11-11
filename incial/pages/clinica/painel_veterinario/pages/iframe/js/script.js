document.addEventListener("DOMContentLoaded", function () {
  
    fetch('../../../../../../bd_conexao/php/endpoint_veterinario.php') 
        .then(response => {
            if (!response.ok) {
                throw new Error("Erro ao carregar os dados do servidor.");
            }
            return response.text();
        })
        .then(data => {
            // O PHP retorna: Nome Completo | Primeiro Nome | Total Contratadas | Total Demitidas
            const partes = data.split("|");

            if (partes.length >= 4) {
                const nomeCompleto = partes[0];
                const primeiroNome = partes[1];
                const totalContratadas = partes[2];
                const totalDemitidas = partes[3];

                // 1. Atualiza o nome na saudação (usando o primeiro nome ou nome completo)
                const elementoSaudacao = document.getElementById("nome");
                if (elementoSaudacao) {
                    elementoSaudacao.textContent = `${primeiroNome}!`;
                }

                // 2. Atualiza os números nos cartões de resumo
                const spanContratadas = document.getElementById("quantidade-contratadas");
                if (spanContratadas) {
                    spanContratadas.textContent = totalContratadas;
                }

                const spanDemitidas = document.getElementById("quantidade-demitidas");
                if (spanDemitidas) {
                    spanDemitidas.textContent = totalDemitidas;
                }
            }
        })
        .catch(error => {
            console.error("Erro na requisição:", error);
        });
});