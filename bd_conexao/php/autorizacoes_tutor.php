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

// Capturar termo de busca (clínica ou pet)
$termo_busca = trim($_GET["busca"] ?? "");

// PROCESSAR AÇÕES DOS BOTÕES (CONFIRMAR, NEGAR, REVOGAR, RESTAURAR)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_autorizacao = filter_input(INPUT_POST, "id_autorizacao", FILTER_VALIDATE_INT);
    $acao = trim($_POST["acao"] ?? "");

    if ($id_autorizacao && $id_autorizacao > 0) {
        // Buscar status atual e colunas de controle da tabela autorizacoes
        $sql_v = "SELECT status FROM autorizacoes WHERE id_autorizacao = ? AND id_tutor = ? LIMIT 1";
        $stmt_v = $conn->prepare($sql_v);
        $stmt_v->bind_param("ii", $id_autorizacao, $id_tutor);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result()->fetch_assoc();
        $stmt_v->close();

        if ($res_v) {
            $status_atual = $res_v["status"];
            $novo_status = "";
            $confirmado = null;
            $negado = null;
            $revogado = null;

            // Regras de transição:
            // 1. Se espera -> Confirmar (confirmado=1, negado=0, revogado=0, status='confirmado') ou Negar (confirmado=0, negado=1, revogado=0, status='negado')
            // 2. Se confirmada -> Revogar (revogado=1, status='revogado')
            // 3. Se revogada -> Restaurar para confirmada (confirmado=1, revogado=0, status='confirmado')
            if ($acao === "confirmar" && $status_atual === "espera") {
                $novo_status = "confirmado"; 
                $confirmado = 1; $negado = 0; $revogado = 0;
            } elseif ($acao === "negar" && $status_atual === "espera") {
                $novo_status = "negado";
                $confirmado = 0; $negado = 1; $revogado = 0;
            } elseif ($acao === "revogar" && $status_atual === "confirmado") {
                $novo_status = "revogado";
                $revogado = 1;
            } elseif ($acao === "restaurar" && $status_atual === "revogado") {
                $novo_status = "confirmado";
                $confirmado = 1; $revogado = 0;
            }

            if ($novo_status !== "") {
                // Atualiza o status e as flags booleanas correspondentes no banco baseadas no seu dump
                $sql_up = "UPDATE autorizacoes SET status = ?, confirmado = ?, negado = ?, revogado = ? WHERE id_autorizacao = ? AND id_tutor = ?";
                $stmt_up = $conn->prepare($sql_up);
                // Mapeamento de tipos: s (status), i (confirmado), i (negado), i (revogado), i (id_autorizacao), i (id_tutor)
                $stmt_up->bind_param("siiiii", $novo_status, $confirmado, $negado, $revogado, $id_autorizacao, $id_tutor);
                
                if ($stmt_up->execute()) {
                    $mensagem = "Autorização atualizada com sucesso!";
                    $tipo_mensagem = "sucesso";
                } else {
                    $mensagem = "Erro ao atualizar a autorização.";
                    $tipo_mensagem = "erro";
                }
                $stmt_up->close();
            }
        }
    }
}

// BUSCAR AUTORIZAÇÕES (COM FILTRO)
$sql_aut = "
    SELECT 
        a.id_autorizacao,
        a.status,
        a.data_solicitacao,
        cl.nome_clinica,
        p.nome AS nome_pet
    FROM autorizacoes a
    INNER JOIN clinica cl ON a.id_clinica = cl.id_clinica
    INNER JOIN pet p ON a.id_pet = p.id_pet
    WHERE a.id_tutor = ?
";

$params = [$id_tutor];
$types = "i";

if ($termo_busca !== "") {
    $sql_aut .= " AND (cl.nome_clinica LIKE ? OR p.nome LIKE ?)";
    $like = "%" . $termo_busca . "%";
    array_push($params, $like, $like);
    $types .= "ss";
}

$sql_aut .= " ORDER BY a.data_solicitacao DESC";

$stmt_a = $conn->prepare($sql_aut);
if ($stmt_a && !empty($params)) {
    $stmt_a->bind_param($types, ...$params);
}
$stmt_a->execute();
$todas_autorizacoes = $stmt_a->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_a->close();

// Separar em seções por status para exibição isolada
$secoes = [
    'espera' => [],
    'confirmado' => [],
    'revogado' => [],
    'negado' => []
];

foreach ($todas_autorizacoes as $aut) {
    $st = strtolower($aut['status']);
    // Normalização caso o banco traga 'confirmado' ou 'confirmada'
    if ($st === 'confirmada') $st = 'confirmado';
    if ($st === 'revogada') $st = 'revogado';
    if ($st === 'negada') $st = 'negado';

    if (isset($secoes[$st])) {
        $secoes[$st][] = $aut;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Autoriza&ccedil;&otilde;es - SISVET</title>
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

        /* Estilo das Seções */
        .secao-autorizacoes {
            margin-bottom: 30px;
        }
        .secao-titulo {
            font-size: 18px;
            color: var(--azul-escuro);
            border-bottom: 2px solid var(--borda);
            padding-bottom: 8px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-autorizacao {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .card-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-topo h3 {
            margin: 0;
            color: var(--texto);
            font-size: 16px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-espera { background-color: var(--amarelo-bg); color: var(--amarelo-txt); border: 1px solid #FEC84B; }
        .badge-confirmado { background-color: var(--azul-claro); color: var(--azul); border: 1px solid #B2DDFF; }
        .badge-revogado { background-color: #FEF3F2; color: var(--vermelho); border: 1px solid #FECDCA; }
        .badge-negado { background-color: #F8F9FA; color: #343A40; border: 1px solid #CED4DA; }

        .info-grid {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--texto-secundario);
        }
        .info-grid strong { color: var(--texto); }

        .acoes-aut {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 5px;
            padding-top: 10px;
            border-top: 1px dashed var(--borda);
        }
        .btn-acao {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-confirmar { background-color: var(--verde); color: #FFF; }
        .btn-confirmar:hover { background-color: #054F31; }
        
        .btn-negar, .btn-revogar { background-color: var(--vermelho); color: #FFF; }
        .btn-negar:hover, .btn-revogar:hover { background-color: var(--vermelho-hover); }

        .btn-restaurar { background-color: var(--azul); color: #FFF; }
        .btn-restaurar:hover { background-color: var(--azul-escuro); }

        .aviso-negado {
            font-size: 13px;
            color: var(--vermelho);
            font-style: italic;
            background-color: #FEF3F2;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #FECDCA;
            margin-top: 5px;
        }
        .vazio-secao {
            font-size: 13px;
            color: var(--texto-secundario);
            font-style: italic;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cabecalho">
            <div>
                <h1>Minhas Autoriza&ccedil;&otilde;es</h1>
                <p>Controle o acesso das cl&iacute;nicas aos prontu&aacute;rios e dados dos seus pets.</p>
            </div>
            
        </header>

        <!-- Barra de Pesquisa -->
        <form method="GET" action="" class="form-busca">
            <input type="text" name="busca" placeholder="Pesquisar por nome da cl&iacute;nica ou do pet..." value="<?= htmlspecialchars($termo_busca, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if ($termo_busca !== ""): ?>
                <a href="autorizacoes_tutor.php" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <!-- SEÇÃO 1: ESPERA -->
        <div class="secao-autorizacoes">
            <h2 class="secao-titulo">Em Espera</h2>
            <?php if (!empty($secoes['espera'])): ?>
                <?php foreach ($secoes['espera'] as $aut): ?>
                    <div class="card-autorizacao">
                        <div class="card-topo">
                            <h3>Cl&iacute;nica: <strong><?= htmlspecialchars($aut["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></h3>
                            <span class="badge badge-espera">Em Espera</span>
                        </div>
                        <div class="info-grid">
                            <div>Pet: <strong><?= htmlspecialchars($aut["nome_pet"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Solicitado em: <?= date('d/m/Y H:i', strtotime($aut["data_solicitacao"])) ?></div>
                        </div>
                        <div class="acoes-aut">
                            <form action="" method="POST">
                                <input type="hidden" name="id_autorizacao" value="<?= $aut["id_autorizacao"] ?>">
                                <button type="submit" name="acao" value="confirmar" class="btn-acao btn-confirmar">Confirmar</button>
                                <button type="submit" name="acao" value="negar" class="btn-acao btn-negar">Negar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma autoriza&ccedil;&atilde;o em espera.</div>
            <?php endif; ?>
        </div>

        <!-- SEÇÃO 2: CONFIRMADAS -->
        <div class="secao-autorizacoes">
            <h2 class="secao-titulo">Confirmadas</h2>
            <?php if (!empty($secoes['confirmado'])): ?>
                <?php foreach ($secoes['confirmado'] as $aut): ?>
                    <div class="card-autorizacao">
                        <div class="card-topo">
                            <h3>Cl&iacute;nica: <strong><?= htmlspecialchars($aut["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></h3>
                            <span class="badge badge-confirmado">Confirmada</span>
                        </div>
                        <div class="info-grid">
                            <div>Pet: <strong><?= htmlspecialchars($aut["nome_pet"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Solicitado em: <?= date('d/m/Y H:i', strtotime($aut["data_solicitacao"])) ?></div>
                        </div>
                        <div class="acoes-aut">
                            <form action="" method="POST">
                                <input type="hidden" name="id_autorizacao" value="<?= $aut["id_autorizacao"] ?>">
                                <button type="submit" name="acao" value="revogar" class="btn-acao btn-revogar">Revogar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma autoriza&ccedil;&atilde;o confirmada.</div>
            <?php endif; ?>
        </div>

        <!-- SEÇÃO 3: REVOGADAS -->
        <div class="secao-autorizacoes">
            <h2 class="secao-titulo">Revogadas</h2>
            <?php if (!empty($secoes['revogado'])): ?>
                <?php foreach ($secoes['revogado'] as $aut): ?>
                    <div class="card-autorizacao">
                        <div class="card-topo">
                            <h3>Cl&iacute;nica: <strong><?= htmlspecialchars($aut["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></h3>
                            <span class="badge badge-revogado">Revogada</span>
                        </div>
                        <div class="info-grid">
                            <div>Pet: <strong><?= htmlspecialchars($aut["nome_pet"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Solicitado em: <?= date('d/m/Y H:i', strtotime($aut["data_solicitacao"])) ?></div>
                        </div>
                        <div class="acoes-aut">
                            <form action="" method="POST">
                                <input type="hidden" name="id_autorizacao" value="<?= $aut["id_autorizacao"] ?>">
                                <button type="submit" name="acao" value="restaurar" class="btn-acao btn-restaurar">Restaurar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma autoriza&ccedil;&atilde;o revogada.</div>
            <?php endif; ?>
        </div>

        <!-- SEÇÃO 4: NEGADAS -->
        <div class="secao-autorizacoes">
            <h2 class="secao-titulo">Negadas</h2>
            <?php if (!empty($secoes['negado'])): ?>
                <?php foreach ($secoes['negado'] as $aut): ?>
                    <div class="card-autorizacao">
                        <div class="card-topo">
                            <h3>Cl&iacute;nica: <strong><?= htmlspecialchars($aut["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></h3>
                            <span class="badge badge-negado">Negada</span>
                        </div>
                        <div class="info-grid">
                            <div>Pet: <strong><?= htmlspecialchars($aut["nome_pet"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Solicitado em: <?= date('d/m/Y H:i', strtotime($aut["data_solicitacao"])) ?></div>
                        </div>
                        <!-- Conforme regra: se estiver negada, não exibe botões, apenas o aviso -->
                        <div class="aviso-negado">
                            Se quiser autorizar a cl&iacute;nica, deve entrar em contato com ela.
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma autoriza&ccedil;&atilde;o negada.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>