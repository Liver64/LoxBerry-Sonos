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
$(document).on('pageinit', function() {
	selection();
	callayout();
	destlayout();
	radlayout();
	weatherlayout();
	donation();
	//getsbconfig();
	//getradio();
});

var tvmonerr = "true";


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
			$('input:checkbox').checkboxradio('refresh');
		} else {
			document.getElementById('mainchk' + i).checked = false;
			$('input:checkbox').checkboxradio('refresh');
		}
	}
	return;
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
 * callayout(), destlayout(), radlayout(), weatherlayout()
 * - Toggle visibility for their related detail blocks.
 */
function callayout() {
	if (document.main_form.cal_det.checked == true) {
		$(".caldet").show();
	} else {
		$(".caldet").hide();
	}
}

function destlayout() {
	if (document.main_form.dest_det.checked == true) {
		$(".destdet").show();
	} else {
		$(".destdet").hide();
	}
}

function radlayout() {
	if (document.main_form.radio_det.checked == true) {
		$(".radiodet").show();
	} else {
		$(".radiodet").hide();
	}
}

function weatherlayout() {
	if (document.main_form.weather_det.checked == true) {
		$(".weatherdet").show();
	} else {
		$(".weatherdet").hide();
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
 * 3) Sonos listener actions
 * ================================================================================================ */

/**
 * restartSonosListener()
 * - Calls index.cgi?action=restart_listener
 * - On success: reload page
 */
function restartSonosListener() {
	$.get('index.cgi', { action: 'restart_listener' })
		.done(function (data) {
			console.log('Restart command sent to Sonos Event Listener.');
			location.reload();
		})
		.fail(function () {
			console.log('Failed to restart Sonos Event Listener.');
		});
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
	var cat = document.getElementById('sendlox').value;
	if (cat == "true") {
		console.log("Communication to Loxone turned on");
		$('.field_ms').show();
		//$('.label_template').show();
		//$('.template').show();
		$('.empty_template').show();
	} else {
		console.log("Communication to Loxone turned off");
		$('.field_ms').hide();
		//$('.label_template').hide();
		//$('.template').hide();
		$('.empty_template').hide();
	}
	$("#sendlox").flipswitch("refresh");
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
	console.log("populateElevenlabsLang");

	if (!document.main_form.tts_elevenlabs ||
		document.main_form.tts_elevenlabs.checked !== true) {
		return;
	}

	const url = 'https://api.elevenlabs.io/v1/models';
	$('#t2slang').empty();

	const key = document.getElementById('apikey').value.trim();
	if (!key) {
		console.warn("ElevenLabs language error: no API key set.");
		return;
	}

	const options = { method: 'GET', headers: { "xi-api-key": key } };

	fetch(url, options)
		.then(response => response.json())
		.then(function (data) {

			if (data[0] === undefined) {
				console.warn("ElevenLabs language error:", data.detail ? data.detail.message : "Unknown error");
				return false;
			}

			let lang = data[0]['languages'];

			$('#t2slang').append(
				'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_LANGUAGE_DROPDOWN></option>'
			);

			lang.forEach((lang, index) => {
				if (lang.language_id == "<TMPL_VAR TTS.messageLang>") {
					$('#t2slang').append(
						"<option selected='selected' value=\"" + lang.language_id + "\">&nbsp;" +
						lang.name + "</option>"
					);
				} else {
					$('#t2slang').append(
						"<option value=\"" + lang.language_id + "\">&nbsp;" +
						lang.name + "</option>"
					);
				}
			});

			$('#t2slang').selectmenu('refresh', true);

			// Remove old handlers, add dedicated ElevenLabs handler
			$('#t2slang').off('change.elevenlabs').on('change.elevenlabs', function () {
				// Only refresh voice list
				populateElevenlabsVoices();
			});

			// Initial voice list load
			populateElevenlabsVoices();
		})
		.catch(function (err) {
			console.warn("ElevenLabs language error:", err);
		});
}

function populateElevenlabsVoices() {
	console.log("populateElevenlabsVoices");

	$('#voice').empty();

	const key = document.getElementById('apikey').value.trim();
	if (!key) {
		console.warn("ElevenLabs voice error: no API key set.");
		return;
	}

	const options = { method: 'GET', headers: { "xi-api-key": key } };
	const url = 'https://api.elevenlabs.io/v2/voices';

	fetch(url, options)
		.then(response => response.json())
		.then(function (data) {
			let voice = data['voices'];

			if (voice === undefined) {
				console.warn("ElevenLabs voice error:", data.detail ? data.detail.message : "Unknown error");
				return false;
			}

			$('#voice').append(
				'<option selected="true" value="" disabled><TMPL_VAR T2S.SELECT_VOICE_DROPDOWN></option>'
			);

			voice.forEach((voice, index) => {
				if (voice.voice_id == "<TMPL_VAR TTS.voice>") {
					$('#voice').append(
						"<option selected='selected' id=\"" + voice.preview_url +
						"\" value=\"" + voice.voice_id + "\">&nbsp;" +
						voice.name + " - " + voice.labels.age + ", " +
						voice.labels.description + "</option>"
					);
				} else {
					$('#voice').append(
						"<option id=\"" + voice.preview_url +
						"\" value=\"" + voice.voice_id + "\">&nbsp;" +
						voice.name + " - " + voice.labels.age + ", " +
						voice.labels.description + "</option>"
					);
				}
			});

			$('#voice').selectmenu('refresh', true);
		})
		.catch(function (err) {
			console.warn("ElevenLabs voice error:", err);
		});

	return;
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
 * AddRadio()
 * - Adds a new radio station row to table #tblBasic2
 * - Re-applies jQuery Mobile styles with trigger('create')
 */
function AddRadio() {

	// Total number of radios
	var iteration = document.getElementById('countradios').value;
	iteration = parseInt(iteration)

	var tbl = document.getElementById('tblBasic2');

	// Del 1st row if there were no radios
	if (iteration < 1) {
		var row = tbl.deleteRow(-1);
	}

	var lastRow = tbl.rows.length;
	iteration += 1;
	document.getElementById("countradios").value = iteration;

	// Add cells
	var row = tbl.insertRow(lastRow);
	var cellRight = row.insertCell(0);
	cellRight.innerHTML = '<tr><td style="height: 25px; width: 43px; text-align: center;"><INPUT type="checkbox" style="width: 20px" name="chkradios' + iteration + '" id="chkradios' + iteration + '" align="center"/></td>'
	var cellRight = row.insertCell(1);
	cellRight.innerHTML = '<td style="height: 28px"><input type="text" id="radioname' + iteration + '" name="radioname' + iteration + '" size="20" value="" /> </td>'
	var cellRight = row.insertCell(2);
	cellRight.innerHTML = '<td style="width: 600px; height: 28px"><input type="text" id="radiourl' + iteration + '" name="radiourl' + iteration + '" size="100" value="" style="width: 100%" /> </td>'
	var cellRight = row.insertCell(3);
	cellRight.innerHTML = '<td style="width: 600px; height: 28px"><input type="text" id="coverurl' + iteration + '" name="coverurl' + iteration + '" size="100" value="" style="width: 100%" /> </td></tr>'

	// Recreate JQUERY styles
	$("#main_form").trigger('create');
}

$.ajax({
	url: 'index.cgi',
	type: 'post',
	data: { action: 'getradio'},
	dataType: 'json',
		async: false,
		success: function( response, textStatus, jqXHR )  {
			//$('#func_list').append('<option value="" disabled>** only for FOLLOW function***</option>');
			$.each(response, function(index, value) {
				result = value.split(',');
				$('#func_list').append('<option value="' + result[1] + '">Plugin Radio: ' + result[0] + '</option>');
			});
		}
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
	})
	.always(function(response) {
		$('#func_list').val('<TMPL_VAR VARIOUS.selfunction>');
		console.log( "Action get Radio Favorites executed" );
	})

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
function getsbconfig()   {

	$.ajax({
		url: 'index.cgi',
		type: 'post',
		data: { action: 'soundbars'},
		dataType: 'json',
		async: false,
		success: function(data, textStatus, jqXHR )   {
			//console.log(data);
			$.each(data, function(index, valu) {
				if (valu[13] == 'SB')   {
					console.log(valu);
					if (valu[8] == 'SUB' && valu.length == 15 || valu.length == 17) {
						$("#tvmonnightsub_" + index).val(valu[14].tvmonnightsub).flipswitch("refresh");
						$("#tvmonnightsubn_" + index).val(valu[14].tvsubnight).flipswitch("refresh");
						$("#subgain_" + index).val(valu[14].tvmonnightsublevel).selectmenu("refresh");
					} else if (valu[8] == 'SUB' && valu.length == 14) {
						$("#tvmonnightsub_" + index).val("false").flipswitch("refresh");
						$("#tvmonnightsubn_" + index).val("false").flipswitch("refresh");
						$("#subgain_" + index).val("0").selectmenu("refresh");
					} else {
						$("#tvmonnightsubn_" + index).flipswitch("disable").flipswitch("refresh");
						$("#tvmonnightsub_" + index).flipswitch("disable").flipswitch("refresh");
						$("#subgain_" + index).val("0").selectmenu("disable").selectmenu("refresh");
					}
					if (valu[10] == 'SUR' && valu.length == 15 || valu.length == 17) {
						$("#tvmonsurr_" + index).val(valu[14].tvmonsurr).flipswitch("refresh");
					} else if (valu[10] == 'SUR' && valu.length == 14) {
						$("#tvmonsurr_" + index).val("false").flipswitch("refresh");
					} else {
						$("#tvmonsurr_" + index).val('false').flipswitch("disable").flipswitch("refresh");
					}
					if (valu.length == 15 || valu.length == 17)   {
						$("#sbzone_" + index).val(index).text("refresh");
						$("#usesb_" + index).val(valu[14].usesb).flipswitch("refresh");
						$("#tvmonspeech_" + index).val(valu[14].tvmonspeech).flipswitch("refresh");
						$("#tvmonnight_" + index).val(valu[14].tvmonnight).flipswitch("refresh");
						$("#tvmonnightsub_" + index).val(valu[14].tvmonnightsub).flipswitch("refresh");
						$("#fromtime_" + index).val(valu[14].fromtime);
						$("#tvvol_" + index).val(valu[14].tvvol).text("refresh");
						$("#tvbass_" + index).val(valu[14].tvbass).text("refresh");
						$("#tvtreble_" + index).val(valu[14].tvtreble).text("refresh");
					}
					var usage = document.getElementById("usesb_" + index).value;
					if (usage == "true")   {
						var tvmonvol = document.getElementById("tvvol_" + index).value;
						var tvmontreble = document.getElementById("tvtreble_" + index).value;
						var tvmonbass = document.getElementById("tvbass_" + index).value;
						if (tvmonvol.length == 0)    {
							tvmonerr = "false";
						}
						if (tvmontreble.length == 0)    {
							tvmonerr = "false";
						}
						if (tvmonbass.length == 0)    {
							tvmonerr = "false";
						}
						// Field validation, but without abort
						validate_enable("#tvvol_" + index);
						validate_enable("#tvtreble_" + index);
						validate_enable("#tvbass_" + index);
					}

				}
			});
		}
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
	})
	.always(function(data) {
		console.log( "Action get Soundbars Config executed" );
	})
};

/**
 * validateSB()
 * - Checks if any SB elements exist (#sbX) and shows/hides TV monitor settings accordingly
 */
function validateSB()   {
	var iteration = document.getElementById('countplayers').value;
	var sbyes = "0";
	iteration = parseInt(iteration)
	iteration += 1;
	for (i = 1; i < iteration; ++i) {
		if($('#sb' + i + '').length)   {
			var sbyes = "1";
		}
	}
	if (sbyes == "1")   {
		$('.tvmon_header').show();
		$('.tvmon_switch').show();
	} else {
		$('.tvmon_header').hide();
		$('.tvmon_switch').hide();
	}
	$("#tvmon").flipswitch("refresh");
}

/**
 * validateTVMon()
 * - Shows/hides TV monitor blocks based on #tvmon flipswitch value
 */
function validateTVMon()   {
	var tvmonitor = document.getElementById('tvmon').value;
	if (tvmonitor == "true")   {
		$('.tvmon_header').show();
		$('.tvmon_body').show();
		console.log("TV Monitor On");
	} else {
		$('.tvmon_header').hide();
		$('.tvmon_body').hide();
		console.log("TV Monitor Off");
	}
	$("#tvmon").flipswitch("refresh");
}

/**
 * TV Monitor onChange handler (kept as-is)
 */
$("#tvmon").on("change", function(e){
	var tvmonitor = document.getElementById('tvmon').value;
	if (tvmonitor == "true")   {
		timeout('<TMPL_VAR TEMPLATE.TV_MONITOR_ON>', 'OK', 'info', 'TV Monitor', '4000');
	} else {
		timeout('<TMPL_VAR TEMPLATE.TV_MONITOR_OFF>', 'OK', 'info', 'TV Monitor', '4000');
	}
});


/* ================================================================================================
 * 12) Backup/Restore buttons
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


/* ================================================================================================
 * 13) Dialog helpers
 * ================================================================================================ */

/**
 * dialog()
 * - SilverBox popup with confirm button
 */
function dialog(text, ButtonText, Icon='', Title) {
	// https://silverboxjs.ir/documentation/?v=latest
	silverBox({
		alertIcon: Icon,
		text: text,
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
function timeout(text, ButtonText, Icon='', Title, timeout) {
	// https://silverboxjs.ir/documentation/?v=latest
	silverBox({
		timer: timeout,
		//alertIcon: Icon,
		customIcon: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/info.svg",
		text: text,
		centerContent: true,
		title: {
			text: Title
		},
	});
}

/**
 * discover()
 * - Discover Sonos devices prompt
 */
function discover() {
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
			onClick: () => {url = './index.cgi?do=scanning';
							document.location.href = url;
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
	console.log("MESSAGE");
	timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3500');
}

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


/* ================================================================================================
 * 16) Document ready: bind handlers + run initial state updates
 * ================================================================================================ */

$(document).ready(function(e) {
	console.log("documentreadyfunction");

	// Engine selection changes
	//$('#engine-selector input').change(prepareTTSConfigFields);

	// Language changes: populate voice list (note: Azure overwrites 'change' handler using off('change') as before)
	$('#t2slang').change(populateVoice);

	// Initial UI setup
	initial_lang_voice_load();
	checkboxes();
	validateSB();
	validateTVMon();
	getsbconfig();

	// Main submit validation
	$("form#main_form").submit(function(e) {
		console.log("submit");

		if (!validateVolumes(e)) {
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
				if (apidata.length !== 32 && apidata.length !== 51) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY32>', 'ElevenLabs', '#apikey');
				if (!requireLangVoice('ElevenLabs')) return false;
				break;

			case 'tts_piper':
				if (!selectlang) return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'Piper', '#t2slang');
				break;

			case 'tts_respvoice':
				if (!selectlang) return fail(e, '<TMPL_VAR T2S.VALIDATE_T2S_LANG>', 'Responsive Voice', '#t2slang');
				break;

			case 'tts_azure':
				if (![32,36,84].includes(apidata.length)) return fail(e, '<TMPL_VAR ERRORS.ERR_APIKEY32>', 'Azure', '#apikey');
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
		$('input:checkbox').checkboxradio('refresh');

		let isChecked = false;
		const checkBoxes = document.getElementsByClassName('chk-checked');
		for (let i = 0; i < checkBoxes.length; i++) {
			if (checkBoxes[i].checked) { isChecked = true; break; }
		}
		if (!isChecked) {
			$('html, body').animate({ scrollTop: $("#mainchk1").offset().top }, 600);
			return fail(e, '<TMPL_VAR ERRORS.ERR_CHECKBOX>', 'T2S Emergency', '#mainchk1');
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
</script>