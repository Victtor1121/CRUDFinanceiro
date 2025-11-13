
<link rel="stylesheet" href="assets/css/login.css">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card-login">
        <h2>Bem-vindo ao Sistema Financeiro</h2>
        <?php if (isset($_GET['erro'])): ?>
          <div class="alert alert-danger text-center">
            Usuário não encontrado. Verifique seu e-mail e senha.
          </div>
        <?php endif; ?>
        <form method="POST" action="login.php">
          <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control" name="senha" required>
          </div>
          <button type="submit" class="btn btn-custom w-100">Entrar</button>
          <div class="text-center mt-3">
            <p>Não tem uma conta?</p>
            <a href="registrar.php" class="btn btn-outline-light">Criar conta</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input');
    const alertBox = document.querySelector('.alert-danger');

    if (alertBox) {
      inputs.forEach(input => {
        input.addEventListener('input', () => {
          alertBox.style.opacity = '0';
          alertBox.style.transition = 'opacity 0.3s ease';
          setTimeout(() => alertBox.remove(), 300);
        });
      });
    }
  });
</script>


<?php include('includes/footer.php'); ?>
