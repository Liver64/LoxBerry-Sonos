<?php
declare(strict_types=1);

/**
 * Sonos4Lox - TemplateBuilder.php
 * Loxone virtual input/output XML template builder.
 * Version: TEMPLATE_BUILDER_CORE_COMMUNICATION_RELOCATION_V02_2026_06_13
 *
 * Relocated from system/bin/loxberry_loxonetemplatebuilder.php.
 * Class names are intentionally kept for compatibility with existing MsInbound.php logic.
 */


if (!function_exists('s4lox_templatebuilder_value')) {
    /**
     * Convert optional template values into safe XML attribute strings.
     * Missing values are intentionally rendered as empty strings to keep
     * compatibility with older templates that omitted optional attributes.
     */
    function s4lox_templatebuilder_value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }
}

if (!function_exists('s4lox_templatebuilder_attr')) {
    function s4lox_templatebuilder_attr($value): string
    {
        return htmlspecialchars(
            s4lox_templatebuilder_value($value),
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('s4lox_templatebuilder_prop')) {
    function s4lox_templatebuilder_prop($object, string $property, $default = '')
    {
        if (is_object($object) && property_exists($object, $property)) {
            return $object->$property;
        }

        return $default;
    }
}

class LoxoneTemplateBuilder 
{ 
	public $VERSION = "2.0.0.3";
	public $DEBUG = 0;

	function __construct( $params ) {
		
		if(!is_array($params)) {
			throw new Exception('params need to be an array (key / value)');
		}
		
		// Default values of class
		$this->PollingTime = 60;
		$this->CloseAfterSend = "true";
		
		// Parse parameters
		foreach ($params as $param => $val) {
			switch ($param) {
				case "CloseAfterSend": $this->$param = isset($val) && ( $val == false || $val === "false" ) ? "false" : "true"; break;
				default:  $this->$param = $val; 
			}
		}
		
		$this->IOcmd = array ( );
	}
	
	function addIOCmd ( $params ) {
		
		if(!is_array($params)) {
			return $this->IOcmd[$params-1];
		}
		$count = count($this->IOcmd);
		$this->IOcmd[$count] = new IOCmd( $params );
		return count($this->IOcmd);
	}

	function delete( int $lineno ) {
		$lineno = $lineno-1;
		$this->IOcmd[$lineno]->_deleted = true;
	}
	
	function output() {
		
		$crlf = "\r\n";
		$encflags = ENT_XML1|ENT_QUOTES;
		$id = 0;
		
		$o = '<?xml version="1.0" encoding="utf-8"?>'.$crlf;
		$class = get_class($this);
		
		if ($class == "VirtualInHttp") {
			$o .= '<VirtualInHttp ';
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'Address="http://'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Address')).'" ';
			$o .= 'PollingTime="'.$this->PollingTime.'"';
			$o .= '>'.$crlf;
		} elseif ($class == "VirtualInUdp") {
			$o .= '<VirtualInUdp ';
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'Address="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Address')).'" ';
			$o .= 'Port="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Port')).'"';
			$o .= '>'.$crlf;
		} elseif ($class == "VirtualOut") {
			$o .= '<VirtualOut ';
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'Address="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Address')).'" ';
			$o .= 'CmdInit="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdInit')).'" ';
			$o .= 'CloseAfterSend="'.$this->CloseAfterSend.'" ';
			$o .= 'CmdSep="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdSep')).'" ';
			$o .= '>'.$crlf;
		}
		
		foreach( $this->IOcmd as $Cmd ) {
			$o .= $Cmd->getXmlOutput($id);
			$id++;
		}
		
		if ($class == "VirtualInHttp") {
			$o .= '</VirtualInHttp>'.$crlf;
		} elseif ($class == "VirtualInUdp") {
			$o .= '</VirtualInUdp>'.$crlf;
		} elseif ($class == "VirtualOut") {
			$o .= '</VirtualOut>'.$crlf;
		}
		return $o;
	}
}
		
class IOCmd 
{
	function __construct( array $params ) {
		
		$backtrace = debug_backtrace();
		$this->_type = $backtrace[2]['class'];
		
		// Default values of command
		$this->_deleted = false;
		$this->Signed = "true";
		$this->Analog = "true";
		$this->SourceValLow = "0";
		$this->DestValLow = "0";
		$this->SourceValHigh = "100";
		$this->DestValHigh = "100";
		$this->DefVal = "0";
		$this->MinVal = "-2147483647";
		$this->MaxVal = "2147483647";
		$this->CmdOnMethod = "GET";
		$this->CmdOffMethod = "GET";
		$this->Repeat = "0";
		$this->RepeatRate = "0";
		
		foreach ($params as $param => $val) {
			switch ($param) {
				case "Signed": 
				case "Analog": $this->$param = isset($val) && ( $val == false || $val === "false" ) ? "false" : "true"; break;
				default: $this->$param = $val;
			}
		}
	}

	function delete() {
		$this->_deleted = true;
	}
	
	function getXmlOutput(int $xmlSetID = null) {
		
		if($this->_deleted) {
			return;
		}
		
		$encflags = ENT_XML1|ENT_QUOTES;
		
		$o = "";
		$crlf = "\r\n";
		if ($this->_type == "VirtualInHttp") {
			$o .= "\t".'<VirtualInHttpCmd ';
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'Check="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Check')).'" ';
			$o .= 'Signed="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Signed', 'true')).'" ';
			$o .= 'Analog="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Analog', 'true')).'" ';
			$o .= 'SourceValLow="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'SourceValLow', '0')).'" ';
			$o .= 'DestValLow="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DestValLow', '0')).'" ';
			$o .= 'SourceValHigh="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'SourceValHigh', '100')).'" ';
			$o .= 'DestValHigh="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DestValHigh', '100')).'" ';
			$o .= 'DefVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DefVal', '0')).'" ';
			$o .= 'MinVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'MinVal', '-2147483647')).'" ';
			$o .= 'MaxVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'MaxVal', '2147483647')).'"';
			$o .= '/>'.$crlf;	
		}
		
		elseif ($this->_type == "VirtualInUdp") {
			$o .= "\t".'<VirtualInUdpCmd ';
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'Address="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Address')).'" ';
			$o .= 'Check="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Check')).'" ';
			$o .= 'Signed="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Signed', 'true')).'" ';
			$o .= 'Analog="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Analog', 'true')).'" ';
			$o .= 'SourceValLow="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'SourceValLow', '0')).'" ';
			$o .= 'DestValLow="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DestValLow', '0')).'" ';
			$o .= 'SourceValHigh="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'SourceValHigh', '100')).'" ';
			$o .= 'DestValHigh="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DestValHigh', '100')).'" ';
			$o .= 'DefVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'DefVal', '0')).'" ';
			$o .= 'MinVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'MinVal', '-2147483647')).'" ';
			$o .= 'MaxVal="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'MaxVal', '2147483647')).'"';
			$o .= '/>'.$crlf;	
		}
		
		elseif ($this->_type == "VirtualOut") {
			$o .= "\t".'<VirtualOutCmd ';
			
			if(isset($xmlSetID)) {
				$o .= 'ID="'.$xmlSetID.'" ';
			}
			$o .= 'Title="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Title')).'" ';
			$o .= 'Comment="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Comment')).'" ';
			$o .= 'CmdOnMethod="'.strtoupper(s4lox_templatebuilder_value(s4lox_templatebuilder_prop($this, 'CmdOnMethod', 'GET'))).'" ';
			$o .= 'CmdOn="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOn')).'" ';
			$o .= 'CmdOnHTTP="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOnHTTP')).'" ';
			$o .= 'CmdOnPost="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOnPost')).'" ';
			$o .= 'CmdOffMethod="'.strtoupper(s4lox_templatebuilder_value(s4lox_templatebuilder_prop($this, 'CmdOffMethod', 'GET'))).'" ';
			$o .= 'CmdOff="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOff')).'" ';
			$o .= 'CmdOffHTTP="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOffHTTP')).'" ';
			$o .= 'CmdOffPost="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'CmdOffPost')).'" ';
			$o .= 'Analog="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Analog', 'true')).'" ';
			$o .= 'Repeat="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'Repeat', '0')).'" ';
			$o .= 'RepeatRate="'.s4lox_templatebuilder_attr(s4lox_templatebuilder_prop($this, 'RepeatRate', '0')).'"';
			$o .= '/>'.$crlf;	
		}
		
		else {
			throw new Exception($this->_type . " is an unknown IO type");
		}
		
		return($o);
	}

}

// Virtual HTTP Inputs
class VirtualInHttp extends LoxoneTemplateBuilder 
{
	function VirtualInHttpCmd( $params ) {
		return parent::addIOCmd( $params );
	}
}

// Virtual UDP Inputs
class VirtualInUdp extends LoxoneTemplateBuilder 
{
	function VirtualInUdpCmd( $params ) {
		return parent::addIOCmd( $params );
	}
}

// Virtual Outputs
class VirtualOut extends LoxoneTemplateBuilder 
{
	function VirtualOutCmd( $params ) {
		return parent::addIOCmd( $params );
	}
}
