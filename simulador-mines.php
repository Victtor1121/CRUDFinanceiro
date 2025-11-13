<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// usuário logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
$usuario_id = intval($_SESSION['usuario_id']);
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// calcula saldo atual (receitas - despesas)
$balStmt = $pdo->prepare("
    SELECT 
       SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receitas,
       SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesas
    FROM transacoes
    WHERE usuario_id = ?
");
$balStmt->execute([$usuario_id]);
$bal = $balStmt->fetch(PDO::FETCH_ASSOC);
$saldo_atual = (float)($bal['total_receitas'] ?? 0) - (float)($bal['total_despesas'] ?? 0);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Mines - Financeiro</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <style>
  /* ====== MINES STYLES (integra ao seu tema) ====== */
  
  .mines-wrap { display:flex; gap:20px; align-items:flex-start; padding:18px; }
  .mines-panel { width:320px; background: rgba(255,255,255,0.03); border-radius:12px; padding:18px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
  .mines-panel h3 { margin:0 0 12px; color:#dcefff; }
  .mines-control { display:flex; flex-direction:column; gap:12px; }
  .mines-control label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #bcd6ea;
  margin-bottom: 6px; /* separa a label do input */
}
  
.mines-control .input,
.mines-control select {
  display: block;
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.06);
  color: #eaf6ff;
  font-size: 0.95rem;
  box-sizing: border-box;
  transition: all 0.25s ease;
}
.mines-control .input:focus,
.mines-control select:focus {
  outline: none;
  border-color: rgba(0, 180, 216, 0.6);
  box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.15);
}
  .mines-btn {
    display:flex;
    gap:10px;
  }
/* ====== BOTÃO START / STOP COM GRADIENTE VERDE ====== */
.btn-start {
  position: relative;
  display: inline-block;
  width: 100%;
  padding: 12px 18px;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1rem;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #fff;
  background: linear-gradient(90deg, #00c853, #00e676);
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0, 255, 140, 0.25);
  transition: all 0.25s ease;
  overflow: hidden;
}

/* Brilho animado cruzando o botão */
.btn-start::before {
  content: "";
  position: absolute;
  top: 0;
  left: -75%;
  width: 50%;
  height: 100%;
  background: linear-gradient(
    120deg,
    transparent,
    rgba(255, 255, 255, 0.25),
    transparent
  );
  transform: skewX(-25deg);
  transition: all 0.6s ease;
}

.btn-start:hover::before {
  left: 130%;
}

.btn-start:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 22px rgba(0, 255, 160, 0.35);
}

.btn-start:active {
  transform: translateY(0);
  box-shadow: 0 2px 10px rgba(0, 255, 160, 0.25);
}

/* Estado "Parar o jogo" — mantém contraste */
.btn-stop {
  background: linear-gradient(90deg, #ff5f6d, #ffc371) !important;
  box-shadow: 0 4px 16px rgba(255, 100, 100, 0.35);
}

.btn-stop:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 22px rgba(255, 150, 100, 0.45);
}

/* Estado desabilitado */
.btn-start[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}


  .mines-board {
    width: 520px;
    height: 520px;
    background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
    border-radius:12px;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    padding: 20px;
    box-sizing: border-box;
    position: relative;
    box-shadow: inset 0 6px 18px rgba(0,0,0,0.4);
  }
  .cell {
    background: rgba(255,255,255,0.03);
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    color: rgba(230, 247, 255, 0.85);
    font-size:1.15rem;
    cursor:pointer;
    user-select:none;
    transition: transform 0.14s ease, background 0.18s ease;
    border:1px solid rgba(255,255,255,0.03);
    height: calc((520px - 40px - 4*14px)/5); /* keep squares */
  }
  .cell:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.45); }
  .cell.revealed { cursor:default; transform:none; background: rgba(255,255,255,0.04); box-shadow:none; }
  .cell.mine { background: linear-gradient(90deg, rgba(200,0,0,0.28), rgba(255,120,120,0.12)); color:#fff; }
  .cell.safe { background: linear-gradient(135deg, rgba(0,255,180,0.25), rgba(0,200,120,0.12)); border: 1px solid rgba(0,255,180,0.25);
  color: #eaffef;
  box-shadow: inset 0 0 6px rgba(0,255,160,0.2);}

  .info-row { display:flex; gap:8px; align-items:center; margin-top:6px; color:#cfe9ff; }
  .muted { color:#93b8d0; font-size:0.9rem; }

  .mines-status { margin-top:10px; color:#eaf6ff; font-weight:600; }
  .small-muted { font-size:0.85rem; color:#a7c3d9; }

  @media (max-width:1000px) {
    .mines-wrap { flex-direction:column; }
    .mines-board { width:100%; height:auto; grid-auto-rows: minmax(56px, 1fr); }
    .cell { height: 64px; }
  }
  .input-panel-tip,
  .input-panel{
  background: rgba(255,255,255,0.12)!important;
  border: 1px solid rgba(255,255,255,0.18)!important;
  border-radius: 14px!important;
  padding: 10px 14px!important;
  font-size: .9rem;
  color: #fff!important;
  transition: 0.25s!important;
  outline: none;
}

.input-panel-tip option {
  background: #0d1b2a;        /* fundo igual seu tema base */
  color: #eafdff;             /* texto claro legível */
  padding: 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: .9rem;
}
.input-panel-tip option:hover,
.input-panel-tip option:focus {
  background: #00b4d8;
  color: #fff;
}
/* === Correção: inputs dentro de .mines-control ocupam toda a largura === */
.mines-control input[type="number"],
.mines-control input[type="text"],
.mines-control select {
  display: block;
  width: 100%;
  min-width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.06);
  color: #eaf6ff;
  font-size: 0.95rem;
  transition: all 0.25s ease;
}

/* Melhora a estética no foco */

.input-panel:focus,
.input-panel-tip:focus,
.input-panel-cat:focus{
  border-color:#00b4d8!important;
  box-shadow: 0 0 10px rgba(0,180,216,0.5);
}
/* ====== ESTILO DAS CASAS REVELADAS AO FINAL DO JOGO ====== */
.cell.safe-final {
  background: linear-gradient(135deg, rgba(0,255,180,0.25), rgba(0,200,120,0.12));
  border: 1px solid rgba(0,255,180,0.25);
  color: #eaffef;
  box-shadow: inset 0 0 6px rgba(0,255,160,0.2);
}
.cell.safe-final:hover {
  transform: none;
  box-shadow: none;
}
.cell.revealed {
  animation: revealFade 0.3s ease forwards;
}
@keyframes revealFade {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}
.btn-start:not(.btn-stop):hover {
  animation: pulseGreen 1.8s infinite;
}
@keyframes pulseGreen {
  0%, 100% { box-shadow: 0 0 20px rgba(0,255,140,0.25); }
  50% { box-shadow: 0 0 30px rgba(0,255,180,0.45); }
}
/* ====== SALDO ATUAL ====== */
.saldo-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 0;
}

.saldo-box label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #bcd6ea;
  letter-spacing: 0.3px;
}

/* Card translúcido para o valor */
.saldo-valor {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 1.3rem;
  font-weight: 700;
  color:rgb(122, 252, 161);
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: inset 0 0 12px rgba(0, 255, 150, 0.08);
  transition: all 0.3s ease;
  text-shadow: 0 0 4px rgba(0, 255, 180, 0.4);
}

/* Gradiente animado no número */
.saldo-valor span {
  background: linear-gradient(90deg,rgb(0, 230, 31), #00c853,rgb(0, 255, 149));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-weight: 800;
  animation: saldoShine 3s linear infinite;
}

@keyframes saldoShine {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}

/* Hover sutil */
.saldo-valor:hover {
  transform: scale(1.02);
  box-shadow: 0 0 14px rgba(0, 255, 180, 0.15);
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
</div>


  <a class="nav-item logout <?= $current === 'logout.php' ? 'active' : '' ?>" href="logout.php">
    <i class="bi bi-box-arrow-right"></i> Sair
  </a>

  
</nav>


    </aside>

  <main class="main">
    <h2 style="color:#eafdff; margin-bottom:12px;">Simulador - Mines</h2>

    <div class="mines-wrap">
      <div class="mines-panel">
        <div class="mines-control">
          <div class="saldo-box">
            <label>Saldo atual</label>
            <div class="saldo-valor">R$ <span id="saldoDisplay"><?= number_format($saldo_atual,2,',','.') ?></span></div>
          </div>

          <div>
            <label>Valor da aposta</label>
            <input id="betInput" class="input-panel" type="number" min="0.1" step="0.01" value="1.00">
          </div>

          <div>
            <label>Número de minas</label>
            <select id="minesSelect" class="input-panel-tip">
              <?php for($m=1;$m<=24;$m++): ?>
                <option value="<?= $m ?>" <?= $m===3?'selected':'' ?>><?= $m ?> minas</option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="mines-btn">
          <button id="mainBtn" class="btn-start">Começar o jogo</button>
          </div>

          <div class="mines-status" id="statusText">Pronto para iniciar.</div>
          <div class="small-muted">Clique em casas do tabuleiro para revelar. Cuidado com as minas.</div>
        </div>
      </div>

      <div class="mines-board" id="board" aria-hidden="true">
        <!-- 25 células renderizadas pelo JS -->
      </div>
    </div>
  </main>

  <script>
/* ====== mines frontend logic ====== */
const boardEl = document.getElementById('board');
const mainBtn = document.getElementById('mainBtn');
const betInput = document.getElementById('betInput');
const minesSelect = document.getElementById('minesSelect');
const statusText = document.getElementById('statusText');
const saldoDisplay = document.getElementById('saldoDisplay');

let gameState = null;
let isPlaying = false;

function renderEmptyBoard() {
  boardEl.innerHTML = '';
  for (let i=0;i<25;i++){
    const d = document.createElement('div');
    d.className = 'cell';
    d.dataset.index = i;
    boardEl.appendChild(d);
  }
}
renderEmptyBoard();

function setControls(disabled) {
  betInput.disabled = disabled;
  minesSelect.disabled = disabled;
}

async function apiPost(data) {
  try {
    const res = await fetch('mines-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'same-origin'
    });
    const txt = await res.text();
    try { return JSON.parse(txt); }
    catch(e) { console.error("Resposta não JSON:", txt); return {error:"Resposta inválida"}; }
  } catch(err) {
    console.error("Erro AJAX:", err);
    return {error:"Falha de conexão"};
  }
}

function updateBoardFromState(state) {
  const cells = boardEl.querySelectorAll('.cell');
  cells.forEach(c => {
    const idx = parseInt(c.dataset.index, 10);
    c.className = 'cell';
    c.innerHTML = '';

    // 🔹 Caso o jogo tenha acabado: revelar tudo
    if (state.over) {
      if (state.mines && state.mines.includes(idx)) {
        // 💣 casa com mina
        c.classList.add('revealed', 'mine');
        c.innerHTML = '<i class="bi bi-x-lg"></i>';
      } else {
        // ✅ casa segura
        c.classList.add('revealed', 'safe-final');
        c.innerHTML = '<i class="bi bi-gem"></i>';
      }
    } 
    // 🔹 Caso ainda esteja jogando: mostrar apenas o que foi revelado
    else if (state.revealed && state.revealed.includes(idx)) {
      if (state.mines && state.mines.includes(idx)) {
        c.classList.add('revealed', 'mine');
        c.innerHTML = '<i class="bi bi-x-lg"></i>';
      } else {
        c.classList.add('revealed', 'safe');
        c.innerHTML = '<i class="bi bi-gem"></i>';
      }
    }
  });
}



/* === COMEÇAR / PARAR === */
mainBtn.addEventListener('click', async ()=>{
  if (!isPlaying) {
    // começar o jogo
    const bet = parseFloat(betInput.value);
    const mines = parseInt(minesSelect.value,10);
    if (!bet || bet <= 0) return alert('Insira um valor válido');
    mainBtn.disabled = true;
    statusText.textContent = 'Iniciando...';
    const r = await apiPost({ action: 'start', bet: bet, mines: mines });
    mainBtn.disabled = false;

    if (r.error) return alert(r.error);

    isPlaying = true;
    mainBtn.textContent = 'Parar o jogo';
    mainBtn.classList.add('btn-stop');

    gameState = r.state;
    updateBoardFromState(gameState);
    statusText.textContent = 'Jogo iniciado — clique em uma casa ou pare a qualquer momento.';
    if (r.saldo !== undefined)
      saldoDisplay.textContent = Number(r.saldo).toFixed(2).replace('.', ',');
    setControls(true);
  } else {
    // parar (cashout)
    mainBtn.disabled = true;
    statusText.textContent = 'Encerrando...';
    const r = await apiPost({ action: 'cashout' });
    mainBtn.disabled = false;

    if (r.error) return alert(r.error);

    isPlaying = false;
    mainBtn.textContent = 'Começar o jogo';
    mainBtn.classList.remove('btn-stop');
    gameState = r.state;
    updateBoardFromState(gameState);
    if (r.result === 'won') {
      statusText.textContent = `Você encerrou o jogo e ganhou R$ ${Number(r.won).toFixed(2)}!`;
    } else {
      statusText.textContent = 'Jogo encerrado.';
    }
    if (r.saldo !== undefined)
      saldoDisplay.textContent = Number(r.saldo).toFixed(2).replace('.', ',');
    setControls(false);
  }
});

/* === REVELAR CELULAS === */
boardEl.addEventListener('click', async ev => {
  // não deixa clicar se o jogo não estiver ativo
  if (!isPlaying || !gameState || gameState.over) return;

  const cell = ev.target.closest('.cell');
  if (!cell) return;

  const idx = parseInt(cell.dataset.index, 10);
  const r = await apiPost({ action: 'reveal', index: idx });
  if (r.error) return alert(r.error);

  gameState = r.state;
  updateBoardFromState(gameState);

  if (gameState.over) {
    isPlaying = false;
    mainBtn.textContent = 'Começar o jogo';
    setControls(false);
    mainBtn.classList.remove('btn-stop');
    if (r.result === 'lost') {
      statusText.textContent = 'Você acertou uma mina e perdeu a aposta.';
    } else if (r.result === 'won') {
      statusText.textContent = `Você ganhou R$ ${Number(r.won).toFixed(2)}!`;
    }

    if (r.saldo !== undefined)
      saldoDisplay.textContent = Number(r.saldo).toFixed(2).replace('.', ',');
  } else {
    statusText.innerHTML = `Casas seguras: ${gameState.revealed.length} — Multiplicador atual: <strong>${Number(r.multiplier).toFixed(3)}x</strong>`;
  }
});

</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname;
  const file = path.substring(path.lastIndexOf('/') + 1);

  // remove qualquer active existente
  document.querySelectorAll('.simulador-dual a').forEach(el => el.classList.remove('active'));

  // marca conforme o arquivo
  if (file.includes('mines')) {
    document.querySelector('.simulador-dual a.mines')?.classList.add('active');
  } else if (file.includes('crash')) {
    document.querySelector('.simulador-dual a.crash')?.classList.add('active');
  }
});
</script>

</body>
</html>
