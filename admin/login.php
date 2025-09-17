<?php
declare(strict_types=1);

require __DIR__ . '/../src/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals(CMS_ADMIN_USER, $_POST['user'] ?? '') &&
        password_verify($_POST['pass'] ?? '', CMS_ADMIN_HASH)) {
        $_SESSION['cms_auth'] = true;
        header('Location: /admin/edit.php');
        exit;
    }

    $error = 'Invalid credentials';
}

?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="robots" content="noindex,nofollow">
    <link href="/styles/fonts.css" rel="stylesheet">
    <link href="/styles/bootstrap.min.css" rel="stylesheet">
    <link href="/styles/main.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<main class="container">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 420px;">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center">Anmeldung</h1>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="user" class="form-label">Benutzername</label>
                            <input
                                id="user"
                                name="user"
                                type="text"
                                class="form-control"
                                autocomplete="username"
                                required
                            >
                            <div class="invalid-feedback">Bitte Benutzername eingeben.</div>
                        </div>

                        <div class="mb-3">
                            <label for="pass" class="form-label">Passwort</label>
                            <input
                                id="pass"
                                name="pass"
                                type="password"
                                class="form-control"
                                autocomplete="current-password"
                                required
                            >
                            <div class="invalid-feedback">Bitte Passwort eingeben.</div>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">Anmelden</button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 text-body-secondary small">© 2025</p>
        </div>
    </div>
</main>
<script src="/scripts/bootstrap.bundle.min.js"></script>
<script>
// Enable Bootstrap client-side validation styling
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.prototype.forEach.call(forms, form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>
