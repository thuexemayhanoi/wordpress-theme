(function () {
  'use strict';
  var root = document.documentElement;
  var key = 'nh-editorial-color-mode';
  var themeButton = document.getElementById('theme-toggle');
  var menuButton = document.getElementById('menu-toggle');
  var navigation = document.getElementById('site-navigation');
  try {
    var mode = window.localStorage.getItem(key);
    if (mode === 'dark' || mode === 'light') root.setAttribute('data-theme', mode);
  } catch (e) { /* Storage may be unavailable. */ }
  if (themeButton) {
    themeButton.addEventListener('click', function () {
      var nextMode = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', nextMode);
      try { window.localStorage.setItem(key, nextMode); } catch (e) { /* No persistence */ }
    });
  }
  if (menuButton && navigation) {
    menuButton.addEventListener('click', function () {
      var opened = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', opened ? 'true' : 'false');
      menuButton.textContent = opened ? '✕' : '☰';
    });
    navigation.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.textContent = '☰';
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.textContent = '☰';
      }
    });
  }
}());
