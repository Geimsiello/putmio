(function () {
  'use strict';

  if (!document.documentElement.classList.contains('tv-site')) {
    return;
  }

  var FOCUS_CLASS = 'tv-focused';
  var zones = [];
  var zoneIndex = 0;
  var itemIndex = 0;

  function $(id) {
    return document.getElementById(id);
  }

  function isVisible(el) {
    return el && (el.offsetParent !== null || el.getAttribute('hidden') === null);
  }

  function collectZones() {
    zones = [];
    var header = document.querySelector('[data-tv-zone="header"]');
    if (header) {
      zones.push({
        type: 'header',
        el: header,
        items: Array.prototype.slice.call(header.querySelectorAll('[data-tv-focus]')).filter(isVisible),
      });
    }
    document.querySelectorAll('[data-tv-zone="row"]').forEach(function (row) {
      var items = Array.prototype.slice.call(row.querySelectorAll('[data-tv-focus]')).filter(isVisible);
      if (items.length) {
        zones.push({ type: 'row', el: row, items: items, track: row.querySelector('[data-tv-row-track]') });
      }
    });
    var standalone = document.querySelectorAll('.tv-main [data-tv-focus]');
    var inZones = new Set();
    zones.forEach(function (z) {
      z.items.forEach(function (item) {
        inZones.add(item);
      });
    });
    var loose = Array.prototype.slice.call(standalone).filter(function (el) {
      return isVisible(el) && !inZones.has(el);
    });
    if (loose.length) {
      zones.push({ type: 'loose', el: document.querySelector('.tv-main'), items: loose });
    }
  }

  function clearFocus() {
    document.querySelectorAll('.' + FOCUS_CLASS).forEach(function (el) {
      el.classList.remove(FOCUS_CLASS);
    });
  }

  function updateInfoRail(el) {
    var rail = $('tv-info-rail');
    if (!rail) return;
    var title = $('tv-info-rail-title');
    var meta = $('tv-info-rail-meta');
    var synopsis = $('tv-info-rail-synopsis');
    if (!el || !el.hasAttribute('data-tv-title')) {
      rail.hidden = true;
      return;
    }
    if (title) title.textContent = el.getAttribute('data-tv-title') || '';
    if (meta) meta.textContent = el.getAttribute('data-tv-meta') || '';
    if (synopsis) synopsis.textContent = el.getAttribute('data-tv-synopsis') || '';
    rail.hidden = false;
  }

  function scrollItemIntoRow(item, track) {
    if (!item || !track) return;
    var left = item.offsetLeft - track.offsetLeft;
    var pad = 48;
    var target = left - pad;
    if (target < 0) target = 0;
    var max = track.scrollWidth - track.clientWidth;
    if (target > max) target = max;
    track.scrollLeft = target;
  }

  function focusCurrent() {
    clearFocus();
    var zone = zones[zoneIndex];
    if (!zone || !zone.items.length) return;
    if (itemIndex < 0) itemIndex = 0;
    if (itemIndex >= zone.items.length) itemIndex = zone.items.length - 1;
    var el = zone.items[itemIndex];
    el.classList.add(FOCUS_CLASS);
    el.focus({ preventScroll: true });
    if (zone.type === 'row' && zone.track) {
      scrollItemIntoRow(el, zone.track);
    } else {
      try {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
      } catch (e) {
        el.scrollIntoView(false);
      }
    }
    updateInfoRail(el);
  }

  function moveHorizontal(delta) {
    var zone = zones[zoneIndex];
    if (!zone) return;
    itemIndex += delta;
    if (itemIndex < 0) itemIndex = 0;
    if (itemIndex >= zone.items.length) itemIndex = zone.items.length - 1;
    focusCurrent();
  }

  function moveVertical(delta) {
    var prevZone = zoneIndex;
    var prevItem = itemIndex;
    zoneIndex += delta;
    if (zoneIndex < 0) zoneIndex = 0;
    if (zoneIndex >= zones.length) zoneIndex = zones.length - 1;
    if (zones[zoneIndex]) {
      itemIndex = Math.min(prevItem, zones[zoneIndex].items.length - 1);
      if (zoneIndex !== prevZone && zones[zoneIndex].items.length <= itemIndex) {
        itemIndex = 0;
      }
    }
    focusCurrent();
  }

  document.addEventListener('keydown', function (evt) {
    if (evt.key === 'ArrowRight') {
      evt.preventDefault();
      moveHorizontal(1);
      return;
    }
    if (evt.key === 'ArrowLeft') {
      evt.preventDefault();
      moveHorizontal(-1);
      return;
    }
    if (evt.key === 'ArrowDown') {
      evt.preventDefault();
      moveVertical(1);
      return;
    }
    if (evt.key === 'ArrowUp') {
      evt.preventDefault();
      moveVertical(-1);
      return;
    }
  });

  document.addEventListener('focusin', function (evt) {
    var target = evt.target;
    if (!target || !target.hasAttribute('data-tv-focus')) return;
    collectZones();
    for (var z = 0; z < zones.length; z++) {
      var idx = zones[z].items.indexOf(target);
      if (idx >= 0) {
        zoneIndex = z;
        itemIndex = idx;
        clearFocus();
        target.classList.add(FOCUS_CLASS);
        updateInfoRail(target);
        break;
      }
    }
  });

  function init() {
    collectZones();
    if (zones.length && zones[0].items.length) {
      zoneIndex = 0;
      itemIndex = 0;
      focusCurrent();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
