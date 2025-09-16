<?php
// includes/header.php
// includes/header.php
require_once __DIR__ . '/utils.php'; // does nothing if already loaded
?>

<header>
  <section class="middle-section-header">
    <nav class="site-nav">
      <div class="logo"><a href="index.php"><img src="assets/img/logo.jpeg" alt="Início"></a></div>
      <div class="links" id="primary-nav">
        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>" href="index.php">Classificação</a>
        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'last_matches.php' ? ' active' : '' ?>" href="last_matches.php">Jogos Passados</a>
      </div>
    </nav>
  </section>
  <section class="bottom-section-header overlay"></section>
</header>