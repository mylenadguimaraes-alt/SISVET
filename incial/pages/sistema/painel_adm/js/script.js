// Arquivo: js/nome_admin.js
document.addEventListener("DOMContentLoaded", function () {
    fetch("../../../../bd_conexao/php/dados_admin.php")
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                const spanNome = document.getElementById("nome");
                if (spanNome) {
                    spanNome.textContent = data.nome;
                }
            }
        })
        .catch(error => console.error("Erro ao carregar o nome do administrador:", error));
});