<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verificar se o tutor está logado
if (!isset($_SESSION["id_tutor"])) {
    header("Location: login_tutor.php");
    exit;
}

$id_tutor = (int) $_SESSION["id_tutor"];
$mensagem = "";
$tipo_mensagem = "";

// ========================================
// PROCESSAR ATUALIZAÇÃO DO PERFIL (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome_completo   = trim($_POST["nome_completo"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $cpf_com_mascara = trim($_POST["cpf"] ?? "");
    $telefone        = trim($_POST["telefone"] ?? "");
    $data_nascimento = trim($_POST["data_nascimento"] ?? "");
    $cep             = trim($_POST["cep"] ?? "");
    $endereco        = trim($_POST["endereco"] ?? "");
    $numero          = trim($_POST["numero"] ?? "");
    $complemento     = trim($_POST["complemento"] ?? "");
    $bairro          = trim($_POST["bairro"] ?? "");
    $cidade          = trim($_POST["cidade"] ?? "");
    $estado          = trim($_POST["estado"] ?? "");
    $nova_senha      = trim($_POST["nova_senha"] ?? "");

    // Remove a máscara do CPF, salvando apenas os números
    $cpf = preg_replace('/[^0-9]/', '', $cpf_com_mascara);

    if ($nome_completo === "" || $email === "" || $cpf === "" || $telefone === "" || $data_nascimento === "" || $cep === "" || $endereco === "" || $numero === "" || $cidade === "" || $estado === "") {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipo_mensagem = "erro";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
        $tipo_mensagem = "erro";
    } else {
        // Verificar se o e-mail ou CPF já pertencem a outro tutor
        $sql_dup = "SELECT id_tutor FROM tutor WHERE (email = ? OR cpf = ?) AND id_tutor != ? LIMIT 1";
        $stmt_d = $conn->prepare($sql_dup);
        $stmt_d->bind_param("ssi", $email, $cpf, $id_tutor);
        $stmt_d->execute();
        $res_dup = $stmt_d->get_result();

        if ($res_dup->num_rows > 0) {
            $mensagem = "O e-mail ou CPF informado já está cadastrado por outro usuário.";
            $tipo_mensagem = "erro";
            $stmt_d->close();
        } else {
            $stmt_d->close();

            if ($nova_senha !== "") {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $sql_up = "
                    UPDATE tutor 
                    SET nome_completo = ?, email = ?, cpf = ?, telefone = ?, data_nascimento = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, senha = ?
                    WHERE id_tutor = ?
                ";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("sssssssssssssi", $nome_completo, $email, $cpf, $telefone, $data_nascimento, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $senha_hash, $id_tutor);
            } else {
                $sql_up = "
                    UPDATE tutor 
                    SET nome_completo = ?, email = ?, cpf = ?, telefone = ?, data_nascimento = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?
                    WHERE id_tutor = ?
                ";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("ssssssssssssi", $nome_completo, $email, $cpf, $telefone, $data_nascimento, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $id_tutor);
            }

            if ($stmt_up->execute()) {
                $mensagem = "Perfil atualizado com sucesso!";
                $tipo_mensagem = "sucesso";
            } else {
                $mensagem = "Erro ao atualizar o perfil: " . $stmt_up->error;
                $tipo_mensagem = "erro";
            }
            $stmt_up->close();
        }
    }
}

// ========================================
// BUSCAR DADOS ATUAIS DO TUTOR
// ========================================
$sql_tutor = "SELECT nome_completo, email, data_nascimento, telefone, cep, endereco, numero, complemento, bairro, cidade, estado, cpf FROM tutor WHERE id_tutor = ? LIMIT 1";
$stmt_t = $conn->prepare($sql_tutor);
$stmt_t->bind_param("i", $id_tutor);
$stmt_t->execute();
$tutor = $stmt_t->get_result()->fetch_assoc();
$stmt_t->close();

if (!$tutor) {
    exit("Tutor não encontrado.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - SISVET</title>
    <style>
        :root {
            --azul: #1351B4;
            --azul-escuro: #0B3478;
            --azul-claro: #EAF2FF;
            --fundo: #F5F5F5;
            --branco: #FFFFFF;
            --texto: #202B3C;
            --texto-secundario: #667085;
            --borda: #D9E0EA;
            --vermelho: #D92D20;
            --verde: #067647;
            --verde-bg: #ECFDF3;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px;
            background-color: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: var(--branco);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--borda);
            box-shadow: 0 4px 14px rgba(11, 52, 120, 0.05);
        }
        .cabecalho {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--borda);
            padding-bottom: 15px;
        }
        .cabecalho h1 {
            margin: 0 0 6px;
            color: var(--azul-escuro);
            font-size: 24px;
        }
        .cabecalho p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 14px;
        }
        .btn-voltar {
            padding: 8px 14px;
            background-color: #667085;
            color: #FFF;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-voltar:hover { background-color: #475467; }

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--verde-bg); border: 1px solid #A6F4C5; color: var(--verde); }
        .mensagem-erro { background-color: #FEF3F2; border: 1px solid #FECDCA; color: var(--vermelho); }

        .grade {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .campo-completo {
            grid-column: 1 / -1;
        }
        label {
            font-size: 13px;
            font-weight: 600;
            color: var(--texto);
        }
        .opcional {
            font-weight: normal;
            color: var(--texto-secundario);
        }
        input[type="text"], input[type="email"], input[type="date"], input[type="password"] {
            padding: 10px 12px;
            border: 1px solid var(--borda);
            border-radius: 6px;
            font-size: 14px;
            color: var(--texto);
            background-color: #FCFCFD;
            width: 100%;
            outline: none;
        }
        input:focus {
            outline: 2px solid var(--azul);
            border-color: transparent;
        }

        .secao-titulo {
            font-size: 16px;
            color: var(--azul-escuro);
            margin: 25px 0 15px 0;
            border-bottom: 1px solid var(--borda);
            padding-bottom: 6px;
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
            border-top: 1px solid var(--borda);
            padding-top: 20px;
        }
        .botao-salvar {
            background-color: var(--azul);
            color: #FFF;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .botao-salvar:hover { background-color: var(--azul-escuro); }
    </style>
</head>
<body>
    <main class="container">
        <header class="cabecalho">
            <div>
                <h1>Meu Perfil</h1>
                <p>Visualize e atualize suas informa&ccedil;&otilde;es cadastrais.</p>
            </div>
            
        </header>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <h3 class="secao-titulo" style="margin-top: 0;">Dados Pessoais</h3>
            <div class="grade">
                <div class="campo campo-completo">
                    <label for="nome_completo">Nome Completo *</label>
                    <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($tutor["nome_completo"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="email">E-mail *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($tutor["email"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="cpf">CPF *</label>
                    <?php 
                        $cpf_db = preg_replace('/[^0-9]/', '', $tutor["cpf"] ?? '');
                        $cpf_formatado = strlen($cpf_db) === 11 ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $cpf_db) : ($tutor["cpf"] ?? '');
                    ?>
                    <input type="text" id="cpf" name="cpf" maxlength="14" value="<?= htmlspecialchars($cpf_formatado, ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="telefone">Telefone *</label>
                    <?php 
                        $tel_db = preg_replace('/[^0-9]/', '', $tutor["telefone"] ?? '');
                        $tel_formatado = strlen($tel_db) === 11 ? preg_replace("/(\d{2})(\d{5})(\d{4})/", "($1) $2-$3", $tel_db) : (strlen($tel_db) === 10 ? preg_replace("/(\d{2})(\d{4})(\d{4})/", "($1) $2-$3", $tel_db) : ($tutor["telefone"] ?? ''));
                    ?>
                    <input type="text" id="telefone" name="telefone" maxlength="15" value="<?= htmlspecialchars($tel_formatado, ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="data_nascimento">Data de Nascimento *</label>
                    <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($tutor["data_nascimento"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>
            </div>

            <h3 class="secao-titulo">Endere&ccedil;o</h3>
            <div class="grade">
                <div class="campo">
                    <label for="cep">CEP *</label>
                    <?php 
                        $cep_db = preg_replace('/[^0-9]/', '', $tutor["cep"] ?? '');
                        $cep_formatado = strlen($cep_db) === 8 ? preg_replace("/(\d{5})(\d{3})/", "$1-$2", $cep_db) : ($tutor["cep"] ?? '');
                    ?>
                    <input type="text" id="cep" name="cep" maxlength="9" value="<?= htmlspecialchars($cep_formatado, ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="endereco">Logradouro / Rua *</label>
                    <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($tutor["endereco"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="numero">N&uacute;mero *</label>
                    <input type="text" id="numero" name="numero" value="<?= htmlspecialchars($tutor["numero"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="complemento">Complemento <span class="opcional">(opcional)</span></label>
                    <input type="text" id="complemento" name="complemento" value="<?= htmlspecialchars($tutor["complemento"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                </div>

                <div class="campo">
                    <label for="bairro">Bairro</label>
                    <input type="text" id="bairro" name="bairro" value="<?= htmlspecialchars($tutor["bairro"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                </div>

                <div class="campo">
                    <label for="cidade">Cidade *</label>
                    <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($tutor["cidade"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="estado">Estado (UF) *</label>
                    <input type="text" id="estado" name="estado" maxlength="2" value="<?= htmlspecialchars($tutor["estado"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>
            </div>

            <h3 class="secao-titulo">Seguran&ccedil;a</h3>
            <div class="grade">
                <div class="campo campo-completo">
                    <label for="nova_senha">Nova Senha <span class="opcional">(Deixe em branco caso não queira alterar)</span></label>
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite uma nova senha se desejar">
                </div>
            </div>

            <div class="acoes">
                <button type="submit" class="botao-salvar">Salvar Altera&ccedil;&otilde;es</button>
            </div>
        </form>
    </main>

    <!-- Script JavaScript para aplicar as máscaras -->
    <script>
        function aplicarMascaraCPF(e) {
            let v = e.target.value.replace(/\D/g, "");
            if (v.length > 11) v = v.slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, "$1.$2");
            v = v.replace(/(\d{3})(\d)/, "$1.$2");
            v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
            e.target.value = v;
        }

        function aplicarMascaraTelefone(e) {
            let v = e.target.value.replace(/\D/g, "");
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, "($1) $2-$3");
            } else if (v.length > 5) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3");
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})/, "($1) $2");
            } else {
                v = v.replace(/^(\d*)/, "($1");
            }
            e.target.value = v;
        }

        function aplicarMascaraCEP(e) {
            let v = e.target.value.replace(/\D/g, "");
            if (v.length > 8) v = v.slice(0, 8);
            v = v.replace(/(\d{5})(\d)/, "$1-$2");
            e.target.value = v;
        }

        document.getElementById('cpf').addEventListener('input', aplicarMascaraCPF);
        document.getElementById('telefone').addEventListener('input', aplicarMascaraTelefone);
        document.getElementById('cep').addEventListener('input', aplicarMascaraCEP);
    </script>
</body>
</html>