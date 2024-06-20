<?php

declare(strict_types = 1);

// SONOS Acces Handler
// using PHP SoapClient



class SonosAccess
{
    public $address;

    public function __construct($address)
    {
        $this->address = $address;
		
		global $lbplogdir;
		
		# Create Logger environment based on used platforms
		# params for default $log_level = debug, info, error, warning
		
		$platform = PHP_OS_FAMILY;
		if ($platform == "Linux")   {
			# check whether it is a Loxberry
			if (getenv('LBHOMEDIR') != false)   {
				# Loxberry specific
				$logfolder = $lbplogdir;
				# use Sonos Plugin Loglevel
				if (!isset($_GET['debug']))    {
					$level = LBSystem::pluginloglevel();
				} else {
					$level = "7";
				}
				if ($level == "0")   { 
					$log_level = "off";
				} elseif ($level == "3")   { 
					$log_level = "error";
				} elseif ($level == "4")   { 
					$log_level = "warning";	
				} elseif ($level == "6")   { 
					$log_level = "info";	
				} elseif ($level == "7")   { 
					$log_level = "debug";
				}
			} else {
				# Non Loxberry
				$logfolder = "";
				$log_level = "";
			}
		} elseif ($platform == "Windows")   {
			$logfolder = "";
			$log_level = "0";
		} elseif ($platform == "BSD")   {
			$logfolder = "";
			$log_level = "0";
		} elseif ($platform == "Darwin")   {		// OS X
			$logfolder = "";
			$log_level = "0";
		} elseif ($platform == "Solaris")   {
			$logfolder = "";
			$log_level = "0";
		} elseif ($platform == "Unknown")   {
			$logfolder = "";
			$log_level = "0";
		} else {
			$logfolder = __DIR__ . "/logs";
			$log_level = "0";
		}
		
		$this->createLog($logfolder, $log_level);
    }


	/**
	 * Create Logging Interface 
	 *
	 * @param string $logfolder 
	 * @param int $loglevel
	 * 
	 * https://github.com/Idearia/php-logger
	 *
	 * @return String
	 */
	 
    public function createLog($logfolder, $log_level)
    {
		include_once("Logger.php");

		$time = date("dmY");
		Logger::$log_dir = $logfolder;
		Logger::$log_file_name = "SOAP-Log-".$time;
		Logger::$log_file_extension = 'log';
		
		Logger::$log_level = $log_level;
		Logger::$write_log = true;
		Logger::$print_log = false;	
	}
	

	/**
	 * Add URI to Queue 
	 *
	 * @param string $file     Uri or Filename
	 * @param string $meta     Metadata
	 *
	 */
	 
    public function AddToQueue($file, $meta = "")
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'AddURIToQueue',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($file, 'EnqueuedURI'),
                new SoapParam($meta, 'EnqueuedURIMetaData'),
                new SoapParam('0', 'DesiredFirstTrackNumberEnqueued'),
                new SoapParam('1', 'EnqueueAsNext')
            ]
        );
    }
	
	
	/**
	 * Load Stream to Queue
	 *
	 * @param string URI			URI of Stream
	 * @param string $Metadata     	Metadata
	 *
	 */	
	 
    public function SetAVTransportURI($tspuri, $MetaData = "")
    {	
		$this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'SetAVTransportURI',
            [
                new SoapParam('0', 'InstanceID'),
				new SoapParam(htmlspecialchars($tspuri, ENT_COMPAT | ENT_HTML401, ini_get('default_charset'), false), 'CurrentURI'),
                new SoapParam($MetaData, 'CurrentURIMetaData')
            ]
        );
    }
	
	
	/**
	 * Save current queue as Sonos Playlist
	 *
	 * @param string $title          Title of Playlist
	 * @param string $id             Playlists ID (optional)
	 *
	 * @return string
	 *
	 */
	 
    public function SaveQueue($title, $id = "")
    {
		$returnContent = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'SaveQueue',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($title, 'Title'),
                new SoapParam($id, 'ObjectID')
            ]
        );
	}
	
	
	/**
	 * Delete a Sonos Playlist
	 *
	 * @param string $id 
	 *
	 * @return: none
	 */	
	
	public function DeleteSonosPlaylist($id)
    {
		$this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'DestroyObject',
            [
                  new SoapParam($id, 'ObjectID')
            ]
        );
	}
	
	
	/**
	Example output: BrowseContentDirectory():

	Array
	(
		[0] => Array
			(
				[typ] => item
				[res] => x-rincon-cpcontainer%3A1006206ccatalog%252fplaylists%252fB09WDW2FY7%252f%2523prime_playlist%3Fsid%3D201%26flags%3D8300%26sn%3D8
				[resorg] => x-rincon-cpcontainer:1006206ccatalog%2fplaylists%2fB09WDW2FY7%2f%23prime_playlist?sid=201&flags=8300&sn=8
				[albumArtURI] => https://m.media-amazon.com/images/I/51yats2QBFL.jpg
				[title] => 1. Best of Prime
				[artist] => leer
				[id] => FV:2/164
				[parentid] => FV:2
				[album] => leer
			)
	)
	allowed objectID's:
	
	"SQ:" = Sonos Playlists
	"FV:2" = Sonos Favorites 		--> use GetFavorites()
	"S:" = Share
	"R:0/0" = Radio Stations
	"R:0/1" = Radio Shows
	"A" = Music library
	"A:ARTIST / A:ALBUMARTIST / A:ALBUM / A:GENRE / A:COMPOSER / A:TRACKS / A:PLAYLISTS" = Music library Details
	
	allowed browseFlag's:
	"BrowseDirectChildren"
	"BrowseMetadata"
	
	example for sort criteria:
	"+upnp:artist,+dc:title" = for sorting on artist then on title
 */
 
    public function BrowseContentDirectory($objectID = 'SQ:', $browseFlag = 'BrowseDirectChildren', $requestedCount = 1000, $startingIndex = 0, $filter = '', $sortCriteria = ''): array
    {
        $returnContent = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam($objectID, 'ObjectID'),
                new SoapParam($browseFlag, 'BrowseFlag'),
                new SoapParam($filter, 'Filter'),
                new SoapParam($startingIndex, 'StartingIndex'),
                new SoapParam($requestedCount, 'RequestedCount'),
                new SoapParam($sortCriteria, 'SortCriteria')
            ]
        );
		
		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $returnContent['Result'], $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($returnContent['Result']);
        $liste = array();
		for($i=0,$size=count($xml);$i<$size;$i++)
        {
            // Wenn Container vorhanden, dann ist es ein Browse Element
            // Wenn Item vorhanden, dann ist es ein Song.
            if(isset($xml->container[$i])){
                  $aktrow = $xml->container[$i];
                  $attr = $xml->container[$i]->attributes();
                  $liste[$i]['typ'] = "container";
             }else if(isset($xml->item[$i])){
               //Item vorhanden also nur noch Musik
                  $aktrow = $xml->item[$i];
                  $attr = $xml->item[$i]->attributes();
                  $liste[$i]['typ'] = "item";
            }else{
               //Fehler aufgetreten
               return false;
            }
			      $id = $attr['id'];
                  $parentid = $attr['parentID'];
                  $albumart = $aktrow->xpath("upnp:albumArtURI");
                  $titel = $aktrow->xpath("dc:title");
                  $interpret = $aktrow->xpath("dc:creator");
                  $album = $aktrow->xpath("upnp:album");
				  
                  if(isset($aktrow->res)){
                     $res = (string)$aktrow->res;
                     $liste[$i]['res'] = urlencode($res);
					 $liste[$i]['resorg'] = ($res);

                   }else{
                      $liste[$i]['res'] = "leer";
					  $liste[$i]['resorg'] = "leer";
                   }
                      $resattr = $aktrow->res->attributes();
                     # if(isset($resattr['duration'])){
                      #   $liste[$i]['duration']=(string)$resattr['duration'];
                      #}else{
                      #   $liste[$i]['duration']="leer";
                      #}
                  if(isset($albumart[0])){
                   $liste[$i]['albumArtURI']=(string)($albumart[0]);
                  }else{
                   $liste[$i]['albumArtURI'] ="leer";
                  }
                  $liste[$i]['title']=(string)$titel[0];
                  if(isset($interpret[0])){
                      $liste[$i]['artist']=(string)$interpret[0];
                  }else{
                     $liste[$i]['artist']="leer";
                  }
                  if(isset($id) && !empty($id)){
                      #$liste[$i]['id']=urlencode((string)$id);
					  $liste[$i]['id']=((string)$id);
                  }else{
                      $liste[$i]['id']="leer";
                  }
                  if(isset($parentid) && !empty($parentid)){
                      #$liste[$i]['parentid']=urlencode((string)$parentid
					  $liste[$i]['parentid']=((string)$parentid);
                  }else{
                      $liste[$i]['parentid']="leer";
                  }
                    if(isset($album[0])){
                   $liste[$i]['album']=(string)$album[0];
                  }else{
                   $liste[$i]['album']="leer";
                  }
				  

        }
	return $liste;
    }


	/**
	 * Clear devices Queue
	 *
	 * @return String
	 */
 
    public function ClearQueue()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'RemoveAllTracksFromQueue',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    }


	/**
	 * Delegate GroupCoordinator to another Zone
	 *
	 * @param string $RinconID, $Rejoin)
	 *
	 * @return: String
	 */
 
    public function DelegateGroupCoordinationTo(string $NewCoordinator, bool $RejoinGroup)
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'DelegateGroupCoordinationTo',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($NewCoordinator, 'NewCoordinator'),
                new SoapParam($RejoinGroup, 'RejoinGroup')
            ]
        );
    }
	
	
	/**
	 * Get Bass for Zone
	 *
	 * @param: None
	 *
	 * @return: String
	 */	
	 
    public function GetBass(): int
    {
        return (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetBass',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel')
            ]
        );
    }

	
	/**
	 * Get Battery level
	 *
	 * @param: None
	 *
	 * @return: String
	 */	
	public function GetBatteryLevel(): int
    {

        // this is not UPNP based...
        $level = 0;
        $result = file_get_contents('http://' . $this->address . ':1400/status/batterystatus');

        $xml = new SimpleXMLElement($result);
        if (isset($xml->LocalBatteryStatus->Data)) {
            foreach ($xml->LocalBatteryStatus->Data as $data) {
                if ($data->attributes()['name'] == 'Level') {
                    $level = intval($data);
                }
            }
        }
        return $level;
    }
	
	
	/**
	 * Get crossfade mode for Zone
	 *
	 * @param: None
	 *
	 * @return: bool
	 */	
	 
    public function GetCrossfade(): bool
    {
        $crossfade = (int) $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetCrossfadeMode',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        if ($crossfade === 1) {
            return true;
        } else {
            return false;
        }
    }
	
	
	/**
	 * Get dialog level for Zone
	 *
	 * @param: None
	 *
	 * @return: bool
	 */	
    public function GetDialogLevel($type)
    {
        $dialogLevel = (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetEQ',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($type, 'EQType')
            ]
        );
		
		if ($type == "SubGain") {
			return $dialogLevel;
		} else {
			if ($dialogLevel === 1) {
				return true;
			} else {
				return false;
			}
		}
    }


	/**
	 * Get Loudness for Zone
	 *
	 * @param: None
	 *
	 * @return: bool
	 */	
    public function GetLoudness(): bool
    {
        $loudness = (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetLoudness',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel')
            ]
        );

        if ($loudness === 1) {
            return true;
        } else {
            return false;
        }
    }


	/**
	Example output: GetMediaInfo():
	
	** Radio
	Array
	(
		[NrTracks] => 11
		[CurrentURI] => x-sonosapi-radio:sonos%3a3014?sid=303&flags=8296&sn=15
		[CurrentURIMetaData] => <DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><item id="100c2068sonos%3a3014" parentID="00080004%2fstations%2fde-DE%2fDE%2fc2Q6REU6cG9wLWhpdHM" restricted="true"><dc:title>Main Stream</dc:title><upnp:class>object.item.audioItem.audioBroadcast.#list-genre</upnp:class><dc:creator>Aktuelle Hits für Erwachsene</dc:creator><upnp:albumArtURI>https://sonosradio.imgix.net/placeholders/04ecd6eaa694770d0d468e371d7ecf50_09.png?w=200&amp;auto=format,compress</upnp:albumArtURI><r:albumArtist>Aktuelle Hits für Erwachsene</r:albumArtist><r:description>Pop/Hits</r:description><desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">SA_RINCON77575_X_#Svc77575-9eab0e1-Token</desc></item></DIDL-Lite>
		[title] => Main Stream
		[id] => 100c2068sonos%3a3014
		[albumArtURI] => https://sonosradio.imgix.net/placeholders/04ecd6eaa694770d0d468e371d7ecf50_09.png?w=200&auto=format,compress
		[UpnpClass] => object.item.audioItem.audioBroadcast.#list-genre
		[artist] => Pop/Hits
	)
	
	** All other
	Array
	(
		[NrTracks] => 67
		[CurrentURI] => x-rincon-queue:RINCON_347E5C335F6401400#0
		[CurrentURIMetaData] => 
		[title] => 
		[id] => 
		[albumArtURI] => 
		[UpnpClass] => 
		[artist] => 
	)
	*/

    public function GetMediaInfo(): array
    {
        $mediaInfo = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetMediaInfo',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        $xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $mediaInfo['CurrentURIMetaData'], $vals, $index);
        xml_parser_free($xmlParser);

        if (isset($index['DC:TITLE']) && isset($vals[$index['DC:TITLE'][0]]['value'])) {
            $mediaInfo['title'] = $vals[$index['DC:TITLE'][0]]['value'];
        } else {
            $mediaInfo['title'] = '';
        }
		
		if (isset($index['ITEM']) && isset($vals[$index['ITEM'][0]]['attributes']['ID'])) {
            $mediaInfo['id'] = $vals[$index['ITEM'][0]]['attributes']['ID'];
        } else {
            $mediaInfo['id'] = '';
        }
		
		if (isset($index['UPNP:ALBUMARTURI']) && isset($vals[$index['UPNP:ALBUMARTURI'][0]]['value'])) {
            $mediaInfo['albumArtURI'] = $vals[$index['UPNP:ALBUMARTURI'][0]]['value'];
        } else {
            $mediaInfo['albumArtURI'] = 'http://' . $this->address . ':1400/getaa?s=1&u=' . urlencode($mediaInfo['CurrentURI']);
        }
		
		if (isset($index['UPNP:CLASS']) && isset($vals[$index['UPNP:CLASS'][0]]['value'])) {
            $mediaInfo['UpnpClass'] = $vals[$index['UPNP:CLASS'][0]]['value'];
        } else {
            $mediaInfo['UpnpClass'] = '';
        }
		
		if (isset($index['R:DESCRIPTION']) && isset($vals[$index['R:DESCRIPTION'][0]]['value'])) {
            $mediaInfo['artist'] = $vals[$index['R:DESCRIPTION'][0]]['value'];
        } else {
            $mediaInfo['artist'] = '';
        }
		$mediaInfo['CurrentURIMetaData'] = htmlspecialchars($mediaInfo['CurrentURIMetaData']);
		# Delete not used entries from Original output array
		unset($mediaInfo['MediaDuration']);
		unset($mediaInfo['RecordMedium']);
		unset($mediaInfo['PlayMedium']);
		unset($mediaInfo['WriteStatus']);
		unset($mediaInfo['NextURI']);
		unset($mediaInfo['NextURIMetaData']);
        return $mediaInfo;
    }
	
	
	/**
	 * Get Mute for Zone
	 *
	 * @param: None
	 *
	 * @return: bool
	 */	
	 
    public function GetMute(): bool
    {
        $mute = (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetMute',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel')
            ]
        );
        if ($mute === 1) {
            return true;
        } else {
            return false;
        }
    }
	
	
	/**
	 * Get NightMode for Zone
	 *
	 * @param: None
	 *
	 * @return: bool
	 */	
	 
    public function GetNightMode(): bool
    {
        $nightMode = (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetEQ',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('NightMode', 'EQType')
            ]
        );

        if ($nightMode === 1) {
            return true;
        } else {
            return false;
        }
    }
	
	
	/**
	 * Get Output fixed Volume mode
	 *
	 * @param: None
	 *
	 * @return: bool
	 */
	 
    public function GetOutputFixed(): bool
    {
        $mute = (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetOutputFixed',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        if ($mute === 1) {
            return true;
        } else {
            return false;
        }
    }


	/**
	Example output: GetPositionInfo
	
	Array
	(
		[Track] => 1
		[TrackDuration] => 0:02:21
		[TrackMetaData] => x-sonosapi-hls-static:catalog%2ftracks%2fB098RK49DF%2f%3fplaylistAsin%3dB09YQC3D2X%26playlistType%3dprimePlaylist?sid=201&flags=8&sn=8/getaa?s=1&u=x-sonosapi-hls-static%3acatalog%252ftracks%252fB098RK49DF%252f%253fplaylistAsin%253dB09YQC3D2X%2526playlistType%253dprimePlaylist%3fsid%3d201%26flags%3d8%26sn%3d8STAY [Clean]object.item.audioItem.musicTrackThe Kid LAROI & Justin BieberBest of Prime
		[TrackURI] => x-sonosapi-hls-static:catalog%2ftracks%2fB098RK49DF%2f%3fplaylistAsin%3dB09YQC3D2X%26playlistType%3dprimePlaylist?sid=201&flags=8&sn=8
		[RelTime] => 0:00:31
		[RelCount] => 2147483647
		[AbsCount] => 2147483647
		[artist] => The Kid LAROI & Justin Bieber
		[title] => STAY [Clean]
		[album] => Best of Prime
		[UpnpClass] => object.item.audioItem.musicTrack
		[ProtocolInfo] => sonos.com-http:*:application/x-mpegURL:*
		[albumArtURI] => http://192.168.50.42:1400/getaa?s=1&u=x-sonosapi-hls-static%3acatalog%252ftracks%252fB098RK49DF%252f%253fplaylistAsin%253dB09YQC3D2X%2526playlistType%253dprimePlaylist%3fsid%3d201%26flags%3d8%26sn%3d8
		[albumArtist] => 
		[streamContent] => 
		[URI] => x-sonosapi-hls-static:catalog%2ftracks%2fB098RK49DF%2f%3fplaylistAsin%3dB09YQC3D2X%26playlistType%3dprimePlaylist?sid=201&flags=8&sn=8
		[CurrentURIMetaData] => <DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><item id="-1" parentID="-1" restricted="true"><res protocolInfo="sonos.com-http:*:application/x-mpegURL:*" duration="0:02:21">x-sonosapi-hls-static:catalog%2ftracks%2fB098RK49DF%2f%3fplaylistAsin%3dB09YQC3D2X%26playlistType%3dprimePlaylist?sid=201&amp;flags=8&amp;sn=8</res><r:streamContent></r:streamContent><upnp:albumArtURI>/getaa?s=1&amp;u=x-sonosapi-hls-static%3acatalog%252ftracks%252fB098RK49DF%252f%253fplaylistAsin%253dB09YQC3D2X%2526playlistType%253dprimePlaylist%3fsid%3d201%26flags%3d8%26sn%3d8</upnp:albumArtURI><dc:title>STAY [Clean]</dc:title><upnp:class>object.item.audioItem.musicTrack</upnp:class><dc:creator>The Kid LAROI &amp; Justin Bieber</dc:creator><upnp:album>Best of Prime</upnp:album></item></DIDL-Lite>
	)
	*/	
	
    public function GetPositionInfo(): array
    {
        $positionInfo = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetPositionInfo',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        $xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $positionInfo['TrackMetaData'], $vals, $index);
        xml_parser_free($xmlParser);

        if (isset($index['DC:CREATOR']) && isset($vals[$index['DC:CREATOR'][0]]['value'])) {
            $positionInfo['artist'] = $vals[$index['DC:CREATOR'][0]]['value'];
        } else {
            $positionInfo['artist'] = '';
        }

        if (isset($index['DC:TITLE']) && isset($vals[$index['DC:TITLE'][0]]['value'])) {
			# Exclude (Exception) for TuneIn Radio
			$tunein = mb_strpos($vals[$index['DC:TITLE'][0]]['value'], 'tunein');
			if ($tunein != false)   {
				$positionInfo['title'] = '';
			} else {
				$positionInfo['title'] = $vals[$index['DC:TITLE'][0]]['value'];
			}
        } else {
            $positionInfo['title'] = '';
        }

        if (isset($index['UPNP:ALBUM']) && isset($vals[$index['UPNP:ALBUM'][0]]['value'])) {
            $positionInfo['album'] = $vals[$index['UPNP:ALBUM'][0]]['value'];
        } else {
            $positionInfo['album'] = '';
        }
		
		if (isset($index['UPNP:CLASS']) && isset($vals[$index['UPNP:CLASS'][0]]['value'])) {
            $positionInfo['UpnpClass'] = $vals[$index['UPNP:CLASS'][0]]['value'];
        } else {
            $positionInfo['UpnpClass'] = '';
        }
		
		if (isset($index['RES']) && isset($vals[$index['RES'][0]]['attributes']['PROTOCOLINFO'])) {
            $positionInfo['ProtocolInfo'] = $vals[$index['RES'][0]]['attributes']['PROTOCOLINFO'];
        } else {
            $positionInfo['ProtocolInfo'] = '';
        }

        if (isset($index['UPNP:ALBUMARTURI']) && isset($vals[$index['UPNP:ALBUMARTURI'][0]]['value'])) {
            if (preg_match('/^https?:\/\/[\w,.,-,:]*\/\S*/', $vals[$index['UPNP:ALBUMARTURI'][0]]['value']) == 1) {
                $positionInfo['albumArtURI'] = $vals[$index['UPNP:ALBUMARTURI'][0]]['value'];
            } else {
                $positionInfo['albumArtURI'] = 'http://' . $this->address . ':1400' . $vals[$index['UPNP:ALBUMARTURI'][0]]['value'];
            }
        } else {
            $positionInfo['albumArtURI'] = '';
        }

        if (isset($index['R:ALBUMARTIST']) && isset($vals[$index['R:ALBUMARTIST'][0]]['value'])) {
            $positionInfo['albumArtist'] = $vals[$index['R:ALBUMARTIST'][0]]['value'];
        } else {
            $positionInfo['albumArtist'] = '';
        }

        if (isset($index['R:STREAMCONTENT']) && isset($vals[$index['R:STREAMCONTENT'][0]]['value'])) {
            $positionInfo['streamContent'] = $vals[$index['R:STREAMCONTENT'][0]]['value'];
        } else {
            $positionInfo['streamContent'] = '';
        }
		
		if (isset($index["RES"]) and isset($vals[$index["RES"][0]]["value"])) {
			$positionInfo["URI"] = $vals[$index["RES"][0]]["value"];
		} else {
			$positionInfo["URI"] = "";
		}
		$positionInfo['CurrentURIMetaData'] = htmlspecialchars($positionInfo['TrackMetaData']);
		# Delete not used entries from array
		unset($positionInfo['AbsTime']);
        return $positionInfo;
    }
	
	
	/**
	 * Get battery level for Zone (ROAM and MOVE)
	 *
	 * @param: None
	 *
	 * @return: String
	 */	
	 
    public function GetPowerSource(): int
    {

        // this is not UPNP based...
        $power_source = 0;
        $result = file_get_contents('http://' . $this->address . ':1400/status/batterystatus');

        $xml = new SimpleXMLElement($result);
        if (isset($xml->LocalBatteryStatus->Data)) {
            foreach ($xml->LocalBatteryStatus->Data as $data) {
                if ($data->attributes()['name'] == 'PowerSource') {
                    $power_source = 0;  // setting to "unknown" not to produce errors
                    if ($data == 'BATTERY') {
                        $power_source = 1;
                    } elseif ($data == 'SONOS_CHARGING_RING') {
                        $power_source = 2;
                    } elseif ($data == 'USB_POWER') {
                        $power_source = 3;
                    }
                }
            }
        }
        return $power_source;
    }
		
		
	/**
	 * Get Remaining Sleeptimer for Zone
	 *
	 * @param: None
	 *
	 * @return: String
	 */

    public function GetSleeptimer(): string
    {
        $remainingTimer = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetRemainingSleepTimerDuration',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
        return $remainingTimer['RemainingSleepTimerDuration'];
    }


	 /**
	 * Get transport info for zone
	 *
	 * @return Array
	 */
    public function GetTransportInfo(): int
    {
        $returnContent = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetTransportInfo',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        switch ($returnContent['CurrentTransportState']) {
          case 'PLAYING':
            return 1;
          case 'PAUSED_PLAYBACK':
            return 2;
          case 'STOPPED':
            return 3;
          case 'TRANSITIONING':
            return 5;
          default:
            throw new Exception('Unknown Transport State: ' . $returnContent['CurrentTransportState']);
        }
    }
	
	
	/**
	 * Get transport settings for zone
	 *
	 * @return Array
	 */

    public function GetTransportSettings(): int
    {
        $returnContent = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetTransportSettings',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );

        switch ($returnContent['PlayMode']) {
          case 'NORMAL':
            return 0;
          case 'REPEAT_ALL':
            return 1;
          case 'REPEAT_ONE':
            return 2;
          case 'SHUFFLE_NOREPEAT':
            return 3;
          case 'SHUFFLE':
            return 4;
          case 'SHUFFLE_REPEAT_ONE':
            return 5;
          default:
            throw new Exception('Unknown Play Mode: ' . $returnContent['CurrentTransportState']);
        }
    }


	/**
	 * Get transport action for a renderer
	 *
	 * @return String
	 */
	 
	public function GetCurrentTransportActions()
	{
   	$returnContent = $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'GetCurrentTransportActions',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    	$ret = preg_replace("#(.*)<Actions>(.*?)\</Actions>(.*)#is",'$2',$returnContent);
    	return $ret;
	}
	
	
	/**
	 * Get Treble for Zone
	 *
	 * @param: none
	 *
	 * @return: string
	 */	
 
    public function GetTreble(): int
    {
        return (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetTreble',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel')
            ]
        );
    }


	/**
	 * Get current volume information from player
	 *
	 * @return String
	 */
 
    public function GetVolume($channel = 'Master'): int
    {
        return (int) $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'GetVolume',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel')
            ]
        );
    }


	 /**
	 * Get current information from player
	 *
	 * @return String
	 */
 
    public function GetZoneGroupAttributes(): array
    {
        return $this->processSoapCall(
            '/ZoneGroupTopology/Control',
            'urn:schemas-upnp-org:service:ZoneGroupTopology:1',
            'GetZoneGroupAttributes',
            []
        );
    }
	
	 /**
	 * Get current information from player
	 *
	 * @return String
	 */

    public function GetZoneGroupState()
    {
        $zonegroups = $this->processSoapCall(
            '/ZoneGroupTopology/Control',
            'urn:schemas-upnp-org:service:ZoneGroupTopology:1',
            'GetZoneGroupState',
            []
        );
		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $zonegroups, $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($zonegroups);
		
		$liste = array();
		for($i=0,$size=count($xml->ZoneGroups->ZoneGroup);$i<$size;$i++)
        {
			#$attrhead = $xml->ZoneGroups->ZoneGroup[$i]->attributes();
			$attrdetail = $xml->ZoneGroups->ZoneGroup[$i]->ZoneGroupMember->attributes();
			$satdetail = $xml->ZoneGroups->ZoneGroup[$i]->ZoneGroupMember->Satellite;
			$liste[$i]['ZoneName'] = (string)$attrdetail['ZoneName'];
			$liste[$i]['UUID'] = (string)$attrdetail['UUID'];
			$liste[$i]['HDMI'] = (string)$attrdetail['HdmiCecAvailable'];
			$liste[$i]['Wifi_Enabled'] = (string)$attrdetail['WifiEnabled'];
			$liste[$i]['BehindWifiExtender'] = (string)$attrdetail['BehindWifiExtender'];
			$liste[$i]['Eth_Enabled'] = (string)$attrdetail['EthLink'];
			foreach ($satdetail as $key)    {
				#print_r($key->attributes()->UUID);
			}
			#$liste[$i]['Satellite'] = $satdetail;
        }
		print_r($liste);
		print_r($xml->ZoneGroups);
	}
	
	
	/**
	 * Get current player Online
	 *
	 * @return array
	 */

    public function GetZoneStates()
    {
        $ZoneStates = $this->processSoapCall(
            '/ZoneGroupTopology/Control',
            'urn:schemas-upnp-org:service:ZoneGroupTopology:1',
            'GetZoneGroupState',
            []
        );
		return $ZoneStates;
	}
	 
	 
	 
	/**
	 * Get Autoplay RINCON-ID from player
	 *
	 * @return array
	 
	 Array
	(
		[RoomUUID] => RINCON_949F3EC767F101400
		[Source] => 
	)
	
	 */

    public function GetAutoplayRoomUUID()
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'GetAutoplayRoomUUID',
            []
        );
    }
	
	
	/**
	 * Set Autoplay RINCON-ID from player
	 *
	 * @param string $uuid			Rincon-ID of Player
	 * @param string $source		Source to be played (could be empty)
	 */

    public function SetAutoplayRoomUUID($uuid, $source = "")
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'SetAutoplayRoomUUID',
            [
                new SoapParam($uuid, 'RoomUUID'),
				new SoapParam($source, 'Source')
			]
        );
    }
	
	
	/**
	 * Get Autoplay linked zones from player
	 *
	 * @return array
	 
	 Array
	(
		[IncludeLinkedZones] => 0
		[Source] => 
	)
	
	 */

    public function GetAutoplayLinkedZones()
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'GetAutoplayLinkedZones',
            []
        );
    }
	
	
	/**
	 * Set Autoplay linked zones from player
	 *
	 * @param string $zones			true or false
	 * @param string $source		Source to be played (could be empty)
	 */

    public function SetAutoplayLinkedZones($zones, $source = "")
    {	
	
		if ($zones == 'true')   {
			$zones = 1;
		} else {
			$zones = 0;
		}
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'SetAutoplayLinkedZones',
            [
                new SoapParam($zones, 'IncludeLinkedZones'),
				new SoapParam($source, 'Source')
			]
        );
    }
	
	
	/**
	 * Get Autoplay Volume from player
	 *
	 * @return array
	 
	 Array
	(
		[CurrentVolume] => 20
		[Source] => 
	)
	
	 */

    public function GetAutoplayVolume()
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'GetAutoplayVolume',
            []
        );
    }
	
	
	/**
	 * Set Autoplay Volume for player
	 *
	 * @param string $zones			true or false
	 * @param string $source		Source to be played (could be empty)
	 */

    public function SetAutoplayVolume($volume, $source = "")
    {	
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'SetAutoplayVolume',
            [
                new SoapParam($volume, 'Volume'),
				new SoapParam($source, 'Source')
			]
        );
    }
	
	
	/**
	 * Get Used Autoplay Volume from player
	 *
	 * @return array
	 
	 Array
	(
		[CurrentVolume] => 20
		[Source] => 
	)
	
	 */

    public function GetUseAutoplayVolume()
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'GetUseAutoplayVolume',
            []
        );
    }
	
	
	/**
	 * Set used Autoplay Volume zones for player
	 *
	 * @param string $zones			true or false
	 * @param string $source		Source to be played (could be empty)
	 */

    public function SetUseAutoplayVolume($zones, $source = "")
    {	
	
		if ($zones == 'true')   {
			$zones = 1;
		} else {
			$zones = 0;
		}
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'SetUseAutoplayVolume',
            [
                new SoapParam($zones, 'UseVolume'),
				new SoapParam($source, 'Source')
			]
        );
    }
	
	
	/**
	 * Get Button locked State from player
	 *
	 * @return string
	 *
	 */

    public function GetButtonLockState()
    {
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'GetButtonLockState',
            []
        );
    }
	
	
	/**
	 * Set Button Lock State for player
	 *
	 * @param string $zones			true or false
	 */

    public function SetButtonLockState($zones)
    {	
	
		if ($zones == 'true')   {
			$zones = "On";
		} else {
			$zones = "Off";
		}
        return $this->processSoapCall(
            '/DeviceProperties/Control',
            'urn:schemas-upnp-org:service:DeviceProperties:1',
            'SetButtonLockState',
            [
                new SoapParam($zones, 'DesiredButtonLockState')
			]
        );
    }
	

	/**
	 * Next 
	 *
	 * @return Void
	 */
 
    public function Next()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Next',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    }


	/**
	 * Pauses playing
	 *
	 * @return Void
	 */
 
    public function Pause()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Pause',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    }


	/**
	 * Play or continue playback
	 *
	  * @return Void
	 */
 
    public function Play()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Play',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('1', 'Speed')
            ]
        );
    }


	/**
	 * Previous
	 *
	 * @return Void
	 */
 
    public function Previous()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Previous',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    }


	/**
	 * Ramps Volume to $volume using $rampType
	 *
	 *   Ramps Volume to $volume using the Method mentioned in $ramp_type as string:
	 *   "SLEEP_TIMER_RAMP_TYPE" - mutes and ups Volume per default within 17 seconds to desiredVolume
	 *   "ALARM_RAMP_TYPE" -Switches audio off and slowly goes to volume
	 *   "AUTOPLAY_RAMP_TYPE" - very fast and smooth; Implemented from Sonos for the autoplay feature.
	 *
	 * @param string $volume               (DesiredVolume)
	 *
	 * @return Void
	*/
  
    public function RampToVolume($rampType, $volume)
    {
        switch ($rampType) {
          case 1:
            $rampType = 'SLEEP_TIMER_RAMP_TYPE';
            break;
          case 2:
            $rampType = 'ALARM_RAMP_TYPE';
            break;
          case 3:
            $rampType = 'AUTOPLAY_RAMP_TYPE';
            break;
        }

        return $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'RampToVolume',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel'),
                new SoapParam($rampType, 'RampType'),
                new SoapParam($volume, 'DesiredVolume'),
                new SoapParam(0, 'ResetVolumeAfter'),
                new SoapParam('', 'ProgramURI')
            ]
        );
    }


	/**
		Example output: GetCurrentPlaylist():
		
	Array
	(
		[0] => Array
			(
				[listid] => 1
				[albumArtURI] => http://192.168.50.42:1400/getaa?s=1&u=x-sonos-spotify%3aspotify%253atrack%253a0eBEo8ckRAmUEdrbmgpevQ%3fsid%3d9%26flags%3d8224%26sn%3d5
				[title] => Kogong
				[artist] => Mark Forster
				[album] => Kogong
			)
	)
	 */

	/**
	 * Returns an array with the songs of the actual sonos queue
	 *
	 * @return String
	 */
 
    public function GetCurrentPlaylist()
    {
        $CurrPlaylist = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Q:0', 'ObjectID'),
				new SoapParam('BrowseDirectChildren', 'BrowseFlag'),
				new SoapParam('0', 'StartingIndex'),
				new SoapParam('', 'Filter'),
				new SoapParam('', 'SortCriteria'),
				new SoapParam('1000', 'RequestedCount')
            ]
        );

		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $CurrPlaylist['Result'], $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($CurrPlaylist['Result']);
		$liste = array();
        for($i=0,$size=count($xml);$i<$size;$i++)
        {
		    $aktrow = $xml->item[$i];
            $albumart = $aktrow->xpath("upnp:albumArtURI");
            $title = $aktrow->xpath("dc:title");
            $artist = $aktrow->xpath("dc:creator");
            $album = $aktrow->xpath("upnp:album");
            $liste[$i]['listid']=$i+1;
            if(isset($albumart[0]))   {
                $liste[$i]['albumArtURI']="http://" . $this->address . ":1400".(string)$albumart[0];
            } else {
                $liste[$i]['albumArtURI'] ="";
            }
			if(isset($title[0]))    {
				$liste[$i]['title']=(string)$title[0];
			} else {
				$liste[$i]['title']="";
			}
            if(isset($artist[0]))    {
                $liste[$i]['artist']=(string)$artist[0];
            } else {
                $liste[$i]['artist']="";
            }
            if(isset($album[0]))   {
                $liste[$i]['album']=(string)$album[0];
            } else {
                $liste[$i]['album']="";
            }
        }
	return $liste;
    }
	

	/**
		Example output: GetSonosPlaylists():
		
	Array
	(
		[0] => Array
			(
				[id] => SQ:929
				[title] => Mix
				[typ] => Sonos
				[file] => file%3A%2F%2F%2Fjffs%2Fsettings%2Fsavedqueues.rsq%23929
			)
	)
	 */
	 
	/**
	 * Returns an array with all sonos playlists
	 *
	 * @return Array
	 */
 
    public function GetSonosPlaylists()
    {
		$SonosPlaylist = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('SQ:', 'ObjectID'),
				new SoapParam('BrowseDirectChildren', 'BrowseFlag'),
				new SoapParam('0', 'StartingIndex'),
				new SoapParam('', 'Filter'),
				new SoapParam('', 'SortCriteria'),
				new SoapParam('1000', 'RequestedCount')
            ]
        );

		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $SonosPlaylist['Result'], $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($SonosPlaylist['Result']);
		$liste = array();
        for($i=0,$size=count($xml);$i<$size;$i++)
        {
            $attr = $xml->container[$i]->attributes();
            $liste[$i]['id'] = (string)$attr['id'];
            $title = $xml->container[$i];
            $title = $title->xpath('dc:title');
            $liste[$i]['title'] = (string)$title[0];
            $liste[$i]['typ'] = "Sonos";
            $liste[$i]['file'] = urlencode((string)$xml->container[$i]->res);
        }
		return $liste;
	}


	/**
		Example output: GetPlaylist():
		
	Array
	(
		[0] => Array
			(
				[listid] => 1
				[albumArtURI] => http://192.168.50.42:1400/getaa?s=1&u=x-sonos-spotify%3aspotify%253atrack%253a0eBEo8ckRAmUEdrbmgpevQ%3fsid%3d9%26flags%3d8224%26sn%3d5
				[title] => Kogong
				[artist] => 
				[album] => Kogong
			)
	)
	 */

	/**
	 * Returns an array with all songs of a specific Playlist
	 *
	 * @param string $value Id (SQ:xxx) of the playlist
	 *
	 * @return Array
	 */
 
    public function GetPlaylist($value)
    {
		$Playlist = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($value, 'ObjectID'),
				new SoapParam('BrowseDirectChildren', 'BrowseFlag'),
				new SoapParam('0', 'StartingIndex'),
				new SoapParam('', 'Filter'),
				new SoapParam('', 'SortCriteria'),
				new SoapParam('1000', 'RequestedCount')
            ]
        );	
		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $Playlist['Result'], $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($Playlist['Result']);
		$liste = array();
        for($i=0,$size=count($xml);$i<$size;$i++)
        {
            $aktrow = $xml->item[$i];
            $albumart = $aktrow->xpath("upnp:albumArtURI");
            $title = $aktrow->xpath("dc:title");
            $artist = $aktrow->xpath("dc:creator");
            $album = $aktrow->xpath("upnp:album");
            $liste[$i]['listid']=$i+1;
            if(isset($albumart[0]))    {
                $liste[$i]['albumArtURI']="http://" . $this->address . ":1400".(string)$albumart[0];
            } else {
                $liste[$i]['albumArtURI'] ="";
            }
            $liste[$i]['title']=(string)$title[0];
            if(isset($interpret[0]))   {
                $liste[$i]['artist'] = (string)$artist[0];
            } else {
                $liste[$i]['artist'] = "";
            }
            if(isset($album[0]))   {
                $liste[$i]['album'] = (string)$album[0];
            } else {
                $liste[$i]['album'] = "";
            }
        }
		return $liste;
	}


	/**
		Example output: GetImportedPlaylist(): 		// .m3u
		
	Array
	(
		[0] => Array
			(
				[id] => 1
				[title] => Kogong
				[typ] => Import
				[file] => 
			)
	)
	 */

	/**
	 * Returns an array with all imported PL
	 *
	 * @return Array
	 */

 public function GetImportedPlaylists()
    {
		$ImportedPL = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('A:PLAYLISTS', 'ObjectID'),
				new SoapParam('BrowseDirectChildren', 'BrowseFlag'),
				new SoapParam('0', 'StartingIndex'),
				new SoapParam('', 'Filter'),
				new SoapParam('', 'SortCriteria'),
				new SoapParam('1000', 'RequestedCount')
            ]
        );
		
		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $ImportedPL['Result'], $vals, $index);
        xml_parser_free($xmlParser);

		if ($ImportedPL['NumberReturned'] == "0")     {
			$liste['id'] = "";
			$liste['title'] = "";
            $liste['typ'] = "No Imported Playlists";
            $liste['file'] = "";
		} else {
			$xml = new SimpleXMLElement($ImportedPL['Result']);
			for($i=0,$size=count($xml);$i<$size;$i++)
			{
				$attr = $xml->container[$i]->attributes();
				$liste[$i]['id'] = (string)$attr['id'];
				$title = $xml->container[$i];
				$title = $title->xpath('dc:title');
				$liste[$i]['title'] = (string)$title[0];
				$liste[$i]['title'] = preg_replace("/^(.+)\.m3u$/i","\\1",$liste[$i]['title']);
				$liste[$i]['typ'] = "Import";
				$liste[$i]['file'] = (string)$xml->container[$i]->res;
			}
		}
		return $liste;
	}


	/**
		Example output: GetFavorites():
		
	Array
	(
		[0] => Array
			(
				[typ] => Playlist
				[resorg] => x-rincon-cpcontainer:1006206ccatalog%2fplaylists%2fB09WDW2FY7%2f%23prime_playlist?sid=201&flags=8300&sn=8
				[res] => x-rincon-cpcontainer%3A1006206ccatalog%252fplaylists%252fB09WDW2FY7%252f%2523prime_playlist%3Fsid%3D201%26flags%3D8300%26sn%3D8
				[protocolInfo] => x-rincon-cpcontainer:*:*:*
				[UpnpClass] => object.container.playlistContainer
				[albumArtURI] => https://m.media-amazon.com/images/I/51yats2QBFL.jpg
				[title] => 1. Best of Prime
				[artist] => Amazon Music Playliste
				[id] => 1006206ccatalog%2fplaylists%2fB09WDW2FY7%2f%23prime_playlist
				[parentid] => 10fe2064catalog%2fplaylists%2f%23prime_playlists
				[token] => SA_RINCON51463_X_#Svc51463-0-Token
			)
	)
	 */
	 
	/**
	 * Returns an array with all Sonos Favorites
	 *
	 *
	 * @return Array
	 */
 
    public function GetFavorites()
    {
		$Favorites = $this->processSoapCall(
            '/MediaServer/ContentDirectory/Control',
            'urn:schemas-upnp-org:service:ContentDirectory:1',
            'Browse',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('FV:2', 'ObjectID'),
				new SoapParam('BrowseDirectChildren', 'BrowseFlag'),
				new SoapParam('0', 'StartingIndex'),
				new SoapParam('', 'Filter'),
				new SoapParam('', 'SortCriteria'),
				new SoapParam('1000', 'RequestedCount')
            ]
        );	
		$xmlParser = xml_parser_create('UTF-8');
        xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_parse_into_struct($xmlParser, $Favorites['Result'], $vals, $index);
        xml_parser_free($xmlParser);

        $xml = new SimpleXMLElement($Favorites['Result']);
		$liste = array();
		for($i=0,$size=count($xml);$i<$size;$i++)
			
        {
            // If Container exist, use Browse Element
            // If Item exist, then it's a song.
            if(isset($xml->container[$i]))   {
                  $aktrow = $xml->container[$i];
                  $attr = $xml->container[$i]->attributes();
				  $liste[$i]['typ'] = "container";
             } else if(isset($xml->item[$i]))   {
               // If Item exist still Music
                  $aktrow = $xml->item[$i];
                  $attr = $xml->item[$i]->attributes();
				  $result = $xml->item[$i]->xpath('r:resMD');
				  $liste[$i]['typ'] = "item";
            } else {
               return false;
            }
				# extract id, parentID and token
				$xmls = new SimpleXMLElement((string)$result[0][0]);
				$tmp = $xmls->item->attributes();
				$tmp1 = $xmls->item->xpath('upnp:class');
							
				  $id = $tmp['id'];
                  $parentid = $tmp['parentID'];
				  $token = $xmls->item->desc;
				  $upnpclass = $tmp1[0][0];
                  $albumart = $aktrow->xpath("upnp:albumArtURI");
                  $titel = $aktrow->xpath("dc:title");
                  $interpret[0] = $aktrow->xpath("r:description");
                  $album = $aktrow->xpath("upnp:album");
				  
                  if(isset($aktrow->res))    {
						$res = (string)$aktrow->res;
						$liste[$i]['resorg'] = ($res);
						$liste[$i]['res'] = urlencode($res);
                  } else {
						$liste[$i]['res'] = "";
                  }
                  $resattr = $aktrow->res->attributes();
				  
				  if(isset($resattr['protocolInfo']))    {
						$liste[$i]['protocolInfo']=(string)$resattr['protocolInfo'];
                  } else {
						$liste[$i]['protocolInfo']="";
                  }
				  
				  if(isset($upnpclass[0]))   {
						$liste[$i]['UpnpClass'] = (string)$upnpclass;
                  } else {
						$liste[$i]['UpnpClass'] ="";
                  } 
					  
                  if(isset($albumart[0]))   {
						$liste[$i]['albumArtURI']=(string)$albumart[0];
                  }else{
						$liste[$i]['albumArtURI'] ="";
                  }
                  $liste[$i]['title']=(string)$titel[0];
				  
                  if(isset($interpret[0]))    {
						$liste[$i]['artist']=(string)$interpret[0][0];
                  } else {
						$liste[$i]['artist']="";
                  }
					# Prepare type of favorit (Not UPNP)
				  if($liste[$i]['typ'] == "item")   {
						$identpl = substr($liste[$i]['resorg'],0, 17); 
						# Prepare Radio
						if ($identpl == "x-sonosapi-stream" or $identpl == "x-sonosapi-radio:")  {
							$liste[$i]['typ'] = "Radio";
						# Prepare Album
						} else if ($identpl == "file:///jffs/sett" or $identpl == "x-rincon-cpcontai" or $identpl == "x-rincon-playlist") {
							$liste[$i]['typ'] = "Playlist";
						} else {
						# Prepare Track
							$liste[$i]['typ'] = "Track";
						}
                  } else {
                     $liste[$i]['typ'] = "Playlist";
                  }
				  
                  if(isset($id) && !empty($id))     {
						$liste[$i]['id']=((string)$id);
                  } else {
						$liste[$i]['id'] = "leer";
                  }
				  
                  if(isset($parentid) && !empty($parentid))    {
						$liste[$i]['parentid']=((string)$parentid);
                  } else {
						$liste[$i]['parentid']="leer";
                  }
				  
				  if(isset($token[0]))    {
						$liste[$i]['token']=(string)$token[0];
                  } else {
						$liste[$i]['token'] ="leer";
                  }
 		}
		return $liste;
	}

	
	/**
	 * Remove a track from queue (not from Playlist!!)
	 *
	 * @param string $track  Tracknumber/id to remove from current sonos queue (!)
	 *
	 * @return string
	 */
 
    public function RemoveFromQueue($track)
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'RemoveTrackFromQueue',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Q:0/' . $track, 'ObjectID')
            ]
        );
    }


	/**
	 * REWIND
	 *
	 * @return String
	 */
	 
    public function Rewind()
    {
        $this->Seek('REL_TIME', '00:00:00');
    }


	/**
	 * SEEK
	 *
	 * @param string $arg1           	Unit ("TRACK_NR" || "REL_TIME" || "SECTION")
	 * @param string $arg2             	Target (if this Arg is not set Arg1 is considered to be "REL_TIME and the real arg1 value is set as arg2 value)
	 *
	 * @return String
	 */
 
    public function Seek($unit, $target)
    {
		#if ($target == "NONE")    {
		#	$unit = "REL_TIME"; 
		#	$target = $unit;
		#} else {
		#	$unit = $unit; 
		#	$target = $target;
		#}
		
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Seek',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($unit, 'Unit'),
                new SoapParam($target, 'Target')
            ]
        );
    }


	/**
	 * Set Bass for Zone
	 *
	 * @param string Treble
	 *
	 * @return: None
	 */	
 
    public function SetBass($bass)
    {
        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetBass',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($bass, 'DesiredBass')
            ]
        );
    }


	/**
	 * Set crossfade to true or false
	 *
	 * @param string $crossfade          Enable/ Disable = 1/0 (string) = true /false (boolean)
	 *
	 * @return Void
	*/
  
    public function SetCrossfade($crossfade)		// Loxberry: SetCrossfadeMode
    {
        if ($crossfade) {
            $crossfade = '1';
        } else {
            $crossfade = '0';
        }

        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'SetCrossfadeMode',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($crossfade, 'CrossfadeMode')
            ]
        );
    }


	/**
	 * Set various options for TV Mode
	 *
	 * @param string $dialogLevel   0 or 1
	 * @param string $EQType        SubEnable, SurroundEnable, NightMode, DialogLevel 
	 *
	 * @return String
	 */
 
	public function SetDialogLevel($dialogLevel, $EQType = "DialogLevel")
    {
        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetEQ',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($EQType, 'EQType'),
                new SoapParam($dialogLevel, 'DesiredValue')
            ]
        );
    }


	/**
	 * Set Loudness for Zone
	 *
	 * @param string (0 or 1)
	 *
	 * @return: None
	 */	
 
    public function SetLoudness($loud)
    {
        if ($loud) {
            $loud = '1';
        } else {
            $loud = '0';
        }

        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetLoudness',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel'),
                new SoapParam($loud, 'DesiredLoudness')
            ]
        );
    }


	/**
	 * Set mute/unmute for a player
	 *
	 * @param string $mute           Mute unmute as (boolean)true/false or (string)1/0
	 *
	 * @return String
 */
 
    public function SetMute($mute)
    {
        if ($mute) {
            $mute = '1';
        } else {
            $mute = '0';
        }

        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetMute',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('Master', 'Channel'),
                new SoapParam($mute, 'DesiredMute')
            ]
        );
    }
	

	/**
	 * Set nightmode for TV
	 *
	 * @param string $mode          0 or 1
	 *
	 * @return String
	 */
 
    public function SetNightMode($nightMode)
    {
        if ($nightMode) {
            $nightMode = '1';
        } else {
            $nightMode = '0';
        }

        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetEQ',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam('NightMode', 'EQType'),
                new SoapParam($nightMode, 'DesiredValue')
            ]
        );
    }


	/**
	 * Set Playmode for a renderer (could affect more than one zone!)
	 * 
	 * @param string $mode "NORMAL" || "REPEAT_ALL" || "SHUFFLE_NOREPEAT" || "SHUFFLE"
	 *
	 * @return String
	 */
    public function SetPlayMode($PlayMode)
    {
        switch ($PlayMode) {
          case 0:
            $PlayMode = 'NORMAL';
            break;
          case 1:
            $PlayMode = 'REPEAT_ALL';
            break;
          case 2:
            $PlayMode = 'REPEAT_ONE';
            break;
          case 3:
            $PlayMode = 'SHUFFLE_NOREPEAT';
            break;
          case 4:
            $PlayMode = 'SHUFFLE';
            break;
          case 5:
            $PlayMode = 'SHUFFLE_REPEAT_ONE';
            break;
          default:
            throw new Exception('Unknown Play Mode: ' . $PlayMode);
        }
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'SetPlayMode',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($PlayMode, 'NewPlayMode')
            ]
        );
    }


	/**
	 * Set Queue
	 *
	 * @param string $queue      	transport URI or Queue
	 * @param string $MetaData    	(optional for MetaData)
	 *
	 * @return Void
	 */
 
    public function SetQueue($queue, $MetaData = "")
    {
        $this->SetAVTransportURI($queue, $MetaData);
    }


	/**
	 * Load Radio station
	 *
	 * @param string $radio            	radio url
	 * @param string $radio_name       	Name of station (optional)
	 * @param string $metaData      	Cover url (optional)
	 *
	 * @return array
	 */
 
    public function SetRadio($radio, $radio_name = 'Radio', $metaData = "")
    {
        #$metaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><item id="-1" parentID="-1" restricted="true"><dc:title>' . htmlspecialchars($radio_name) . '</dc:title><upnp:class>object.item.audioItem.audioBroadcast</upnp:class><desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">SA_RINCON65031_</desc></item></DIDL-Lite>';
		// changed 19.07.2022 Cover URL for nextradio
		$metaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><item id="-1" parentID="-1"><upnp:albumArtURI>' . htmlspecialchars($metaData) . '</upnp:albumArtURI><upnp:class>object.item.audioItem.audioBroadcast</upnp:class><dc:title>' . htmlspecialchars($radio_name) . '</dc:title><desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">SA_RINCON65031_</desc></item></DIDL-Lite>';

        $this->SetAVTransportURI($radio, $metaData);
    }


	/** 
	 * Sleeptimer in Minutes (0-59) 
	 * 
	 * @params string
	 *
	 * @return: None
	 */  
 
    public function SetSleeptimer($hours, $minutes, $seconds)
    {
        if ($hours == 0 && $minutes == 0 && $seconds == 0) {
            $sleeptimer = '';
        } else {
            $sleeptimer = $hours . ':' . $minutes . ':' . $seconds;
        }

        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'ConfigureSleepTimer',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($sleeptimer, 'NewSleepTimerDuration')
            ]
        );
    }


	/**
	 * Jump directly to the track
	 *
	 * @param string $track    Number/ID of the track to play in queue
	 *
	 * @return string
	 */
 
    public function SetTrack($track)
    {
        $this->Seek('TRACK_NR', $track);
    }


	/**
	 * Set Treble for Zone
	 *
	 * @param string Treble
	 *
	 * @return: None
	 */	
 
    public function SetTreble($treble)
    {
        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetTreble',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($treble, 'DesiredTreble')
            ]
        );
    }


	/**
	 * Set volume for a player
	 *
	 * @param string $volume          Volume in percent
	 *
	 * @return String
	 */
 
    public function SetVolume($volume, $channel = 'Master')
    {
        $this->processSoapCall(
            '/MediaRenderer/RenderingControl/Control',
            'urn:schemas-upnp-org:service:RenderingControl:1',
            'SetVolume',
            [
                new SoapParam('0', 'InstanceID'),
                new SoapParam($channel, 'Channel'),
                new SoapParam($volume, 'DesiredVolume')
            ]
        );
    }


	/**
	 * Stop playing
	 *
	 * @return Void
	 */
 
    public function Stop()
    {
        $this->processSoapCall(
            '/MediaRenderer/AVTransport/Control',
            'urn:schemas-upnp-org:service:AVTransport:1',
            'Stop',
            [
                new SoapParam('0', 'InstanceID')
            ]
        );
    }


	/**
	 * Get information of devices inputs
	 *
	 * @return Array
	 *
	 */
   
    public function GetAudioInputAttributes()
    {
		$returnContent = $this->processSoapCall(
				'/AudioIn/Control',
				'urn:schemas-upnp-org:service:AudioIn:1',
				'GetAudioInputAttributes',
				[
					new SoapParam('0', 'InstanceID')
				]
			);
			$xmlParser = xml_parser_create("UTF-8");
			xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
			xml_parse_into_struct($xmlParser, $returnContent, $vals, $index);
			xml_parser_free($xmlParser);

			$AudioInReturn = Array();

			$key="CurrentName";
			if ( isset($index[strtoupper($key)][0]) and isset($vals[ $index[strtoupper($key)][0] ]['value'])) {
			$AudioInReturn[$key] = $vals[ $index[strtoupper($key)][0] ]['value'];
			} else { 
			$AudioInReturn[$key] = ""; 
		}

			$key="CurrentIcon";
			if ( isset($index[strtoupper($key)][0]) and isset($vals[ $index[strtoupper($key)][0] ]['value'])) {
			$AudioInReturn[$key] = $vals[ $index[strtoupper($key)][0] ]['value'];
			} else { 
			$AudioInReturn[$key] = ""; 
		}
      return $AudioInReturn; //Assoziatives Array
    }


	/**
		Example output: GetZoneAttributes():
		
	Array
	(
		[CurrentZoneName] => Schlafen
		[CurrentIcon] => x-rincon-roomicon:living
		[CurrentConfiguration] => 1
		[CurrentTargetRoomName] => 
	)
	 */

	/**
	 * Reads Zone Attributes
	 *
	 * @return Array
	 *
	**/ 

    public function GetZoneAttributes()
   {
		$returnContent = $this->processSoapCall(
				'/DeviceProperties/Control',
				'urn:schemas-upnp-org:service:DeviceProperties:1',
				'GetZoneAttributes',
				[
					new SoapParam('0', 'InstanceID')
				]
			);
        return $returnContent;
    }
	
	
	/**
	 * Check if Player Update is available
	 *
	 * @return Array
	 *
	**/ 

    public function CheckForUpdate()
   {
		$returnContent = $this->processSoapCall(
				'/ZoneGroupTopology/Control',
				'urn:schemas-upnp-org:service:ZoneGroupTopology:1',
				'CheckForUpdate',
				[
					new SoapParam('All', 'UpdateType'),
					new SoapParam('0', 'CachedOnly'),
					new SoapParam('', 'Version')
				]
			);
			
			$xmlParser = xml_parser_create("UTF-8");
			xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
			xml_parse_into_struct($xmlParser, $returnContent, $vals, $index);
			xml_parser_free($xmlParser);
			#print_r($vals);
			
			$return = array();
			$return['updateitem'] = $index['UPDATEITEM'][0];
			$return['type'] = $vals[0]['attributes']['TYPE'];
			$return['swgen'] = $vals[0]['attributes']['SWGEN'];
			$return['version'] = str_replace("v", "", substr($vals[0]['attributes']['UPDATEURL'], strpos($vals[0]['attributes']['UPDATEURL'], 'v'), 5));
			$return['build'] = $vals[0]['attributes']['VERSION'];
			$return['updateurl'] = $vals[0]['attributes']['UPDATEURL'];
			$return['downloadsize'] = $vals[0]['attributes']['DOWNLOADSIZE'];

			return $return;
    }
	
	
	
	/**
	 * Execute Player Software Update
	 *
	 * @param (string) Update URL
	 *
	 *
	**/ 

    public function BeginSoftwareUpdate($UpdateURL, $ui4="ui4", $ExtraOptions="")
   {
		$returnContent = $this->processSoapCall(
				'/ZoneGroupTopology/Control',
				'urn:schemas-upnp-org:service:ZoneGroupTopology:1',
				'BeginSoftwareUpdate',
				[
					new SoapParam($UpdateURL, 'UpdateURL'),
					new SoapParam($ui4, 'Flags'),
					new SoapParam($ExtraOptions, 'ExtraOptions')
				]
        );
		return $returnContent;
    }


	/**
		Example output: GetZoneInfo():
		
	Array
	(
		 [SerialNumber] => 00-zz-58-32-yy-xx:5
		 [SoftwareVersion] => 15.4-442xx
		 [DisplaySoftwareVersion] => 3.5.x
		 [HardwareVersion] => 1.16.3.z-y
		 [IPAddress] => yyy.168.z.xxx
		 [MACAddress] => 00:zz:58:32:yy:xx
		 [CopyrightInfo] => ? 2004-2007 Sonos, Inc. All Rights Reserved.
		 [ExtraInfo] => OTP: 1.1.x(1-yy-3-0.x)
		 [HTAudioIn] => 0
		 [Flags] => 0
	)
	 * @return Array
	 *
	 */ 
 
 public function GetZoneInfo()
   {
	   $returnContent = $this->processSoapCall(
				'/DeviceProperties/Control',
				'urn:schemas-upnp-org:service:DeviceProperties:1',
				'GetZoneInfo',
				[
					new SoapParam('0', 'InstanceID')
				]
			);
        return $returnContent;
   }
 
 

	/**
	 * Set the state of the white LED
	 *
	 * @param string $state             true||false value or On / Off
	 *
	 * @return Boolean
	 */
 
    public function SetLEDState($state)
    {

	   if($state=="On")   { 
		$state = "On"; 
	   } else {   
		if($state=="Off")   { 
			$state = "Off"; 
		} else {
				if($state)   { 
			$state = "On"; 
			} else { 
			$state = "Off"; 
			}
			}
	   }
		return (bool)$this->processSoapCall(
				'/DeviceProperties/Control',
				'urn:schemas-upnp-org:service:DeviceProperties:1',
				'SetLEDState',
				[
					new SoapParam($state, 'DesiredLEDState')
				]
        );
    }


	/**
	 * Get the state of the white LED
	 *
	 * @return Boolean
	 *
	 */
 
    public function GetLEDState() // added br
   {

		$content = $this->processSoapCall(
				'/DeviceProperties/Control',
				'urn:schemas-upnp-org:service:DeviceProperties:1',
				'GetLEDState',
				[
					new SoapParam('0', 'InstanceID')
				]
			);
		if ($content == "On")   { 
			return(true); 
		} else {
			return(false);
		}
    }


	 /**
	 * Add a Member to a existing ZoneGroup
	 
	 * @param string $MemberID             LocalUUID/ Rincon of Player to add
	 *
	 * @return Array
	 *
	 */
 
	public function AddMember($MemberID)
    {
		$returnContent = $this->processSoapCall(
				'/GroupManagement/Control',
				'urn:schemas-upnp-org:service:GroupManagement:1',
				'AddMember',
				[
					new SoapParam($MemberID, 'MemberID')
				]
			);
			$xmlParser = xml_parser_create("UTF-8");
			xml_parser_set_option($xmlParser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
			xml_parse_into_struct($xmlParser, $returnContent, $vals, $index);
			xml_parser_free($xmlParser);

			$ZoneAttributes = Array();

			$key="CurrentTransportSettings";
			if ( isset($index[strtoupper($key)][0]) and isset($vals[ $index[strtoupper($key)][0] ]['value']))   {
			$ZoneAttributes[$key] = $vals[ $index[strtoupper($key)][0] ]['value'];
			} else { 
			$ZoneAttributes[$key] = ""; 
		}

			$key="GroupUUIDJoined";
			if ( isset($index[strtoupper($key)][0]) and isset($vals[ $index[strtoupper($key)][0] ]['value']))   {
			$ZoneAttributes[$key] = $vals[ $index[strtoupper($key)][0] ]['value'];
			} else { 
			$ZoneAttributes[$key] = ""; 
		}
		  return ($ZoneAttributes); // Assoziatives Array
    }


	/**
	 * Remove a Member from an existing ZoneGroup
	 *
	 * @param string $MemberID             LocalUUID/ Rincon of Player to remove
	 *
	 * @return Sring
	 *
	 */
	public function RemoveMember($MemberID)
    {
		return $this->processSoapCall(
				'/GroupManagement/Control',
				'urn:schemas-upnp-org:service:GroupManagement:1',
				'RemoveMember',
				[
					new SoapParam($MemberID, 'MemberID')
				]
			);	
     }


	/**
	 * Get info on actual crossfade mode
	 *
	 * @return Boolean
	 */
 
	public function GetCrossfadeMode() // added br
    {
		return (bool) $this->processSoapCall(
				'/MediaRenderer/AVTransport/Control',
				'urn:schemas-upnp-org:service:AVTransport:1',
				'GetCrossfadeMode',
				[
					new SoapParam('0', 'InstanceID')
				]
        );	
    }
	

	/**
	 * Get info on actual GroupMute
	 *
	 * @return Boolean
	 */

	public function GetGroupMute()  
	{ 
	  return (bool) $this->processSoapCall(
   	    '/MediaRenderer/GroupRenderingControl/Control',
   	    'urn:schemas-upnp-org:service:GroupRenderingControl:1',
            'GetGroupMute',
            [
                new SoapParam('0', 'InstanceID')
            ]
          );	
	}


	/**
	 * Set Group Mute
	 *
	 * @param: string
	 *
	 * @return string
	 */
	public function SetGroupMute($mute) 
	{ 
        if($mute) { $mute = "1"; } else { $mute = "0"; }
 
		return (int) $this->processSoapCall(
			'/MediaRenderer/GroupRenderingControl/Control',
			'urn:schemas-upnp-org:service:GroupRenderingControl:1',
				'SetGroupMute',
				[	
					new SoapParam('0', 'InstanceID'),
					new SoapParam($mute, 'DesiredMute')
				]
          );
	}


	/**
	 * Set Group Volume
	 *
	 * @param: string
	 *
	 * @return
	 */

	public function SetGroupVolume($volume) 
	{ 
		if($volume<'10') { 	$length = '312'; } else { $length = '313'; }
		
		$this->processSoapCall(
			'/MediaRenderer/GroupRenderingControl/Control',
			'urn:schemas-upnp-org:service:GroupRenderingControl:1',
				'SetGroupVolume',
				[	
					new SoapParam('0', 'InstanceID'),
					new SoapParam($volume, 'DesiredVolume')
				]
			  );
	}


	/**
	 * Get info on actual Group Volume
	 *
	 * @return string
	 */
	public function GetGroupVolume() 
	{ 

		return (int) $this->processSoapCall(
			'/MediaRenderer/GroupRenderingControl/Control',
			'urn:schemas-upnp-org:service:GroupRenderingControl:1',
				'GetGroupVolume',
				[	
					new SoapParam('0', 'InstanceID')
				]
			  );
	}


	/**
	 * Get info on actual Snapshot Group Volume
	 *
	 * @return string
	 */
	public function SnapshotGroupVolume() 
	{ 

		return (int) $this->processSoapCall(
			'/MediaRenderer/GroupRenderingControl/Control',
			'urn:schemas-upnp-org:service:GroupRenderingControl:1',
				'SnapshotGroupVolume',
				[	
					new SoapParam('0', 'InstanceID')
				]
			  );
	}

	
	/**
	 * Set Relative Group Volume
	 *
	 * @param: string
	 *
	 * @return
	 */
	 
	public function SetRelativeGroupVolume($volume) 
	{ 
	
		$this->processSoapCall(
			'/MediaRenderer/GroupRenderingControl/Control',
			'urn:schemas-upnp-org:service:GroupRenderingControl:1',
				'SetRelativeGroupVolume',
				[	
					new SoapParam('0', 'InstanceID'),
					new SoapParam($volume, 'Adjustment')
				]
          );
	}


	/**
	 * Remove Zone from Group
	 *
	 * @param
	 *
	 * @return
	 */
	public function BecomeCoordinatorOfStandaloneGroup()
	{
	
		$this->processSoapCall(
			'/MediaRenderer/AVTransport/Control',
			'urn:schemas-upnp-org:service:AVTransport:1',
				'BecomeCoordinatorOfStandaloneGroup',
				[	
					new SoapParam('0', 'InstanceID')
				]
			  );
	}
 

	/**
	 * Create a Stereo Pair of two matching single zones
	 *
	 * @param string RinconID LEFT and RinconID RIGHT
	 *
	 * @return: None
	 */

	public function CreateStereoPair($ChannelMapSet) 
	{

		$this->processSoapCall(
			'/DeviceProperties/Control',
			'urn:schemas-upnp-org:service:DeviceProperties:1',
				'CreateStereoPair',
				[	
					new SoapParam($ChannelMapSet, 'ChannelMapSet')
				]
			  );
	}


	 /**
	 * Seperate a Stereo Pair in two single zones
	 *
	 * @param string RinconID LEFT and RinconID RIGHT
	 *
	 * @return: None
	 */
	public function SeperateStereoPair($ChannelMapSet) 
	{

		$this->processSoapCall(
			'/DeviceProperties/Control',
			'urn:schemas-upnp-org:service:DeviceProperties:1',
				'SeperateStereoPair',
				[	
					new SoapParam($ChannelMapSet, 'ChannelMapSet')
				]
			  );
	}


	/**
	 * Set for Sonos CONNECT the Volume to fixed or variable
	 * 
	 * @params '0' = variable, '1' = fixed
	 * @return string
	 */ 
 
   public function SetVolumeMode($mode) 
   {
		return $this->processSoapCall(
			'/MediaRenderer/RenderingControl/Control',
			'urn:schemas-upnp-org:service:RenderingControl:1',
				'SetOutputFixed',
				[	
					new SoapParam('0', 'InstanceID'),
					new SoapParam($mode, 'DesiredFixed')
				]
          );
   }



	/**
	 * Get for Sonos CONNECT the Volume mode
	 * 
	 * @params 
	 * @return '0' = variable, '1' = fixed
	 */ 
 
   public function GetVolumeMode($uuid) 
   {
		return (bool) $this->processSoapCall(
			'/MediaRenderer/RenderingControl/Control',
			'urn:schemas-upnp-org:service:RenderingControl:1',
				'GetOutputFixed',
				[	
					new SoapParam('0', 'InstanceID')
				]
          );
   }



	/**
		Example output: ListAlarms():

	Array
	(
		[0] => Array
			(
				[ID] => 27
				[StartTime] => 07:30:00
				[Duration] => 02:00:00
				[Recurrence] => WEEKDAYS
				[Enabled] => 0
				[RoomUUID] => RINCON_347E5C335F6401400
				[ProgramURI] => file:///jffs/settings/savedqueues.rsq#57
				[ProgramMetaData] => Max Ruheobject.container.playlistContainerRINCON_AssociatedZPUDN
				[PlayMode] => SHUFFLE
				[Volume] => 10
				[IncludeLinkedZones] => 0
				[minpastmid] => 440
				[Room] => schlafen
			)
	)
	/**
	 * Returns a list of alarms from device
	 *
	 * @return Array
	 *
	 */
	 
 public function ListAlarms()
    {
		$returnContent = $this->processSoapCall(
   	    '/AlarmClock/Control',
   	    'urn:schemas-upnp-org:service:AlarmClock:1',
            'ListAlarms',
            []
          );
		  
        $xmlr = new SimpleXMLElement($returnContent['CurrentAlarmList']);
        $liste = array();
        for($i=0,$size=count($xmlr);$i<$size;$i++)
        {
			$attr = $xmlr->Alarm[$i]->attributes();
            $liste[$i]['ID'] = (string)$attr['ID'];
            $liste[$i]['StartTime'] = (string)$attr['StartTime'];
            $liste[$i]['Duration'] = (string)$attr['Duration'];
            $liste[$i]['Recurrence'] = (string)$attr['Recurrence'];
            $liste[$i]['Enabled'] = (string)$attr['Enabled'];
            $liste[$i]['RoomUUID'] = (string)$attr['RoomUUID'];
            $liste[$i]['ProgramURI'] = (string)$attr['ProgramURI'];
            $liste[$i]['ProgramMetaData'] = (string)$attr['ProgramMetaData'];
            $liste[$i]['PlayMode'] = (string)$attr['PlayMode'];
            $liste[$i]['Volume'] = (string)$attr['Volume'];
            $liste[$i]['IncludeLinkedZones'] = (string)$attr['IncludeLinkedZones'];
        }
        return $liste;
    }


	/**
	 * Updates an existing alarm
	 *
	 * @param string $id             Id of the Alarm
	 * @param string $startzeit      StartLocalTime
	 * @param string $duration       Duration
	 * @param string $welchetage     Recurrence 
	 * @param string $an             Enabled? (true/false)
	 * @param string $roomid         Room UUID
	 * @param string $programm       ProgramUri
	 * @param string $programmmeta   ProgramMetadata
	 * @param string $playmode       PlayMode
	 * @param string $volume         Volume
	 * @param string $linkedzone     IncludeLinkedZones
	 *
	 * @return Void
	 *
	 */
	  
	public function UpdateAlarm($id, $startzeit, $duration, $welchetage, $an, $roomid, $programm, $programmeta, $playmode, $volume, $linkedzone)
	{
		$this->processSoapCall(
   	    '/AlarmClock/Control',
   	    'urn:schemas-upnp-org:service:AlarmClock:1',
            'UpdateAlarm',
            [	
				new SoapParam($id, 'ID'),
				new SoapParam($startzeit, 'StartLocalTime'),
				new SoapParam($duration, 'Duration'),
				new SoapParam($welchetage, 'Recurrence'),
				new SoapParam($an, 'Enabled'),
				new SoapParam($roomid, 'RoomUUID'),
				#new SoapParam(htmlspecialchars($programm), 'ProgramURI'),
				#new SoapParam(htmlspecialchars($programmeta), 'ProgramMetaData'),
				new SoapParam($programm, 'ProgramURI'),
				new SoapParam($programmeta, 'ProgramMetaData'),
				new SoapParam($playmode, 'PlayMode'),
				new SoapParam($volume, 'Volume'),
				new SoapParam($linkedzone, 'IncludeLinkedZones')
			]
          );
	}
	
	
	/**
	 * Communicates with the Sonos device
	 * 
	 * @params $path, $uri, action, array[Parameter]
	 * @return 
	 */ 
	 
    private function processSoapCall($path, $uri, $action,  array $parameter = [])
    {
        try {
            $client = new SoapClient(null, [
                'location'           => 'http://' . $this->address . ':1400' . $path,
                'uri'                => $uri,
                'trace'              => true,
				'use'              	 => SOAP_LITERAL
            ]);
						
            $result = $client->__soapCall($action, $parameter);
			@Logger::info("REQUEST: " . ($client->__getLastRequest()));
			@Logger::info("RESPONSE: " . ($client->__getLastResponse()));
			#echo "REQUEST:\n " . htmlentities($client->__getLastRequest()) . "\n";
			#echo "RESPONSE:\n " . htmlentities($client->__getLastResponse()) . "\n";
        } catch (Exception $e) {
            $faultstring = $e->faultstring;
            $faultcode = $e->faultcode;
            if (isset($e->detail->UPnPError->errorCode)) {
                $errorCode = $e->detail->UPnPError->errorCode;
				@Logger::error('Error during Soap Call: ' . $faultstring . ' ' . $faultcode . ' ' . $errorCode . ' (' . $this->resolveErrorCode($path, $errorCode) . ')');
                throw new Exception('Error during Soap Call: ' . $faultstring . ' ' . $faultcode . ' ' . $errorCode . ' (' . $this->resolveErrorCode($path, $errorCode) . ')');
            } else {
				@Logger::error('Error during Soap Call: ' . $faultstring . ' ' . $faultcode);
                throw new Exception('Error during Soap Call: ' . $faultstring . ' ' . $faultcode);
            }
        }
		return $result;
    }

    private function resolveErrorCode($path, $errorCode)
    {
        $errorList = [
            '/MediaRenderer/AVTransport/Control'      => [
				'400' => 'ERROR_AV_UPNP_AVT_BAD_REQUEST',
				'401' => 'ERROR_AV_UPNP_AVT_INVALID_ACTION',
				'402' => 'ERROR_AV_UPNP_AVT_INVALID_ARGS',
				'404' => 'ERROR_AV_UPNP_AVT_INVALID_VAR',
				'412' => 'ERROR_AV_UPNP_AVT_PRECONDITION_FAILED',
				'501' => 'ERROR_AV_UPNP_AVT_ACTION_FAILED',
				'600' => 'ERROR_AV_UPNP_AVT_ARGUMENT_VALUE_INVALID',
				'601' => 'ERROR_AV_UPNP_AVT_ARGUMENT_VALUE_OUT_OF_RANGE',
				'602' => 'ERROR_AV_UPNP_AVT_OPTIONAL_ACTION_NOT_IMPLEMENTED',
				'603' => 'ERROR_AV_UPNP_AVT_OUT_OF_MEMORY',
				'604' => 'ERROR_AV_UPNP_AVT_HUMAN_INTERVENTION_REQUIRED',
				'605' => 'ERROR_AV_UPNP_AVT_STRING_ARGUMENT_TOO_LONG',
				'606' => 'ERROR_AV_UPNP_AVT_ACTION_NOT_AUTHORIZED',
				'607' => 'ERROR_AV_UPNP_AVT_SIGNATURE_FAILURE',
				'608' => 'ERROR_AV_UPNP_AVT_SIGNATURE_MISSING',
				'609' => 'ERROR_AV_UPNP_AVT_NOT_ENCRYPTED',
				'610' => 'ERROR_AV_UPNP_AVT_INVALID_SEQUENCE',
				'611' => 'ERROR_AV_UPNP_AVT_INVALID_CONTROL_URL',
				'612' => 'ERROR_AV_UPNP_AVT_NO_SUCH_SESSION',
                '701' => 'ERROR_AV_UPNP_AVT_INVALID_TRANSITION',
                '702' => 'ERROR_AV_UPNP_AVT_NO_CONTENTS',
                '703' => 'ERROR_AV_UPNP_AVT_READ_ERROR',
                '704' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_PLAY_FORMAT',
                '705' => 'ERROR_AV_UPNP_AVT_TRANSPORT_LOCKED',
                '706' => 'ERROR_AV_UPNP_AVT_WRITE_ERROR',
                '707' => 'ERROR_AV_UPNP_AVT_PROTECTED_MEDIA',
                '708' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_REC_FORMAT',
                '709' => 'ERROR_AV_UPNP_AVT_FULL_MEDIA',
                '710' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_SEEK_MODE',
                '711' => 'ERROR_AV_UPNP_AVT_ILLEGAL_SEEK_TARGET',
                '712' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_PLAY_MODE',
                '713' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_REC_QUALITY',
                '714' => 'ERROR_AV_UPNP_AVT_ILLEGAL_MIME',
                '715' => 'ERROR_AV_UPNP_AVT_CONTENT_BUSY',
                '716' => 'ERROR_AV_UPNP_AVT_RESOURCE_NOT_FOUND',
                '717' => 'ERROR_AV_UPNP_AVT_UNSUPPORTED_PLAY_SPEED',
                '718' => 'ERROR_AV_UPNP_AVT_INVALID_INSTANCE_ID'
            ],
            '/MediaRenderer/RenderingControl/Control' => [
                '701' => 'ERROR_AV_UPNP_RC_INVALID_PRESET_NAME',
                '702' => 'ERROR_AV_UPNP_RC_INVALID_INSTANCE_ID'
            ],
            '/MediaServer/ContentDirectory/Control'   => [
                '701' => 'ERROR_AV_UPNP_CD_NO_SUCH_OBJECT',
                '702' => 'ERROR_AV_UPNP_CD_INVALID_CURRENTTAGVALUE',
                '703' => 'ERROR_AV_UPNP_CD_INVALID_NEWTAGVAL UE',
                '704' => 'ERROR_AV_UPNP_CD_REQUIRED_TAG_DELETE',
                '705' => 'ERROR_AV_UPNP_CD_READONLY_TAG_UPDATE',
                '706' => 'ERROR_AV_UPNP_CD_PARAMETER_NUM_MISMATCH',
                '708' => 'ERROR_AV_UPNP_CD_BAD_SEARCH_CRITERIA',
                '709' => 'ERROR_AV_UPNP_CD_BAD_SORT_CRITERIA',
                '710' => 'ERROR_AV_UPNP_CD_NO_SUCH_CONTAINER',
                '711' => 'ERROR_AV_UPNP_CD_RESTRICTED_OBJECT',
                '712' => 'ERROR_AV_UPNP_CD_BAD_METADATA',
                '713' => 'ERROR_AV_UPNP_CD_RESTRICTED_PARENT_OBJECT',
                '714' => 'ERROR_AV_UPNP_CD_NO_SUCH_SOURCE_RESOURCE',
                '715' => 'ERROR_AV_UPNP_CD_SOURCE_RESOURCE_ACCESS_DENIED',
                '716' => 'ERROR_AV_UPNP_CD_TRANSFER_BUSY',
                '717' => 'ERROR_AV_UPNP_CD_NO_SUCH_FILE_TRANSFER',
                '718' => 'ERROR_AV_UPNP_CD_NO_SUCH_DESTINATION_RESOURCE',
                '719' => 'ERROR_AV_UPNP_CD_DESTINATION_RESOURCE_ACCESS_DENIED',
                '720' => 'ERROR_AV_UPNP_CD_REQUEST_FAILED'
            ]
        ];

		if (isset($errorList[$path][$errorCode])) {
			return $errorList[$path][$errorCode];
		} else {
			return 'UNKNOWN';
		}
	}
}


?>