<script>

$(document).on('pageinit', function() {
    select();
	select_update();
});

function select() {
	var rad = document.getElementById('announceradio').value;
	var rad1 = document.getElementById('announceradio_always').value;
	if (rad == true || rad1 == true) {
		$('.radioannounce').show();
	} else {
		$('.radioannounce').hide();
	}
}

function select_always() {
	var rad1 = document.getElementById('announceradio_always').value;
	if (rad1 == true) {
		$('.radioannounce').show();
    } else {
		$('.radioannounce').hide();
	}
}
				
function select_update() {
	var up = document.getElementById('hw_update').value;
	if (up == 'true') {
		$('.update').show();
	} else {
		$('.update').hide();
	}
}
						
function message()   {
	console.log("Save");
	timeout('<TMPL_VAR SAVE.SAVE_MESSAGE>', 'OK', 'info', '<TMPL_VAR SAVE.SAVE_ALL_OK>', '3000');
}
				
function timeout(text, ButtonText, Icon='', Title, timeout)    {
	// https://silverboxjs.ir/documentation/?v=latest
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

</script>