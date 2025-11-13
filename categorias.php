<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// usuário logado
$usuario_id = $_SESSION['usuario_id'] ?? 1;
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'add') {
    $nome = trim($_POST['nome']);
    if (!empty($nome)) {
        $tipo = $_POST['tipo'] ?? null;
        $sql = "INSERT INTO categorias (nome, tipo, usuario_id) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $tipo, $usuario_id]);
    }
    header("Location: categorias.php");
    exit;
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);

    // valida se categoria é do usuário
    $chk = $pdo->prepare("SELECT usuario_id FROM categorias WHERE id=?");
    $chk->execute([$id]);
    $own = $chk->fetchColumn();

    if ($own == $usuario_id) {
        $tipo = $_POST['tipo'] ?? null;
        $sql = "UPDATE categorias SET nome=?, tipo=? WHERE id=? AND usuario_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $id, $tipo, $usuario_id]);
    }

    header("Location: categorias.php");
    exit;
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
    $id = intval($_POST['id']);

    // só pode deletar se for do usuário
    $sql = "DELETE FROM categorias WHERE id=? AND usuario_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $usuario_id]);

    header("Location: categorias.php");
    exit;
}

// LIST
$stmt = $pdo->prepare("
  SELECT id, nome, usuario_id, tipo
  FROM categorias
  WHERE usuario_id IS NULL OR usuario_id = ?
  ORDER BY usuario_id IS NULL DESC, nome ASC
");
$stmt->execute([$usuario_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Categorias</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <style>
     .panel form label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 500;
  font-size: 0.85rem;
  color: #dce7f3;
}

.input-panel,
.input-panel-tip,
.input-panel-cat {
  background: rgba(255,255,255,0.12)!important;
  border: 1px solid rgba(255,255,255,0.18)!important;
  border-radius: 14px!important;
  padding: 10px 14px!important;
  font-size: .9rem;
  color: #fff!important;
  transition: 0.25s!important;
  outline: none;
}

.input-panel:focus,
.input-panel-tip:focus,
.input-panel-cat:focus{
  border-color:#00b4d8!important;
  box-shadow: 0 0 10px rgba(0,180,216,0.5);
}
.btn-mini-excluir{
    color: #d00;
    background-color: transparent;
    border: none;
    margin-left: -5px;
    cursor: pointer;
    }
    .btn-mini-editar{
    color: #00b4d8;
    background-color: transparent;
    border: none;
    }
    .btn-mini {
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 0.85rem;
  cursor: pointer;
}
.align-right { text-align: right; }
    .table { width:100%; border-collapse: collapse; }
    .table th, .table td { padding:8px;border-bottom:1px solid #eeeeee29; } 
    .table tbody tr:hover {
  border-left: 1px solid #00b4d8;
}
.input-panel-cat option,
.input-panel-tip option {
  background: #0d1b2a;        /* fundo igual seu tema base */
  color: #eafdff;             /* texto claro legível */
  padding: 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: .9rem;
}

.input-panel-cat option:hover,
.input-panel-tip option:hover,
.input-panel-cat option:focus,
.input-panel-tip option:focus {
  background: #00b4d8;
  color: #fff;
}
  </style>
</head>
<body>

<div class="app-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
      <div class="brand"><div class="logo">Financeiro</div><div class="small">Controle Pessoal</div></div>
      <?php
$current = basename($_SERVER['PHP_SELF']); // ex: 'dashboard.php'
?>
<nav class="nav">
  <a class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>

  <a class="nav-item <?= $current === 'transacoes.php' ? 'active' : '' ?>" href="transacoes.php">
    <i class="bi bi-wallet2"></i> Transações
  </a>

  <a class="nav-item <?= $current === 'categorias.php' ? 'active' : '' ?>" href="categorias.php">
    <i class="bi bi-tags"></i> Categorias
  </a>

  <a class="nav-item <?= $current === 'relatorios.php' ? 'active' : '' ?>" href="relatorios.php">
    <i class="bi bi-bar-chart"></i> Relatórios
  </a>

  <a class="nav-item <?= $current === 'perfil.php' ? 'active' : '' ?>" href="perfil.php">
    <i class="bi bi-person-circle"></i> Meu Perfil
  </a>

  <!-- === SEÇÃO DE SIMULADORES === -->
 <div class="simuladores-section">
  <span class="simuladores-title"><i class="bi bi-controller"></i> Simuladores</span>

  <div class="simulador-dual">
    <a href="simulador-crash.php" class="half crash">
      <i class="bi bi-rocket-takeoff"></i> Crash
    </a>
    <a href="simulador-mines.php" class="half mines">
      <i class="bi bi-gem"></i> Mines
    </a>
  </div>

  <a class="nav-item logout <?= $current === 'logout.php' ? 'active' : '' ?>" href="logout.php">
    <i class="bi bi-box-arrow-right"></i> Sair
  </a>
</nav>


  </aside>

  <main class="main">
    <header class="topbar">
      <div class="welcome">
        <h1>Categorias</h1>
        <p class="muted">Gerencie as categorias do seu sistema</p>
      </div>
    </header>

    <div class="content-grid" style="display:flex; gap:16px;">

        <section style="flex:1;">
        <div class="panel" style="padding:20px;">
    <h3 style="margin-bottom:18px;">Lista de Categorias</h3>

    <?php
      $globais = array_filter($categorias, fn($c) => is_null($c['usuario_id']));
      $minhas  = array_filter($categorias, fn($c) => !is_null($c['usuario_id']));
    ?>

    <!-- CATEGORIAS GLOBAIS -->
    <h4 style="margin-bottom:8px;opacity:.7;font-size:.9rem;">Categorias Padrão</h4>
    <table class="table" style="margin-bottom:20px;">
      <tbody>
        <?php if(count($globais) == 0): ?>
          <tr><td style="opacity:.6;font-size:.8rem;padding:8px;">Nenhuma categoria padrão cadastrada</td></tr>
        <?php endif; ?>
        <?php foreach($globais as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['nome']) ?> 

            <small style="opacity:.6;font-size:.70rem;">(<?= $c['tipo'] ?>)</small>
            <span style="opacity:.5;font-size:.75rem">(padrão)</span></td>
            <td class="align-right" ><i class="bi bi-lock" title="Categoria padrão protegida" ></i></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- CATEGORIAS DO USUÁRIO -->
    <h4 style="margin-bottom:8px;opacity:.7;font-size:.9rem;">Minhas Categorias</h4>
    <table class="table">
      <tbody>
        <?php if(count($minhas) == 0): ?>
          <tr><td style="opacity:.6;font-size:.8rem;padding:8px;">Você ainda não criou categorias</td></tr>
        <?php endif; ?>
        <?php foreach($minhas as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['nome']) ?>
            <small style="opacity:.6;font-size:.70rem;">(<?= $c['tipo'] ?>)</small></td>
            <td class="align-right">

              <a href="?edit=<?= $c['id'] ?>" class="btn-edit btn-mini" data-tooltip="Editar categoria"><i class="bi bi-pencil-square"></i></a>

              <form style="display:inline;" method="post" onsubmit="return confirm('Excluir categoria?');">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn-delete btn-mini" data-tooltip="Excluir categoria"><i class="bi bi-trash"></i></button>
              </form>

            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
</div>

        </section>

        <aside class="right-col" style="width:360px;">
          <div class="panel">
            <?php
              $editMode = false;
              $catEdit = null;
              if(isset($_GET['edit'])){
                $idE = intval($_GET['edit']);
                $q = $pdo->prepare("SELECT * FROM categorias WHERE id=? AND usuario_id=?");
                $q->execute([$idE, $usuario_id]);
                $catEdit = $q->fetch(PDO::FETCH_ASSOC);
                if($catEdit) $editMode = true;
              }
            ?>

            <h3><?= $editMode ? 'Editar Categoria' : 'Nova Categoria' ?></h3>
            <form method="post">
              <?php if($editMode): ?>
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" value="<?= $catEdit['id'] ?>">
              <?php else: ?>
                <input type="hidden" name="acao" value="add">
              <?php endif; ?>

              <label style="display:flex;flex-direction:column;gap:4px;font-weight:500;font-size:.85rem;color:#dce7f3;">
                Nome da Categoria
                <input required class="input-panel" type="text" name="nome" value="<?= $editMode ? htmlspecialchars($catEdit['nome']) : '' ?>">
              </label>
              <label style="display:flex;flex-direction:column;gap:4px;font-size:.85rem;color:#dce7f3;margin-top:12px;">
                  Tipo da Categoria
                  <select name="tipo" class="input-panel-tip" required>
                    <option value="receita" <?= ($editMode && $catEdit['tipo']=='receita' ? 'selected' : '') ?>>Receita</option>
                    <option value="despesa" <?= ($editMode && $catEdit['tipo']=='despesa' ? 'selected' : '') ?>>Despesa</option>
                  </select>
                </label>


              <button class="btn-custom" style="margin-top:12px;" data-tooltip="Adicionar categoria">Adicionar</button>
            </form>
          </div>
        </aside>

    </div>

  </main>
</div>
<script>
document.addEventListener("mousemove", e => {
  const t = e.target.closest("[data-tooltip]");
  if (!t) return;

  const tooltip = t.getAttribute("data-tooltip");
  const el = t;

  const rect = el.getBoundingClientRect();
  const tipWidth = tooltip.length * 6.8;

  let x = rect.left + rect.width / 2 - tipWidth / 2;
  if (x < 5) x = 5;
  if (x + tipWidth > window.innerWidth) x = window.innerWidth - tipWidth - 5;

  el.style.setProperty("--tooltip-x", x + "px");
});
</script>

</body>
</html>
