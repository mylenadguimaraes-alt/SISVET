<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";
require_once __DIR__ . "/supabase_config.php"; // Configura��es do Supabase (SUPABASE_URL, SUPABASE_KEY, SUPABASE_BUCKET)

// Garante UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verificar se o tutor está logado
if (!isset($_SESSION["id_tutor"])) {
    header("Location: login_tutor.php");
    exit;
}

$id_tutor = (int) $_SESSION["id_tutor"];
$id_clinica = filter_input(INPUT_GET, "id_clinica", FILTER_VALIDATE_INT);

if (!$id_clinica || $id_clinica <= 0) {
    http_response_code(400);
    exit("Cl�nica inv�lida ou n�o informada.");
}

// Buscar dados da clínica para exibir na tela
$sql_clinica = "SELECT nome_clinica, cidade, estado FROM clinica WHERE id_clinica = ? AND autorizado = 1 LIMIT 1";
$stmt_c = $conn->prepare($sql_clinica);
$stmt_c->bind_param("i", $id_clinica);
$stmt_c->execute();
$clinica = $stmt_c->get_result()->fetch_assoc();
$stmt_c->close();

if (!$clinica) {
    http_response_code(404);
    exit("Clínica não encontrada ou não autorizada.");
}

$erro = "";
$sucesso = false;

// Processar Envio do Formulário (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $motivo = trim($_POST["motivo"] ?? "");
    $descricao_detalhada = trim($_POST["descricao_detalhada"] ?? "");

    if ($motivo === "" || $descricao_detalhada === "") {
        $erro = "Preencha o motivo e a descrição detalhada da denúncia.";
    } else {
        $caminhoEvidenciaBanco = null;

        // Processar upload da evidencia para o Supabase Storage (se houver arquivo)
        if (isset($_FILES["evidencia"]) && $_FILES["evidencia"]["error"] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES["evidencia"]["error"] !== UPLOAD_ERR_OK) {
                $erro = "Erro no envio do arquivo de evid�ncia.";
            } else {
                $tamanhoMaximo = 10 * 1024 * 1024; // 10 MB
                if ($_FILES["evidencia"]["size"] > $tamanhoMaximo) {
                    $erro = "O arquivo de evid�ncia deve ter no m�ximo 10 MB.";
                } else {
                    $tmpName = $_FILES["evidencia"]["tmp_name"];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpName);

                    $tiposPermitidos = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp",
                        "application/pdf" => "pdf"
                    ];

                    if (!isset($tiposPermitidos[$mime])) {
                        $erro = "Formato de arquivo inv�lido. Envie uma imagem (JPG, PNG, WEBP) ou PDF.";
                    } else {
                        $conteudoArquivo = file_get_contents($tmpName);
                        $extensao = $tiposPermitidos[$mime];
                        $nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;
                        $caminhoBucket = "denuncias/" . $id_tutor . "/" . $nomeArquivo;

                        $urlUpload = rtrim(SUPABASE_URL, "/") .
                            "/storage/v1/object/" .
                            rawurlencode(SUPABASE_BUCKET) . "/" .
                            str_replace("%2F", "/", rawurlencode($caminhoBucket));

                        $ch = curl_init($urlUpload);
                        curl_setopt_array($ch, [
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => $conteudoArquivo,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => [
                                "apikey: " . SUPABASE_KEY,
                                "Authorization: Bearer " . SUPABASE_KEY,
                                "Content-Type: application/octet-stream",
                                "x-upsert: false"
                            ],
                            CURLOPT_TIMEOUT => 60
                        ]);

                        $resposta = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode >= 200 && $httpCode < 300) {
                            $caminhoEvidenciaBanco = $caminhoBucket;
                        } else {
                            $erro = "Erro ao enviar evid�ncia para o armazenamento externo (HTTP: $httpCode).";
                        }
                    }
                }
            }
        }

        if ($erro === "") {
            $sql_insert = "
                INSERT INTO denuncias (id_tutor, id_clinica, motivo, descricao_detalhada, evidencia, status)
                VALUES (?, ?, ?, ?, ?, 'pendente')
            ";
            $stmt_ins = $conn->prepare($sql_insert);
            if ($stmt_ins) {
                $stmt_ins->bind_param("iisss", $id_tutor, $id_clinica, $motivo, $descricao_detalhada, $caminhoEvidenciaBanco);
                if ($stmt_ins->execute()) {
                    $sucesso = true;
                } else {
                    $erro = "Erro ao registrar a den�ncia no banco de dados: " . $stmt_ins->error;
                }
                $stmt_ins->close();
            } else {
                $erro = "Erro interno ao preparar cadastro da den�ncia.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denunciar Cl&iacute;nica - SISVET</title>
    
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
            --vermelho-hover: #B42318;
            --verde-sucesso: #067647;
            --fundo-sucesso: #ECFDF3;
            --borda-sucesso: #A6F4C5;
            --fundo-erro: #FEF3F2;
            --borda-erro: #FECDCA;
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
        .cabecalho h1 {
            margin: 0 0 8px;
            color: var(--vermelho);
            font-size: 24px;
        }
        .cabecalho p {
            margin: 0 0 20px;
            color: var(--texto-secundario);
            font-size: 14px;
        }
        .info-clinica {
            background-color: var(--fundo);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            border-left: 4px solid var(--azul);
        }
        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--fundo-sucesso); border: 1px solid var(--borda-sucesso); color: var(--verde-sucesso); }
        .mensagem-erro { background-color: var(--fundo-erro); border: 1px solid var(--borda-erro); color: var(--vermelho); }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
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
        input[type="text"], select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--borda);
            border-radius: 6px;
            font-size: 14px;
            color: var(--texto);
            background-color: #FCFCFD;
            width: 100%;
            outline: none;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid var(--azul);
            border-color: transparent;
        }
        textarea { resize: vertical; min-height: 120px; }
        .acoes {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            border-top: 1px solid var(--borda);
            padding-top: 20px;
        }
        .botao {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s;
        }
        .botao-voltar { background-color: #667085; color: #FFFFFF; }
        .botao-voltar:hover { background-color: #475467; }
        .botao-enviar { background-color: var(--vermelho); color: #FFFFFF; }
        .botao-enviar:hover { background-color: var(--vermelho-hover); }
    </style>
</head>
<body>
    <main class="container">
        <header class="cabecalho">
            <h1>Formul&aacute;rio de Den&uacute;ncia de Cl&iacute;nica</h1>
            <p>Relate irregularidades ou problemas graves encontrados na unidade abaixo.</p>
        </header>

        <div class="info-clinica">
            <strong>Cl&iacute;nica Alvo:</strong> <?= htmlspecialchars($clinica["nome_clinica"], ENT_QUOTES, "UTF-8") ?><br>
            <strong>Localiza&ccedil;&atilde;o:</strong> <?= htmlspecialchars($clinica["cidade"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($clinica["estado"], ENT_QUOTES, "UTF-8") ?>
        </div>

        <?php if ($sucesso): ?>
            <div class="mensagem mensagem-sucesso">
                Den&uacute;ncia enviada com sucesso! Nossa equipe an&aacute;lise o caso em at&eacute; 48 horas.
                <br><br>
                <a href="clinicas_tutor.php" style="color: var(--verde-sucesso); font-weight: bold; text-decoration: underline;">Voltar para a lista de cl&iacute;nicas</a>
            </div>
        <?php else: ?>
            <?php if ($erro !== ""): ?>
                <div class="mensagem mensagem-erro"><?= htmlspecialchars($erro, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>

            <form id="formDenuncia" action="" method="POST" enctype="multipart/form-data">
                <div class="campo">
                    <label for="motivo">Motivo da Den&uacute;ncia *</label>
                    <select id="motivo" name="motivo" required>
                        <option value="">Selecione o motivo principal</option>
                        <option value="Atendimento inadequado">Atendimento inadequado / neglig&ecirc;ncia</option>
                        <option value="Cobran�a abusiva">Cobran&ccedil;a abusiva / indevida</option>
                        <option value="Condi��es de higiene prec�rias">Condi&ccedil;&otilde;es de higiene prec&aacute;rias</option>
                        <option value="Falta de alvar� ou licen�a">Falta de alvar&aacute; ou licen&ccedil;a de funcionamento</option>
                        <option value="Outros">Outros motivos</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="descricao_detalhada">Descri&ccedil;&atilde;o Detalhada *</label>
                    <textarea id="descricao_detalhada" name="descricao_detalhada" placeholder="Descreva detalhadamente o ocorrido, datas, nomes e fatos relevantes..." required></textarea>
                </div>

                <div class="campo">
                    <label for="evidencia">Evid&ecirc;ncia (Foto, Print ou PDF) <span class="opcional">(opcional)</span></label>
                    <input type="file" id="evidencia" name="evidencia" accept="image/*,application/pdf">
                    <small style="color: var(--texto-secundario); font-size: 12px; margin-top: 4px;">Tamanho m&aacute;ximo de 10 MB.</small>
                </div>

                <div class="acoes">
                    <a href="clinicas_tutor.php" class="botao botao-voltar">Voltar</a>
                    <button type="submit" id="botaoEnviar" class="botao botao-enviar">Enviar Den&uacute;ncia</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <!-- Script para travar o botão de envio no duplo clique -->
    <script>
        const form = document.getElementById('formDenuncia');
        if (form) {
            form.addEventListener('submit', function(event) {
                const botao = document.getElementById('botaoEnviar');
                if (form.checkValidity()) {
                    botao.disabled = true;
                    botao.textContent = 'Enviando...';
                    botao.style.opacity = '0.7';
                    botao.style.cursor = 'not-allowed';
                }
            });
        }
    </script>
</body>
</html>