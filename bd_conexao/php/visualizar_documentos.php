<?php
session_start();

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/supabase_documento.php";

$tipo = $_GET["tipo"] ?? "";
$caminho = null;

switch ($tipo) {

    // ========================================
    // DOCUMENTO INDIVIDUAL DA TABELA DOCUMENTOS
    // ========================================
    case "documento_individual":
        $idDocumento = filter_input(INPUT_GET, "id_documento", FILTER_VALIDATE_INT);

        if (!$idDocumento || $idDocumento <= 0) {
            http_response_code(400);
            exit("ID do documento inválido.");
        }

        $sql = "
            SELECT caminho_documento
            FROM documentos
            WHERE id_documento = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            exit("Não foi possível consultar o documento.");
        }

        $stmt->bind_param("i", $idDocumento);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($registro) {
            $caminho = $registro["caminho_documento"];
        }
        break;

    // Documento da recepcionista
    case "recepcionista":
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$id) {
            $idRecepcionistaLogada = $_SESSION["id"] ?? $_SESSION["id_recepcionista"] ?? null;
            if (empty($idRecepcionistaLogada)) {
                http_response_code(401);
                exit("Você precisa estar logado para visualizar documentos.");
            }
            $sql = "SELECT caminho_documento FROM recepcionista WHERE id_recepcionista = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $idRecepcionistaLogada);
            $stmt->execute();
            $registro = $stmt->get_result()->fetch_assoc();
            if ($registro) { $caminho = $registro["caminho_documento"]; }
            $stmt->close();
        } else {
            if (empty($_SESSION["id_dono"])) {
                http_response_code(401);
                exit("Você precisa estar logado como dono para visualizar este documento.");
            }
            $idDono = (int) $_SESSION["id_dono"];
            $sql = "
                SELECT r.caminho_documento
                FROM contrato_recepcionista cr
                INNER JOIN recepcionista r ON r.id_recepcionista = cr.id_recepcionista
                INNER JOIN clinica c ON c.id_clinica = cr.id_clinica
                WHERE cr.id_contrato = ? AND c.id_dono = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $idDono);
            $stmt->execute();
            $registro = $stmt->get_result()->fetch_assoc();
            if ($registro) { $caminho = $registro["caminho_documento"]; }
            $stmt->close();
        }
        break;

    // Documento de verificação do veterinário
    case "veterinario":
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$id) {
            $idVeterinarioLogado = $_SESSION["id"] ?? $_SESSION["id_veterinario"] ?? null;
            if (empty($idVeterinarioLogado)) {
                http_response_code(401);
                exit("Você precisa estar logado para visualizar documentos.");
            }
            $sql = "SELECT caminho_documento_verificacao FROM veterinarios WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $idVeterinarioLogado);
            $stmt->execute();
            $registro = $stmt->get_result()->fetch_assoc();
            if ($registro) { $caminho = $registro["caminho_documento_verificacao"]; }
            $stmt->close();
        } else {
            if (empty($_SESSION["id_dono"])) {
                http_response_code(401);
                exit("Você precisa estar logado para visualizar documentos.");
            }
            $idDono = (int) $_SESSION["id_dono"];
            $sql = "
                SELECT v.caminho_documento_verificacao
                FROM contrato_veterinario cv
                INNER JOIN veterinarios v ON v.id = cv.id_veterinario
                INNER JOIN clinica c ON c.id_clinica = cv.id_clinica
                WHERE cv.id_contrato = ? AND c.id_dono = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $idDono);
            $stmt->execute();
            $registro = $stmt->get_result()->fetch_assoc();
            if ($registro) { $caminho = $registro["caminho_documento_verificacao"]; }
            $stmt->close();
        }
        break;

    // Documento de identificação do dono
    case "dono":
        if (empty($_SESSION["id_dono"])) {
            http_response_code(401);
            exit("Você precisa estar logado para visualizar documentos.");
        }
        $idDono = (int) $_SESSION["id_dono"];
        $sql = "SELECT documento_identificacao FROM dono_clinica WHERE id_dono = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idDono);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        if ($registro) { $caminho = $registro["documento_identificacao"]; }
        $stmt->close();
        break;

    // Documento de licença da clínica
    case "clinica":
        if (empty($_SESSION["id_dono"])) {
            http_response_code(401);
            exit("Você precisa estar logado para visualizar documentos.");
        }
        $idDono = (int) $_SESSION["id_dono"];
        $idClinica = filter_input(INPUT_GET, "id_clinica", FILTER_VALIDATE_INT);
        if (!$idClinica || $idClinica <= 0) {
            http_response_code(400);
            exit("ID da clínica inválido.");
        }
        $sql = "SELECT documento_licenca FROM clinica WHERE id_clinica = ? AND id_dono = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $idClinica, $idDono);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        if ($registro) { $caminho = $registro["documento_licenca"]; }
        $stmt->close();
        break;

    // ========================================
    // FOTO DO PET
    // Tutor e veterinário autorizado
    // ========================================

    case "foto_pet":

        $idPet = filter_input(INPUT_GET, "id_pet", FILTER_VALIDATE_INT);

        if (!$idPet || $idPet <= 0) {
            http_response_code(400);
            exit("ID do animal inválido.");
        }

        // ------------------------------------
        // TUTOR
        // ------------------------------------

        $idTutor = $_SESSION["id_tutor"] ?? null;

        if (!empty($idTutor)) {

            $sql = "
                SELECT foto
                FROM pet
                WHERE id_pet = ?
                  AND id_tutor = ?
                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $idPet, $idTutor);

        }

        // ------------------------------------
        // VETERINÁRIO
        // ------------------------------------

        else {

            $idVeterinario = $_SESSION["id"]
                ?? $_SESSION["id_veterinario"]
                ?? null;

            $idClinica = $_SESSION["id_clinica_ativa"] ?? null;

            if (empty($idVeterinario) || empty($idClinica)) {
                http_response_code(401);
                exit("Sessão do veterinário não encontrada.");
            }

            $sql = "
                SELECT p.foto
                FROM pet p

                INNER JOIN autorizacoes a
                    ON a.id_pet = p.id_pet

                INNER JOIN contrato_veterinario cv
                    ON cv.id_clinica = a.id_clinica

                WHERE p.id_pet = ?
                  AND a.id_clinica = ?
                  AND a.status = 'confirmado'
                  AND cv.id_veterinario = ?
                  AND cv.status = 'contratado'
                  AND (
                      cv.data_fim IS NULL
                      OR cv.data_fim >= CURDATE()
                  )

                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iii",
                $idPet,
                $idClinica,
                $idVeterinario
            );
        }

        if (!$stmt) {
            http_response_code(500);
            exit("Erro ao consultar foto do animal.");
        }

        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro || empty($registro["foto"])) {
            http_response_code(404);
            exit("Foto não encontrada ou acesso não autorizado.");
        }

        $caminho = $registro["foto"];

        break;

        // Documento de licença da clínica para Administrador
    case "adm_clinica":
        if (empty($_SESSION["id_administrador"]) && empty($_SESSION["id_admin"])) {
            http_response_code(401);
            exit("Acesso negado. Apenas administradores do sistema podem visualizar este documento.");
        }

        $idClinica = filter_input(INPUT_GET, "id_clinica", FILTER_VALIDATE_INT);
        if (!$idClinica || $idClinica <= 0) {
            http_response_code(400);
            exit("ID da clínica inválido.");
        }

        $sql = "SELECT documento_licenca FROM clinica WHERE id_clinica = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idClinica);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($registro) {
            $caminho = $registro["documento_licenca"];
        }
        break;

     
    // EVIDÊNCIA DE DENÚNCIA (ADMIN)
    case "evidencia":
        if (empty($_SESSION["id_administrador"]) && empty($_SESSION["id_admin"])) {
            http_response_code(401);
            exit("Acesso negado. Apenas administradores do sistema podem visualizar evidências.");
        }

        $idDenuncia = filter_input(INPUT_GET, "id_denuncia", FILTER_VALIDATE_INT);
        if (!$idDenuncia || $idDenuncia <= 0) {
            http_response_code(400);
            exit("ID da denúncia inválido.");
        }

        $sql = "SELECT evidencia FROM denuncias WHERE id_denuncia = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            exit("Não foi possível consultar a evidência.");
        }

        $stmt->bind_param("i", $idDenuncia);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($registro && !empty($registro["evidencia"])) {
            $caminho = $registro["evidencia"];
        }
        break;

    default:
        http_response_code(400);
        exit("Tipo de documento inválido.");
}

// Verifica se o caminho foi encontrado.
if (!is_string($caminho) || trim($caminho) === "") {
    http_response_code(404);
    exit("Documento não encontrado ou acesso não autorizado.");
}

try {
    // Gera um link temporário para o arquivo privado no Supabase.
    $link = gerarLinkDocumentoSupabase($caminho);

    $urlSupabase = defined("SUPABASE_URL") ? parse_url(SUPABASE_URL) : false;
    $urlDocumento = parse_url($link);

    if (
        !is_array($urlSupabase) ||
        !is_array($urlDocumento) ||
        strtolower($urlDocumento["scheme"] ?? "") !== "https" ||
        strtolower($urlDocumento["host"] ?? "") !== strtolower($urlSupabase["host"] ?? "")
    ) {
        throw new RuntimeException("O link retornado pelo Supabase não é válido.");
    }

    header("Cache-Control: no-store, private");
    header("Pragma: no-cache");
    header("Location: " . $link, true, 302);
    exit;

} catch (Throwable $e) {
    error_log("Erro ao visualizar documento: " . $e->getMessage());
    http_response_code(502);
    exit("Não foi possível abrir o documento. Verifique a configuração do Supabase e o caminho do arquivo.");
}
?>