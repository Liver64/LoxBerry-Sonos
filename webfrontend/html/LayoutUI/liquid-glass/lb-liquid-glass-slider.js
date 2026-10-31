/* LoxBerry CSSFramework - Liquid Glass Slider helper V261
 * Path: webfrontend/html/plugins/cssframework/js/liquid-glass/lb-liquid-glass-slider.js
 *
 * Adds a Liquid Glass visual slider surface for existing LoxBerry Design System
 * range inputs. The native input[type="range"].lb-slider remains the real form
 * field and source of truth. This file intentionally contains no CSS and makes
 * no Core changes.
 */

(function (window, document) {
	'use strict';

	function toNumber(value, fallback) {
		var number = Number(value);
		return isNaN(number) ? fallback : number;
	}

	function dispatchSliderEvents(input) {
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function getRootForInput(input, id) {
		var root = document.querySelector('[data-lb-liquid-slider-for="' + id + '"]');

		if (root) {
			return root;
		}

		var wrap = input.closest ? input.closest('.lb-slider-wrap') : null;

		if (!wrap) {
			return null;
		}

		return wrap.querySelector('.slider-wrapper');
	}

	function getDecimals(step) {
		var text = String(step);
		var index = text.indexOf('.');

		if (index === -1) {
			return 0;
		}

		return text.length - index - 1;
	}

	function initLbLiquidGlassSlider(options) {
		if (!options || !options.id) {
			return;
		}

		var input = document.getElementById(options.id);

		if (!input) {
			return;
		}

		var root = getRootForInput(input, options.id);

		if (!root) {
			return;
		}

		var slider = root.querySelector('.slider-container');
		var thumb = root.querySelector('.slider-thumb-glass');
		var progress = root.querySelector('.slider-progress');
		var tooltip = root.querySelector('.slider-tooltip');
		var valueDisplay = options.valueId ? document.getElementById(options.valueId) : null;

		if (!slider || !thumb || !progress || !tooltip) {
			return;
		}

		if (root.getAttribute('data-lb-liquid-slider-ready') === '1') {
			return;
		}

		root.setAttribute('data-lb-liquid-slider-ready', '1');

		var min = toNumber(input.getAttribute('min'), 0);
		var max = toNumber(input.getAttribute('max'), 100);
		var stepAttr = input.getAttribute('step');
		var step = stepAttr && stepAttr !== 'any' ? toNumber(stepAttr, 1) : 1;
		var fallback = toNumber(options.fallback, min);
		var prefix = options.prefix || root.getAttribute('data-prefix') || '';
		var suffix = options.suffix || root.getAttribute('data-suffix') || '';
		var locale = options.locale || root.getAttribute('data-locale') || 'de-DE';
		var decimals = getDecimals(step);
		var isDragging = false;
		var sliderRect = null;

		function clamp(value) {
			return Math.max(min, Math.min(max, value));
		}

		function snap(value) {
			if (!step || step <= 0) {
				return value;
			}

			var snapped = min + Math.round((value - min) / step) * step;
			return Number(snapped.toFixed(decimals));
		}

		function getValue() {
			return clamp(snap(toNumber(input.value, fallback)));
		}

		function formatValue(value) {
			return prefix + Number(value).toLocaleString(locale) + suffix;
		}

		function valueToPercent(value) {
			if (max === min) {
				return 0;
			}

			return ((value - min) / (max - min)) * 100;
		}

		function percentToValue(percent) {
			return clamp(snap(min + ((max - min) * percent / 100)));
		}

		function readSliderRect() {
			sliderRect = slider.getBoundingClientRect();
			return sliderRect;
		}

		function render(value) {
			value = clamp(snap(value));

			var percent = valueToPercent(value);

			/* V261: Keep the thumb position value-based, not pixel-width-based.
			 * Some Sonos DETAILS rows are hidden during first initialization. In that
			 * state getBoundingClientRect().width is 0, which previously locked the
			 * thumb at 0px although the progress/value badge were already correct.
			 * Percent positioning survives hidden -> visible transitions and stays
			 * aligned with the saved input value. */
			progress.style.width = percent + '%';
			thumb.style.left = percent + '%';
			tooltip.textContent = formatValue(value);

			thumb.setAttribute('role', 'slider');
			thumb.setAttribute('tabindex', '0');
			thumb.setAttribute('aria-valuemin', String(min));
			thumb.setAttribute('aria-valuemax', String(max));
			thumb.setAttribute('aria-valuenow', String(value));

			if (input.id) {
				thumb.setAttribute('aria-controls', input.id);
			}
		}

		function setValue(value, triggerEvents) {
			value = clamp(snap(value));
			input.value = value;

			if (valueDisplay) {
				valueDisplay.textContent = formatValue(value);
			}

			render(value);

			if (triggerEvents) {
				dispatchSliderEvents(input);
			}
		}

		function updatePosition(clientX) {
			var rect = readSliderRect();
			var percent = ((clientX - rect.left) / rect.width) * 100;
			percent = Math.max(0, Math.min(100, percent));
			setValue(percentToValue(percent), true);
		}

		function startDrag(clientX) {
            if (input.disabled || input.readOnly) return;
			readSliderRect();
			isDragging = true;
			thumb.classList.add('active');
			updatePosition(clientX);
		}

		function stopDrag() {
			if (!isDragging) {
				return;
			}

			isDragging = false;
			thumb.classList.remove('active');
		}

		slider.addEventListener('mousedown', function (event) {
			event.preventDefault();
			startDrag(event.clientX);
		});

		document.addEventListener('mousemove', function (event) {
			if (!isDragging) {
				return;
			}

			event.preventDefault();
			updatePosition(event.clientX);
		});

		document.addEventListener('mouseup', stopDrag);

		slider.addEventListener('touchstart', function (event) {
			if (!event.touches || !event.touches.length) {
				return;
			}

			startDrag(event.touches[0].clientX);
		}, { passive: true });

		document.addEventListener('touchmove', function (event) {
			if (!isDragging || !event.touches || !event.touches.length) {
				return;
			}

			updatePosition(event.touches[0].clientX);
		}, { passive: true });

		document.addEventListener('touchend', stopDrag);
		document.addEventListener('touchcancel', stopDrag);

		thumb.addEventListener('keydown', function (event) {
            if (input.disabled || input.readOnly) return;
			var value = getValue();

			if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
				event.preventDefault();
				setValue(value - step, true);
				return;
			}

			if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
				event.preventDefault();
				setValue(value + step, true);
				return;
			}

			if (event.key === 'Home') {
				event.preventDefault();
				setValue(min, true);
				return;
			}

			if (event.key === 'End') {
				event.preventDefault();
				setValue(max, true);
			}
		});

		input.addEventListener('input', function () {
			readSliderRect();
			setValue(getValue(), false);
		});

		input.addEventListener('change', function () {
			readSliderRect();
			setValue(getValue(), false);
		});

		window.addEventListener('resize', function () {
			readSliderRect();
			render(getValue());
		});

		readSliderRect();
		setValue(getValue(), false);
	}

	function initLbLiquidGlassSliders() {
		var roots = document.querySelectorAll('[data-lb-liquid-slider-for]');

		Array.prototype.forEach.call(roots, function (root) {
			var id = root.getAttribute('data-lb-liquid-slider-for');

			if (!id) {
				return;
			}

			initLbLiquidGlassSlider({
				id: id,
				valueId: root.getAttribute('data-lb-liquid-slider-value') || '',
				fallback: root.getAttribute('data-fallback') || '',
				prefix: root.getAttribute('data-prefix') || '',
				suffix: root.getAttribute('data-suffix') || '',
				locale: root.getAttribute('data-locale') || 'de-DE'
			});
		});
	}

	window.initLbLiquidGlassSlider = initLbLiquidGlassSlider;
	window.initLbLiquidGlassSliders = initLbLiquidGlassSliders;

})(window, document);
