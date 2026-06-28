(function () {
  if (!document.documentElement.classList.contains('tv-mode')) {
    return;
  }

  var focusables = [];
  var currentIndex = -1;

  function collectFocusables() {
    focusables = Array.prototype.slice.call(
      document.querySelectorAll('[data-pm-tv-focus]:not([disabled]):not([hidden])')
    ).filter(function (el) {
      return el.offsetParent !== null || el === document.activeElement;
    });
  }

  function focusAt(index) {
    if (!focusables.length) {
      return;
    }
    if (index < 0) {
      index = focusables.length - 1;
    }
    if (index >= focusables.length) {
      index = 0;
    }
    currentIndex = index;
    focusables[currentIndex].focus();
  }

  function moveFocus(delta) {
    collectFocusables();
    if (!focusables.length) {
      return;
    }
    var next = currentIndex;
    if (next < 0) {
      next = focusables.indexOf(document.activeElement);
    }
    focusAt(next + delta);
  }

  document.addEventListener('keydown', function (evt) {
    var key = evt.key;
    if (key === 'ArrowRight' || key === 'ArrowDown') {
      evt.preventDefault();
      moveFocus(1);
      return;
    }
    if (key === 'ArrowLeft' || key === 'ArrowUp') {
      evt.preventDefault();
      moveFocus(-1);
      return;
    }
  });

  document.addEventListener('focusin', function (evt) {
    var target = evt.target;
    if (!target || !target.hasAttribute('data-pm-tv-focus')) {
      return;
    }
    collectFocusables();
    var idx = focusables.indexOf(target);
    if (idx >= 0) {
      currentIndex = idx;
    }
  });

  window.addEventListener('load', function () {
    collectFocusables();
    if (focusables.length && document.activeElement === document.body) {
      focusAt(0);
    }
  });
})();
