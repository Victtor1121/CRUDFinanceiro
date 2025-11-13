-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/11/2025 às 01:17
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `financeiro`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nome` varchar(80) NOT NULL,
  `cor` varchar(7) DEFAULT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `usuario_id`, `nome`, `cor`, `tipo`, `criado_em`) VALUES
(1, NULL, 'Salário', NULL, 'receita', '2025-10-23 01:06:23'),
(2, NULL, 'Investimentos', NULL, 'receita', '2025-10-23 01:06:23'),
(3, NULL, 'Alimentação', NULL, 'despesa', '2025-10-23 01:06:23'),
(4, NULL, 'Transporte', NULL, 'despesa', '2025-10-23 01:06:23'),
(5, NULL, 'Educação', NULL, 'despesa', '2025-10-23 01:06:23'),
(6, NULL, 'Saúde', NULL, 'despesa', '2025-10-23 01:06:23'),
(9, 1, 'Aluguel', NULL, 'receita', '2025-11-03 21:33:02'),
(10, 3, 'Academia', NULL, 'despesa', '2025-11-03 22:10:52'),
(11, 1, 'Academia', NULL, 'despesa', '2025-11-03 22:11:23'),
(12, 1, 'Obra', NULL, 'despesa', '2025-11-04 00:12:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes`
--

CREATE TABLE `transacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL CHECK (`valor` >= 0),
  `data_transacao` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `transacoes`
--

INSERT INTO `transacoes` (`id`, `usuario_id`, `categoria_id`, `tipo`, `descricao`, `valor`, `data_transacao`, `criado_em`) VALUES
(11, 2, 1, 'receita', 'Bingo', 180.00, '2025-10-01', '2025-11-01 15:56:23'),
(12, 2, 1, 'receita', 'Água', 5.00, '2025-10-10', '2025-11-01 15:57:11'),
(13, 2, 3, 'despesa', '', 50.00, '2025-10-29', '2025-11-01 15:57:34'),
(14, 2, 6, 'despesa', 'Remédio', 20.00, '2025-11-01', '2025-11-01 16:05:28'),
(15, 2, 4, 'despesa', 'Uber', 10.00, '2025-10-29', '2025-11-01 16:07:08'),
(20, 1, 1, 'receita', '', 1050.00, '2025-10-07', '2025-11-03 15:28:47'),
(21, 1, 1, 'receita', 'Tigre', 300.00, '2025-10-20', '2025-11-03 15:29:14'),
(22, 1, 3, 'despesa', 'Mercado', 300.00, '2025-10-23', '2025-11-03 15:29:38'),
(23, 1, 2, 'despesa', 'Conta de Luz', 150.00, '2025-10-28', '2025-11-03 15:30:08'),
(24, 1, 1, 'receita', '', 350.00, '2025-10-31', '2025-11-03 15:33:35'),
(28, 1, 2, 'receita', '', 300.00, '2025-10-31', '2025-11-03 15:40:24'),
(29, 1, NULL, 'despesa', '', 300.00, '2025-11-01', '2025-11-03 15:40:41'),
(30, 1, NULL, 'despesa', '', 200.00, '2025-11-01', '2025-11-03 15:41:12'),
(31, 1, NULL, 'receita', '', 500.00, '2025-11-02', '2025-11-03 15:41:25'),
(32, 1, NULL, 'despesa', 'Lanche', 50.00, '2025-11-01', '2025-11-03 15:41:58'),
(33, 1, 5, 'despesa', '', 1000.00, '2025-11-01', '2025-11-03 15:46:01'),
(34, 1, 1, 'receita', '', 1000.00, '2025-11-02', '2025-11-03 15:59:48'),
(35, 1, NULL, 'despesa', '', 1000.00, '2025-11-03', '2025-11-03 16:00:09'),
(38, 1, 1, 'despesa', '', 10.00, '2025-11-03', '2025-11-03 16:28:07'),
(39, 1, 7, 'despesa', '', 100.00, '2025-11-02', '2025-11-03 21:15:18'),
(40, 1, 4, 'despesa', '', 10.00, '2025-11-03', '2025-11-03 21:45:35'),
(41, 1, 1, 'receita', '', 10.00, '2025-11-03', '2025-11-03 21:49:16'),
(42, 1, 12, 'despesa', 'Pagamento pedreiro', 300.00, '2025-11-04', '2025-11-04 00:12:30');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `data_nascimento`, `email`, `senha`, `criado_em`) VALUES
(1, 'Victor Hugo Manzano Brandão', '2007-07-08', 'victorhugomanzanobrandao@gmail.com', '$2y$10$mkbrgqNahBycwcthg1RjZu.HY3zNKnjiXh5pkj1wKcmPwdWnI.eVy', '2025-10-20 20:27:20'),
(2, 'teste', NULL, 'eunice.manzano22@gmail.com', '$2y$10$Fqb/4R0qEYR7EgM0ac7MjutcIb2hoMUGGk2pD7L.u4j2b5E.kdC4i', '2025-11-01 10:00:30'),
(3, 'João', NULL, 'joao@gmail.com', '$2y$10$DqArMhrNgrWjwc6En35VjuMC60GNfllSzeA5Xhu04gHVnHecXk3D.', '2025-11-03 18:55:30');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `transacoes`
--
ALTER TABLE `transacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `transacoes`
--
ALTER TABLE `transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
