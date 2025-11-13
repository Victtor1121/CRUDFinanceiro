<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulando um usuário logado temporariamente (ajuste depois com login real)
$usuario_id = $_SESSION['usuario_id'] ?? 1;

// ======= CREATE =======
if (isset($_POST['acao']) && $_POST['acao'] === 'add') {
    $tipo = $_POST['tipo'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data = $_POST['data'];
    $categoria_id = $_POST['categoria_id'] ?? null;

    $sql = "INSERT INTO transacoes (usuario_id, categoria_id, tipo, descricao, valor, data_transacao)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $categoria_id, $tipo, $descricao, $valor, $data]);
    echo json_encode(['status' => 'ok', 'msg' => 'Transação adicionada com sucesso!']);
    exit;
}

// ======= READ =======
if (isset($_GET['acao']) && $_GET['acao'] === 'listar') {
    $sql = "SELECT t.id, t.tipo, t.descricao, t.valor, t.data_transacao, c.nome AS categoria
            FROM transacoes t
            LEFT JOIN categorias c ON t.categoria_id = c.id
            WHERE t.usuario_id = ?
            ORDER BY t.data_transacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($dados);
    exit;
}

// ======= UPDATE =======
if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $tipo = $_POST['tipo'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data = $_POST['data'];
    $categoria_id = $_POST['categoria_id'] ?? null;

    $sql = "UPDATE transacoes SET categoria_id=?, tipo=?, descricao=?, valor=?, data_transacao=? 
            WHERE id=? AND usuario_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria_id, $tipo, $descricao, $valor, $data, $id, $usuario_id]);
    echo json_encode(['status' => 'ok', 'msg' => 'Transação atualizada com sucesso!']);
    exit;
}

// ======= DELETE =======
if (isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
    $id = $_POST['id'];
    $sql = "DELETE FROM transacoes WHERE id=? AND usuario_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $usuario_id]);
    echo json_encode(['status' => 'ok', 'msg' => 'Transação excluída com sucesso!']);
    exit;
}
?>
