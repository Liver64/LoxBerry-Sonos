<script>
/* =================================================================================================
 * Sonos4Lox UI Script (structured, NO functional changes)
 * -------------------------------------------------------------------------------------------------
 * Goal of this version:
 * - Same behavior, same logic, same selectors, same AJAX calls
 * - Only: better structure + detailed English documentation + clearer sectioning
 *
 * Sections:
 *   1) Page lifecycle / init
 *   2) Layout toggles (show/hide blocks)
 *   3) Sonos listener actions
 *   4) TTS engine handling (engine selection, key refresh, language/voice population)
 *   5) Provider-specific loaders (VoiceRSS, Polly, Piper, Google Cloud, Azure, ElevenLabs)
 *   6) Radios / scan
 *   7) Sonos test click handler (tblzonen)
 *   8) Soundbar / TV Monitor config
 *   9) Backup/Restore + dialogs
 *  10) Form validation + submit handling
 * ================================================================================================= */


/* ================================================================================================
 * 1) Page lifecycle / init
 * ================================================================================================ */

//console.log(config);
$(document).on('pageinit', function () {

	// SETTINGS page init (only if the related controls exist)
	if (document.getElementById('cal_det')) {
		callayout();
	}
	if (document.getElementById('dest_det')) {
		destlayout();
	}
	if (document.getElementById('radio_det')) {
		radlayout();
	}
	if (document.getElementById('weather_det')) {
		weatherlayout();
	}
	if (document.getElementById('donate')) {
		donation();
	}
	// DETAILS page init
	if (document.getElementById('detail_form')) {
		details_init();
	}
	toggleUdpXmlButton();
	initSonosHealthAmpel();
	
});

// DISABLE (ElevenLabs) - ENABLE (all other) Language Dropdown
$('#engine-selector input[name="t2s_engine"]').on('change', function () {
	updateLanguageDropdownForEngine();
});

// hide 2-digits language ISO-Code in UI
$(".clangiso").hide();

var tvmonerr = "true";

/**
 * Initializes the complete row on page load / page init.
 *
 * setTimeout(...) is used because some jQuery Mobile elements
 * are rendered a moment after the initial DOM is available.
 */
$(document).on("pageinit ready", function () {
	initSonosHealthAmpel();
	setTimeout(initS4LTransferRow, 0);
	setTimeout(toggleUdpPortVisibility, 50);
});

/**
 * Toggles the whole Fields for Communication to Loxone 
 */
$(document).off('change.sendloxCustom', '#sendlox').on('change.sendloxCustom', '#sendlox', function () {
	selection();
});

/**
 * refreshJqmCheckboxes(selector)
 * -----------------------------------------------------------------------------
 * Safely refreshes jQuery Mobile checkboxradio widgets while excluding
 * custom flipswitches and non-enhanced elements.
 *
 * @param {string|HTMLElement|jQuery} selector (optional)
 * - If provided: only refresh these elements
 * - If omitted: refresh all checkboxes on the page
 *
 * Behavior:
 * - Excludes elements with class ".no-jqm-flipswitch"
 * - Excludes elements with data-role="none"
 * - Only refreshes elements already initialized by jQM
 */
function refreshJqmCheckboxes(selector) {
    var $boxes = selector
        ? $(selector)
        : $('input:checkbox');

    $boxes = $boxes
        .not('.no-jqm-flipswitch')          // exclude custom flipswitches
        .filter(function () {
            return $(this).attr('data-role') !== 'none'; // exclude non-jQM elements
        });

    if (!$boxes.length) {
        return;
    }

    $boxes.each(function () {
        if ($(this).data('mobile-checkboxradio')) {
            $(this).checkboxradio('refresh');
        }
    });
}


/**
 * checkboxradio override (safe wrapper)
 * -----------------------------------------------------------------------------
 * Prevents jQuery Mobile from initializing or refreshing custom flipswitches.
 *
 * Filters out:
 * - .no-jqm-flipswitch
 * - data-role="none"
 *
 * Ensures:
 * - Existing jQM checkboxradio usage remains untouched
 * - Custom switches are completely isolated from jQM
 */
(function ($) {
    var originalCheckboxradio = $.fn.checkboxradio;

    if (typeof originalCheckboxradio === 'function') {
        $.fn.checkboxradio = function () {

            var $filtered = this
                .not('.no-jqm-flipswitch')
                .filter(function () {
                    return $(this).attr('data-role') !== 'none';
                });

            if (!$filtered.length) {
                return this;
            }

            return originalCheckboxradio.apply($filtered, arguments);
        };
    }
})(jQuery);


/**
 * lbParseBool(value)
 * -----------------------------------------------------------------------------
 * Converts common truthy representations into a real boolean.
 *
 * Accepted TRUE values:
 * - true
 * - "true"
 * - 1
 * - "1"
 * - "on"
 * - "yes"
 *
 * Everything else is treated as false.
 *
 * Why this helper exists:
 * - Custom LoxBerry flipswitches exchange values between:
 *   1) the visible checkbox
 *   2) the hidden form field
 *   3) the wrapper data-value attribute
 *
 * To keep all three layers stable, every incoming value is normalized here.
 *
 * @param {any} value
 * @returns {boolean}
 */
function lbParseBool(value) {
    return /^(true|1|on|yes)$/i.test(String(value || '').trim());
}


/**
 * syncCustomFlipswitch($wrap)
 * -----------------------------------------------------------------------------
 * Synchronizes the visual and logical state of one custom flipswitch wrapper.
 *
 * Responsibilities:
 * - read the current state primarily from the hidden field
 * - apply checked / unchecked state to the real checkbox
 * - normalize checkbox.value to "true" / "false"
 * - normalize hidden.value to "true" / "false"
 * - mirror the state to wrapper CSS classes:
 *     - .is-on
 *     - .is-disabled
 * - persist the current state back into data-value
 *
 * Why hidden field first?
 * - During normal runtime, the hidden field is the most reliable source for
 *   form submission and restored state handling.
 *
 * @param {jQuery} $wrap
 * @returns {void}
 */
function syncCustomFlipswitch($wrap) {
    if (!$wrap || !$wrap.length) {
        return;
    }

    var inputId = $wrap.attr('data-input');
    if (!inputId) {
        return;
    }

    var $cb = $('#' + inputId);
    var $hidden = $('#' + inputId + '_hidden');

    if (!$cb.length || !$hidden.length) {
        return;
    }

    var checked;

    if (String($hidden.val() || '').trim() !== '') {
        checked = lbParseBool($hidden.val());
    } else {
        checked = $cb.is(':checked');
    }

    $cb.prop('checked', checked);
    $cb.val(checked ? 'true' : 'false');
    $hidden.val(checked ? 'true' : 'false');

    $wrap.attr('data-value', checked ? 'true' : 'false');
    $wrap.toggleClass('is-on', checked);
    $wrap.toggleClass('is-disabled', $cb.is(':disabled'));
}


/**
 * initCustomFlipswitches(context)
 * -----------------------------------------------------------------------------
 * Initializes all custom LoxBerry flipswitches inside the given context.
 *
 * Initialization strategy:
 *
 * FIRST initialization of one switch:
 * - Prefer wrapper data-value
 * - If data-value is missing, use hidden field
 * - If hidden field is also empty, use the current checkbox state
 *
 * FOLLOW-UP initializations:
 * - Prefer hidden field
 * - Fall back to current checkbox state
 *
 * Why this logic is important:
 * - On first page load, data-value usually represents the server-rendered state
 *   and must not be overwritten by a stale hidden field.
 * - After the switch was initialized once, the hidden field becomes the main
 *   source of truth during UI interaction.
 *
 * Additional behavior:
 * - Marks each wrapper with data-lb-initialized="true"
 * - Binds one namespaced change handler per switch
 * - Keeps checkbox, hidden field and wrapper data-value fully synchronized
 * - Triggers dependent UI logic for specific switches:
 *     - sendlox  -> selection()
 *     - tvmon    -> validateTVMon()
 *
 * @param {HTMLElement|jQuery|Document=} context
 * @returns {void}
 */
function initCustomFlipswitches(context) {
    var $root = context ? $(context) : $(document);

    $root.find('.lb-flipswitch').each(function () {
        var $wrap = $(this);
        var inputId = $wrap.attr('data-input');
        var rawValue = $wrap.attr('data-value');

        var $checkbox = $('#' + inputId);
        var $hidden   = $('#' + inputId + '_hidden');

        if (!$checkbox.length || !$hidden.length) {
            return;
        }

        var checked;
        var alreadyInitialized = ($wrap.attr('data-lb-initialized') === 'true');

        if (!alreadyInitialized) {
            if (typeof rawValue !== 'undefined' && String(rawValue).trim() !== '') {
                checked = lbParseBool(rawValue);
            } else if (String($hidden.val() || '').trim() !== '') {
                checked = lbParseBool($hidden.val());
            } else {
                checked = $checkbox.is(':checked');
            }

            $wrap.attr('data-lb-initialized', 'true');
        } else {
            if (String($hidden.val() || '').trim() !== '') {
                checked = lbParseBool($hidden.val());
            } else {
                checked = $checkbox.is(':checked');
            }
        }

        $checkbox.prop('checked', checked);
        $checkbox.val(checked ? 'true' : 'false');
        $hidden.val(checked ? 'true' : 'false');
        $wrap.attr('data-value', checked ? 'true' : 'false');

        $checkbox.off('change.lbflip').on('change.lbflip', function () {
            var isChecked = $(this).is(':checked');

            $(this).val(isChecked ? 'true' : 'false');
            $hidden.val(isChecked ? 'true' : 'false');
            $wrap.attr('data-value', isChecked ? 'true' : 'false');

            syncCustomFlipswitch($wrap);

            /* --------------------------------------------------------------
             * Trigger dependent UI logic immediately for specific switches.
             * -------------------------------------------------------------- */
            if (this.id === 'tvmon') {
                validateTVMon();
            }
        });

        syncCustomFlipswitch($wrap);
    });

    /* ----------------------------------------------------------------------
     * Run dependent UI initialization after all switches are synchronized.
     * ---------------------------------------------------------------------- */
    if ($('#sendlox').length) {
        selection();
    }
    if ($('#tvmon').length) {
        validateTVMon();
    }
}


/**
 * setCustomFlipswitchValue(id, value)
 * -----------------------------------------------------------------------------
 * Programmatically sets a custom flipswitch to ON or OFF.
 *
 * This helper updates:
 * - checkbox.checked
 * - checkbox.value
 * - hidden.value
 * - wrapper data-value
 * - wrapper CSS classes (through syncCustomFlipswitch)
 *
 * Typical use cases:
 * - restoring values from backend config
 * - enabling/disabling soundbar-related options
 * - applying defaults for newly created UI sections
 *
 * @param {string} id
 * @param {any} value
 * @returns {void}
 */
function setCustomFlipswitchValue(id, value) {
    var $wrap = $('.lb-flipswitch[data-input="' + id + '"]');
    var $cb = $('#' + id);
    var $hidden = $('#' + id + '_hidden');

    if (!$wrap.length || !$cb.length || !$hidden.length) {
        return;
    }

    var checked = lbParseBool(value);

    $cb.prop('checked', checked);
    $cb.val(checked ? 'true' : 'false');
    $hidden.val(checked ? 'true' : 'false');
    $wrap.attr('data-value', checked ? 'true' : 'false');

    syncCustomFlipswitch($wrap);
}


/**
 * setCustomFlipswitchDisabled(id, disabled)
 * -----------------------------------------------------------------------------
 * Enables or disables a custom flipswitch and immediately synchronizes its
 * visual wrapper state.
 *
 * Important:
 * - This helper does not change the ON/OFF value itself.
 * - It only changes the disabled state and updates wrapper CSS classes.
 *
 * @param {string} id
 * @param {boolean} disabled
 * @returns {void}
 */
function setCustomFlipswitchDisabled(id, disabled) {
    var $wrap = $('.lb-flipswitch[data-input="' + id + '"]');
    var $cb = $('#' + id);

    if (!$wrap.length || !$cb.length) {
        return;
    }

    $cb.prop('disabled', !!disabled);
    syncCustomFlipswitch($wrap);
}


/**
 * setSoundbarSelectState(id, enabled, fallbackValue)
 * -----------------------------------------------------------------------------
 * Enables or disables a jQuery Mobile select element used in the Soundbar /
 * TV Monitor section.
 *
 * Behavior:
 * - optionally applies a fallback value before disabling
 * - updates the native disabled property
 * - updates jQuery Mobile selectmenu UI if available
 * - safely falls back to a simple change trigger if selectmenu throws
 *
 * Typical use cases:
 * - disable SubLevel fields if a device has no SUB capability
 * - restore and re-enable select fields if capability exists
 *
 * @param {string} id
 * @param {boolean} enabled
 * @param {string|number=} fallbackValue
 * @returns {void}
 */
function setSoundbarSelectState(id, enabled, fallbackValue) {
    var $select = $('#' + id);

    if (!$select.length) {
        return;
    }

    if (!enabled && typeof fallbackValue !== 'undefined') {
        $select.val(fallbackValue);
    }

    $select.prop('disabled', !enabled);

    try {
        if (enabled) {
            $select.selectmenu('enable');
        } else {
            $select.selectmenu('disable');
        }
        $select.selectmenu('refresh', true);
    } catch (e) {
        $select.trigger('change');
    }
}


/**
 * Flipswitch lifecycle hooks
 * -----------------------------------------------------------------------------
 * pageinit / pageshow:
 * - needed for jQuery Mobile page lifecycle handling
 *
 * document ready:
 * - needed for classic page load cases
 *
 * Result:
 * - custom switches are initialized reliably in both jQM and non-jQM flows
 */
$(document).off('pageinit.lbflip pageshow.lbflip');
$(document).on('pageinit.lbflip pageshow.lbflip', function () {
    initCustomFlipswitches(this);
});

$(function () {
    initCustomFlipswitches(document);
});

/* ================================================================================================
 * 2) Layout toggles (show/hide blocks)
 * ================================================================================================ */

/**
 * checkboxes()
 * - Iterates over all players and restores checkbox state from their value="on"/"off".
 * - Also refreshes jQuery Mobile checkboxradio styling after changes.
 */
function checkboxes() {
	//console.log('checkboxes');
	// get count of players for iteration
	var iteration = document.getElementById('countplayers').value;
	iteration = parseInt(iteration)
	iteration += 1;

	// loop through players
	for (i = 1; i < iteration; ++i) {
		if (document.getElementById('mainchk' + i).value == "on") {
			document.getElementById('mainchk' + i).checked = true;
		} else {
			document.getElementById('mainchk' + i).checked = false;
		}
	}
	refreshJqmCheckboxes(); // ✅ nur einmal!
}

/**
 * t2slayout()
 * - Toggles visibility for ".t2sdet" depending on flipswitch t2s_det.
 */
function t2slayout() {
	if (document.main_form.t2s_det.checked == true) {
		$(".t2sdet").show();
	} else {
		$(".t2sdet").hide();
	}
}

/**
 * Generic handler for Function layouts for
 * callayout(), destlayout(), radlayout(), weatherlayout()
 */
function toggleLayoutButton(fieldId) {
    var hiddenField = document.getElementById(fieldId);
    var button = document.getElementById("btn_" + fieldId);

    if (!hiddenField || !button) {
        return;
    }

    if (hiddenField.value === "1") {
        hiddenField.value = "0";
        button.classList.remove("active");
    } else {
        hiddenField.value = "1";
        button.classList.add("active");
    }
}

/* Beim Laden alles auf Standard zurücksetzen */
document.addEventListener("DOMContentLoaded", function () {
    var fields = ["radio_det", "weather_det", "dest_det", "cal_det"];

    fields.forEach(function (fieldId) {
        var hiddenField = document.getElementById(fieldId);
        var button = document.getElementById("btn_" + fieldId);

        if (hiddenField) {
            hiddenField.value = "0";
        }

        if (button) {
            button.classList.remove("active");
        }
    });
});


/**
 * callayout(), destlayout(), radlayout(), weatherlayout()
 * - Toggle visibility for their related detail blocks.
 * - Reads state from hidden fields used by the layout buttons.
 */
function callayout() {
    if (document.main_form.cal_det.value === "1") {
        $(".caldet").show();
    } else {
        $(".caldet").hide();
    }
}

function destlayout() {
    if (document.main_form.dest_det.value === "1") {
        $(".destdet").show();
    } else {
        $(".destdet").hide();
    }
}

function radlayout() {
    if (document.main_form.radio_det.value === "1") {
        $(".radiodet").show();
    } else {
        $(".radiodet").hide();
    }
}

function weatherlayout() {
    if (document.main_form.weather_det.value === "1") {
        $(".weatherdet").show();
    } else {
        $(".weatherdet").hide();
    }
}


/**
 * updateLanguageDropdownForEngine()
 * - Toggles visibility for language dropdown depending on selected Engine
 */
/**
 * updateLanguageDropdownForEngine()
 * - Hides the language dropdown completely for ElevenLabs
 * - Shows/enables it for all other TTS engines
 */
function updateLanguageDropdownForEngine() {
	var isElevenLabs = $('#engine-selector input[name="t2s_engine"]:checked').val() === '9011';

	if (isElevenLabs) {
		$('#t2slang_wrap').hide();
		$('#t2slang').prop('disabled', true);

		try {
			$('#t2slang').selectmenu('disable');
			$('#t2slang').selectmenu('refresh', true);
		} catch (e) {
			/* ignore if selectmenu is not initialized */
		}
	} else {
		$('#t2slang_wrap').show();
		$('#t2slang').prop('disabled', false);

		try {
			$('#t2slang').selectmenu('enable');
			$('#t2slang').selectmenu('refresh', true);
		} catch (e) {
			/* ignore if selectmenu is not initialized */
		}
	}
}


/**
 * donation()
 * - If "donate" checkbox checked => hide donation collapsible (#coll_donate)
 */
function donation() {
	if (document.getElementById('donate').checked) {
		$("#coll_donate").hide();
	} else {
		$("#coll_donate").show();
	}
}


/* ================================================================================================
 * 4) TTS engine handling (visibility, keys, language/voice population)
 * ================================================================================================ */

/**
 * prepareTTSConfigFields()
 * - Core UI switch for engine-specific config blocks:
 *   - Hides all .ttsconfig except the current engine class + .ttsbaseconfig
 * - Ensures voice dropdown is enabled for supported engines
 * - Refreshes selectmenus and triggers language+voice population
 */
function prepareTTSConfigFields() {
	console.log("prepareTTSConfigFields");

	const selectedEngine = $('#engine-selector input:checked');
	if (selectedEngine) {
		$('.ttsconfig').not('.' + selectedEngine.val()).not('.ttsbaseconfig').hide();
		$('.' + selectedEngine.val() + ', .ttsbaseconfig').show();
	} else {
		$('.ttsconfig').hide();
	}
	
	// Adjust API key width for ElevenLabs
	if (document.getElementById('tts_elevenlabs').checked == true) {
		$('#apikey_wrap').css({
			'flex': '0 0 570px',
			'min-width': '570px',
			'max-width': '570px'
		});
	} else {
		$('#apikey_wrap').css({
			'flex': '0 0 500px',
			'min-width': '500px',
			'max-width': '500px'
		});
	}
	updateLanguageDropdownForEngine();

	// T2S INSTANZ
	if (document.getElementById('tts_voicerss').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	} else if (document.getElementById('tts_azure').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	} else if (document.getElementById('tts_elevenlabs').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	} else if (document.getElementById('tts_polly').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	} else if (document.getElementById('tts_google_cloud').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	} else if (document.getElementById('tts_piper').checked == true) {
		$('#voice').removeClass('ui-disabled');
		$('#voice').selectmenu('refresh', true);
	}

	$('#t2slang').selectmenu('refresh', true);
	$('#voice').selectmenu('refresh', true);

	populateLanguageAndVoiceFields();
}

/**
 * selection()
 * - Controls whether Loxone communication fields (.field_ms / .empty_template) are shown.
 * - Driven by flipswitch #sendlox value "true"/"false".
 */
function selection() {
    console.log("Selection");

    var cat = $('#sendlox_hidden').val();

    if (cat == "true") {
        console.log("Communication to Loxone turned on");
 		$('.field_ms').show();
		$('.empty_template').show();
    } else {
        console.log("Communication to Loxone turned off");
 		$('.field_ms').hide();
		$('.empty_template').hide();
    }

    $('#miniserver').selectmenu('refresh', true);
}


/* ================================================================================================
 * 5) Speech / voice handling (generic, JSON-based lists + provider switch)
 * ================================================================================================ */

/**
 * populateVoice()
 * - Populates #voice depending on currently selected engine and selected language.
 * - Important:
 *   - ElevenLabs is handled separately by populateElevenlabsLang()/populateElevenlabsVoices()
 *   - Google Cloud is handled separately by populateGoogleCloudVoices()
 */
function populateVoice() {
	console.log("populateVoice");
	$('#voice').selectmenu('refresh', true);

	// === SPECIAL CASE: ELEVENLABS ==========================================
	// ElevenLabs language+voices are handled by populateElevenlabsLang()/Voices.
	// This prevents double calls, especially because #t2slang historically still has
	// onchange="populateVoice()" somewhere.
	if (document.main_form.tts_elevenlabs &&
		document.main_form.tts_elevenlabs.checked === true) {

		console.log("populateVoice: ElevenLabs active → handled separately, skipping.");
		return;
	}

	// ==== VoiceRSS ====
	if (document.getElementById('tts_voicerss').checked == true) {
		var selectedlanguage = $("#t2slang").val();
		console.log("VoiceRSS selectedlanguage:", selectedlanguage);

		if (selectedlanguage) {
			$('#voice').empty();
			$('#voice').removeClass('ui-disabled');

			const url = "/plugins/<TMPL_VAR PLUGINDIR>/voice_engines/langfiles/voicerss_voices.json";
			$.getJSON(url, function(listvoice) {
				$('#voice').append(
					'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				);

				// Stimmen nach Sprache filtern (language in JSON = value in voicerss.json)
				var selectedvoiceList = listvoice.filter(function(item) {
					return item.language === selectedlanguage;
				});

				$.each(selectedvoiceList, function(index, value) {
					if (value.name == '<TMPL_VAR VOICE>') {
						$('#voice').append(
							'<option selected="selected" value="' + value.name + '">' +
							value.name + '</option>'
						);
					} else {
						$('#voice').append(
							'<option value="' + value.name + '">' +
							value.name + '</option>'
						);
					}
				});

				$('#voice').selectmenu('refresh', true);
			});
		}

	// ==== Polly ====
	} else if (document.main_form.tts_polly.checked == true) {
		var selectedlanguage = $("#t2slang option:selected").val();
		if (selectedlanguage) {
			$('#voice').empty();
			$('#voice').removeClass('ui-disabled');
			const url = "/plugins/<TMPL_VAR PLUGINDIR>/voice_engines/langfiles/polly_voices.json";
			$.getJSON(url, function(listvoice) {
				$('#voice').append(
					'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				);
				var selectedvoiceList = listvoice.filter(function(item) {
					return item.language === selectedlanguage;
				});
				$.each(selectedvoiceList, function(index, value) {
					if (value.name == '<TMPL_VAR VOICE>') {
						$('#voice').append(
							'<option selected="selected" value="' + value.name + '">' +
							value.name + '</option>'
						);
					} else {
						$('#voice').append(
							'<option value="' + value.name + '">' +
							value.name + '</option>'
						);
					}
					$('#voice').selectmenu('refresh', true);
				});
			});
		}

	// ==== Google Cloud (Voices are built in populateGoogleCloudVoices()) ====
	} else if (document.main_form.tts_google_cloud.checked == true) {
		return;

	// ==== Piper ====
	} else if (document.main_form.tts_piper.checked == true) {
		var selectedlanguage = $("#t2slang option:selected").val();
		$('#t2slang').selectmenu('refresh', true);
		console.log("popvoice (Piper): " + selectedlanguage);
		if (selectedlanguage) {
			$('#voice').empty();
			$('#voice').removeClass('ui-disabled');
			const url = "/plugins/<TMPL_VAR PLUGINDIR>/voice_engines/langfiles/piper_voices.json";
			$.getJSON(url, function(listvoice) {
				$('#voice').append(
					'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				);
				var selectedvoiceList = listvoice.filter(function(item) {
					return item.language === selectedlanguage;
				});
				$.each(selectedvoiceList, function(index, value) {
					if (value.name == '<TMPL_VAR VOICE>') {
						$('#voice').append(
							'<option selected="selected" value="' + value.name + '">' +
							value.name + '</option>'
						);
					} else {
						$('#voice').append(
							'<option value="' + value.name + '">' +
							value.name + '</option>'
						);
					}
					$('#voice').selectmenu('refresh', true);
				});
			});
		}
	}

	$('#voice').selectmenu('refresh', true);
	$('#t2slang').selectmenu('refresh', true);

	// generate language ISO Code (two chars) into info field
	var isocode = '<TMPL_VAR CODE>'.substr(0, 2);
	$('#langiso').val(isocode);
	$("#langiso").textinput("refresh");
}

/**
 * piperInfo(event)
 * - For Piper: if user clicks on the link for further languages/voices
 *   a dialog box appears, link to Voices/languages open in a new Browser
 *   and link to update languages appears
 */
function piperInfo(event) {
	event.preventDefault();

	var lbhost   = window.location.hostname;
	var hfUrl    = "https://huggingface.co/rhasspy/piper-voices/tree/main";
	var localUrl = "http://" + lbhost + "/plugins/sonos4lox/bin/piper-voices.php";

	var text = "<TMPL_VAR T2S.PIPER_DIA1><br><br>"
	+ "<div style='text-align:center;'>"
	+ "<div style='margin-bottom:1px;'>"
	+ "</div>"
	+ "<a href='" + hfUrl + "' "
	+ "target='_blank' rel='noopener noreferrer' "
	+ "style='color:#0066cc; text-decoration:underline;'>"
	+ "Piper Voices"
	+ "</a>"
	+ "</div><br>"
	+ "<TMPL_VAR T2S.PIPER_DIA5><br><br>"
	+ "<code style='font-size:14px;font-weight:bold'>"
	+ "/opt/loxberry/webfrontend/html/voice_engines/piper-voices"
	+ "</code><br><br>"
	+ "<TMPL_VAR T2S.PIPER_DIA4><br><br>"
	+ "<a href='" + localUrl + "' "
	+ "target='_blank' style='color:#0066cc;text-decoration:underline;'>"
	+ localUrl
	+ "</a>";

	dialog(text, "OK", "info", "Piper Voices");
}

/**
 * setCorrectLang()
 * - For Google Cloud: if user selects a voice first, it can carry a data-lang attribute.
 *   This function ensures #t2slang matches the voice language.
 */
function setCorrectLang() {
	console.log('setCorrectLang');
	if (document.main_form.tts_google_cloud.checked == true) {
		var selectedlanguage = $("#t2slang").val();
		if ($("#voice").find("option:selected").attr('data-lang') != selectedlanguage) {
			$('#t2slang option[value="' + $("#voice").find("option:selected").attr('data-lang') + '"]').attr("selected", "selected");
			$('#t2slang').selectmenu('refresh', true);
		}
	}
}


/* ================================================================================================
 * 6) Provider-specific loaders
 * ================================================================================================ */

/*************************************************************************************************************
 * populateGoogleCloudVoices()
 * - Fetches voices from Google Cloud TTS API
 * - Fills #t2slang with language codes (locale)
 * - Fills #voice with Chirp3 voices for selected language
 * - Handles errors by resetting dropdowns and logging to console
 *************************************************************************************************************/
function populateGoogleCloudVoices() {
	console.log("populateGoogleCloudVoices");

	var apikey = $('#apikey').val().trim();
	if (!apikey) {
		console.log("Google Cloud Voice: No API key set, aborting voices load.");
		return;
	}

	const url = "https://texttospeech.googleapis.com/v1/voices?key=" + apikey;

	fetch(url)
		.then(function (response) {

			// === HTTP errors handled like Azure – but no dialog ===
			if (!response.ok) {
				console.warn(
					"Google Cloud Voice HTTP error:",
					response.status,
					response.status === 401 ? "(possibly invalid API key)" : ""
				);

				// Reset dropdowns to a defined state
				$('#t2slang').empty().append(
					'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
				).selectmenu('refresh', true);

				$('#voice').empty().append(
					'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				).selectmenu('refresh', true);

				// Throw to skip next then()
				throw new Error("HTTP " + response.status);
			}

			return response.json();
		})
		.then(function (data) {
			if (!data || !data.voices) {
				console.log("Google Cloud Voice: no voices in response");
				return;
			}

			const langs        = new Map(); // langCode -> Label
			const voicesByLang = new Map(); // langCode -> [{key,value,display}]

			// === Only Chirp3 voices ===
			data.voices.forEach(function (v) {

				if (!v.name.includes("Chirp3")) return;

				const codes = v.languageCodes || [];
				if (!codes.length) return;

				const parts  = v.name.split("-");
				const short  = parts[parts.length - 1];              // e.g. "Achernar"
				const gender = v.ssmlGender ? v.ssmlGender.toLowerCase() : "";
				const disp   = gender ? (short + " (" + gender + ")") : short;

				codes.forEach(function (code) {

					// Remember language
					if (!langs.has(code)) {
						langs.set(code, prettyLang(code));
					}

					// Voices per language
					let list = voicesByLang.get(code);
					if (!list) {
						list = [];
						voicesByLang.set(code, list);
					}

					// Prevent duplicates (same short+gender)
					const key = short + "|" + gender;
					if (!list.some(function (item) { return item.key === key; })) {
						list.push({
							key:     key,
							value:   v.name,   // technical voice name
							display: disp      // "Achernar (female)"
						});
					}
				});
			});

			// === Fill language dropdown sorted alphabetically ===
			const sortedLangs = Array.from(langs.entries())
				.sort(function (a, b) {
					return a[1].localeCompare(b[1], 'de', { sensitivity: 'base' });
				});

			$('#t2slang').empty().append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
			);

			sortedLangs.forEach(function ([code, label]) {
				$('#t2slang').append('<option value="' + code + '">' + label + '</option>');
			});

			const savedLanguage = "<TMPL_VAR CODE>";
			let initialLang = (savedLanguage && langs.has(savedLanguage))
				? savedLanguage
				: (sortedLangs[0] ? sortedLangs[0][0] : "");

			if (initialLang) {
				$('#t2slang').val(initialLang);
			}
			$('#t2slang').selectmenu('refresh', true);

			// === Helper: fill voices for a given language ===
			function fillVoicesForLanguage(langCode) {
				$('#voice').empty().append(
					'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				);

				const list = voicesByLang.get(langCode) || [];

				list.sort(function (a, b) {
					return a.display.localeCompare(b.display, 'de', { sensitivity: 'base' });
				});

				list.forEach(function (item) {
					$('#voice').append(
						'<option value="' + item.value + '">' + item.display + '</option>'
					);
				});

				const savedVoice = "<TMPL_VAR VOICE>";
				if (savedVoice && list.some(function (i) { return i.value === savedVoice; })) {
					$('#voice').val(savedVoice);
				}

				$('#voice').selectmenu('refresh', true);
			}

			// Initial: current language
			const currentLang = $('#t2slang').val();
			if (currentLang) {
				fillVoicesForLanguage(currentLang);
			}

			// On language change: re-filter voices
			$('#t2slang').off('change.googlevoices').on('change.googlevoices', function () {
				fillVoicesForLanguage(this.value);
			});
		})
		.catch(function (err) {
			// HTTP errors already logged above
			if (String(err.message || "").startsWith("HTTP ")) {
				return;
			}

			console.warn("Google Cloud Voice: connection error", err);

			$('#t2slang').empty().append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
			).selectmenu('refresh', true);

			$('#voice').empty().append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
			).selectmenu('refresh', true);
		});
}

/**
 * prettyLang(code)
 * - Small helper to display language code as readable name (Intl.DisplayNames)
 */
function prettyLang(code) {
	try {
		const langIso = code.split("-")[0];
		const langName = new Intl.DisplayNames(
			[navigator.language || navigator.userLanguage],
			{ type: "language" }
		).of(langIso);

		const region = code.split("-")[1]?.toUpperCase();

		if (langName && region) return langName + " (" + region + ")";
		if (langName) return langName;
	} catch (e) {
		console.warn("prettyLang failed for", code, e);
	}
	return code;
}


/**
 * populateLanguageBasedOnJSON(url)
 * - Generic JSON language list loader
 * - Fills #t2slang with languages
 * - Selects saved language (<TMPL_VAR CODE>) if present; otherwise falls back to first entry
 * - After that it calls populateVoice() to fill the voice dropdown
 */
function populateLanguageBasedOnJSON(url) {
	console.log("populateLanguageBasedOnJSON", url);

	$.getJSON(url, function(listlang) {
		$('#t2slang').empty();

		// Placeholder
		$('#t2slang').append(
			'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
		);

		// Fill languages from JSON
		$.each(listlang, function(index, value) {
			$('#t2slang').append(
				'<option value="' + value.value + '">' + value.country + '</option>'
			);
		});

		// Saved CODE from template (e.g. "de-DE" or "de_DE")
		var savedCode = '<TMPL_VAR CODE>';
		var chosenVal = "";

		if (savedCode) {
			console.log("Selecting CODE (raw):", savedCode);

			// 1) exact match
			if ($("#t2slang option[value='" + savedCode + "']").length > 0) {
				chosenVal = savedCode;
			} else {
				// 2) '-' -> '_' (de-DE -> de_DE)
				var alt1 = savedCode.replace('-', '_');
				if ($("#t2slang option[value='" + alt1 + "']").length > 0) {
					chosenVal = alt1;
				} else {
					// 3) '_' -> '-' (de_DE -> de-DE)
					var alt2 = savedCode.replace('_', '-');
					if ($("#t2slang option[value='" + alt2 + "']").length > 0) {
						chosenVal = alt2;
					} else {
						// 4) fallback: first 2 letters (de)
						var shortCode = savedCode.substr(0, 2);
						$("#t2slang option").each(function() {
							var v = this.value;
							if (!chosenVal && v && v.substr(0, 2) === shortCode) {
								chosenVal = v; // e.g. de_DE
							}
						});
					}
				}
			}
		}

		// 5) If still nothing selected: pick first entry
		if (!chosenVal && listlang.length > 0) {
			chosenVal = listlang[0].value;
		}

		if (chosenVal) {
			console.log("Selecting CODE (resolved):", chosenVal);
			$('#t2slang').val(chosenVal);
		}

		$('#t2slang').selectmenu('refresh', true);

		// Now language is always valid -> populate voice list
		populateVoice();
	});
}

// --- Unsaved marker for API fields (jQuery Mobile safe) ----------------------------
function setApiDirty(isDirty) {
	const $api = $('#apikey');
	const $sec = $('#seckey');

	// jQM wrappers (this is what you usually SEE)
	const $apiWrap = $api.closest('.ui-input-text');
	const $secWrap = $sec.closest('.ui-input-text');

	if (isDirty) {
		$api.addClass('api-dirty');
		$sec.addClass('api-dirty');
		$apiWrap.addClass('api-dirty');
		$secWrap.addClass('api-dirty');
		$('#lapikey').addClass('api-dirty-label');
	} else {
		$api.removeClass('api-dirty');
		$sec.removeClass('api-dirty');
		$apiWrap.removeClass('api-dirty');
		$secWrap.removeClass('api-dirty');
		$('#lapikey').removeClass('api-dirty-label');
	}
}

// inject CSS once
(function injectApiDirtyCss() {
	if (document.getElementById('api-dirty-css')) return;
	const css = `
		.api-dirty { background-color: #FFFFC0 !important; }
		.api-dirty-label { font-weight: bold; }
	`;
	const st = document.createElement('style');
	st.id = 'api-dirty-css';
	st.appendChild(document.createTextNode(css));
	document.head.appendChild(st);
})();

// Bind handlers on jQM lifecycle (NOT only document.ready)
$(document)
	.off('pagecreate.apiDirty pageshow.apiDirty')
	.on('pagecreate.apiDirty pageshow.apiDirty', function () {

		// Keyup is very reliable in jQM, input is sometimes flaky depending on browser/jQM
		$(document).off('keyup.apiDirty change.apiDirty', '#apikey,#seckey')
			.on('keyup.apiDirty change.apiDirty', '#apikey,#seckey', function () {
				const a = ($('#apikey').val() || '').trim();
				const s = ($('#seckey').val() || '').trim();
				setApiDirty(a !== '' || s !== '');
			});
	});


/**
 * checkProvider()
 * - First loads API keys for currently selected engine from backend
 * - Then triggers correct provider loader:
 *   - Google Cloud -> populateGoogleCloudVoices()
 *   - Azure       -> populateAzureVoices()
 *   - ElevenLabs  -> populateElevenlabsLang()
 *   - Otherwise   -> populateLanguageAndVoiceFields()
 */
function checkProvider() {
	// Do NOT refresh keys here; use whatever is currently in the input fields.
	if (document.main_form.tts_google_cloud?.checked === true) {
		populateGoogleCloudVoices();
	}
	else if (document.main_form.tts_azure?.checked === true) {
		populateAzureVoices();
	}
	else if (document.main_form.tts_elevenlabs?.checked === true) {
		populateElevenlabsLang();
	}
	else {
		populateLanguageAndVoiceFields();
	}
}



/**
 * refreshApiKeysForCurrentEngine(callback, force)
 * - force === true  : overwrite fields exactly with backend result (also empty)  => use on ENGINE SWITCH
 * - force === false : do NOT wipe non-empty fields with empty backend values     => use on VOICES button
 */
function refreshApiKeysForCurrentEngine(callback, force) {
	var t2sengine = $('input[name=t2s_engine]:checked').val();

	if (!t2sengine) {
		if (typeof callback === 'function') callback();
		return;
	}

	$.ajax({
		url: "./index.cgi",
		type: "GET",
		dataType: "json",
		data: { getkeys: 1, t2s_engine: t2sengine }
	})
	.done(function (result) {

		const curApi = ($("#apikey").val() || "").trim();
		const curSec = ($("#seckey").val() || "").trim();

		const newApi = ((result && result.apikey) ? String(result.apikey) : "").trim();
		const newSec = ((result && result.seckey) ? String(result.seckey) : "").trim();

		if (force === true) {
			// ENGINE SWITCH: reflect backend exactly (empty clears field)
			$("#apikey").val(newApi);
			$("#seckey").val(newSec);

			// <-- CLEAR DIRTY, because values now come from backend (saved state)
			if (typeof setApiDirty === 'function') setApiDirty(false);

		} else {
			// VOICES BUTTON / NON-DESTRUCTIVE
			let changed = false;
			if (newApi !== "" || curApi === "") { $("#apikey").val(newApi); changed = true; }
			if (newSec !== "" || curSec === "") { $("#seckey").val(newSec); changed = true; }

			// Only clear dirty if we actually replaced values from backend
			if (changed && typeof setApiDirty === 'function') setApiDirty(false);
		}
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log("refreshApiKeysForCurrentEngine failed:", textStatus, errorThrown);
	})
	.always(function () {
		if (typeof callback === 'function') callback();
	});
}


/**
 * Engine selector change handler:
 * - First refresh keys from backend
 * - Then apply UI changes via prepareTTSConfigFields()
 */
$('#engine-selector input')
	.off('change.engine')
	.on('change.engine', function () {
		refreshApiKeysForCurrentEngine(function () {
			prepareTTSConfigFields();
		}, true); // <- force
	});


/* ================================================================================================
 * 6.1) ElevenLabs
 * ================================================================================================ */

function populateElevenlabsLang() {
	console.log("populateElevenlabsLang (ElevenLabs v2)");

	if (!document.main_form.tts_elevenlabs ||
		document.main_form.tts_elevenlabs.checked !== true) {
		return;
	}

	const $lang = $('#t2slang');

	// UI only
	$lang.empty().append(
		'<option value="DE-de">Auto (Multilingual)</option>'
	);
	$lang.selectmenu('refresh', true);

	// ensure POST value exists independently of jQM selectmenu
	let $hidden = $('#t2slang_hidden');
	if ($hidden.length === 0) {
		$hidden = $('<input>', {
			type: 'hidden',
			id: 't2slang_hidden',
			name: 't2slang'
		}).appendTo('#main_form');
	}
	// Fake entry, not used in backend for ElevenLabs
	$hidden.val('DE-de');
	// -----------------------

	populateElevenlabsVoices();
}

function populateElevenlabsVoices() {
	console.log("populateElevenlabsVoices");

	$('#voice').empty();

	const key = document.getElementById('apikey').value.trim();
	if (!key) {
		console.warn("ElevenLabs voice error: no API key set.");
		return;
	}

	const options = {
		method: 'GET',
		headers: { "xi-api-key": key }
	};

	const url = 'https://api.elevenlabs.io/v2/voices';

	fetch(url, options)
		.then(response => response.json())
		.then(function (data) {

			let voices = data['voices'];
			if (!voices) {
				console.warn(
					"ElevenLabs voice error:",
					data.detail ? data.detail.message : "Unknown error"
				);
				return;
			}

			$('#voice').append(
				'<option selected="true" value="" disabled>' +
				'<TMPL_VAR T2S.SELECT_VOICE_DROPDOWN>' +
				'</option>'
			);

			voices.forEach((voice) => {

				let labelAge = voice.labels && voice.labels.age ? voice.labels.age : '';
				let labelDesc = voice.labels && voice.labels.description ? voice.labels.description : '';

				let label = voice.name;
				if (labelAge || labelDesc) {
					label += " - " + labelAge;
					if (labelDesc) label += ", " + labelDesc;
				}

				if (voice.voice_id === "<TMPL_VAR TTS.voice>") {
					$('#voice').append(
						"<option selected='selected' id=\"" +
						(voice.preview_url || "") +
						"\" value=\"" + voice.voice_id + "\">&nbsp;" +
						label + "</option>"
					);
				} else {
					$('#voice').append(
						"<option id=\"" +
						(voice.preview_url || "") +
						"\" value=\"" + voice.voice_id + "\">&nbsp;" +
						label + "</option>"
					);
				}
			});

			$('#voice').selectmenu('refresh', true);
		})
		.catch(function (err) {
			console.warn("ElevenLabs voice error:", err);
		});
}



/* ================================================================================================
 * 6.2) Microsoft Azure
 * ================================================================================================ */

var regionOptions = document.getElementById("regionOptions");

/**
 * populateAzureVoices()
 * - Fetches voice list from Azure (region-based endpoint)
 * - Fills #t2slang with Locale
 * - Fills #voice with voices matching selected Locale
 * - Errors are logged only; dropdowns are reset
 */
function populateAzureVoices() {
	console.log("populateAzureVoices");

	var apikey = $('#apikey').val();
	if (!apikey) {
		console.log("Azure Voice: No API key set, aborting voice load.");
		return;
	}
	console.log("Azure Voice: Actual API-Key:", apikey);

	// Clear fields
	$('#t2slang').empty();
	$('#voice').empty();
	$("#info").empty(); // harmless if not present

	var request = new XMLHttpRequest();
	request.open(
		'GET',
		'https://' + regionOptions.value + ".tts.speech." +
		(regionOptions.value.startsWith("china") ? "azure.cn" : "microsoft.com") +
		"/cognitiveservices/voices/list",
		true
	);
	request.setRequestHeader("Ocp-Apim-Subscription-Key", apikey);

	request.onload = function () {
		if (request.status >= 200 && request.status < 400) {
			const data = JSON.parse(this.response);

			// --- Unique languages (Locale -> LocaleName) ---
			let langMap = {};
			data.forEach(function (v) {
				if (!langMap[v.Locale]) {
					langMap[v.Locale] = v.LocaleName;
				}
			});

			// --- Language dropdown (#t2slang) ---
			$('#t2slang').append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
			);
			Object.keys(langMap).forEach(function (language) {
				$('#t2slang').append(
					'<option value="' + language + '">' + langMap[language] + '</option>'
				);
			});

			// --- Voice dropdown placeholder (#voice) ---
			$('#voice').append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
			);

			$('#t2slang').selectmenu('refresh', true);
			$('#voice').selectmenu('refresh', true);

			// --- Load voices for selected language ---
			function loadVoices(selectedLang) {
				$('#voice').empty();
				$('#voice').append(
					'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
				);

				const voices = data.filter(function (v) { return v.Locale === selectedLang; });
				voices.forEach(function (voice) {
					// Preselect based on saved voice
					if (voice.ShortName == '<TMPL_VAR VOICE>') {
						$('#voice').append(
							'<option selected="selected" value="' + voice.ShortName + '">' +
							voice.DisplayName + " (" + voice.Gender + ")" +
							'</option>'
						);
					} else {
						$('#voice').append(
							'<option value="' + voice.ShortName + '">' +
							voice.DisplayName + " (" + voice.Gender + ")" +
							'</option>'
						);
					}
				});

				$('#voice').selectmenu('refresh', true);
			}

			// --- OnChange: select language -> reload voices ---
			$('#t2slang').off('change').on('change', function () {
				var selectedLang = $(this).val();
				$('#t2slang').val(selectedLang).selectmenu('refresh', true);
				loadVoices(selectedLang);
			});

			// --- Initial: preselected language from template (CODE) ---
			var defaultLang = '<TMPL_VAR CODE>';
			if (defaultLang && langMap[defaultLang]) {
				$('#t2slang').val(defaultLang).selectmenu('refresh', true);
				loadVoices(defaultLang);
			}

		} else {
			// HTTP error -> console only
			console.warn(
				"Azure Voice HTTP error:",
				request.status,
				request.status === 401 ? "(possibly invalid API key)" : ""
			);

			$('#t2slang').empty().append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
			).selectmenu('refresh', true);

			$('#voice').empty().append(
				'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
			).selectmenu('refresh', true);
		}
	};

	request.onerror = function () {
		console.warn('Azure Voice: connection error while loading voices');

		$('#t2slang').empty().append(
			'<option selected disabled value=""><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
		).selectmenu('refresh', true);

		$('#voice').empty().append(
			'<option selected disabled value=""><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
		).selectmenu('refresh', true);
	};

	request.send();
}


/* ================================================================================================
 * 7) Engine → language/voice field population (dispatcher)
 * ================================================================================================ */

/**
 * populateLanguageAndVoiceFields()
 * - Dispatcher that decides which language source to use based on engine id
 * - For "local" engines: JSON file -> populateLanguageBasedOnJSON(url)
 * - For Google/Azure/ElevenLabs: direct online loaders
 */
function populateLanguageAndVoiceFields() {
	console.log("populateLanguageAndVoiceFields");
	const selectedEngine = $('#engine-selector input:checked');

	$('#t2slang').empty();
	$('#voice').empty();

	var url = "/plugins/<TMPL_VAR PLUGINDIR>/voice_engines/langfiles/";

	switch (selectedEngine.val()) {
		case '1001':
			url = url + "voicerss.json";
			break;
		case '4001':
			url = url + "polly.json";
			break;
		case '9012':
			url = url + "piper.json";
			break;
		case '6001':
			url = url + "respvoice.json";
			break;
		case '8001':
			url = null;
			populateGoogleCloudVoices();
			break;
		case '9001':
			url = null;
			populateAzureVoices();
			break;
		case '9011':
			url = null;
			populateElevenlabsLang();
			break;
		default:
			$('#t2slang').append('<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>');
			$('#t2slang').prop('selectedIndex', 0);
			break;
	}

	// *** Safety / guard ***
	if (url && url.endsWith("json")) {
		populateLanguageBasedOnJSON(url);
	}

	$('#t2slang').selectmenu('refresh', true);
	$('#voice').selectmenu('refresh', true);
	$('select').selectmenu();
	$('select').selectmenu('refresh', true);

	var isocode = '<TMPL_VAR CODE>'.substr(0, 2);
	$('#langiso').val(isocode);
	$("#langiso").textinput("refresh");
}


/* ================================================================================================
 * 8) Initial load helpers
 * ================================================================================================ */

/**
 * initial_lang_voice_load()
 * - If no engine selected initially -> hide all engine config blocks
 * - Otherwise select engine radio button and call prepareTTSConfigFields()
 */
function initial_lang_voice_load() {
	console.log("initial_lang_voice_load");

	// if screen is initial, no T2S engine selected
	if (!$('#val_t2s').val()) {
		$('.ttsconfig').hide();
	} else {
		console.log("selecting engine: " + $('#val_t2s').val());
		$('#engine-selector input[value="' + $('#val_t2s').val() + '"]').prop('checked', true).checkboxradio("refresh");
		$('#t2slang').selectmenu('refresh', true);
		$('#voice').selectmenu('refresh', true);
		prepareTTSConfigFields();
	}
};


/* ================================================================================================
 * 9) Radios / scan
 * ================================================================================================ */

/**
 * Delete Radio Station
 * - Deletes selected row of radio station from table #tblBasic2
 */
$(document).on("click", "a.jsDelRadio", function (e) {
  e.preventDefault();

  const $btn  = $(this);
  const idx   = String($btn.data("idx") || "");
  const name  = String($btn.data("name") || "");

  confirmDelete(
    "<TMPL_VAR ZONES.ASK_DELETE4>",
    "<TMPL_VAR ZONES.ASK_DELETE3> '" + name + "' <TMPL_VAR ZONES.ASK_DELETE2>",
    function () {
      $("#chkradios" + idx).prop("checked", true);
      $btn.closest("tr").css("opacity", "0.4");
      $("#main_form").trigger("submit");
    },
    {
      deleteText: "<TMPL_VAR ZONES.BUTTON_CONFIRM>",
      cancelText: "<TMPL_VAR ZONES.BUTTON_CANCEL>",
      btnColor: "#6dac20",
      alertIcon: "warning"
    }
  );
});


/**
 * Manual Sonos IP input:
 * Pressing ENTER in the IP field uses the same logic as the "Scan Player" button.
 */
$(document).on('pagecreate pagebeforeshow', function () {

    $(document).off('keydown', '#vlan_ips_input').on('keydown', '#vlan_ips_input', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            submitManualSonosIpScan($(this).val());
            return false;
        }
    });

});

/**
 * AddRadio()
 * - Adds a new radio station row to table #tblBasic2
 * - Re-applies jQuery Mobile styles with trigger('create')
 */
function AddRadio() {

  // Total number of radios
  var iteration = parseInt(document.getElementById('countradios').value || "0", 10);

  var tbl = document.getElementById('tblBasic2');

  // Delete last info row if there were no radios (the "empty" row)
  if (iteration < 1) {
    // table has: header row + empty row => remove empty row (last row)
    if (tbl.rows.length > 1) tbl.deleteRow(tbl.rows.length - 1);
  }

  iteration += 1;
  document.getElementById("countradios").value = iteration;

  // Insert new row at end
  var row = tbl.insertRow(tbl.rows.length);

  // --- Column 1: (new row) no delete icon yet, but keep hidden checkbox for backend ---
  var c0 = row.insertCell(0);
  c0.style.height = "25px";
  c0.style.width  = "43px";
  c0.style.textAlign = "center";
  c0.innerHTML =
  "<input type='checkbox' name='chkradios" + iteration + "' id='chkradios" + iteration + "' style='display:none' />" +
  "&nbsp;";

  // --- Column 2: station name ---
  var c1 = row.insertCell(1);
  c1.style.height = "28px";
  c1.innerHTML =
    "<input type='text' id='radioname" + iteration + "' name='radioname" + iteration + "' size='20' value='' />";

  // --- Column 3: station url ---
  var c2 = row.insertCell(2);
  c2.style.width  = "600px";
  c2.style.height = "28px";
  c2.innerHTML =
    "<input type='text' id='radiourl" + iteration + "' name='radiourl" + iteration + "' size='100' value='' style='width:100%' />";

  // --- Column 4: cover url ---
  var c3 = row.insertCell(3);
  c3.style.width  = "600px";
  c3.style.height = "28px";
  c3.innerHTML =
    "<input type='text' id='coverurl" + iteration + "' name='coverurl" + iteration + "' size='100' value='' style='width:100%' />";

  // Recreate jQuery Mobile styles
  $("#main_form").trigger("create");
}


/**
 * Scan()
 * - Redirects to index.cgi?do=scan
 */
function Scan() {
	url = './index.cgi?do=scan';
	document.location.href = url;
}


/* ================================================================================================
 * 10) Sonos test click handler (#tblzonen)
 * ================================================================================================ */

$(function () {

	$("#tblzonen").on("click", function (event) {

		var zonetest = event.target.value;

		// If no value found (click on empty cell/text) -> abort
		if (!zonetest) {
			return;
		}

		var texttospeech = document.getElementById('testtext').value;

		if (texttospeech === "") {
			// fallback text
			texttospeech = '<TMPL_VAR ZONES.SONOS_TEST_PLAYER> ' + zonetest;
		}

		// Fixed test volume (35)
		var testvolume = 35;

		// Read TTS parameters from UI
		var t2sengine  = $("input[name=t2s_engine]:checked").val() || "";
		var language   = $("#t2slang").val()   || "";
		var voice      = $("#voice").val()     || "";
		var apikey     = $("#apikey").val()    || "";
		var secretkey  = $("#seckey").val()    || "";

		$.ajax({
			url: '/plugins/<TMPL_VAR PLUGINDIR>/bin/player_on_test.php',
			type: 'post',
			dataType: 'json',
			data: {
				room:       zonetest,
				text:       texttospeech,
				volume:     testvolume,
				t2sengine:  t2sengine,
				language:   language,
				voice:      voice,
				apikey:     apikey,
				secretkey:  secretkey
			}
		})
		.done(function (response) {
			if (response && response.success) {
				console.log("Action Test Text-to-speech OK:", response.message);
			} else {
				console.log("Action Test Text-to-speech failed:", response ? response.message : "No response");
				console.warn(response && response.message ? response.message : "Fehler beim Starten der Testausgabe.");
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			console.log("AJAX error:", textStatus, errorThrown);
			console.warn("Fehler beim Aufruf von player_on_test.php: " + textStatus);
		})
		.always(function () {
			console.log("Action Test Text-to-speech finished");
		});
	});

});


/* ================================================================================================
 * 11) Soundbar config / TV monitor handling
 * ================================================================================================ */

/**
 * getsbconfig()
 * - Loads soundbar config via index.cgi action=soundbars
 * - Updates a lot of UI elements depending on response structure
 * - Leaves validation as-is (no abort, tvmonerr flag toggling)
 */
function getsbconfig() {

	function setLbSwitchValueLocal(id, value) {
		setCustomFlipswitchValue(id, value);

		var $wrap = $('.lb-flipswitch[data-input="' + id + '"]');
		var $cb = $('#' + id);
		var $hidden = $('#' + id + '_hidden');

		if (typeof syncCustomFlipswitch === "function") {
			syncCustomFlipswitch($wrap);
		} else {
			var checked = String(($hidden.val() || "")).toLowerCase() === "true";
			$cb.prop('checked', checked);
			$cb.val(checked ? 'true' : 'false');
			$wrap.toggleClass('is-on', checked);
			$wrap.toggleClass('is-disabled', $cb.is(':disabled'));
		}
	}

	function setLbSwitchDisabledLocal(id, disabled) {
		var $wrap = $('.lb-flipswitch[data-input="' + id + '"]');
		var $cb = $('#' + id);

		if (!$cb.length) {
			return;
		}

		$cb.prop("disabled", !!disabled);

		if (typeof syncCustomFlipswitch === "function") {
			syncCustomFlipswitch($wrap);
		} else {
			$wrap.toggleClass('is-disabled', !!disabled);
			$wrap.toggleClass('is-on', $cb.is(':checked'));
		}
	}

	function setSoundbarSelectStateLocal(id, enabled, fallbackValue) {
		var $select = $("#" + id);

		if (!$select.length) {
			return;
		}

		if (!enabled && typeof fallbackValue !== "undefined") {
			$select.val(fallbackValue);
		}

		$select.prop("disabled", !enabled);

		try {
			if (enabled) {
				$select.selectmenu("enable");
			} else {
				$select.selectmenu("disable");
			}
			$select.selectmenu("refresh", true);
		} catch (e) {
			$select.trigger("change");
		}
	}

	$.ajax({
		url: 'index.cgi',
		type: 'post',
		data: { action: 'soundbars' },
		dataType: 'json',
		async: false,
		success: function (data, textStatus, jqXHR) {

			$.each(data, function (index, valu) {

				if (valu[13] == 'SB') {
					console.log(valu);

					var hasSavedConfig = ((valu.length == 15 || valu.length == 17) && valu[14]);
					var hasSub = (valu[8] !== 'NOSUB');
					var hasSur = (valu[10] !== 'NOSUR');

					/* ------------------------------------------------------------------
					 * SUB capability handling
					 * Source of truth:
					 * - valu[8] => SUB / NOSUB
					 * ------------------------------------------------------------------ */
					if (hasSub) {
						setLbSwitchDisabledLocal("tvmonnightsub_" + index, false);
						setLbSwitchDisabledLocal("tvmonnightsubn_" + index, false);

						setLbSwitchValueLocal(
							"tvmonnightsub_" + index,
							(hasSavedConfig && typeof valu[14].tvmonnightsub !== "undefined")
								? valu[14].tvmonnightsub
								: "false"
						);

						setLbSwitchValueLocal(
							"tvmonnightsubn_" + index,
							(hasSavedConfig && typeof valu[14].tvsubnight !== "undefined")
								? valu[14].tvsubnight
								: "false"
						);

						$("#tvsublevel_" + index).val(
							(hasSavedConfig && typeof valu[14].tvsublevel !== "undefined")
								? valu[14].tvsublevel
								: "0"
						);

						$("#tvmonnightsublevel_" + index).val(
							(hasSavedConfig && typeof valu[14].tvmonnightsublevel !== "undefined")
								? valu[14].tvmonnightsublevel
								: "0"
						);

						setSoundbarSelectStateLocal("tvsublevel_" + index, true);
						setSoundbarSelectStateLocal("tvmonnightsublevel_" + index, true);

					} else {
						setLbSwitchValueLocal("tvmonnightsub_" + index, "false");
						setLbSwitchValueLocal("tvmonnightsubn_" + index, "false");

						setLbSwitchDisabledLocal("tvmonnightsub_" + index, true);
						setLbSwitchDisabledLocal("tvmonnightsubn_" + index, true);

						setSoundbarSelectStateLocal("tvsublevel_" + index, false, "0");
						setSoundbarSelectStateLocal("tvmonnightsublevel_" + index, false, "0");
					}

					/* ------------------------------------------------------------------
					 * SURROUND capability handling
					 * Source of truth:
					 * - valu[10] => SUR / NOSUR
					 * ------------------------------------------------------------------ */
					if (hasSur) {
						setLbSwitchDisabledLocal("tvmonsurr_" + index, false);

						setLbSwitchValueLocal(
							"tvmonsurr_" + index,
							(hasSavedConfig && typeof valu[14].tvmonsurr !== "undefined")
								? valu[14].tvmonsurr
								: "false"
						);

						$("#tvsurrlevel_" + index).val(
							(hasSavedConfig && typeof valu[14].tvsurrlevel !== "undefined")
								? valu[14].tvsurrlevel
								: "0"
						);

						setSoundbarSelectStateLocal("tvsurrlevel_" + index, true);

					} else {
						setLbSwitchValueLocal("tvmonsurr_" + index, "false");
						setLbSwitchDisabledLocal("tvmonsurr_" + index, true);

						setSoundbarSelectStateLocal("tvsurrlevel_" + index, false, "0");
					}

					/* ------------------------------------------------------------------
					 * Main soundbar config
					 * ------------------------------------------------------------------ */
					if (hasSavedConfig) {
						$("#sbzone_" + index).val(index).text("refresh");

						setLbSwitchValueLocal("usesb_" + index, valu[14].usesb);
						setLbSwitchValueLocal("tvmonspeech_" + index, valu[14].tvmonspeech);
						setLbSwitchValueLocal("tvmonnight_" + index, valu[14].tvmonnight);

						$("#fromtime_" + index).val(
							typeof valu[14].fromtime !== "undefined" ? valu[14].fromtime : ""
						);

						$("#tvvol_" + index).val(
							typeof valu[14].tvvol !== "undefined" ? valu[14].tvvol : ""
						).text("refresh");

						$("#tvbass_" + index).val(
							typeof valu[14].tvbass !== "undefined" ? valu[14].tvbass : ""
						).text("refresh");

						$("#tvtreble_" + index).val(
							typeof valu[14].tvtreble !== "undefined" ? valu[14].tvtreble : ""
						).text("refresh");

						/* --------------------------------------------------------------
						 * IMPORTANT:
						 * Apply visibility/state logic only AFTER all values are set
						 * -------------------------------------------------------------- */
						toggleSoundbar(index);
						toggleNightFieldsByTime(index);
						toggleSoundbarSubLevels(index);
						toggleSoundbarSurrLevel(index);

						if (typeof updateSoundbarColspan === "function") {
							updateSoundbarColspan(index);
						}

						/* --------------------------------------------------------------
						 * tvgrpstop checkboxes (standard jQM checkboxes)
						 * -------------------------------------------------------------- */
						setTimeout(function () {

							var saved = [];

							if (valu[14] && Array.isArray(valu[14].tvgrpstop)) {
								saved = valu[14].tvgrpstop;
							}

							$("input[name='tvgrpstop_" + index + "']").each(function () {
								var roomValue = $(this).val();
								var shouldCheck = saved.includes(roomValue);

								$(this).prop("checked", shouldCheck);
								refreshJqmCheckboxes(this);
							});

						}, 100);
					}

					/* ------------------------------------------------------------------
					 * Validation
					 * IMPORTANT: custom flipswitch state must be read from hidden input
					 * ------------------------------------------------------------------ */
					var usage = $("#usesb_" + index + "_hidden").val();

					if (usage == "true") {
						var tvmonvol = document.getElementById("tvvol_" + index).value;
						var tvmontreble = document.getElementById("tvtreble_" + index).value;
						var tvmonbass = document.getElementById("tvbass_" + index).value;

						if (tvmonvol.length == 0) {
							tvmonerr = "false";
						}
						if (tvmontreble.length == 0) {
							tvmonerr = "false";
						}
						if (tvmonbass.length == 0) {
							tvmonerr = "false";
						}

						//validate_enable("#tvvol_" + index);
						//validate_enable("#tvtreble_" + index);
						//validate_enable("#tvbass_" + index);
					}
				}
			});
		}
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
	})
	.always(function (data) {
		console.log("Action get Soundbars Config executed");
	});
}

/**
 * validateSB()
 * - Checks if any SB elements exist (#sbX) and shows/hides TV monitor settings accordingly
 */
function hasAnySoundbar() {
    return $("[id^='tblsb_']").length > 0 ||
           $("[id^='soundbar_header_']").length > 0 ||
           $("[id^='soundbar_row_']").length > 0 ||
           $("[id^='usesb_']").length > 0;
}

function validateSB() {
    var hasSb = hasAnySoundbar();

    if (hasSb) {
        $('.tvmon_master, .tvmon_switch_row, .tvmon_switch, .tvmon_header').show();
    } else {
        if ($('#tvmon').length) {
            setCustomFlipswitchValue('tvmon', false);
        }

        $('.tvmon_master, .tvmon_switch_row, .tvmon_switch, .tvmon_header, .tvmon_body, .tvmon_extra').hide();
    }

    refreshJqmCheckboxes();
}

/**
 * validateTVMon()
 * - Shows/hides TV monitor blocks based on #tvmon flipswitch state
 */
function validateTVMon() {
	if (!hasAnySoundbar()) {
        $('.tvmon_master, .tvmon_switch_row, .tvmon_switch, .tvmon_header, .tvmon_body, .tvmon_extra').hide();
        return;
    }
    var tvmonitor = $('#tvmon').is(':checked');

    if (tvmonitor) {
        $('.tvmon_header').show();
        $('.tvmon_body').show();
        $('.tvmon_extra').show();
        console.log("TV Monitor On");

        /* --------------------------------------------------------------
         * IMPORTANT:
         * After global TV Monitor is shown again, every individual
         * soundbar row must be restored according to usesb_<room>.
         * Otherwise rows appear although the room switch is OFF.
         * -------------------------------------------------------------- */
        $("input[id^='usesb_']").each(function () {
            var room = this.id.replace("usesb_", "");

            toggleSoundbar(room);
            toggleNightFieldsByTime(room);
            toggleSoundbarSubLevels(room);
			toggleSoundbarSurrLevel(room);

            if (typeof updateSoundbarColspan === "function") {
                updateSoundbarColspan(room);
            }
        });

    } else {
        $('.tvmon_header').hide();
        $('.tvmon_body').hide();
        $('.tvmon_extra').hide();
        console.log("TV Monitor Off");
    }

    refreshJqmCheckboxes();
}

/**
 * TV Monitor onChange handler
 */
$("#tvmon").on("change", function () {
	var tvmonitor = $(this).is(':checked');

	if (tvmonitor) {
		timeout('<TMPL_VAR TEMPLATE.TV_MONITOR_ON>', 'OK', 'info', 'TV Monitor', '3000');
	} else {
		timeout('<TMPL_VAR TEMPLATE.TV_MONITOR_OFF>', 'OK', 'info', 'TV Monitor', '3300');
	}
});

/**
 * Show or hide the soundbar detail rows depending on the Soundbar switch state.
 * ON  -> show
 * OFF -> hide
 */
function toggleSoundbar(room) {
    var header  = document.getElementById("soundbar_header_" + room);
    var row     = document.getElementById("soundbar_row_" + room);
    var usesb   = document.getElementById("usesb_" + room);
    var topcell = document.getElementById("soundbar_topcell_" + room);

    if (!header || !row || !usesb || !topcell) {
        return;
    }

    if (usesb.checked) {
        header.style.display = "";
        row.style.display = "";
        topcell.classList.remove("sb_topcell_collapsed");
    } else {
        header.style.display = "none";
        row.style.display = "none";
        topcell.classList.add("sb_topcell_collapsed");
    }

    toggleNightFieldsByTime(room);
    toggleSoundbarSubLevels(room);
	toggleSoundbarSurrLevel(room);

    if (typeof updateSoundbarColspan === "function") {
        updateSoundbarColspan(room);
    }
}

function updateSoundbarColspan(room) {
    var visibleCols = $("#soundbar_header_" + room).children("th:visible").length;
    $("#soundbar_topcell_" + room).attr("colspan", visibleCols);
}

/**
 * Show/hide the two SurLevel fields depending on the related Surround switch.
 * We only hide the inner wrapper, not the whole <td>, so the table layout stays stable.
 */
function toggleSoundbarSurrLevel(room) {
    var $surrSwitch = $("#tvmonsurr_" + room);
    var $surrHidden = $("#tvmonsurr_" + room + "_hidden");

    var isOn = false;

    if ($surrHidden.length) {
        isOn = lbParseBool($surrHidden.val());
    } else if ($surrSwitch.length) {
        isOn = $surrSwitch.is(":checked");
    }

    var showSurrCol = $surrSwitch.length &&
                      isOn &&
                      !$surrSwitch.prop("disabled");

    $(".sb_tvsurrlevel_col_" + room).css("display", showSurrCol ? "table-cell" : "none");

    updateSoundbarColspan(room);
}

/**
 * Show/hide the two SubLevel fields depending on the related Subwoofer switch.
 * We only hide the inner wrapper, not the whole <td>, so the table layout stays stable.
 */
function toggleSoundbarSubLevels(room) {
    var $tvSubSwitch    = $("#tvmonnightsub_" + room);
    var $nightSubSwitch = $("#tvmonnightsubn_" + room);
    var $fromTimeInput  = $("#fromtime_" + room);

    var fromTimeValue   = $.trim($fromTimeInput.val() || "");
    var hasValidTime    = /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(fromTimeValue);

    var showTvSubCol = $tvSubSwitch.length &&
                       $tvSubSwitch.is(":checked") &&
                       !$tvSubSwitch.prop("disabled");

    var showNightSubCol = hasValidTime &&
                          $nightSubSwitch.length &&
                          $nightSubSwitch.is(":checked") &&
                          !$nightSubSwitch.prop("disabled");

    $(".sb_tvsublevel_col_" + room).css("display", showTvSubCol ? "table-cell" : "none");
    $(".sb_nightsublevel_col_" + room).css("display", showNightSubCol ? "table-cell" : "none");

    updateSoundbarColspan(room);
}

/**
 * Initialize SubLevel visibility for all soundbars.
 */
function initSoundbarSubLevels() {
    $("[id^='usesb_']").each(function () {
        var room = this.id.replace("usesb_", "");
        toggleSoundbarSubLevels(room);
		toggleSoundbarSurrLevel(room);
    });
}

/**
 * React live when one of the two Subwoofer switches is changed.
 */
$(document)
    .off("change.sbSubLevels")
    .on("change.sbSubLevels", "[id^='tvmonnightsub_'], [id^='tvmonnightsubn_']", function () {
        var room = this.id
            .replace("tvmonnightsubn_", "")
            .replace("tvmonnightsub_", "");

        toggleSoundbarSubLevels(room);
    });

$(document)
    .off("change.sbSurrLevel")
    .on("change.sbSurrLevel", "input[id^='tvmonsurr_']:not([id$='_hidden'])", function () {
        var room = this.id.replace("tvmonsurr_", "");
        toggleSoundbarSurrLevel(room);
    });

/**
 * Initialize all Soundbar rows on page load.
 */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[id^='usesb_']").forEach(function (el) {
        var room = el.id.replace("usesb_", "");
        toggleSoundbar(room);
    });
});

/**
 * Re-check UDP port visibility whenever the MQTT/UDP switch changes.
 */
$(document)
	.off("change.s4lUdpPortToggle", "#sendloxMQTT")
	.on("change.s4lUdpPortToggle", "#sendloxMQTT", function () {
	toggleUdpPortVisibility();
});

var sonosHealthPollTimer = null;
var sonosHealthWaitBaseTs = 0;
var sonosHealthWaitStartedAtMs = 0;

var SONOS_HEALTH_WAIT_STORAGE_KEY = "s4l_health_wait_pending";
var SONOS_HEALTH_WAIT_TS_KEY      = "s4l_health_wait_base_ts";
var SONOS_HEALTH_WAIT_MAX_MS      = 120000; // safety fallback: 2 minutes

function updateSonosHealthAmpel(statusClass) {
	var imgBase = "/plugins/<TMPL_VAR PLUGINDIR>/images/";
	var imgFile = "A-Rot.svg";

	if (statusClass === "ok") {
		imgFile = "A-Gruen.svg";
	} else if (statusClass === "warn") {
		imgFile = "A-Gelb.svg";
	} else {
		imgFile = "A-Rot.svg";
	}

	$("#sonosHealthAmpel").attr("src", imgBase + imgFile);
}

function initSonosHealthAmpel() {
	updateSonosHealthAmpel("<TMPL_VAR SONOS_HEALTH_STATUS_CLASS>");
}

function showListenerRestartWaitBox() {
	$("#listenerRestartWaitBox").stop(true, true).fadeIn(150);
}

function hideListenerRestartWaitBox() {
	$("#listenerRestartWaitBox").stop(true, true).fadeOut(150);
}

function getCurrentSonosHealthTimestamp() {
	return parseInt($("#sonosHealthTimestamp").val(), 10) || 0;
}

function rememberHealthWaitState(baseTs) {
	sessionStorage.setItem(SONOS_HEALTH_WAIT_STORAGE_KEY, "1");
	sessionStorage.setItem(SONOS_HEALTH_WAIT_TS_KEY, String(baseTs || 0));
}

function clearHealthWaitState() {
	sessionStorage.removeItem(SONOS_HEALTH_WAIT_STORAGE_KEY);
	sessionStorage.removeItem(SONOS_HEALTH_WAIT_TS_KEY);
}

function stopHealthWaitWatcher() {
	if (sonosHealthPollTimer) {
		clearInterval(sonosHealthPollTimer);
		sonosHealthPollTimer = null;
	}
}

function finishHealthWait() {
	stopHealthWaitWatcher();
	hideListenerRestartWaitBox();
	clearHealthWaitState();
}

function fetchSonosHealth(done) {
	$.ajax({
		url: window.location.pathname,
		type: "GET",
		dataType: "json",
		cache: false,
		data: {
			action: "get_sonos_health_json",
			_: Date.now()
		}
	}).done(function (response) {
		if (typeof done === "function") {
			done(response || null);
		}
	}).fail(function () {
		if (typeof done === "function") {
			done(null);
		}
	});
}

function handleFetchedSonosHealth(health) {
	if (!health) {
		if ((Date.now() - sonosHealthWaitStartedAtMs) >= SONOS_HEALTH_WAIT_MAX_MS) {
			finishHealthWait();
		}
		return;
	}

	var newTs = parseInt(health.timestamp, 10) || 0;
	var newStatusClass = health.status_class || "error";

	updateSonosHealthAmpel(newStatusClass);
	$("#sonosHealthTimestamp").val(newTs);

	if (newTs > sonosHealthWaitBaseTs) {
		finishHealthWait();
		return;
	}

	if ((Date.now() - sonosHealthWaitStartedAtMs) >= SONOS_HEALTH_WAIT_MAX_MS) {
		finishHealthWait();
	}
}

function startWaitingForNextSonosHealth(baseTs) {
	sonosHealthWaitBaseTs = parseInt(baseTs, 10) || 0;
	sonosHealthWaitStartedAtMs = Date.now();

	stopHealthWaitWatcher();
	showListenerRestartWaitBox();

	fetchSonosHealth(handleFetchedSonosHealth);

	sonosHealthPollTimer = setInterval(function () {
		fetchSonosHealth(handleFetchedSonosHealth);
	}, 5000);
}

function initPendingHealthWaitOnLoad() {
	if (!$("#sonosHealthTimestamp").length) {
		return;
	}

	var pending = sessionStorage.getItem(SONOS_HEALTH_WAIT_STORAGE_KEY);
	if (pending !== "1") {
		return;
	}

	var baseTs = parseInt(sessionStorage.getItem(SONOS_HEALTH_WAIT_TS_KEY) || "0", 10) || 0;
	var currentTs = getCurrentSonosHealthTimestamp();

	if (currentTs > baseTs) {
		finishHealthWait();
		return;
	}

	startWaitingForNextSonosHealth(baseTs);
}


/**
 * restartSonosListener()
 * - Calls index.cgi?action=restart_listener
 * - On success: reload page
 */
function restartSonosListener() {
	var baseTs = getCurrentSonosHealthTimestamp();

	rememberHealthWaitState(baseTs);
	showListenerRestartWaitBox();
	updateSonosHealthAmpel("error");

	$.ajax({
		url: window.location.pathname,
		type: "POST",
		cache: false,
		dataType: "json",
		data: {
			action: "restart_listener"
		}
	}).done(function (response) {
		if (response && response.success) {
			startWaitingForNextSonosHealth(baseTs);
		} else {
			finishHealthWait();
			alert("Listener restart failed.");
		}
	}).fail(function () {
		finishHealthWait();
		alert("Listener restart failed.");
	});
}

/*
 * Main settings save:
 * When the settings page form is submitted, remember the current
 * health timestamp and keep the wait box active across the reload
 * until a newer health timestamp appears.
 */
$(document).on("submit", "form", function () {
	if (!$("#sonosHealthTimestamp").length) {
		return;
	}

	if (!$("#sendlox_hidden").length || !$("#UDP").length) {
		return;
	}

	if (!hasLoxoneTransferChanges()) {
		return;
	}

	var baseTs = getCurrentSonosHealthTimestamp();

	updateSonosHealthAmpel("error");
	rememberHealthWaitState(baseTs);
	showListenerRestartWaitBox();
});

var initialLoxoneTransferState = null;

function getCurrentLoxoneTransferState() {
	var sendlox = ($("#sendlox_hidden").val() || "").toString().trim().toLowerCase();
	var udp     = ($("#UDP").val() || "").toString().trim();

	return {
		sendlox: sendlox,
		udp: udp
	};
}

function rememberInitialLoxoneTransferState() {
	if (!$("#sendlox_hidden").length || !$("#UDP").length) {
		return;
	}

	initialLoxoneTransferState = getCurrentLoxoneTransferState();
}

function hasLoxoneTransferChanges() {
	if (!initialLoxoneTransferState) {
		return false;
	}

	var currentState = getCurrentLoxoneTransferState();

	return (
		currentState.sendlox !== initialLoxoneTransferState.sendlox ||
		currentState.udp     !== initialLoxoneTransferState.udp
	);
}

$(document).on("pageinit", function () {
	initSonosHealthAmpel();
	initPendingHealthWaitOnLoad();

	setTimeout(function () {
		rememberInitialLoxoneTransferState();
	}, 300);
});

$(window).on("beforeunload pagehide", function () {
	stopHealthWaitWatcher();
});

/**
 * Shows custom tooltip and applies its visual styling.
 * The tooltip uses the plugin green color and removes any text shadow.
 */
function showGreenTooltip(selector) {
	$(selector).css({
		"background": "#6db33f",
		"color": "#ffffff",
		"text-shadow": "none",
		"box-shadow": "0 2px 10px rgba(0,0,0,0.18)",
		"display": "inline-block",
		"width": "max-content",
		"min-width": "220px",
		"max-width": "420px",
		"white-space": "normal",
		"pointer-events": "none"
	});

	$(selector + " div").css({
		"border-top-color": "#6db33f"
	});

	$(selector).stop(true, true).fadeIn(120);
}

/**
 * Hides a tooltip.
 */
function hideTooltip(selector) {
	$(selector).stop(true, true).fadeOut(120);
}

/**
 * Controls visibility of the UDP port field.
 *
 * Behavior:
 * - If the MQTT/UDP switch does not exist, the UDP port field is shown.
 * - If the switch exists and is on the right/checked position, the UDP port field is shown.
 * - Otherwise, the UDP port field is hidden.
 */
function toggleUdpPortVisibility() {
	if (!$("#sendloxMQTT").length) {
		$("#udpPortCell").css("display", "table-cell");
		return;
	}

	var switchIsRight = $("#sendloxMQTT").is(":checked");

	if (switchIsRight) {
		$("#udpPortCell").css("display", "table-cell");
	} else {
		$("#udpPortCell").hide();
	}
}

/**
 * Applies final layout and alignment fixes for the Loxone transfer row.
 *
 * This function:
 * - sets a fixed width for the Miniserver select field
 * - styles the generated jQuery Mobile button wrapper
 * - vertically centers the text inside the select button
 * - updates UDP port visibility
 * - calls selection() so the full communication block is shown/hidden correctly
 */
function initS4LTransferRow() {
	$("#ms").css({
		"width": "225px",
		"min-width": "225px",
		"max-width": "225px"
	});

	$("#ms-button").css({
		"width": "225px",
		"min-width": "225px",
		"max-width": "225px",
		"margin": "0",
		"display": "inline-block",
		"vertical-align": "middle",
		"box-sizing": "border-box",
		"height": "32px",
		"line-height": "32px",
		"text-align": "center",
		"padding-left": "28px",
		"padding-right": "28px",
		"padding-top": "0",
		"padding-bottom": "0"
	});

	$("#ms-button span, #ms-button .ui-btn-text").css({
		"display": "flex",
		"align-items": "center",
		"justify-content": "center",
		"width": "100%",
		"height": "30px",
		"line-height": "1",
		"text-align": "center",
		"padding-left": "0",
		"padding-right": "0",
		"position": "relative",
		"top": "-1px"
	});

	toggleUdpPortVisibility();
	selection();
}

function toggleUdpXmlButton() {
    var $udp = $('#UDP');

    // Fallback, falls das Feld kein id="UDP" hat
    if (!$udp.length) {
        $udp = $('input[name="UDP"]');
    }

    var udpVal = $.trim($udp.val() || '');

    if (udpVal === '') {
        $('#btn3').closest('.btnd').hide();
    } else {
        $('#btn3').closest('.btnd').show();
    }
}

/* ================================================================================================
 * 12) Backup/Restore/Delete buttons
 * ================================================================================================ */

// Save config file extern
$(function() {
	$("#savec").click(function(){
		console.log("Save pressed");
		$("#savec").attr("disabled", true);
		$.ajax({
			url: 'index.cgi',
			type: 'post',
			data: { action: 'saveconfig'},
			success: function( response, textStatus, jqXHR )  {
				if(response == true)	{
					// For wait 3 seconds
					setTimeout(function()  {
						location.reload();  // Refresh page
					}, 3000);
				}
				console.log( "Action file saved" );
				timeout('<TMPL_VAR BASIS.PLUGIN_BACKUP_SUCCESS>', 'OK', 'success', 'Backup Config', '3000');
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			dialog('<TMPL_VAR BASIS.PLUGIN_BACKUP_FAIL> Error: ' + errorThrown, '<TMPL_VAR BASIS.PLUGIN_BUTTON>', 'error', 'Backup Config');
			console.log(errorThrown);
		})
		.always(function(response) {
			console.log( "Action save file executed" );
		})
	});
});

// Restore config file extern
$(function() {
	$("#restorec").click(function(){
		console.log("Restore pressed");
		$("#restorec").attr("disabled", true);
		$.ajax({
			url: 'index.cgi',
			type: 'post',
			data: { action: 'restoreconfig'},
			success: function( response, textStatus, jqXHR )  {
				if(response == true)	{
					// For wait 3 seconds
					setTimeout(function()  {
						location.reload();  // Refresh page
					}, 3000);
				}
				console.log( "Action file restored" );
				timeout('<TMPL_VAR BASIS.PLUGIN_RESTORE_SUCCESS>', 'OK', 'success', 'Restore Config', '3000');
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			dialog('<TMPL_VAR BASIS.PLUGIN_RESTORE_FAIL> Error: ' + errorThrown, '<TMPL_VAR BASIS.PLUGIN_BUTTON>', 'error', 'Restore Config');
			console.log(errorThrown);
		})
		.always(function(response) {
			console.log( "Action restore file executed" );
		})
	});

	//location.reload();
	$('#main_form').trigger('create');
});


// Delete zone from config
$(document).on("click", "a.jsDelZone", function (e) {
  e.preventDefault();

  const $btn = $(this);
  const idx  = String($btn.data("idx") || "");
  const room = String($btn.data("room") || "");

  confirmDelete(
    "<TMPL_VAR ZONES.TEXT_DELETE>",
    "<TMPL_VAR ZONES.ASK_DELETE1>'" + room + "' <TMPL_VAR ZONES.ASK_DELETE2>",
    function () {
      $("#chkplayers" + idx).prop("checked", true);
      $btn.closest("tr").css("opacity", "0.4");
      $("#main_form").trigger("submit");
    },
    {
      deleteText: "<TMPL_VAR ZONES.BUTTON_CONFIRM>",
      cancelText: "<TMPL_VAR ZONES.BUTTON_CANCEL>",
      btnColor: "#6dac20",
      alertIcon: "warning"
    }
  );
});


/* ================================================================================================
 * 13) Dialog helpers
 * ================================================================================================ */

/**
 * confirmDelete(title, text, onConfirm, opts)
 * - Shows a SilverBox confirm dialog with Delete/Cancel buttons.
 * - Calls onConfirm() only if user clicks Delete.
 */
function confirmDelete(title, text, onConfirm, opts) {
	opts = opts || {};

	var btnColor   = opts.btnColor || "#6dac20";
	var delText    = opts.deleteText || "<TMPL_VAR ZONES.BUTTON_CONFIRM>";
	var cancelText = opts.cancelText || "<TMPL_VAR ZONES.BUTTON_CANCEL>";
	var icon       = opts.alertIcon || "warning";

	// Fallback if SilverBox is not available
	if (typeof silverBox !== "function") {
		if (confirm((title ? title + "\n\n" : "") + (text || ""))) {
			if (typeof onConfirm === "function") {
				onConfirm();
			}
		}
		return;
	}

	var boxOptions = {
		alertIcon: icon,
		title: { text: title || "Delete" },
		centerContent: true,

		confirmButton: {
			bgColor: btnColor,
			border: "10px",
			borderColor: btnColor,
			textColor: "#fff",
			text: delText,
			iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/confirm.svg",
			closeOnClick: true,
			onClick: function () {
				if (typeof onConfirm === "function") {
					onConfirm();
				}
			}
		},

		cancelButton: {
			bgColor: btnColor,
			border: "10px",
			borderColor: btnColor,
			textColor: "#fff",
			text: cancelText,
			iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/cancel.svg",
			closeOnClick: true
		}
	};

	if (opts.useHtml) {
		boxOptions.html = text || "<TMPL_VAR ZONES.ASK_DELETE>";
	} else {
		boxOptions.text = text || "<TMPL_VAR ZONES.ASK_DELETE>";
	}

	silverBox(boxOptions);
}

/**
 * dialog()
 * - SilverBox popup with confirm button
 */
function dialog(text, ButtonText, Icon='', Title) {
	// https://silverboxjs.ir/documentation/?v=latest
	silverBox({
		alertIcon: Icon,
		html: text,
		centerContent: true,
		title: {
			text: Title
		},
		confirmButton: {
			bgColor: "#6dac20",
			border: "10px",
			borderColor: "#6dac20",
			textColor: "#fff",
			text: ButtonText,
			closeOnClick: true,
			onClick: () => {$("button").button();
							$("restorec").button("enable");
			},
		}
	});
}

/**
 * timeout()
 * - SilverBox timer popup (auto closes)
 */
/**
 * timeout()
 * - SilverBox timer popup (auto closes)
 * - Icon = "info"    -> uses custom info.svg
 * - Icon = "warning" -> uses SilverBox alertIcon warning
 */
function timeout(text, ButtonText, Icon = 'info', Title, timeout) {
	// https://silverboxjs.ir/documentation/?v=latest

	var boxOptions = {
		timer: timeout,
		text: text,
		centerContent: true,
		title: {
			text: Title
		}
	};

	if (Icon === 'info') {
		boxOptions.customIcon = "/plugins/<TMPL_VAR PLUGINDIR>/web/images/info.svg";
	} else if (Icon === 'warning') {
		boxOptions.alertIcon = 'warning';
	} else if (Icon) {
		boxOptions.alertIcon = 'info';
	}

	silverBox(boxOptions);
}

/**
 * submitManualSonosIpScan() - OBSOLTE ? _
 *
 * - Sends manually entered Sonos IP(s) to index.cgi?action=save_vlan_ip
 * - Reuses the main "Scan Player" button when the manual IP field is visible
 */
function submitManualSonosIpScan(raw) {
    raw = (raw || '').trim();

    if (!raw) {
        dialog('Bitte mindestens eine Sonos-IP eingeben (z.B. 192.168.10.50).', "OK", "info", "IP-Adresse");
        $('#vlan_ips_input').focus();
        return false;
    }

    var ips = raw.split(/[\s,;]+/).filter(Boolean);
    var ipv4re = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/;

    var invalid = ips.filter(function (ip) {
        return !ipv4re.test(ip);
    });

    if (invalid.length > 0) {
        dialog(
            'Ungültige IP-Adresse(n): ' + invalid.join(', ') + '\nBitte nur gültige IPv4-Adressen eingeben.',
            "OK",
            "info",
            "IP-Adresse"
        );
        $('#vlan_ips_input').focus();
        return false;
    }

    var $btn = $('#btnplayerscan');
    $btn.addClass('ui-disabled');

    var $form = $('<form>', {
        method: 'POST',
        action: 'index.cgi'
    }).append(
        $('<input>', { type: 'hidden', name: 'action',   value: 'save_vlan_ip' }),
        $('<input>', { type: 'hidden', name: 'vlan_ips', value: raw })
    );

    $('body').append($form);
    $form.submit();

    return true;
}


/**
 * discover()
 * - If the manual IP field is visible, "Scan Player" submits the manual IP(s)
 * - Otherwise it starts the normal Auto Discovery workflow
 */
function discover() {
    var $manualInput = $('#vlan_ips_input:visible');

    if ($manualInput.length > 0) {
        submitManualSonosIpScan($manualInput.val());
        return;
    }

    // https://silverboxjs.ir/documentation/?v=latest
    silverBox({
        alertIcon: 'warning',
        text: '<TMPL_VAR ZONES.SONOS_SCAN_TEXT>',
        footer: "<a href='#'>Auto Discover Sonos</a>",
        centerContent: true,
        title: {
            text: '<TMPL_VAR ZONES.SONOS_SCAN_HEADER>'
        },
        confirmButton: {
            bgColor: "#6dac20",
            border: "10px",
            borderColor: "#6dac20",
            textColor: "#fff",
            text: '<TMPL_VAR ZONES.BUTTON_NEXT>',
            iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/confirm.svg",
            closeOnClick: false,
            onClick: () => {
				const form = document.createElement('form');
				form.method = 'POST';
				form.action = './index.cgi';
				form.style.display = 'none';

				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'do';
				input.value = 'scanning';

				form.appendChild(input);
				document.body.appendChild(form);
				form.submit();
			},
        },
        cancelButton: {
            bgColor: "#6dac20",
            border: "10px",
            borderColor: "#6dac20",
            textColor: "#fff",
            text: '<TMPL_VAR ZONES.BUTTON_BACK>',
            iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/cancel.svg",
            closeOnClick: true
        },
    });
}

/**
 * message()
 * - Shows save message popup (kept as-is)
 */
function message() {
	var isDetails = !!document.getElementById('detail_form');
	console.log(isDetails ? "Save" : "MESSAGE");
	timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', isDetails ? '3000' : '3500');
}

/**
 * unicastScanHintMessage()
 * Shows a warning after a failed MULTICAST/BROADCAST discovery scan.
 */
function unicastScanHintMessage() {
	var isDetails = !!document.getElementById('detail_form');
	console.log("MULTICAST/BROADCAST scan failed");
	dialog('<TMPL_VAR ZONES.INFO_UNICAST>', 'OK', 'warning', '<TMPL_VAR ZONES.INFO_UNICAST_HEADER>');
}


/**
 * Show MULTICAST/BROADCAST warning once if template marker exists.
 * After the warning timeout has expired, highlight and focus the manual IP input field.
 */
$(document).on('pagecreate pageshow', function () {
	if ($('#show_unicast_scan_hint').length < 1) {
		return;
	}

	if (window.__s4lox_unicast_scan_hint_shown === true) {
		return;
	}

	window.__s4lox_unicast_scan_hint_shown = true;

	window.setTimeout(function () {
		var isDetails = !!document.getElementById('detail_form');
		var popupTimeout = isDetails ? 3000 : 4500;

		unicastScanHintMessage();

		// Scroll input into view after SilverBox auto-close timeout has expired,
		// then highlight it and set focus.
		window.setTimeout(function () {
			var input = document.getElementById('vlan_ips_input');

			if (!input) {
				return;
			}

			var $input = $('#vlan_ips_input');

			// Highlight input field yellow
			setTvMonFieldHighlight($input, true);

			input.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});

			// Set focus after scrolling
			window.setTimeout(function () {
				try {
					input.focus({ preventScroll: true });
				} catch (e) {
					input.focus();
				}
			}, 250);

			// Remove yellow highlight as soon as the user starts entering/changing data
			$input
				.off('.s4loxUnicastHighlight')
				.one('input.s4loxUnicastHighlight change.s4loxUnicastHighlight paste.s4loxUnicastHighlight keydown.s4loxUnicastHighlight', function () {
					setTvMonFieldHighlight($input, false);
				});

		}, popupTimeout + 300);

	}, 250);
});

function refresh() {
	$("#langiso").textinput("refresh");
	$('#t2slang').selectmenu('refresh');
	$('#voice').selectmenu('refresh');
}


/* ================================================================================================
 * 14) Download helpers (XML export)
 * ================================================================================================ */

function downloadFile(filename) {
	var element = document.createElement('a');
	element.setAttribute('href','/plugins/<TMPL_VAR PLUGINDIR>/system/'  + filename);
	element.setAttribute('download', filename);
	document.body.appendChild(element);
	element.click();
	return;
}

function checkfile(filename, e) {
	$.post("/plugins/<TMPL_VAR PLUGINDIR>/system/ms_inbound.php");
	var url = '/plugins/<TMPL_VAR PLUGINDIR>/system/'  + filename;
	var http = new XMLHttpRequest();
	http.open('GET', url, false);
	http.send();
	//console.log("http status: " + http.status);
	if (http.status !== 200)  {
		dialog('<TMPL_VAR ERRORS.ERR_XM_TEMP>', 'OK', 'error', 'Check filename');
		$('html, body').animate({
			scrollTop: $("#zone1").offset().top
		}, 500);
		$("#zone1").focus();
		if (e) e.preventDefault();
	} else {
		//console.log("Filename: " + filename);
		downloadFile(filename);
	}
}


/* ================================================================================================
 * 15) Validation helpers (used by submit)
 * ================================================================================================ */

/**
 * showFail(sel, msg, title, e)
 * - Highlights field and shows a timed popup (no dialog)
 */
function showFail(sel, msg, title, e) {
	setTimeout(function () { $(sel).focus(); }, 50);
	$(sel).css('background-color','#FFFFC0');
	timeout(msg, 'OK', 'info', title, '2000');
	if (e) e.preventDefault();
	return false;
}

/**
 * validateVolumes(e)
 * - Validates per-player volume inputs (t2svol, sonosvol, maxvol)
 * - Must be unsigned int and <= 100
 * - Returns true on success
 */
function validateVolumes(e) {
	var iteration = parseInt(document.getElementById('countplayers').value, 10) + 1;

	function isIntUnsigned(s) { return /^\d+$/.test(s); }

	for (var i = 1; i < iteration; ++i) {

		var selT2S   = '#t2svol'   + i;
		var selSonos = '#sonosvol' + i;
		var selMax   = '#maxvol'   + i;

		if ($(selT2S).length !== 1 || $(selSonos).length !== 1 || $(selMax).length !== 1) {
			continue;
		}

		var t2sStr   = (($(selT2S).val() ?? '') + '').trim();
		var sonosStr = (($(selSonos).val() ?? '') + '').trim();
		var maxStr   = (($(selMax).val() ?? '') + '').trim();

		if (t2sStr === '' || !isIntUnsigned(t2sStr) || (parseInt(t2sStr,10) > 100)) {
			return showFail(selT2S, '<TMPL_VAR ZONES.ERROR_T2S_VOLUME_PLAYER>', 'T2S Volume', e);
		}

		if (sonosStr === '' || !isIntUnsigned(sonosStr) || (parseInt(sonosStr,10) > 100)) {
			return showFail(selSonos, '<TMPL_VAR ZONES.ERROR_SONOS_VOLUME_PLAYER>', 'Sonos Volume', e);
		}

		if (maxStr === '' || !isIntUnsigned(maxStr) || (parseInt(maxStr,10) > 100)) {
			return showFail(selMax, '<TMPL_VAR ZONES.ERROR_MAX_VOLUME_PLAYER>', 'Max. Volume', e);
		}
	}

	return true; // <-- important: explicit success
}

/**
 * fail(e, msg, title, focusSel)
 * - Shows error dialog, scrolls to #info and focuses a given selector
 */
function fail(e, msg, title, focusSel) {
	dialog(msg, 'OK', 'error', title);
	if (focusSel) {
		$('html, body').animate({ scrollTop: $("#info").offset().top }, 600);
		setTimeout(function(){ $(focusSel).focus(); }, 50);
	}
	if (e) e.preventDefault();
	return false;
}

/**
 * getSelectedEngine()
 * - Returns the checked TTS engine radio id (e.g. "tts_polly") or null
 */
function getSelectedEngine() {
	const ids = [
		'tts_polly','tts_voicerss','tts_google_cloud','tts_elevenlabs',
		'tts_piper','tts_respvoice','tts_azure'
	];
	for (const id of ids) {
		const el = document.getElementById(id);
		if (el && el.checked) return id;
	}
	return null;
}

/* =========================================================================================
 * Calendar URL validation (Waste Calendar JSON + iCal ICS)
 * -----------------------------------------------------------------------------------------
 * Purpose:
 * - Validate two input fields (by default: #wastecal and #cal) via index.cgi backend action
 * - Uses fetch() with Accept: application/json
 * - Shows inline status row directly after the input:
 *     - "Checking…" while request is running
 *     - error message on failure
 *     - hidden on success / empty field
 *
 * Backend contract (expected JSON):
 *   { ok: true }                       -> valid
 *   { ok: false, msg: "..." }          -> invalid with message
 *   { ok: false, error: "..." }        -> invalid with message
 *
 * Notes for template-rendered JS:
 * - Avoid writing literal template tags in comments/strings.
 * - This code is safe to be included via template include.
 * ========================================================================================= */

(function($){
  const TAG = '[caldav-validate]';

  /**
   * Determine endpoint for validation.
   * - Uses current location path and replaces last segment with "index.cgi"
   * - Falls back to "index.cgi" if anything goes wrong
   */
  function endpoint(){
    try { return location.pathname.replace(/[^/]+$/, 'index.cgi') || 'index.cgi'; }
    catch (e) { return 'index.cgi'; }
  }

  /* -----------------------------
   * Inline message row helpers
   * ----------------------------- */

  // Ensures a <div class="calmsg"> exists right after the input
  function ensureMsg($in){
    let $m = $in.next('.calmsg');
    if (!$m.length) {
      $m = $('<div class="calmsg hidden" aria-live="polite" aria-atomic="true"></div>');
      $in.after($m);
    }
    return $m;
  }

  function showPending($in){
    const $m = ensureMsg($in);
    $m.removeClass('error').text('Checking…').removeClass('hidden');
  }

  function showError($in, msg){
    const $m = ensureMsg($in);
    $m.addClass('error').text(msg).removeClass('hidden');
  }

  function clearMsg($in){
    const $m = ensureMsg($in);
    $m.addClass('hidden').text('');
  }

  /* -----------------------------
   * Server validation
   * ----------------------------- */

  /**
   * validateUrl(url, mode)
   * mode: "json" or "ics"
   * returns: { ok:boolean, error?:string, status?:number, raw?:any }
   */
  async function validateUrl(url, mode){
    const qs   = new URLSearchParams({ action:'validate_ics', url, mode });
    const href = endpoint() + '?' + qs.toString();

    const t0 = performance.now();
    console.log(TAG, 'start', { mode, url, href });

    try {
      const r = await fetch(href, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store',
        credentials: 'same-origin'
      });

      const ct = (r.headers.get('content-type') || '').toLowerCase();
      const dt = Math.round(performance.now() - t0);
      console.log(TAG, 'response', { status: r.status, ok: r.ok, contentType: ct, timeMs: dt });

      if (!r.ok) {
        let snippet = '';
        try { snippet = (await r.text()).slice(0, 200); } catch (e) {}
        return { ok:false, error:`HTTP ${r.status}`, status:r.status, raw:snippet };
      }

      let data = {};
      try { data = await r.json(); }
      catch (e) {
        console.warn(TAG, 'JSON parse failed:', e);
        return { ok:false, error:'Invalid server response (not valid JSON)' };
      }

      if (data && data.ok === true) {
        return { ok:true, raw:data };
      } else {
        const serverMsg = (data && (data.msg || data.error)) || 'Invalid response';
        return { ok:false, error: serverMsg, raw:data };
      }

    } catch (e) {
      console.error(TAG, 'network error:', e);
      return { ok:false, error:'Network error (server not reachable)', raw:String(e) };
    }
  }

  /* -----------------------------
   * UI marking
   * ----------------------------- */

  // Only mark red on error; keep neutral on success
  function mark($in, state){
    $in.removeClass('valid invalid');
    if (state === 'err') $in.addClass('invalid');
  }

  async function run(sel, mode){
    const $in = $(sel);
    const url = ($in.val() || '').trim();

    if (!url){
      console.log(TAG, 'empty -> neutral', sel);
      clearMsg($in);
      mark($in, null);
      return;
    }

    showPending($in);
    const res = await validateUrl(url, mode);

    if (res.ok){
      console.log(TAG, 'ok', sel, res.raw);
      clearMsg($in);   // success -> hide row
      mark($in, null); // keep neutral
    } else {
      // friendly messages by error type
      let msg = res.error || 'Unknown error';
      const lower = String(msg).toLowerCase();

      if (lower.includes('json')) {
        msg = 'Invalid JSON response – please check your URL/parameters.';
      } else if (/^http\s*\d+/.test(String(msg))) {
        msg = `Server error (${msg}).`;
      } else if (lower.includes('network')) {
        msg = 'Network error – server not reachable.';
      }

      showError($in, msg);
      mark($in, 'err');
      console.warn(TAG, 'invalid', { field: sel, mode, reason: res.error, status: res.status, raw: res.raw });
    }
  }

  /* -----------------------------
   * Event bindings
   * ----------------------------- */

  // Validate on blur/change only
  $(document)
    .on('blur change', '#wastecal', function(){ run('#wastecal', 'json'); })
    .on('blur change', '#cal',      function(){ run('#cal',      'ics');  });

  // Optionally validate prefilled values on load
  $(function(){
    if ($('#wastecal').val()) run('#wastecal','json');
    if ($('#cal').val())      run('#cal','ics');
  });

})(jQuery);



function setTvMonFieldHighlight($field, active) {
    var color = active ? '#FFFFC0' : '';
    $field.css('background-color', color);
    $field.closest('.ui-input-text').css('background-color', color);
}

function validateTvMonitorSoundbarFields(e) {
    var tvMonitorOn = false;

    if ($('#tvmon_hidden').length) {
        tvMonitorOn = lbParseBool($('#tvmon_hidden').val());
    } else if ($('#tvmon').length) {
        tvMonitorOn = $('#tvmon').is(':checked');
    }

    if (!tvMonitorOn) {
        return true;
    }

    function failField($field) {
        var msg = $field.hasClass('tvvol')
			? '<TMPL_VAR VOLUME_PROFILES.ERROR_VOLUME_PLAYER>'
			: '<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER>';

        setTvMonFieldHighlight($field, true);

        $('html, body').animate({
            scrollTop: Math.max(0, $field.offset().top - 120)
        }, 400);

        setTimeout(function () {
            $field.focus();
            $field.select();
        }, 60);

        timeout(msg, 'OK', 'info', 'TV Monitor', '2200');

        if (e) {
            e.preventDefault();
        }
        return false;
    }

    function validateOneField(id, min, max) {
        var $field = $('#' + id);

        if (!$field.length || $field.prop('disabled') || !$field.is(':visible')) {
            return true;
        }

        var raw = $.trim($field.val());

        if (raw === '') {
            return failField($field);
        }

        if (!/^-?\d+$/.test(raw)) {
            return failField($field);
        }

        var num = parseInt(raw, 10);

        if (num < min || num > max) {
            return failField($field);
        }

        setTvMonFieldHighlight($field, false);
        return true;
    }

    var isValid = true;

    $("[id^='usesb_']").each(function () {
        var room = this.id.replace("usesb_", "");
        var usesbOn = false;

        if ($("#usesb_" + room + "_hidden").length) {
            usesbOn = lbParseBool($("#usesb_" + room + "_hidden").val());
        } else {
            usesbOn = $("#usesb_" + room).is(':checked');
        }

        if (!usesbOn) {
            return true; // continue
        }

        if (!validateOneField("tvvol_" + room, 0, 100)) {
            isValid = false;
            return false;
        }

        if (!validateOneField("tvtreble_" + room, -10, 10)) {
            isValid = false;
            return false;
        }

        if (!validateOneField("tvbass_" + room, -10, 10)) {
            isValid = false;
            return false;
        }
    });

    return isValid;
}

/* Highlight wieder entfernen, sobald der User tippt/ändert */
$(document)
    .off('input.tvmonFieldValidate change.tvmonFieldValidate', '.tvvol, .tvtreble, .tvbass')
    .on('input.tvmonFieldValidate change.tvmonFieldValidate', '.tvvol, .tvtreble, .tvbass', function () {
        setTvMonFieldHighlight($(this), false);
    });

/* ================================================================================================
 * 16) Document ready: bind handlers + run initial state updates
 * ================================================================================================ */

$(document).ready(function(e) {
	console.log("documentreadyfunction");
	$(".usesb-flipswitch").flipswitch("refresh");

	toggleRadioAnnounce();
	
	$('#func_list').on('change', function () {
        toggleCronFields();
    });
    toggleCronFields();
	
	$('#follow_host').on('change', function () {
        toggleFollowDelayFields();
    });

    toggleFollowDelayFields();
	toggleUdpXmlButton();

	$(document).on('change click', '#announceradio, #announceradio_always', function () {
		toggleRadioAnnounce();
	});
	
	$("input[id^='fromtime_']").each(function () {
        var room = this.id.replace("fromtime_", "");
        toggleNightFieldsByTime(room);
        toggleSoundbarSubLevels(room);
    });

	// IMPORTANT: This block belongs to the SETTINGS page (TTS config).
	if (!document.getElementById('engine-selector')) {
		return;
	}

	$('#t2slang').change(populateVoice);

	initial_lang_voice_load();
	checkboxes();
	validateSB();
	validateTVMon();
	getsbconfig();
	initSoundbarSubLevels();
	updateLanguageDropdownForEngine();
	
	$("form#main_form").submit(function(e) {	// Main submit validation
		console.log("submit");

		if (!validateVolumes(e)) {
			return false;
		}
		if (!validateTvMonitorSoundbarFields(e)) {
			return false;
		}

		const selectlang  = (document.getElementById('t2slang')?.value || '').trim();
		const selectvoice = (document.getElementById('voice')?.value || '').trim();
		const apidata     = (document.getElementById('apikey')?.value || '').trim();
		const seckeydata  = (document.getElementById('seckey')?.value || '').trim();

		const engine = getSelectedEngine();
		if (!engine) {
			return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'T2S Engine', 'Provider');
		}

		// helper for language+voice requirement
		function requireLangVoice(title) {
			if (!selectlang)  return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', title, '#t2slang');
			if (!selectvoice) return fail(e, '<TMPL_VAR T2S.VALIDATE_VOICE>', title, '#voice');
			return true;
		}

		// Engine-specific rules
		switch (engine) {

			case 'tts_polly':
				if (apidata.length !== 20) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY20>', 'Polly', '#apikey');
				if (seckeydata.length !== 40) return fail(e, '<TMPL_VAR ERRORS.ERR_SECRETKEY>', 'Polly', '#seckey');
				if (!requireLangVoice('Polly')) return false;
				break;

			case 'tts_voicerss':
				if (apidata.length !== 32) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY32>', 'Voice RSS', '#apikey');
				if (!requireLangVoice('Voice RSS')) return false;
				break;

			case 'tts_google_cloud':
				if (apidata.length !== 39) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY39>', 'Google Cloud', '#apikey');
				if (!requireLangVoice('Google Cloud')) return false;
				break;

			case 'tts_elevenlabs':
				if (apidata.length !== 32 && apidata.length !== 51 && apidata.length !== 64) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY_ELEVEN>', 'ElevenLabs', '#apikey');
				if (!requireLangVoice('ElevenLabs')) return false;
				break;

			case 'tts_piper':
				if (!selectlang) return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'Piper', '#t2slang');
				break;

			case 'tts_respvoice':
				if (!selectlang) return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'Responsive Voice', '#t2slang');
				break;

			case 'tts_azure':
				if (![32,36,84].includes(apidata.length)) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY_AZURE>', 'Azure', '#apikey');
				if (!selectlang) return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'Azure', '#t2slang');
				break;
		}

		// UI refreshes (ok)
		$("#langiso").textinput("refresh");
		$("#info").trigger('create');

		// Checkbox values + at least one active?
		const iteration = parseInt(document.getElementById('countplayers').value, 10) + 1;

		for (let i = 1; i < iteration; ++i) {
			const el = document.getElementById('mainchk' + i);
			if (!el) continue;
			el.value = el.checked ? "on" : "off";
		}
		refreshJqmCheckboxes();
		
		// Check if min. 1 Player has been set as Emergency TTS
		let isChecked = false;
		const checkBoxes = document.getElementsByClassName('chk-checked');

		for (let i = 0; i < checkBoxes.length; i++) {
			if (checkBoxes[i].checked) {
				isChecked = true;
				break;
			}
		}

		if (!isChecked) {
			const focusSelector = '#mainchk$countplayers';

			dialog(
				'<TMPL_VAR ERRORS.ERR_CHECKBOX>',
				'ERROR',
				'error',
				'T2S Emergency'
			);

			const $target = $(focusSelector);

			if ($target.length) {
				$('html, body').stop(true, true).animate({
					scrollTop: $target.offset().top - 80
				}, 600, function () {
					const el = $target.get(0);

					if (el) {
						try {
							el.focus({ preventScroll: true });
						} catch (err) {
							el.focus();
						}
					}
				});
			}

			return false;
		}

		// validate jingle file selection
		var selectjingle = document.getElementById("file_gong").value;
		var selectt2s     = $("input[name=t2s_engine]:checked").val();

		if (!selectjingle && selectt2s) {
			$('html, body').animate({
				scrollTop: $("#info").offset().top
			}, 1000);

			dialog('<TMPL_VAR T2S.VALIDATE_JINGLE>', 'SELECT', 'error', 'T2S Jingle');
			$("#file_gong").focus();
			return false; // stops submit reliably
		}

		// show Save message
		timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3500');
		if (typeof setApiDirty === 'function') setApiDirty(false);
		return true;
	});
});


/* ================================================================================================
 * DETAILS page helpers (safe, only used if #detail_form exists)
 * ================================================================================================ */

function toggleCronFields() {
    var selectedValue = $('#func_list').val();

    // Anzeigen nur wenn eine echte Funktion gewählt wurde
    // "" = Placeholder oder None -> ausblenden
    if (selectedValue && selectedValue !== "") {
        $('#cron_label_cell, #cron_slider_cell').show();

        // ionRangeSlider nach dem Einblenden kurz updaten,
        // damit Breite korrekt berechnet wird
        var cronSlider = $('#cron').data('ionRangeSlider');
        if (cronSlider) {
            cronSlider.update({});
        }
    } else {
        $('#cron_label_cell, #cron_slider_cell').hide();
    }
}

function details_init() {
	select_update();
	load_radio_favorites_into_func_list();
	toggleRadioAnnounce();
}

function toggleRadioAnnounce() {
    var el  = document.getElementById('announceradio');
    var el1 = document.getElementById('announceradio_always');
    if (!el || !el1) return;

    if (el.checked || el1.checked) {
        $('.radioannounce').show();
    } else {
        $('.radioannounce').hide();
    }
}

function toggleFollowDelayFields() {
    var selectedValue = $('#follow_host').val();

    // Anzeigen nur wenn wirklich ein Player gewählt wurde
    // ausblenden bei "", "false" oder "keinem"
    if (selectedValue && selectedValue !== '' && selectedValue !== 'false' && selectedValue !== 'keinem') {
        $('#follow_delay_label_cell, #follow_delay_slider_cell').show();

        var followSlider = $('#waitleave').data('ionRangeSlider');
        if (followSlider) {
            followSlider.update({});
        }
    } else {
        $('#follow_delay_label_cell, #follow_delay_slider_cell').hide();
    }
}

function toggleNightFieldsByTime(room) {
    var $input = $("#fromtime_" + room);
    if (!$input.length) return;

    var value = $.trim($input.val());
    var isValid = /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);

    // Nur die "normalen" Night-Spalten schalten:
    // NIGHT + NIGHT SUB
    $(".sb_night_col_" + room)
        .not(".sb_nightsublevel_col_" + room)
        .css("display", isValid ? "table-cell" : "none");

    // Wenn keine gültige Uhrzeit gesetzt ist, Night-SubLevel immer ausblenden
    if (!isValid) {
        $(".sb_nightsublevel_col_" + room).css("display", "none");
    }

    // Night-SubLevel danach IMMER über den zugehörigen Flipswitch steuern
    toggleSoundbarSubLevels(room);
}

$(function () {
    $("input[id^='fromtime_']").each(function () {
        var room = this.id.replace("fromtime_", "");
        toggleNightFieldsByTime(room);
    });
});

$(document).on("change", "input[id^='tvmonnightsub_'], input[id^='tvmonnightsubn_']", function () {
    var room = this.id
        .replace("tvmonnightsub_", "")
        .replace("tvmonnightsubn_", "");

    toggleSoundbarSubLevels(room);
});

function details_init() {
	select_update();
	load_radio_favorites_into_func_list();

	toggleRadioAnnounce();

	setTimeout(function () {
		toggleRadioAnnounce();
	}, 50);
}

function select_update() {
	var upEl = document.getElementById('hw_update');
	if (!upEl) return;

	if (upEl.checked) {
		$('.update').show();
	} else {
		$('.update').hide();
	}
}

document.addEventListener('DOMContentLoaded', function () {
	select_update();
});

function load_radio_favorites_into_func_list() {
	if (!document.getElementById('func_list')) return;

	$.ajax({
		url: 'index.cgi',
		type: 'post',
		data: { action: 'getradio' },
		dataType: 'json',
		async: false,
		success: function (response) {
			$.each(response, function (index, value) {
				var result = value.split(',');
				$('#func_list').append('<option value="' + result[1] + '">Plugin Radio: ' + result[0] + '</option>');
			});
		}
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
	})
	.always(function () {
		$('#func_list').val('<TMPL_VAR VARIOUS.selfunction>');
		console.log("Action get Radio Favorites executed");
	});
}

/* =============================================================================
 * Sonos release selector + install handoff
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Load all available Sonos plugin releases via AJAX
 * - Prefer a newer version as default selection if available
 * - Otherwise preselect the currently installed version
 * - Highlight newer selected versions in red
 * - Show the "Installation" button only if the selected version differs
 *   from the installed one
 * - Open the LoxBerry plugin installer in a new browser window
 * - Automatically fill the installer's "archiveurl" field with the
 *   selected GitHub release ZIP URL
 * ============================================================================= */

var sonosInstalledVersion = '';

/* -----------------------------------------------------------------------------
 * normalizeVersion(v)
 * -----------------------------------------------------------------------------
 * Normalizes a version string:
 * - converts to string
 * - trims whitespace
 * - removes a leading "v" if present
 * ----------------------------------------------------------------------------- */
function normalizeVersion(v) {
	return String(v || '').trim().replace(/^v/i, '');
}

/* -----------------------------------------------------------------------------
 * buildSonosArchiveUrl(version)
 * -----------------------------------------------------------------------------
 * Builds the GitHub ZIP archive URL for a selected Sonos release tag.
 * ----------------------------------------------------------------------------- */
function buildSonosArchiveUrl(version) {
	version = normalizeVersion(version);
	if (!version) {
		return '';
	}
	return 'https://github.com/Liver64/LoxBerry-Sonos/archive/refs/tags/v' + version + '.zip';
}

/* -----------------------------------------------------------------------------
 * updateSonosReleaseInstallButton()
 * -----------------------------------------------------------------------------
 * Shows the install button only if the selected version differs from
 * the installed version.
 * ----------------------------------------------------------------------------- */
function updateSonosReleaseInstallButton() {
	var selected = normalizeVersion($('#sonos_release').val());
	var installed = normalizeVersion(sonosInstalledVersion);

	if (selected && installed && selected !== installed) {
		$('#sonos_release_install_wrap').show();
	} else {
		$('#sonos_release_install_wrap').hide();
	}
}

/* -----------------------------------------------------------------------------
 * updateSonosReleaseVisualState()
 * -----------------------------------------------------------------------------
 * Highlights the visible select text in red when the currently selected
 * dropdown entry is marked as "newer".
 * ----------------------------------------------------------------------------- */
function updateSonosReleaseVisualState() {
	var $sel = $('#sonos_release');
	var $selectedOption = $sel.find('option:selected');
	var isNewer = String($selectedOption.attr('data-is-newer') || '0') === '1';

	var $btn = $('#sonos_release-button');
	var $wrap = $btn.closest('.ui-select');

	if (isNewer) {
		$sel.addClass('sonos-release-newer').css({
			color: '#c62828',
			fontWeight: '700',
			webkitTextFillColor: '#c62828'
		});

		$btn.addClass('sonos-release-newer');
		$wrap.addClass('sonos-release-newer');
	} else {
		$sel.removeClass('sonos-release-newer').css({
			color: '',
			fontWeight: '',
			webkitTextFillColor: ''
		});

		$btn.removeClass('sonos-release-newer');
		$wrap.removeClass('sonos-release-newer');
	}
}

/* -----------------------------------------------------------------------------
 * openSonosReleaseInstall()
 * -----------------------------------------------------------------------------
 * Opens the LoxBerry plugin installer in a new browser window and injects
 * the selected release ZIP URL into the installer field "archiveurl".
 * ----------------------------------------------------------------------------- */
function openSonosReleaseInstall() {
	var selected = normalizeVersion($('#sonos_release').val());
	if (!selected) {
		return;
	}

	var archiveUrl = buildSonosArchiveUrl(selected);
	if (!archiveUrl) {
		return;
	}

	var installUrl = '/admin/system/plugininstall.cgi';
	var win = window.open(installUrl, '_blank');

	if (!win) {
		alert('The installation window was blocked by the browser.');
		return;
	}

	var tries = 0;
	var maxTries = 100;

	var timer = setInterval(function () {
		tries++;

		try {
			if (!win || win.closed) {
				clearInterval(timer);
				return;
			}

			var doc = win.document;
			if (!doc) {
				if (tries >= maxTries) {
					clearInterval(timer);
				}
				return;
			}

			var archiveField = doc.getElementById('archiveurl');
			if (archiveField) {
				archiveField.removeAttribute('readonly');
				archiveField.value = archiveUrl;

				if (typeof archiveField.dispatchEvent === 'function') {
					archiveField.dispatchEvent(new Event('input', { bubbles: true }));
					archiveField.dispatchEvent(new Event('change', { bubbles: true }));
				}

				archiveField.setAttribute('readonly', 'readonly');

				var pinField = doc.getElementById('securepin');
				if (pinField) {
					pinField.focus();
				}

				clearInterval(timer);
				return;
			}

			if (tries >= maxTries) {
				clearInterval(timer);
			}
		} catch (e) {
			if (tries >= maxTries) {
				clearInterval(timer);
			}
		}
	}, 200);
}

/* -----------------------------------------------------------------------------
 * loadSonosReleaseDropdown()
 * -----------------------------------------------------------------------------
 * Loads Sonos versions from the backend AJAX action:
 *   index.cgi?action=getsonosversions
 *
 * Behavior:
 * - fills the dropdown with available versions
 * - marks installed versions in the label
 * - marks newer versions in the label
 * - prefers the first newer version as default selection
 * - otherwise falls back to installed or latest_stable
 * - refreshes the jQuery Mobile selectmenu
 * - updates button visibility and red highlight afterwards
 * ----------------------------------------------------------------------------- */
function loadSonosReleaseDropdown() {
	$.getJSON('index.cgi?action=getsonosversions', function (data) {
		var $sel = $('#sonos_release');
		var installed = normalizeVersion(data.installed);
		var latestStable = normalizeVersion(data.latest_stable);
		var firstNewer = '';

		sonosInstalledVersion = installed;
		$sel.empty();

		if (data && data.releases && data.releases.length) {
			$.each(data.releases, function (_, rel) {
				var version = normalizeVersion(rel.version);
				var label = version;

				if (rel.is_newer) {
					label += ' (latest)';
					if (!firstNewer) {
						firstNewer = version;
					}
				}

				if (version === installed) {
					label += ' (installed)';
				}

				$sel.append($('<option>', {
					value: version,
					text: label
				}).attr('data-is-newer', rel.is_newer ? '1' : '0'));
			});

			/* Prefer the first newer version if available */
			if (firstNewer) {
				$sel.val(firstNewer);
			} else if (installed) {
				$sel.val(installed);
			} else if (latestStable) {
				$sel.val(latestStable);
			}
		} else {
			$sel.append($('<option>', {
				value: '',
				text: data && data.error ? data.error : 'No releases found'
			}));
		}

		if ($sel.data('mobile-selectmenu')) {
			$sel.selectmenu('refresh', true);
		}

		updateSonosReleaseInstallButton();
		updateSonosReleaseVisualState();
	}).fail(function () {
		var $sel = $('#sonos_release');
		$sel.empty().append($('<option>', {
			value: '',
			text: 'AJAX error loading releases'
		}));

		if ($sel.data('mobile-selectmenu')) {
			$sel.selectmenu('refresh', true);
		}

		$('#sonos_release_install_wrap').hide();
		$sel.removeClass('sonos-release-newer').css({
			color: '',
			fontWeight: '',
			webkitTextFillColor: ''
		});
		$('#sonos_release-button').removeClass('sonos-release-newer');
		$('#sonos_release-button').closest('.ui-select').removeClass('sonos-release-newer');
	});
}

/* -----------------------------------------------------------------------------
 * Event: dropdown selection changed
 * ----------------------------------------------------------------------------- */
$(document).on('change', '#sonos_release', function () {
	updateSonosReleaseInstallButton();

	/* Let jQuery Mobile update the visible select button first */
	setTimeout(function () {
		updateSonosReleaseVisualState();
	}, 0);
});

/* -----------------------------------------------------------------------------
 * Event: page initialization
 * ----------------------------------------------------------------------------- */
$(document).on('pageinit', function () {
	loadSonosReleaseDropdown();
});

/* -----------------------------------------------------------------------------
 * Testing Page
 * Version: TESTING_JSON_EDITOR_JS_V03_2026_06_08_REINDEX_AFTER_DELETE
 * ----------------------------------------------------------------------------- */
(function () {
        'use strict';

        var testingSourceActions = [
            'sonosplaylist',
            'pluginradio',
            'playfavorite'
        ];

        var selectedTestingJsonRow = null;

        function textOf(id, fallback) {
            var el = document.getElementById(id);
            return el ? el.textContent : (fallback || '');
        }

        window.setTestingAction = function (action) {
            var actionEl = document.getElementById('testing_action');
            if (actionEl) {
                actionEl.value = action || '';
            }
        };

        function getTestingActionFromContext() {
            var active = document.activeElement;
            if (active && active.closest && active.closest('.testing-json-collapsible')) {
                return 'save_json';
            }

            var actionEl = document.getElementById('testing_action');
            var action = actionEl ? String(actionEl.value || '') : '';

            if (action !== '') {
                return action;
            }

            return 'execute';
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatTestingConfirmText(value) {
            value = String(value || '').trim();

            var markerPos = value.indexOf('!!');
            var mainText = value;
            var warningText = '';

            if (markerPos >= 0) {
                mainText = value.substring(0, markerPos).trim();
                warningText = value.substring(markerPos).trim();
            }

            var html = '';

            if (mainText !== '') {
                html += '<p style="margin:0 0 10px 0;">' + escapeHtml(mainText) + '</p>';
            }

            if (warningText !== '') {
                html += '<p style="margin:18px 0 0 0;">'
                    + '<span style="font-weight:700 !important; color:red;">'
                    + escapeHtml(warningText)
                    + '</span>'
                    + '</p>';
            }

            return html;
        }

        function updateTestingDropdown(details) {
            if (!details) {
                return;
            }

            var summary = details.querySelector('summary');
            var placeholder = details.getAttribute('data-placeholder') || '';
            var checked = details.querySelectorAll('input[type="checkbox"]:checked');
            var values = [];

            checked.forEach(function (input) {
                values.push(input.value);
            });

            if (summary) {
                summary.textContent = values.length ? values.join(', ') : placeholder;
            }
        }

        function getSelectedTestingZone() {
            var zone = document.querySelector('input[name="testing_zone"]:checked');
            return zone ? zone.value : '';
        }

        function syncTestingMemberOptions() {
            var selectedZone = getSelectedTestingZone();
            var memberDetails = document.getElementById('testing_member_details');

            if (!memberDetails) {
                return;
            }

            memberDetails.querySelectorAll('input[name="testing_member"]').forEach(function (input) {
                var label = input.closest('label');
                var isZone = selectedZone && input.value === selectedZone;

                if (isZone) {
                    input.checked = false;
                    input.disabled = true;
                    if (label) {
                        label.style.setProperty('display', 'none', 'important');
                    }
                } else {
                    input.disabled = false;
                    if (label) {
                        label.style.removeProperty('display');
                    }
                }
            });

            updateTestingDropdown(memberDetails);
        }

        function showTestingAlert(message) {
            if (typeof silverBox === 'function') {
                silverBox({
                    alertIcon: 'warning',
                    html: message,
                    centerContent: true,
                    title: { text: textOf('testing_error_title', 'Testing') },
                    confirmButton: {
                        bgColor: '#6dac20',
                        border: '10px',
                        borderColor: '#6dac20',
                        textColor: '#fff',
                        text: textOf('testing_confirm_button', 'OK'),
                        closeOnClick: true
                    }
                });
            } else {
                alert(message);
            }
        }

        window.confirmTestingExecute = function (form) {
            var scenario = document.getElementById('testing_scenario');
            var zone = document.querySelector('input[name="testing_zone"]:checked');

            if (!scenario || !scenario.value) {
                showTestingAlert(textOf('testing_error_scenario_required', 'Please select a test scenario.'));
                return false;
            }

            if (!zone) {
                showTestingAlert(textOf('testing_error_zone_required', 'Please select one online zone.'));
                return false;
            }

            if (typeof silverBox !== 'function') {
                var fallbackConfirmText = textOf(
                    'testing_confirm_text',
                    'Do you really want to execute this test scenario?'
                );

                fallbackConfirmText = fallbackConfirmText.replace('!!', '\n\n!!');

                if (confirm(textOf('testing_confirm_title', 'Execute test') + '\n\n' + fallbackConfirmText)) {
                    form.submit();
                }

                return false;
            }

            silverBox({
                alertIcon: 'warning',
                html: formatTestingConfirmText(textOf('testing_confirm_text', 'Do you really want to execute this test scenario?')),
                centerContent: true,
                title: { text: textOf('testing_confirm_title', 'Execute test') },
                confirmButton: {
                    bgColor: '#6dac20',
                    border: '10px',
                    borderColor: '#6dac20',
                    textColor: '#fff',
                    text: textOf('testing_confirm_button', 'Execute'),
                    closeOnClick: false,
                    onClick: function () {
                        form.submit();
                    }
                },
                cancelButton: {
                    bgColor: '#6dac20',
                    border: '10px',
                    borderColor: '#6dac20',
                    textColor: '#fff',
                    text: textOf('testing_cancel_button', 'Cancel'),
                    closeOnClick: true
                }
            });

            return false;
        };

        function setTestingInputHighlight(input, active) {
            if (!input) {
                return;
            }

            if (typeof setTvMonFieldHighlight === 'function' && typeof window.jQuery === 'function') {
                setTvMonFieldHighlight($(input), !!active);
                return;
            }

            var color = active ? '#FFFFC0' : '';
            input.style.backgroundColor = color;

            var wrapper = input.closest ? input.closest('.ui-input-text') : null;
            if (wrapper) {
                wrapper.style.backgroundColor = color;
            }
        }

        function clearTestingSourceValidationState(sourceInput) {
            if (!sourceInput) {
                return;
            }

            sourceInput.classList.remove('testing-json-source-error');
            setTestingInputHighlight(sourceInput, false);

            if (typeof window.jQuery === 'function') {
                $(sourceInput).closest('.ui-input-text').removeClass('testing-json-source-error');
            }
        }

        function isTestingSourceInputVisible(sourceInput) {
            if (!sourceInput || sourceInput.disabled) {
                return false;
            }

            var cell = sourceInput.closest ? sourceInput.closest('.testing-json-source-cell, td') : null;
            var wrapper = sourceInput.closest ? sourceInput.closest('.ui-input-text') : null;

            if (cell && cell.classList && cell.classList.contains('testing-json-source-hidden')) {
                return false;
            }

            if (cell && window.getComputedStyle(cell).display === 'none') {
                return false;
            }

            if (wrapper && window.getComputedStyle(wrapper).display === 'none') {
                return false;
            }

            if (window.getComputedStyle(sourceInput).display === 'none') {
                return false;
            }

            return true;
        }

        function focusTestingSourceInput(row, sourceInput) {
            if (!row || !sourceInput) {
                return;
            }

            markTestingJsonRow(row);
            sourceInput.classList.add('testing-json-source-error');

            if (typeof window.jQuery === 'function') {
                $(sourceInput).closest('.ui-input-text').addClass('testing-json-source-error');
            }

            setTestingInputHighlight(sourceInput, true);

            try {
                row.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            } catch (e) {
                row.scrollIntoView(true);
            }

            window.setTimeout(function () {
                try {
                    sourceInput.focus({ preventScroll: true });
                } catch (e) {
                    sourceInput.focus();
                }

                try {
                    sourceInput.select();
                } catch (e) {
                    /* ignore for unsupported input types */
                }
            }, 250);
        }

        function validateTestingJsonSourceFields() {
            var table = getTestingJsonTable();
            var firstInvalidInput = null;
            var firstInvalidRow = null;
            var firstInvalidRowNumber = '';

            updateTestingSourceVisibility();

            if (!table || !table.tBodies.length) {
                return true;
            }

            table.tBodies[0].querySelectorAll('tr.testing-json-row').forEach(function (row) {
                var sourceInput = row.querySelector('input.testing-json-source[name^="testing_source_"]');

                if (!sourceInput) {
                    return;
                }

                if (!isTestingSourceInputVisible(sourceInput)) {
                    clearTestingSourceValidationState(sourceInput);
                    return;
                }

                if (String(sourceInput.value || '').trim() !== '') {
                    clearTestingSourceValidationState(sourceInput);
                    return;
                }

                if (!firstInvalidInput) {
                    firstInvalidInput = sourceInput;
                    firstInvalidRow = row;

                    var numberCell = row.querySelector('.testing-json-number');
                    if (numberCell) {
                        firstInvalidRowNumber = String(numberCell.textContent || '').trim();
                    }
                }
            });

            if (!firstInvalidInput) {
                return true;
            }

            showTestingAlert(
                textOf('testing_error_source_required', 'Bitte PL/Radio ausfüllen') +
                (firstInvalidRowNumber !== '' ? ' in Zeile ' + firstInvalidRowNumber : '') +
                '.'
            );

            focusTestingSourceInput(firstInvalidRow, firstInvalidInput);
            return false;
        }

        window.confirmTestingSubmit = function (form) {
            var action = getTestingActionFromContext();
            window.setTestingAction(action);

            if (action === 'save_json') {
                return validateTestingJsonSourceFields();
            }

            return window.confirmTestingExecute(form);
        };

        function injectTestingJsonCss() {
            if (document.getElementById('testing-json-runtime-css')) {
                return;
            }

            var css = ''
                + 'tr.testing-json-row-selected > td {'
                + 'outline: 2px solid #6dac20 !important;'
                + 'outline-offset: -2px !important;'
                + '}'
                + '.testing-json-source-hidden .testing-json-source,'
                + '.testing-json-source-hidden .ui-input-text,'
                + '.testing-json-source-hidden .ui-input-text input.testing-json-source {'
                + 'display: none !important;'
                + '}'
                + '.testing-json-source-error,'
                + '.testing-json-source-error input {'
                + 'background-color: #FFFFC0 !important;'
                + 'border-color: #ff9800 !important;'
                + '}';

            var style = document.createElement('style');
            style.id = 'testing-json-runtime-css';
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        }

        function setTestingJsonIndexedName(row, prefix, rowIndex) {
            if (!row || !prefix) {
                return;
            }

            row.querySelectorAll('[name^="' + prefix + '"]').forEach(function (field) {
                field.setAttribute('name', prefix + rowIndex);
            });
        }

        function reindexTestingJsonRowFields(row, rowIndex) {
            if (!row) {
                return;
            }

            setTestingJsonIndexedName(row, 'testing_status_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_name_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_risk_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_url_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_timeout_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_category_', rowIndex);
            setTestingJsonIndexedName(row, 'testing_source_', rowIndex);
        }

        function renumberTestingJsonRows() {
            var table = document.getElementById('testing_json_table');
            if (!table || !table.tBodies.length) {
                return;
            }

            var rows = table.tBodies[0].querySelectorAll('tr.testing-json-row');
            rows.forEach(function (row, index) {
                var rowIndex = index + 1;
                var number = row.querySelector('.testing-json-number');

                if (number) {
                    number.textContent = String(rowIndex);
                }

                reindexTestingJsonRowFields(row, rowIndex);
            });

            var countEl = document.getElementById('testing_json_count');
            if (countEl) {
                countEl.value = String(rows.length);
            }
        }

        function getTestingJsonTable() {
            return document.getElementById('testing_json_table');
        }

        function getValidSelectedTestingJsonRow() {
            var table = getTestingJsonTable();

            if (!table || !table.tBodies.length || !selectedTestingJsonRow) {
                return null;
            }

            if (!selectedTestingJsonRow.parentNode || selectedTestingJsonRow.parentNode !== table.tBodies[0]) {
                selectedTestingJsonRow = null;
                return null;
            }

            return selectedTestingJsonRow;
        }

        function markTestingJsonRow(row) {
            var table = getTestingJsonTable();
            if (!table || !row || !row.classList || !row.classList.contains('testing-json-row')) {
                return;
            }

            table.querySelectorAll('tr.testing-json-row-selected').forEach(function (oldRow) {
                oldRow.classList.remove('testing-json-row-selected');
            });

            selectedTestingJsonRow = row;
            selectedTestingJsonRow.classList.add('testing-json-row-selected');
        }

        function clearTestingJsonRowSelection(row) {
            if (row && selectedTestingJsonRow === row) {
                selectedTestingJsonRow = null;
            }
        }

        function getTestingUrlAction(urlValue) {
            var value = String(urlValue || '').trim();
            var query = value;
            var match;

            if (query.indexOf('?') >= 0) {
                query = query.substring(query.indexOf('?') + 1);
            }

            query = query.replace(/^\/+/, '');

            match = query.match(/(?:^|&)action=([^&#]*)/i);
            if (!match) {
                return '';
            }

            try {
                return decodeURIComponent(match[1].replace(/\+/g, ' ')).toLowerCase();
            } catch (e) {
                return String(match[1] || '').toLowerCase();
            }
        }

        function testingActionUsesSource(action) {
            action = String(action || '').toLowerCase();
            return testingSourceActions.indexOf(action) !== -1;
        }

        function getTestingRowIndexFromField(field) {
            var name = field ? String(field.getAttribute('name') || '') : '';
            var match = name.match(/_(\d+)$/);
            return match ? match[1] : '';
        }

        function ensureTestingSourceCell(row) {
            if (!row) {
                return null;
            }

            var sourceInput = row.querySelector('input.testing-json-source');
            if (sourceInput) {
                return sourceInput.closest('td');
            }

            var categoryInput = row.querySelector('input.testing-json-category');
            if (!categoryInput) {
                return null;
            }

            var cell = categoryInput.closest('td');
            if (!cell) {
                return null;
            }

            var index = getTestingRowIndexFromField(categoryInput);
            var categoryValue = categoryInput.value || '';

            cell.classList.add('testing-json-source-cell');
            cell.innerHTML = ''
                + '<input type="hidden" name="testing_category_' + escapeHtml(index) + '" value="' + escapeHtml(categoryValue) + '" data-role="none">'
                + '<input type="text" name="testing_source_' + escapeHtml(index) + '" value="" class="lb-input testing-json-source" data-role="none" placeholder="SOURCE">';

            return cell;
        }

        function setTestingSourceCellVisible(cell, visible) {
            if (!cell) {
                return;
            }

            var sourceInput = cell.querySelector('input.testing-json-source');
            var wrapper = sourceInput ? sourceInput.closest('.ui-input-text') : null;

            cell.classList.toggle('testing-json-source-hidden', !visible);

            if (sourceInput) {
                sourceInput.disabled = !visible;
                sourceInput.style.setProperty('display', visible ? '' : 'none', 'important');
            }

            if (wrapper) {
                wrapper.style.setProperty('display', visible ? '' : 'none', 'important');
            }
        }

        function updateTestingSourceVisibilityForRow(row) {
            if (!row) {
                return;
            }

            var urlInput = row.querySelector('input.testing-json-url');
            var sourceCell = ensureTestingSourceCell(row);

            if (!urlInput || !sourceCell) {
                return;
            }

            var action = getTestingUrlAction(urlInput.value || '');
            setTestingSourceCellVisible(sourceCell, testingActionUsesSource(action));
        }

        function updateTestingSourceVisibility() {
            var table = getTestingJsonTable();
            if (!table || !table.tBodies.length) {
                return;
            }

            table.tBodies[0].querySelectorAll('tr.testing-json-row').forEach(function (row) {
                updateTestingSourceVisibilityForRow(row);
            });
        }

        document.addEventListener('click', function (event) {
            var row = event.target && event.target.closest ? event.target.closest('tr.testing-json-row') : null;
            if (!row) {
                return;
            }

            if (event.target.closest && event.target.closest('.jsDelTestingJson')) {
                return;
            }

            markTestingJsonRow(row);
        });

        document.addEventListener('click', function (event) {
            var btn = event.target && event.target.closest ? event.target.closest('.jsDelTestingJson') : null;
            if (!btn) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var row = btn.closest('tr');
            if (!row) {
                return false;
            }

            var nameInput = row.querySelector('input[name^="testing_name_"]');
            var testName = nameInput && nameInput.value ? nameInput.value : '';

            var title = textOf('testing_json_delete_title', 'Test löschen');
            var text  = textOf('testing_json_delete_text', 'Diesen Test wirklich löschen?');

            function removeRow() {
                if (row && row.parentNode) {
                    clearTestingJsonRowSelection(row);
                    row.parentNode.removeChild(row);
                    renumberTestingJsonRows();
                    updateTestingSourceVisibility();
                }
            }

            if (typeof silverBox === 'function') {
                silverBox({
                    alertIcon: 'warning',
                    html: text + (testName !== '' ? '<br><br><b>' + escapeHtml(testName) + '</b>' : ''),
                    centerContent: true,
                    title: { text: title },
                    confirmButton: {
                        bgColor: '#6dac20',
                        border: '10px',
                        borderColor: '#6dac20',
                        textColor: '#fff',
                        text: textOf('testing_json_delete_button', 'Löschen'),
                        closeOnClick: true,
                        onClick: function () {
                            removeRow();
                        }
                    },
                    cancelButton: {
                        bgColor: '#6dac20',
                        border: '10px',
                        borderColor: '#6dac20',
                        textColor: '#fff',
                        text: textOf('testing_cancel_button', 'Abbrechen'),
                        closeOnClick: true
                    }
                });

                return false;
            }

            if (confirm(title + "\n\n" + text + (testName !== '' ? "\n\n" + testName : ''))) {
                removeRow();
            }

            return false;
        });

        window.AddTestingJsonRow = function () {
            var table = getTestingJsonTable();
            var countEl = document.getElementById('testing_json_count');
            if (!table || !table.tBodies.length || !countEl) {
                return false;
            }

            var tbody = table.tBodies[0];
            var selectedRow = getValidSelectedTestingJsonRow();
            var nextIndex = parseInt(countEl.value || '0', 10);
            if (isNaN(nextIndex) || nextIndex < 0) {
                nextIndex = 0;
            }
            nextIndex += 1;
            countEl.value = String(nextIndex);

            var row = document.createElement('tr');
            row.className = 'testing-json-row';

            if (selectedRow && selectedRow.nextSibling) {
                tbody.insertBefore(row, selectedRow.nextSibling);
            } else if (selectedRow) {
                tbody.appendChild(row);
            } else {
                tbody.appendChild(row);
            }

            function appendCell(className, html) {
                var cell = document.createElement('td');
                cell.className = className;
                cell.innerHTML = html;
                row.appendChild(cell);
                return cell;
            }

            appendCell('testing-json-delete-cell',
                '<a href="#" class="jsDelTestingJson" data-role="none" title="' + escapeHtml(textOf('testing_json_delete_title', 'Delete test')) + '">' +
                    '<img class="ico_delete" src="/plugins/<TMPL_VAR PLUGINDIR>/images/recycle-bin.png" border="0" width="24" height="24">' +
                '</a>'
            );

            appendCell('testing-json-number-cell', '<span class="testing-json-number"></span>');
            appendCell('testing-json-status-cell',
                '<select name="testing_status_' + nextIndex + '" class="lb-select testing-json-status" data-role="none">' +
                    '<option value="active" selected="selected">active</option>' +
                    '<option value="inactive">inactive</option>' +
                '</select>'
            );
            appendCell('testing-json-name-cell', '<input type="text" name="testing_name_' + nextIndex + '" value="" class="lb-input testing-json-name" data-role="none">');
            appendCell('testing-json-risk-cell',
                '<select name="testing_risk_' + nextIndex + '" class="lb-select testing-json-risk" data-role="none">' +
                    '<option value="low">low</option>' +
                    '<option value="middle" selected="selected">middle</option>' +
                    '<option value="high">high</option>' +
                    '<option value="critical">critical</option>' +
                    '<option value="safe">safe</option>' +
                    '<option value="info">info</option>' +
                '</select>'
            );
            appendCell('testing-json-url-cell', '<input type="text" name="testing_url_' + nextIndex + '" value="" class="lb-input testing-json-url" data-role="none">');
            appendCell('testing-json-timeout-cell', '<input type="number" name="testing_timeout_' + nextIndex + '" value="" min="1" max="300" class="lb-input testing-json-timeout" data-role="none">');
            appendCell('testing-json-category-cell testing-json-source-cell testing-json-source-hidden',
                '<input type="hidden" name="testing_category_' + nextIndex + '" value="" data-role="none">' +
                '<input type="text" name="testing_source_' + nextIndex + '" value="" class="lb-input testing-json-source" data-role="none" placeholder="SOURCE" style="display:none !important;" disabled>'
            );

            renumberTestingJsonRows();
            updateTestingSourceVisibilityForRow(row);
            markTestingJsonRow(row);

            var firstInput = row.querySelector('input[name^="testing_name_"]');
            if (firstInput) {
                firstInput.focus();
            }

            return false;
        };

        document.addEventListener('input', function (event) {
            if (!event.target || !event.target.matches('input.testing-json-url')) {
                return;
            }

            updateTestingSourceVisibilityForRow(event.target.closest('tr.testing-json-row'));
        });

        document.addEventListener('input', function (event) {
            if (!event.target || !event.target.matches('input.testing-json-source')) {
                return;
            }

            if (String(event.target.value || '').trim() !== '') {
                clearTestingSourceValidationState(event.target);
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input.testing-json-url')) {
                updateTestingSourceVisibilityForRow(event.target.closest('tr.testing-json-row'));
                return;
            }

            var checkbox = event.target;
            if (!checkbox || !checkbox.matches('.testing_player_checkbox')) {
                return;
            }

            var details = checkbox.closest('.testing_player_details');
            if (!details) {
                return;
            }

            if (details.getAttribute('data-single') === '1' && checkbox.checked) {
                details.querySelectorAll('.testing_player_checkbox').forEach(function (other) {
                    if (other !== checkbox) {
                        other.checked = false;
                    }
                });
                details.removeAttribute('open');
            }

            updateTestingDropdown(details);

            if (checkbox.name === 'testing_zone') {
                syncTestingMemberOptions();
            }
        });

        injectTestingJsonCss();
        document.querySelectorAll('.testing_player_details').forEach(updateTestingDropdown);
        syncTestingMemberOptions();
        updateTestingSourceVisibility();
    }());

</script>
