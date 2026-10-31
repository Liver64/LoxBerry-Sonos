/* Sonos4Lox: connect the CSS Studio glass slider to existing Ion.RangeSlider fields. */
(function (window, document) {
    'use strict';
    var queued = false;
    function active() {
        return document.body && (document.body.classList.contains('theme-liquid-glass') ||
            document.body.classList.contains('theme-user-liquid-glass'));
    }
    function enhanceSliders() {
        var $ = window.jQuery;
        if (!active() || !$ || !window.initLbLiquidGlassSlider) return;
        document.querySelectorAll('input').forEach(function (source) {
            var ion = $(source).data('ionRangeSlider');
            if (!ion || ion.options.type === 'double' || !source.id || source.dataset.s4loxGlassSlider) return;
            var container = ion.$cache && ion.$cache.cont && ion.$cache.cont[0];
            if (!container || !container.parentNode) return;
            var outer = document.createElement('div');
            outer.className = 's4lox-glass-slider';
            var id = source.id + '__glass';
            outer.innerHTML = '<div class="lb-slider-wrap lb-slider-wrap-liquid-glass">' +
                '<input type="range" class="lb-slider" data-role="none">' +
                '<div class="lb-liquid-slider-main"><div class="slider-wrapper"><div class="slider-container">' +
                '<div class="slider-progress"></div><div class="slider-thumb-glass" role="slider" tabindex="0">' +
                '<div class="slider-tooltip"></div><div class="slider-thumb-glass-filter"></div>' +
                '<div class="slider-thumb-glass-overlay"></div><div class="slider-thumb-glass-specular"></div>' +
                '</div></div></div></div><span class="lb-slider-value"></span></div>';
            var input = outer.querySelector('input');
            var root = outer.querySelector('.slider-wrapper');
            var thumb = outer.querySelector('[role="slider"]');
            input.id = id;
            input.min = ion.options.min;
            input.max = ion.options.max;
            input.step = ion.options.step;
            input.value = ion.result.from;
            root.setAttribute('data-lb-liquid-slider-for', id);
            outer.querySelector('.lb-slider-value').id = id + '_value';
            var label = source.getAttribute('aria-label');
            var labelledby = source.getAttribute('aria-labelledby');
            var explicitLabel = Array.from(document.querySelectorAll('label[for]')).find(function (node) { return node.htmlFor === source.id; });
            var nearbyLabel = source.closest('tr') && source.closest('tr').querySelector('label');
            if (!label && !labelledby && (explicitLabel || nearbyLabel)) label = (explicitLabel || nearbyLabel).textContent.trim();
            if (labelledby) thumb.setAttribute('aria-labelledby', labelledby);
            else thumb.setAttribute('aria-label', label || source.name || source.id);
            var syncing = false;
            var signature = '';
            function sync() {
                input.min = ion.options.min;
                input.max = ion.options.max;
                input.step = ion.options.step;
                input.disabled = !!(source.disabled || ion.options.disable || ion.options.block);
                thumb.setAttribute('aria-disabled', String(input.disabled));
                thumb.tabIndex = input.disabled ? -1 : 0;
                var next = [ion.result.from, input.min, input.max, input.step, input.disabled].join('|');
                if (signature !== next) {
                    signature = next;
                    input.value = ion.result.from;
                    input.dispatchEvent(new Event('input', {bubbles: false}));
                }
            }
            input.addEventListener('input', function () {
                if (syncing || input.disabled) return;
                syncing = true;
                try {
                    var value = Number(input.value);
                    if (Number(ion.result.from) !== value) {
                        ion.update({from: value});
                        if (typeof ion.options.onChange === 'function') ion.options.onChange.call(ion, ion.result);
                    }
                    $(source).trigger('change');
                } finally { syncing = false; }
            });
            input.addEventListener('change', function () {
                if (!input.disabled && typeof ion.options.onFinish === 'function') ion.options.onFinish.call(ion, ion.result);
            });
            container.parentNode.insertBefore(outer, container);
            try {
                window.initLbLiquidGlassSlider({id: id, valueId: id + '_value', fallback: ion.result.from,
                    prefix: ion.options.prefix || '', suffix: ion.options.postfix || '', locale: document.documentElement.lang || 'de-DE'});
            } catch (error) { outer.remove(); return; }
            source.dataset.s4loxGlassSlider = '1';
            container.classList.add('s4lox-glass-slider-original');
            var originalUpdate = ion.update;
            ion.update = function () {
                var result = originalUpdate.apply(this, arguments);
                container = ion.$cache.cont[0];
                container.classList.add('s4lox-glass-slider-original');
                if (!syncing) { syncing = true; try { sync(); } finally { syncing = false; } }
                return result;
            };
            $(source).on('change.s4loxGlass', function () {
                if (syncing) return;
                syncing = true; try { sync(); } finally { syncing = false; }
            });
            syncing = true; try { sync(); } finally { syncing = false; }
        });
    }
    function schedule() {
        if (queued) return;
        queued = true;
        window.setTimeout(function () {
            queued = false;
            enhanceSliders();
        }, 0);
    }
    function start() {
        if (!active()) return;
        enhanceSliders();
        if (window.MutationObserver) new MutationObserver(function (records) {
            if (records.some(function (record) { return Array.from(record.addedNodes).some(function (node) {
                return node.nodeType === 1 && (node.matches('input, .irs') || node.querySelector('input, .irs'));
            }); })) schedule();
        }).observe(document.body, {childList: true, subtree: true});
        if (window.jQuery) window.jQuery(document).on('pageinit pagecreate pageshow', schedule);
        window.setTimeout(schedule, 200);
    }
    window.s4loxEnhanceGlassSliders = enhanceSliders;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})(window, document);
