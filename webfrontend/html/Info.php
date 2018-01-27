<?php

/**
* Submodul: Info
*
**/

/**
/* Funktion : info --> zeigt visuelle Informationen bzlg. Titel/Sender an
/*
/* @param: 	empty
/* @return: 
**/	

function info()  {
	global $sonos;
	
    $PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
	$streamContent = $PositionInfo["streamContent"];
	if($sonos->GetTransportInfo() == 1 )  {
		# Play
		$status = 'Play';
	} else {
		# Pause
		$status = 'Pause';
	}  
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Cover - Dann Radio Cover
		$bild = $radio["logo"];
	}
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Title - Dann Radio Title
		$title = $GetMediaInfo["title"];
	}   
	if($PositionInfo["album"] == '')  {
		# Kein Album - Dann Radio Stream Info
		$album = $PositionInfo["streamContent"];
	}   
	echo'
		cover: <tab>' . $bild . '<br>   
		title: <tab>' . $title . '<br>
		album: <tab>' . $album . '<br>
		artist: <tab>' . $artist . '<br>
		time: <tab>' . $reltime . '<br>
		status: <tab>' . $status . '<br>
		';
}
      

/**
/* Funktion : cover --> zeigt visuelle Informationen bzgl. Cover an
/*
/* @param: 	empty
/* @return: 
**/	

function cover() {
	global $sonos;

	$PositionInfo = $sonos->GetPositionInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$bild = $PositionInfo["albumArtURI"];
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Cover - Dann Radio Cover
		$bild = $radio["logo"];
	}
	echo' ' . $bild . ' ';
}
		

/**
/* Funktion : titel --> zeigt Titel Informationen an
/* 
/* @param: 	empty
/* @return: 
**/	
		
function title()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$title = $PositionInfo["title"];
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Title - Dann Radio Title
		$title = $GetMediaInfo["title"];
	}
	echo' ' . $title . ' ';
}



/**
/* Funktion : artist --> zeigt Artist Informationen an
/*
/* @param: 	empty
/* @return: 
**/	
		
function artist()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
	echo' ' . $artist . ' ';      
}
		
/**
/* Funktion : album --> zeigt Album Informationen an
/*
/* @param: 	empty
/* @return: 
**/	
		 
function album()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$album = $PositionInfo["album"];
	if($PositionInfo["album"] == '')  {
		# Kein Album - Dann Radio Stream Info
		$album = $PositionInfo["streamContent"];
	}
	echo'' . $album . '';
}


/**
/* Funktion : titelinfo --> zeigt Informationen bzgl. Tiel/Interpret etc. an
/*
/* @param: 	empty
/* @return: 
**/	
		
function titelinfo()  {
	global $sonos;
	
	if($debug == 1) {
		#echo debug();
	}
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
		echo'
			<table>
				<tr>
					<td><img src="' . $bild . '" width="200" height="200" border="0"></td>
					<td>
					Titel: ' . $title . '<br><br>
					Album: ' . $album . '<br><br>
					Artist: ' . $artist . '</td>
				</tr>
				<tr>
				<td>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=previous" target="_blank">Back</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=play" target="_blank">Cancel</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=pause" target="_blank">Pause</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=stop" target="_blank">Stop</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=next" target="_blank">Next</a>
					</table>
				';
}


?>