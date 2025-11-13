<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error'=>'Usuário não autenticado']);
    exit;
}
$usuario_id = intval($_SESSION['usuario_id']);
$action = $input['action'] ?? '';

function get_saldo(PDO $pdo, $uid) {
    $stmt = $pdo->prepare("
      SELECT 
         SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receitas,
         SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesas
      FROM transacoes
      WHERE usuario_id = ?
    ");
    $stmt->execute([$uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float)($r['total_receitas'] ?? 0) - (float)($r['total_despesas'] ?? 0);
}

function random_mines($count, $cells = 25) {
    $arr = range(0, $cells-1);
    shuffle($arr);
    return array_slice($arr, 0, $count);
}

function fair_multiplier($safe_revealed, $mines, $cells = 25, $house_edge = 0.015) {
    // prob survive safe_revealed picks:
    if ($safe_revealed <= 0) return 1.0;
    $p = 1.0;
    for ($i=0;$i<$safe_revealed;$i++){
        $p *= (($cells - $mines - $i) / ($cells - $i));
    }
    if ($p <= 0) return 1.0;
    $mult = 1.0 / $p;
    $mult *= (1 - $house_edge);
    return $mult;
}

if ($action === 'start') {
    $bet = floatval($input['bet'] ?? 0);
    $mines = intval($input['mines'] ?? 3);
    if ($bet <= 0) { echo json_encode(['error'=>'Valor de aposta inválido']); exit; }
    if ($mines < 1 || $mines > 24) { echo json_encode(['error'=>'Número de minas inválido']); exit; }

    // checa saldo
    $saldo = get_saldo($pdo, $usuario_id);
    if ($saldo < $bet) {
        echo json_encode(['error'=>'Saldo insuficiente','saldo'=>$saldo]);
        exit;
    }

    // insere transacao de despesa (reserva do bet)
    $today = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO transacoes (usuario_id, categoria_id, tipo, descricao, valor, data_transacao) VALUES (?, 2, 'despesa', ?, ?, ?)");
        $desc = "Aposta Mines (finalizado)";
        $ins->execute([$usuario_id, $desc, $bet, $today]);
        $bet_tx_id = $pdo->lastInsertId();

        // cria estado do jogo na sessão
        $mines_pos = random_mines($mines, 25);
        $_SESSION['mines_game'] = [
            'bet' => $bet,
            'bet_tx_id' => $bet_tx_id,
            'mines' => $mines_pos,
            'revealed' => [],
            'mines_count' => $mines,
            'over' => false,
            'started_at' => time()
        ];

        $pdo->commit();

        $state = [
            'revealed' => [],
            'mines' => [], // server keeps mines hidden
            'mines_count' => $mines,
            'over' => false
        ];
        $newSaldo = get_saldo($pdo, $usuario_id);

        echo json_encode(['ok'=>true,'state'=>$state,'saldo'=>$newSaldo]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error'=>'Erro ao iniciar aposta','detail'=>$e->getMessage()]);
        exit;
    }
}

if (!isset($_SESSION['mines_game'])) {
    echo json_encode(['error'=>'Nenhum jogo ativo']);
    exit;
}
$game = &$_SESSION['mines_game'];

if ($action === 'reveal') {
    $idx = intval($input['index'] ?? -1);
    if ($idx < 0 || $idx > 24) { echo json_encode(['error'=>'Casa inválida']); exit; }
    if ($game['over']) { echo json_encode(['error'=>'Jogo já encerrado']); exit; }
    if (in_array($idx, $game['revealed'])) {
        // já revelada
    } else {
        // se for mina -> perde
        if (in_array($idx, $game['mines'])) {
            $game['over'] = true;
            // lost: bet already foi debitado na start; nada mais a fazer
            $state = [
                'revealed' => $game['revealed'],
                'mines' => $game['mines'],
                'over' => true
            ];
            // limpa sessão de jogo
            unset($_SESSION['mines_game']);
            $saldo = get_saldo($pdo, $usuario_id);
            echo json_encode([
                'ok'=>true,
                'result'=>'lost',
                'message'=>'Você acertou uma mina.',
                'state'=>$state,
                'saldo'=>$saldo
            ]);
            exit;
        } else {
            // safe
            $game['revealed'][] = $idx;
            // calcula multiplicador justo
            $safeCount = count($game['revealed']);
            $mult = fair_multiplier($safeCount, $game['mines_count']);
            $state = [
                'revealed' => $game['revealed'],
                'mines' => [], // still hidden
                'over' => false
            ];
            echo json_encode([
                'ok'=>true,
                'result'=>'safe',
                'state'=>$state,
                'multiplier'=>$mult,
                'message'=>'Casa segura'
            ]);
            exit;
        }
    }
    // fallback return state
    $safeCount = count($game['revealed']);
    $mult = fair_multiplier($safeCount, $game['mines_count']);
    echo json_encode(['ok'=>true,'state'=>['revealed'=>$game['revealed'],'mines'=>[],'over'=>$game['over']],'multiplier'=>$mult]);
    exit;
}

if ($action === 'cashout') {
    if ($game['over']) { echo json_encode(['error'=>'Jogo já encerrado']); exit; }
    $bet = (float)$game['bet'];
    $revealed = $game['revealed'];
    $safeCount = count($revealed);
    $mines = $game['mines_count'];
    $mult = fair_multiplier($safeCount, $mines);
    $winnings = $bet * $mult;
    // round to 2 decimals
    $winnings = round($winnings, 2);

    // grava receita (ganho)
    $today = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        // insert receita (ganho)
        $desc = "Ganho Mines (cashout)";
        $ins = $pdo->prepare("INSERT INTO transacoes (usuario_id, categoria_id, tipo, descricao, valor, data_transacao) VALUES (?, 2, 'receita', ?, ?, ?)");
        $ins->execute([$usuario_id, $desc, $winnings, $today]);

        // encerra jogo e limpa sessão
        $_SESSION['mines_game']['over'] = true;
        $state = [
            'revealed' => $game['revealed'],
            'mines' => $game['mines'],
            'over' => true
        ];

        // persist commit
        $pdo->commit();

        // cleanup game
        unset($_SESSION['mines_game']);

        $saldo = get_saldo($pdo, $usuario_id);
        echo json_encode([
            'ok'=>true,
            'result'=>'won',
            'won'=>$winnings,
            'state'=>$state,
            'saldo'=>$saldo
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error'=>'Erro ao registrar ganho','detail'=>$e->getMessage()]);
        exit;
    }
}

echo json_encode(['error'=>'Ação inválida']);
exit;
