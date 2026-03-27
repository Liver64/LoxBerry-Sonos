<script>
/* =========================================================================================
 * Sonos4Lox - Volume Profiles UI (VOLUME page)
 * -----------------------------------------------------------------------------------------
 * IMPORTANT:
 * - This file is included by the template engine (TMPL_INCLUDE).
 * - Therefore: Do NOT introduce new template tags in comments
 *   otherwise HTML::Template may try to resolve them and can throw a 500 error.
 * ========================================================================================= */


/* =========================================================================================
 * jQuery Mobile - pageinit hook (currently unused)
 * ========================================================================================= */
$(document).on('pageinit', function() {
	//getvolprofiles();
	//$('#main_form').trigger('create');
});


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


/* =========================================================================================
 * Load & apply existing profiles from backend
 * ========================================================================================= */
function getvolprofiles() {
	let countdata = 0;
	var data;

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
			$('#profile' + countdata).val(data[parseInt(countdata) - 1].Name);

			unsorted = outerArray.Player;
			const sorted = Object.keys(unsorted).sort().reduce(
				(obj, key) => {
					obj[key] = unsorted[key];
					return obj;
				},
				{}
			);

			var iteration = 0;
			iteration = parseInt(iteration);

			$.each(sorted, function(j, innerArray) {
				iteration++;

				setCustomFlipswitchValue('loudness_' + iteration + '_' + countdata, innerArray[0].Loudness === 'true');

				if (innerArray[0].Surround === 'na') {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + countdata, true);
					setCustomFlipswitchValue('surround_' + iteration + '_' + countdata, false);
				} else {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + countdata, false);
					setCustomFlipswitchValue('surround_' + iteration + '_' + countdata, innerArray[0].Surround === 'true');
				}

				if (innerArray[0].Subwoofer === 'na') {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + countdata, true);
					$('#sbass_' + iteration + '_' + countdata)
						.attr("disabled", "disabled")
						.css("background", "rgba(192,192,192, 0.2)");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + countdata, false);
				} else {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + countdata, false);
					$('#sbass_' + iteration + '_' + countdata)
						.removeAttr("disabled")
						.css("background", "");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + countdata, innerArray[0].Subwoofer === 'true');
				}

				if (innerArray[0].Master == 'true') {
					setCheckboxSafe('#master_' + iteration + '_' + countdata, true, "true");
				} else {
					setCheckboxSafe('#master_' + iteration + '_' + countdata, false, "false");
				}

				if (innerArray[0].Member == 'true') {
					setCheckboxSafe('#member_' + iteration + '_' + countdata, true, "true");
				} else {
					setCheckboxSafe('#member_' + iteration + '_' + countdata, false, "false");
				}
			});
		});
	})
	.always(function(data) {
		initCustomFlipswitches(document);
		$('#main_table').trigger('create').trigger('refresh');
		console.log("Action get Volume Profiles executed", data);
	});
}


/* =========================================================================================
 * Create a new profile dynamically (builds a new table block)
 * ========================================================================================= */
function create_new_profile() {

	last_id = parseInt(document.getElementById('last_id').value);
	new_id = last_id + 1;

	var iteration = 0;
	iteration = parseInt(iteration);
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
			trHTML += "<img onclick='NewSonosData()' title='Load current values from Sonos devices' value='" + new_id + "' id='btnload" + new_id + "' name='btnload" + new_id + "' src='/plugins/<TMPL_VAR PLUGINDIR>/images/musik-note.png' border='0' width='28' height='28'>\n";
			trHTML += "<img title='Clone values from last Profile' onclick='cloneprofile()' value='new_id' id='btnclone" + new_id + "' name='btnclone" + new_id + "' class='ico_clone' src='/plugins/<TMPL_VAR PLUGINDIR>/images/clone.svg' border='0' width='33' height='33'></td>\n";
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
				(obj, key) => {
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
					trHTML += "<td style='width: 55px; height: 15px;'><input disabled type='text' id='sbass_" + iteration + "_" + new_id + "' name='sbass_" + iteration + "_" + new_id + "' size='100' value='' style='background: rgba(192,192,192, 0.2)'></td>\n";
				} else {
					trHTML += "<td style='width: 55px; height: 15px;'><input type='text' id='sbass_" + iteration + "_" + new_id + "' name='sbass_" + iteration + "_" + new_id + "' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER>' value=''></td>\n";
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
	var data;

	var last_id = parseInt(document.getElementById('last_id').value);
	var new_id = last_id + 1;

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
			unsorted = outerArray.Player;
			const sorted = Object.keys(unsorted).sort().reduce(
				(obj, key) => {
					obj[key] = unsorted[key];
					return obj;
				},
				{}
			);

			var iteration = 0;
			iteration = parseInt(iteration);

			$.each(sorted, function(j, innerArray) {
				iteration++;

				$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
				$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
				$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);

				setCustomFlipswitchValue('loudness_' + iteration + '_' + new_id, innerArray[0].Loudness === 'true');

				if (innerArray[0].Surround === 'na') {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + new_id, true);
					setCustomFlipswitchValue('surround_' + iteration + '_' + new_id, false);
				} else {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + new_id, false);
					setCustomFlipswitchValue('surround_' + iteration + '_' + new_id, innerArray[0].Surround === 'true');
				}

				if (innerArray[0].Subwoofer === 'na') {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + new_id, true);
					$('#sbass_' + iteration + '_' + new_id)
						.attr("disabled", "disabled")
						.css("background", "rgba(192,192,192, 0.2)");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + new_id, false);
				} else {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + new_id, false);
					$('#sbass_' + iteration + '_' + new_id)
						.removeAttr("disabled")
						.css("background", "");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + new_id, innerArray[0].Subwoofer === 'true');
				}

				$('#sbass_' + iteration + '_' + new_id).val(innerArray[0].Subwoofer_level);

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
		console.log("Action Clone Profile executed", data);
	});
}


/* =========================================================================================
 * Load current values from Sonos devices into a profile
 * ========================================================================================= */

// Helper for "new profile" load action
function NewSonosData() {
	var last_id = parseInt(document.getElementById('last_id').value);
	var load = last_id + 1;
	obtainSonosData(load);
}

// Fetch actual data from all players
function obtainSonosData(load) {
	var arrkey = parseInt(load) - 1;
	var new_id = load;

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
			unsorted = outerArray.Player;
			const sorted = Object.keys(unsorted).sort().reduce(
				(obj, key) => {
					obj[key] = unsorted[key];
					return obj;
				},
				{}
			);

			var iteration = 0;
			iteration = parseInt(iteration);

			$.each(sorted, function(j, innerArray) {
				iteration++;

				$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
				$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
				$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);

				setCustomFlipswitchValue('loudness_' + iteration + '_' + new_id, innerArray[0].Loudness === 'true');

				if (innerArray[0].Surround === 'na') {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + new_id, true);
					setCustomFlipswitchValue('surround_' + iteration + '_' + new_id, false);
				} else {
					setCustomFlipswitchDisabled('surround_' + iteration + '_' + new_id, false);
					setCustomFlipswitchValue('surround_' + iteration + '_' + new_id, innerArray[0].Surround === 'true');
				}

				if (innerArray[0].Subwoofer === 'na') {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + new_id, true);
					$('#sbass_' + iteration + '_' + new_id)
						.attr("disabled", "disabled")
						.css("background", "rgba(192,192,192, 0.2)");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + new_id, false);
				} else {
					setCustomFlipswitchDisabled('subwoofer_' + iteration + '_' + new_id, false);
					$('#sbass_' + iteration + '_' + new_id)
						.removeAttr("disabled")
						.css("background", "");
					setCustomFlipswitchValue('subwoofer_' + iteration + '_' + new_id, innerArray[0].Subwoofer === 'true');
				}

				setCheckboxSafe('#master_' + iteration + '_' + new_id, false, "false");
				setCheckboxSafe('#member_' + iteration + '_' + new_id, false, "false");
				$('#sbass_' + iteration + '_' + new_id).val(innerArray[0].Subwoofer_level);
			});
		});
	})
	.always(function(data) {
		initCustomFlipswitches(document);
		console.log("Action load Sonos Data executed", data);
	});
}


/* =========================================================================================
 * SilverBox dialogs & notifications
 * ========================================================================================= */

// Timed info popup
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

// Initiate deleting profile (icon handler)
$(".ico-delete").on("click", function() {
	$('#delprofil').val(0);
	var del = ($(this).attr('value'));
	deletedialog('<TMPL_VAR VOLUME_PROFILES.DIALOG_DELETE>', '<TMPL_VAR VOLUME_PROFILES.DIALOG_BUTTON_DELETE>', 'question', 'Volume Profile', del);
});

// Initiate loading actual data (icon handler)
$(".ico-load").on("click", function() {
	var load = ($(this).attr('value'));
	obtainSonosData(load);
});

// Confirmation dialog for profile deletion
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

// Message during saving
function message() {
	console.log("Save data");
	timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3000');
}

// Submit helper for deletion
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

			if (master.value == "true") {
				master.checked = true;
				member.checked = false;
				$('#master_' + i + '_' + e).attr('disabled', false);
			} else {
				master.checked = false;
			}

			var $master = $('#master_' + i + '_' + e);
			var $member = $('#member_' + i + '_' + e);

			if ($master.data('mobile-checkboxradio')) {
				$master.checkboxradio('refresh');
			}
			if ($member.data('mobile-checkboxradio')) {
				$member.checkboxradio('refresh');
			}
		}
	}
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
	getvolprofiles();
	checkboxes();

	// Master checkbox rule: only one master per profile group
	$(document.body).on('click','input[name^="master"]',function(event) {
		var id = ($(this).attr('class'));
		if($(this).is(':checked')) {
			$('input[class=' + id + ']').not(this).attr('disabled',true);
		} else {
			$('input[class=' + id + ']').attr('disabled',false);
		}
		refreshCheckboxRadioSafe('input:checkbox');
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

				if (masterEl) {
					if (masterEl.checked) {
						masterEl.value = "true";
						if (memberEl) {
							memberEl.checked = false;
							memberEl.value = "false";
						}
					} else {
						masterEl.value = "false";
					}
				}
				if (memberEl) {
					memberEl.value = memberEl.checked ? "true" : "false";
				}

				var selV = '#vol_' + pi + '_' + pj;
				var selT = '#treble_' + pi + '_' + pj;
				var selB = '#bass_' + pi + '_' + pj;

				if ($(selV).length !== 1 || $(selT).length !== 1 || $(selB).length !== 1) {
					continue;
				}

				var vStr = (($(selV).val() ?? '') + '').trim();
				var tStr = (($(selT).val() ?? '') + '').trim();
				var bStr = (($(selB).val() ?? '') + '').trim();

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
			}
		}

		message();
		$('#main_table').trigger('create').trigger('refresh');
		return true;
	});
});
</script>