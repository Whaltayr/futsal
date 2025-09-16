<?php
// includes/footer.php

// includes/header.php
require_once __DIR__ . '/utils.php'; // does nothing if already loaded
$year = date('Y');

?>
<header>
<footer class="site-footer">
  <div class="footer-inner">
    <section class="f-brand">
      <a class="brand" href="index.php">
        <img src="assets/img/logo.jpeg" alt="Logo" class="brand-logo" />
        <span class="brand-name">Futsal</span>
      </a>
      <p class="brand-desc">Resultados, tabelas e jogos do seu torneio de futsal.</p>
    </section>

    <nav class="f-links" aria-label="Links rápidos">
      <h4>Links</h4>
      <ul>
        <li><a href="index.php">Classificação</a></li>
        <li><a href="last_matches.php">Jogos Passados</a></li>
      </ul>
    </nav>

    <section class="f-contact">
      <h4>Contacto</h4>
      <ul>
        <li><a href="mailto:contato@seu-dominio.tld">contato@seu-dominio.tld</a></li>
        <li><a href="tel:+351000000000">+351 000 000 000</a></li>
      </ul>
      <div class="f-social" aria-label="Redes sociais">
        <a href="#" aria-label="Instagram" class="social">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <path d="M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm10 2H7a3 3 0 00-3 3v10a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3zm-5 3.5A5.5 5.5 0 1112 18a5.5 5.5 0 010-11zm0 2A3.5 3.5 0 1015.5 13 3.5 3.5 0 0012 7.5zM18 7.25a.75.75 0 11.75-.75.75.75 0 01-.75.75z"/>
          </svg>
        </a>
        <a href="#" aria-label="Facebook" class="social">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <path d="M13 10h3l-1 4h-2v8h-4v-8H7v-4h2V8a4 4 0 014-4h3v4h-3a1 1 0 00-1 1z"/>
          </svg>
        </a>
        <a href="#" aria-label="Twitter/X" class="social">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <path d="M3 3l7.5 9L3 21h4.5L12 14.4 16.5 21H21l-7.5-9L21 3h-4.5L12 9.6 7.5 3z"/>
          </svg>
        </a>
      </div>
    </section>
  </div>
  <div class="f-bottom">
    <p>© <?= htmlspecialchars($year) ?> Futsal — Todos os direitos reservados.</p>
  </div>
</footer>