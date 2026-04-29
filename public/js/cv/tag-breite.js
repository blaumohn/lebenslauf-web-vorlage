(function () {
  function maxTextBreite(el) {
    const range = document.createRange();
    range.selectNodeContents(el);
    const rects = [...range.getClientRects()];
    if (!rects.length) return 0;
    return Math.max(...rects.map(function (r) { return r.width; }));
  }

  function tagBreitenAnpassen() {
    document.querySelectorAll('.tag').forEach(function (el) {
      el.style.width = '';
      const style = getComputedStyle(el);
      const padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
      el.style.width = Math.ceil(maxTextBreite(el) + padding) + 'px';
    });
  }

  tagBreitenAnpassen();
  window.addEventListener('resize', tagBreitenAnpassen);
})();
