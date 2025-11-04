<?php
session_start();
require_once('includes/conexao.php');

$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

$sql = "SELECT * FROM usuarios WHERE email = :email";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':email', $email);
$stmt->execute();

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($senha, $usuario['senha'])) {
  $_SESSION['usuario_id'] = $usuario['id'];
  $_SESSION['usuario_nome'] = $usuario['nome'];
  header('Location: dashboard.php');
} else {
  header('Location: index.php?erro=1');
}
