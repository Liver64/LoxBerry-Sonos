<?php
/*---------------------------------------------------------------------------/
	
File:  
	Desc     : PHP Classes to Control Sonos PLAY:1 
	Date     : 2015-01-14T18:46:59+01:00
	Version  : 1.00.45
	Publisher: (c)2015 Xaver Bauer 
	Contact  : x.bauer@tier-freunde.net

Device:
	Device Type  : urn:schemas-upnp-org:device:ZonePlayer:1
	URL 		 : http://xxx.xxx.xxx.56:1400/xml/device_description.xml	
	Friendly Name: xxx.xxx.xxx.56 - Sonos PLAY:1
	Manufacturer : Sonos, Inc.
	URL 		 : http://www.sonos.com
	Model        : Sonos PLAY:1
	Name 		 : Sonos PLAY:1
	Number 		 : S1
	URL 		 : http://www.sonos.com/products/zoneplayers/S1

/*--------------------------------------------------------------------------*/
/*##########################################################################/
/*  Class  : SonosUpnpDevice 
/*  Desc   : Master Class to Controll Device 
/*	Vars   :
/*  private _SERVICES  : (object) Holder for all Service Classes
/*  private _DEVICES   : (object) Holder for all Service Classes
/*  private _IP        : (string) IP Adress from Device
/*  private _PORT      : (int)    Port from Device
/*##########################################################################*/
class SonosUpnpDevice {
    private $_SERVICES=null;
    private $_DEVICES=null;
    private $_IP='';
    private $_PORT=1400;
    /***************************************************************************
    /* Funktion : __construct
    /* 
    /*  Benoetigt:
    /*    @url (string)  Device Url eg. '192.168.1.1:1400'
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function __construct($url){
        $p=parse_url($url);
        $this->_IP=(isSet($p['host']))?$p['host']:$url;
        $this->_PORT=(isSet($p['port']))?$p['port']:1400;
        $this->_SERVICES=new stdClass();
        $this->_DEVICES=new stdClass();
        $this->_SERVICES->AlarmClock=new SonosAlarmClock($this);
        $this->_SERVICES->MusicServices=new SonosMusicServices($this);
        $this->_SERVICES->DeviceProperties=new SonosDeviceProperties($this);
        $this->_SERVICES->SystemProperties=new SonosSystemProperties($this);
        $this->_SERVICES->ZoneGroupTopology=new SonosZoneGroupTopology($this);
        $this->_SERVICES->GroupManagement=new SonosGroupManagement($this);
        $this->_SERVICES->QPlay=new SonosQPlay($this);
        $this->_DEVICES->ContentDirectory=new SonosContentDirectory($this);
        $this->_DEVICES->MediaServerConnectionManager=new SonosConnectionManager($this,'urn:schemas-upnp-org:service:ConnectionManager:1','/MediaServer/ConnectionManager/Control','/MediaServer/ConnectionManager/Event');
        $this->_DEVICES->RenderingControl=new SonosRenderingControl($this);
        $this->_DEVICES->MediaRendererConnectionManager=new SonosConnectionManager($this,'urn:schemas-upnp-org:service:ConnectionManager:1','/MediaRenderer/ConnectionManager/Control','/MediaRenderer/ConnectionManager/Event');
        $this->_DEVICES->AVTransport=new SonosAVTransport($this);
        $this->_DEVICES->Queue=new SonosQueue($this);
        $this->_DEVICES->GroupRenderingControl=new SonosGroupRenderingControl($this);
    }
    /***************************************************************************
    /* Funktion : GetIcon
    /* 
    /*  Benoetigt:
    /*    @IconNr (int)
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*    @width  (int)
    /*    @height (int)
    /*    @url (string)
    /*
    /****************************************************************************/
    function GetIcon($id) {
        switch($id){
            case 0 : return array('width'=>48,'height'=>48,'url'=>'http://192.168.112.56:1400/img/icon-S1.png');break;
        }
        return array('width'=>0,'height'=>0,'url'=>'');
    }
    /***************************************************************************
    /* Funktion : IconCount
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*    @count (int) => The Numbers of Icons Avail
    /*
    /****************************************************************************/
    function IconCount() { return 1;}
    /***************************************************************************
    /* Funktion : Upnp
    /* 
    /*  Benoetigt:
    /*    @url (string)
    /*    @SOAP_service (string)
    /*    @SOAP_action (string)
    /*    @SOAP_arguments (sting) [Optional]
    /*    @XML_filter (string|stringlist|array of strings) [Optional]
    /*
    /*  Liefert als Ergebnis:
    /*    @result (string|array) => The XML Soap Result
    /*
    /****************************************************************************/
    public function Upnp($url,$SOAP_service,$SOAP_action,$SOAP_arguments = '',$XML_filter = ''){
        $POST_xml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">';
        $POST_xml .= '<s:Body>';
        $POST_xml .= '<u:'.$SOAP_action.' xmlns:u="'.$SOAP_service.'">';
        $POST_xml .= $SOAP_arguments;
        $POST_xml .= '</u:'.$SOAP_action.'>';
        $POST_xml .= '</s:Body>';
        $POST_xml .= '</s:Envelope>';
        $POST_url = $this->_IP.":".$this->_PORT.$url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_URL, $POST_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml", "SOAPAction: ".$SOAP_service."#".$SOAP_action));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_xml);
        $r = curl_exec($ch);
        curl_close($ch);
        if ($XML_filter != '')
            return $this->Filter($r,$XML_filter);
        else
            return $r;
    }
    /***************************************************************************
    /* Funktion : Filter
    /* 
    /*  Benoetigt:
    /*    @subject (string)
    /*    @pattern (string|stringlist|array of strings)
    /*
    /*  Liefert als Ergebnis:
    /*    @result (array|variant) => Array format FilterPattern=>Value
    /*
    /****************************************************************************/
    public function Filter($subject,$pattern){
        $multi=is_array($pattern);
        if(!$multi){
            $pattern=explode(',',$pattern);
            $multi=(count($pattern)>1);
        }	
        foreach($pattern as $pat){
            if(!$pat)continue;
            preg_match('/\<'.$pat.'\>(.+)\<\/'.$pat.'\>/',$subject,$matches);
            if($multi)$n[$pat]=(isSet($matches[1]))?$matches[1]:false;
            else return (isSet($matches[1]))?$matches[1]:false;
        }	
        return $n;
    }
    /***************************************************************************
    /* Funktion : GetServiceNames
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*    @result (array) => Namen der vorhandenen Services
    /*
    /****************************************************************************/
    public function GetServiceNames(){
        foreach($this->_SERVICES as $fn=>$tmp)if(substr($fn,0,1)!='_')$n[]=$fn;
        foreach($this->_DEVICES as $fn=>$tmp)if(substr($fn,0,1)!='_')$n[]=$fn;
        return $n;
    }
    /***************************************************************************
    /* Funktion : GetServiceFunctionNames
    /* 
    /*  Benoetigt:
    /*    @ServiceName (string)
    /*
    /*  Liefert als Ergebnis:
    /*    @result (array) => Namen der vorhandenen Service Funktionen
    /*
    /****************************************************************************/
    public function GetServiceFunctionNames($ServiceName){
        if(isSet($this->_SERVICES->$ServiceName)){
            $p=&$this->_SERVICES->$ServiceName;
        }else if(isSet($this->_DEVICES->$ServiceName)){
            $p=&$this->_DEVICES->$ServiceName;
        }else throw new Exception('Unbekanner Service-Name '.$ServiceName.' !!!');
        foreach(get_class_methods($p) as $fn)if(substr($fn,0,1)!='_')$n[]=$fn;
        return $n;	
    }
    /***************************************************************************
    /* Funktion : CallService
    /* 
    /*  Benoetigt:
    /*    @ServiceName (string)
    /*    @FunctionName (string)
    /*    @Arguments (string,array) [Optional] Funktions Parameter
    /*
    /*  Liefert als Ergebnis:
    /*    @result (array|variant) => siehe Funktion
    /*
    /****************************************************************************/
    public function CallService($ServiceName, $FunctionName, $Arguments=null){
        if(is_object($ServiceName))$p=$ServiceName;
        else if(isSet($this->_SERVICES->$ServiceName)){
            $p=&$this->_SERVICES->$ServiceName;
        }else if(isSet($this->_DEVICES->$ServiceName)){
            $p=&$this->_DEVICES->$ServiceName;
        }else throw new Exception('Unbekanner Service-Name '.$ServiceName.' !!!');
        if(!method_exists($p,$FunctionName)) throw new Exception('Unbekannter Funktions-Name '.$FunctionName.' !!! Service:'.$ServiceName);
        if(!is_null($Arguments)){
            $a=&$Arguments;
            if (!is_array($a))$a=Array($a);
            switch(count($a)){
                case 1: return $p->$FunctionName($a[0]);break;
                case 2: return $p->$FunctionName($a[0],$a[1]);break;
                case 3: return $p->$FunctionName($a[0],$a[1],$a[2]);break;
                case 4: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3]);break;
                case 5: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4]);break;
                case 6: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5]);break;
                case 7: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6]);break;
                case 8: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7]);break;
                case 9: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8]);break;
                case 10: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8],$a[9]);break;
                case 11: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8],$a[9],$a[10]);break;
                case 12: return $p->$FunctionName($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8],$a[9],$a[10],$a[11]);break;
                default: return $p->$FunctionName();
            }
        }else return $p->$FunctionName();
    }
    /***************************************************************************
    /* Funktion : __call
    /* 
    /*  Benoetigt:
    /*    @FunctionName (string)
    /*    @arguments (array)
    /*
    /*  Liefert als Ergebnis:
    /*    @result (variant) => siehe aufzurufende Funktion
    /*
    /****************************************************************************/
    public function __call($FunctionName, $arguments){
        if(!$p=$this->_ServiceObjectByFunctionName($FunctionName))
            throw new Exception('Unbekannte Funktion '.$FunctionName.' !!!');
        return $this->CallService($p,$FunctionName, $arguments);
    }
    /***************************************************************************
    /* Funktion : _ServiceObjectByFunctionName
    /* 
    /*  Benoetigt:
    /*    @FunctionName (string)
    /*
    /*  Liefert als Ergebnis:
    /*    @result (function||null) ServiceObject mit der gusuchten Function
    /*
    /****************************************************************************/
    private function _ServiceObjectByFunctionName($FunctionName){
        foreach($this->_SERVICES as $fn=>$tmp)if(method_exists($this->_SERVICES->$fn,$FunctionName)){return $this->_SERVICES->$fn;}
        foreach($this->_DEVICES as $fn=>$tmp)if(method_exists($this->_DEVICES->$fn,$FunctionName)){return $this->_DEVICES->$fn;}
        return false;
    }
    /***************************************************************************
    /* Funktion : sendPacket
    /* 
    /*  Benoetigt:
    /*    @content (string)
    /*
    /*  Liefert als Ergebnis:
    /*    @result (array)
    /*
    /****************************************************************************/
    public function sendPacket( $content ){
        $fp = fsockopen($this->_IP, $this->_PORT, $errno, $errstr, 10);
        if (!$fp)throw new Exception("Error opening socket: ".$errstr." (".$errno.")");
            fputs ($fp,$content);$ret = "";
            while (!feof($fp))$ret.= fgets($fp,128); // filters xml answer
            fclose($fp);
        if(strpos($ret, "200 OK") === false)throw new Exception("Error sending command: ".$ret);
        foreach(preg_split("/\n/", $ret) as $v)if(trim($v)&&(strpos($v,"200 OK")===false))$array[]=trim($v);
        return $array;
    }
    /***************************************************************************
    /* Funktion : GetBaseUrl
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*    @result (string)
    /*
    /****************************************************************************/
    public function GetBaseUrl(){ 
        return $this->_IP.':'.$this->_PORT;
    }
}
/*##########################################################################/
/*  Class  : SonosUpnpClass 
/*  Desc   : Basis Class for Services
/*	Vars   :
/*  private SERVICE     : (string) Service URN
/*  private SERVICEURL  : (string) Path to Service Control
/*  private EVENTURL    : (string) Path to Event Control
/*  public  BASE        : (Object) Points to MasterClass
/*##########################################################################*/
class SonosUpnpClass {
    protected $SERVICE="";
    protected $SERVICEURL="";
    protected $EVENTURL="";
    var $BASE=null;
    /***************************************************************************
    /* Funktion : __construct
    /* 
    /*  Benoetigt:
    /*    @BASE (object) Referenz of MasterClass
    /*    @SERVICE (string) [Optional]
    /*    @SERVICEURL (string) [Optional]
    /*    @EVENTURL (string) [Optional]
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function __construct($BASE, $SERVICE="", $SERVICEURL="", $EVENTURL=""){
        $this->BASE=$BASE;
        if($SERVICE)$this->SERVICE=$SERVICE;
        if($SERVICEURL)$this->SERVICEURL=$SERVICEURL;
        if($EVENTURL)$this->EVENTURL=$EVENTURL;
    }
    /***************************************************************************
    /* Funktion : RegisterEventCallback
    /* 
    /*  Benoetigt:
    /*    @callback_url (string) Url die bei Ereignissen aufgerufen wird
    /*    @timeout (int) Gueltigkeitsdauer der CallbackUrl
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*    @SID (string)
    /*    @TIMEOUT (int)
    /*    @Server (string)
    /*
    /****************************************************************************/
    public function RegisterEventCallback($callback_url,$timeout=300){
        if(!$this->EVENTURL)return false;	
        $content='SUBSCRIBE '.$this->EVENTURL.' HTTP/1.1
HOST: '.$this->BASE->GetBaseUrl().'
CALLBACK: <'.$callback_url.'>
NT: upnp:event
TIMEOUT: Second-'.$timeout.'
Content-Length: 0

';
print_r($content);
        $a=$this->BASE->sendPacket($content);$res=false;
        if($a)foreach($a as $r){$m=explode(':',$r);if(isSet($m[1])){$b=array_shift($m);$res[$b]=implode(':',$m);}}
        return $res;
    }
    /***************************************************************************
    /* Funktion : UnRegisterEventCallback
    /* 
    /*  Benoetigt:
    /*    ?
    /*
    /*  Liefert als Ergebnis:
    /*    ?
    /*
    /****************************************************************************/
    public function UnRegisterEventCallback(){ 
        if(!$this->EVENTURL)return false;	
        $content='UNSUBSCRIBE '.$this->EVENTURL.' HTTP/1.1
HOST: '.$this->BASE->GetBaseUrl().'
Content-Length: 0

';
        return $this->BASE->sendPacket($content);
    }
}
/*##########################################################################*/
/*  Class  : AlarmClock 
/*  Service: urn:schemas-upnp-org:service:AlarmClock:1
/*	     Id: urn:upnp-org:serviceId:AlarmClock 
/*##########################################################################*/
class SonosAlarmClock extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:AlarmClock:1';
    protected $SERVICEURL='/AlarmClock/Control';
    protected $EVENTURL='/AlarmClock/Event';
    /***************************************************************************
    /* Funktion : SetFormat
    /* 
    /*  Benoetigt:
    /*          @DesiredTimeFormat (string) 
    /*          @DesiredDateFormat (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetFormat($DesiredTimeFormat, $DesiredDateFormat){
        $args="<DesiredTimeFormat>$DesiredTimeFormat</DesiredTimeFormat><DesiredDateFormat>$DesiredDateFormat</DesiredDateFormat>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetFormat',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetFormat
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentTimeFormat (string) 
    /*          @CurrentDateFormat (string) 
    /*
    /****************************************************************************/
    public function GetFormat(){
        $args="";
        $filter="CurrentTimeFormat,CurrentDateFormat";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetFormat',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetTimeZone
    /* 
    /*  Benoetigt:
    /*          @Index (i4) 
    /*          @AutoAdjustDst (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetTimeZone($Index, $AutoAdjustDst){
        $args="<Index>$Index</Index><AutoAdjustDst>$AutoAdjustDst</AutoAdjustDst>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetTimeZone',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTimeZone
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Index (i4) 
    /*          @AutoAdjustDst (boolean) 
    /*
    /****************************************************************************/
    public function GetTimeZone(){
        $args="";
        $filter="Index,AutoAdjustDst";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTimeZone',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTimeZoneAndRule
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Index (i4) 
    /*          @AutoAdjustDst (boolean) 
    /*          @CurrentTimeZone (string) 
    /*
    /****************************************************************************/
    public function GetTimeZoneAndRule(){
        $args="";
        $filter="Index,AutoAdjustDst,CurrentTimeZone";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTimeZoneAndRule',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTimeZoneRule
    /* 
    /*  Benoetigt:
    /*          @Index (i4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @TimeZone (string) 
    /*
    /****************************************************************************/
    public function GetTimeZoneRule($Index){
        $args="<Index>$Index</Index>";
        $filter="TimeZone";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTimeZoneRule',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetTimeServer
    /* 
    /*  Benoetigt:
    /*          @DesiredTimeServer (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetTimeServer($DesiredTimeServer){
        $args="<DesiredTimeServer>$DesiredTimeServer</DesiredTimeServer>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetTimeServer',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTimeServer
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentTimeServer (string) 
    /*
    /****************************************************************************/
    public function GetTimeServer(){
        $args="";
        $filter="CurrentTimeServer";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTimeServer',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetTimeNow
    /* 
    /*  Benoetigt:
    /*          @DesiredTime (string) 
    /*          @TimeZoneForDesiredTime (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetTimeNow($DesiredTime, $TimeZoneForDesiredTime){
        $args="<DesiredTime>$DesiredTime</DesiredTime><TimeZoneForDesiredTime>$TimeZoneForDesiredTime</TimeZoneForDesiredTime>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetTimeNow',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetHouseholdTimeAtStamp
    /* 
    /*  Benoetigt:
    /*          @TimeStamp (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @HouseholdUTCTime (string) 
    /*
    /****************************************************************************/
    public function GetHouseholdTimeAtStamp($TimeStamp){
        $args="<TimeStamp>$TimeStamp</TimeStamp>";
        $filter="HouseholdUTCTime";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetHouseholdTimeAtStamp',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTimeNow
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentUTCTime (string) 
    /*          @CurrentLocalTime (string) 
    /*          @CurrentTimeZone (string) 
    /*          @CurrentTimeGeneration (ui4) 
    /*
    /****************************************************************************/
    public function GetTimeNow(){
        $args="";
        $filter="CurrentUTCTime,CurrentLocalTime,CurrentTimeZone,CurrentTimeGeneration";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTimeNow',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : CreateAlarm
    /* 
    /*  Benoetigt:
    /*          @StartLocalTime (string) 
    /*          @Duration (string) 
    /*          @Recurrence (string)  => Auswahl: ONCE|WEEKDAYS|WEEKENDS|DAILY
    /*          @Enabled (boolean) 
    /*          @RoomUUID (string) 
    /*          @ProgramURI (string) 
    /*          @ProgramMetaData (string) 
    /*          @PlayMode (string)  => Auswahl: NORMAL|REPEAT_ALL|SHUFFLE_NOREPEAT|SHUFFLE
    /*          @Volume (ui2) 
    /*          @IncludeLinkedZones (boolean) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AssignedID (ui4) 
    /*
    /****************************************************************************/
    public function CreateAlarm($StartLocalTime, $Duration, $Recurrence, $Enabled, $RoomUUID, $ProgramURI, $ProgramMetaData, $PlayMode, $Volume, $IncludeLinkedZones){
        $args="<StartLocalTime>$StartLocalTime</StartLocalTime><Duration>$Duration</Duration><Recurrence>$Recurrence</Recurrence><Enabled>$Enabled</Enabled><RoomUUID>$RoomUUID</RoomUUID><ProgramURI>$ProgramURI</ProgramURI><ProgramMetaData>$ProgramMetaData</ProgramMetaData><PlayMode>$PlayMode</PlayMode><Volume>$Volume</Volume><IncludeLinkedZones>$IncludeLinkedZones</IncludeLinkedZones>";
        $filter="AssignedID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CreateAlarm',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : UpdateAlarm
    /* 
    /*  Benoetigt:
    /*          @ID (ui4) 
    /*          @StartLocalTime (string) 
    /*          @Duration (string) 
    /*          @Recurrence (string)  => Auswahl: ONCE|WEEKDAYS|WEEKENDS|DAILY
    /*          @Enabled (boolean) 
    /*          @RoomUUID (string) 
    /*          @ProgramURI (string) 
    /*          @ProgramMetaData (string) 
    /*          @PlayMode (string)  => Auswahl: NORMAL|REPEAT_ALL|SHUFFLE_NOREPEAT|SHUFFLE
    /*          @Volume (ui2) 
    /*          @IncludeLinkedZones (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function UpdateAlarm($ID, $StartLocalTime, $Duration, $Recurrence, $Enabled, $RoomUUID, $ProgramURI, $ProgramMetaData, $PlayMode, $Volume, $IncludeLinkedZones){
        $args="<ID>$ID</ID><StartLocalTime>$StartLocalTime</StartLocalTime><Duration>$Duration</Duration><Recurrence>$Recurrence</Recurrence><Enabled>$Enabled</Enabled><RoomUUID>$RoomUUID</RoomUUID><ProgramURI>$ProgramURI</ProgramURI><ProgramMetaData>$ProgramMetaData</ProgramMetaData><PlayMode>$PlayMode</PlayMode><Volume>$Volume</Volume><IncludeLinkedZones>$IncludeLinkedZones</IncludeLinkedZones>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'UpdateAlarm',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : DestroyAlarm
    /* 
    /*  Benoetigt:
    /*          @ID (ui4) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function DestroyAlarm($ID){
        $args="<ID>$ID</ID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'DestroyAlarm',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ListAlarms
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentAlarmList (string) 
    /*          @CurrentAlarmListVersion (string) 
    /*
    /****************************************************************************/
    public function ListAlarms(){
        $args="";
        $filter="CurrentAlarmList,CurrentAlarmListVersion";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ListAlarms',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetDailyIndexRefreshTime
    /* 
    /*  Benoetigt:
    /*          @DesiredDailyIndexRefreshTime (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetDailyIndexRefreshTime($DesiredDailyIndexRefreshTime){
        $args="<DesiredDailyIndexRefreshTime>$DesiredDailyIndexRefreshTime</DesiredDailyIndexRefreshTime>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetDailyIndexRefreshTime',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetDailyIndexRefreshTime
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentDailyIndexRefreshTime (string) 
    /*
    /****************************************************************************/
    public function GetDailyIndexRefreshTime(){
        $args="";
        $filter="CurrentDailyIndexRefreshTime";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetDailyIndexRefreshTime',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : MusicServices 
/*  Service: urn:schemas-upnp-org:service:MusicServices:1
/*	     Id: urn:upnp-org:serviceId:MusicServices 
/*##########################################################################*/
class SonosMusicServices extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:MusicServices:1';
    protected $SERVICEURL='/MusicServices/Control';
    protected $EVENTURL='/MusicServices/Event';
    /***************************************************************************
    /* Funktion : GetSessionId
    /* 
    /*  Benoetigt:
    /*          @ServiceId (i2) 
    /*          @Username (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @SessionId (string) 
    /*
    /****************************************************************************/
    public function GetSessionId($ServiceId, $Username){
        $args="<ServiceId>$ServiceId</ServiceId><Username>$Username</Username>";
        $filter="SessionId";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetSessionId',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ListAvailableServices
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @AvailableServiceDescriptorList (string) 
    /*          @AvailableServiceTypeList (string) 
    /*          @AvailableServiceListVersion (string) 
    /*
    /****************************************************************************/
    public function ListAvailableServices(){
        $args="";
        $filter="AvailableServiceDescriptorList,AvailableServiceTypeList,AvailableServiceListVersion";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ListAvailableServices',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : UpdateAvailableServices
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function UpdateAvailableServices(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'UpdateAvailableServices',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : DeviceProperties 
/*  Service: urn:schemas-upnp-org:service:DeviceProperties:1
/*	     Id: urn:upnp-org:serviceId:DeviceProperties 
/*##########################################################################*/
class SonosDeviceProperties extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:DeviceProperties:1';
    protected $SERVICEURL='/DeviceProperties/Control';
    protected $EVENTURL='/DeviceProperties/Event';
    /***************************************************************************
    /* Funktion : SetLEDState
    /* 
    /*  Benoetigt:
    /*          @DesiredLEDState (string)  => Auswahl: On|Off
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetLEDState($DesiredLEDState){
        $args="<DesiredLEDState>$DesiredLEDState</DesiredLEDState>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetLEDState',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetLEDState
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentLEDState (string)  => Auswahl: On|Off
    /*
    /****************************************************************************/
    public function GetLEDState(){
        $args="";
        $filter="CurrentLEDState";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetLEDState',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddBondedZones
    /* 
    /*  Benoetigt:
    /*          @ChannelMapSet (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function AddBondedZones($ChannelMapSet){
        $args="<ChannelMapSet>$ChannelMapSet</ChannelMapSet>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddBondedZones',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveBondedZones
    /* 
    /*  Benoetigt:
    /*          @ChannelMapSet (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveBondedZones($ChannelMapSet){
        $args="<ChannelMapSet>$ChannelMapSet</ChannelMapSet>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveBondedZones',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : CreateStereoPair
    /* 
    /*  Benoetigt:
    /*          @ChannelMapSet (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function CreateStereoPair($ChannelMapSet){
        $args="<ChannelMapSet>$ChannelMapSet</ChannelMapSet>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CreateStereoPair',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SeparateStereoPair
    /* 
    /*  Benoetigt:
    /*          @ChannelMapSet (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SeparateStereoPair($ChannelMapSet){
        $args="<ChannelMapSet>$ChannelMapSet</ChannelMapSet>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SeparateStereoPair',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetZoneAttributes
    /* 
    /*  Benoetigt:
    /*          @DesiredZoneName (string) 
    /*          @DesiredIcon (string) 
    /*          @DesiredConfiguration (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetZoneAttributes($DesiredZoneName, $DesiredIcon, $DesiredConfiguration){
        $args="<DesiredZoneName>$DesiredZoneName</DesiredZoneName><DesiredIcon>$DesiredIcon</DesiredIcon><DesiredConfiguration>$DesiredConfiguration</DesiredConfiguration>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetZoneAttributes',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetZoneAttributes
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentZoneName (string) 
    /*          @CurrentIcon (string) 
    /*          @CurrentConfiguration (string) 
    /*
    /****************************************************************************/
    public function GetZoneAttributes(){
        $args="";
        $filter="CurrentZoneName,CurrentIcon,CurrentConfiguration";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetZoneAttributes',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetHouseholdID
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentHouseholdID (string) 
    /*
    /****************************************************************************/
    public function GetHouseholdID(){
        $args="";
        $filter="CurrentHouseholdID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetHouseholdID',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetZoneInfo
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @SerialNumber (string) 
    /*          @SoftwareVersion (string) 
    /*          @DisplaySoftwareVersion (string) 
    /*          @HardwareVersion (string) 
    /*          @IPAddress (string) 
    /*          @MACAddress (string) 
    /*          @CopyrightInfo (string) 
    /*          @ExtraInfo (string) 
    /*          @HTAudioIn (ui4) 
    /*
    /****************************************************************************/
    public function GetZoneInfo(){
        $args="";
        $filter="SerialNumber,SoftwareVersion,DisplaySoftwareVersion,HardwareVersion,IPAddress,MACAddress,CopyrightInfo,ExtraInfo,HTAudioIn";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetZoneInfo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetAutoplayLinkedZones
    /* 
    /*  Benoetigt:
    /*          @IncludeLinkedZones (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetAutoplayLinkedZones($IncludeLinkedZones){
        $args="<IncludeLinkedZones>$IncludeLinkedZones</IncludeLinkedZones>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetAutoplayLinkedZones',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetAutoplayLinkedZones
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @IncludeLinkedZones (boolean) 
    /*
    /****************************************************************************/
    public function GetAutoplayLinkedZones(){
        $args="";
        $filter="IncludeLinkedZones";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetAutoplayLinkedZones',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetAutoplayRoomUUID
    /* 
    /*  Benoetigt:
    /*          @RoomUUID (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetAutoplayRoomUUID($RoomUUID){
        $args="<RoomUUID>$RoomUUID</RoomUUID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetAutoplayRoomUUID',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetAutoplayRoomUUID
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @RoomUUID (string) 
    /*
    /****************************************************************************/
    public function GetAutoplayRoomUUID(){
        $args="";
        $filter="RoomUUID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetAutoplayRoomUUID',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetAutoplayVolume
    /* 
    /*  Benoetigt:
    /*          @Volume (ui2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetAutoplayVolume($Volume){
        $args="<Volume>$Volume</Volume>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetAutoplayVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetAutoplayVolume
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentVolume (ui2) 
    /*
    /****************************************************************************/
    public function GetAutoplayVolume(){
        $args="";
        $filter="CurrentVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetAutoplayVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ImportSetting
    /* 
    /*  Benoetigt:
    /*          @SettingID (ui4) 
    /*          @SettingURI (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ImportSetting($SettingID, $SettingURI){
        $args="<SettingID>$SettingID</SettingID><SettingURI>$SettingURI</SettingURI>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ImportSetting',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetUseAutoplayVolume
    /* 
    /*  Benoetigt:
    /*          @UseVolume (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetUseAutoplayVolume($UseVolume){
        $args="<UseVolume>$UseVolume</UseVolume>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetUseAutoplayVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetUseAutoplayVolume
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @UseVolume (boolean) 
    /*
    /****************************************************************************/
    public function GetUseAutoplayVolume(){
        $args="";
        $filter="UseVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetUseAutoplayVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddHTSatellite
    /* 
    /*  Benoetigt:
    /*          @HTSatChanMapSet (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function AddHTSatellite($HTSatChanMapSet){
        $args="<HTSatChanMapSet>$HTSatChanMapSet</HTSatChanMapSet>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddHTSatellite',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveHTSatellite
    /* 
    /*  Benoetigt:
    /*          @SatRoomUUID (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveHTSatellite($SatRoomUUID){
        $args="<SatRoomUUID>$SatRoomUUID</SatRoomUUID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveHTSatellite',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : SystemProperties 
/*  Service: urn:schemas-upnp-org:service:SystemProperties:1
/*	     Id: urn:upnp-org:serviceId:SystemProperties 
/*##########################################################################*/
class SonosSystemProperties extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:SystemProperties:1';
    protected $SERVICEURL='/SystemProperties/Control';
    protected $EVENTURL='/SystemProperties/Event';
    /***************************************************************************
    /* Funktion : SetString
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*          @StringValue (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetString($VariableName, $StringValue){
        $args="<VariableName>$VariableName</VariableName><StringValue>$StringValue</StringValue>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetString',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetStringX
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*          @StringValue (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetStringX($VariableName, $StringValue){
        $args="<VariableName>$VariableName</VariableName><StringValue>$StringValue</StringValue>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetStringX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetString
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @StringValue (string) 
    /*
    /****************************************************************************/
    public function GetString($VariableName){
        $args="<VariableName>$VariableName</VariableName>";
        $filter="StringValue";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetString',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetStringX
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @StringValue (string) 
    /*
    /****************************************************************************/
    public function GetStringX($VariableName){
        $args="<VariableName>$VariableName</VariableName>";
        $filter="StringValue";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetStringX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Remove
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Remove($VariableName){
        $args="<VariableName>$VariableName</VariableName>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Remove',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveX
    /* 
    /*  Benoetigt:
    /*          @VariableName (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveX($VariableName){
        $args="<VariableName>$VariableName</VariableName>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetWebCode
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @WebCode (string) 
    /*
    /****************************************************************************/
    public function GetWebCode($AccountType){
        $args="<AccountType>$AccountType</AccountType>";
        $filter="WebCode";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetWebCode',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ProvisionTrialAccount
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AccountUDN (string) 
    /*
    /****************************************************************************/
    public function ProvisionTrialAccount($AccountType){
        $args="<AccountType>$AccountType</AccountType>";
        $filter="AccountUDN";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ProvisionTrialAccount',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ProvisionCredentialedTrialAccountX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountID (string) 
    /*          @AccountPassword (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @IsExpired (boolean) 
    /*          @AccountUDN (string) 
    /*
    /****************************************************************************/
    public function ProvisionCredentialedTrialAccountX($AccountType, $AccountID, $AccountPassword){
        $args="<AccountType>$AccountType</AccountType><AccountID>$AccountID</AccountID><AccountPassword>$AccountPassword</AccountPassword>";
        $filter="IsExpired,AccountUDN";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ProvisionCredentialedTrialAccountX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : MigrateTrialAccountX
    /* 
    /*  Benoetigt:
    /*          @TargetAccountType (ui4) 
    /*          @TargetAccountID (string) 
    /*          @TargetAccountPassword (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function MigrateTrialAccountX($TargetAccountType, $TargetAccountID, $TargetAccountPassword){
        $args="<TargetAccountType>$TargetAccountType</TargetAccountType><TargetAccountID>$TargetAccountID</TargetAccountID><TargetAccountPassword>$TargetAccountPassword</TargetAccountPassword>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'MigrateTrialAccountX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddAccountX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountID (string) 
    /*          @AccountPassword (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AccountUDN (string) 
    /*
    /****************************************************************************/
    public function AddAccountX($AccountType, $AccountID, $AccountPassword){
        $args="<AccountType>$AccountType</AccountType><AccountID>$AccountID</AccountID><AccountPassword>$AccountPassword</AccountPassword>";
        $filter="AccountUDN";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddAccountX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddAccountWithCredentialsX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountToken (string) 
    /*          @AccountKey (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function AddAccountWithCredentialsX($AccountType, $AccountToken, $AccountKey){
        $args="<AccountType>$AccountType</AccountType><AccountToken>$AccountToken</AccountToken><AccountKey>$AccountKey</AccountKey>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddAccountWithCredentialsX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddOAuthAccountX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountToken (string) 
    /*          @AccountKey (string) 
    /*          @OAuthDeviceID (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AccountUDN (string) 
    /*
    /****************************************************************************/
    public function AddOAuthAccountX($AccountType, $AccountToken, $AccountKey, $OAuthDeviceID){
        $args="<AccountType>$AccountType</AccountType><AccountToken>$AccountToken</AccountToken><AccountKey>$AccountKey</AccountKey><OAuthDeviceID>$OAuthDeviceID</OAuthDeviceID>";
        $filter="AccountUDN";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddOAuthAccountX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveAccount
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountID (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveAccount($AccountType, $AccountID){
        $args="<AccountType>$AccountType</AccountType><AccountID>$AccountID</AccountID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveAccount',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : EditAccountPasswordX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountID (string) 
    /*          @NewAccountPassword (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function EditAccountPasswordX($AccountType, $AccountID, $NewAccountPassword){
        $args="<AccountType>$AccountType</AccountType><AccountID>$AccountID</AccountID><NewAccountPassword>$NewAccountPassword</NewAccountPassword>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'EditAccountPasswordX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetAccountNicknameX
    /* 
    /*  Benoetigt:
    /*          @AccountUDN (string) 
    /*          @AccountNickname (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetAccountNicknameX($AccountUDN, $AccountNickname){
        $args="<AccountUDN>$AccountUDN</AccountUDN><AccountNickname>$AccountNickname</AccountNickname>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetAccountNicknameX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RefreshAccountCredentialsX
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountUID (ui4) 
    /*          @AccountToken (string) 
    /*          @AccountKey (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RefreshAccountCredentialsX($AccountType, $AccountUID, $AccountToken, $AccountKey){
        $args="<AccountType>$AccountType</AccountType><AccountUID>$AccountUID</AccountUID><AccountToken>$AccountToken</AccountToken><AccountKey>$AccountKey</AccountKey>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RefreshAccountCredentialsX',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : EditAccountMd
    /* 
    /*  Benoetigt:
    /*          @AccountType (ui4) 
    /*          @AccountID (string) 
    /*          @NewAccountMd (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function EditAccountMd($AccountType, $AccountID, $NewAccountMd){
        $args="<AccountType>$AccountType</AccountType><AccountID>$AccountID</AccountID><NewAccountMd>$NewAccountMd</NewAccountMd>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'EditAccountMd',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : DoPostUpdateTasks
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function DoPostUpdateTasks(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'DoPostUpdateTasks',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ResetThirdPartyCredentials
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ResetThirdPartyCredentials(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ResetThirdPartyCredentials',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : EnableRDM
    /* 
    /*  Benoetigt:
    /*          @RDMValue (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function EnableRDM($RDMValue){
        $args="<RDMValue>$RDMValue</RDMValue>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'EnableRDM',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetRDM
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @RDMValue (boolean) 
    /*
    /****************************************************************************/
    public function GetRDM(){
        $args="";
        $filter="RDMValue";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetRDM',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReplaceAccountX
    /* 
    /*  Benoetigt:
    /*          @AccountUDN (string) 
    /*          @NewAccountID (string) 
    /*          @NewAccountPassword (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewAccountUDN (string) 
    /*
    /****************************************************************************/
    public function ReplaceAccountX($AccountUDN, $NewAccountID, $NewAccountPassword){
        $args="<AccountUDN>$AccountUDN</AccountUDN><NewAccountID>$NewAccountID</NewAccountID><NewAccountPassword>$NewAccountPassword</NewAccountPassword>";
        $filter="NewAccountUDN";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReplaceAccountX',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : ZoneGroupTopology 
/*  Service: urn:schemas-upnp-org:service:ZoneGroupTopology:1
/*	     Id: urn:upnp-org:serviceId:ZoneGroupTopology 
/*##########################################################################*/
class SonosZoneGroupTopology extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:ZoneGroupTopology:1';
    protected $SERVICEURL='/ZoneGroupTopology/Control';
    protected $EVENTURL='/ZoneGroupTopology/Event';
    /***************************************************************************
    /* Funktion : CheckForUpdate
    /* 
    /*  Benoetigt:
    /*          @UpdateType (string)  => Auswahl: All|Software
    /*          @CachedOnly (boolean) 
    /*          @Version (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @UpdateItem (string) 
    /*
    /****************************************************************************/
    public function CheckForUpdate($UpdateType, $CachedOnly, $Version){
        $args="<UpdateType>$UpdateType</UpdateType><CachedOnly>$CachedOnly</CachedOnly><Version>$Version</Version>";
        $filter="UpdateItem";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CheckForUpdate',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : BeginSoftwareUpdate
    /* 
    /*  Benoetigt:
    /*          @UpdateURL (string) 
    /*          @Flags (ui4) 
    /*          @ExtraOptions (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function BeginSoftwareUpdate($UpdateURL, $Flags, $ExtraOptions){
        $args="<UpdateURL>$UpdateURL</UpdateURL><Flags>$Flags</Flags><ExtraOptions>$ExtraOptions</ExtraOptions>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'BeginSoftwareUpdate',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReportUnresponsiveDevice
    /* 
    /*  Benoetigt:
    /*          @DeviceUUID (string) 
    /*          @DesiredAction (string)  => Auswahl: Remove|VerifyThenRemoveSystemwide
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ReportUnresponsiveDevice($DeviceUUID, $DesiredAction){
        $args="<DeviceUUID>$DeviceUUID</DeviceUUID><DesiredAction>$DesiredAction</DesiredAction>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReportUnresponsiveDevice',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReportAlarmStartedRunning
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ReportAlarmStartedRunning(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReportAlarmStartedRunning',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SubmitDiagnostics
    /* 
    /*  Benoetigt:
    /*          @IncludeControllers (boolean) 
    /*          @Type (string)  => Auswahl: Healthcheck|Server|User
    /*
    /*  Liefert als Ergebnis:
    /*          @DiagnosticID (ui4) 
    /*
    /****************************************************************************/
    public function SubmitDiagnostics($IncludeControllers, $Type){
        $args="<IncludeControllers>$IncludeControllers</IncludeControllers><Type>$Type</Type>";
        $filter="DiagnosticID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SubmitDiagnostics',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RegisterMobileDevice
    /* 
    /*  Benoetigt:
    /*          @MobileDeviceName (string) 
    /*          @MobileDeviceUDN (string) 
    /*          @MobileIPAndPort (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RegisterMobileDevice($MobileDeviceName, $MobileDeviceUDN, $MobileIPAndPort){
        $args="<MobileDeviceName>$MobileDeviceName</MobileDeviceName><MobileDeviceUDN>$MobileDeviceUDN</MobileDeviceUDN><MobileIPAndPort>$MobileIPAndPort</MobileIPAndPort>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RegisterMobileDevice',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetZoneGroupAttributes
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentZoneGroupName (string) 
    /*          @CurrentZoneGroupID (string) 
    /*          @CurrentZonePlayerUUIDsInGroup (string) 
    /*
    /****************************************************************************/
    public function GetZoneGroupAttributes(){
        $args="";
        $filter="CurrentZoneGroupName,CurrentZoneGroupID,CurrentZonePlayerUUIDsInGroup";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetZoneGroupAttributes',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetZoneGroupState
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @ZoneGroupState (string) 
    /*
    /****************************************************************************/
    public function GetZoneGroupState(){
        $args="";
        $filter="ZoneGroupState";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetZoneGroupState',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : GroupManagement 
/*  Service: urn:schemas-upnp-org:service:GroupManagement:1
/*	     Id: urn:upnp-org:serviceId:GroupManagement 
/*##########################################################################*/
class SonosGroupManagement extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:GroupManagement:1';
    protected $SERVICEURL='/GroupManagement/Control';
    protected $EVENTURL='/GroupManagement/Event';
    /***************************************************************************
    /* Funktion : AddMember
    /* 
    /*  Benoetigt:
    /*          @MemberID (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentTransportSettings (string) 
    /*          @GroupUUIDJoined (string) 
    /*          @ResetVolumeAfter (boolean) 
    /*          @VolumeAVTransportURI (string) 
    /*
    /****************************************************************************/
    public function AddMember($MemberID){
        $args="<MemberID>$MemberID</MemberID>";
        $filter="CurrentTransportSettings,GroupUUIDJoined,ResetVolumeAfter,VolumeAVTransportURI";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddMember',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveMember
    /* 
    /*  Benoetigt:
    /*          @MemberID (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveMember($MemberID){
        $args="<MemberID>$MemberID</MemberID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveMember',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReportTrackBufferingResult
    /* 
    /*  Benoetigt:
    /*          @MemberID (string) 
    /*          @ResultCode (i4) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ReportTrackBufferingResult($MemberID, $ResultCode){
        $args="<MemberID>$MemberID</MemberID><ResultCode>$ResultCode</ResultCode>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReportTrackBufferingResult',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : QPlay 
/*  Service: urn:schemas-tencent-com:service:QPlay:1
/*	     Id: urn:tencent-com:serviceId:QPlay 
/*##########################################################################*/
class SonosQPlay extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-tencent-com:service:QPlay:1';
    protected $SERVICEURL='/QPlay/Control';
    protected $EVENTURL='/QPlay/Event';
    /***************************************************************************
    /* Funktion : QPlayAuth
    /* 
    /*  Benoetigt:
    /*          @Seed (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Code (string) 
    /*          @MID (string) 
    /*          @DID (string) 
    /*
    /****************************************************************************/
    public function QPlayAuth($Seed){
        $args="<Seed>$Seed</Seed>";
        $filter="Code,MID,DID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'QPlayAuth',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : ContentDirectory 
/*  Service: urn:schemas-upnp-org:service:ContentDirectory:1
/*	     Id: urn:upnp-org:serviceId:ContentDirectory 
/*##########################################################################*/
class SonosContentDirectory extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:ContentDirectory:1';
    protected $SERVICEURL='/MediaServer/ContentDirectory/Control';
    protected $EVENTURL='/MediaServer/ContentDirectory/Event';
    /***************************************************************************
    /* Funktion : GetSearchCapabilities
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @SearchCaps (string) 
    /*
    /****************************************************************************/
    public function GetSearchCapabilities(){
        $args="";
        $filter="SearchCaps";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetSearchCapabilities',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetSortCapabilities
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @SortCaps (string) 
    /*
    /****************************************************************************/
    public function GetSortCapabilities(){
        $args="";
        $filter="SortCaps";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetSortCapabilities',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetSystemUpdateID
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @Id (ui4) 
    /*
    /****************************************************************************/
    public function GetSystemUpdateID(){
        $args="";
        $filter="Id";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetSystemUpdateID',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetAlbumArtistDisplayOption
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @AlbumArtistDisplayOption (string) 
    /*
    /****************************************************************************/
    public function GetAlbumArtistDisplayOption(){
        $args="";
        $filter="AlbumArtistDisplayOption";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetAlbumArtistDisplayOption',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetLastIndexChange
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @LastIndexChange (string) 
    /*
    /****************************************************************************/
    public function GetLastIndexChange(){
        $args="";
        $filter="LastIndexChange";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetLastIndexChange',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Browse
    /* 
    /*  Benoetigt:
    /*          @ObjectID (string) 
    /*          @BrowseFlag (string)  => Auswahl: BrowseMetadata|BrowseDirectChildren
    /*          @Filter (string) 
    /*          @StartingIndex (ui4) 
    /*          @RequestedCount (ui4) 
    /*          @SortCriteria (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Result (string) 
    /*          @NumberReturned (ui4) 
    /*          @TotalMatches (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /****************************************************************************/
    public function Browse($ObjectID, $BrowseFlag, $Filter, $StartingIndex, $RequestedCount, $SortCriteria){
        $args="<ObjectID>$ObjectID</ObjectID><BrowseFlag>$BrowseFlag</BrowseFlag><Filter>$Filter</Filter><StartingIndex>$StartingIndex</StartingIndex><RequestedCount>$RequestedCount</RequestedCount><SortCriteria>$SortCriteria</SortCriteria>";
        $filter="Result,NumberReturned,TotalMatches,UpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Browse',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : FindPrefix
    /* 
    /*  Benoetigt:
    /*          @ObjectID (string) 
    /*          @Prefix (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @StartingIndex (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /****************************************************************************/
    public function FindPrefix($ObjectID, $Prefix){
        $args="<ObjectID>$ObjectID</ObjectID><Prefix>$Prefix</Prefix>";
        $filter="StartingIndex,UpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'FindPrefix',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetAllPrefixLocations
    /* 
    /*  Benoetigt:
    /*          @ObjectID (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @TotalPrefixes (ui4) 
    /*          @PrefixAndIndexCSV (string) 
    /*          @UpdateID (ui4) 
    /*
    /****************************************************************************/
    public function GetAllPrefixLocations($ObjectID){
        $args="<ObjectID>$ObjectID</ObjectID>";
        $filter="TotalPrefixes,PrefixAndIndexCSV,UpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetAllPrefixLocations',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : CreateObject
    /* 
    /*  Benoetigt:
    /*          @ContainerID (string) 
    /*          @Elements (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @ObjectID (string) 
    /*          @Result (string) 
    /*
    /****************************************************************************/
    public function CreateObject($ContainerID, $Elements){
        $args="<ContainerID>$ContainerID</ContainerID><Elements>$Elements</Elements>";
        $filter="ObjectID,Result";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CreateObject',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : UpdateObject
    /* 
    /*  Benoetigt:
    /*          @ObjectID (string) 
    /*          @CurrentTagValue (string) 
    /*          @NewTagValue (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function UpdateObject($ObjectID, $CurrentTagValue, $NewTagValue){
        $args="<ObjectID>$ObjectID</ObjectID><CurrentTagValue>$CurrentTagValue</CurrentTagValue><NewTagValue>$NewTagValue</NewTagValue>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'UpdateObject',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : DestroyObject
    /* 
    /*  Benoetigt:
    /*          @ObjectID (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function DestroyObject($ObjectID){
        $args="<ObjectID>$ObjectID</ObjectID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'DestroyObject',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RefreshShareList
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RefreshShareList(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RefreshShareList',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RefreshShareIndex
    /* 
    /*  Benoetigt:
    /*          @AlbumArtistDisplayOption (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RefreshShareIndex($AlbumArtistDisplayOption){
        $args="<AlbumArtistDisplayOption>$AlbumArtistDisplayOption</AlbumArtistDisplayOption>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RefreshShareIndex',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RequestResort
    /* 
    /*  Benoetigt:
    /*          @SortOrder (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RequestResort($SortOrder){
        $args="<SortOrder>$SortOrder</SortOrder>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RequestResort',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetShareIndexInProgress
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @IsIndexing (boolean) 
    /*
    /****************************************************************************/
    public function GetShareIndexInProgress(){
        $args="";
        $filter="IsIndexing";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetShareIndexInProgress',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetBrowseable
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @IsBrowseable (boolean) 
    /*
    /****************************************************************************/
    public function GetBrowseable(){
        $args="";
        $filter="IsBrowseable";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetBrowseable',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetBrowseable
    /* 
    /*  Benoetigt:
    /*          @Browseable (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetBrowseable($Browseable){
        $args="<Browseable>$Browseable</Browseable>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetBrowseable',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : ConnectionManager 
/*  Service: urn:schemas-upnp-org:service:ConnectionManager:1
/*	     Id: urn:upnp-org:serviceId:ConnectionManager 
/*##########################################################################*/
class SonosConnectionManager extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:ConnectionManager:1';
    protected $SERVICEURL='/MediaServer/ConnectionManager/Control';
    protected $EVENTURL='/MediaServer/ConnectionManager/Event';
    /***************************************************************************
    /* Funktion : GetProtocolInfo
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Source (string) 
    /*          @Sink (string) 
    /*
    /****************************************************************************/
    public function GetProtocolInfo(){
        $args="";
        $filter="Source,Sink";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetProtocolInfo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetCurrentConnectionIDs
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis:
    /*          @ConnectionIDs (string) 
    /*
    /****************************************************************************/
    public function GetCurrentConnectionIDs(){
        $args="";
        $filter="ConnectionIDs";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetCurrentConnectionIDs',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetCurrentConnectionInfo
    /* 
    /*  Benoetigt:
    /*          @ConnectionID (i4) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @RcsID (i4) 
    /*          @AVTransportID (i4) 
    /*          @ProtocolInfo (string) 
    /*          @PeerConnectionManager (string) 
    /*          @PeerConnectionID (i4) 
    /*          @Direction (string)  => Auswahl: Input|Output
    /*          @Status (string)  => Auswahl: OK|ContentFormatMismatch|InsufficientBandwidth|UnreliableChannel|Unknown
    /*
    /****************************************************************************/
    public function GetCurrentConnectionInfo($ConnectionID){
        $args="<ConnectionID>$ConnectionID</ConnectionID>";
        $filter="RcsID,AVTransportID,ProtocolInfo,PeerConnectionManager,PeerConnectionID,Direction,Status";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetCurrentConnectionInfo',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : RenderingControl 
/*  Service: urn:schemas-upnp-org:service:RenderingControl:1
/*	     Id: urn:upnp-org:serviceId:RenderingControl 
/*##########################################################################*/
class SonosRenderingControl extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:RenderingControl:1';
    protected $SERVICEURL='/MediaRenderer/RenderingControl/Control';
    protected $EVENTURL='/MediaRenderer/RenderingControl/Event';
    /***************************************************************************
    /* Funktion : GetMute
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF|SpeakerOnly
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentMute (boolean) 
    /*
    /****************************************************************************/
    public function GetMute($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="CurrentMute";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetMute',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetMute
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF|SpeakerOnly
    /*          @DesiredMute (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetMute($InstanceID=0, $Channel='MASTER', $DesiredMute){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><DesiredMute>$DesiredMute</DesiredMute>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetMute',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ResetBasicEQ
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Bass (i2) 
    /*          @Treble (i2) 
    /*          @Loudness (boolean) 
    /*          @LeftVolume (ui2) 
    /*          @RightVolume (ui2) 
    /*
    /****************************************************************************/
    public function ResetBasicEQ($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="Bass,Treble,Loudness,LeftVolume,RightVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ResetBasicEQ',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ResetExtEQ
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @EQType (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ResetExtEQ($InstanceID=0, $EQType){
        $args="<InstanceID>$InstanceID</InstanceID><EQType>$EQType</EQType>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ResetExtEQ',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentVolume (ui2) 
    /*
    /****************************************************************************/
    public function GetVolume($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="CurrentVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*          @DesiredVolume (ui2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetVolume($InstanceID=0, $Channel='MASTER', $DesiredVolume){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><DesiredVolume>$DesiredVolume</DesiredVolume>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetRelativeVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*          @Adjustment (i4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewVolume (ui2) 
    /*
    /****************************************************************************/
    public function SetRelativeVolume($InstanceID=0, $Channel='MASTER', $Adjustment){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><Adjustment>$Adjustment</Adjustment>";
        $filter="NewVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetRelativeVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetVolumeDB
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentVolume (i2) 
    /*
    /****************************************************************************/
    public function GetVolumeDB($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="CurrentVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetVolumeDB',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetVolumeDB
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*          @DesiredVolume (i2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetVolumeDB($InstanceID=0, $Channel='MASTER', $DesiredVolume){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><DesiredVolume>$DesiredVolume</DesiredVolume>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetVolumeDB',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetVolumeDBRange
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @MinValue (i2) 
    /*          @MaxValue (i2) 
    /*
    /****************************************************************************/
    public function GetVolumeDBRange($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="MinValue,MaxValue";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetVolumeDBRange',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetBass
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentBass (i2) 
    /*
    /****************************************************************************/
    public function GetBass($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentBass";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetBass',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetBass
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DesiredBass (i2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetBass($InstanceID=0, $DesiredBass){
        $args="<InstanceID>$InstanceID</InstanceID><DesiredBass>$DesiredBass</DesiredBass>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetBass',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTreble
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentTreble (i2) 
    /*
    /****************************************************************************/
    public function GetTreble($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentTreble";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTreble',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetTreble
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DesiredTreble (i2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetTreble($InstanceID=0, $DesiredTreble){
        $args="<InstanceID>$InstanceID</InstanceID><DesiredTreble>$DesiredTreble</DesiredTreble>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetTreble',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetEQ
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @EQType (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentValue (i2) 
    /*
    /****************************************************************************/
    public function GetEQ($InstanceID=0, $EQType){
        $args="<InstanceID>$InstanceID</InstanceID><EQType>$EQType</EQType>";
        $filter="CurrentValue";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetEQ',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetEQ
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @EQType (string) 
    /*          @DesiredValue (i2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetEQ($InstanceID=0, $EQType, $DesiredValue){
        $args="<InstanceID>$InstanceID</InstanceID><EQType>$EQType</EQType><DesiredValue>$DesiredValue</DesiredValue>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetEQ',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetLoudness
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentLoudness (boolean) 
    /*
    /****************************************************************************/
    public function GetLoudness($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="CurrentLoudness";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetLoudness',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetLoudness
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*          @DesiredLoudness (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetLoudness($InstanceID=0, $Channel='MASTER', $DesiredLoudness){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><DesiredLoudness>$DesiredLoudness</DesiredLoudness>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetLoudness',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetSupportsOutputFixed
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentSupportsFixed (boolean) 
    /*
    /****************************************************************************/
    public function GetSupportsOutputFixed($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentSupportsFixed";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetSupportsOutputFixed',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetOutputFixed
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentFixed (boolean) 
    /*
    /****************************************************************************/
    public function GetOutputFixed($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentFixed";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetOutputFixed',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetOutputFixed
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DesiredFixed (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetOutputFixed($InstanceID=0, $DesiredFixed){
        $args="<InstanceID>$InstanceID</InstanceID><DesiredFixed>$DesiredFixed</DesiredFixed>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetOutputFixed',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetHeadphoneConnected
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentHeadphoneConnected (boolean) 
    /*
    /****************************************************************************/
    public function GetHeadphoneConnected($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentHeadphoneConnected";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetHeadphoneConnected',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RampToVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*          @RampType (string)  => Auswahl: SLEEP_TIMER_RAMP_TYPE|ALARM_RAMP_TYPE|AUTOPLAY_RAMP_TYPE
    /*          @DesiredVolume (ui2) 
    /*          @ResetVolumeAfter (boolean) 
    /*          @ProgramURI (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @RampTime (ui4) 
    /*
    /****************************************************************************/
    public function RampToVolume($InstanceID=0, $Channel='MASTER', $RampType, $DesiredVolume, $ResetVolumeAfter, $ProgramURI){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel><RampType>$RampType</RampType><DesiredVolume>$DesiredVolume</DesiredVolume><ResetVolumeAfter>$ResetVolumeAfter</ResetVolumeAfter><ProgramURI>$ProgramURI</ProgramURI>";
        $filter="RampTime";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RampToVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RestoreVolumePriorToRamp
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Channel (string) Vorgabe = 'MASTER'  => Auswahl: Master|LF|RF
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RestoreVolumePriorToRamp($InstanceID=0, $Channel='MASTER'){
        $args="<InstanceID>$InstanceID</InstanceID><Channel>$Channel</Channel>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RestoreVolumePriorToRamp',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetChannelMap
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @ChannelMap (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetChannelMap($InstanceID=0, $ChannelMap){
        $args="<InstanceID>$InstanceID</InstanceID><ChannelMap>$ChannelMap</ChannelMap>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetChannelMap',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : AVTransport 
/*  Service: urn:schemas-upnp-org:service:AVTransport:1
/*	     Id: urn:upnp-org:serviceId:AVTransport 
/*##########################################################################*/
class SonosAVTransport extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:AVTransport:1';
    protected $SERVICEURL='/MediaRenderer/AVTransport/Control';
    protected $EVENTURL='/MediaRenderer/AVTransport/Event';
    /***************************************************************************
    /* Funktion : SetAVTransportURI
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @CurrentURI (string) 
    /*          @CurrentURIMetaData (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetAVTransportURI($InstanceID=0, $CurrentURI, $CurrentURIMetaData){
        $args="<InstanceID>$InstanceID</InstanceID><CurrentURI>$CurrentURI</CurrentURI><CurrentURIMetaData>$CurrentURIMetaData</CurrentURIMetaData>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetAVTransportURI',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetNextAVTransportURI
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @NextURI (string) 
    /*          @NextURIMetaData (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetNextAVTransportURI($InstanceID=0, $NextURI, $NextURIMetaData){
        $args="<InstanceID>$InstanceID</InstanceID><NextURI>$NextURI</NextURI><NextURIMetaData>$NextURIMetaData</NextURIMetaData>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetNextAVTransportURI',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddURIToQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @EnqueuedURI (string) 
    /*          @EnqueuedURIMetaData (string) 
    /*          @DesiredFirstTrackNumberEnqueued (ui4) 
    /*          @EnqueueAsNext (boolean) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @FirstTrackNumberEnqueued (ui4) 
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*
    /****************************************************************************/
    public function AddURIToQueue($InstanceID=0, $EnqueuedURI, $EnqueuedURIMetaData, $DesiredFirstTrackNumberEnqueued, $EnqueueAsNext){
        $args="<InstanceID>$InstanceID</InstanceID><EnqueuedURI>$EnqueuedURI</EnqueuedURI><EnqueuedURIMetaData>$EnqueuedURIMetaData</EnqueuedURIMetaData><DesiredFirstTrackNumberEnqueued>$DesiredFirstTrackNumberEnqueued</DesiredFirstTrackNumberEnqueued><EnqueueAsNext>$EnqueueAsNext</EnqueueAsNext>";
        $filter="FirstTrackNumberEnqueued,NumTracksAdded,NewQueueLength";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddURIToQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddMultipleURIsToQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @UpdateID (ui4) 
    /*          @NumberOfURIs (ui4) 
    /*          @EnqueuedURIs (string) 
    /*          @EnqueuedURIsMetaData (string) 
    /*          @ContainerURI (string) 
    /*          @ContainerMetaData (string) 
    /*          @DesiredFirstTrackNumberEnqueued (ui4) 
    /*          @EnqueueAsNext (boolean) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @FirstTrackNumberEnqueued (ui4) 
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function AddMultipleURIsToQueue($InstanceID=0, $UpdateID, $NumberOfURIs, $EnqueuedURIs, $EnqueuedURIsMetaData, $ContainerURI, $ContainerMetaData, $DesiredFirstTrackNumberEnqueued, $EnqueueAsNext){
        $args="<InstanceID>$InstanceID</InstanceID><UpdateID>$UpdateID</UpdateID><NumberOfURIs>$NumberOfURIs</NumberOfURIs><EnqueuedURIs>$EnqueuedURIs</EnqueuedURIs><EnqueuedURIsMetaData>$EnqueuedURIsMetaData</EnqueuedURIsMetaData><ContainerURI>$ContainerURI</ContainerURI><ContainerMetaData>$ContainerMetaData</ContainerMetaData><DesiredFirstTrackNumberEnqueued>$DesiredFirstTrackNumberEnqueued</DesiredFirstTrackNumberEnqueued><EnqueueAsNext>$EnqueueAsNext</EnqueueAsNext>";
        $filter="FirstTrackNumberEnqueued,NumTracksAdded,NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddMultipleURIsToQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReorderTracksInQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @StartingIndex (ui4) 
    /*          @NumberOfTracks (ui4) 
    /*          @InsertBefore (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ReorderTracksInQueue($InstanceID=0, $StartingIndex, $NumberOfTracks, $InsertBefore, $UpdateID){
        $args="<InstanceID>$InstanceID</InstanceID><StartingIndex>$StartingIndex</StartingIndex><NumberOfTracks>$NumberOfTracks</NumberOfTracks><InsertBefore>$InsertBefore</InsertBefore><UpdateID>$UpdateID</UpdateID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReorderTracksInQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveTrackFromQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @ObjectID (string) 
    /*          @UpdateID (ui4) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveTrackFromQueue($InstanceID=0, $ObjectID, $UpdateID){
        $args="<InstanceID>$InstanceID</InstanceID><ObjectID>$ObjectID</ObjectID><UpdateID>$UpdateID</UpdateID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveTrackFromQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveTrackRangeFromQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @UpdateID (ui4) 
    /*          @StartingIndex (ui4) 
    /*          @NumberOfTracks (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function RemoveTrackRangeFromQueue($InstanceID=0, $UpdateID, $StartingIndex, $NumberOfTracks){
        $args="<InstanceID>$InstanceID</InstanceID><UpdateID>$UpdateID</UpdateID><StartingIndex>$StartingIndex</StartingIndex><NumberOfTracks>$NumberOfTracks</NumberOfTracks>";
        $filter="NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveTrackRangeFromQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveAllTracksFromQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RemoveAllTracksFromQueue($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveAllTracksFromQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SaveQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Title (string) 
    /*          @ObjectID (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AssignedObjectID (string) 
    /*
    /****************************************************************************/
    public function SaveQueue($InstanceID=0, $Title, $ObjectID){
        $args="<InstanceID>$InstanceID</InstanceID><Title>$Title</Title><ObjectID>$ObjectID</ObjectID>";
        $filter="AssignedObjectID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SaveQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : BackupQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function BackupQueue($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'BackupQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : CreateSavedQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Title (string) 
    /*          @EnqueuedURI (string) 
    /*          @EnqueuedURIMetaData (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*          @AssignedObjectID (string) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function CreateSavedQueue($InstanceID=0, $Title, $EnqueuedURI, $EnqueuedURIMetaData){
        $args="<InstanceID>$InstanceID</InstanceID><Title>$Title</Title><EnqueuedURI>$EnqueuedURI</EnqueuedURI><EnqueuedURIMetaData>$EnqueuedURIMetaData</EnqueuedURIMetaData>";
        $filter="NumTracksAdded,NewQueueLength,AssignedObjectID,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CreateSavedQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddURIToSavedQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @ObjectID (string) 
    /*          @UpdateID (ui4) 
    /*          @EnqueuedURI (string) 
    /*          @EnqueuedURIMetaData (string) 
    /*          @AddAtIndex (ui4) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function AddURIToSavedQueue($InstanceID=0, $ObjectID, $UpdateID, $EnqueuedURI, $EnqueuedURIMetaData, $AddAtIndex){
        $args="<InstanceID>$InstanceID</InstanceID><ObjectID>$ObjectID</ObjectID><UpdateID>$UpdateID</UpdateID><EnqueuedURI>$EnqueuedURI</EnqueuedURI><EnqueuedURIMetaData>$EnqueuedURIMetaData</EnqueuedURIMetaData><AddAtIndex>$AddAtIndex</AddAtIndex>";
        $filter="NumTracksAdded,NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddURIToSavedQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReorderTracksInSavedQueue
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @ObjectID (string) 
    /*          @UpdateID (ui4) 
    /*          @TrackList (string) 
    /*          @NewPositionList (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @QueueLengthChange (i4) 
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function ReorderTracksInSavedQueue($InstanceID=0, $ObjectID, $UpdateID, $TrackList, $NewPositionList){
        $args="<InstanceID>$InstanceID</InstanceID><ObjectID>$ObjectID</ObjectID><UpdateID>$UpdateID</UpdateID><TrackList>$TrackList</TrackList><NewPositionList>$NewPositionList</NewPositionList>";
        $filter="QueueLengthChange,NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReorderTracksInSavedQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetMediaInfo
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @NrTracks (ui4) 
    /*          @MediaDuration (string) 
    /*          @CurrentURI (string) 
    /*          @CurrentURIMetaData (string) 
    /*          @NextURI (string) 
    /*          @NextURIMetaData (string) 
    /*          @PlayMedium (string)  => Auswahl: NONE|NETWORK
    /*          @RecordMedium (string)  => Auswahl: NONE
    /*          @WriteStatus (string) 
    /*
    /****************************************************************************/
    public function GetMediaInfo($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="NrTracks,MediaDuration,CurrentURI,CurrentURIMetaData,NextURI,NextURIMetaData,PlayMedium,RecordMedium,WriteStatus";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetMediaInfo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTransportInfo
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @CurrentTransportState (string)  => Auswahl: STOPPED|PLAYING|PAUSED_PLAYBACK|TRANSITIONING
    /*          @CurrentTransportStatus (string) 
    /*          @CurrentSpeed (string)  => Auswahl: 1
    /*
    /****************************************************************************/
    public function GetTransportInfo($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentTransportState,CurrentTransportStatus,CurrentSpeed";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTransportInfo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetPositionInfo
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Track (ui4) 
    /*          @TrackDuration (string) 
    /*          @TrackMetaData (string) 
    /*          @TrackURI (string) 
    /*          @RelTime (string) 
    /*          @AbsTime (string) 
    /*          @RelCount (i4) 
    /*          @AbsCount (i4) 
    /*
    /****************************************************************************/
    public function GetPositionInfo($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="Track,TrackDuration,TrackMetaData,TrackURI,RelTime,AbsTime,RelCount,AbsCount";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetPositionInfo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetDeviceCapabilities
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @PlayMedia (string) 
    /*          @RecMedia (string) 
    /*          @RecQualityModes (string) 
    /*
    /****************************************************************************/
    public function GetDeviceCapabilities($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="PlayMedia,RecMedia,RecQualityModes";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetDeviceCapabilities',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetTransportSettings
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @PlayMode (string)  => Auswahl: NORMAL|REPEAT_ALL|SHUFFLE_NOREPEAT|SHUFFLE
    /*          @RecQualityMode (string) 
    /*
    /****************************************************************************/
    public function GetTransportSettings($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="PlayMode,RecQualityMode";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetTransportSettings',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetCrossfadeMode
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CrossfadeMode (boolean) 
    /*
    /****************************************************************************/
    public function GetCrossfadeMode($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CrossfadeMode";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetCrossfadeMode',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Stop
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Stop($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Stop',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Play
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Speed (string) Vorgabe = 1  => Auswahl: 1
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Play($InstanceID=0, $Speed=1){
        $args="<InstanceID>$InstanceID</InstanceID><Speed>$Speed</Speed>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Play',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Pause
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Pause($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Pause',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Seek
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Unit (string)  => Auswahl: TRACK_NR|REL_TIME|SECTION
    /*          @Target (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Seek($InstanceID=0, $Unit, $Target){
        $args="<InstanceID>$InstanceID</InstanceID><Unit>$Unit</Unit><Target>$Target</Target>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Seek',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Next
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Next($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Next',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : NextProgrammedRadioTracks
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function NextProgrammedRadioTracks($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'NextProgrammedRadioTracks',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Previous
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Previous($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Previous',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : NextSection
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function NextSection($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'NextSection',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : PreviousSection
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function PreviousSection($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'PreviousSection',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetPlayMode
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @NewPlayMode (string)  => Auswahl: NORMAL|REPEAT_ALL|SHUFFLE_NOREPEAT|SHUFFLE
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetPlayMode($InstanceID=0, $NewPlayMode){
        $args="<InstanceID>$InstanceID</InstanceID><NewPlayMode>$NewPlayMode</NewPlayMode>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetPlayMode',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetCrossfadeMode
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @CrossfadeMode (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetCrossfadeMode($InstanceID=0, $CrossfadeMode){
        $args="<InstanceID>$InstanceID</InstanceID><CrossfadeMode>$CrossfadeMode</CrossfadeMode>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetCrossfadeMode',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : NotifyDeletedURI
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DeletedURI (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function NotifyDeletedURI($InstanceID=0, $DeletedURI){
        $args="<InstanceID>$InstanceID</InstanceID><DeletedURI>$DeletedURI</DeletedURI>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'NotifyDeletedURI',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetCurrentTransportActions
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @Actions (string) 
    /*
    /****************************************************************************/
    public function GetCurrentTransportActions($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="Actions";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetCurrentTransportActions',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : BecomeCoordinatorOfStandaloneGroup
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function BecomeCoordinatorOfStandaloneGroup($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'BecomeCoordinatorOfStandaloneGroup',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : DelegateGroupCoordinationTo
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @NewCoordinator (string) 
    /*          @RejoinGroup (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function DelegateGroupCoordinationTo($InstanceID=0, $NewCoordinator, $RejoinGroup){
        $args="<InstanceID>$InstanceID</InstanceID><NewCoordinator>$NewCoordinator</NewCoordinator><RejoinGroup>$RejoinGroup</RejoinGroup>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'DelegateGroupCoordinationTo',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : BecomeGroupCoordinator
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @CurrentCoordinator (string) 
    /*          @CurrentGroupID (string) 
    /*          @OtherMembers (string) 
    /*          @TransportSettings (string) 
    /*          @CurrentURI (string) 
    /*          @CurrentURIMetaData (string) 
    /*          @SleepTimerState (string) 
    /*          @AlarmState (string) 
    /*          @StreamRestartState (string) 
    /*          @CurrentQueueTrackList (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function BecomeGroupCoordinator($InstanceID=0, $CurrentCoordinator, $CurrentGroupID, $OtherMembers, $TransportSettings, $CurrentURI, $CurrentURIMetaData, $SleepTimerState, $AlarmState, $StreamRestartState, $CurrentQueueTrackList){
        $args="<InstanceID>$InstanceID</InstanceID><CurrentCoordinator>$CurrentCoordinator</CurrentCoordinator><CurrentGroupID>$CurrentGroupID</CurrentGroupID><OtherMembers>$OtherMembers</OtherMembers><TransportSettings>$TransportSettings</TransportSettings><CurrentURI>$CurrentURI</CurrentURI><CurrentURIMetaData>$CurrentURIMetaData</CurrentURIMetaData><SleepTimerState>$SleepTimerState</SleepTimerState><AlarmState>$AlarmState</AlarmState><StreamRestartState>$StreamRestartState</StreamRestartState><CurrentQueueTrackList>$CurrentQueueTrackList</CurrentQueueTrackList>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'BecomeGroupCoordinator',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : BecomeGroupCoordinatorAndSource
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @CurrentCoordinator (string) 
    /*          @CurrentGroupID (string) 
    /*          @OtherMembers (string) 
    /*          @CurrentURI (string) 
    /*          @CurrentURIMetaData (string) 
    /*          @SleepTimerState (string) 
    /*          @AlarmState (string) 
    /*          @StreamRestartState (string) 
    /*          @CurrentAVTTrackList (string) 
    /*          @CurrentQueueTrackList (string) 
    /*          @CurrentSourceState (string) 
    /*          @ResumePlayback (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function BecomeGroupCoordinatorAndSource($InstanceID=0, $CurrentCoordinator, $CurrentGroupID, $OtherMembers, $CurrentURI, $CurrentURIMetaData, $SleepTimerState, $AlarmState, $StreamRestartState, $CurrentAVTTrackList, $CurrentQueueTrackList, $CurrentSourceState, $ResumePlayback){
        $args="<InstanceID>$InstanceID</InstanceID><CurrentCoordinator>$CurrentCoordinator</CurrentCoordinator><CurrentGroupID>$CurrentGroupID</CurrentGroupID><OtherMembers>$OtherMembers</OtherMembers><CurrentURI>$CurrentURI</CurrentURI><CurrentURIMetaData>$CurrentURIMetaData</CurrentURIMetaData><SleepTimerState>$SleepTimerState</SleepTimerState><AlarmState>$AlarmState</AlarmState><StreamRestartState>$StreamRestartState</StreamRestartState><CurrentAVTTrackList>$CurrentAVTTrackList</CurrentAVTTrackList><CurrentQueueTrackList>$CurrentQueueTrackList</CurrentQueueTrackList><CurrentSourceState>$CurrentSourceState</CurrentSourceState><ResumePlayback>$ResumePlayback</ResumePlayback>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'BecomeGroupCoordinatorAndSource',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ChangeCoordinator
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @CurrentCoordinator (string) 
    /*          @NewCoordinator (string) 
    /*          @NewTransportSettings (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ChangeCoordinator($InstanceID=0, $CurrentCoordinator, $NewCoordinator, $NewTransportSettings){
        $args="<InstanceID>$InstanceID</InstanceID><CurrentCoordinator>$CurrentCoordinator</CurrentCoordinator><NewCoordinator>$NewCoordinator</NewCoordinator><NewTransportSettings>$NewTransportSettings</NewTransportSettings>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ChangeCoordinator',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ChangeTransportSettings
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @NewTransportSettings (string) 
    /*          @CurrentAVTransportURI (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ChangeTransportSettings($InstanceID=0, $NewTransportSettings, $CurrentAVTransportURI){
        $args="<InstanceID>$InstanceID</InstanceID><NewTransportSettings>$NewTransportSettings</NewTransportSettings><CurrentAVTransportURI>$CurrentAVTransportURI</CurrentAVTransportURI>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ChangeTransportSettings',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ConfigureSleepTimer
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @NewSleepTimerDuration (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function ConfigureSleepTimer($InstanceID=0, $NewSleepTimerDuration){
        $args="<InstanceID>$InstanceID</InstanceID><NewSleepTimerDuration>$NewSleepTimerDuration</NewSleepTimerDuration>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ConfigureSleepTimer',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetRemainingSleepTimerDuration
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @RemainingSleepTimerDuration (string) 
    /*          @CurrentSleepTimerGeneration (ui4) 
    /*
    /****************************************************************************/
    public function GetRemainingSleepTimerDuration($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="RemainingSleepTimerDuration,CurrentSleepTimerGeneration";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetRemainingSleepTimerDuration',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RunAlarm
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @AlarmID (ui4) 
    /*          @LoggedStartTime (string) 
    /*          @Duration (string) 
    /*          @ProgramURI (string) 
    /*          @ProgramMetaData (string) 
    /*          @PlayMode (string)  => Auswahl: NORMAL|REPEAT_ALL|SHUFFLE_NOREPEAT|SHUFFLE
    /*          @Volume (ui2) 
    /*          @IncludeLinkedZones (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function RunAlarm($InstanceID=0, $AlarmID, $LoggedStartTime, $Duration, $ProgramURI, $ProgramMetaData, $PlayMode, $Volume, $IncludeLinkedZones){
        $args="<InstanceID>$InstanceID</InstanceID><AlarmID>$AlarmID</AlarmID><LoggedStartTime>$LoggedStartTime</LoggedStartTime><Duration>$Duration</Duration><ProgramURI>$ProgramURI</ProgramURI><ProgramMetaData>$ProgramMetaData</ProgramMetaData><PlayMode>$PlayMode</PlayMode><Volume>$Volume</Volume><IncludeLinkedZones>$IncludeLinkedZones</IncludeLinkedZones>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RunAlarm',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : StartAutoplay
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @ProgramURI (string) 
    /*          @ProgramMetaData (string) 
    /*          @Volume (ui2) 
    /*          @IncludeLinkedZones (boolean) 
    /*          @ResetVolumeAfter (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function StartAutoplay($InstanceID=0, $ProgramURI, $ProgramMetaData, $Volume, $IncludeLinkedZones, $ResetVolumeAfter){
        $args="<InstanceID>$InstanceID</InstanceID><ProgramURI>$ProgramURI</ProgramURI><ProgramMetaData>$ProgramMetaData</ProgramMetaData><Volume>$Volume</Volume><IncludeLinkedZones>$IncludeLinkedZones</IncludeLinkedZones><ResetVolumeAfter>$ResetVolumeAfter</ResetVolumeAfter>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'StartAutoplay',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetRunningAlarmProperties
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @AlarmID (ui4) 
    /*          @GroupID (string) 
    /*          @LoggedStartTime (string) 
    /*
    /****************************************************************************/
    public function GetRunningAlarmProperties($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="AlarmID,GroupID,LoggedStartTime";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetRunningAlarmProperties',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SnoozeAlarm
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Duration (string) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SnoozeAlarm($InstanceID=0, $Duration){
        $args="<InstanceID>$InstanceID</InstanceID><Duration>$Duration</Duration>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SnoozeAlarm',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : Queue 
/*  Service: urn:schemas-sonos-com:service:Queue:1
/*	     Id: urn:sonos-com:serviceId:Queue 
/*##########################################################################*/
class SonosQueue extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-sonos-com:service:Queue:1';
    protected $SERVICEURL='/MediaRenderer/Queue/Control';
    protected $EVENTURL='/MediaRenderer/Queue/Event';
    /***************************************************************************
    /* Funktion : AddURI
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @UpdateID (ui4) 
    /*          @EnqueuedURI (string) 
    /*          @EnqueuedURIMetaData (string) 
    /*          @DesiredFirstTrackNumberEnqueued (ui4) 
    /*          @EnqueueAsNext (boolean) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @FirstTrackNumberEnqueued (ui4) 
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function AddURI($QueueID, $UpdateID, $EnqueuedURI, $EnqueuedURIMetaData, $DesiredFirstTrackNumberEnqueued, $EnqueueAsNext){
        $args="<QueueID>$QueueID</QueueID><UpdateID>$UpdateID</UpdateID><EnqueuedURI>$EnqueuedURI</EnqueuedURI><EnqueuedURIMetaData>$EnqueuedURIMetaData</EnqueuedURIMetaData><DesiredFirstTrackNumberEnqueued>$DesiredFirstTrackNumberEnqueued</DesiredFirstTrackNumberEnqueued><EnqueueAsNext>$EnqueueAsNext</EnqueueAsNext>";
        $filter="FirstTrackNumberEnqueued,NumTracksAdded,NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddURI',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AddMultipleURIs
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @UpdateID (ui4) 
    /*          @ContainerURI (string) 
    /*          @ContainerMetaData (string) 
    /*          @DesiredFirstTrackNumberEnqueued (ui4) 
    /*          @EnqueueAsNext (boolean) 
    /*          @NumberOfURIs (ui4) 
    /*          @EnqueuedURIsAndMetaData (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @FirstTrackNumberEnqueued (ui4) 
    /*          @NumTracksAdded (ui4) 
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function AddMultipleURIs($QueueID, $UpdateID, $ContainerURI, $ContainerMetaData, $DesiredFirstTrackNumberEnqueued, $EnqueueAsNext, $NumberOfURIs, $EnqueuedURIsAndMetaData){
        $args="<QueueID>$QueueID</QueueID><UpdateID>$UpdateID</UpdateID><ContainerURI>$ContainerURI</ContainerURI><ContainerMetaData>$ContainerMetaData</ContainerMetaData><DesiredFirstTrackNumberEnqueued>$DesiredFirstTrackNumberEnqueued</DesiredFirstTrackNumberEnqueued><EnqueueAsNext>$EnqueueAsNext</EnqueueAsNext><NumberOfURIs>$NumberOfURIs</NumberOfURIs><EnqueuedURIsAndMetaData>$EnqueuedURIsAndMetaData</EnqueuedURIsAndMetaData>";
        $filter="FirstTrackNumberEnqueued,NumTracksAdded,NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AddMultipleURIs',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : AttachQueue
    /* 
    /*  Benoetigt:
    /*          @QueueOwnerID (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @QueueID (ui4) 
    /*          @QueueOwnerContext (string) 
    /*
    /****************************************************************************/
    public function AttachQueue($QueueOwnerID){
        $args="<QueueOwnerID>$QueueOwnerID</QueueOwnerID>";
        $filter="QueueID,QueueOwnerContext";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'AttachQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Backup
    /* 
    /*  Benoetigt: Nichts
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function Backup(){
        $args="";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Backup',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : Browse
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @StartingIndex (ui4) 
    /*          @RequestedCount (ui4) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @Result (string) 
    /*          @NumberReturned (ui4) 
    /*          @TotalMatches (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /****************************************************************************/
    public function Browse($QueueID, $StartingIndex, $RequestedCount){
        $args="<QueueID>$QueueID</QueueID><StartingIndex>$StartingIndex</StartingIndex><RequestedCount>$RequestedCount</RequestedCount>";
        $filter="Result,NumberReturned,TotalMatches,UpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'Browse',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : CreateQueue
    /* 
    /*  Benoetigt:
    /*          @QueueOwnerID (string) 
    /*          @QueueOwnerContext (string) 
    /*          @QueuePolicy (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @QueueID (ui4) 
    /*
    /****************************************************************************/
    public function CreateQueue($QueueOwnerID, $QueueOwnerContext, $QueuePolicy){
        $args="<QueueOwnerID>$QueueOwnerID</QueueOwnerID><QueueOwnerContext>$QueueOwnerContext</QueueOwnerContext><QueuePolicy>$QueuePolicy</QueuePolicy>";
        $filter="QueueID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'CreateQueue',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveAllTracks
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function RemoveAllTracks($QueueID, $UpdateID){
        $args="<QueueID>$QueueID</QueueID><UpdateID>$UpdateID</UpdateID>";
        $filter="NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveAllTracks',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : RemoveTrackRange
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @UpdateID (ui4) 
    /*          @StartingIndex (ui4) 
    /*          @NumberOfTracks (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function RemoveTrackRange($QueueID, $UpdateID, $StartingIndex, $NumberOfTracks){
        $args="<QueueID>$QueueID</QueueID><UpdateID>$UpdateID</UpdateID><StartingIndex>$StartingIndex</StartingIndex><NumberOfTracks>$NumberOfTracks</NumberOfTracks>";
        $filter="NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'RemoveTrackRange',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReorderTracks
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @StartingIndex (ui4) 
    /*          @NumberOfTracks (ui4) 
    /*          @InsertBefore (ui4) 
    /*          @UpdateID (ui4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function ReorderTracks($QueueID, $StartingIndex, $NumberOfTracks, $InsertBefore, $UpdateID){
        $args="<QueueID>$QueueID</QueueID><StartingIndex>$StartingIndex</StartingIndex><NumberOfTracks>$NumberOfTracks</NumberOfTracks><InsertBefore>$InsertBefore</InsertBefore><UpdateID>$UpdateID</UpdateID>";
        $filter="NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReorderTracks',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : ReplaceAllTracks
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @UpdateID (ui4) 
    /*          @ContainerURI (string) 
    /*          @ContainerMetaData (string) 
    /*          @CurrentTrackIndex (ui4) 
    /*          @NewCurrentTrackIndices (string) 
    /*          @NumberOfURIs (ui4) 
    /*          @EnqueuedURIsAndMetaData (string) 
    /*
    /*  Liefert als Ergebnis: Array mit folgenden Keys
    /*          @NewQueueLength (ui4) 
    /*          @NewUpdateID (ui4) 
    /*
    /****************************************************************************/
    public function ReplaceAllTracks($QueueID, $UpdateID, $ContainerURI, $ContainerMetaData, $CurrentTrackIndex, $NewCurrentTrackIndices, $NumberOfURIs, $EnqueuedURIsAndMetaData){
        $args="<QueueID>$QueueID</QueueID><UpdateID>$UpdateID</UpdateID><ContainerURI>$ContainerURI</ContainerURI><ContainerMetaData>$ContainerMetaData</ContainerMetaData><CurrentTrackIndex>$CurrentTrackIndex</CurrentTrackIndex><NewCurrentTrackIndices>$NewCurrentTrackIndices</NewCurrentTrackIndices><NumberOfURIs>$NumberOfURIs</NumberOfURIs><EnqueuedURIsAndMetaData>$EnqueuedURIsAndMetaData</EnqueuedURIsAndMetaData>";
        $filter="NewQueueLength,NewUpdateID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'ReplaceAllTracks',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SaveAsSonosPlaylist
    /* 
    /*  Benoetigt:
    /*          @QueueID (ui4) 
    /*          @Title (string) 
    /*          @ObjectID (string) 
    /*
    /*  Liefert als Ergebnis:
    /*          @AssignedObjectID (string) 
    /*
    /****************************************************************************/
    public function SaveAsSonosPlaylist($QueueID, $Title, $ObjectID){
        $args="<QueueID>$QueueID</QueueID><Title>$Title</Title><ObjectID>$ObjectID</ObjectID>";
        $filter="AssignedObjectID";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SaveAsSonosPlaylist',$args,$filter);
    }}

/*##########################################################################*/
/*  Class  : GroupRenderingControl 
/*  Service: urn:schemas-upnp-org:service:GroupRenderingControl:1
/*	     Id: urn:upnp-org:serviceId:GroupRenderingControl 
/*##########################################################################*/
class SonosGroupRenderingControl extends SonosUpnpClass {
    protected $SERVICE='urn:schemas-upnp-org:service:GroupRenderingControl:1';
    protected $SERVICEURL='/MediaRenderer/GroupRenderingControl/Control';
    protected $EVENTURL='/MediaRenderer/GroupRenderingControl/Event';
    /***************************************************************************
    /* Funktion : GetGroupMute
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentMute (boolean) 
    /*
    /****************************************************************************/
    public function GetGroupMute($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentMute";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetGroupMute',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetGroupMute
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DesiredMute (boolean) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetGroupMute($InstanceID=0, $DesiredMute){
        $args="<InstanceID>$InstanceID</InstanceID><DesiredMute>$DesiredMute</DesiredMute>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetGroupMute',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : GetGroupVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis:
    /*          @CurrentVolume (ui2) 
    /*
    /****************************************************************************/
    public function GetGroupVolume($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="CurrentVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'GetGroupVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetGroupVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @DesiredVolume (ui2) 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SetGroupVolume($InstanceID=0, $DesiredVolume){
        $args="<InstanceID>$InstanceID</InstanceID><DesiredVolume>$DesiredVolume</DesiredVolume>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetGroupVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SetRelativeGroupVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*          @Adjustment (i4) 
    /*
    /*  Liefert als Ergebnis:
    /*          @NewVolume (ui2) 
    /*
    /****************************************************************************/
    public function SetRelativeGroupVolume($InstanceID=0, $Adjustment){
        $args="<InstanceID>$InstanceID</InstanceID><Adjustment>$Adjustment</Adjustment>";
        $filter="NewVolume";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SetRelativeGroupVolume',$args,$filter);
    }
    /***************************************************************************
    /* Funktion : SnapshotGroupVolume
    /* 
    /*  Benoetigt:
    /*          @InstanceID (ui4) Vorgabe = 0 
    /*
    /*  Liefert als Ergebnis: Nichts
    /*
    /****************************************************************************/
    public function SnapshotGroupVolume($InstanceID=0){
        $args="<InstanceID>$InstanceID</InstanceID>";
        $filter="";
        return $this->BASE->Upnp($this->SERVICEURL,$this->SERVICE,'SnapshotGroupVolume',$args,$filter);
    }}

?>