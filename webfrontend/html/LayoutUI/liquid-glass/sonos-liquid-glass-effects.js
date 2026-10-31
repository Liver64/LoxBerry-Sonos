(function (window, document, $) {
  'use strict';

  var FILTER_ID = 'lb-lg-distort';
  var SVG_CLASS = 'lb-liquid-glass-svg-filters';
  var filterRaf = null;
  var filterMotionUntil = 0;
  var activeGlassElements = [];
  var elementMotionRaf = null;

  var LAYER_CLASS_FILTER = 'lb-lg-glass-filter';
  var LAYER_CLASS_OVERLAY = 'lb-lg-glass-overlay';
  var LAYER_CLASS_SPECULAR = 'lb-lg-glass-specular';

  function isLiquidGlassTheme() {
    return document.body &&
      (
        document.body.classList.contains('theme-user-liquid-glass') ||
        document.body.classList.contains('theme-liquid-glass')
      );
  }

  function ensureLiquidGlassSvgFilter() {
    var existingFilter = document.getElementById(FILTER_ID);

    if (
      existingFilter &&
      document.getElementById('lb-lg-turbulence') &&
      document.getElementById('lb-lg-offset') &&
      document.getElementById('lb-lg-displace')
    ) {
      return;
    }

    /* Remove stale test filters from earlier experiments. */
    document.querySelectorAll('.' + SVG_CLASS).forEach(function (node) {
      node.remove();
    });

    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

    svg.setAttribute('class', SVG_CLASS);
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('width', '0');
    svg.setAttribute('height', '0');
    svg.setAttribute(
      'style',
      'position:absolute;width:0;height:0;overflow:hidden;pointer-events:none;'
    );

    svg.innerHTML =
      '<filter id="mini-liquid-lens" x="-35%" y="-35%" width="170%" height="170%"><feTurbulence type="fractalNoise" baseFrequency="0.016 0.021" numOctaves="2" seed="92" result="lensNoise"></feTurbulence><feDisplacementMap in="SourceGraphic" in2="lensNoise" scale="24" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap></filter>' +
      '<filter id="' + FILTER_ID + '" x="-35%" y="-35%" width="170%" height="170%" color-interpolation-filters="sRGB">' +
        '<feTurbulence id="lb-lg-turbulence" type="fractalNoise" baseFrequency="0.016 0.021" numOctaves="2" seed="92" result="noise"></feTurbulence>' +
        '<feOffset id="lb-lg-offset" in="noise" dx="0" dy="0" result="shiftedNoise"></feOffset>' +
        '<feGaussianBlur in="shiftedNoise" stdDeviation="1.4" result="blurredNoise"></feGaussianBlur>' +
        '<feDisplacementMap id="lb-lg-displace" in="SourceGraphic" in2="blurredNoise" scale="42" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap>' +
      '</filter>';

    document.body.insertBefore(svg, document.body.firstChild);
  }

  function kickLiquidGlassFilterMotion() {
    var turbulence = document.getElementById('lb-lg-turbulence');
    var offset = document.getElementById('lb-lg-offset');
    var displace = document.getElementById('lb-lg-displace');

    if (!turbulence || !offset || !displace) {
      return;
    }

    filterMotionUntil = window.performance.now() + 760;

    if (filterRaf) {
      return;
    }

    function tick(now) {
      var remaining = Math.max(0, filterMotionUntil - now);

      if (remaining <= 0) {
        turbulence.setAttribute('baseFrequency', '0.016 0.021');
        offset.setAttribute('dx', '0');
        offset.setAttribute('dy', '0');
        displace.setAttribute('scale', '42');
        filterRaf = null;
        return;
      }

      var phase = now / 1000;
      var strength = Math.min(1, remaining / 320);

      var fx = 0.016 + Math.sin(phase * 17.0) * 0.0046 * strength;
      var fy = 0.021 + Math.cos(phase * 13.0) * 0.0054 * strength;
      var dx = Math.sin(phase * 19.0) * 28 * strength;
      var dy = Math.cos(phase * 15.0) * 24 * strength;
      var scale = 42 + Math.sin(phase * 14.0) * 18 * strength;

      turbulence.setAttribute('baseFrequency', fx.toFixed(4) + ' ' + fy.toFixed(4));
      offset.setAttribute('dx', dx.toFixed(1));
      offset.setAttribute('dy', dy.toFixed(1));
      displace.setAttribute('scale', scale.toFixed(1));

      filterRaf = window.requestAnimationFrame(tick);
    }

    filterRaf = window.requestAnimationFrame(tick);
  }

  function isGlassLayerNode(node) {
    return node &&
      node.nodeType === 1 &&
      (
        node.classList.contains(LAYER_CLASS_FILTER) ||
        node.classList.contains(LAYER_CLASS_OVERLAY) ||
        node.classList.contains(LAYER_CLASS_SPECULAR)
      );
  }

  function addGlassLayerNodes(el) {
    if (!el || el.querySelector(':scope > .' + LAYER_CLASS_FILTER)) {
      return;
    }

    var filter = document.createElement('div');
    filter.className = LAYER_CLASS_FILTER;

    var overlay = document.createElement('div');
    overlay.className = LAYER_CLASS_OVERLAY;

    var specular = document.createElement('div');
    specular.className = LAYER_CLASS_SPECULAR;

    el.insertBefore(specular, el.firstChild);
    el.insertBefore(overlay, el.firstChild);
    el.insertBefore(filter, el.firstChild);
  }

  function isSonosStatusTextControl(el) {
    if (!el || !el.matches) {
      return false;
    }

    /* V242:
     * Sonos player/zone name fields are semantic status badges, not normal
     * editable form fields. Keep their surface under sonos_lbv4.css so the
     * online/offline colors win over Liquid Glass. This intentionally covers
     * the whole player/volume/soundbar zone field family, not only the online
     * class, because online status may be assigned after page initialization. */
    return el.matches([
      'input.player-zone-input',
      'input.volume-zone-input',
      'input.sb_topline_input',
      'input.zone-online',
      'input.player-zone-online',
      'input.volume-zone-online',
      'input.sb_topline_input_online',
      'input.lb-input.player-zone-input',
      'input.lb-input.volume-zone-input',
      'input.lb-input.zone-online',
      'input.lb-input.player-zone-online',
      'input.lb-input.volume-zone-online'
    ].join(','));
  }

  function unwrapNativeGlassControl(control) {
    if (!control || !control.closest) {
      return;
    }

    var wrapper = control.closest('.lb-lg-native-glass');

    if (!wrapper || control.parentNode !== wrapper || !wrapper.parentNode) {
      return;
    }

    delete control.dataset.lbLgNativeWrapped;
    control.setAttribute('data-lb-real-glass', 'off');

    wrapper.parentNode.insertBefore(control, wrapper);
    wrapper.remove();
  }

  function cleanSonosStatusControls(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selector = [
      'input.player-zone-input',
      'input.volume-zone-input',
      'input.sb_topline_input',
      'input.zone-online',
      'input.player-zone-online',
      'input.volume-zone-online',
      'input.sb_topline_input_online'
    ].join(',');

    forEachMatch(scope, selector, function (control) {
      control.setAttribute('data-lb-real-glass', 'off');
      unwrapNativeGlassControl(control);
    });
  }

  var sonosStatusObserverStarted = false;

  function observeSonosStatusControls() {
    if (sonosStatusObserverStarted || !window.MutationObserver || !document.body) {
      return;
    }

    sonosStatusObserverStarted = true;

    var observer = new window.MutationObserver(function (records) {
      records.forEach(function (record) {
        var target = record.target;

        if (target && target.nodeType === 1 && isSonosStatusTextControl(target)) {
          target.setAttribute('data-lb-real-glass', 'off');
          unwrapNativeGlassControl(target);
        }
      });
    });

    observer.observe(document.body, {
      subtree: true,
      attributes: true,
      attributeFilter: ['class']
    });
  }

  function shouldSkipContainerElement(el) {
    if (!el || el.classList.contains('lb-lg-real-glass')) {
      return true;
    }

    if (el.closest('.lb-liquid-glass-svg-filters')) {
      return true;
    }

    if (el.closest('.ui-flipswitch')) {
      return true;
    }

    if (el.closest('.ui-navbar')) {
      return true;
    }

    if (el.closest('.lb-tab-bar')) {
      return true;
    }

    if (el.closest('.lb-lg-native-glass')) {
      return true;
    }

    /* V242: jQM wrappers around Sonos status inputs must also stay clean. */
    if (
      el.matches('.ui-input-text, .ui-input-search') &&
      el.querySelector('input.player-zone-input, input.volume-zone-input, input.sb_topline_input, input.zone-online, input.player-zone-online, input.volume-zone-online, input.sb_topline_input_online')
    ) {
      return true;
    }

    /* V239: Button groups must stay visually calm/connected.
     * Do not inject real-glass layers into labels/buttons inside .lb-btn-group,
     * because the moving lens/distortion breaks the grouped pill appearance. */
    if (el.closest('.lb-btn-group')) {
      return true;
    }

    if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
      return true;
    }

    return false;
  }

  function initGlassElement(el) {
    if (!el) {
      return;
    }

    el.classList.add('lb-lg-real-glass');

    if (!el.dataset.lbLgSeed) {
      el.dataset.lbLgSeed = String(Math.random() * 1000);
    }

    addGlassLayerNodes(el);
    bindGlassMotion(el);
  }

  function addGlassLayers(el) {
    if (shouldSkipContainerElement(el)) {
      return;
    }

    initGlassElement(el);

    if (
      el.matches('.ui-btn, .lb-btn, button') &&
      !el.querySelector(':scope > .lb-lg-glass-content')
    ) {
      var content = document.createElement('span');
      content.className = 'lb-lg-glass-content';

      Array.from(el.childNodes).forEach(function (node) {
        if (isGlassLayerNode(node)) {
          return;
        }

        content.appendChild(node);
      });

      el.appendChild(content);
    }
  }

  function isAllowedNativeInputType(type) {
    var normalized = String(type || 'text').toLowerCase();

    return [
      'text',
      'password',
      'email',
      'url',
      'number',
      'tel',
      'search',
      'time',
      'date',
      'datetime-local',
      'month',
      'week',
      'button',
      'submit',
      'reset'
    ].indexOf(normalized) !== -1;
  }

  function isNativeGlassControl(el) {
    if (!el || el.dataset.lbLgNativeWrapped === '1') {
      return false;
    }

    if (el.closest('.lb-lg-native-glass')) {
      return false;
    }

    if (el.closest('.ui-input-text, .ui-input-search, .ui-select')) {
      return false;
    }

    if (el.closest('.lb-flipswitch, .ui-flipswitch, .lb-slider-wrap, .lb-liquid-slider-main')) {
      return false;
    }

    /* V239: Keep button groups free of native glass wrappers as well. */
    if (el.closest('.lb-btn-group')) {
      return false;
    }

    /* V242: Sonos status inputs are owned by sonos_lbv4.css. Never wrap them. */
    if (isSonosStatusTextControl(el)) {
      el.setAttribute('data-lb-real-glass', 'off');
      return false;
    }

    if (el.matches('.lb-slider, .irs-hidden-input, .no-jqm-flipswitch')) {
      return false;
    }

    if (el.matches('[data-lb-no-real-glass="1"], [data-lb-real-glass="off"]')) {
      return false;
    }

    if (el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
      return true;
    }

    if (el.tagName === 'INPUT') {
      return isAllowedNativeInputType(el.getAttribute('type'));
    }

    return false;
  }

  function copyNativeLayoutToWrapper(control, wrapper) {
    var style = control.style;

    [
      'width',
      'minWidth',
      'maxWidth',
      'height',
      'minHeight',
      'maxHeight',
      'flex',
      'flexBasis',
      'alignSelf',
      'verticalAlign'
    ].forEach(function (prop) {
      if (style[prop]) {
        wrapper.style[prop] = style[prop];
      }
    });

    [
      'margin',
      'marginTop',
      'marginRight',
      'marginBottom',
      'marginLeft'
    ].forEach(function (prop) {
      if (style[prop]) {
        wrapper.style[prop] = style[prop];
        style[prop] = '0';
      }
    });

    if (!wrapper.style.width && control.classList.contains('lb-select')) {
      wrapper.style.width = '100%';
    }
  }

  function syncNativeGlassState(control, wrapper) {
    if (!control || !wrapper) {
      return;
    }

    var disabled = !!control.disabled ||
      control.getAttribute('aria-disabled') === 'true' ||
      control.classList.contains('ui-disabled');

    wrapper.classList.toggle('lb-lg-is-disabled', disabled);
    wrapper.classList.toggle('lb-lg-is-readonly', !!control.readOnly);
  }

  function observeNativeGlassState(control, wrapper) {
    syncNativeGlassState(control, wrapper);

    if (!window.MutationObserver) {
      return;
    }

    var observer = new window.MutationObserver(function () {
      syncNativeGlassState(control, wrapper);
    });

    observer.observe(control, {
      attributes: true,
      attributeFilter: ['disabled', 'readonly', 'class', 'aria-disabled']
    });
  }

  function wrapNativeGlassControl(control) {
    if (!isNativeGlassControl(control)) {
      return;
    }

    var wrapper = document.createElement('span');
    wrapper.className = 'lb-lg-native-glass';

    if (control.tagName === 'SELECT') {
      wrapper.classList.add('lb-lg-native-select');
    } else if (control.tagName === 'TEXTAREA') {
      wrapper.classList.add('lb-lg-native-textarea');
    } else if (control.tagName === 'INPUT') {
      wrapper.classList.add('lb-lg-native-input');

      if (String(control.getAttribute('type') || 'text').toLowerCase() === 'time') {
        wrapper.classList.add('lb-lg-native-time');
      }

      if (['button', 'submit', 'reset'].indexOf(String(control.getAttribute('type') || '').toLowerCase()) !== -1) {
        wrapper.classList.add('lb-lg-native-button');
      }
    }

    control.dataset.lbLgNativeWrapped = '1';
    copyNativeLayoutToWrapper(control, wrapper);

    control.parentNode.insertBefore(wrapper, control);
    wrapper.appendChild(control);

    initGlassElement(wrapper);
    observeNativeGlassState(control, wrapper);
  }

  function kickElementWaveMotion(el) {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (!el || el.classList.contains('lb-lg-is-disabled')) {
      return;
    }

    el.classList.add('lb-lg-is-moving');
    el._lbLgMotionUntil = window.performance.now() + 760;

    if (activeGlassElements.indexOf(el) === -1) {
      activeGlassElements.push(el);
    }

    if (!elementMotionRaf) {
      elementMotionRaf = window.requestAnimationFrame(tickElementWaveMotion);
    }
  }

  function tickElementWaveMotion(now) {
    var stillActive = [];

    activeGlassElements.forEach(function (el) {
      var until = el._lbLgMotionUntil || 0;
      var remaining = Math.max(0, until - now);

      if (remaining <= 0 || el.classList.contains('lb-lg-is-disabled')) {
        el.classList.remove('lb-lg-is-moving');
        el.style.removeProperty('--lb-lg-wave-x');
        el.style.removeProperty('--lb-lg-wave-y');
        el.style.removeProperty('--lb-lg-wave-rotate');
        el.style.removeProperty('--lb-lg-wave-scale');
        return;
      }

      var seed = parseFloat(el.dataset.lbLgSeed || '0') || 0;
      var phase = now / 1000 + seed;
      var strength = Math.min(1, remaining / 320);

      var waveX = Math.sin(phase * 14.0) * 10 * strength;
      var waveY = Math.cos(phase * 11.0) * 8 * strength;
      var waveRotate = Math.sin(phase * 8.0) * 1.8 * strength;
      var waveScale = 1.075 + (0.018 * strength);

      el.style.setProperty('--lb-lg-wave-x', waveX.toFixed(2) + 'px');
      el.style.setProperty('--lb-lg-wave-y', waveY.toFixed(2) + 'px');
      el.style.setProperty('--lb-lg-wave-rotate', waveRotate.toFixed(2) + 'deg');
      el.style.setProperty('--lb-lg-wave-scale', waveScale.toFixed(3));

      stillActive.push(el);
    });

    activeGlassElements = stillActive;

    if (activeGlassElements.length) {
      elementMotionRaf = window.requestAnimationFrame(tickElementWaveMotion);
    } else {
      elementMotionRaf = null;
    }
  }

  function bindGlassMotion(el) {
    if (!el || el.dataset.lbLgMotionBound === '1') {
      return;
    }

    el.dataset.lbLgMotionBound = '1';

    function startMotion(event) {
      if (el.classList.contains('lb-lg-is-disabled')) {
        return;
      }

      /* V253:
       * Do not animate the shared SVG filter on every click/focus. The filter
       * is used by all Liquid-Glass elements, so changing its turbulence/offset
       * makes the complete page wobble for the animation duration. Keep the
       * global filter static and only start the local element wave. */
      kickElementWaveMotion(el);
    }

    function startKeyboardMotion(event) {
      if (!event) {
        return;
      }

      var key = event.key || '';

      if (key === 'Enter' || key === ' ' || key === 'Spacebar' || key === 'ArrowUp' || key === 'ArrowDown' || key === 'ArrowLeft' || key === 'ArrowRight') {
        startMotion(event);
      }
    }

    /*
     * V233: Hover and plain mouse movement must stay calm.
     * pointermove/pointerenter are intentionally not bound here because browsers
     * fire synthetic pointermove events while entering native controls. Those
     * events caused the whole surface to wobble although the user only hovered.
     * Motion now starts only on explicit interaction: click/touch/focus/keyboard.
     */
    el.addEventListener('pointerdown', startMotion);
    el.addEventListener('focusin', startMotion);
    el.addEventListener('keydown', startKeyboardMotion);
  }

  function forEachMatch(scope, selector, callback) {
    if (!scope) {
      return;
    }

    if (scope.nodeType === 1 && scope.matches && scope.matches(selector)) {
      callback(scope);
    }

    if (scope.querySelectorAll) {
      scope.querySelectorAll(selector).forEach(callback);
    }
  }

  function enhanceLiquidGlassControls(root) {
    if (!isLiquidGlassTheme()) {
      return;
    }

    ensureLiquidGlassSvgFilter();

    var scope = root && root.querySelectorAll ? root : document;

    var containerSelector = [
      '.lb-content .ui-btn',
      '.lb-content .lb-btn',
      '.lb-content button',
      '.lb-content .ui-input-text',
      '.lb-content .ui-input-search',
      '.lb-content .ui-select .ui-btn'
    ].join(',');

    var nativeSelector = [
      '.lb-content input.lb-input',
      '.lb-content input.textfield',
      '.lb-content input.form-control',
      '.lb-content input.player-time-input',
      '.lb-content input[type="text"]',
      '.lb-content input[type="password"]',
      '.lb-content input[type="email"]',
      '.lb-content input[type="url"]',
      '.lb-content input[type="number"]',
      '.lb-content input[type="tel"]',
      '.lb-content input[type="search"]',
      '.lb-content input[type="time"]',
      '.lb-content input[type="date"]',
      '.lb-content input[type="datetime-local"]',
      '.lb-content input[type="month"]',
      '.lb-content input[type="week"]',
      '.lb-content input[type="button"].lb-btn',
      '.lb-content input[type="submit"].lb-btn',
      '.lb-content input[type="reset"].lb-btn',
      '.lb-content textarea',
      '.lb-content select.lb-select',
      '.lb-content select'
    ].join(',');

    observeSonosStatusControls();
    cleanSonosStatusControls(scope);

    forEachMatch(scope, containerSelector, addGlassLayers);
    forEachMatch(scope, nativeSelector, wrapNativeGlassControl);

    /* Run once more after wrapping, so any late status class wins. */
    cleanSonosStatusControls(scope);
  }

  function runLater(root) {
    window.setTimeout(function () {
      enhanceLiquidGlassControls(root || document);
    }, 0);

    window.setTimeout(function () {
      enhanceLiquidGlassControls(root || document);
    }, 150);

    window.setTimeout(function () {
      enhanceLiquidGlassControls(root || document);
    }, 450);
  }

  window.lbEnhanceLiquidGlassControls = enhanceLiquidGlassControls;
  window.lbKickLiquidGlassFilterMotion = kickLiquidGlassFilterMotion;

  document.addEventListener('DOMContentLoaded', function () {
    runLater(document);
  });

  window.addEventListener('load', function () {
    runLater(document);
  });

  if ($ && $(document).on) {
    $(document).on('pageinit pagecreate pageshow', function (event) {
      runLater(event.target || document);
    });

    $(document).on('create', function (event) {
      runLater(event.target || document);
    });
  }

})(window, document, window.jQuery);