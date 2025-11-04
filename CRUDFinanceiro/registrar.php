<link rel="stylesheet" href="assets/css/login.css">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card-login">
        <h2>Criar Conta</h2>
        <?php if (isset($_GET['erro'])): ?>
          <div class="alert alert-danger text-center">
            E-mail já cadastrado. Tente outro.
          </div>
        <?php endif; ?>
        <form method="POST" action="registrar_action.php">
          <div class="mb-3">
            <label for="nome" class="form-label">Nome completo</label>
            <input type="text" class="form-control" name="nome" required>
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control" name="senha" required>
          </div>
          <button type="submit" class="btn btn-custom w-100">Registrar</button>
        </form>
        <div class="text-center mt-3">
          <a href="index.php" class="btn btn-outline-light">Voltar ao login</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('includes/footer.php'); ?>
