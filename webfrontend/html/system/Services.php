<?php
/**
 * class to control Music Services for Sonos Multiroom System
 *
 * Version: 		1.0
 * Date: 			20.11.2017
 * Auto:    		Oliver Lewald
 * published in: 	http://plugins.loxberry.de/
 *
 **/

/** Available Services to interact with Sonos System:
* Spotify
* Amazon
* Apple
* Napster
*
**/

/** Available functions for each service to interact with Sonos System:
* V1.0
* - SetSpotifyTrack($pl, $trackno)
* - SetSpotifyPlaylist($pl, $rincon)
* - SetSpotifyAlbum($pl, $rincon)
* - SetAmazonPlaylist($pl, $rincon)
* - SetAmazonAlbum($pl, $rincon)
* - SetGooglePlaylist($pl, $rincon)		--> NOT WORKING PROPERLY
* - SetGoogleTrack($pl, $rincon)		--> NOT WORKING PROPERLY
* - SetAppleTrack($pl, $rincon, $trackno)
* - SetApplePlaylist($pl, $rincon)
* - SetAppleAlbum($pl, $rincon)
* - SetNapsterPlaylist($pl, $rincon, $mail)
* - SetNapsterAlbum($pl, $rincon, $mail)
* - SetLocalTrack($pl, $rincon)
**/




class SonosMusicService {
   private $address = "";
   
   public function __construct( $address ) {
      $this->address = $address;
  }


/**
 * Load Spotify track into Queue
 *
 * @param string Spotify URI
 * @param string Track Number of current position in playlist
 *
 * @return String
 */
   
	public function SetSpotifyTrack($pl, $trackno)
   {
	   
$reg = 'SA_RINCON2311_X_#Svc2311-0-Token';   // Region EU
#$reg = 'SA_RINCON3079_X_#Svc3079-0-Token';  // Region US

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"
CONTENT-TYPE: text/xml; charset="utf-8"
HOST: '.$this->address.':1400';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-sonos-spotify:spotify%3atrack%3a'.$pl.'?sid=203&amp;sn=1</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"&gt;&lt;item id="16054235spotify%3atrack%3a'.$pl.'" restricted="true"&gt;&lt;dc:title&gt;&lt;/dc:title&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack&lt;/upnp:class&gt;&lt;desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/"&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>'.$trackno.'</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
/**
 * Load Spotify playist into Queue
 *
 * @param string Spotify URI
 * @param string Rincon ID of requested player
 *
 * @return String
 */
   
   public function SetSpotifyPlaylist($pl)
   {
	  
$reg = 'SA_RINCON2311_X_#Svc2311-0-Token';   // Region EU
#$reg = 'SA_RINCON3079_X_#Svc3079-0-Token';  // Region US
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'cspotify%3auser%3aspotify%3aplaylist%3a'.$pl.'?sid=9&amp;flags=8300&amp;sn=5</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;1006206cspotify%3auser%3aspotify%3aplaylist%3a'.$pl.'&quot; parentID=&quot;10082064spotify%3acategory%3apop&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.playlistContainer&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
	$returnContent = $this->sendPacket($content);
   }
   
 

/**
 * Load Spotify album into Queue
 *
 * @param string Spotify URI
 * @param string Rincon ID of requested player
 *
 * @return String
 */ 
   
      public function SetSpotifyAlbum($pl)
   {
	   
$reg = 'SA_RINCON2311_X_#Svc2311-0-Token';   // Region EU
#$reg = 'SA_RINCON3079_X_#Svc3079-0-Token';  // Region US
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'cspotify%3aalbum%3a'.$pl.'?sid=9&amp;sn=5</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;1004206cspotify%3aalbum%3a'.$pl.'&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;Representing&lt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
/**
 * Load Amazon Playlist into Queue
 *
 * @param string Amazon ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */ 
   
      public function SetAmazonPlaylist($pl)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist?sid=201&amp;sn=8</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist&quot; parentID=&quot;'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.playlistContainer&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   


/**
 * Load Amazon track into Queue
 *
 * @param string Amazon ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */ 
   
      public function SetAmazonTrack($pl1, $pl2)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-sonosapi-hls-static:catalog%2ftracks%2f'.$pl1.'%2f%3fplaylistAsin%3d'.$pl2.'%26playlistType%3dprimePlaylist?sid=201&amp;flags=0&amp;sn=8</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;10030000catalog%2ftracks%2f'.$pl1.'%2f%3fplaylistAsin%3d'.$pl2.'%26playlistType%3dprimePlaylist&quot; parentID=&quot;'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }




   
   
/**
 * Load Amazon Album into Queue
 *
 * @param string Amazon ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */ 
   
      public function SetAmazonAlbum($pl)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'ccatalog%2falbums%2f'.$pl.'%2f%23album_desc?sid=201&amp;sn=8</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'ccatalog%2falbums%2f'.$pl.'%2f%23album_desc&quot; parentID=&quot;'.$rand.'catalog%2fpopular%2falbums%2f%23popular_albums_desc&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
   


      public function SetGoogleTrack($pl)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-sonos-http:A0DvPDnows'.$pl.'.mp3?sid=151&amp;flags=8268&amp;sn=9</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;10030020A0DvPDnows'.$pl.'&quot; parentID=&quot;100e206cf'.$pl.'&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack.#topCharts&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;SA_RINCON38663_X_#Svc38663-0-Token&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }   
   
   
      public function SetGooglePlaylist($pl)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:1004204cxw'.$pl.'?sid=151&amp;flags=8268&amp;sn=9</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;1004204cxw'.$pl.'&quot; parentID=&quot;100d206cKnPYi2AfiTlRxVzJOAD9Vw&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum.#topCharts&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;SA_RINCON38663_X_#Svc38663-0-Token&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   

    public function SetGooglePlaylist_backup($pl)
   {
	   
$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
         <EnqueuedURI>x-rincon-cpcontainer:1004204cxw'.$pl.'?sid=151&amp;flags=8268&amp;sn=9</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;1004204cxw'.$pl.'&quot; parentID=&quot;100d206cKnPYi2AfiTlRxVzJOAD9Vw&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum.#topCharts&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;SA_RINCON38663_X_#Svc38663-0-Token&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
/**
 * Load Apple Album into Queue
 *
 * @param string Apple Album ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */    
 
    public function SetAppleAlbum($pl)
   {
	   
$reg = 'SA_RINCON52231_X_#Svc52231-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'calbum%3a'.$pl.'?sid=204&amp;flags=8300&amp;sn=10</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'calbum%3a'.$pl.'&quot; parentID=&quot;100d2064albumchart%3a34&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum.#TopItem&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   


/**
 * Load Apple Music Track into Queue
 *
 * @param string Apple Track ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */     
   
    public function SetAppleTrack($pl, $trackno)
   {

$reg = 'SA_RINCON52231_X_#Svc52231-0-Token';
$rand = mt_rand(10000000, 19999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>x-sonos-http:song%3a'.$pl.'.mp4?sid=204&amp;flags=8224&amp;sn=10</EnqueuedURI>
		 <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'song%3a'.$pl.'&quot; parentID=&quot;100e206csongchart%3a34&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack.#TopItem&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>'.$trackno.'</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
    
   

/**
 * Load Apple Music Playlist into Queue
 *
 * @param string Apple Album ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */     
   
    public function SetApplePlaylist($pl)
   {
	   
$reg = 'SA_RINCON52231_X_#Svc52231-0-Token';
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'cplaylist%3apl.'.$pl.'?sid=204&amp;flags=8300&amp;sn=10</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'calbum%3a'.$pl.'&quot; parentID=&quot;100d2064albumchart%3a34&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum.#TopItem&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
/**
 * Load Napster Album into Queue
 *
 * @param string Napster Album ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */    
 
    public function SetNapsterAlbum($pl, $mail)
   {
	   
$reg = 'SA_RINCON51975_'.$mail;
$rand = mt_rand(100000, 199999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'ecexplore%3aalbum%3a%3aAlb.'.$pl.'?sid=203&amp;flags=8428&amp;sn=1</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'ecexplore%3aalbum%3a%3aAlb.'.$pl.'&quot; parentID=&quot;'.$rand.'root%3anewreleases&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.album.musicAlbum&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   
   
   
/**
 * Load Napster Playlist into Queue
 *
 * @param string Napster Playlist ID
 * @param string Rincon ID of requested player
 *
 * @return String
 */    
 
    public function SetNapsterPlaylist($pl, $mail)
   {
	   
$reg = 'SA_RINCON51975_'.$mail;
$rand = mt_rand(1000000, 1999999);

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>x-rincon-cpcontainer:'.$rand.'cexplore%3aplaylist%3a%3app.'.$pl.'?sid=203&amp;flags=8428&amp;sn=1</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'cexplore%3aplaylist%3a%3app.'.$pl.'&quot; parentID=&quot;'.$rand.'explore%3atag%3aplaylists%3a%3atag.156763213&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.playlistContainer&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   

/**
 * Load Track from local network into Queue
 *
 * @param string track
 * @param string Rincon ID of requested player
 *
 * @return String
 */    
 
    public function SetLocalTrack($pl, $file)
   {
	   

$header='POST /MediaRenderer/AVTransport/Control HTTP/1.1
CONNECTION: close
CCEPT-ENCODING: gzip
HOST: '.$this->address.':1400
USER-AGENT: Linux UPnP/1.0 Sonos/39.2-47040 (WDCR:Microsoft Windows NT 6.1.7601 Service Pack 1)
CONTENT-TYPE: text/xml; charset="utf-8"
SOAPACTION: "urn:schemas-upnp-org:service:AVTransport:1#AddURIToQueue"';


$xml='<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
   <s:Body>
      <u:AddURIToQueue xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
         <InstanceID>0</InstanceID>
		 <EnqueuedURI>'.$pl.'</EnqueuedURI>
         <EnqueuedURIMetaData>&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$file.'&quot; parentID=&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;RINCON_AssociatedZPUDN&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</EnqueuedURIMetaData>
         <DesiredFirstTrackNumberEnqueued>0</DesiredFirstTrackNumberEnqueued>
         <EnqueueAsNext>1</EnqueueAsNext>
      </u:AddURIToQueue>
   </s:Body>
</s:Envelope>';
$content=$header . '
Content-Length: '. strlen($xml) .'

'. $xml;
	
    $returnContent = $this->sendPacket($content);
   }
   
   

 
   
/***************************************************************************
            Helper / sendPacket
***************************************************************************/

/** 
 * XMLsendPacket 
 * 
 * - <b>NOTE:</b> This function does send of a soap query and DOES NOT filter a xml answer 
 * - <b>Returns:</b> Answer as XML 
 * 
 * @return Array 
 */ 
    private function XMLsendPacket($content) 
    { 
        $fp = fsockopen($this->address, 1400 /* Port */, $errno, $errstr, 10); 
        if (!$fp) 
            throw new Exception("Error opening socket: ".$errstr." (".$errno.")"); 
             
        fputs ($fp, $content); 
        $ret = ""; 
        $buffer = ""; 
        while (!feof($fp)) { 
            $ret.= fgets($fp,128); 
        } 

        fclose($fp); 

        if(strpos($ret, "200 OK") === false) 
            throw new Exception("Error sending command: ".$ret); 

        $array = preg_split("/\r\n/", $ret); 

        $result = ""; 
        if(strpos($ret, "TRANSFER-ENCODING: chunked") === false){ 
            $result = $array[count($array) - 1]; 
        }else{ 
            $chunksStarted = false; 
            $content       = false; 
            foreach($array as $key => $value){ 
                if($value == ""){ 
                    $chunksStarted = true; 
                    continue; 
                } 
                if($chunksStarted === false) 
                    continue; 
                if($content === false){ 
                    if( $value === 0) 
                        break; 
                    $content = true; 
                    continue; 
                } 
                $result = $result.$value; 
                $content = false; 
            } 
        }  
         
        return $result; 
    } 

/** 
 * sendPacket - communicate with the device 
 * 
 * - <b>NOTE:</b> This function does send of a soap query and may filter xml answers 
 * - <b>Returns:</b> Answer 
 * 
 * @return Array 
 */ 

    private function sendPacket($content) 
    { 
        $fp = fsockopen($this->address, 1400 /* Port */, $errno, $errstr, 10); 
        if (!$fp) 
            throw new Exception("Error opening socket: ".$errstr." (".$errno.")"); 

        fputs ($fp, $content); 
        $ret = ""; 
        while (!feof($fp)) { 
            $ret.= fgetss($fp,128); 
        } 
        fclose($fp); 

        if(strpos($ret, "200 OK") === false) 
            throw new Exception("Error sending command: ".$ret); 
         
        $array = preg_split("/\r\n/", $ret); 

        $result = ""; 
        if(strpos($ret, "TRANSFER-ENCODING: chunked") === false){ 
            $result = $array[count($array) - 1]; 
        }else{ 
            $chunksStarted = false; 
            $content       = false; 
            foreach($array as $key => $value){ 
                if($value == ""){ 
                    $chunksStarted = true; 
                    continue; 
                } 
                if($chunksStarted === false) 
                    continue; 
                if($content === false){ 
                    if( $value === 0) 
                        break; 
                    $content = true; 
                    continue; 
                } 
                $result = $result.$value; 
                $content = false; 
            } 
        }  
         
        return $result; 
    }  




}
?>
