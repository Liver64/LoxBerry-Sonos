<script>
/* =========================================================================================
 * Sonos4Lox - Volume Profiles UI (VOLUME page)
 * -----------------------------------------------------------------------------------------
 * IMPORTANT:
 * - This file is included by the template engine (TMPL_INCLUDE).
 * - Therefore: Do NOT introduce new template tags in comments
 *   otherwise HTML::Template may try to resolve them and can throw a 500 error.
 * - Functional code below is intentionally kept identical to your working original.
 * ========================================================================================= */


/* =========================================================================================
 * jQuery Mobile - pageinit hook (currently unused)
 * ========================================================================================= */
$(document).on('pageinit', function() {
	//getvolprofiles();
	//$('#main_form').trigger('create');
});


/* =========================================================================================
 * Load & apply existing profiles from backend
 * ========================================================================================= */
function getvolprofiles()   {
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
				//console.log(data);
				$.each(data, function(i, outerArray) {
					countdata++;
					$('#profile' + countdata).val(data[parseInt(countdata) - 1].Name);
					unsorted = outerArray.Player
					const sorted = Object.keys(unsorted).sort().reduce(
						(obj, key) => {
								obj[key] = unsorted[key];
								return obj;
								},
						{}
					);
					var iteration = 0;
					iteration = parseInt(iteration)
					$.each(sorted, function(j, innerArray) {
						iteration++;
						//console.log(j);
						//console.log(innerArray);
						if (innerArray[0].Loudness == 'true') {
							$('#loudness_' + iteration + '_' + countdata).val("true").flipswitch("refresh");
						} else {
							$('#loudness_' + iteration + '_' + countdata).val("false").flipswitch("refresh");
						}
						if (innerArray[0].Surround == 'na') {
							$('#surround_' + iteration + '_' + countdata).flipswitch("disable");
							$('#surround_' + iteration + '_' + countdata).val("false").flipswitch("refresh");
						} else if (innerArray[0].Surround == 'true') {
							$('#surround_' + iteration + '_' + countdata).val("true").flipswitch("refresh");
						} else {
							$('#surround_' + iteration + '_' + countdata).val("false").flipswitch("refresh");
						}
						if (innerArray[0].Subwoofer == 'na') {
							$('#subwoofer_' + iteration + '_' + countdata).flipswitch("disable");
							$('#sbass_' + iteration + '_' + countdata).attr("disabled", "disabled").css("background", "rgba(192,192,192, 0.2)").textinput("refresh");
							$('#subwoofer_' + iteration + '_' + countdata).val("false").flipswitch("refresh");
						} else if (innerArray[0].Subwoofer == 'true') {
							$('#subwoofer_' + iteration + '_' + countdata).val("true").flipswitch("refresh");
						} else {
							$('#subwoofer_' + iteration + '_' + countdata).val("false").flipswitch("refresh");
						}
						if (innerArray[0].Master == 'true') {
							$('#master_' + iteration + '_' + countdata).prop('checked',true).val("true").checkboxradio("refresh");
						} else {
							$('#master_' + iteration + '_' + countdata).prop('checked',false).val("false").checkboxradio("refresh");
						}
						if (innerArray[0].Member == 'true') {
							$('#member_' + iteration + '_' + countdata).prop('checked',true).checkboxradio("refresh");
						} else {
							$('#member_' + iteration + '_' + countdata).prop('checked',false).checkboxradio("refresh");
						}
					})
				})
	})
	.always(function(data) {
		$('#main_table').trigger('create').trigger('refresh');
		console.log( "Action get Volume Profiles executed", data );
	})

};


/* =========================================================================================
 * Create a new profile dynamically (builds a new table block)
 * ========================================================================================= */
function create_new_profile()   {

	let count = 0;
	last_id = parseInt(document.getElementById('last_id').value);
	new_id = last_id + 1;

	var iteration = 0;
	iteration = parseInt(iteration)
	var trHTML = '';

	$.ajax({
			url: 'index.cgi',
			type: 'post',
			data: { action: 'soundbars'},
			dataType: 'json',
			async: false,
				success: function(data, textStatus, jqXHR)   {
					//console.log(new_id);
					trHTML += "<div class=" + new_id +">";
					trHTML += "<table class='tables' style='width:100%' id='tblvol_prof" + new_id +"' name='tblvol_prof" + new_id +"'>\n";
					trHTML += "<th align='left' style='height: 25px; width:150px'>&nbsp;Profile #" + new_id +"</th>\n";
					trHTML += "<th align='middle' colspan='8'><div style='width: 180px; align: left'>\n";
					trHTML += "<input class='textfield' type='text' style='align: middle; width: 100%' id='profile" + new_id +"' name='profile" + new_id +"' value='' placeholder='Volume Profile Name'/>\n";
					trHTML += "<td valign='left'>";
					trHTML += "<img onclick='NewSonosData()' title='Load current values from Sonos devices' value='" + new_id +"' id='btnload" + new_id +"' name='btnload" + new_id +"' src='/plugins/<TMPL_VAR PLUGINDIR>/images/musik-note.png' border='0' width='28' height='28'>\n";
					trHTML += "<img title='Clone values from last Profile' onclick='cloneprofile()' value='new_id' id='btnclone" + new_id +"' name='btnclone" + new_id +"' class='ico_clone' src='/plugins/<TMPL_VAR PLUGINDIR>/images/clone.svg' border='0' width='33' height='33'></td>\n";
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
					// sort table
					const sorted = Object.keys(data).sort().reduce(
						(obj, key) => {
							obj[key] = data[key];;
							return obj;
							},
						{}
					);
					$.each(sorted, function(j, value) {
						iteration++;
						//console.log(j);
						//console.log(value);
						trHTML += "<tr>";
						trHTML += "<td style='height: 25px; width: 160px;'><input type='text' id='zone_"+ iteration + "_" + new_id +"' name='zone_"+ iteration + "_" + new_id +"' readonly='true' value='" + j +"' style='width: 100%; background-color: #e6e6e6;'></td>";
						trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='vol_"+ iteration + "_" + new_id +"' name='vol_"+ iteration + "_" + new_id +"' size='100' data-validation-rule='special:number-min-max-value:0:100' data-validation-error-msg='<TMPL_VAR T2S.ERROR_VOLUME_PLAYER>' value=''></td>";
						trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='treble_"+ iteration + "_" + new_id +"' name='treble_"+ iteration + "_" + new_id +"' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER>' value=''></td>\n";
						trHTML += "<td style='width: 45px; height: 15px;'><input type='text' id='bass_"+ iteration + "_" + new_id +"' name='bass_"+ iteration + "_" + new_id +"' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER>' value=''></td>\n";
						trHTML += "<td style='height: 10px; width: 5px; align: middle'>";
						trHTML += "<fieldset><select id='loudness_"+ iteration + "_" + new_id +"' name='loudness_"+ iteration + "_" + new_id +"' data-role='flipswitch' style='width: 5%'>\n";
						trHTML += "<option value='false'><TMPL_VAR T2S.LABEL_FLIPSWITCH_OFF></option><option value='true'><TMPL_VAR T2S.LABEL_FLIPSWITCH_ON></option>";
						trHTML += "</select></fieldset></td>\n";
						trHTML += "<td style='height: 10px; width: 25px; align: middle'>";
						if (value[10] == 'NOSUR') {
							trHTML += "<fieldset><select disabled id='surround_"+ iteration + "_" + new_id +"' name='surround_"+ iteration + "_" + new_id +"' data-role='flipswitch' style='width: 5%'>\n";
						} else {
							trHTML += "<fieldset><select id='surround_"+ iteration + "_" + new_id +"' name='surround_"+ iteration + "_" + new_id +"' data-role='flipswitch' style='width: 5%'>\n";
						}
						trHTML += "<option value='false'><TMPL_VAR T2S.LABEL_FLIPSWITCH_OFF></option><option value='true'><TMPL_VAR T2S.LABEL_FLIPSWITCH_OFF></option>";
						trHTML += "</select></fieldset></td>\n";
						trHTML += "<td style='height: 10px; width: 25px; align: middle'>";
						if (value[8] == 'NOSUB') {
							trHTML += "<fieldset><select disabled id='subwoofer_"+ iteration + "_" + new_id +"' name='subwoofer_"+ iteration + "_" + new_id +"' data-role='flipswitch' style='width: 5%'>\n";
						} else {
							trHTML += "<fieldset><select id='subwoofer_"+ iteration + "_" + new_id +"' name='subwoofer_"+ iteration + "_" + new_id +"' data-role='flipswitch' style='width: 5%'>\n";
						}
						trHTML += "<option value='false'><TMPL_VAR T2S.LABEL_FLIPSWITCH_OFF></option><option value='true'><TMPL_VAR T2S.LABEL_FLIPSWITCH_OFF></option>";
						trHTML += "</select></fieldset></td>\n";
						if (value[8] == 'NOSUB') {
							trHTML += "<td style='width: 55px; height: 15px;'><input disabled type='text' id='sbass_"+ iteration + "_" + new_id +"' name='sbass_"+ iteration + "_" + new_id +"' size='100' value=''></td>\n";
						} else {
							trHTML += "<td style='width: 55px; height: 15px;'><input type='text' id='sbass_"+ iteration + "_" + new_id +"' name='sbass_"+ iteration + "_" + new_id +"' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='<TMPL_VAR VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER>' value=''></td>\n";
						}
						trHTML += "<td style='width: 60px; height: 15px'><input type='checkbox' id='master_"+ iteration + "_" + new_id +"' name='master_"+ iteration + "_" + new_id +"' class="+ new_id +"></td>\n";
						trHTML += "<td style='width: 60px; height: 15px'><input type='checkbox' id='member_"+ iteration + "_" + new_id +"' name='member_"+ iteration + "_" + new_id +"' class='member_"+ new_id +"'></td>\n";
						trHTML += "</tr>";
					})
					trHTML += "</table></div>";
					//$('input:checkbox').checkboxradio('refresh');
					$('#btnscan').hide();
					$('.ico_delete').hide();
					$('#new_id').val(new_id);
					$('#formtable').append(trHTML);
					$('#tblvol_prof' + new_id +'').trigger('create');
					$('#main_table').trigger('create');
					$("#btnsubmit").focus();
					$("#profile" + new_id +"").focus();
				}
			})

		.fail(function (jqXHR, textStatus, errorThrown) {
			console.log(errorThrown);
		})
		.always(function(data) {
			console.log( "Action New Volume Profile executed", data );
		})
	};


	/* =========================================================================================
	 * Clone values from last saved profile into the newly created profile
	 * ========================================================================================= */
	function cloneprofile()   {
		var data;

		var last_id = parseInt(document.getElementById('last_id').value);
		var new_id = last_id + 1;
		//console.log(last_id);
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
						unsorted = outerArray.Player
						const sorted = Object.keys(unsorted).sort().reduce(
							(obj, key) => {
									obj[key] = unsorted[key];
									return obj;
									},
							{}
						);
						var iteration = 0;
						iteration = parseInt(iteration)
						$.each(sorted, function(j, innerArray) {
							iteration++;
							//console.log(j);
							//console.log(innerArray);
							$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
							$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
							$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);
							if (innerArray[0].Loudness == 'true') {
								$('#loudness_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#loudness_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							if (innerArray[0].Surround == 'na') {
								$('#surround_' + iteration + '_' + new_id).flipswitch("disable");
								$('#surround_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							} else if (innerArray[0].Surround == 'true') {
								$('#surround_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#surround_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							if (innerArray[0].Subwoofer == 'na') {
								$('#subwoofer_' + iteration + '_' + new_id).flipswitch("disable");
								$('#sbass_' + iteration + '_' + new_id).attr("disabled", "disabled").css("background", "rgba(192,192,192, 0.2)").textinput("refresh");
								$('#subwoofer_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							} else if (innerArray[0].Subwoofer == 'true') {
								$('#subwoofer_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#subwoofer_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							$('#sbass_' + iteration + '_' + new_id).val(innerArray[0].Subwoofer_level);
							if (innerArray[0].Master == 'true') {
								$('#master_' + iteration + '_' + new_id).prop('checked',true).checkboxradio("refresh");
							} else {
								$('#master_' + iteration + '_' + new_id).prop('checked',false).checkboxradio("refresh");
							}
							if (innerArray[0].Member == 'true') {
								$('#member_' + iteration + '_' + new_id).prop('checked',true).checkboxradio("refresh");
							} else {
								$('#member_' + iteration + '_' + new_id).prop('checked',false).checkboxradio("refresh");
							}
						})
					})
		})
		.always(function(data) {
			console.log( "Action Clone Profile executed", data );
		})

	};


	/* =========================================================================================
	 * Load current values from Sonos devices into a profile
	 * ========================================================================================= */

	// Helper for "new profile" load action
	function NewSonosData()   {
		var last_id = parseInt(document.getElementById('last_id').value);
		var load = last_id + 1;
		obtainSonosData(load);
	}

	// Fetch actual data from all players
	function obtainSonosData(load)   {
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
					//console.log(data);
					$.each(data, function(i, outerArray) {
						//console.log(outerArray);
						//$('#profile' + new_id).val("");
						unsorted = outerArray.Player
						const sorted = Object.keys(unsorted).sort().reduce(
							(obj, key) => {
									obj[key] = unsorted[key];
									return obj;
									},
							{}
						);
						var iteration = 0;
						iteration = parseInt(iteration)
						$.each(sorted, function(j, innerArray) {
							iteration++;
							//console.log(sorted);
							//console.log(innerArray);
							$('#vol_' + iteration + '_' + new_id).val(innerArray[0].Volume);
							$('#treble_' + iteration + '_' + new_id).val(innerArray[0].Treble);
							$('#bass_' + iteration + '_' + new_id).val(innerArray[0].Bass);
							if (innerArray[0].Loudness == 'true') {
								$('#loudness_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#loudness_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							if (innerArray[0].Surround == 'na') {
								$('#surround_' + iteration + '_' + new_id).flipswitch("disable");
								$('#surround_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							} else if (innerArray[0].Surround == 'true') {
								$('#surround_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#surround_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							if (innerArray[0].Subwoofer == 'na') {
								$('#subwoofer_' + iteration + '_' + new_id).flipswitch("disable");
								$('#sbass_' + iteration + '_' + new_id).attr("disabled", "disabled").css("background", "rgba(192,192,192, 0.2)").textinput("refresh");
								$('#subwoofer_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							} else if (innerArray[0].Subwoofer == 'true') {
								$('#subwoofer_' + iteration + '_' + new_id).val("true").flipswitch("refresh");
							} else {
								$('#subwoofer_' + iteration + '_' + new_id).val("false").flipswitch("refresh");
							}
							$('#master_' + iteration + '_' + new_id).prop('checked',false).val("false").checkboxradio("refresh");
							$('#member_' + iteration + '_' + new_id).prop('checked',false).val("false").checkboxradio("refresh");
							$('#sbass_' + iteration + '_' + new_id).val(innerArray[0].Subwoofer_level);
						})
					})
		})
		.always(function(data) {
			console.log( "Action load Sonos Data executed", data );
		})

	};


	/* =========================================================================================
	 * SilverBox dialogs & notifications
	 * ========================================================================================= */

	// Timed info popup
	function timeout(text, ButtonText, Icon='', Title, timeout)    {
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
	function deletedialog(text, ButtonText, Icon='', Title, del)    {
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
							iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/images/recycle-bin.png",
							onClick: () => { deleteProfile(del)	},
							},
			cancelButton: {
							text: "<TMPL_VAR SAVE.CANCEL_BUTTON>",
							iconStart: "/plugins/<TMPL_VAR PLUGINDIR>/web/images/cancel.svg",
							onClick: () => {return;
											},
							},
			});
	}

	// Message during saving
	function message()   {
		console.log("Save data");
		timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3000');
	}

	// Submit helper for deletion
	function deleteProfile(del)   {
		$('#delprofil').val(del);
		$('#main_form').trigger('submit').trigger('create');
	}


	/* =========================================================================================
	 * Checkbox state sync
	 * ========================================================================================= */
	function checkboxes()   {
		var iteration = document.getElementById('countplayers').value;
		var iterate_id = document.getElementById('new_id').value;
		iteration = parseInt(iteration)
		iterate_id = parseInt(iterate_id)
		iteration += 1;
		iterate_id += 1;
		let array =[];
		for (i = 1; i < iteration; ++i) {
			for (e = 1; e < iterate_id; ++e) {
				if (document.getElementById('master_' + i + '_' + e).value == "true") {
					document.getElementById('master_' + i + '_' + e).checked = true;
					document.getElementById('member_' + i + '_' + e).checked = false;
					$('#master_' + i + '_' + e).attr('disabled',false);
					//array.push($('#member_' + i + '_' + e).val());
				} else {
					document.getElementById('master_' + i + '_' + e).checked = false;
					array.push($('#member_' + i + '_' + e).val());
				}
			}
		}
		$('input:checkbox').checkboxradio('refresh');
		//console.log(array);
		if(array.length){
			//alert('Value of selected checkboxes are: ${array}');
		} else {
			alert("Checkbox is not selected, Please select one!");
		}
	}


	/* =========================================================================================
	 * Profile name dialog
	 * ========================================================================================= */
	function ProfilNameDialog(text, ButtonText, Icon='', Title)    {
		// https://silverboxjs.ir/documentation/?v=latest
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
		getvolprofiles();
		checkboxes();
		//validateProfiles();

		// Master checkbox rule: only one master per profile group
		$(document.body).on('click','input[name^="master"]',function(event)  {
			var id = ($(this).attr('class'));
			if($(this).is(':checked'))  {
				$('input[class='+ id +']').not(this).attr('disabled',true);
			} else {
				$('input[class='+ id +']').attr('disabled',false);
			}
			$('input:checkbox').checkboxradio('refresh');
		});


		$("form#main_form").submit(function (e) {
		  console.log("Submit");

		  // helpers (defined once; used in loops)
		  function showFail(sel, msg) {
			setTimeout(function () { $(sel).focus(); }, 50);
			$(sel).css('background-color','#FFFFC0');
			timeout(msg, 'OK', 'info', 'Sound Profile', '2000');
			e.preventDefault();
			return false;
		  }

		  function isIntUnsigned(s) { return /^\d+$/.test(s); }   // 0..n
		  function isIntSigned(s)   { return /^-?\d+$/.test(s); } // -n..n

		  // validate profile names
		  var iterate = parseInt(document.getElementById('new_id').value, 10) + 1;
		  for (var i = 1; i < iterate; ++i) {
			if ($('#profile' + i).val() === "") {
			  $('#profile' + i).focus();
			  ProfilNameDialog("<TMPL_VAR VOLUME_PROFILES.DIALOG_PROFILE_NAME>", "OK", Icon = '', "Name");
			  e.preventDefault();
			  return false;
			}
		  }

		  // players/profiles loops
		  var iteration  = parseInt(document.getElementById('countplayers').value, 10) + 1;
		  var iterate_id = parseInt(document.getElementById('new_id').value, 10) + 1;

		  $('input:checkbox').checkboxradio('refresh');

		  for (var pi = 1; pi < iteration; ++pi) {
			for (var pj = 1; pj < iterate_id; ++pj) {

			  // master/member checkboxes (safe)
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

			  // selectors
			  var selV = '#vol_' + pi + '_' + pj;
			  var selT = '#treble_' + pi + '_' + pj;
			  var selB = '#bass_' + pi + '_' + pj;

			  // if field does not exist -> skip
			  if ($(selV).length !== 1 || $(selT).length !== 1 || $(selB).length !== 1) {
				continue;
			  }

			  // read + trim
			  var vStr = (($(selV).val() ?? '') + '').trim();
			  var tStr = (($(selT).val() ?? '') + '').trim();
			  var bStr = (($(selB).val() ?? '') + '').trim();

			  // Volume: required + integer + 0..100
			  if (vStr === '' || !isIntUnsigned(vStr)) {
				return showFail(selV, '<TMPL_VAR VOLUME_PROFILES.ERROR_VOLUME_PLAYER>');
			  }
			  var v = parseInt(vStr, 10);
			  if (v < 0 || v > 100) {
				return showFail(selV, '<TMPL_VAR VOLUME_PROFILES.ERROR_VOLUME_PLAYER>');
			  }

			  // Treble: required + integer + -10..10
			  if (tStr === '' || !isIntSigned(tStr)) {
				return showFail(selT, '<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_PLAYER>');
			  }
			  var t = parseInt(tStr, 10);
			  if (t < -10 || t > 10) {
				return showFail(selT, '<TMPL_VAR VOLUME_PROFILES.ERROR_TREBLE_PLAYER>');
			  }

			  // Bass: required + integer + -10..10
			  if (bStr === '' || !isIntSigned(bStr)) {
				return showFail(selB, '<TMPL_VAR VOLUME_PROFILES.ERROR_BASS_PLAYER>');
			  }
			  var b = parseInt(bStr, 10);
			  if (b < -10 || b > 10) {
				return showFail(selB, '<TMPL_VAR VOLUME_PROFILES.ERROR_BASS_PLAYER>');
			  }
			}
		  }

		  // all ok
		  message();
		  $('#main_table').trigger('create').trigger('refresh');
		  return true;
		});
	});

</script>
