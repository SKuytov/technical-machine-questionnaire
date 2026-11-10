(function () {
  'use strict';

  // Потвърждение преди опасни действия
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
  });

  // Автоматично изпращане при смяна на роля
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // Показване на броя избрани файлове
  document.querySelectorAll('.file-pick input[type=file]').forEach(function (inp) {
    var label = inp.parentNode.querySelector('span');
    var original = label.textContent;
    inp.addEventListener('change', function () {
      var n = inp.files ? inp.files.length : 0;
      label.textContent = n ? ('Избрани: ' + n + (n === 1 ? ' файл — ' + inp.files[0].name : ' файла')) : original;
      inp.parentNode.classList.toggle('has', n > 0);
    });
  });

  // Предупреждение при незапазени промени в отговор
  var dirty = new Set();
  document.querySelectorAll('form[data-dirty-guard]').forEach(function (f) {
    var mark = function () { dirty.add(f); };
    f.addEventListener('input', mark);
    f.addEventListener('change', mark);
    f.addEventListener('submit', function () { dirty.clear(); });
  });
  document.querySelectorAll('form').forEach(function (f) {
    if (!f.hasAttribute('data-dirty-guard')) {
      f.addEventListener('submit', function (ev) {
        if (dirty.size && !ev.defaultPrevented &&
            !window.confirm('Имате незапазени отговори. Да продължа ли без да ги запазя?')) {
          ev.preventDefault();
        } else if (!ev.defaultPrevented) {
          dirty.clear();
        }
      });
    }
  });
  window.addEventListener('beforeunload', function (ev) {
    if (dirty.size) { ev.preventDefault(); ev.returnValue = ''; }
  });

  // Предпазване от двойно изпращане
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) { return; }
      var b = f.querySelector('button[type=submit]');
      if (b) { setTimeout(function () { b.disabled = true; }, 0); }
    });
  });
})();
