<script>
/* =========================================================================================
 * Sonos4Lox - Volume Profiles UI (VOLUME page)
 * -----------------------------------------------------------------------------------------
 * IMPORTANT:
 * - This file is included by the template engine (TMPL_INCLUDE).
 * - Therefore: Do NOT introduce new template tags in comments
 *   otherwise HTML::Template may try to resolve them and can throw a 500 error.
 *
 * OVERVIEW
 * --------
 * This module handles:
 * - loading existing volume profiles from backend
 * - creating new profiles dynamically
 * - cloning the last profile into a new profile
 * - reading live Sonos values into a profile
 * - custom flipswitch synchronization
 * - Master / Member mutual exclusion logic
 * - validation before form submit
 *
 * CAPABILITY RULES
 * ----------------
 * Existing profiles must NOT decide SR / SW availability from old saved values like:
 *   innerArray[0].Surround  == 'na'
 *   innerArray[0].Subwoofer == 'na'
 *
 * Those values are historical profile content only.
 *
 * CURRENT availability must always come from:
 *   index.cgi  action: 'soundbars'
 *
 * This is why:
 * - new profiles already worked
 * - existing profiles did not
 *
 * VISUAL RULES
 * ------------
 * - Disabled SubLevel field (#sbass_*) = standard light gray
 * - Enabled SubLevel field (#sbass_*)  = white
 * - Disabled flipswitch background is controlled by CSS via .lb-flipswitch.is-disabled
 * ========================================================================================= */


/* =========================================================================================
 * jQuery Mobile - pageinit hook (currently unused)
 * ========================================================================================= */
$(document).on('pageinit', function() {
	//getvolprofiles();
	//$('#main_form').trigger('create');
});


/* =========================================================================================
 * Visual constants for SubLevel field
 * ========================================================================================= */
var SBASS_DISABLED_BG = 'rgba(192,192,192, 0.2)';
var SBASS_ENABLED_BG  = '#ffffff';


/* =========================================================================================
 * Custom flipswitch helpers
 * ========================================================================================= */
function lbParseBool(value) {
	return value === true || value === 'true' || value === 1 || value === '1';
}

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

	var checked = lbParseBool($hidden.val());
	$cb.prop('checked', checked);
	$cb.val(checked ? 'true' : 'false');
	$hidden.val(checked ? 'true' : 'false');

	$wrap.toggleClass('is-on', checked);
	$wrap.toggleClass('is-disabled', $cb.is(':disabled'));
}

function clearSbassWhenSubwooferOff(inputId, checked) {
	var match = String(inputId || '').match(/^subwoofer_(\d+)_(\d+)$/);

	if (!match) {
		return;
	}

	var playerIdx  = match[1];
	var profileIdx = match[2];
	var $sbass     = $('#sbass_' + playerIdx + '_' + profileIdx);

	if (!$sbass.length) {
		return;
	}

	if (!checked) {
		$sbass.val('');
	}
}

function initCustomFlipswitches(context) {
	var $root = context ? $(context) : $(document);

	$root.find('.lb-flipswitch').each(function () {
		var $wrap = $(this);
		var inputId = $wrap.attr('data-input');

		if (!inputId) {
			return;
		}

		var $cb = $('#' + inputId);
		var $hidden = $('#' + inputId + '_hidden');

		if (!$cb.length || !$hidden.length) {
			return;
		}

		if ($hidden.val() === '' || typeof $hidden.val() === 'undefined') {
			var dataValue = $wrap.attr('data-value');
			$hidden.val(lbParseBool(dataValue) ? 'true' : 'false');
		}

		$cb.off('change.lbflipswitch').on('change.lbflipswitch', function () {
			var checked = $(this).is(':checked');
			$(this).val(checked ? 'true' : 'false');
			$hidden.val(checked ? 'true' : 'false');

			clearSbassWhenSubwooferOff(inputId, checked);

			syncCustomFlipswitch($wrap);
		});

		syncCustomFlipswitch($wrap);
	});
}

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

	syncCustomFlipswitch($wrap);
}

function setCustomFlipswitchDisabled(id, disabled) {
	var $wrap = $('.lb-flipswitch[data-input="' + id + '"]');
	var $cb = $('#' + id);

	if (!$wrap.length || !$cb.length) {
		return;
	}

	$cb.prop('disabled', !!disabled);
	syncCustomFlipswitch($wrap);
}


/* =========================================================================================
 * Generic checkbox / jQuery Mobile helpers
 * ========================================================================================= */
function refreshCheckboxRadioSafe(selector) {
	$(selector).each(function () {
		var $el = $(this);
		if ($el.data('mobile-checkboxradio')) {
			$el.checkboxradio('refresh');
		}
	});
}

function setCheckboxSafe(selector, checked, value) {
	var $el = $(selector);

	if (!$el.length) {
		return;
	}

	$el.prop('checked', !!checked);

	if (typeof value !== 'undefined') {
		$el.val(value);
	}

	if ($el.data('mobile-checkboxradio')) {
		$el.checkboxradio('refresh');
	}
}

function refreshSingleCheckboxSafe($el) {
	if ($el && $el.length && $el.data('mobile-checkboxradio')) {
		$el.checkboxradio('refresh');
	}
}

function setCheckboxFieldVisible($checkbox, visible) {
	var $cell = $checkbox.closest('td');

	if (!$cell.length) {
		return;
	}

	if (visible) {
		$cell.children().show();
	} else {
		$cell.children().hide();
	}

	refreshSingleCheckboxSafe($checkbox);
}


/* =========================================================================================
 * Master / Member row synchronization
 * ========================================================================================= */
function syncMasterMemberRow(playerIdx, profileIdx) {
	var suffix  = playerIdx + '_' + profileIdx;
	var $master = $('#master_' + suffix);
	var $member = $('#member_' + suffix);

	if (!$master.length || !$member.length) {
		return;
	}

	if ($master.is(':checked')) {
		$member.prop('checked', false).val('false');
		$member.prop('disabled', true);
		$master.prop('disabled', false);

		setCheckboxFieldVisible($master, true);
		setCheckboxFieldVisible($member, false);

	} else if ($member.is(':checked')) {
		$master.prop('checked', false).val('false');
		$master.prop('disabled', true);
		$member.prop('disabled', false);

		setCheckboxFieldVisible($master, false);
		setCheckboxFieldVisible($member, true);

	} else {
		$master.prop('disabled', false);
		$member.prop('disabled', false);

		setCheckboxFieldVisible($master, true);
		setCheckboxFieldVisible($member, true);
	}

	refreshSingleCheckboxSafe($master);
	refreshSingleCheckboxSafe($member);
}

function syncMasterAvailabilityForProfile(profileIdx) {
	var $masters = $('input[id^="master_"][id$="_' + profileIdx + '"]');

	if (!$masters.length) {
		return;
	}

	var $checkedMaster = $masters.filter(':checked').first();

	if ($checkedMaster.length) {
		$masters.not($checkedMaster).prop('disabled', true);
		$checkedMaster.prop('disabled', false);
	} else {
		$masters.prop('disabled', false);
	}

	$masters.each(function () {
		var suffix = this.id.replace('master_', '');
		var $master = $(this);
		var $member = $('#member_' + suffix);

		if ($member.length && $member.is(':checked')) {
			$master.prop('disabled', true);
		}

		refreshSingleCheckboxSafe($master);
	});
}

function syncAllMasterMemberRows() {
	var iteration  = parseInt($('#countplayers').val(), 10) + 1;
	var iterate_id = parseInt($('#new_id').val(), 10) + 1;

	if (isNaN(iteration) || isNaN(iterate_id)) {
		return;
	}

	for (var i = 1; i < iteration; ++i) {
		for (var e = 1; e < iterate_id; ++e) {
			syncMasterMemberRow(i, e);
		}
	}

	for (var p = 1; p < iterate_id; ++p) {
		syncMasterAvailabilityForProfile(p);
	}
}


/* =========================================================================================
 * Current capability cache
 * -----------------------------------------------------------------------------------------
 * Source of truth for SR / SW availability:
 *   index.cgi  action: 'soundbars'
 * ========================================================================================= */
var currentCapabilities = {};

function normalizeRoomName(room) {
	return $.trim(String(room || ''));
}

function fetchCurrentCapabilities(callback) {
	$.ajax({
		url: 'index.cgi',
		type: 'post',
		data: { action: 'soundbars' },
		dataType: 'json',
		async: false
	})
	.done(function(data) {
		currentCapabilities = {};

		$.each(data, function(room, value) {
			currentCapabilities[normalizeRoomName(room)] = {
				surround:  (value[10] !== 'NOSUR'),
				subwoofer: (value[8]  !== 'NOSUB')
			};
		});

		if (typeof callback === 'function') {
			callback(currentCapabilities);
		}
	})
	.fail(function(jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
		currentCapabilities = {};

		if (typeof callback === 'function') {
			callback(currentCapabilities);
		}
	});
}

function hasSurroundCapability(room) {
	room = normalizeRoomName(room);
	return !!(currentCapabilities[room] && currentCapabilities[room].surround);
}

function hasSubwooferCapability(room) {
	room = normalizeRoomName(room);
	return !!(currentCapabilities[room] && currentCapabilities[room].subwoofer);
}


/* =========================================================================================
 * Capability application helpers
 * ========================================================================================= */
function setSbassFieldState(playerIdx, profileIdx, enabled, subLevelValue) {
	var $sbass  = $('#sbass_' + playerIdx + '_' + profileIdx);
	var $uiWrap = $sbass.closest('.ui-input-text');

	if (!$sbass.length) {
		return;
	}

	if (enabled) {
		$sbass
			.removeAttr('disabled')
			.prop('disabled', false)
			.prop('readonly', false)
			.css('background', '#ffffff')
			.css('color', '');

		if (typeof subLevelValue !== 'undefined') {
			$sbass.val(subLevelValue);
		}

		if ($sbass.data('mobile-textinput')) {
			try {
				$sbass.textinput('enable');
			} catch (e) {}
		}

		$uiWrap
			.removeClass('ui-state-disabled ui-disabled')
			.attr('aria-disabled', 'false')
			.css('background', '#ffffff');

	} else {
		$sbass
			.val('')
			.attr('disabled', 'disabled')
			.prop('disabled', true)
			.css('background', 'rgba(192,192,192, 0.2)')
			.css('color', '');

		if ($sbass.data('mobile-textinput')) {
			try {
				$sbass.textinput('disable');
			} catch (e) {}
		}

		$uiWrap
			.addClass('ui-state-disabled ui-disabled')
			.attr('aria-disabled', 'true')
			.css('background', 'rgba(192,192,192, 0.2)');
	}

	if ($sbass.data('mobile-textinput')) {
		try {
			$sbass.textinput('refresh');
		} catch (e) {}
	}
}

function applySurroundCapability(playerIdx, profileIdx, roomName, savedValue) {
	var surroundId = 'surround_' + playerIdx + '_' + profileIdx;

	if (hasSurroundCapability(roomName)) {
		setCustomFlipswitchDisabled(surroundId, false);
		setCustomFlipswitchValue(surroundId, savedValue === 'true');
	} else {
		setCustomFlipswitchValue(surroundId, false);
		setCustomFlipswitchDisabled(surroundId, true);
	}
}

function applySubwooferCapability(playerIdx, profileIdx, roomName, savedValue, subLevelValue) {
	var subwooferId = 'subwoofer_' + playerIdx + '_' + profileIdx;

	if (hasSubwooferCapability(roomName)) {
		setCustomFlipswitchDisabled(subwooferId, false);
		setCustomFlipswitchValue(subwooferId, savedValue === 'true');
		setSbassFieldState(playerIdx, profileIdx, true, subLevelValue);
	} else {
		setCustomFlipswitchValue(subwooferId, false);
		setCustomFlipswitchDisabled(subwooferId, true);
		setSbassFieldState(playerIdx, profileIdx, false, '');
	}
}

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
		"white-space": "normal"
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

/* =========================================================================================
 * Load & apply existing profiles from backend
 * ========================================================================================= */
function getvolprofiles() {
	let countdata = 0;

	fetchCurrentCapabilities(function() {
		$.ajax({
			url: 'index.cgi',
			type: 'post',
			data: { action: 'profiles'},
			dataType: 'json',
		})

		.fail(function (jqXHR, textStatus, errorThrown) {
			console.log(errorThrown);
		})

		.done(function(data, textStatus, jqXHR) {
			$.each(data, function(i, outerArray) {
				countdata++;
				$('#profile' + countdata).val(data[parseInt(countdata, 10) - 1].Name);

				var unsorted = outerArray.Player;
				const sorted = Object.keys(unsorted).sort().reduce(
					function(obj, key) {
						obj[key] = unsorted[key];
						return obj;
					},
					{}
				);

				var iteration = 0;

				$.each(sorted, function(j, innerArray) {
					iteration++;

					var roomName = normalizeRoomName(j);

					setCustomFlipswitchValue('loudness_' + iteration + '_' + countdata, innerArray[0].Loudness === 'true');

					applySurroundCapability(iteration, countdata, roomName, innerArray[0].Surround);
					applySubwooferCapability(iteration, countdata, roomName, innerArray[0].Subwoofer, innerArray[0].Subwoofer_level);

					if (innerArray[0].Master == 'true') {
						setCheckboxSafe('#master_' + iteration + '_' + countdata, true, 'true');
					} else {
						setCheckboxSafe('#master_' + iteration + '_' + countdata, false, 'false');
					}

					if (innerArray[0].Member == 'true') {
						setCheckboxSafe('#member_' + iteration + '_' + countdata, true, 'true');
					} else {
						setCheckboxSafe('#member_' + iteration + '_' + countdata, false, 'false');
					}
				});
			});
		})

		.always(function(data) {
			initCustomFlipswitches(document);
			$('#main_table').trigger('create').trigger('refresh');
			syncAllMasterMemberRows();
			console.log("Action get Volume Profiles executed", data);
		});
	});
}


/* =========================================================================================
 * Create a new profile dynamically (builds a new table block)
 * ========================================================================================= */
function create_new_profile() {

	last_id = parseInt(document.getElementById('last_id').value, 10);
	new_id = last_id + 1;

	var iteration = 0;
	var trHTML = '';

	$.ajax({
		url: 'index.cgi',
		type: 'post',
		data: { action: 'soundbars'},
		dataType: 'json',
		async: false,
		success: function(data, textStatus, jqXHR) {
			trHTML += "<div class='" + new_id + "'>";
			trHTML += "<table class='tables' style='width:100%' id='tblvol_prof" + new_id + "' name='tblvol_prof" + new_id + "'>\n";
			trHTML += "<th align='left' style='height: 25px; width:150px'>&nbsp;Profile #" + new_id + "</th>\n";
			trHTML += "<th align='middle' colspan='8'><div style='width: 180px; align: left'>\n";
			trHTML += "<input class='textfield' type='text' style='align: middle; width: 100%' id='profile" + new_id + "' name='profile" + new_id + "' value='' placeholder='Volume Profile Name'/>\n";
			trHTML += "<td valign='left'>";
			trHTML += "<span style='position:relative; display:inline-block; margin-right:8px;'>"
				+ "<img onclick='NewSonosData()' "
				+ "value='" + new_id + "' "
				+ "id='btnload" + new_id + "' "
				+ "name='btnload" + new_id + "' "
				+ "style='cursor:pointer;' "
				+ "onmouseenter=\"showGreenTooltip('#btnload_tip_" + new_id + "')\" "
				+ "onmouseleave=\"hideTooltip('#btnload_tip_" + new_id + "')\" "
				+ "src='/plugins/<TMPL_VAR PLUGINDIR>/images/musik-note.png' "
				+ "border='0' width='28' height='28'>"
				+ "<div id='btnload_tip_" + new_id + "' "
				+ "style='display:none; position:absolute; left:50%; bottom:38px; transform:translateX(-50%); "
				+ "padding:8px 12px; border-radius:6px; white-space:nowrap; z-index:9999;'>"
				+ "Load current values from Sonos devices"
				+ "<div style='position:absolute; left:50%; transform:translateX(-50%); bottom:-8px; width:0; height:0; "
				+ "border-left:8px solid transparent; border-right:8px solid transparent; border-top:8px solid #6db33f;'></div>"
				+ "</div>"
				+ "</span>\n";

			trHTML += "<span style='position:relative; display:inline-block;'>"
				+ "<img onclick='cloneprofile()' "
				+ "value='" + new_id + "' "
				+ "id='btnclone" + new_id + "' "
				+ "name='btnclone" + new_id + "' "
				+ "class='ico_clone' "
				+ "style='cursor:pointer;' "
				+ "onmouseenter=\"showGreenTooltip('#btnclone_tip_" + new_id + "')\" "
				+ "onmouseleave=\"hideTooltip('#btnclone_tip_" + new_id + "')\" "
				+ "src='/plugins/<TMPL_VAR PLUGINDIR>/images/clone.svg' "
				+ "border='0' width='33' height='33'>"
				+ "<div id='btnclone_tip_" + new_id + "' "
				+ "style='display:none; position:absolute; left:50%; bottom:43px; transform:translateX(-50%); "
				+ "padding:8px 12px; border-radius:6px; white-space:nowrap; z-index:9999;'>"
				+ "Clone values from last Profile"
				+ "<div style='position:absolute; left:50%; transform:translateX(-50%); bottom:-8px; width:0; height:0; "
				+ "border-left:8px solid transparent; border-right:8px solid transparent; border-top:8px solid #6db33f;'></div>"
				+ "</div>"
				+ "</span></td>\n";
			trHTML += "</th><tr><th style='background-color: #6dac20;' align='left'>&nbsp;Rooms</th><div class='form-group col-7'>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>V</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>T</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>B</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>L</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>SR</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>SW</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>SWL</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>MA</th>\n";
			trHTML += "<th class='form-control' style='background-color: #6dac20; align: center'>ME</th>\n";
			trHTML += "</div></tr>";

			const sorted = Object.keys(data).sort().reduce(
				function(obj, key) {
					obj[key] = data[key];
					return obj;
				},
				{}
			);

			$.each(sorted, function(j, value) {
				iteration++;

				var surroundDisabledAttr = (value[10] == 'NOSUR') ? " disabled='disabled'" : "";
				var surroundWrapClass = (value[10] == 'NOSUR') ? "lb-flipswitch is-disabled" : "lb-flipswitch";

				var subDisabledAttr = (value[8] == 'NOSUB') ? " disabled='disabled'" : "";
				var subWrapClass = (value[8] == 'NOSUB') ? "lb-flipswitch is-disabled" : "lb-flipswitch";

				trHTML += "<tr>";
				trHTML += "<td style='height: 25px; width: 160px;'><input type='text' id='zone_" + iteration + "_" + new_id + "' name='zone_" + iteration + "_" + new_id + "' readonly='true' value='" + j + "' style='width: 100%; background-color: #e6e6e6;'></td>";
				trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='vol_" + iteration + "_" + new_id + "' name='vol_" + iteration + "_" + new_id + "' size='100' data-validation-rule='special:number-min-max-value:0:100' data-validation-error-msg='<TMPL_VAR T2S.ERROR_VOLUME_PLAYER>' value=''></td>";
				trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='treble_" + iteration + "_" + new_id + "' name='treble_" + iteration + "_" + new_id + "' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER>' value=''></td>\n";
				trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='bass_" + iteration + "_" + new_id + "' name='bass_" + iteration + "_" + new_id + "' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER>' value=''></td>\n";

				trHTML += "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
				trHTML += "<div class='lb-flipswitch' data-input='loudness_" + iteration + "_" + new_id + "' data-value='false'>";
				trHTML += "<input type='hidden' name='loudness_" + iteration + "_" + new_id + "' id='loudness_" + iteration + "_" + new_id + "_hidden' value='false'>";
				trHTML += "<input type='checkbox' id='loudness_" + iteration + "_" + new_id + "' class='lb-flipswitch-checkbox no-jqm-flipswitch' data-role='none'>";
				trHTML += "<label class='lb-flipswitch-label' for='loudness_" + iteration + "_" + new_id + "'><span class='lb-flipswitch-inner'></span><span class='lb-flipswitch-switch'></span></label>";
				trHTML += "</div>";
				trHTML += "</td>\n";

				trHTML += "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
				trHTML += "<div class='" + surroundWrapClass + "' data-input='surround_" + iteration + "_" + new_id + "' data-value='false'>";
				trHTML += "<input type='hidden' name='surround_" + iteration + "_" + new_id + "' id='surround_" + iteration + "_" + new_id + "_hidden' value='false'>";
				trHTML += "<input type='checkbox' id='surround_" + iteration + "_" + new_id + "' class='lb-flipswitch-checkbox no-jqm-flipswitch' data-role='none'" + surroundDisabledAttr + ">";
				trHTML += "<label class='lb-flipswitch-label' for='surround_" + iteration + "_" + new_id + "'><span class='lb-flipswitch-inner'></span><span class='lb-flipswitch-switch'></span></label>";
				trHTML += "</div>";
				trHTML += "</td>\n";

				trHTML += "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
				trHTML += "<div class='" + subWrapClass + "' data-input='subwoofer_" + iteration + "_" + new_id + "' data-value='false'>";
				trHTML += "<input type='hidden' name='subwoofer_" + iteration + "_" + new_id + "' id='subwoofer_" + iteration + "_" + new_id + "_hidden' value='false'>";
				trHTML += "<input type='checkbox' id='subwoofer_" + iteration + "_" + new_id + "' class='lb-flipswitch-checkbox no-jqm-flipswitch' data-role='none'" + subDisabledAttr + ">";
				trHTML += "<label class='lb-flipswitch-label' for='subwoofer_" + iteration + "_" + new_id + "'><span class='lb-flipswitch-inner'></span><span class='lb-flipswitch-switch'></span></label>";
				trHTML += "</div>";
				trHTML += "</td>\n";

				if (value[8] == 'NOSUB') {
					trHTML += "<td style='width: 55px; height: 15px;'><input disabled type='text' id='sbass_" + iteration + "_" + new_id + "' name='sbass_" + iteration + "_" + new_id + "' size='100' value='' style='background: " + SBASS_DISABLED_BG + ";'></td>\n";
				} else {
					trHTML += "<td style='width: 55px; height: 15px;'><input type='text' id='sbass_" + iteration + "_" + new_id + "' name='sbass_" + iteration + "_" + new_id + "' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER>' value='' style='background: " + SBASS_ENABLED_BG + ";'></td>\n";
				}

				trHTML += "<td style='width: 60px; height: 15px'><input type='checkbox' id='master_" + iteration + "_" + new_id + "' name='master_" + iteration + "_" + new_id + "' class='" + new_id + "'></td>\n";
				trHTML += "<td style='width: 60px; height: 15px'><input type='checkbox' id='member_" + iteration + "_" + new_id + "' name='member_" + iteration + "_" + new_id + "' class='member_" + new_id + "'></td>\n";
				trHTML += "</tr>";
			});

			trHTML += "</table></div>";

			$('#btnscan').hide();
			$('.ico_delete').hide();
			$('#new_id').val(new_id);
			$('#formtable').append(trHTML);

			initCustomFlipswitches($('#tblvol_prof' + new_id));
			$('#tblvol_prof' + new_id).trigger('create');
			$('#main_table').trigger('create');
			refreshCheckboxRadioSafe('input:checkbox');
			syncAllMasterMemberRows();

			$("#btnsubmit").focus();
			$("#profile" + new_id).focus();
		}
	})

	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(errorThrown);
	})
	.always(function(data) {
		console.log("Action New Volume Profile executed", data);
	});
}


/* =========================================================================================
 * Clone values from last saved profile into the newly created profile
 * ========================================================================================= */
function cloneprofile() {
	var last_id = parseInt(document.getElementById('last_id').value, 10);
	var new_id = last_id + 1;

	fetchCurrentCapabilities(function() {
		$.ajax({
			url: 'index.cgi',
			type: 'post',
			data: { action: 'profiles'},
			dataType: 'json',
			async: false,
		})

		.fail(function (jqXHR, textStatus, errorThrown) {
			console.log(errorThrown);
		})

		.done(function(data, textStatus, jqXHR) {
			$.each(data, function(i, outerArray) {
				var unsorted = outerArray.Player;
				const sorted = Object.keys(unsorted).sort().reduce(
					function(obj, key) {
						obj[key] = unsorted[key];
						return obj;
					},
					{}
				);

				var iteration = 0;

				$.each(sorted, function(j, innerArray) {
					iteration++;

					var roomName = normalizeRoomName(j);

					$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
					$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
					$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);

					setCustomFlipswitchValue('loudness_' + iteration + '_' + new_id, innerArray[0].Loudness === 'true');

					applySurroundCapability(iteration, new_id, roomName, innerArray[0].Surround);
					applySubwooferCapability(iteration, new_id, roomName, innerArray[0].Subwoofer, innerArray[0].Subwoofer_level);

					if (innerArray[0].Master == 'true') {
						setCheckboxSafe('#master_' + iteration + '_' + new_id, true, "true");
					} else {
						setCheckboxSafe('#master_' + iteration + '_' + new_id, false, "false");
					}

					if (innerArray[0].Member == 'true') {
						setCheckboxSafe('#member_' + iteration + '_' + new_id, true, "true");
					} else {
						setCheckboxSafe('#member_' + iteration + '_' + new_id, false, "false");
					}
				});
			});
		})

		.always(function(data) {
			initCustomFlipswitches(document);
			$('#main_table').trigger('create').trigger('refresh');
			syncAllMasterMemberRows();
			console.log("Action Clone Profile executed", data);
		});
	});
}


/* =========================================================================================
 * Load current values from Sonos devices into a profile
 * ========================================================================================= */
function NewSonosData() {
	var last_id = parseInt(document.getElementById('last_id').value, 10);
	var load = last_id + 1;
	obtainSonosData(load);
}

function obtainSonosData(load) {
	var new_id = load;

	fetchCurrentCapabilities(function() {
		$.ajax({
			url: '/plugins/<TMPL_VAR PLUGINDIR>/bin/vol_prof_ini.php',
			type: 'post',
			dataType: "json",
			data: { 'new_id': 'true'}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			console.log(errorThrown);
		})
		.done(function(data, textStatus, jqXHR) {
			$.each(data, function(i, outerArray) {
				var unsorted = outerArray.Player;
				const sorted = Object.keys(unsorted).sort().reduce(
					function(obj, key) {
						obj[key] = unsorted[key];
						return obj;
					},
					{}
				);

				var iteration = 0;

				$.each(sorted, function(j, innerArray) {
					iteration++;

					var roomName = normalizeRoomName(j);

					$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
					$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
					$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);

					setCustomFlipswitchValue('loudness_' + iteration + '_' + new_id, innerArray[0].Loudness === 'true');

					applySurroundCapability(iteration, new_id, roomName, innerArray[0].Surround);
					applySubwooferCapability(iteration, new_id, roomName, innerArray[0].Subwoofer, innerArray[0].Subwoofer_level);

					setCheckboxSafe('#master_' + iteration + '_' + new_id, false, "false");
					setCheckboxSafe('#member_' + iteration + '_' + new_id, false, "false");
				});
			});
		})
		.always(function(data) {
			initCustomFlipswitches(document);
			$('#main_table').trigger('create').trigger('refresh');
			syncAllMasterMemberRows();
			console.log("Action load Sonos Data executed", data);
		});
	});
}


/* =========================================================================================
 * SilverBox dialogs & notifications
 * ========================================================================================= */
function timeout(text, ButtonText, Icon='', Title, timeout) {
	silverBox({
		timer: timeout,
		customIcon: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/info.svg",
		text: text,
		centerContent: true,
		title: {
			text: Title
		},
	});
}

$(".ico-delete").on("click", function() {
	$('#delprofil').val(0);
	var del = ($(this).attr('value'));
	deletedialog('<TMPL_VAR VOLUME_PROFILES.DIALOG_DELETE>', '<TMPL_VAR VOLUME_PROFILES.DIALOG_BUTTON_DELETE>', 'question', 'Volume Profile', del);
});

$(".ico-load").on("click", function() {
	var load = ($(this).attr('value'));
	obtainSonosData(load);
});

function deletedialog(text, ButtonText, Icon='', Title, del) {
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
			iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/images/recycle-bin.png",
			onClick: () => { deleteProfile(del) },
		},
		cancelButton: {
			text: "<TMPL_VAR SAVE.CANCEL_BUTTON>",
			iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/cancel.svg",
			onClick: () => { return; },
		},
	});
}

function message() {
	console.log("Save data");
	timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3000');
}

function deleteProfile(del) {
	$('#delprofil').val(del);
	$('#main_form').trigger('submit').trigger('create');
}


/* =========================================================================================
 * Checkbox state sync
 * ========================================================================================= */
function checkboxes() {
	var iteration = document.getElementById('countplayers').value;
	var iterate_id = document.getElementById('new_id').value;
	iteration = parseInt(iteration, 10);
	iterate_id = parseInt(iterate_id, 10);
	iteration += 1;
	iterate_id += 1;

	for (var i = 1; i < iteration; ++i) {
		for (var e = 1; e < iterate_id; ++e) {
			var master = document.getElementById('master_' + i + '_' + e);
			var member = document.getElementById('member_' + i + '_' + e);

			if (!master || !member) {
				continue;
			}

			var masterIsTrue = (master.value === "true");
			var memberIsTrue = (member.value === "true");

			if (masterIsTrue) {
				master.checked = true;
				member.checked = false;
				member.value = "false";
			} else if (memberIsTrue) {
				member.checked = true;
				master.checked = false;
				master.value = "false";
			} else {
				master.checked = false;
				member.checked = false;
			}

			refreshSingleCheckboxSafe($(master));
			refreshSingleCheckboxSafe($(member));
		}
	}

	syncAllMasterMemberRows();
}


/* =========================================================================================
 * Profile name dialog
 * ========================================================================================= */
function ProfilNameDialog(text, ButtonText, Icon='', Title) {
	silverBox({
		alertIcon: 'warning',
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
			closeOnClick: true
		}
	});
}


/* =========================================================================================
 * Document ready: initial load + UI rules + form validation
 * ========================================================================================= */
$(document).ready(function(e) {
	initCustomFlipswitches(document);

	fetchCurrentCapabilities(function() {
		getvolprofiles();
		checkboxes();
	});

	$(document.body).on('change', 'input[name^="master"]', function () {
		var parts = this.id.split('_');
		var playerIdx = parts[1];
		var profileIdx = parts[2];

		if ($(this).is(':checked')) {
			$('#member_' + playerIdx + '_' + profileIdx)
				.prop('checked', false)
				.val('false');
		}

		syncMasterMemberRow(playerIdx, profileIdx);
		syncMasterAvailabilityForProfile(profileIdx);
	});

	$(document.body).on('change', 'input[name^="member"]', function () {
		var parts = this.id.split('_');
		var playerIdx = parts[1];
		var profileIdx = parts[2];

		if ($(this).is(':checked')) {
			$('#master_' + playerIdx + '_' + profileIdx)
				.prop('checked', false)
				.val('false');
		}

		syncMasterMemberRow(playerIdx, profileIdx);
		syncMasterAvailabilityForProfile(profileIdx);
	});

	$("form#main_form").submit(function (e) {
		console.log("Submit");

		function showFail(sel, msg) {
			setTimeout(function () { $(sel).focus(); }, 50);
			$(sel).css('background-color','#FFFFC0');
			timeout(msg, 'OK', 'info', 'Sound Profile', '2000');
			e.preventDefault();
			return false;
		}

		function isIntUnsigned(s) { return /^\d+$/.test(s); }
		function isIntSigned(s)   { return /^-?\d+$/.test(s); }

		var iterate = parseInt(document.getElementById('new_id').value, 10) + 1;
		for (var i = 1; i < iterate; ++i) {
			if ($('#profile' + i).val() === "") {
				$('#profile' + i).focus();
				ProfilNameDialog("<TMPL_VAR VOLUME_PROFILES.DIALOG_PROFILE_NAME>", "OK", Icon = '', "Name");
				e.preventDefault();
				return false;
			}
		}

		var iteration  = parseInt(document.getElementById('countplayers').value, 10) + 1;
		var iterate_id = parseInt(document.getElementById('new_id').value, 10) + 1;

		refreshCheckboxRadioSafe('input:checkbox');

		for (var pi = 1; pi < iteration; ++pi) {
			for (var pj = 1; pj < iterate_id; ++pj) {

				var masterEl = document.getElementById('master_' + pi + '_' + pj);
				var memberEl = document.getElementById('member_' + pi + '_' + pj);

				if (masterEl && memberEl) {
					if (masterEl.checked) {
						masterEl.value = "true";
						memberEl.checked = false;
						memberEl.value = "false";
					} else if (memberEl.checked) {
						memberEl.value = "true";
						masterEl.checked = false;
						masterEl.value = "false";
					} else {
						masterEl.value = "false";
						memberEl.value = "false";
					}
				} else {
					if (masterEl) {
						masterEl.value = masterEl.checked ? "true" : "false";
					}
					if (memberEl) {
						memberEl.value = memberEl.checked ? "true" : "false";
					}
				}

				var selV = '#vol_' + pi + '_' + pj;
				var selT = '#treble_' + pi + '_' + pj;
				var selB = '#bass_' + pi + '_' + pj;
				var selS = '#sbass_' + pi + '_' + pj;

				if ($(selV).length !== 1 || $(selT).length !== 1 || $(selB).length !== 1) {
					continue;
				}

				var vStr = (String($(selV).val() || '')).trim();
				var tStr = (String($(selT).val() || '')).trim();
				var bStr = (String($(selB).val() || '')).trim();

				var hasSwlField = ($(selS).length === 1);
				var swlEnabled  = hasSwlField && !$(selS).prop('disabled');
				var sStr        = swlEnabled ? (String($(selS).val() || '')).trim() : '';
				
				if (swlEnabled && sStr === '') {
					$(selS).val('0');
					sStr = '0';
				}

				if (vStr === '' || !isIntUnsigned(vStr)) {
					return showFail(selV, '<TMPL_VAR VOLUME_PROFILES.ERROR_VOLUME_PLAYER>');
				}
				var v = parseInt(vStr, 10);
				if (v < 0 || v > 100) {
					return showFail(selV, '<TMPL_VAR VOLUME_PROFILES.ERROR_VOLUME_PLAYER>');
				}

				if (tStr === '' || !isIntSigned(tStr)) {
					return showFail(selT, '<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_PLAYER>');
				}
				var t = parseInt(tStr, 10);
				if (t < -10 || t > 10) {
					return showFail(selT, '<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_PLAYER>');
				}

				if (bStr === '' || !isIntSigned(bStr)) {
					return showFail(selB, '<TMPL_VAR VOLUME_PROFILES.ERROR_BASS_PLAYER>');
				}
				var b = parseInt(bStr, 10);
				if (b < -10 || b > 10) {
					return showFail(selB, '<TMPL_VAR VOLUME_PROFILES.ERROR_BASS_PLAYER>');
				}

				/* SWL validation:
				   only validate when the field exists and is enabled */
				if (swlEnabled && sStr !== '') {
					if (!isIntSigned(sStr)) {
						return showFail(selS, '<TMPL_VAR VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER>');
					}

					var s = parseInt(sStr, 10);
					if (s < -15 || s > 15) {
						return showFail(selS, '<TMPL_VAR VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER>');
					}
				}
			}
		}

		message();
		$('#main_table').trigger('create').trigger('refresh');
		return true;
	});
});
</script>