<?php
// ========================================
// ARQUIVO: db_connect.php
// Conexão segura com PDO (MySQL)
// ========================================

$host = 'localhost';
$dbname = 'financeiro';
$user = 'root';       // altere se necessário
$pass = '';           // altere se tiver senha

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>
