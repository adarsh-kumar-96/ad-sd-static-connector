/**
 * AD-SD WP Static Connector — Shortcode Loader v1.1.2
 *
 * Include this file once per page: <script src="/adsd-loader.js" defer></script>
 * Then add as many [data-adsd-sc] divs as you need anywhere in the HTML.
 * Content loads automatically and stays updated when WordPress changes.
 */
(function () {
  'use strict';

  function execScripts(el) {
    var scripts = el.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
      var old = scripts[i];
      var s = document.createElement('script');
      if (old.src) { s.src = old.src; s.async = false; }
      else { s.textContent = old.textContent; }
      old.parentNode.replaceChild(s, old);
    }
  }

  function loadBlock(el) {
    var sc = el.getAttribute('data-adsd-sc');
    if (!sc || el.getAttribute('data-adsd-loaded')) return;
    el.setAttribute('data-adsd-loaded', '1');

    var url = window.location.origin + '/adsd-sc/?sc=' + encodeURIComponent(sc);

    fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        el.innerHTML = html;
        execScripts(el);
      })
      .catch(function (e) {
        console.error('[ADSD] Failed to load from', url, ':', e.message);
      });
  }

  function init() {
    var blocks = document.querySelectorAll('[data-adsd-sc]');
    for (var i = 0; i < blocks.length; i++) {
      loadBlock(blocks[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
