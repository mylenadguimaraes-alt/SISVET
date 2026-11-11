document.addEventListener("DOMContentLoaded", function () {
    fetch('../../../../../../bd_conexao/php/endpoint_recepcionista.php') 
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
                const primeiroNome = partes[1];
                const totalContratadas = partes[2];
                const totalDemitidas = partes[3];

                // 1. Atualiza o nome dentro do span com id="nome" na saudação
                const elementoNome = document.getElementById("nome");
                if (elementoNome) {
                    elementoNome.textContent = primeiroNome;
                }

                // 2. Atualiza o número de clínicas contratadas da recepcionista
                const spanContratadas = document.getElementById("quantidade-contratadas-rec");
                if (spanContratadas) {
                    spanContratadas.textContent = totalContratadas;
                }

                // 3. Atualiza o número de clínicas demitidas da recepcionista
                const spanDemitidas = document.getElementById("quantidade-demitidas-rec");
                if (spanDemitidas) {
                    spanDemitidas.textContent = totalDemitidas;
                }
            }
        })
        .catch(error => {
            console.error("Erro na requisição:", error);
        });
});