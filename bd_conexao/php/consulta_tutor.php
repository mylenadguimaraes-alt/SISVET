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

// Capturar o termo de pesquisa (se houver)
$termo_busca = trim($_GET["busca"] ?? "");

// ========================================
// PROCESSAR AÇÃO DOS BOTÕES (CONFIRMAR / CANCELAR)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_consulta = filter_input(INPUT_POST, "id_consulta", FILTER_VALIDATE_INT);
    $acao = trim($_POST["acao"] ?? "");

    if ($id_consulta && $id_consulta > 0) {
        $sql_verifica = "SELECT status FROM consultas WHERE id_consulta = ? AND id_tutor = ? LIMIT 1";
        $stmt_v = $conn->prepare($sql_verifica);
        $stmt_v->bind_param("ii", $id_consulta, $id_tutor);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result()->fetch_assoc();
        $stmt_v->close();

        if ($res_v) {
            $status_atual = $res_v["status"];
            $novo_status = "";

            if ($acao === "confirmar" && $status_atual === "pendente") {
                $novo_status = "confirmada";
            } elseif ($acao === "cancelar" && ($status_atual === "pendente" || $status_atual === "confirmada")) {
                $novo_status = "cancelada";
            }

            if ($novo_status !== "") {
                $sql_update = "UPDATE consultas SET status = ? WHERE id_consulta = ? AND id_tutor = ?";
                $stmt_up = $conn->prepare($sql_update);
                $stmt_up->bind_param("sii", $novo_status, $id_consulta, $id_tutor);
                if ($stmt_up->execute()) {
                    $mensagem = "Status da consulta atualizado com sucesso para: <strong>" . ucfirst($novo_status) . "</strong>.";
                    $tipo_mensagem = "sucesso";
                } else {
                    $mensagem = "Erro ao atualizar o status da consulta.";
                    $tipo_mensagem = "erro";
                }
                $stmt_up->close();
            } else {
                $mensagem = "Ação não permitida para o status atual da consulta.";
                $tipo_mensagem = "erro";
            }
        }
    }
}

// ========================================
// BUSCAR CONSULTAS DO TUTOR (COM FILTRO E ORDENAÇÃO)
// Do mais recente para o mais antigo
// ========================================
$sql_consultas = "
    SELECT 
        c.id_consulta,
        c.data_hora,
        c.motivo,
        c.status,
        c.observacoes,
        cl.nome_clinica,
        p.nome AS nome_pet
    FROM consultas c
    INNER JOIN clinica cl ON c.id_clinica = cl.id_clinica
    INNER JOIN pet p ON c.id_pet = p.id_pet
    WHERE c.id_tutor = ?
";

$params = [$id_tutor];
$types = "i";

// Se o usuário digitou algo na busca, filtramos por nome da clínica ou do pet
if ($termo_busca !== "") {
    $sql_consultas .= " AND (cl.nome_clinica LIKE ? OR p.nome LIKE ?)";
    $like = "%" . $termo_busca . "%";
    array_push($params, $like, $like);
    $types .= "ss";
}

// Ordenação: Do mais recente para o mais antigo
$sql_consultas .= " ORDER BY c.data_hora DESC";

$stmt_c = $conn->prepare($sql_consultas);
if ($stmt_c && !empty($params)) {
    $stmt_c->bind_param($types, ...$params);
}
$stmt_c->execute();
$consultas = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_c->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Consultas - SISVET</title>
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
            --verde: #067647;
            --verde-bg: #ECFDF3;
            --amarelo-bg: #FFFAEB;
            --amarelo-txt: #B54708;
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
            max-width: 900px;
            margin: 0 auto;
        }
        .cabecalho {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cabecalho h1 {
            margin: 0 0 6px;
            color: var(--azul-escuro);
            font-size: 26px;
        }
        .cabecalho p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 14px;
        }
        .btn-voltar {
            padding: 10px 16px;
            background-color: #667085;
            color: #FFF;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-voltar:hover { background-color: #475467; }

        /* Estilo da barra de pesquisa */
        .form-busca {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: var(--branco);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid var(--borda);
        }
        .form-busca input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--borda);
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .form-busca input:focus { border-color: var(--azul); }
        .btn-pesquisar {
            background-color: var(--azul);
            color: var(--branco);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-pesquisar:hover { background-color: var(--azul-escuro); }
        .btn-limpar {
            padding: 10px;
            text-decoration: none;
            color: var(--texto-secundario);
            font-size: 14px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--verde-bg); border: 1px solid #A6F4C5; color: var(--verde); }
        .mensagem-erro { background-color: #FEF3F2; border: 1px solid #FECDCA; color: var(--vermelho); }

        .card-consulta {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .card-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--borda);
            padding-bottom: 10px;
        }
        .card-topo h3 {
            margin: 0;
            color: var(--azul-escuro);
            font-size: 18px;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pendente { background-color: var(--amarelo-bg); color: var(--amarelo-txt); border: 1px solid #FEC84B; }
        .badge-confirmada { background-color: var(--azul-claro); color: var(--azul); border: 1px solid #B2DDFF; }
        .badge-realizada { background-color: var(--verde-bg); color: var(--verde); border: 1px solid #A6F4C5; }
        .badge-cancelada { background-color: #FEF3F2; color: var(--vermelho); border: 1px solid #FECDCA; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            font-size: 14px;
        }
        .info-grid strong { color: var(--texto-secundario); }

        .acoes-consulta {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 5px;
            padding-top: 10px;
            border-top: 1px dashed var(--borda);
        }
        .btn-acao {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-confirmar { background-color: var(--verde); color: #FFF; }
        .btn-confirmar:hover { background-color: #054F31; }
        .btn-cancelar { background-color: var(--vermelho); color: #FFF; }
        .btn-cancelar:hover { background-color: var(--vermelho-hover); }

        .sem-resultados {
            text-align: center;
            background: var(--branco);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--borda);
            color: var(--texto-secundario);
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cabecalho">
            <div>
                <h1>Minhas Consultas</h1>
                <p>Acompanhe e gerencie as consultas agendadas para os seus pets.</p>
            </div>
            
        </header>

        <!-- Barra de Pesquisa por Nome da Clínica ou do Pet -->
        <form method="GET" action="" class="form-busca">
            <input type="text" name="busca" placeholder="Pesquisar por nome da clínica ou do pet..." value="<?= htmlspecialchars($termo_busca, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if ($termo_busca !== ""): ?>
                <a href="consulta_tutor.php" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <?php if (!empty($consultas)): ?>
            <?php foreach ($consultas as $c): ?>
                <?php 
                    $dataObj = new DateTime($c["data_hora"]);
                    $dataFormatada = $dataObj->format('d/m/Y');
                    $horaFormatada = $dataObj->format('H:i');
                ?>
                <div class="card-consulta">
                    <div class="card-topo">
                        <h3>Pet: <?= htmlspecialchars($c["nome_pet"], ENT_QUOTES, "UTF-8") ?></h3>
                        <span class="badge badge-<?= $c["status"] ?>"><?= ucfirst($c["status"]) ?></span>
                    </div>

                    <div class="info-grid">
                        <div><strong>Cl&iacute;nica:</strong> <?= htmlspecialchars($c["nome_clinica"], ENT_QUOTES, "UTF-8") ?></div>
                        <div><strong>Data e Hor&aacute;rio:</strong> <?= $dataFormatada ?> &agrave;s <?= $horaFormatada ?></div>
                        <div><strong>Motivo:</strong> <?= htmlspecialchars($c["motivo"], ENT_QUOTES, "UTF-8") ?></div>
                        <div><strong>Observa&ccedil;&otilde;es:</strong> <?= !empty($c["observacoes"]) ? htmlspecialchars($c["observacoes"], ENT_QUOTES, "UTF-8") : "Nenhuma" ?></div>
                    </div>

                    <?php if ($c["status"] === "pendente" || $c["status"] === "confirmada"): ?>
                        <div class="acoes-consulta">
                            <form action="" method="POST" style="display:inline;">
                                <input type="hidden" name="id_consulta" value="<?= $c["id_consulta"] ?>">
                                
                                <?php if ($c["status"] === "pendente"): ?>
                                    <button type="submit" name="acao" value="confirmar" class="btn-acao btn-confirmar">Confirmar Consulta</button>
                                    <button type="submit" name="acao" value="cancelar" class="btn-acao btn-cancelar">Cancelar Consulta</button>
                                <?php elseif ($c["status"] === "confirmada"): ?>
                                    <button type="submit" name="acao" value="cancelar" class="btn-acao btn-cancelar">Cancelar Consulta</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sem-resultados">Nenhuma consulta encontrada com os crit&eacute;rios informados.</div>
        <?php endif; ?>
    </div>
</body>
</html>