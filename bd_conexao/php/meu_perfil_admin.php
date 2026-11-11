<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verificar se o administrador está logado
if (!isset($_SESSION["id_administrador"]) && !isset($_SESSION["id_admin"])) {
    header("Location: login_admin.php");
    exit;
}

$id_admin = $_SESSION["id_administrador"] ?? $_SESSION["id_admin"];
$mensagem = "";
$tipo_mensagem = "";

// ========================================
// PROCESSAR ATUALIZAÇÃO DO PERFIL (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome_completo = trim($_POST["nome_completo"] ?? "");
    $email         = trim($_POST["email"] ?? "");
    $nova_senha    = trim($_POST["nova_senha"] ?? "");

    if ($nome_completo === "" || $email === "") {
        $mensagem = "O nome completo e o e-mail são obrigatórios.";
        $tipo_mensagem = "erro";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
        $tipo_mensagem = "erro";
    } else {
        // Verificar se o e-mail já pertence a outro administrador
        $sql_dup = "SELECT id_administrador FROM administrador WHERE email = ? AND id_administrador != ? LIMIT 1";
        $stmt_d = $conn->prepare($sql_dup);
        $stmt_d->bind_param("si", $email, $id_admin);
        $stmt_d->execute();
        $res_dup = $stmt_d->get_result();

        if ($res_dup->num_rows > 0) {
            $mensagem = "O e-mail informado já está em uso por outro administrador.";
            $tipo_mensagem = "erro";
            $stmt_d->close();
        } else {
            $stmt_d->close();

            // Atualiza com ou sem nova senha, alterando também o nome completo
            if ($nova_senha !== "") {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $sql_up = "UPDATE administrador SET nome_completo = ?, email = ?, senha = ? WHERE id_administrador = ?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("sssi", $nome_completo, $email, $senha_hash, $id_admin);
            } else {
                $sql_up = "UPDATE administrador SET nome_completo = ?, email = ? WHERE id_administrador = ?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("ssi", $nome_completo, $email, $id_admin);
            }

            if ($stmt_up->execute()) {
                $mensagem = "Dados atualizados com sucesso!";
                $tipo_mensagem = "sucesso";
            } else {
                $mensagem = "Erro ao atualizar os dados: " . $stmt_up->error;
                $tipo_mensagem = "erro";
            }
            $stmt_up->close();
        }
    }
}

// ========================================
// BUSCAR DADOS ATUAIS DO ADMINISTRADOR
// ========================================
$sql_admin = "SELECT nome_completo, email, data_cadastro FROM administrador WHERE id_administrador = ? LIMIT 1";
$stmt_a = $conn->prepare($sql_admin);
$stmt_a->bind_param("i", $id_admin);
$stmt_a->execute();
$admin = $stmt_a->get_result()->fetch_assoc();
$stmt_a->close();

if (!$admin) {
    exit("Administrador não encontrado.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Administrador - SISVET</title>
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
            max-width: 700px;
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

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--verde-bg); border: 1px solid #A6F4C5; color: var(--verde); }
        .mensagem-erro { background-color: #FEF3F2; border: 1px solid #FECDCA; color: var(--vermelho); }

        .grade {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 20px;
        }
        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
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
        input[type="text"], input[type="email"], input[type="password"] {
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
        input:disabled {
            background-color: #E9ECEF;
            color: #6C757D;
            cursor: not-allowed;
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
                <h1>Dados do Administrador</h1>
                <p>Visualize e atualize suas informa&ccedil;&otilde;es cadastrais e de acesso.</p>
            </div>
           
        </header>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <h3 class="secao-titulo" style="margin-top: 0;">Informa&ccedil;&otilde;es Pessoais e de Contato</h3>
            <div class="grade">
                <div class="campo">
                    <label for="nome_completo">Nome Completo *</label>
                    <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($admin["nome_completo"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="email">E-mail de Acesso *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin["email"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>

                <div class="campo">
                    <label for="data_cadastro">Data de Cadastro <span class="opcional">(Inalterável)</span></label>
                    <input type="text" id="data_cadastro" value="<?= date('d/m/Y H:i', strtotime($admin["data_cadastro"])) ?>" disabled>
                </div>
            </div>

            <h3 class="secao-titulo">Seguran&ccedil;a</h3>
            <div class="grade">
                <div class="campo">
                    <label for="nova_senha">Nova Senha <span class="opcional">(Deixe em branco caso não queira alterar)</span></label>
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite uma nova senha se desejar">
                </div>
            </div>

            <div class="acoes">
                <button type="submit" class="botao-salvar">Salvar Altera&ccedil;&otilde;es</button>
            </div>
        </form>
    </main>
</body>
</html>