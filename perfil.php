<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
// ====== Apagar todas as transações ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'delete_all') {
  $del = $pdo->prepare("DELETE FROM transacoes WHERE usuario_id = ?");
  $del->execute([$usuario_id]);
  $msg = "⚠️ Todas as transações foram apagadas com sucesso!";
}

// ====== Atualização de dados ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'update') {
  
    $nome = trim($_POST['nome']);
    $data_nascimento = $_POST['data_nascimento'] ?: null;
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, data_nascimento = ? WHERE id = ?");
    $stmt->execute([$nome, $email, $data_nascimento, $usuario_id]);
    
    $_SESSION['email'] = $email;
    

    $_SESSION['nome'] = $nome; // atualiza sessão
    $msg = "Dados atualizados com sucesso!";
}

// ====== Alterar senha ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'senha') {
    $senha_atual = $_POST['senha_atual'];
    $senha_nova = password_hash($_POST['senha_nova'], PASSWORD_DEFAULT);

    $ver = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
    $ver->execute([$usuario_id]);
    $senha_db = $ver->fetchColumn();

    if (password_verify($senha_atual, $senha_db)) {
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$senha_nova, $usuario_id]);
        $msg = "Senha alterada com sucesso!";
    } else {
        $msg = "⚠️ Senha atual incorreta.";
    }
}

// ====== Busca dados ======
$stmt = $pdo->prepare("SELECT nome, email, data_nascimento, criado_em FROM usuarios WHERE id = ?");

$stmt->execute([$usuario_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Meu Perfil</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<style>
.panel form label {
  display:flex;
  flex-direction:column;
  gap:4px;
  font-weight:500;
  font-size:.85rem;
  color:#dce7f3;
}
.input-criada {
  background:rgba(255,255,255,0.12)!important;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.05)!important;
  border:1px solid rgba(255,255,255,0.18)!important;
  border-radius:14px!important;
  padding:10px 14px!important;
  font-size:.9rem;
  color:#fff!important;
  cursor: not-allowed; 
  opacity: 0.5;  
  outline:none;          /* leve transparência */
}

.grid-form{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
  margin-top:10px;
}
.grid-form-full{ grid-column:1 / span 2; }
.input-panel{
  background:rgba(255,255,255,0.12)!important;
  border:1px solid rgba(255,255,255,0.18)!important;
  border-radius:14px!important;
  padding:10px 14px!important;
  font-size:.9rem;
  color:#fff!important;
  outline:none;
}

.input-panel:focus{
  border-color:#00b4d8!important;
  box-shadow:0 0 10px rgba(0,180,216,0.5);
}

.btn-custom{
  width:100%!important;
  padding:10px;
  background:#00b4d8;
  color:white;
  border:none;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
}
</style>
</head>

<body>
<div class="app-layout">
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
  <h1>Meu Perfil</h1>
  <p class="muted">Gerencie suas informações pessoais</p>
</header>

<?php if(isset($msg)): ?>
<div style="background:#003d52;padding:10px;border-radius:10px;margin-bottom:10px;color:#fff;">
<?= $msg ?>
</div>
<?php endif; ?>

<div class="panel">
<h3><i class="bi bi-person-circle"></i> Informações da Conta</h3>

<form method="post">
<input type="hidden" name="acao" value="update">

<div class="grid-form">

  <label>
  <span class="label-icon"><i class="bi bi-person"></i> Nome</span>
    <input class="input-panel" type="text" name="nome" value="<?= htmlspecialchars($user['nome']) ?>" required>

  </label>

  <label>
  <span class="label-icon"><i class="bi bi-envelope"></i> Email</span>
    <input class="input-panel" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
  </label>

  <label class="grid-form-full">
  <span class="label-icon"><i class="bi bi-calendar-date"></i> Data de Nascimento</span>
    <input class="input-panel" type="date" name="data_nascimento" value="<?= $user['data_nascimento'] ?>">
  </label>

  <label class="grid-form-full">
  <span class="label-icon"><i class="bi bi-clock-history"></i> Conta criada em:</span>
    <input class="input-criada" type="text" value="<?= date('d/m/Y H:i', strtotime($user['criado_em'])) ?>" disabled>
  </label>

  <button class="btn-custom grid-form-full">Salvar dados</button>
</div>
</form>



<hr style="margin:20px 0;border-color:#234;">

<h3><i class="bi bi-lock"></i> Alterar Senha</h3>
<form method="post">
<input type="hidden" name="acao" value="senha">

<div class="grid-form">
  <label>
  <span class="label-icon"><i class="bi bi-shield-lock"></i> Senha atual</span>
    <input class="input-panel" type="password" name="senha_atual" required>
  </label>

  <label>
  <span class="label-icon"><i class="bi bi-key"></i> Nova senha</span>
    <input class="input-panel" type="password" name="senha_nova" required minlength="4">
  </label>

  <button class="btn-custom grid-form-full">Atualizar senha</button>
</div>
</form>
<!-- ====== BOTÃO DE RISCO ====== -->
<div class="danger-zone">
  <h3><i class="bi bi-exclamation-triangle"></i> Zona de Risco</h3>
  <p class="muted">Esta ação irá <strong>apagar todas as transações</strong> da sua conta. Esta operação é irreversível.</p>
  <button type="button" class="btn-danger-delete" id="deleteAllBtn">
    <i class="bi bi-trash3"></i> Apagar todas as transações
  </button>
</div>
</div>
</main>
</div>

<!-- ====== MODAL DE CONFIRMAÇÃO ====== -->
<div id="confirmModal" class="confirm-modal">
  <div class="confirm-box">
    <h4><i class="bi bi-exclamation-octagon"></i> Confirmar exclusão</h4>
    <p>Tem certeza de que deseja <strong>excluir todas as suas transações?</strong><br>Esta ação não poderá ser desfeita.</p>
    <div class="confirm-actions">
      <button id="confirmCancel" class="btn-cancel">Cancelar</button>
      <form method="post" style="margin:0;">
        <input type="hidden" name="acao" value="delete_all">
        <button type="submit" class="btn-confirm">Sim, apagar tudo</button>
      </form>
    </div>
  </div>
</div>



</body>
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
<script>
document.getElementById('deleteAllBtn').addEventListener('click', () => {
  document.getElementById('confirmModal').style.display = 'flex';
});
document.getElementById('confirmCancel').addEventListener('click', () => {
  document.getElementById('confirmModal').style.display = 'none';
});
</script>

</html>
