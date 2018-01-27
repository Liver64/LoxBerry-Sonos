<?php
  /**
  * XN-DEBUG FUNCTION
  *
  * @package     xn-debug
  * @author      Rouven Volk <rvolk@users.sourceforge.net>
  * @link        http://xn-debug.sourceforge.net
  * @copyright   Rouven Volk © 2005-2010
  * @license     GNU General Public License version 3.0 (GPLv3)
  * @svn         $Revision: 54 $
  * @version     1.3.1 beta
  *
  * This debugger is build with serveral interacting technologies. The core
  * element is a PHP based function, which can be integrated into the
  * application files. This function can be used to inspect PHP elements at
  * nearly every point of code. Finally the function will be executed again as
  * shutdown function after the application is finished. At this point an HTML
  * output is generated and placed at the end of the server response to the
  * browser.
  *
  * We aim to fulfil the needs of PHP development by testing the application in
  * Google’s Chrome Browser and Mozilla Firefox, because we consider them the
  * most common browsers in web development. The application will run in
  * Microsoft’s Internet Explorer as well, but this browser is not supported
  * primarily.
  *
  * Once the HTML snipped is executed, a virtual debugging window is displayed.
  * Styling is based on a dedicated CSS file and a handful of JavaScript
  * functions.
  *
  * Use the following code to activate the debugger:
  *
  * require_once('function.debug.php');
  * __debug(true);
  *
  * Remember that __debug() must be called before any actual output is sent,
  * either by normal HTML tags, blank lines in a file, or from PHP. The
  * debugger will be disabled, if the first parameter of the first call
  * is not boolean true!
  *
  */

  /**
  * __debug(mixed $var [,string $title])
  *
  * This function collect informations about the given variable and paste them
  * to the later programs output. It takes an optional secondary argument for
  * the title, which will be a automatic generated if no string is given. The
  * return value will be a reference to the origin variable, which will be not
  * modified.
  *
  * @param   mixed    $var      anything you want
  * @param   string   $title    title
  * @return  mixed    $var      return the origin input for inline use
  */
  function &__debug($var=NULL,$title=NULL){

    #  global identifier
    $gid = strtoupper(__FUNCTION__.md5(__FILE__));

    #  define debug mode on first run, if $var is bool
    if(!isset($GLOBALS[$gid]) && !defined($gid))
      define($gid,defined(strtoupper(__FUNCTION__))?(bool)constant(strtoupper(__FUNCTION__)):(bool)$var);


    #  Return if debug mode is not true
    if(!defined($gid) || !constant($gid) || !is_bool(constant($gid))) return $var;

    #  Get arguments
    $args = func_get_args();

    #  event
    $event = array('type'  => count($args)?((count($args) <= 2)?'debug':'errorhandler'):'output',
              'memory'  =>  function_exists('memory_get_usage')?memory_get_usage():0,
              'desc'  => "",
              'data'  => array()
            );

    #  title
    if(!is_null($title) && !is_string($title)) $title = null;

    #  Initialisation
    if(!isset($GLOBALS[$gid])){
      if(version_compare(PHP_VERSION, "4.3.0", "<"))
        die("The debugger requires at least PHP 4.3.0!");
      if(headers_sent())
        die("Headers already sent, __debug() must be called before any actual output is sent, either by normal HTML tags, blank lines in a file, or from PHP.");
      $cfg = array( 'maxTitleLength'        => 100,
                    'maxParamLength'        => 40,
                    'maxErrors'             => 30,
                    'maxMessages'           => 30,
                    'maxMessageLength'      => 32000,
                    'maxMessageGroupCount'  => 100,
                    'maxLineLength'         => 80,
                    'maxFileSize'           => 51200,
                    'maxMemory'             =>  '25%',
                    'globalIdentifier'      =>  $gid,
                    'functionName'          => __FUNCTION__,
                    'outputTitle'           => is_string($title)?preg_replace('~[^\w/\.]+~','',$title):NULL,
                    'outputHandler'         => 'html',
                    'outputIdentifier'      => NULL,
                    'outputSections'        => array('errors','messages','search','system','history','performance','browse','source','info','doc'),
                    'outputSystemVars'      => '_GET,_POST,_COOKIE,_REQUEST,_FILES,_SESSION,_HEADERS,_SERVER,_ENV,_CLASSES,_INTERFACES,_INCLUDED,_PROTOCOLS,_USAGE', //,_FUNCTIONS
                    'outputBaseDir'         =>  @dirname(array_shift(get_included_files())),
                    'searchLimit'           => 1000,
                    'searchDepth'           => 10000,
                    'searchTimelimit'       => 10,
                    'timerPrecision'        => 4,
                    'diagramHeight'         => 150);
      $GLOBALS[$gid] = array('cfg'=>$cfg,'ini'=>array(),'references'=>array(),'stats'=>array());
      #  register itself as shutdown function
      register_shutdown_function(__FUNCTION__);

      $event['type'] = 'init';
      $event['desc'] = "Initialized ".__FUNCTION__." function, starting time and event tracking.";

      #  capture debug specific http requests
      if(isset($_REQUEST[$gid]) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'],$gid) !== false)){
        while(ob_get_length()) ob_end_clean();
        if(!isset($_REQUEST[$gid])){
          $uri = explode("?",$_SERVER['REQUEST_URI']);
          parse_str(end($uri),$_REQUEST);
        }
        $rq = strtolower(isset($_REQUEST[$gid])?$_REQUEST[$gid]:"");
        switch($rq){
          case 'css':
            header("Content-type: text/css");
            $GLOBALS[$gid]['cfg']['outputHandler'] = $rq;
            break;
          case 'js':
            header("Content-type: text/javascript");
            $GLOBALS[$gid]['cfg']['outputHandler'] = 'js_'.preg_replace('~\W+~','',$_REQUEST['s']);
            break;
          case 'icon':
            header("Content-type: image/gif");
            $GLOBALS[$gid]['cfg']['outputHandler'] = $rq;
            break;
          case 'download':
            header("Expires: 0");
            header("Pragma: no-cache");
            header("Cache-Control: no-cache");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Type: application/force-download");
            header("Content-Description: File Transfer");
            header("Content-Disposition: attachment; filename=".basename(__FILE__));
            header("Content-Transfer-Encoding: binary");
            readfile(__FILE__);
            $GLOBALS[$gid]['cfg']['outputHandler'] = 'none';
            exit;
          case 'info':
          case 'doc':
          case 'source':
          case 'browse':
            $GLOBALS[$gid]['cfg']['outputHandler'] = 'ajax';
            $GLOBALS[$gid]['cfg']['outputSection'] = $rq;
            break;
          default:
            $GLOBALS[$gid]['cfg']['outputHandler'] = 'none';
        }
        exit;
      }
    }

    #  Registry
    $reg =& $GLOBALS[$gid];

    #  stop measuring of ticks in case of output (save performance if ticks are declared)
     if($event['type'] == 'output') @unregister_tick_function(__FUNCTION__);

    #  timer (use number_format instead of bcadd/bcsub for compatibility with older PHP versions
    list($usec, $sec) = explode(" ", microtime());
    $event['time'] = number_format((float)$usec+(float)$sec,$reg['cfg']['timerPrecision'],".","");
    $event['age']  = isset($reg['events'][0])?number_format((float)$event['time']-(float)$reg['events'][0]['time'],$reg['cfg']['timerPrecision'],".",""):0;

    #  register event
    $reg['events'][] =& $event;

    #  messure memory usage
    if(!isset($reg['stats']['memoryUsage']))
      $reg['stats']['memoryUsage'] = strlen(print_r($reg,true));

    #  memory check
    if(!is_numeric($reg['cfg']['maxMemory'])){
      $ml = strtolower(trim(ini_get('memory_limit')));
      switch(substr($ml,-1)) {
        case 'g': $ml *= 1024;
        case 'm': $ml *= 1024;
        case 'k': $ml *= 1024;
      }
      $reg['cfg']['maxMemory'] = preg_match('~^(100|\d{1,2})%$~',(string)$reg['cfg']['maxMemory'],$m)?floor(($ml / 100) * $m[1]):$ml;
    }

    #  return after initialisation
    if($event['type'] == 'init') return $var;

    #  Debug
    if($event['type']=='debug'){

      #  special commands
      $commands  = array( 'search'  => array('string','int','bool','object','resource','var','NULL'),
                          'config'  => array_keys($reg['cfg']),
                          'error'   => array('handler','reporting','tracing','bubble'),
                          'memory'  => array('tick'),
                          'convert' => array('php2json'),
                          'group'   => NULL
                        );

      #  identify special commands
      $value = NULL;
      if(!is_null($title) && preg_match("~^(\w+):([\w ]+)$~",$title,$m)
        && in_array($m[1],array_keys($commands)) && (is_null($commands[$m[1]]) || in_array($m[2],$commands[$m[1]]))){
        $event['type']  = $m[1];
        $method  = $m[2];
      }

      #  run command
      switch($event['type']){
        /**
        * Config
        *
        * System:
        *
        * You can select, which PHP super global variables you like to display:
        * __debug('_GET,_POST,_COOKIE,_REQUEST,_FILES,_SESSION,_SERVER,_ENV','config:outputSystemVars');
        * But there are also other useful functions mapped to this section:
        * __debug('_HEADERS,_CLASSES,_INTERFACES,_INCLUDED,_PROTOCOLS,_USAGE','config:outputSystemVars');
        * You should only select the items you need, because they can take a lot of memory.
        *
        *
        * Info:
        *
        * You can display the result of phpinfo() in this debug window. Just get
        * sure, that the 'info' section is registered, for example:
        * __debug(array('errors','messages','system','doc','info'),'config:outputSections');
        *
        *
        * Documentation:
        *
        * This section scans the sourcecode of the debug function file for comments
        * and presents his results in a scrollable textfield. You can display or
        * hide this section, when you add 'doc' to your config:outputSections.
        *
        * For example:
        * __debug(array('errors','messages','system','doc'),'config:outputSections');
        */
        case "config":
          if(!isset($reg['cfg'][$method]))
            die("Config method '{$method}' doesn't exists.");
          if(gettype($var) != gettype($reg['cfg'][$method]) && $method != 'maxMemory')
            die("Wrong type for config '{$method}', ".gettype($reg['cfg'][$method])." expected.");
          $reg['cfg'][$method] = $var;
          $event['desc'] = "Set config '{$method}' to '{$var}'";
          break;

        /**
        * Search
        *
        * You can use the search function to find a lot of variables, objects and values
        * inside of your current php memory. This function use the GLOBALS-Array to
        * get access to all defined variables. The following search types will support
        * you to find the right content.
        *
        * Search modes: var, string, int, bool, object, resource
        * Syntax: __debug((mixed) $query, "search:[mode]");
        *
        * Search variables, keys and attributes named something like 'foo':
        * __debug("foo","search:var");
        *
        * Search variables, keys and attributes named exactely 'foo':
        * __debug("~^foo$~","search:var");
        *
        * Search variables with type string and containing 'bar':
        * __debug("bar","search:string");
        *
        * Search variables with type string and containing exactely 'bar':
        * __debug("~^bar$~","search:string");
        *
        * Search variables with type string and containing something like '100':
        * __debug("100","search:string");
        *
        * Search variables containing integer value 100:
        * __debug(100,"search:int");
        *
        * Search variables with lower integer value then 100:
        * __debug("< 100","search:int");
        *
        * Search variables with highter integer value then 100:
        * __debug("> 100","search:int");
        *
        * Search variables with type bool value true:
        * __debug(true,"search:bool");
        *
        * Search variables with NULL values:
        * __debug(NULL,"search:NULL");
        * or:
        * __debug(NULL,"search:string");
        *
        * Search objects of type 'myclass':
        * __debug("myclass","search:object");
        *
        * Search resources of type 'stream':
        * __debug("stream","search:resource");
        *
        * As you could see, the search value is positioned as the first parameter
        * of the debug function and could be a scalar variable or also a regular
        * expression. The search function is activated by the second parameter.
        * You have to call the search function and define the search mode after
        * the double point.
        *
        * If you like to use the search inside of a method or function, you have
        * to consider, that current variables of this access area may not be
        * available in the global memory. But you can use this workaround as well:
        *
        * $GLOBALS['myFunctionVars'] = get_defined_vars();
        */
        case "search":
          $pattern = $var;
          $operator = NULL;
          $highlight = $pattern;

          if(is_null($pattern)) $method = "NULL";

          if((!is_scalar($pattern) && !is_null($pattern)) || !is_scalar($method))
            die("Invalid parameter for search function.");

          switch($method){
            case "bool":
              if(!is_bool($pattern)) die("search:bool expects bool value.");
              $highlight = $pattern?"TRUE":"FALSE";
              break;
            case "int":
              if(!preg_match("~^([<>]?)\s?(\-?\d+\.?\d*)$~i",$pattern,$m))
                die("search:bool expects bool value.");
              $operator = $m[1]?$m[1]:"==";
              $pattern = $m[2];
              break;
            case "NULL":
              $pattern = "";
              $highlight = "NULL";
              break;
            default:
              if(!preg_match("~^([^\d\w\\\\])(.*)(\\1)([imsxeADSUxu]*)$~",$pattern))
                $pattern = !strlen($pattern) && $method == "string"?"~^$~":"~.*".str_replace('~','\~',preg_quote($pattern)).".*~i";
          }
          $stack = array();
          $results = array();
          foreach($GLOBALS as $k=>$v){
            if(in_array($k,array('GLOBALS',$gid))) continue;
            $stack[] = array('$'.$k,$v);
          }

          while(($sv = array_pop($stack)) && count($results) < $reg['cfg']['searchLimit']){
            if(is_array($sv[1]) || is_object($sv[1])){
              if($method == "object" && is_object($sv[1]) && preg_match($pattern,get_class($sv[1])))
                $results[] = $sv[0]." = ".get_class($sv[1]);
              foreach($sv[1] as $k=>$v){
                if($method == "var" && preg_match($pattern,$k))
                  $results[] = $sv[0].(is_array($sv[1])?"['$k']":"->{$k}")." = (".gettype($v).")";
                $stack[] = array($sv[0].(is_array($sv[1])?"['{$k}']":"->{$k}"),$v);
              }
            }else{
              switch($method){
                case "bool":
                  if($sv[1] === $pattern) $results[] = $sv[0]." = ".($sv[1]?"TRUE":"FALSE");
                  break;
                case "int":
                  if(!is_numeric($sv[1])) continue;
                  if(eval("return \$sv[1] {$operator} {$pattern};"))
                    $results[] = $sv[0]." = ".$sv[1];
                  break;
                case "resource":
                  if(is_resource($sv[1]) && preg_match($pattern,get_resource_type($sv[1])))
                    $results[] = $sv[0]." = ".get_resource_type($sv[1]);
                  break;
                case "NULL":
                  if(is_null($sv[1])) $results[] = $sv[0]." = NULL";
                  break;
                case "string":
                  if(preg_match_all($pattern,substr($sv[1],0,$reg['cfg']['searchDepth']),$m)) $results[] = "{$sv[0]} = '{$m[0][0]}'";
                    elseif(strlen($sv[1]) > $reg['cfg']['searchDepth']) $results[] = "// Nothing found within depth of {$reg['cfg']['searchDepth']} chars!";
                  break;
              }
            }
          }
          if(count($stack)) $results[] = "// Search limit reached.";

          $reg[$event['type']][] = array('id'=>__FUNCTION__."_search_".(isset($reg[$event['type']])?count($reg[$event['type']]):0),'type'=>$method,'pattern'=>$pattern,'results'=>$results,'highlight'=>$highlight);
          $event['desc'] = "Searched {$method} with '{$highlight}', found ".count($results)." results.";
          $event['data'] = array('id'=>__FUNCTION__."_search_".(count($reg[$event['type']])-1),'type'=>$method,'pattern'=>$pattern,'results'=>$results,'highlight'=>$highlight);
          $reg['stats']['memoryUsage'] += strlen(print_r($event,true));
          break;



        /**
        * Errors
        *
        * Use this code to activate the integrated error handler:
        * __debug(true,"error:handler");
        *
        * Set error reporting to the required level:
        * __debug(E_ALL ^ E_STRICT,"error:reporting");
        *
        * Activate tracing for this level:
        * __debug(E_ALL ^ E_NOTICE,"error:tracing");
        *
        */
        case "error":
          if($method == "handler"){
            if($var){
              if(isset($reg['error']['handler']) && $reg['error']['handler'] != NULL){
                $event['desc'] = "Tried to activate error handler again, command skipped.";
              }else{
                $reg['error']['handler'] = set_error_handler(__FUNCTION__);
                if(!isset($reg['error']['reporting']))
                  $reg['error']['reporting'] = E_ALL ^ E_STRICT;
                if(!isset($reg['error']['tracing']))
                  $reg['error']['tracing'] = E_ALL ^ E_NOTICE;
                $reg['error']['active'] = true;
                $event['desc'] = "Activated error handler.";
              }
            }else{
              $event['desc'] = "Disabled error handler.";
              if(isset($reg['error']['handler'])){
                set_error_handler($reg['error']['handler']);
                $event['desc'] .= " Restored previous error handler.";
              }else{
                restore_error_handler();
              }
              $reg['error']['handler'] = NULL;
              $reg['error']['active'] = false;
              $event['desc'] = "Disabled error handler.";
            }
          }elseif(in_array($method,array("reporting","tracing"))){
            if(is_int($var)){
              $reg['error'][$method] = $var;
              $event['desc'] = "Set error {$method} to '{$var}'.";
            }else{
              $event['desc'] = "Set error {$method} to '{$var}' failed, please use only the PHP ERROR constants.";
            }
          }elseif($method == "bubble"){
            if($var){
              if(isset($reg['error']['handler']) && !is_null($reg['error']['handler'])){
                $event['desc'] = "Error event bubbling activated.";
                $reg['error']['bubble'] = true;
              }else $event['desc'] = "Can't activated error event bubbling, there is no error handler specified!";
            }else{
              $reg['error']['bubble'] = false;
              $event['desc'] = "Error event bubbling deactivated.";
            }
          }
          $event['data']['method'] = $method;
          break;


        case "memory":
          #  do you like to do something else on a tick?
          $event['type'] = 'tick';
          break;

        case "convert":
          $event['desc'] = "Used converter.";
          if($method == "php2json"){
            switch (gettype($var)) {
              case 'boolean':
                $var = $var?'true':'false';
                break;
              case 'integer':
              case 'double':
                $var = (float) $var;
                break;
              case 'resource':
              case 'string':
                $var = '"'.str_replace(array("\r", "\n", "<", ">", "&"),array('\r', '\n', '\x3c', '\x3e', '\x26'),addslashes($var)).'"';
                break;
              case 'array':
                if (empty($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
                  $output = array();
                  foreach($var as $v) $output[] = call_user_func(__FUNCTION__,$v,"convert:php2json");
                  $var = '[ '. implode(', ', $output) .' ]';
                  break;
                }
              case 'object':
                $output = array();
                ;
                foreach($var as $k => $v) $output[] = call_user_func(__FUNCTION__,strval($k),"convert:php2json") .': '. call_user_func(__FUNCTION__,$v,"convert:php2json");
                $var = '{ '. implode(', ', $output) .' }';
                break;
              default:
                $var = 'null';
            }
          }
          $event['data']['method'] = $method;
          array_pop($reg['events']);
          break;

        case "group":
          $group = true;


        /**
        * Messages(default)
        *
        * Set your own max title length value:
        * __debug(200,'config:maxTitleLength');
        *
        * Set the max parameter length with this code:
        * __debug(20,'config:maxParamLength');
        *
        * And finally set the maximum message length (characters):
        * __debug(16000,'config:maxMessageLength');
        */
        default:
          $msg = array();
          if(!isset($group) || $group !== true) $group = false;

          #  copy var and get type
          $msg['var'] = $var;
          $msg['type'] = gettype($var);

          #  special type handling
          switch($msg['type']){
            case "boolean":
              $msg['var'] = $var?"TRUE":"FALSE";
              break;
            case "string":
              $msg['type'] = "string (".strlen($var).")";
              break;
            case "array":
              $msg['type'] .= " (".count($var).")";
              break;
            case "object":
              $msg['type'] = get_class($var)." (object)";
              if(get_parent_class(get_class($var)))
                $msg['type'] .=" extends ".get_parent_class(get_class($var));
              $msg['methods'] = get_class_methods(get_class($var));
              $msg['vars'] = array_merge(get_class_vars(get_class($var)),get_object_vars($var));
              break;
            case "resource":
              $msg['type'] = get_resource_type($var)." (ressource)";
              break;
            case "NULL":
              $msg['var'] = "NULL";
              break;
          }

          #  get trace, file and line
          $msg['included'] = get_included_files();
          $trace = debug_backtrace();
          $msg['trace']  = array();
          foreach($trace as $tr){
            $t = array();
            $t['title'] = "";
            $t['base'] = $reg['cfg']['outputBaseDir'];
            $t['args'] = array();
            $t['file'] = "";
            $t['line'] = "";
            if(isset($tr['args'])){
              foreach($tr['args'] as $a)
                $t['args'][] = is_scalar($a)?(is_string($a)?("\"".(strlen($a)>$reg['cfg']['maxParamLength']?("...".substr($a,-($reg['cfg']['maxParamLength']))):$a)."\""):(is_bool($a)?($a?"true":"false"):$a)):gettype($a);
            }

            if(isset($tr['class'])) $t['title'] = "{$tr['class']}{$tr['type']}{$tr['function']}(".implode(",",$t['args']).")";
              elseif(isset($tr['function'])) $t['title'] = "{$tr['function']}(".implode(",",$t['args']).")";

            if(isset($tr['file'])){
              $t['file'] = str_replace("\\","/",$tr['file']);
              for($i=0;$i < strlen($t['file']) && $i < strlen($t['base']) && $t['file']{$i} == $t['base']{$i};$i++);
              $t['file'] = strlen(substr($t['file'],0,$i)) < $i?$t['file']:preg_replace("~^([^/])~","/$1",substr($t['file'],$i));
              $t['line'] = isset($tr['line'])?$tr['line']:0;
            }
            $msg['trace'][] = $t;

          }

          if(!isset($trace[0]['file'])) $trace[0]['file'] = $trace[0]['line'] = "unknown";
          $msg['line'] = $trace[0]['line'];

          #  get path and file name
          $msg['file'] = str_replace("\\","/",$trace[0]['file']);
          for($i=0;$i < strlen($msg['file']) && $i < strlen($t['base']) && $msg['file']{$i} == $t['base']{$i};$i++);
          $msg['file'] = strlen(substr($msg['file'],0,$i)) < $i?$msg['file']:preg_replace("~^([^/])~","/$1",substr($msg['file'],$i));

          #  and now get the real source
          $msg['source'] = __FUNCTION__."()";
          if(is_readable($trace[0]['file'])){
            $fp = fopen($trace[0]['file'], "r");
            $curLine = 1;
            while($f = fgets($fp, 4096)) if($msg['line'] == $curLine++) $msg['source'] = $f;
            fclose($fp);
          }


          #  convert var to a printable message
          $msg['var'] = print_r($msg['var'],true);
          $msg['length'] = strlen($msg['var']);

          #  create automatic an debug title for this var ..
          if(is_null($title)){
            $tArgs = array();
            if(isset($trace[1]['args'])){
              foreach($trace[1]['args'] as $arg)
                $tArgs[] = is_scalar($arg)?(is_string($arg)?("\"".(strlen($arg)>$reg['cfg']['maxParamLength']?("...".htmlspecialchars(substr($arg,-($reg['cfg']['maxParamLength'])))):htmlspecialchars($arg))."\""):$arg):gettype($arg);
              $tArgs = implode(",",$tArgs);
            }
            if(isset($trace[1]['class'])){
              $msg['title'] = "{$trace[1]['class']}{$trace[1]['type']}{$trace[1]['function']}({$tArgs})";
              $msg['titleColor'] = "class";
            }elseif(isset($trace[1]['function'])){
              $msg['title'] = "{$trace[1]['function']}({$tArgs})";
              $msg['titleColor'] = "function";
            }else{
              $msg['title'] = __FUNCTION__;
              $msg['titleColor'] = "none";
            }
          # .. or use the given one
          }else{
            $msg['title'] = htmlspecialchars($title);
            $msg['titleColor'] = "user";
          }
          unset($trace);
          #  cut to long titles
          if(strlen($msg['title']) > $reg['cfg']['maxTitleLength'])
            $msg['title'] = substr($msg['title'],0,$reg['cfg']['maxTitleLength'])."...";

          #  create an new unique id to identify this message
          if($group){
            $msg['count'] = 1;
            $msg['id'] = __FUNCTION__."_message_".md5($method);
            if(isset($reg['messages'][$msg['id']]))
              $msg['count'] = ++$reg['messages'][$msg['id']]['count'];
            $msg['title'] = $method." [".($msg['count'])."]";
          }else{
            $i = 0;
            do {
              $msg['id'] = __FUNCTION__."_message_".md5($msg['title'])."_".$i++;
            } while(isset($reg['messages'][$msg['id']]));
          }
          if(!isset($reg['messages'])) $reg['messages'] = array();
          if((int)$reg['cfg']['maxMessages'] && count($reg['messages']) < $reg['cfg']['maxMessages'] && (!$reg['cfg']['maxMemory'] || $reg['stats']['memoryUsage'] < $reg['cfg']['maxMemory'])){
            if($msg['length'] > $reg['cfg']['maxMessageLength'])
              $msg['var'] = substr($msg['var'],0,$reg['cfg']['maxMessageLength']);
            if($group){
              if(!isset($reg['messages'][$msg['id']])) $reg['messages'][$msg['id']] = array('length'=>0,'var'=>array());
              $msg['titleColor'] = "group";
              array_push($reg['messages'][$msg['id']]['var'],$msg['var']);
              $msg['var'] = $reg['messages'][$msg['id']]['var'];
              if(count($msg['var']) > $reg['cfg']['maxMessageGroupCount'])
                array_pop($msg['var']);
              $msg['length'] = $msg['length']+$reg['messages'][$msg['id']]['length'];
              $msg['type'] = "group (".$msg['length'].")";
            }
            $reg['messages'][$msg['id']] = $msg;
            $event['type']  = 'msg';
            $event['data'] =& $reg['messages'][$msg['id']];
            $reg['stats']['memoryUsage'] += strlen(is_array($msg['var'])?implode('',$msg['var']):$msg['var']);
          }
          $event['desc'] = $msg['title'];
          if(!$group && (is_numeric($var) || is_bool($var))) $event['desc'] .= ": ".$msg['var'];
      }


    #  Errorhandler
    }elseif($event['type']=='errorhandler'){
      if(isset($reg['error']['active']) && $reg['error']['active'] && $args){
        if(!isset($reg['errors']['count'])) $reg['errors']['count'] = 0;
        if($reg['error']['reporting'] & $args[0]){
          $error = array();
          $error['id'] = NULL;
          $error['occurrences'] = 1;
          $keys = array('type','msg','file','line','vars');
          foreach($args as $k=>$v)
            $error[$keys[$k]] = $v;

          $error['length'] = strlen($error['msg']);
          $error['msg'] = substr($error['msg'],0,$reg['cfg']['maxMessageLength']);
          $error['base'] = $reg['cfg']['outputBaseDir'];
          $error['file'] = str_replace("\\","/",$error['file']);
          for($i=0;$i < strlen($error['file']) && $i < strlen($error['base']) && $error['file']{$i} == $error['base']{$i};$i++);
          $error['file'] = strlen(substr($error['file'],0,$i)) < $i?$error['file']:preg_replace("~^([^/])~","/$1",substr($error['file'],$i));
          #  remove vars if seems like the globals array
          if(array_key_exists('GLOBALS',$error['vars'])
            && array_key_exists('HTTP_ENV_VARS',$error['vars'])
            && array_key_exists('HTTP_SERVER_VARS',$error['vars']))
            $error['vars'] = NULL;

          $error['id'] = md5($error['type'].$error['msg'].$error['file'].$error['line']);
          $error['trace']  = NULL;

          if((int)$reg['cfg']['maxErrors'] && $reg['errors']['count'] < $reg['cfg']['maxErrors'] && (!$reg['cfg']['maxMemory'] || $reg['stats']['memoryUsage'] < $reg['cfg']['maxMemory'])){
            if(!isset($reg['errors'][$error['type']][$error['id']])){
              if($reg['error']['tracing'] & $error['type']){
                $error['trace'] = array();
                foreach(array_slice(debug_backtrace(),1) as $tr){
                  $t = array();
                  $t['title'] = "";
                  $t['base'] = $reg['cfg']['outputBaseDir'];
                  $t['args'] = array();
                  $t['file'] = "";
                  $t['line'] = "";
                  if(isset($tr['args'])){
                    foreach($tr['args'] as $a)
                      $t['args'][] = is_scalar($a)?(is_string($a)?("\"".(strlen($a)>$reg['cfg']['maxParamLength']?("...".substr($a,-($reg['cfg']['maxParamLength']))):$a)."\""):(is_bool($a)?($a?"true":"false"):$a)):gettype($a);
                  }
                  if(isset($tr['class'])) $t['title'] = "{$tr['class']}{$tr['type']}{$tr['function']}(".implode(",",$t['args']).")";
                    elseif(isset($tr['function'])) $t['title'] = "{$tr['function']}(".implode(",",$t['args']).")";

                  if(isset($tr['file'])){
                    $t['file'] = str_replace("\\","/",$tr['file']);
                    for($i=0;$i < strlen($t['file']) && $i < strlen($t['base']) && $t['file']{$i} == $t['base']{$i};$i++);
                    $t['file'] = strlen(substr($t['file'],0,$i)) < $i?$t['file']:preg_replace("~^([^/])~","/$1",substr($t['file'],$i));
                    $t['line'] = isset($tr['line'])?$tr['line']:0;
                  }
                  $error['trace'][] = $t;
                }
              }
              $event['data'] = $error;
              $reg['errors'][$error['type']][$error['id']] =& $event['data'];
              $reg['errors']['count']++;
            }else{
              $event['data'] = $error;
              $reg['errors'][$error['type']][$error['id']]['occurrences']++;
            }
            $reg['stats']['memoryUsage'] += strlen(print_r($error,true));
          }

          $event['desc'] = substr($error['msg'],0,$reg['cfg']['maxTitleLength']);

        }else{
          array_pop($reg['events']);
        }

         if(isset($reg['error']['bubble']) && $reg['error']['bubble'] && !is_null($reg['error']['handler']))
           call_user_func_array($reg['error']['handler'], $args);
      }else{
        array_pop($reg['events']);
      }



    #  Output
    }elseif($reg['cfg']['outputHandler']){
      #  send buffered output
      while(ob_get_length()) ob_end_flush();

      $memoryPeak = function_exists('memory_get_peak_usage')?memory_get_peak_usage():0;

      #  get the templates
      $tplFileName = __FILE__;
      $tplFile = file(__FILE__);
      $tplSource = "";
      $debugInfo = array();

      $collecting = false;
      foreach($tplFile as $n=>$line){
        if(!$collecting && preg_match("~^\s+\*\s+@(\w+)\s+(.+)$~i",$line,$m))
          $debugInfo[$m[1]] = trim($m[2]);

        if($collecting){
          if(!preg_match("~^<!--\sDEBUG {$reg['cfg']['outputHandler']} END\s-->~i",$line)){
            $tplSource .= $line;
          }else $collecting = false;
        }elseif(preg_match("~^<!--\sDEBUG {$reg['cfg']['outputHandler']} BEGIN\s-->~i",$line,$m)){
          $collecting = true;
        }
      }

      $event['desc'] = "PHP shut down, generating ".__FUNCTION__." output now.";
      #  generate output identifier
      if(!$reg['cfg']['outputIdentifier']){
        $trace = debug_backtrace();
        $reg['cfg']['outputIdentifier'] = $reg['cfg']['functionName']."_".substr(md5(print_r($trace,true)),0,6);
      }
      #  disable error handler to avoid conflicts
      if(isset($reg['error']['active']) && $reg['error']['active'])
        call_user_func(__FUNCTION__,false,'error:handler');

      #  evaluate template output as own php script with no limits
      $debugInfo['memory_limit'] = ini_set('memory_limit','-1');
      $debugInfo['time_limit'] = ini_get('max_execution_time');
      @set_time_limit(0);
      error_reporting(0);
      ob_start();
      @eval(chr(63).chr(62).$tplSource.chr(60).chr(63));
      unset($tplSource);
      switch(strtolower(preg_replace('~^([^_]+)(_.+)?$~','$1',$reg['cfg']['outputHandler']))){
        case "html":
          unset($reg); // gain some memory back
          print(preg_replace("~>[\s\n]+<~m","><",ob_get_contents().(ob_end_clean()?'':'')));
          break;
        default:
          if(ob_get_length()) ob_end_flush();
      }
    }
    return $var;
  }

  #  return to including script and skip the template part below
  return;
?>


<!-- DEBUG NONE BEGIN -->
<!-- DEBUG NONE END -->



<!-- DEBUG AJAX BEGIN -->
<?php
  # Cache live circle (in minutes)
  $minutes = 60;
  $exp_gmt = gmdate("D, d M Y H:i:s", time() + $minutes * 60) ." GMT";
  $mod_gmt = gmdate("D, d M Y H:i:s", getlastmod()) ." GMT";
  error_reporting(0);

  #  HTML entity mask
  $entities = array("&nbsp;"  =>  "&#xA0;",
                    "<br>"    =>  "<br />",
                    "&amp;"   =>  "&#x26;",
                    "&bull;"  =>  "&#x95;",
                    "&lt;"    =>  "&#x3C;",
                    "&gt;"    =>  "&#x3E;",
                    "&quot;"  =>  "&#x22;");
  ob_start();
  switch($reg['cfg']['outputSection']){
    case "info":
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
      header("Content-type: text/plain; charset=UTF-8");
      ob_start();
      phpinfo();
      $phpinfo = ob_get_contents();
      ob_end_clean();
      print(substr($phpinfo,strpos($phpinfo,'<body>')+6,strpos($phpinfo,'</body>')-strpos($phpinfo,'<body>')-6));
      break;


    case "doc":
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
      header("Content-type: text/html; charset=UTF-8");
      ?>
      <div style="white-space: nowrap; background-color: #fff; color: #333; text-align: left;font-family:monospace;">
        <iframe src="http://xn-debug.sourceforge.net/check.php?v=<?php echo urlencode($debugInfo['version'])?>" style="display: none;"></iframe>
        <table style="width: 100%;">
          <tr>
            <td>
            <?php
            $caption = false;
            while(($r = array_shift($tplFile)) && strpos($r,chr(63).chr(62))===false){
              if(preg_match("~^\s*/\*+\s?.*$~",$r)) $caption = true;
              elseif(!preg_match("~^\s*\*/.*$~",$r) && preg_match("~^\s*\*\s+(.*)$~",$r,$m)){
                $line = trim(htmlspecialchars($m[1]))."\n";
                switch(true){
                  case (strpos($line,'@author') !== false && preg_match('~^@author.+&lt;(.+)&gt;\s*$~i',$line,$ma)):
                    $line = str_replace($ma[1],'<a href="mailto:'.$ma[1].'" style="font-style: italic; color: #blue;">'.$ma[1].'</a>',$line);
                    break;
                  case (strpos($line,'@link') !== false && preg_match('~^@link\s+(\S+)\s*$~i',$line,$ml)):
                    $line = str_replace($ml[1],'<a href="'.$ml[1].'" target="_blank" style="font-style: italic; color: #blue;">'.$ml[1].'</a>',$line);
                    break;
                  case (strpos($line,'@version') !== false && preg_match('~^@version\s+(.+)\s*$~i',$line,$mv)):
                    $line = str_replace($mv[1],'<a href="?'.$reg['cfg']['globalIdentifier'].'=download" style="font-style: italic; color: #blue;">'.$mv[1].'&nbsp;(Download)</a>',$line);
                    break;
                }
                $line = nl2br(str_replace("\t","&nbsp;&nbsp;&nbsp;",$line));

                if($caption){
                  $line = "</td></tr><tr><th style=\"font: 14px tahoma; padding: 20px 0 4px; font-weight: bold;\">{$line}</th></tr><tr><td>\n";
                  $caption = false;
                }
                print($line);
              }
            }?>
            </td>
          </tr>
        </table>
      </div>
      <?php
      break;

    case "source":
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");


      #  high security risk!
      $file = isset($_REQUEST['file'])?str_replace(array("\\","//","../"),array("/","/",""),$_REQUEST['file']):NULL;
      if(is_null($file)) $file = isset($_COOKIE["{$reg['cfg']['globalIdentifier']}_source_file"])?$_COOKIE["{$reg['cfg']['globalIdentifier']}_source_file"]:"";
      $line = isset($_REQUEST['line'])?(int)$_REQUEST['line']:0;
      $id  = isset($_REQUEST['id'])?$_REQUEST['id']:NULL;

      #  find the file by exploring the base path
      if(!file_exists($file)){
        $base = $reg['cfg']['outputBaseDir'];
        while(strlen($base) > 3 && !file_exists($base.$file))
          $base = dirname($base);
        if(file_exists($base.$file))
          $file = str_replace(array("\\","//","../"),array("/","/",""),$base.$file);
      }

      $source = "";
      if(file_exists($file) && is_file($file) && is_readable($file)){
        $fs = filesize($file);
        $info = pathinfo($file);
        if(isset($info['extension'])) $info['extension'] = strtoupper($info['extension']);
        if($_REQUEST['force'] === "1" || $fs <= $reg['cfg']['maxFileSize']){
          $source = file($file);
          $lines = count($source);
          $source = implode("",$source);
          $mime = array('JPG'=>'image/jpeg','JPEG'=>'image/jpeg','GIF'=>'image/gif','PNG'=>'image/png');
          if($img = getimagesize($file) && in_array($info['extension'],array_keys($mime))){ ?>
            <img src="data:<?php echo $mime[$info['extension']]?>;base64,<?php echo base64_encode(implode('',file($file)))?>" />
        <?php
          }else{
            header("Content-type: text/html; charset=UTF-8");
            // stat
            $highlight = (in_array(strtolower($info['extension']),array("php","html")) || $fs > 51200);
            if(!$highlight) $source = '<code>'.htmlspecialchars($source).'</code>';
              else  $source = str_replace("\n","",highlight_string($source,true));

            print("<table><tr><td style=\"background-color: #aaa; color: #333; padding: 2px; border: 1px solid #808080; border-left-color: #fff; border-top-color: #fff;\" onclick=\"{$reg['cfg']['globalIdentifier']}.makeHttpRequest('?{$reg['cfg']['globalIdentifier']}=browse&file=".dirname($file)."','{$reg['cfg']['globalIdentifier']}.browse');\" colspan=\"2\">{$file}</td></tr><tr><td class=\"line\" style=\"vertical-align:top;\"><code>");
            for($i=1;$i<=$lines;$i++)
              print(($i===$line?"&bull;":"")."<a name=\"{$gid}_source_line_{$i}\"></a>{$i}<br />");
            print("</code></td><td class=\"code\"".($highlight?"":" style=\"white-space:pre;\"").">{$source}</td></tr>\n");
            print("</table>");
          }
        }else{
          header("Content-type: text/html; charset=UTF-8");
          ?>
          <table style="margin: 5px;">
            <tr>
              <td>File:</td>
              <td><b><?php echo $file?></b></td>
            </tr>
            <tr>
              <td>Size:</td>
              <td style="color: red;"><?php echo number_format($fs/1024,2,",",".")?>kB (max. <?php echo number_format($reg['cfg']['maxFileSize']/1024,2,",",".")?>kB)</td>
            </tr>
            <tr>
              <td colspan="2">&nbsp;</td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td onclick="<?php echo $reg['cfg']['globalIdentifier']?>.getSource('<?php echo urlencode($file)?>',<?php echo $line?>,true);" style="font-weight: bold; cursor: pointer; padding: 2px;">Click here to load it anyway!</td>
            </tr>
          </table>
          <?php
        }
        setcookie("{$reg['cfg']['globalIdentifier']}_source_file",$file,0,'/');

      }else{
        print("File doesn't exists: {$file}");
      }
      break;

    case "browse":
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
      header("Content-type: text/html; charset=UTF-8");

      #  high security risk!
      $file = isset($_REQUEST['file'])?str_replace(array("\\","//","../"),array("/","/",""),$_REQUEST['file']."/"):NULL;
      if(is_null($file)) $file = isset($_COOKIE["{$reg['cfg']['globalIdentifier']}_browse_file"])?$_COOKIE["{$reg['cfg']['globalIdentifier']}_browse_file"]:NULL;
      $line = isset($_REQUEST['line'])?(int)$_REQUEST['line']:NULL;
      $id  = isset($_REQUEST['id'])?$_REQUEST['id']:NULL;
      $base = $reg['cfg']['outputBaseDir'];
      if(is_null($file)) $file = $base."/";

      if(!file_exists($file)){
        for($i=0;$i < strlen($file) && $i < strlen($base) && $file{$i} == $base{$i};$i++);
        $file = $base.preg_replace("~^([^/])~","/$1",substr($file,$i));
      }

      if(file_exists($file) && is_file($file) && is_readable($file))
        $file = dirname($file);

      $files = array();
      $dirs = array();

      $file = str_replace(array("\\","//","../"),array("/","/",""),$file);
      foreach(glob("{$file}*") as $f){
        if(is_file($f)) $files[] = $f;
        if(is_dir($f)) $dirs[] = $f;
      }?>
      <ul style="background-color: white;">
        <li style="background-color: #aaa; color: #333; padding: 2px; border: 1px solid #808080; border-left-color: #fff; border-top-color: #fff;"><?php echo $file?></li>
        <?php if(strlen($file) > strlen($base) || dirname($file) != "."){?>
        <li style="color: #999;" onclick="<?php echo $reg['cfg']['globalIdentifier']?>.makeHttpRequest('?<?php echo $reg['cfg']['globalIdentifier']?>=browse&file=<?php echo urlencode(dirname($file)."/")?>','<?php echo $reg['cfg']['globalIdentifier']?>.browse');">..</li>
        <?php }?>
        <?php foreach($dirs as $d){?>
        <li style="color: #999;" onclick="<?php echo $reg['cfg']['globalIdentifier']?>.makeHttpRequest('?<?php echo $reg['cfg']['globalIdentifier']?>=browse&file=<?php echo urlencode($d."/")?>','<?php echo $reg['cfg']['globalIdentifier']?>.browse');">[<?php echo substr($d,strlen($file))?>]</li>
        <?php }?>
        <?php foreach($files as $f){?>
        <li onclick="<?php echo $reg['cfg']['globalIdentifier']?>.getSource('<?php echo urlencode($f)?>',0);"><?php echo substr($f,strlen($file))?> (<?php echo number_format(filesize($f)/1024,2,",",".")?>kB)</li>
        <?php }?>
      </ul>
      <?php
      setcookie("{$reg['cfg']['globalIdentifier']}_browse_file",$file,0,'/');
      break;

  }

  $output = ob_get_contents();
  ob_end_clean();
  print(strtr($output,$entities));
?>
<!-- DEBUG AJAX END -->



<!-- DEBUG CSS BEGIN -->
<?php
  #header("Cache-Control: no-cache");
  #header("Pragma: no-cache");
  header("Content-type: text/css");

  #  print doc header
  $rec = false;
  foreach($tplFile as $r){
    if(preg_match("~^\s*/\*+\s?.*$~",$r)) $rec = true;
    if(!$rec) continue;
    print($r);
    if($rec && preg_match("~^\s*\*/.*$~",$r)) break;
  }

  $id = preg_replace("~[^\w\d]+~","",isset($_REQUEST['key'])?$_REQUEST['key']:$reg['cfg']['outputIdentifier']);

  #  Colors
  $color = array( 'light'           => "#fff",
                  'dark'            => "#404040",
                  'grey'            => "#666",
                  'shadow'          => "#808080",
                  'darkbackground'  => "#8B8B8B",
                  'background'      => "#D4D0C8");


?>

  /**
  * Window
  */
  #<?php echo $id?>_window {
    display: block;
    visibility: visible;
    overflow: hidden;
    width: 0px;
    height: 0px;
    position: absolute;
    z-index: 0;
    left: 0;
    top: 0;
    background-color: <?php echo $color['background']?>;
    border: 0;
    color: black;
    font: 11px Verdana;
    cursor: default;
  }
  #<?php echo $id?>_window * {
    position: relative;
    margin: 0;
    padding: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: inherit;
    text-align: left;
    border: 0;
  }

  #<?php echo $id?>_window a {
    text-decoration: none;
    cursor: pointer;
  }

  #<?php echo $id?>_window_outerborder {
    background: <?php echo $color['background']?>;
    border: 1px solid <?php echo $color['dark']?>;
    border-left-color: <?php echo $color['background']?>;
    border-top-color: <?php echo $color['background']?>;
  }
  #<?php echo $id?>_window_innerborder {
    position: relative;
    background-color: <?php echo $color['background']?>;
    border: 1px solid <?php echo $color['shadow']?>;
    border-left-color: <?php echo $color['light']?>;
    border-top-color: <?php echo $color['light']?>;
  }

  #<?php echo $id?>_window_head {
    display: block;
    position: relative;
    margin: 2px 2px 0;
    background: transparent;
    height: 20px;
    white-space: nowrap;
    overflow: hidden;
    border: 0;
    -moz-user-select:none;
  }
  #<?php echo $id?>_window_title {
    width: 100%;
    overflow: hidden;
    padding: 2px 4px;
    text-align: left;
    font: bold 11px tahoma;
    color: white;
  }

  #<?php echo $id?>_window_title_box {
    width: 100%;
    overflow: hidden;
  }

  #<?php echo $id?>_window_title_box_ico {
    position: absolute;
    top: 1px;
    left: 1px;
    width: 16px;
    height: 16px;
    overflow: hidden;
    background-image: url('?<?php echo $reg['cfg']['globalIdentifier']?>=icon');
    background-position: 0px 0px;
    background-repeat: no-repeat;
  }
  #<?php echo $id?>_window_title_box_ico.ani {
    background-position: 0px -16px;
  }
  #<?php echo $id?>_window_title_box_ico.red {
    background-position: -16px 0px;
  }
  #<?php echo $id?>_window_title_box_ico.red.ani {
    background-position: -16px -16px;
  }
  #<?php echo $id?>_window_title_box_ico.green {
    background-position: -32px 0px;
  }
  #<?php echo $id?>_window_title_box_ico.green.ani {
    background-position: -32px -16px;
  }
  #<?php echo $id?>_window_title_box_ico.yellow {
    background-position: -48px 0px;
  }
  #<?php echo $id?>_window_title_box_ico.yellow.ani {
    background-position: -48px -16px;
  }
  #<?php echo $id?>_window_title_box_content {
    color: white;
    background: black;
    border: 0;
    height: 18px;
    overflow: hidden;
    padding-left: 16px;
    padding-right: 72px;
  }
  #<?php echo $id?>_window_head .<?php echo $id?>_window_bt {
    position: absolute;
    top: 2px;
    height: 12px;
    width: 12px;
    overflow: hidden;
    margin: 0;
    padding: 0;
    background-color: <?php echo $color['background']?>;
    border: 1px solid <?php echo $color['shadow']?>;
    border-top-color: <?php echo $color['light']?>;
    border-left-color: <?php echo $color['light']?>;
    text-align: left;
    vertical-align: top;
  }
  #<?php echo $id?>_window_bt_min     { right: 49px; }
  #<?php echo $id?>_window_bt_scroll  { right: 34px; }
  #<?php echo $id?>_window_bt_max     { right: 34px; }
  #<?php echo $id?>_window_bt_forward { right: 17px; }
  #<?php echo $id?>_window_bt_close   { right: 2px; }

  #<?php echo $id?>_body {
    margin: 0 2px 2px;
    border: 1px solid <?php echo $color['light']?>;
    border-left-color: <?php echo $color['shadow']?>;
    border-top-color: <?php echo $color['shadow']?>;
  }
  #<?php echo $id?>_body_box {
    width: 100%;
    overflow: hidden;
  }

  #<?php echo $id?>_body_menu {
    background: #bbb;
    height: 20px;
    white-space: nowrap;
    overflow: hidden;
    -moz-user-select: none;
  }

  #<?php echo $id?>_body_menu_box {
    color: black;
    white-space: nowrap;
    overflow: hidden;
    height: 20px;
    border: 1px solid <?php echo $color['dark']?>;
    border-left: 0;
    border-right: 0;
    border-bottom-color: #aaa;
    font-weight: bold;
  }
  #<?php echo $id?>_body_menu_box div.<?php echo $id?>_body_menu_entry {
    float: left;
    color: #555;
    height: 16px;
    overflow: hidden;
    background: transparent;
    border: 1px solid #bbb;
    border-top: 0;
    margin: 0 1px 2px 0;
    padding: 0px 4px 0px;
    font: 11px Verdana;
  }

  #<?php echo $id?>_content {
    width: 100%;
    overflow: hidden;
  }

  #<?php echo $id?>_content table {
    width: 100%;
  }
  /**
  * Sections
  */
  .<?php echo $id?>_window_section_gutter {
    white-space: nowrap;
    width: 100%;
    height: 100%;
    overflow: auto;
    background: <?php echo $color['background']?>;
  }

  .<?php echo $id?>_window_section_gutter div.<?php echo $id?>_content {
    min-width: 460px;
    min-height: 0px;
  }

  #<?php echo $id?>_messages_c,
  #<?php echo $id?>_errors_c,
  #<?php echo $id?>_system_c,
  #<?php echo $id?>_search_c,
  #<?php echo $id?>_history_c,
  #<?php echo $id?>_source_c,
  #<?php echo $id?>_javascript_c {}

  #<?php echo $id?>_info_c,
  #<?php echo $id?>_source_c,
  #<?php echo $id?>_doc_c {
    background-color: white;
  }
  #<?php echo $id?>_info_c div.<?php echo $id?>_content,
  #<?php echo $id?>_doc_c div.<?php echo $id?>_content {
    padding: 10px 15px;
    font-size: 11px;
    font-family: Verdana, sans-serif;
  }
  #<?php echo $id?>_window div#<?php echo $id?>_window_content_errors {
    border: 1px solid <?php echo $color['light']?>;
    border-top-color: <?php echo $color['shadow']?>;
    border-left-color: <?php echo $color['shadow']?>;

  }
  #<?php echo $id?>_window div#<?php echo $id?>_window_content_errors_navigation {
    height: 16px;
    border: 1px solid <?php echo $color['shadow']?>;
    border-left-color: <?php echo $color['light']?>;
    border-top-color: <?php echo $color['light']?>;
  }

  #<?php echo $id?>_window div.<?php echo $id?>_window_content_errors_navigation_button { display: inline; border: 0; margin: 2px 0; padding: 0 2px; background-color: <?php echo $color['background']?>; font-size: 11px; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_errors_list { padding: 6px; background-color: white; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_errors_list_text { margin-bottom: 6px; padding: 1px; background-color: #f9f9f9; border: 1px solid #ddd; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_errors_list_text_header { font-weight: bold; font-size: 12px; padding: 4px; background-color: #e6d0c8; }
  #<?php echo $id?>_window th.<?php echo $id?>_window_content_errors_list_text_line { background-color: #ddd; padding: 0 4px; font-weight: bold; font-size: 11px; margin: 1px 0; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_errors_list_text_content { padding: 2px; white-space: nowrap; }

  #<?php echo $id?>_messages_c a.<?php echo $id?>_window_content_messages_links { text-decoration: none; color: black; font-size: 8pt; }
  #<?php echo $id?>_messages_c fieldset.<?php echo $id?>_window_content_messages { border: 1px solid #888; padding: 5px; width: auto; text-align: left; }
  #<?php echo $id?>_messages_c legend.<?php echo $id?>_window_content_messages_title { cursor: pointer; font-size: 8pt; color: black; padding: 4px; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_title_indicator { color: #666; font: 12px monospace; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_sigma { float: right; cursor: pointer; border: 1px solid white; border-bottom-color: #808080; border-right-color: #808080; padding: 2px 5px; height: 15px; -moz-user-select: none; }
  #<?php echo $id?>_messages_c table.<?php echo $id?>_window_content_messages_trace { display: none; width: 100%; table-layout: auto; border: 1px solid white; border-bottom-color: #808080; border-right-color: #808080; background-color: white; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_messages_position { border: 1px solid #fff; border-left-color: #808080; border-top-color: #808080; margin-bottom: 2px; }
  #<?php echo $id?>_window div.<?php echo $id?>_window_content_messages_position_text { white-space: nowrap; color: #111; padding: 2px; border: 1px solid #d4d0c8; border-top-color: #404040; border-left-color: #404040; cursor: text; overflow: hidden; text-align: left; font-family: monospace; font-size: 12px; }
  #<?php echo $id?>_messages_c span.<?php echo $id?>_window_content_messages_position_line { font: 8pt courier; color: #666; text-align: left; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_options { border: 1px solid #fff; border-top-color: #808080; border-left-color: #808080; margin: 3px 0; text-align: left; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_options_button { border: 1px solid <?php echo $color['shadow']?>; border-top-color: <?php echo $color['light']?>; border-left-color: <?php echo $color['light']?>; background-color: <?php echo $color['background']?>; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content { background-color: #fff; border: 1px solid #fff; border-left-color: #808080; border-top-color: #808080; margin-bottom: 2px; text-align: left; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content_headline { cursor: default; padding: 2px; color: #333; background-color: #ddd; font-weight: normal; border: 0; text-align: left; }  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content_type { cursor: default; padding: 2px; color: #eee; background-color: gray; font-weight: bold; border: 1px solid #808080; border-left-color: #d4d0c8; border-top-color: #d4d0c8; text-align: left; }
  #<?php echo $id?>_messages_c ul.<?php echo $id?>_window_content_messages_content_methods { cursor: default; list-style-image: none; list-style-position: inside; list-style-type: square; margin: 0; padding: 2px; padding-left: 6px; color: #666; background-color: #f0f0f0; border: 1px solid #d4d0c8; border-left-color: #808080; border-top-color: #808080; text-align: left; }
  #<?php echo $id?>_messages_c ul.<?php echo $id?>_window_content_messages_content_methods li { font-family: monospace; color: #666;}  #<?php echo $id?>_messages_c ul.<?php echo $id?>_window_content_messages_content_methods li .type { font-family: monospace; color: #aaa;}  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content_view { padding: 4px; font: 8pt courier; border: 1px solid #d4d0c8; border-top-color: #404040; border-left-color: #404040; cursor: text; overflow: visible; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content_view ol li { padding: 0 0 1em; margin: 0; list-style-type: decimal; list-style-position: inside; color: black; }
  #<?php echo $id?>_messages_c pre.<?php echo $id?>_window_content_messages_content_view_text { margin: 0; text-align: left; font-family: monospace; font-size: 11px; }
  #<?php echo $id?>_messages_c div.<?php echo $id?>_window_content_messages_content_warning,
  #<?php echo $id?>_system_c div.<?php echo $id?>_window_content_system_content_warning {
    background: darkred; color: white; padding: 2px 4px; font-size: x-small; cursor: default;
  }

  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_debug { color: #666; }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_output { color: #666; }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_error { color: #a64038 }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_config { color: #3840a6 }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_errorhandler { background-color: #e6d0c8; }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_msg { background-color: #d4e6c8; }
  #<?php echo $id?>_history_c tr.<?php echo $id?>_window_content_history_type_search { background-color: #D5DDF3; }


  #<?php echo $id?>_source_c *,
  #<?php echo $id?>_browse_c *  {
    font-family: monospace;
  }
  #<?php echo $id?>_source_c td.line {
    border-right: 1px solid #eee;
    padding: 0 5px 0 0;
    color: #666;
    text-align: right;
  }
  #<?php echo $id?>_source_c td.code {
    padding: 0 5px;
    vertical-align: top;
    white-space: nowrap;
  }
  #<?php echo $id?>_history_c .<?php echo $id?>_history_table {
    /*margin: 2px 0;*/
    background-color: #fafafa;
    width: 100%;
  }
  #<?php echo $id?>_history_c .<?php echo $id?>_history_table th {
    font-weight: bold;
    border-bottom: 1px solid #666;
  }
  #<?php echo $id?>_history_c .<?php echo $id?>_history_table td,
  #<?php echo $id?>_history_c .<?php echo $id?>_history_table th {
    padding: 0 2px;
    font-size: 11px;
    font-family: Verdana, sans-serif;
  }
  #<?php echo $id?>_history_c .<?php echo $id?>_history_table td {
    text-align: right;
    vertical-align: top;
  }

  #<?php echo $id?>_system_div {
    border: 1px solid #fff;
    border-top-color: #808080;
    border-left-color: #808080;
    margin: 0;
    text-align: left;
  }
  #<?php echo $id?>_system_table {
    width: 100%; font: 12px tahoma; color: black; border-collapse: collapse; border: 0;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_headline {
    background-color: #ddd; border: 0;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_headline_content {
    padding: 0; background-color: #D4D0C8; border: 0;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_headline_content_text {
    font-size: 11px; font-family: tahoma, sans-serif; border: 1px solid #808080; border-left-color: #fff; border-top-color: #fff; padding: 2px;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_headline_content_text div {
    font-size: x-small; color: gray; float:right;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_content {
    padding: 0; border: 0; width: 100%;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_content_table {
    width: 100%; color: black; border-collapse: collapse; border: 0;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_content_table_left {
    width: 1%; font-size: 11px; font-family: tahoma, sans-serif; padding: 2px 10px 2px 4px; vertical-align: top;
  }
  #<?php echo $id?>_system_c .<?php echo $id?>_system_content_table_right {
    padding: 2px 10px 2px 4px; font-size: 11px; font-family: tahoma, sans-serif; vertical-align: top;
  }
  #<?php echo $id?>_loader {
    display: none;
  }
<!-- DEBUG CSS END -->



<!-- DEBUG HTML BEGIN -->
<?php
  $debugFunction =& $reg['cfg']['functionName'];

  if(is_null($reg['cfg']['outputIdentifier'])){
    #  create an unique key to prevent output conflicts, increase the key length if necessary
    $trace = debug_backtrace();
    $debugID = $debugFunction."_".substr(md5(print_r($trace,true)),0,6);
  }else{
    $debugID = $reg['cfg']['outputIdentifier'];
  }

  $debugClipboard = preg_match("~.*MSIE\s\d.*~i",$_SERVER['HTTP_USER_AGENT']);

  $windowTitle = strlen($reg['cfg']['outputTitle'])?$reg['cfg']['outputTitle']:"{$debugFunction}() [ ".date("Y-m-d H:i:s")." ]";

  #  Sections      id          title
  $sections = array(
              'errors'      => "Errors",
              'messages'    => "Messages",
              'system'      => "System",
              'search'      => "Search",
              'performance' => "Performance",
              'history'     => "History",
              'javascript'  => "JavaScript",
              'doc'         => "Documentation",
              'info'        => "PHP-Info",
              'source'      => "Source",
              'browse'      => "Browse"
              );

  #  remove performance if no statistic is available
  if(!$memoryPeak) unset($sections['performance']);

  #  Show sum of messages or hide section
  if(isset($reg['messages'])) $sections['messages'] .= " <small>[".count($reg['messages']).(count($reg['messages']) == $reg['cfg']['maxMessages']?"/{$reg['cfg']['maxMessages']}":"")."]</small>";
    else unset($sections['messages']);

  #  Show sum of search results or hide section
  if(isset($reg['search'])) $sections['search'] .= " <small>[".count($reg['search'])."]</small>";
    else unset($sections['search']);


  #  name error types
  $errorTypes = array(E_ERROR =>"E_ERROR",
                      E_WARNING =>"E_WARNING",
                      E_PARSE =>"E_PARSE",
                      E_NOTICE =>"E_NOTICE",
                      E_CORE_ERROR =>"E_CORE_ERROR",
                      E_CORE_WARNING =>"E_CORE_WARNING",
                      E_COMPILE_ERROR =>"E_COMPILE_ERROR",
                      E_COMPILE_WARNING =>"E_COMPILE_WARNING",
                      E_USER_ERROR =>"E_USER_ERROR",
                      E_USER_WARNING =>"E_USER_WARNING",
                      E_USER_NOTICE =>"E_USER_NOTICE",
                      E_STRICT =>"E_STRICT",
                      E_RECOVERABLE_ERROR  =>"E_RECOVERABLE_ERROR ",
                      E_DEPRECATED => "E_DEPRECATED",
                      E_USER_DEPRECATED  => "E_USER_DEPRECATED ",
                      E_ALL =>"E_ALL"
                     );

  #  create error navigation
  $errorNav = array();
  if(isset($reg['errors'])){
    foreach(array_keys($reg['errors']) as $type)
      $errorNav[$type] = count($reg['errors'][$type]);
    ksort($errorNav);
  }

  #  Show sum of errors or hide section
  if(isset($reg['errors']) && $reg['errors']['count']) $sections['errors'] .= " <small>[".$reg['errors']['count'].($reg['errors']['count'] == $reg['cfg']['maxErrors']?"/{$reg['cfg']['maxErrors']}":"")."]</small>";
    else unset($sections['errors']);


  #  sort sections by
  $sortedSections = array();
  foreach($reg['cfg']['outputSections'] as $s)
    if(in_array($s,array_keys($sections))) $sortedSections[$s] = $sections[$s];
  $sections = $sortedSections;

  // Deprecated!
  $section = isset($_COOKIE["{$debugID}_section"])?$_COOKIE["{$debugID}_section"]:NULL;
  if(is_null($section) || !in_array($section,array_keys($sections)))
    $section = implode(array_slice(array_keys($sections),0,1));
  $section = NULL;

  # System Vars
  $system = array();
  foreach(explode(",",$reg['cfg']['outputSystemVars']) as $k){
    eval("if(isset(\${$k})) \$system[\$k] = \${$k}; else \$system[\$k] = array();");
    switch($k){
      case '_CONSTANTS':
        $system[$k] = get_defined_constants();
        break;
      case '_INCLUDED':
        $system[$k] = get_included_files();
        break;
      case '_FUNCTIONS':
        $functions = get_defined_functions();
        $system[$k] = $functions['user'];
        asort($system[$k]);
        break;
      case '_HEADERS':
        if(!function_exists('headers_list')) break;
        $system[$k] = headers_list();
        break;
      case '_CLASSES':
        if(!function_exists('get_declared_interfaceared_classes')) continue;
        $system[$k] = get_declared_classes();
        asort($system[$k]);
        break;
      case '_INTERFACES':
        if(!function_exists('get_declared_interfaces')) continue;
        $system[$k] = get_declared_interfaces();
        asort($system[$k]);
        break;
      case '_PROTOCOLS':
        if(!function_exists('getprotobyname')) continue;
        $protocols = array("ip","icmp","ggp","tcp","egp","pup","udp","hmp","xns-idp","rdp","rvd" );
        foreach($protocols as $p)
          $system[$k][$p] = getprotobyname($p)?'available':'no';
        asort($system[$k]);
        break;
      case '_USAGE':
        if(!function_exists('getrusage')) continue;
        $system[$k] = getrusage();
        break;
    }
  }

  #  Filter debug related informations from system output
  $pattern = "~.*({$debugID}|{$reg['cfg']['globalIdentifier']}|{$reg['cfg']['functionName']}_message_).*~i";
  foreach(array_keys($system) as $s){
    foreach(array_keys($system[$s]) as $k)
      if(preg_match($pattern,$k)) unset($system[$s][$k]);
  }


  #  JS-Navigation
  $nav = array();
  $nav['links']       = array($debugID);
  $nav['sections']    = array($debugID);
  $nav['parents']     = array(0);
  $nav['singles']     = array(0);
  $nav['standalone']  = array(0);
  $nav['source']      =  array("");
  $nav['styles']      = array('new Array("#555;transparent;#BBB;normal;pointer","#555;#ccc;#888;normal;","black;#eee;#888;bold;default","black;#eee;#888;bold;")');

  foreach(array_keys($sections) as $id){
    $nav['links'][]       = $id;
    $nav['sections'][]    = $debugID."_".$id;
    $nav['parents'][]     = 0;
    $nav['singles'][]     = 1;
    $nav['standalone'][]  = 0;

    switch($id){
      case "errors":
        $nav['source'][]  = "";
        $nav['styles'][]  = 'null';
        foreach(array_keys($errorNav) as $id){
          if(!isset($errorTypes[$id])) continue;
          $nav['links'][]       = $errorTypes[$id];
          $nav['sections'][]    = $debugID."_errors_".$errorTypes[$id];
          $nav['parents'][]     = array_search('errors',$nav['links']);
          $nav['singles'][]     = 1;
          $nav['standalone'][]  = 0;
          $nav['source'][]      = "";
          $nav['styles'][]      = 'null';
        }
        break;

      case "messages":
        $nav['source'][]  = "";
        $nav['styles'][]  = 'null';
        foreach(array_keys($reg['messages']) as $id){
          $nav['links'][]       = $id;
          $nav['sections'][]    = $id;
          $nav['parents'][]     = array_search('messages',$nav['links']);
          $nav['singles'][]     = 0;
          $nav['standalone'][]  = 1;
          $nav['source'][]      = "";
          $nav['styles'][]      = 'null';
        }
        break;

      case "system":
        $nav['source'][]  = "";
        $nav['styles'][]  = 'new Array(";#D4D0C8;;;pointer",";#e4e0d8;;;",";#D4D0C8;;;",";#e4e0d8;;;")';
        foreach(array_keys($system) as $id){
          $nav['links'][]       = $id;
          $nav['sections'][]    = $debugID."_system_".$id;
          $nav['parents'][]     = array_search('system',$nav['links']);
          $nav['singles'][]     = 0;
          $nav['standalone'][]  = 0;
          $nav['source'][]      = "";
          $nav['styles'][]      = 'null';
        }
        break;

      case "search":
        $nav['source'][]  = "";
        $nav['styles'][]  = 'null';
        foreach(array_keys($reg['search']) as $id){
          $nav['links'][]       = $reg['search'][$id]['id'];
          $nav['sections'][]    = $reg['search'][$id]['id'];
          $nav['parents'][]     = array_search('search',$nav['links']);
          $nav['singles'][]     = 0;
          $nav['standalone'][]  = 1;
          $nav['source'][]      = "";
          $nav['styles'][]      = 'null';
        }
        break;

      case "source":
        $nav['styles'][]  = 'null';
        $nav['source'][]  = "?{$reg['cfg']['globalIdentifier']}=source";
        break;

      case "browse":
        $nav['styles'][]  = 'null';
        $nav['source'][]  = "?{$reg['cfg']['globalIdentifier']}=browse";
        break;

      case "doc":
        $nav['styles'][]  = 'null';
        $nav['source'][]  = "?{$reg['cfg']['globalIdentifier']}=doc";
        break;

      case "info":
        $nav['styles'][]  = 'null';
        $nav['source'][]  = "?{$reg['cfg']['globalIdentifier']}=info";
        break;

      default:
        $nav['styles'][]  = 'null';
        $nav['source'][]  = "";
    }
  }

  #  set data id for links
  foreach(array_keys($reg['events']) as $k)
    if($reg['events'][$k]['type'] == "errorhandler" && isset($errorTypes[$reg['events'][$k]['data']['type']]))
      $reg['events'][$k]['data']['id'] = $debugID."_errors_".$errorTypes[$reg['events'][$k]['data']['type']];

  #  process the history section at last
  if(array_key_exists('history',$sections)){
    $history = $sections['history'];
    unset($sections['history']);
    $sections['history'] = $history;
  }

  #  Colors
  $color = array( 'light'           => "#fff",
                  'dark'            => "#404040",
                  'grey'            => "#666",
                  'shadow'          => "#808080",
                  'darkbackground'  => "#8B8B8B",
                  'background'      => "#D4D0C8",
                  'class'           => "#d4e6c8",
                  'function'        => "#d4d0e6",
                  'group'           => "#d5ddf3",
                  'none'            => "#d4d0c8",
                  'user'            => "#e6d0c8");

  #  CSS styles
  $window = array();
  $window['left'] = 0;
  $window['top'] = 0;
  $window['zindex'] = 1000;
  $window['width'] = 640;
  $window['height'] = 480;
  $window['display'] = "scroll";
  $window['last'] = "min";

  #  update from cookie
  if(isset($_COOKIE["{$debugID}_window"]))
    list($window['left'],$window['top'],$window['zindex'],$window['width'],$window['height'],$window['display'],$window['last']) = explode(":",$_COOKIE["{$debugID}_window"]);


  #  set error styles
  $style['error'] = array(E_ERROR =>"padding: 5px;",
                          E_WARNING=>"padding: 5px;",
                          E_PARSE =>"padding: 5px;",
                          E_NOTICE =>"padding: 5px; color: #aaa;",
                          E_CORE_ERROR =>"padding: 5px; color: #f44;",
                          E_CORE_WARNING =>"padding: 5px;",
                          E_COMPILE_ERROR =>"padding: 5px;",
                          E_COMPILE_WARNING =>"padding: 5px;",
                          E_USER_ERROR =>"padding: 5px;",
                          E_USER_WARNING =>"padding: 5px;",
                          E_USER_NOTICE =>"padding: 5px;",
                          E_ALL =>"padding: 5px;",
                          E_STRICT =>"padding: 5px; color: #44f;"
                        );
?>
<!-- <?php echo $debugFunction?> START -->
<div id="<?php echo $debugID?>_loader" style="position: absolute; top: 0px; left: 0px; z-index: 999; background: #D4D0C8; color: #999; border: 1px solid #999; border-top: 0;">Loading <?php echo $debugFunction?> application...</div>
<div id="<?php echo $debugID?>_window" style="display: none; position: absolute; z-index: <?php echo $window['zindex']?>; left: <?php echo $window['left']?>px; top: <?php echo $window['top']?>px;">
  <a name="<?php echo $debugID?>_window_a" style="position: relative; top: -30px;"></a>
  <script type="text/javascript">
    <!--
    /**
    * Sections
    */
    var <?php echo $debugID?>_nav_sections   = new Array('<?php echo implode("','",$nav['sections'])?>');
    var <?php echo $debugID?>_nav_parents    = new Array(<?php echo implode(",",$nav['parents'])?>);
    var <?php echo $debugID?>_nav_singles    = new Array(<?php echo implode(",",$nav['singles'])?>);
    var <?php echo $debugID?>_nav_standalone = new Array(<?php echo implode(",",$nav['standalone'])?>);
    var <?php echo $debugID?>_nav_source     = new Array('<?php echo implode("','",$nav['source'])?>');

    //  Stylestring: "textcolor;backgroundcolor;[bordercolor|top,right,bottom,left];fontweight;cursor"
    // new Array(defaultStyle,hoverStyle,activeStyle,activeHoverStyle)
    var <?php echo $debugID?>_nav_style = new Array(<?php echo implode(",",$nav['styles'])?>);


    /**
    * Debug window object
    */
    <?php echo $debugID?> = new Object();
    <?php echo $reg['cfg']['globalIdentifier']?> = <?php echo $debugID?>;


    /**
    * debug.init()
    * Init takes control of your website to prevent conflicts!
    *
    * @return  void
    */
    <?php echo $debugID?>.init = function(){
      //  Action
      this.action = 'none'; // none, drag, resize

      //  State
      this.state = new Object();
      this.state.display = "scroll"; // min, max, scroll
      this.state.last    = "min"; // min, max, scroll
      this.state.resize  = '';

      // Timer
      this.timer = new Object();

      // Process stack
      this.processStack = new Array();

      //  Sections stack
      this.sections = new Object();

      // Configuration
      this.cfg = new Object();
      this.cfg.minwidth        = 160;
      this.cfg.minheight       = <?php echo $cfgMinHeight=68?>;
      this.cfg.minimizedwidth  = 120;
      this.cfg.minimizedheight = 26;
      this.cfg.resizerange     = 4;

      // References
      this.ref = new Object();
      this.ref.loader   = document.getElementById('<?php echo $debugID?>_loader');
      this.ref.window   = document.getElementById('<?php echo $debugID?>_window');
      this.ref.title    = document.getElementById('<?php echo $debugID?>_window_title');
      this.ref.ico      = document.getElementById('<?php echo $debugID?>_window_title_box_ico');
      this.ref.body     = document.getElementById('<?php echo $debugID?>_body');
      this.ref.menu     = document.getElementById('<?php echo $debugID?>_body_menu_box');
      this.ref.content  = document.getElementById('<?php echo $debugID?>_content');
      this.ref.status   = document.getElementById('<?php echo $debugID?>_status');
      this.ref.section  = new Object();

      // References: Buttons
      this.ref.btns = new Object();
      this.ref.btns.min     = document.getElementById('<?php echo $debugID?>_window_bt_min');
      this.ref.btns.max     = document.getElementById('<?php echo $debugID?>_window_bt_max');
      this.ref.btns.scroll  = document.getElementById('<?php echo $debugID?>_window_bt_scroll');
      this.ref.btns.close   = document.getElementById('<?php echo $debugID?>_window_bt_close');

      //  Restore
      this.restore = new Object();
      this.restore.left     = <?php echo $window['left']?>;
      this.restore.top      = <?php echo $window['top']?>;
      this.restore.zIndex   = <?php echo $window['zindex']?>;
      this.restore.width    = <?php echo $window['width']?>;
      this.restore.height   = <?php echo $window['height']?>;
      this.restore.display  = "<?php echo $window['display']?>";
      this.restore.last     = "<?php echo $window['last']?>";
      this.restore.cursor   = "default";
      this.restore.file     = "";
      this.restore.line     = 0;
      this.restore.doc      = new Object();
      this.restore.doc.onmousemove = document.onmousemove;

      //  Assign event listener
      document.onmousemove = function(evt){<?php echo $debugID?>.eventUpdate(evt)};

      // Load styles (not compatible with IE6, any solution?)
      var cssPath = [ "?<?php echo $reg['cfg']['globalIdentifier']?>=css&key=<?php echo $debugID?>",
                      "./?<?php echo $reg['cfg']['globalIdentifier']?>=css&key=<?php echo $debugID?>"];
      for(var i in cssPath){
        if (document.all) {
          document.createStyleSheet(cssPath[i]);
        }else{
          var stylelink = document.createElement("link");
          stylelink.setAttribute("type", "text/css", 0);
          stylelink.setAttribute("rel", "stylesheet", 0);
          stylelink.setAttribute("media", "screen", 0);
          stylelink.setAttribute("href", cssPath[i], 0);
          document.getElementsByTagName('head')[0].appendChild(stylelink);
        }
      }

      //  Sections
      for(var i=0; i < <?php echo $debugID?>_nav_sections.length; i++){
        var sec = document.getElementById(<?php echo $debugID?>_nav_sections[i]);
        var con = document.getElementById(<?php echo $debugID?>_nav_sections[i]+"_c");
        if(!sec || !con){
          <?php echo $debugID?>_nav_sections.splice(i,1);
          <?php echo $debugID?>_nav_parents.splice(i,1);
          <?php echo $debugID?>_nav_singles.splice(i,1);
          <?php echo $debugID?>_nav_standalone.splice(i,1);
          <?php echo $debugID?>_nav_style.splice(i,1);
          <?php echo $debugID?>_nav_source.splice(i,1);
          i--;
          continue;
        }
        sec.onclick = function(evt){<?php echo $debugID?>.sectionOnClick(evt)};
        sec.onmouseover = function(evt){<?php echo $debugID?>.sectionOnOver(evt)};
        sec.onmouseout = function(evt){<?php echo $debugID?>.sectionOnOut(evt)};
        sec.style.cursor = 'pointer';
      }
      for(var i = <?php echo $debugID?>_nav_sections.length-1; i > 0; i--){
        sec = this.getSection(<?php echo $debugID?>_nav_sections[i]);
        if(!sec) continue;
        if(!sec.standalone) sec.open(true);
        if(!sec.standalone && !sec.single && sec.brothers.length) sec.open(false);
      }
      this.sectionRestore();

      this.set(this.restore.left,this.restore.top,this.restore.width,this.restore.height);

      this.ref.window.style.visibility = "hidden";
      this.ref.window.style.display = "block";

      switch(this.restore.display){
        case "min": this.minimize(); break;
        case "max": this.maximize(); break;
      }

      this.state.last = this.restore.last;

      this.timer.loader = window.setInterval("<?php echo $debugID?>.afterLoad()",1000);

    }

    /**
    * debug.afterLoad()
    *
    * @return  void
    */
    <?php echo $debugID?>.afterLoad = function(){
      var loaded = false;

      // DOCTYPE validation
      if(document.all && document.body.parentNode.parentNode.firstChild.tagName != "!"){
        alert("You have no valid doctype defined, this will cause errors in the debugger presentation!");
        loaded = true;
      }

      if(true){
        this.ref.window.style.visibility = "visible";
        loaded = true;
      }

      if(!loaded) return;

      this.ref.loader.style.display = "none";
      window.clearInterval(this.timer.loader);
      if(this.state.display == "max") this.maximize();
      this.save();

    }

    /**
    * debug.bringToFront()
    *
    * @return  void
    */
    <?php echo $debugID?>.bringToFront = function(){
      var dom = document.all?document.all:document.getElementsByTagName('*');
      for(var i=0; i < dom.length; i++)
        if(dom[i].style.zIndex >= this.ref.window.style.zIndex)
          this.ref.window.style.zIndex = parseInt(dom[i].style.zIndex)+1;
      this.status('Auto adjusted level to: '+this.ref.window.style.zIndex);
    }

    /**
    * debug.eventStart()
    *
    * @param  string  type
    * @return  void
    */
    <?php echo $debugID?>.eventStart = function(type){
      // cancle current invalid events
      switch(type){
        case "resize":
          if(this.state.display != "scroll") return;
          break;
        case "forward":
          if(this.action != "over" && this.action != "none") return;
          break;
        default:
      }

      //  shutdown open events
      this.eventStop();

      this.action = type;
      switch(this.action){
        case "resize":
          this.restore.width = this.ref.window.offsetWidth;
          this.restore.height = this.ref.window.offsetHeight;
          // and do the same like on drag

        case "drag":
          this.restore.left = this.ref.window.offsetLeft;
          this.restore.top = this.ref.window.offsetTop;
          break;

        case "forward":
          if(this.state.display == "scroll"){
            this.restore.width = this.ref.window.offsetWidth;
            this.restore.height = this.ref.window.offsetHeight;
            this.restore.left = this.ref.window.offsetLeft;
            this.restore.top = this.ref.window.offsetTop;
          }
          this.restore.zIndex = this.ref.window.style.zIndex;
          break;

        case "over":
          // nothing

        default:
          return;
      }

      this.restore.doc.onmouseup = document.onmouseup;
      document.body.style.MozUserSelect = "none";
      this.restore.doc.onselectstart = document.onselectstart;
      document.onselectstart = function(){ return false; };
      document.onmouseup = function(evt){<?php echo $debugID?>.eventStop(evt)};

    }

    /**
    * debug.eventUpdate()
    *
    * @param  object  evt  Event
    * @return  void
    */
    <?php echo $debugID?>.eventUpdate = function(evt){
      if(!evt) evt = window.event;
      var l = this.ref.window.offsetLeft;
      var t = this.ref.window.offsetTop;
      var w = this.ref.window.offsetWidth;
      var h = this.ref.window.offsetHeight;
      var r = l+w;
      var b = t+h;
      var sl = document.body.scrollLeft > window.document.documentElement.scrollLeft?document.body.scrollLeft:window.document.documentElement.scrollLeft;
      var st = document.body.scrollTop > window.document.documentElement.scrollTop?document.body.scrollTop:window.document.documentElement.scrollTop;
      var x = evt.clientX+sl;
      var y = evt.clientY+st;

      if(!this.restore.left) this.restore.left = l;
      if(!this.restore.top) this.restore.top = t;
      if(!this.restore.width) this.restore.width = w;
      if(!this.restore.height) this.restore.height = h;
      if(!this.restore.x) this.restore.x = x;
      if(!this.restore.y) this.restore.y = y;

      switch(this.action){

        case "over":
          if(x < l || x > r || y < t || y > b){
            this.action = "none";
            break;
          }

          this.restore.x = x;
          this.restore.y = y;

          if(this.state.display == "scroll"){
            var range = this.cfg.resizerange;
            var resize = '';

            if(y >= t && y <= t+range) resize += 'n';
            if(y >= b-range && y <= b) resize += 's';
            if(x >= l && x <= l+range) resize += 'w';
            if(x >= r-range && x <= r) resize += 'e';
            if((x >= r-(range*range) && x <= r) && (y >= b-(range*range) && y <= b)) resize = 'se';

            var cursor = resize.length?resize+"-resize":this.restore.cursor;

            if(this.ref.window.style.cursor != cursor){
              this.ref.window.style.cursor = cursor;
              this.ref.window.onmousedown = (cursor != 'default')?function(){<?php echo $debugID?>.eventStart('resize')}:null;
              this.state.resize = resize;
            }
          }
          break;

        case "forward":
          var zIndex = parseInt(this.restore.zIndex)+((y-this.restore.y)+(x-this.restore.x));
          this.status("Level: "+this.ref.window.style.zIndex);
          this.ref.window.style.zIndex = zIndex>0?zIndex:0;

          if(this.state.display == "scroll"){
            var zWidth = Math.round(this.restore.width / this.restore.zIndex * zIndex);
            var zHeight = Math.round(this.restore.height / this.restore.zIndex * zIndex);
            var zLeft = Math.round(this.restore.left + (this.restore.width - zWidth) / 2);
            var zTop = Math.round(this.restore.top + (this.restore.height - zHeight) / 2);
            this.set(zLeft,zTop,zWidth,zHeight);
          }

          break;

        case "drag":
          var dl = (this.restore.left+x-this.restore.x)>0?this.restore.left+x-this.restore.x:0;
          var dt = (this.restore.top+y-this.restore.y)>0?this.restore.top+y-this.restore.y:0;
          this.set(dl,dt);
          break;

        case "resize":
          //  resize directions
          var rnorth = this.state.resize.search(/n/) >= 0;
          var rsouth = this.state.resize.search(/s/) >= 0;
          var reast = this.state.resize.search(/e/) >= 0;
          var rwest = this.state.resize.search(/w/) >= 0;

          var rw = null;
          var rh = null;
          var rl = null;
          var rt = null;

          //  resize south
          if(rsouth) rh = (y > t+this.cfg.minheight?y-t:this.cfg.minheight);
          //  resize east
          if(reast) rw = (x > l+this.cfg.minwidth?x-l:this.cfg.minwidth);

          //  resize north
          if(rnorth){
            if(y <= (b-this.cfg.minheight)){
              rt = ((this.restore.top+y-this.restore.y)>0?this.restore.top+y-this.restore.y:0);
              rh = (b-((this.restore.top+y-this.restore.y)>0?this.restore.top+y-this.restore.y:0));
            }else{
              rt = (b-this.cfg.minheight);
              rh = this.cfg.minheight;
            }
          }

          //  resize west
          if(rwest){
            if(x <= (r-this.cfg.minwidth)){
              rl = ((this.restore.left+x-this.restore.x)>0?this.restore.left+x-this.restore.x:0);
              rw = (r-((this.restore.left+x-this.restore.x)>0?this.restore.left+x-this.restore.x:0));
            }else{
              rl = (r-this.cfg.minwidth);
              rw = this.cfg.minwidth;
            }
          }

          this.set(rl,rt,rw,rh);
          break;
        default:
          if(x >= l && x <= r && y >= t && y <= b) this.eventStart("over");
      }
      if(this.restore.doc.onmousemove) this.restore.doc.onmousemove(evt);
    }

    /**
    * debug.eventStop()
    *
    * @return  void
    */
    <?php echo $debugID?>.eventStop = function(){
      if(this.action == "none") return;
      switch(this.action){
        case "resize":
          this.restore.width = false;
          this.restore.height = false;
          this.state.resize = '';
          // and do the same like on resize
        case "drag":
          this.restore.x = false;
          this.restore.y = false;
          document.body.style.MozUserSelect = "";
          document.onselectstart = this.restore.doc.onselectstart;
          // and do the same like on forward
        case "forward":
          this.ref.window.onmousedown = null;
          this.ref.window.style.cursor = 'default';
          document.onmouseup = this.restore.doc.onmouseup;
          break;
        case "over":
          // nothing
          break;
      }
      this.action = 'none';
      this.save();
    }

    /**
    * debug.set()
    *
    * @param  int  l  left
    * @param  int  t  top
    * @param  int  w  width
    * @param  int  h  height
    * @return  void
    */
    <?php echo $debugID?>.set = function(l,t,w,h){
      if(l == null) l = this.ref.window.offsetLeft;
      if(t == null) t = this.ref.window.offsetTop;
      if(w == null) w = this.ref.window.offsetWidth;
      if(h == null) h = this.ref.window.offsetHeight;

      if(isNaN(l) || l < 0) l = 0;
      if(isNaN(t) || t < 0) t = 0;
      if(isNaN(w) || w < 0) w = this.cfg.minwidth;
      if(isNaN(h) || h < 0) h = this.cfg.minheight;

      l = parseInt(l);
      t = parseInt(t);
      w = parseInt(w);
      h = parseInt(h);

      this.ref.window.style.left = l+"px";
      this.ref.window.style.top = t+"px";
      this.ref.window.style.width = w+"px";
      this.ref.window.style.height = h+"px";

      this.ref.content.style.height = (h<this.cfg.minheight?0:h-this.cfg.minheight)+"px";

    }

    /**
    * debug.title()
    *
    * @param  string  title  Set title of debug window
    * @return  void
    */
    <?php echo $debugID?>.title = function(title){
      if(!this.restore.title) this.restore.title = this.ref.title.innerHTML;
      this.ref.title.innerHTML = title?title+" - "+this.restore.title:this.restore.title;
    }


    /**
    * debug.status()
    *
    * @param  string  status  Set status or if NULL restor default value
    * @return  void
    */
    <?php echo $debugID?>.status = function(status){
      if(!this.restore.status) this.restore.status = this.ref.status.innerHTML;
      this.ref.status.innerHTML = status?status:this.restore.status;
      if(this.ref.status.innerHTML == this.restore.status) return;
      if(this.timer.status) window.clearTimeout(this.timer.status);
      this.timer.status = window.setTimeout("<?php echo $debugID?>.status()", 3000);
    }

    /**
    * debug.titlebar()
    *
    * @return  void
    */
    <?php echo $debugID?>.titlebar = function(){
      switch(this.state.last){
        case "min": this.minimize();
          break;
        case "max": this.maximize();
          break;
        case "scroll": this.scroll();
          break;
      }
    }

    /**
    * debug.minimize()
    *
    * @return  void
    */
    <?php echo $debugID?>.minimize = function(){
      if(this.state.display == "min") return this.scroll();
      this.ref.body.style.display = "none";
      if(this.state.display == "scroll"){
        this.restore.width = this.ref.window.offsetWidth;
        this.restore.height = this.ref.window.offsetHeight;
      }
      this.set(null,null,this.cfg.minimizedwidth,this.cfg.minimizedheight);
      this.state.last = this.state.display;
      this.state.display = "min";
      this.btnupdate();
    }

    /**
    * debug.scroll()
    *
    * @return  void
    */
    <?php echo $debugID?>.scroll = function(){
      if(this.state.display == "min")
        this.ref.body.style.display = "block";
      this.set(null,null,this.restore.width,this.restore.height);
      this.state.last = "min";
      this.state.display = "scroll";
      this.btnupdate();
    }

    /**
    * debug.maximize()
    *
    * @return  void
    */
    <?php echo $debugID?>.maximize = function(){
      if(this.state.display == "scroll"){
        this.restore.top = this.ref.window.offsetTop;
        this.restore.left = this.ref.window.offsetLeft;
        this.restore.width = this.ref.window.offsetWidth;
        this.restore.height = this.ref.window.offsetHeight;
      }
      if(this.state.display == "min"){
        this.ref.body.style.display = "block";
      }

      this.set(null,null,this.cfg.minwidth,this.cfg.minheight);
      var w = this.cfg.minwidth;
      if(w < (this.ref.section.scrollWidth+10))
        w = this.ref.section.scrollWidth+10;
       var h = this.ref.section.scrollHeight+this.cfg.minheight;

      this.state.last = "min";
      this.state.display = "max";
      this.set(null,null,w,h);
      this.btnupdate();

    }

    /**
    * debug.close()
    *
    * @return  void
    */
    <?php echo $debugID?>.close = function(){
      this.state.display = "closed";
      this.ref.window.style.display = "none";
    }


    /**
    * debug.btnupdate()
    *
    * @return  void
    */
    <?php echo $debugID?>.btnupdate = function(){
      switch(this.state.display){
        case "min":
          this.ref.btns.scroll.style.right = this.ref.btns.min.style.right;
          this.ref.btns.min.style.display = "none";
          this.ref.btns.max.style.display = "block";
          this.ref.btns.scroll.style.display = "block";
          break;
        case "max":
          this.ref.btns.scroll.style.right = this.ref.btns.max.style.right;
          this.ref.btns.min.style.display = "block";
          this.ref.btns.max.style.display = "none";
          this.ref.btns.scroll.style.display = "block";
          break;
        case "scroll":
          this.ref.btns.min.style.display = "block";
          this.ref.btns.max.style.display = "block";
          this.ref.btns.scroll.style.display = "none";
          break;
      }
      this.save();
    }


    /**
    * debug.copy()
    *
    * @param  string  text  copy this test to clipboard
    * @return void
    */
    <?php echo $debugID?>.copy = function(text){
      if(window.clipboardData) window.clipboardData.setData('Text', text);
        else alert("Your browser dosn\'t support the clipboardData object!");
    }


    /**
    * debug.getSection()
    *
    * @param  string  id  The id of an section.
    * @return object
    */
    <?php echo $debugID?>.getSection = function(id){
      if(typeof id != "string") return null;
      if(this.sections[id]) return this.sections[id];
      var key = null;
      for(var i=0; i < <?php echo $debugID?>_nav_sections.length; i++)
        if(<?php echo $debugID?>_nav_sections[i] === id) key = i;
      if(key === null) return null;

      var section = new Object();
      section.id = id;
      section.key = key;
      section.single = <?php echo $debugID?>_nav_singles[key];
      section.standalone = <?php echo $debugID?>_nav_standalone[key];
      section.obj = document.getElementById(id);
      section.anchor = id+"_a";
      section.content = document.getElementById(id+"_c");
      section.source = <?php echo $debugID?>_nav_source[key];
      section.parent = <?php echo $debugID?>_nav_sections[<?php echo $debugID?>_nav_parents[key]]!=id?<?php echo $debugID?>_nav_sections[<?php echo $debugID?>_nav_parents[key]]:null;
      section.parentKey = <?php echo $debugID?>_nav_parents[key];
      section.children = new Array();
      section.brothers = new Array();
      for(var i=0; i < <?php echo $debugID?>_nav_sections.length; i++){
        if(<?php echo $debugID?>_nav_parents[i] == key)
          section.children.push(<?php echo $debugID?>_nav_sections[i]);
        if(section.parent)
          if(i != key && i != <?php echo $debugID?>_nav_parents[key] && <?php echo $debugID?>_nav_parents[i] == <?php echo $debugID?>_nav_parents[key])
            section.brothers.push(<?php echo $debugID?>_nav_sections[i]);
      }
      section.display = new Function('display','if(display) this.styleActive(); else this.styleDefault(); this.content.style.display = display?"":"none"; if(display && this.parent == "<?php echo $debugID?>") <?php echo $debugID?>.title(this.obj.firstChild.nodeValue);');
      section.displayed = new Function('return this.content.style.display != "none";');
      section.open = new Function('display','this.display(display); if(display && this.single && this.brothers.length) for(var i=0; i < this.brothers.length;i++) <?php echo $debugID?>.getSection(this.brothers[i]).open(false); if(display && this.parent) <?php echo $debugID?>.getSection(this.parent).open(display);');
      section.visible = new Function('return this.displayed() && (this.parent?<?php echo $debugID?>.getSection(this.parent).visible():true);');
      section.style = function(color,background,border,font,cursor){
        if(color.length) this.obj.style.color = color;
        if(background.length) this.obj.style.backgroundColor = background;
        if(border.length){
          if(border.indexOf(',')>0){
            split = border.split(',');
            this.obj.style.borderTopColor = split[0];
            this.obj.style.borderRightColor = split[1];
            this.obj.style.borderBottomColor = split[2];
            this.obj.style.borderLeftColor = split[3];
          }else{
            this.obj.style.borderTopColor = border;
            this.obj.style.borderRightColor = border;
            this.obj.style.borderBottomColor = border;
            this.obj.style.borderLeftColor = border;
          }
        }
        if(font.length) this.obj.style.fontWeight = font;
        if(cursor.length) this.obj.style.cursor = cursor;
      }
      section.styleDefault = function(){
        if(!<?php echo $debugID?>_nav_style[this.parentKey] || !<?php echo $debugID?>_nav_style[this.parentKey][0]) return;
        var split = <?php echo $debugID?>_nav_style[this.parentKey][0].split(';');
        this.style(split[0],split[1],split[2],split[3],split[4]);
        this.obj.style.paddingRight = "4px";
      }
      section.styleHover = function(){
        if(this.displayed()){
          if(!<?php echo $debugID?>_nav_style[this.parentKey] || !<?php echo $debugID?>_nav_style[this.parentKey][3]) return;
          var split = <?php echo $debugID?>_nav_style[this.parentKey][3].split(';');
        }else{
          if(!<?php echo $debugID?>_nav_style[this.parentKey] || !<?php echo $debugID?>_nav_style[this.parentKey][1]) return;
          var split = <?php echo $debugID?>_nav_style[this.parentKey][1].split(';');
        }
        this.style(split[0],split[1],split[2],split[3],split[4]);

      }
      section.styleActive = function(){
        if(!<?php echo $debugID?>_nav_style[this.parentKey] || !<?php echo $debugID?>_nav_style[this.parentKey][2]) return;
        var split = <?php echo $debugID?>_nav_style[this.parentKey][2].split(';');
        this.style(split[0],split[1],split[2],split[3],split[4]);
      }

      return this.sections[id] = section;
    }


    /**
    * debug.sectionOnClick()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionOnClick = function(evt){
      if (!evt) evt = window.event;
      var src = evt.target?evt.target:evt.srcElement;
      while(!src.id && src != null) src = src.parentNode;

      this.sectionChange(src.id);

      var sec = this.getSection(src.id);
      sec.styleActive();
      if(sec.source){
        sec.obj.style.backgroundPosition = "100% -19px";
        this.makeHttpRequest(sec.source,sec.id+"_content");
      }

    }

    /**
    * debug.sectionSetContent()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionSetContent = function(evt){

    }


    /**
    * debug.sectionOnOver()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionOnOver = function(evt){
      if (!evt) evt = window.event;
      var src = evt.target?evt.target:evt.srcElement;
      while(!src.id && src != null) src = src.parentNode;

      var sec = this.getSection(src.id);
      if(!sec) return;
      sec.styleHover();
    }

    /**
    * debug.sectionOnOut()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionOnOut = function(evt){
      if (!evt) evt = window.event;
      var src = evt.target?evt.target:evt.srcElement;
      while(!src.id && src != null) src = src.parentNode;

      var sec = this.getSection(src.id);
      if(sec.displayed()) sec.styleActive();
        else sec.styleDefault();

    }


    /**
    * debug.sectionChange()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionChange = function(id,force,children){
      var s = this.getSection(id);
      if(s.single && s.visible() && children == null) return s.id;
      s.open(force?true:!s.visible());
      if(children != null){
        for(var i=0; i < s.children.length; i++){
          var c = <?php echo $debugID?>.getSection(s.children[i]);
          c.open(children);
          if(c.standalone) this.sectionSave(c.id);
        }
      }
      if(s.parentKey == 0) this.ref.section = s.content;
      if(this.state.display == "max") this.maximize();
      if(force && document.getElementsByName(s.anchor).length){
        location.hash = s.anchor;
        location.hash = "<?php echo $debugID?>_window_a";
      }
      this.sectionSave(s.standalone?id:'');
    }

    /**
    * debug.sectionSave()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionSave = function(id){
      var sec = this.getSection(id);
      if(sec && sec.standalone){
        this.setCookie(sec.id,sec.displayed()?1:0);
        return;
      }

      var cs = new Array();
      for(var i=0; i < <?php echo $debugID?>_nav_sections.length; i++){
        var sec = this.getSection(<?php echo $debugID?>_nav_sections[i]);
        if(!sec || sec.standalone) continue;
        cs.push(sec.id+"="+(sec.displayed()?1:0));
      }
      this.setCookie('<?php echo $debugID?>_sections',cs.join(';'));
    }

    /**
    * debug.sectionRestore()
    *
    * @return  void
    */
    <?php echo $debugID?>.sectionRestore = function(){
      var cs = this.getCookie('<?php echo $debugID?>_sections');
      // load first section if no cookie exists
      if(!cs) return this.sectionChange(<?php echo $debugID?>_nav_sections[1],true,true);
      cs = cs.split(";");
      var e = null;
      for(var i=0;i<cs.length;i++){
        var s = cs[i].split('=');
        var sec = this.getSection(s[0]);
        if(!sec) continue;
        if(sec.parent && sec.id.substring(sec.parent.length+1) == 'errors') e = sec;
        if(sec.single && s[1]>0){
          sec.open(true);
          if(sec.parentKey == 0) this.ref.section = sec.content;
        }else sec.display(s[1]>0);
        if(s[1]>0 && sec.source){
          this.setContent(sec.id+"_content","<a href=\"javascript:<?php echo $debugID?>.makeHttpRequest('"+sec.source+"','"+sec.id+"_content');\">click here to reload this content from server</a>");
        }
      }
      if(e){
        var ea = false;
        for(var i=0;i<e.children.length;i++)
          if(this.getSection(e.children[i]).displayed()) ea = true;
        if(!ea) this.getSection(e.children[0]).display(true);
      }
    }


    /**
    * debug.showTrace()
    *
    * @param  string  id  The id of an section.
    * @return void
    */
    <?php echo $debugID?>.showTrace = function(id){
      var obj = document.getElementById(id+'_trace');
      if(!obj) return;
      obj.style.display = obj.style.display=='none'?'block':'none';
      if(this.state.display == "max") this.maximize();
    }

    /**
    * debug.save()
    *
    * Remembers the debug window state in a cookie.
    *
    * @return  void
    */
    <?php echo $debugID?>.save = function(){
      var l = this.ref.window.offsetLeft;
      var t = this.ref.window.offsetTop;
      var z = this.ref.window.style.zIndex;
      var w = this.ref.window.offsetWidth;
      var h = this.ref.window.offsetHeight;
      if(this.state.display == "min"){
        w = this.restore.width;
        h = this.restore.height;
      }
      this.setCookie('<?php echo $debugID?>_window',l+":"+t+":"+z+":"+w+":"+h+":"+this.state.display+":"+this.state.last);
    }

    /**
    * debug.setCookie()
    *
    * @param  string  key
    * @param  string  value
    * @param  object  expires
    * @param  string  domain
    * @param  bool    secure
    *
    * @return  void
    */
    <?php echo $debugID?>.setCookie = function(key,value,expires,path,domain,secure){
      if(this.getCookie(key) == value) return;
      var curCookie = key + "=" + escape(value) +
                    ((expires) ? "; expires=" + expires.toGMTString() : "") +
                    ((path) ? "; path=" + path : "; path=/") +
                    ((domain) ? "; domain=" + domain : "") +
                    ((secure) ? "; secure" : "");
      document.cookie = curCookie;
    }


    /**
    * debug.getCookie()
    *
    * @return  void
    */
    <?php echo $debugID?>.getCookie = function(key){
      var dc = document.cookie;
      var prefix = key + "=";
      var begin = dc.indexOf("; " + prefix);
      if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
      } else
        begin += 2;
      var end = document.cookie.indexOf(";", begin);
      if (end == -1)
        end = dc.length;
      return unescape(dc.substring(begin + prefix.length, end));
    }

    /**
    * debug.makeHttpRequest()
    *
    * @return
    */
    <?php echo $debugID?>.makeHttpRequest = function(url, sendTo, xml){
      var http_request = false;
      if (window.XMLHttpRequest) { // Mozilla, Safari,...
        http_request = new XMLHttpRequest();
        if (http_request.overrideMimeType) {
            http_request.overrideMimeType('text/html; charset=utf-8');
        }
      } else if (window.ActiveXObject) { // IE
        try {
          http_request = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
          try {
            http_request = new ActiveXObject("Microsoft.XMLHTTP");
          } catch (e) {}
        }
      }
      if (!http_request) {
        alert('Unfortunatelly you browser doesn\'t support this AJAX feature.');
        return false;
      }

      if(!this.processRun(url)) return false;

      http_request.onreadystatechange = function() {
        if (http_request.readyState == 4) {
          if (http_request.status == 200) {
            if(<?php echo $debugID?>.getContent(sendTo) !== false) <?php echo $debugID?>.setContent(sendTo,http_request.responseText);
              else eval(sendTo + '(http_request.response' + (xml?'XML':'Text') + ')');
          } else {
            switch(http_request.status){
              case 12029:
              case 12030:
              case 12031:
              case 12152:
              case 12159:
                <?php echo $debugID?>.makeHttpRequest(url, sendTo, xml);
                break;
              default:
                alert('<?php echo $debugFunction?>(): There was a problem with the request. (Code: ' + http_request.status + ')');
            }
          }
          <?php echo $debugID?>.processDone(url);
        }
      }
      http_request.open('GET', url, true);
      http_request.send(null);
    }

    /**
    * debug.processRun()
    *
    * @return
    */
    <?php echo $debugID?>.processRun = function(id){
      for(var i=0; i < this.processStack.length; i++)
        if(this.processStack[i] == id) return false;

      this.processStack.push(id);
      this.status('Loading...');
      this.restore.cursor = 'wait';
      this.ref.ico.className = 'ani';
      this.ref.window.style.cursor = this.restore.cursor;
      if(!this.timer.ajax) this.timer.ajax = window.setInterval("<?php echo $debugID?>.processDone()", 500);
      return true;
    }

    /**
    * debug.processDone()
    *
    * @return
    */
    <?php echo $debugID?>.processDone = function(id){
      for(var i=0; i < this.processStack.length; i++)
        if(this.processStack[i] == id)
          this.processStack.splice(i,1);

      if(!this.processStack.length){
        this.status();
        window.clearInterval(this.timer.ajax);
        this.timer.ajax = false;
        this.restore.cursor = 'default';
        this.ref.ico.className = '';
        this.ref.window.style.cursor = this.restore.cursor;
      }else{
        this.status(this.getContent(this.ref.status.id)+'.');
      }
    }

    /**
    * debug.setContent()
    *
    * @return
    */
    <?php echo $debugID?>.setContent = function(id,data,add){
      if(add !== true) document.getElementById(id).innerHTML = "";
      document.getElementById(id).innerHTML += data;
      if(this.state.display == "max") this.maximize();
    }

    /**
    * debug.getContent()
    *
    * Get content of Node[id]
    *
    * @return
    */
    <?php echo $debugID?>.getContent = function(id){
      return document.getElementById(id)?document.getElementById(id).innerHTML:false;
    }

    /**
    * debug.browse()
    *
    * @return
    */
    <?php echo $debugID?>.browse = function(data){
      this.setContent('<?php echo $nav['sections'][array_search('browse',$nav['links'])]?>_content',data);
      this.sectionChange('<?php echo $nav['sections'][array_search('browse',$nav['links'])]?>',true);
    }

    /**
    * debug.getSource()
    *
    * @return
    */
    <?php echo $debugID?>.getSource = function(file,line,force){
      this.restore.file = file;
      this.restore.line = line;
      this.sectionChange('<?php echo $nav['sections'][array_search('source',$nav['links'])]?>',true);
      this.makeHttpRequest("?<?php echo $reg['cfg']['globalIdentifier']?>=source&file="+file+"&line="+line+(force?"&force=1":""),'<?php echo $debugID?>.setSource');
    }

    /**
    * debug.setSource()
    *
    * @return
    */
    <?php echo $debugID?>.setSource = function(data){
      this.setContent('<?php echo $nav['sections'][array_search('source',$nav['links'])]?>_content',data);
      window.location.hash = "<?php echo $reg['cfg']['globalIdentifier']?>_source_line_"+((this.restore.line-10)>0?this.restore.line-10:0);
    }


    -->
  </script>
  <div id="<?php echo $debugID?>_window_outerborder">
    <div id="<?php echo $debugID?>_window_innerborder">
      <!-- Head -->
      <div id="<?php echo $debugID?>_window_head" onselectstart="return false">
        <!-- Title -->
        <div id="<?php echo $debugID?>_window_title_box">
          <div id="<?php echo $debugID?>_window_title_box_content" onmousedown="<?php echo $debugID?>.eventStart('drag')" ondblclick="<?php echo $debugID?>.titlebar()">
            <div id="<?php echo $debugID?>_window_title_box_ico" class=""></div>
            <div id="<?php echo $debugID?>_window_title"><?php echo $windowTitle?></div>
          </div>
        </div>
        <!-- Buttons -->
        <div id="<?php echo $debugID?>_window_bt_min" style="right: 49px;" onclick="<?php echo $debugID?>.minimize();" class="<?php echo $debugID?>_window_bt" title="minimize">
          <div style="position: absolute; left: 2px; top: 2px; width: 6px; height: 2px; background-color: black; overflow: hidden;"></div>
        </div>
        <div id="<?php echo $debugID?>_window_bt_scroll" style="right: 34px;" onclick="<?php echo $debugID?>.scroll();" class="<?php echo $debugID?>_window_bt" title="scroll">
          <div style="position: absolute; left: 2px; top: 2px; border: 1px solid black; border-top-width: 2px; width: 6px; height: 5px; overflow: hidden;"></div>
        </div>
        <div id="<?php echo $debugID?>_window_bt_max" style="right: 34px;" onclick="<?php echo $debugID?>.maximize();" class="<?php echo $debugID?>_window_bt" title="fit">
          <div style="position: absolute; left: 2px; top: 2px; border: 1px solid black; border-left: 0; border-right: 0; width: 8px; height: 1px; overflow: hidden; background-color: transparent;"></div>
          <div style="position: absolute; left: 2px; top: 6px; border: 0; width: 8px; height: 1px; overflow: hidden; background-color: black;"></div>
          <div style="position: absolute; left: 2px; top: 8px; border: 0; width: 5px; height: 1px; overflow: hidden; background-color: black;"></div>
        </div>
        <div id="<?php echo $debugID?>_window_bt_forward" style="right: 17px;" onmousedown="<?php echo $debugID?>.eventStart('forward');" onmouseup="<?php echo $debugID?>.bringToFront();" class="<?php echo $debugID?>_window_bt" title="drag this button up or down to change the window level | doubleclick for auto adjust">
          <div style="position: absolute; left: 4px; top: 1px; border: 1px solid black; border-top-width: 1px; width: 5px; height: 5px; overflow: hidden;"></div>
          <div style="position: absolute; left: 1px; top: 3px; border: 1px solid black; border-top-width: 2px; width: 6px; height: 5px; overflow: hidden; background-color: <?php echo $color['background']?>;"></div>
        </div>
        <div id="<?php echo $debugID?>_window_bt_close" style="right: 2px;" onclick="<?php echo $debugID?>.close();" class="<?php echo $debugID?>_window_bt" title="close">
        <div style="position: absolute; left: 3px; top: -2px; border: 0; width: 12px; height: 12px; overflow: hidden; font: bold 12px sans-serif;">x</div></div>
      </div>
      <!-- Body -->
      <div id="<?php echo $debugID?>_body" style="<?php echo $window['display']=="min"?"display: none; ":""?>">
        <div id="<?php echo $debugID?>_body_box">
          <!-- Menu -->
          <div id="<?php echo $debugID?>_body_menu" onselectstart="return false">
            <div id="<?php echo $debugID?>_body_menu_box">
              <?php $ak = 1;
              foreach($sortedSections as $sId=>$sTitle):
                ?><div id="<?php echo $nav['sections'][array_search($sId,$nav['links'])]?>" class="<?php echo $debugID?>_body_menu_entry"><?php echo $sTitle?></div><?php
              endforeach;?>
            </div>
          </div>
          <!-- Content -->
          <div>
            <div id="<?php echo $debugID?>_content" style="height: <?php echo $window['height']-$cfgMinHeight?>px;">

              <?php foreach($sections as $sId=>$sTitle){?>
              <div id="<?php echo $nav['sections'][array_search($sId,$nav['links'])]?>_c" class="<?php echo $debugID?>_window_section_gutter">
                <div id="<?php echo $nav['sections'][array_search($sId,$nav['links'])]?>_content" class="<?php echo $debugID?>_content">
                <?php switch($sId){

                  case "messages":

                    if(isset($reg['messages'])){?>
                      <div style="text-align: right; padding-right: 2px;">
                        Options: <a class="<?php echo $debugID?>_window_content_messages_links" href="javascript:<?php echo $debugID?>.sectionChange('<?php echo $debugID?>_messages',true,true);">[+] expand all</a>
                        <a class="<?php echo $debugID?>_window_content_messages_links" href="javascript:<?php echo $debugID?>.sectionChange('<?php echo $debugID?>_messages',true,false);">[-] collapse all</a>
                      </div>
                      <?php foreach($reg['messages'] as $msg){
                      ?>
                      <div>
                        <a name="<?php echo $msg['id']?>_a"></a>
                        <fieldset class="<?php echo $debugID?>_window_content_messages">
                          <legend id="<?php echo $msg['id']?>" class="<?php echo $debugID?>_window_content_messages_title" style="font-weight: <?php echo !isset($_COOKIE[$msg['id']]) || $_COOKIE[$msg['id']]?'bold':'normal'?>;">
                            <?php echo $msg['title']?>
                          </legend>
                          <div id="<?php echo $msg['id']?>_c" style="display: <?php echo !isset($_COOKIE[$msg['id']]) || $_COOKIE[$msg['id']]?'inline':'none'?>;">
                            <a href="javascript:void(0);" onclick="<?php echo $debugID?>.getSource('<?php echo urlencode($msg['file'])?>',<?php echo $msg['line']?>);"><?php echo $msg['file']?></a>
                            <div class="<?php echo $debugID?>_window_content_messages_position">
                              <div class="<?php echo $debugID?>_window_content_messages_sigma" onclick="<?php echo $debugID?>.showTrace('<?php echo $msg['id']?>');" title="trace" onselectstart="return false">&Sigma;</div>
                              <div class="<?php echo $debugID?>_window_content_messages_position_text" style="background-color: <?php echo $color[$msg['titleColor']]?>;">
                                <span class="<?php echo $debugID?>_window_content_messages_position_line"><?php echo $msg['line']?> </span><?php echo highlight_string(trim($msg['source']),true)?>
                              </div>
                              <?php if(is_array($msg['trace']) && count($msg['trace'])):?>
                              <table id="<?php echo $msg['id']?>_trace" class="<?php echo $debugID?>_window_content_messages_trace" style="display: none;">
                                <tr>
                                  <th class="<?php echo $debugID?>_window_content_errors_list_text_line" colspan="3">Trace levels (<?php echo count($msg['trace'])?>)</th>
                                </tr>
                                <tr>
                                  <th class="<?php echo $debugID?>_window_content_errors_list_text_line" style="width: 45%;">File</th>
                                  <th class="<?php echo $debugID?>_window_content_errors_list_text_line" style="width: 10%;">Line</th>
                                  <th class="<?php echo $debugID?>_window_content_errors_list_text_line" style="width: 45%;">Trace</th>
                                </tr>
                                <?php foreach($msg['trace'] as $t):?>
                                <tr<?php echo $t['file']?" onclick=\"{$debugID}.getSource('".urlencode($t['file'])."',{$t['line']});\" style=\"cursor: pointer;\"":""?>>
                                  <td style="vertical-align: top;" title="<?php echo $t['file']?>"><?php echo basename($t['file'])?></td>
                                  <td class="<?php echo $debugID?>_window_content_messages_position_line" style="vertical-align: top;"><?php echo $t['line']?></td>
                                  <td><?php echo htmlspecialchars($t['title'])?></td>
                                </tr>
                                <?php endforeach;?>
                                <?php $dirs = array();
                                $ifs = 0;
                                foreach($msg['included'] as $f){
                                  $dirs[dirname($f)][] = $f;
                                  $ifs += filesize($f);
                                }
                                ksort($dirs);
                                ?>
                                <tr>
                                  <th class="<?php echo $debugID?>_window_content_errors_list_text_line" colspan="3"><span style="float: right;">total <?php echo number_format($ifs/1024,2,",",".")." kB"?></span>Included files (<?php echo count($msg['included'])?>)</th>
                                </tr>
                                <?php foreach($dirs as $d=>$files):?>
                                <tr onclick="<?php echo $debugID?>.makeHttpRequest('?<?php echo $reg['cfg']['globalIdentifier']?>=browse&file=<?php echo urlencode($d)?>','<?php echo $debugID?>.browse');">
                                  <th style="background-color: #eee;" colspan="3"><?php echo $d?></th>
                                </tr>
                                  <?php foreach($files as $f):?>
                                  <tr onclick="<?php echo $debugID?>.getSource('<?php echo urlencode($f)?>');" style="cursor: pointer;">
                                    <td colspan="2" style="vertical-align: top;"><?php echo basename($f)?></td>
                                    <td style="vertical-align: top; text-align: right;"><?php echo number_format(filesize($f)/1024,2,'.','')?>kB</td>
                                  </tr>
                                  <?php endforeach;?>
                                <?php endforeach;?>
                              </table>
                              <?php endif;?>

                            </div>
                            <?php if($debugClipboard){?>
                            <div class="<?php echo $debugID?>_window_content_messages_options">
                              <button type="button" class="<?php echo $debugID?>_window_content_messages_options_button" onclick="<?php echo $debugID?>.copy('<?php echo addslashes($msg['file'])?>');" title="copy address to clipboard">&uarr; copy address</button><button type="button" class="<?php echo $debugID?>_window_content_messages_options_button" onclick="<?php echo $debugID?>.copy((this.parentNode.nextSibling.getElementsByTagName('pre')[0]?this.parentNode.nextSibling.getElementsByTagName('pre')[0].innerText:null));" title="copy text to clipboard">&darr; copy text</button>
                            </div>
                            <?php }?>
                            <div class="<?php echo $debugID?>_window_content_messages_content">
                              <div class="<?php echo $debugID?>_window_content_messages_content_type"><?php echo $msg['type']?><?php echo ($msg['type'] == "integer" && (date("U",$msg['var']) == $msg['var']))?" &raquo; ".date("r",$msg['var']):""?></div>
                              <?php if(isset($msg['methods']) && count($msg['methods'])){?>
                              <div class="<?php echo $debugID?>_window_content_messages_content_headline">public methods</div>
                              <ul class="<?php echo $debugID?>_window_content_messages_content_methods">
                                <?php foreach($msg['methods'] as $m){?>
                                <li><?php echo $m?>()</li>
                                <?php }?>
                              </ul>
                              <?php }?>
                              <?php if(isset($msg['vars']) && count($msg['vars'])){?>
                              <div class="<?php echo $debugID?>_window_content_messages_content_headline">public attributes</div>
                              <ul class="<?php echo $debugID?>_window_content_messages_content_methods">
                                <?php foreach($msg['vars'] as $k=>$m){?>
                                <li><?php echo $k?> <span class="type">(<?php echo gettype($m)?>)</span></li>
                                <?php }?>
                              </ul>
                              <?php }?>
                              <div class="<?php echo $debugID?>_window_content_messages_content_view">
                                <?php if(is_array($msg['var'])):?>
                                  <ol>
                                  <?php foreach($msg['var'] as $gi):?>
                                    <li><pre class="<?php echo $debugID?>_window_content_messages_content_view_text"><?php echo htmlspecialchars(wordwrap($gi,$reg['cfg']['maxLineLength'],"\n",true))?></pre></li>
                                  <?php endforeach;?>
                                  </ol>
                                <?php else:?>
                                  <pre class="<?php echo $debugID?>_window_content_messages_content_view_text"><?php echo htmlspecialchars(wordwrap($msg['var'],$reg['cfg']['maxLineLength'],"\n",true))?></pre>
                                <?php endif;?>
                              </div>
                              <?php if(is_array($msg['var']) && count($msg['var']) == $reg['cfg']['maxMessageGroupCount']):?>
                              <div class="<?php echo $debugID?>_window_content_messages_content_warning">
                                Maximum group count reached with <b><?php echo number_format($reg['cfg']['maxMessageGroupCount'],0,",",".")?> items.</b><br>
                                Set <?php echo $debugFunction?>(<?php echo $reg['cfg']['maxMessageGroupCount']?>,'config:maxMessageGroupCount'); to a higher value.
                              </div>
                              <?php elseif($msg['length'] > $reg['cfg']['maxMessageLength']):?>
                              <div class="<?php echo $debugID?>_window_content_messages_content_warning">
                                Output text garbled? <b><?php echo number_format($reg['cfg']['maxMessageLength'],0,",",".")?> bytes</b> of <b><?php echo number_format($msg['length'],0,",",".")?> bytes</b> are displayed.<br>
                                Set <?php echo $debugFunction?>(<?php echo $reg['cfg']['maxMessageLength']?>,'config:maxMessageLength'); to a higher value.
                              </div>
                              <?php endif;?>
                            </div>
                          </div>
                        </fieldset>
                      </div>
                      <?php }
                    }
                    break;


                  case "errors":
                    if(isset($reg['errors'])){?>
                      <div class="<?php echo $debugID?>_window_content_errors">
                        <div class="<?php echo $debugID?>_window_content_errors_navigation">
                          <?php foreach($errorNav as $k=>$v){
                            if(!isset($errorTypes[$k])) continue;?>
                            <div id="<?php echo $nav['sections'][array_search($errorTypes[$k],$nav['links'])]?>" class="<?php echo $debugID?>_window_content_errors_navigation_button"><?php echo $errorTypes[$k]?> [<?php echo $v?>]</div>
                          <?php }?>
                        </div>

                        <div class="<?php echo $debugID?>_window_content_errors_list">
                          <?php foreach(array_keys($reg['errors']) as $type):
                            if(!isset($errorTypes[$type])) continue;?>
                            <a name="<?php echo $nav['sections'][array_search($errorTypes[$type],$nav['links'])]?>_a"></a>
                            <div id="<?php echo $nav['sections'][array_search($errorTypes[$type],$nav['links'])]?>_c" style="display: none;">
                            <?php foreach($reg['errors'][$type] as $error):?>
                              <div class="<?php echo $debugID?>_window_content_errors_list_text">
                                <div class="<?php echo $debugID?>_window_content_errors_list_text_header"><?php echo $errorTypes[$error['type']]?><?php if($error['occurrences']>1): print " [{$error['occurrences']}]"; endif; ?>: <?php echo wordwrap(strip_tags($error['msg']),$reg['cfg']['maxLineLength'],"<br>\n",true)?></div>
                                <?php if($error['length'] > $reg['cfg']['maxMessageLength']):?>
                                <div class="<?php echo $debugID?>_window_content_messages_content_warning">
                                  Output text garbled? <b><?php echo number_format($reg['cfg']['maxMessageLength'],0,",",".")?> bytes</b> of <b><?php echo number_format($error['length'],0,",",".")?> bytes</b> are displayed.<br>
                                  Set <?php echo $debugFunction?>(<?php echo $reg['cfg']['maxMessageLength']?>,'config:maxMessageLength'); to a higher value.
                                </div>
                                <?php endif;?>

                                <div class="<?php echo $debugID?>_window_content_errors_list_text_content"><a href="javascript:void(0);" onclick="<?php echo $debugID?>.getSource('<?php echo urlencode($error['file'])?>',<?php echo $error['line']?>);"><?php echo $error['line']?>: <?php echo $error['file']?></a></div>
                                <?php if($error['trace'] != null):?>
                                  <table>
                                    <tr>
                                      <th class="<?php echo $debugID?>_window_content_errors_list_text_line">File</th>
                                      <th class="<?php echo $debugID?>_window_content_errors_list_text_line">Line</th>
                                      <th class="<?php echo $debugID?>_window_content_errors_list_text_line">Trace</th>
                                    </tr>
                                    <?php foreach($error['trace'] as $t):?>
                                    <tr<?php echo $t['file']?" onclick=\"{$debugID}.getSource('".urlencode($t['file'])."',{$t['line']});\" style=\"cursor: pointer;\"":""?>>
                                      <td style="vertical-align: top;" title="<?php echo $t['file']?>"><?php echo basename($t['file'])?></td>
                                      <td class="<?php echo $debugID?>_window_content_messages_position_line" style="vertical-align: top;"><?php echo $t['line']?></td>
                                      <td><?php echo htmlspecialchars($t['title'])?></td>
                                    </tr>
                                    <?php endforeach;?>
                                  </table>
                                <?php endif;?>
                              </div>
                            <?php endforeach;?>
                            </div>
                          <?php endforeach;?>
                        </div>

                      </div>
                    <?php }
                    break;


                  case "system":?>
                    <div id="<?php echo $debugID?>_system_div">
                      <table id="<?php echo $debugID?>_system_table">
                      <?php foreach($system as $k=>$v){
                        $f = !empty($v);
                        $bg = 0;?>
                        <tr class="<?php echo $debugID?>_system_headline">
                          <td class="<?php echo $debugID?>_system_headline_content">
                            <a name="<?php echo $nav['sections'][array_search($k,$nav['links'])]?>_a"></a>
                            <div id="<?php echo $f?$nav['sections'][array_search($k,$nav['links'])]:""?>" class="<?php echo $debugID?>_system_headline_content_text" style="color: <?php echo $f?'black':'gray'?>;"><div><?php echo count($v)?></div>$<?php echo $k?></div>
                          </td>
                        </tr>
                        <tr>
                          <td class="<?php echo $debugID?>_system_content">
                            <table id="<?php echo $nav['sections'][array_search($k,$nav['links'])]?>_c" class="<?php echo $debugID?>_system_content_table">
                            <?php if($k == "_INCLUDED"){ $ifs = 0; }?>
                            <?php foreach($v as $k1=>$v1){?>
                              <tr style="background-color: <?php echo (($bg++)%2?'#e6e6e6':'#eee')?>;">
                                <?php if($k == "_COOKIE"){?><td onclick="<?php echo $debugID?>.setCookie('<?php echo $k1?>',null,new Date()); this.parentNode.innerHTML=null;" onmouseover="this.nextSibling.nextSibling.style.color = 'red'" onmouseout="this.nextSibling.nextSibling.style.color = ''" style="vertical-align: top;" title="delete cookie">[&times;]</td><?php }?>
                                <td class="<?php echo $debugID?>_system_content_table_left"><?php echo $k1?></td>
                                <td <?php if($k == "_INCLUDED"):?>onclick="<?php echo $debugID?>.getSource('<?php echo urlencode($v1)?>');" <?php endif;?>class="<?php echo $debugID?>_system_content_table_right">
                                  <?php
                                    $v1x = is_scalar($v1)?$v1:print_r($v1,true);
                                    $v1l = strlen($v1x);
                                    if($v1l > $reg['cfg']['maxMessageLength'])
                                      $v1x = substr($v1x,0,$reg['cfg']['maxMessageLength']);
                                  ?>
                                  <pre style="display: inline;"><?php echo htmlspecialchars(wordwrap(trim(preg_replace("~(;|,)\s?~","$1\n",$v1x)),80,"\n"))?></pre>
                                  <?php if($v1l >= $reg['cfg']['maxMessageLength']):?>
                                  <div class="<?php echo $debugID?>_window_content_system_content_warning">
                                    Output text garbled? <b><?php echo number_format($reg['cfg']['maxMessageLength'],0,",",".")?> bytes</b> of <b><?php echo number_format($v1l,0,",",".")?> bytes</b> are displayed.<br>
                                    Set <?php echo $debugFunction?>(<?php echo $reg['cfg']['maxMessageLength']?>,'config:maxMessageLength'); to a higher value.
                                  </div>
                                  <?php endif;?>

                                </td>
                                <?php if($k == "_INCLUDED"):
                                  $ifs += filesize($v1);?>
                                <td style="text-align: right;" class="<?php echo $debugID?>_system_content_table_right"><?php echo filesize($v1)?number_format(filesize($v1)/1024,2,",",".")."&nbsp;kB":"N/A"?></td>
                                <?php endif;?>
                              </tr>
                            <?php }?>
                            <?php if($k == "_INCLUDED"):?>
                            <tr style="background-color: #ccc; color: #222; font-weight: bold;">
                              <td class="<?php echo $debugID?>_system_content_table_left"><?php echo count($v)?></td>
                              <td class="<?php echo $debugID?>_system_content_table_right">
                                <pre style="display: inline;">files included</pre>
                              </td>
                              <td style="text-align: right;" class="<?php echo $debugID?>_system_content_table_right"><?php echo number_format($ifs/1024,2,",",".")."&nbsp;kB"?></td>
                            </tr>
                            <?php endif;?>
                            </table>
                          </td>
                        </tr>
                      <?php }?>
                      </table>
                    </div>
                    <?php break;


                  case "search":?>
                    <div class="<?php echo $debugID?>_window_content_system" style="background-color: white;">
                      <?php foreach($reg['search'] as $s){?>
                        <a name="<?php echo $s['id']?>_a"></a>
                        <div id="<?php echo $s['id']?>" style="background-color: #D5DDF3; border-top: 1px solid #3366CC; padding: 2px;"><span style="float: right;">Results <?php echo count($s['results'])?></span><?php echo $s['type']?>: <b><?php echo htmlspecialchars($s['highlight'])?></b></div>
                        <div id="<?php echo $s['id']?>_c" style="display: <?php echo !isset($_COOKIE[$s['id']]) || $_COOKIE[$s['id']]?'inline':'none'?>;">
                          <?php foreach($s['results'] as $r){?>
                          <div style="margin: 2px 0; padding: 0 2px; background-color: #fafafa;"><?php echo str_replace($s['highlight'],"<span style=\"background-color: yellow;\">{$s['highlight']}</span>",$r)?></div>
                          <?php }?>
                        </div>
                      <?php }?>
                    </div>
                    <?php break;


                  case "browse":
                    break;

                  case "source":
                    break;



                  /**
                  * JavaScript
                  *
                  * You can display trace your JavaScript contents in this debug section,
                  * just use the debug function in JS code:
                  * <script type="text/javascript">__debug('Hello world!');</script>
                  *
                  * Maybe it will be useful for you, but there are also better tools to
                  * debug JavaScript...
                  *
                  * @see  Firebug  (http://getfirebug.com/)
                  */
                  case "javascript":?>
                    <script type="text/javascript">
                      function <?php echo $debugFunction?>_print_r(a,p,d){
                        var s = "";
                        if(typeof p != "boolean") p = false;
                        if(typeof d != "number") d = 5;
                        if(a === null) return "(NULL): NULL";
                        switch(typeof a){
                          case "array":
                          case "object":
                            s += "("+typeof a+") {\n";
                            if(d <= 1) s += "\ttoo much recursion\n";
                              else for(var i in a)
                                s += "\t"+i+" "+<?php echo $debugFunction?>_print_r(a[i],true,d-1).split("\n").join("\n\t")+"\n";
                            s += "}";
                            break;
                          case "boolean":
                            s += "("+(typeof a)+"): "+(a?"true":"false")+";";
                            break;
                          case "string":
                            s += "("+(typeof a)+":"+a.length+"): \""+a+"\";";
                            break;
                          case "number":
                            s += "("+(typeof a)+"): "+a+";";
                            break;
                          default:
                            s += "("+(typeof a)+");";
                        }
                        if(p) return s;
                        alert(s);
                        return true;
                      }
                      function <?php echo $debugFunction?>(v,title){
                        var msg = "<div style=\"background-color: white; font-family: monospace; border: 1px solid #ccc;\">";
                        if(title) msg += "<div style=\"background-color: #ccc; color: #222; font-weight: bold;\">"+title+"</div>";
                        msg += <?php echo $debugFunction?>_print_r(v,true)+"</div>";

                        if(<?php echo $debugID?>.ref.section.id != "<?php echo $debugID?>_javascript_c")
                          <?php echo $debugID?>.sectionChange("<?php echo $debugID?>_javascript",true);

                        <?php echo $debugID?>.setContent("<?php echo $debugID?>_javascript_debug",msg+<?php echo $debugID?>.getContent("<?php echo $debugID?>_javascript_debug"));

                        return v;
                      }
                    </script>
                    <small>You are able to use the <?php echo $debugFunction?>() function in JavaScript now!</small>
                    <div id="<?php echo $debugID?>_javascript_debug" style="white-space: pre; border: 0; margin: 0; padding: 0; text-align: left; font-weight: normal; font-size: 11px; font-family: Verdana, sans-serif;"></div>
                    <?php break;


                  case "info":
                    break;


                  case "doc":
                    break;


                  case "performance":
                    if($memoryPeak){
                      $memoryLimit = $debugInfo['memory_limit'];
                      $memoryLimit = trim($memoryLimit);
                      $reg['stats']['memoryUsage'] += ob_get_length();
                      $last = strtolower($memoryLimit{strlen($memoryLimit)-1});
                      switch($last) {
                        case 'g': $memoryLimit *= 1024;
                        case 'm': $memoryLimit *= 1024;
                        case 'k': $memoryLimit *= 1024;
                      }

                      $maxExecutionTime = $debugInfo['time_limit'];
                      $steps = 100;
                      $cols = array_fill(0,$steps,0);
                      $mod = ($maxExecutionTime/$steps*1000);
                      foreach($reg['events'] as $e){
                        $c = round($e['age']*1000);
                        if((($c-($c%$mod))/$mod)>100) continue;
                        if($cols[($c-($c%$mod))/$mod] == 0)
                          $cols[($c-($c%$mod))/$mod] = 1;
                        if($cols[($c-($c%$mod))/$mod]<$e['memory'])
                          $cols[($c-($c%$mod))/$mod] = $e['memory'];
                        if($memoryPeak < $e['memory']) $memoryPeak = $e['memory'];
                      }
                      while(!end($cols) && count($cols)) array_pop($cols);
                      if(!count($cols)) array_push($cols,1);
                      for($i=0; $i<count($cols);$i++){
                        $cols[$i] = ceil($cols[$i]*100/$memoryPeak);
                        if(!$cols[$i] && isset($cols[$i-1]))
                          $cols[$i] = $cols[$i-1];
                      }

                      $memoryAvailable = $memoryLimit-$memoryPeak;
                      $availablePercent = (float) number_format($memoryAvailable*100/$memoryLimit,2,".","");
                      $usedPercent = 100-$availablePercent;
                      $executionTime = (float)number_format($event['age'],2,".","");
                      $diagramHeight = $reg['cfg']['diagramHeight'];

                      $diskFreeSpace = disk_free_space($reg['cfg']['outputBaseDir']);
                      $diskTotalSpace = disk_total_space($reg['cfg']['outputBaseDir']);

                      ?>
                      <div style="background: white; padding: 4px; text-align: center;">
                        <div id="<?php echo $debugID?>_diagram_efficiency">
                          <div style="position: relative; width: 98%; margin: 0 auto; border: 1px solid black; border-top: 0; border-right: 0;">
                            <table style="position: relative; table-layout: fixed; border-collapse: collapse; width: 100%; height: <?php echo $diagramHeight?>px; background: white;">
                              <tr>
                                <td style="background: #eee; height: <?php echo $availablePercent?>%; overflow: hidden;" title="<?php echo $availablePercent?>% available">
                                </td>
                              </tr>
                              <tr>
                                <td style="position: relative; background: #ccf; height: <?php echo $usedPercent?>%; overflow: hidden;" title="<?php echo $usedPercent?>% peak">
                                  <div style="position: relative; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden;">
                                    <?php foreach($cols as $i=>$height){?>
                                    <div style="position: absolute; left: <?php echo $i*(100/$steps)?>%; bottom: 0px; width: <?php echo (100/$steps)?>.1%; height: <?php echo $height?>%; background: #668; overflow: hidden;" title="<?php echo number_format(($i+1)*($maxExecutionTime/$steps),2,".","")?>sec (<?php echo number_format($height*$memoryPeak/$memoryLimit,2,".","")?>%) <?php echo number_format($memoryPeak/100*$height/1024,0,",",".")?>kB"></div>
                                    <?php }?>
                                  </div>
                                </td>
                              </tr>
                            </table>
                            <div style="position: absolute; top: 2px; left: 2px;">
                              <div style="font-weight: bold;">Memory and time efficiency</div>
                              100% max (<?php echo number_format($memoryLimit/1024,0,",",".")?>kB)<br />
                              <?php echo $availablePercent?>% available (<?php echo number_format($memoryAvailable/1024,0,",",".")?>kB)<br />
                              HDD <?php echo number_format($diskTotalSpace/1024/1024,0,",",".")?>MB free <?php echo number_format($diskFreeSpace*100/$diskTotalSpace,1,",",".")?>%<br />
                              <br />
                              Used <?php echo $executionTime?>sec (<?php echo number_format($executionTime*100/$maxExecutionTime,2,".","")?>%) of <?php echo $maxExecutionTime?>sec max. execution time.
                            </div>
                            <div style="position: absolute; <?php echo $usedPercent<50?"bottom: ".(($diagramHeight/100*$usedPercent)+2):"top: ".(($diagramHeight/100*$availablePercent)+2)?>px; right: 2px; text-align: right;">peak <?php echo number_format($memoryPeak/1024,0,",",".")?>kB (<?php echo $usedPercent?>%)<br />
                            incl. <?php echo $debugFunction?>() <?php echo number_format($reg['stats']['memoryUsage']/1024,0,",",".")?>kB (<?php echo number_format(($reg['stats']['memoryUsage']*100)/$memoryLimit,2,".","")?>%)</div>
                            <div style="position: absolute; top: <?php echo ($diagramHeight/100*$availablePercent)?>px; left: 0px; background: #99c; height: 1px; width: 100%;"></div>
                          </div>
                        </div>
                        <br />
                        <?php
                        $memoryLimit = $memoryPeak;
                        $maxExecutionTime = ceil($event['age']*10)/10;
                        $cols = array_fill(0,$steps,0);
                        $eventlinks = array();
                        $mod = ceil($maxExecutionTime/$steps*1000);
                        foreach($reg['events'] as $e){
                          if($e['age'] > $event['age']) continue;
                          $c = round($e['age']*1000);
                          $k = round((float) number_format(($c-($c%$mod))/$mod,2,".",""));
                          if($k >= count($cols)) continue;
                          if($cols[$k] == 0)
                            $cols[$k] = 0;
                          if($cols[$k]<$e['memory'])
                            $cols[$k] = $e['memory'];
                          if(!isset($eventlinks[$k]))
                            $eventlinks[$k] = array('desc'=>addslashes(htmlspecialchars(strip_tags(nl2br($e['desc'])))),'hash'=>"{$debugID}_event_{$e['age']}_a");
                        }
                        while(!end($cols) && count($cols)) array_pop($cols);
                        if(!count($cols)) array_push($cols,1);
                        for($i=0; $i<count($cols);$i++)
                          $cols[$i] = floor($cols[$i]*100/$memoryPeak);

                        $max = $steps;
                        while(($i = array_search(0,$cols)) !== false && $max--){
                          $next = $i+1;
                          while($cols[$next] == 0 && $next < count($cols)) $next++;
                          $inc = ($cols[$next] - $cols[$i-1])/($next-$i+1);
                          for($f=0;($i+$f)<$next;$f++){
                            $cols[($i+$f)] = round($cols[$i-1]+($inc+(($f)*$inc)));
                          }
                        }
                        ?>
                        <div id="<?php echo $debugID?>_diagram_detail">
                          <div style="position: relative; width: 98%; margin: 0 auto; border: 1px solid black; border-top: 0; border-right: 0;">
                            <table style="position: relative; table-layout: fixed; border-collapse: collapse; width: 100%; height: <?php echo $diagramHeight?>px; background: white;">
                              <tr>
                                <td style="position: relative; background: #ccf; overflow: hidden; height: 100%;">
                                  <div style="position: relative; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden;">
                                    <?php foreach($cols as $i=>$height){?>
                                    <div  style="position: absolute; left: <?php echo $i*(100/$steps)?>%; bottom: 0px; background: #<?php echo isset($eventlinks[$i])?"668":"aac"?>; width: <?php echo (100/$steps)?>.1%; height: <?php echo $height == 0?$cols[$i]=$cols[$i-1]:$height?>%; overflow: hidden;"<?php
                                        $coltitle = addslashes(strtr(htmlspecialchars(strip_tags(number_format(($i+1)*($maxExecutionTime/$steps),2,".","")."sec (".number_format($cols[$i]*$memoryPeak/$memoryLimit,2,".","")."%) ".number_format($memoryPeak/100*$cols[$i]/1024,0,",",".")."kB")),"'\n",'" '));
                                        if(isset($eventlinks[$i])):
                                        ?> onclick="<?php echo $debugID?>.sectionChange('<?php echo $debugID?>_history',true); location.hash='<?php echo $eventlinks[$i]['hash']?>'; location.hash='<?php echo $debugID?>_window_a';"<?php
                                        ?> onmouseover="this.style.backgroundColor = '#eef'; this.style.cursor = 'pointer'; <?php echo $debugID?>.status('<?php echo $coltitle.": ".$eventlinks[$i]['desc']?>');"<?php
                                        ?> onmouseout="this.style.backgroundColor = '#668';"<?php
                                        else:
                                        $coltitle .= " (estimated)";
                                        ?> onmouseover="<?php echo $debugID?>.status('<?php echo $coltitle?>');"<?php
                                        endif;?>></div><?php }
                                    ?>
                                  </div>
                                </td>
                              </tr>
                            </table>
                            <div style="position: absolute; top: 2px; left: 2px;">
                              <div style="font-weight: bold;">Details of <?php echo implode("",array_slice(explode('?',$_SERVER['REQUEST_URI']),0,1))?> (<?php echo $executionTime?> sec. / <?php echo number_format($memoryLimit/1024,0,",",".")?>kB)</div>
                            </div>
                          </div>
                        </div>
                      </div>

                    <?php }else{?>
                      Your PHP version <?php echo PHP_VERSION?> is not compiled with --enable-memory-limit, but it is required for this function to work properly.
                    <?php }
                    break;


                  case "history":
                    #  print summary table
                    $maxExecutionTime = $event['age'];
                    unset($event);
                    $event = array( 'type'    => 'output',
                                    'memory'  =>  function_exists('memory_get_usage')?memory_get_usage():0,
                                    'desc'    => "",
                                    'data'    => array()
                                  );
                    list($usec, $sec) = explode(" ", microtime());
                    $event['time'] = number_format((float)$usec+(float)$sec,$reg['cfg']['timerPrecision'],".","");
                    $event['age']  = isset($reg['events'][0])?number_format((float)$event['time']-(float)$reg['events'][0]['time'],$reg['cfg']['timerPrecision'],".",""):0;
                    $event['desc'] = "Generated {$debugFunction} output after {$maxExecutionTime} in ".number_format((float)$event['time']-(float)$reg['events'][count($reg['events'])-1]['time'],$reg['cfg']['timerPrecision'],".","")." seconds.";

                    #  register event
                    $reg['events'][] =& $event;
                    ?>
                    <table class="<?php echo $debugID?>_history_table">
                      <tr>
                        <th>sec</th>
                        <th>event</th>
                        <?php if($memoryPeak):?><th>memory</th><?php endif;?>
                      </tr>
                      <?php
                      $pa = 0;
                      $pm = 0;
                      foreach($reg['events'] as $e){
                        if($e['type'] == 'tick') continue;?>
                      <?php if($pa > 0 && $e['age'] < $maxExecutionTime && (number_format(($e['age']-$pa)*100/$maxExecutionTime,0,".","")>=5 || ($memoryPeak && number_format(($e['memory']-$pm)*100/$memoryPeak,0,".","")>5))):?>
                      <tr style="color: #999;">
                        <td>+<?php echo number_format(($e['age']-$pa),$reg['cfg']['timerPrecision'],".","")?></td>
                        <td style="text-align: left;">time gap of <?php echo number_format(($e['age']-$pa)*100/$maxExecutionTime,1,".","")?>%<?php if($memoryPeak && $e['memory']!=$pm):?>, memory raise x<?php echo number_format(($e['memory']*100/$pm)/100,1,".","")?> (<?php echo number_format(($e['memory']-$pm)*100/$memoryPeak,0,".","")?>% of peak)<?php endif;?></td>
                        <?php if($memoryPeak):?><td><?php echo $e['memory']?($e['memory']-$pm>0?"+".number_format(($e['memory']-$pm)/1024,0,",",".")."kB":"&hellip;"):'n/a'?></td><?php endif;?>
                      </tr>
                      <?php endif;?>
                      <tr class="<?php echo $debugID?>_window_content_history_type_<?php echo $e['type']?>" title="<?php echo $e['type']?>">
                        <td title="&hellip;<?php echo number_format(($e['age']-$pa),$reg['cfg']['timerPrecision'],".","")?>s&hellip;"><a name="<?php echo $debugID?>_event_<?php echo $e['age']?>_a" style="position: relative; top: -2em;"></a><?php echo number_format($e['age'],$reg['cfg']['timerPrecision'],".","")?></td>
                        <td style="text-align: left;"><?php if(isset($e['data']['id'])){?><a href="javascript:<?php echo $debugID?>.sectionChange('<?php echo $e['data']['id']?>',true)"><?php }?><?php echo htmlspecialchars(substr(strip_tags($e['desc']),0,$reg['cfg']['maxLineLength']))?><?php if(isset($e['data']['id'])){?></a><?php }?></td>
                        <?php if($memoryPeak):?><td<?php if($e['memory']!=$pm):?> title="+<?php echo $e['memory']?number_format(($e['memory']-$pm)/1024,0,",",".")."kB":'n/a'?>"<?php else:?> style="color: #999;"<?php endif;?>><?php echo $e['memory']?number_format($e['memory']/1024,0,",",".")."kB":'n/a'?></td><?php endif;?>
                      </tr>
                      <?php $pa = $e['age'];
                        $pm = $e['memory'];
                      }?>
                    </table>
                    <?php break;
                }?>
                </div>
              </div>
              <?php }?>

            </div>
          </div>
          <!-- Footer -->
          <div onselectstart="return false" style="height: 18px; white-space: nowrap; overflow: hidden; -moz-user-select:none;">
            <div style="white-space: nowrap; height: 14px; padding: 1px 2px; border: 1px solid <?php echo $color['shadow']?>; border-left-color: <?php echo $color['light']?>; border-top-color: <?php echo $color['light']?>;">
              <div id="<?php echo $debugID?>_status" style="width: 100%; overflow: hidden; padding: 1px 0; font-size: 9px; color: #777;">
                v<?php echo $debugInfo['version']?> &bull; Copyright &copy; <?php echo date("Y")?> <?php echo htmlspecialchars($debugInfo['author'])?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="<?php echo $debugID?>" style="display: none;"><div id="<?php echo $debugID?>_c"><script type="text/javascript"><?php echo $debugID?>.init();</script></div></div>
</div>
<!-- <?php echo $debugFunction?> END -->
<!-- DEBUG HTML END -->


<!-- DEBUG ICON BEGIN -->
<?php echo base64_decode("R0lGODlhQAAgAPf/AHl7P7HcsWprNevy6wsLCjc7bv7+/tzdtubn7j14PTk6HW5vSqS5pO/n5qfGp7mm
pUmRSUlKJZPKk8rLk6eouri5pCkpFd2ysEdHR8TFpjZrNvHy60lqScvmy+bmzMaopx06HWs3NUdKbubM
zLCz3XRIRtTVxCVKJeTk49rn2hUoFcrXyufo2sbI32eoZ+jb2tfLytvc6DkdHGpqaGOZY6SlZCUWE1ip
WEkmJcjK6JeZZJWXU+vr4yUoSxMUJOPr4/r69+vj46epxuPj66qsV/Ly8YWGRISFg0SFRJloZalZVZqc
TlNTKxkaMfX29YVGRFGcUcSMi1lZVs3Nz6OjibSZmGi2aLS1l9LT0YijiFKCUtva1WRome7t7StTK7W2
aPb69kRIhYuOxJi1mFRZpppYVqysroqLpFJVgfz5+aVkYlMsK4JTUvn8+b+/v6OJiLHKsbaHhVVZm93e
3Nbb1pBMScPDwrO1y7S2hZiatWFmpYqLVPz8+NzV1Zucnr7A2oW2hcrKsHI7OT5Be/n5+3tAPoWHtk5T
m8q0tJ2h1NXW3a6ukCosUc7Ow/X1+ExMOzY2Ns/Dw66wyna3dvr6+fj19ZGtkcHQwcPEzklNkZKTrztM
O3GJcYpyca+Tknh4ePDw9PD08Dw8S7e5dvT08bGxr/v4972zs008PF1eMLK9svn6+bd4dfv7+4iJcZSV
cH2ofbKzvb29sqeoffTw8JVxcKZ+fH+BT3Z6t26IboeHbpWIiIiViJSVjYiJlfr5+jBeMHCVcF4xMK6u
pXN1maWlrjEzYPf4/H+BpKWupW1wqq6mpYCDru/o6J6gWR4gO7NiX7y+5OS9vKpvbZxgXlxgmmNotO7u
5ufu50xPhuHiv4Rraurq8b7hvi0vWVtgsW9xiPj4+Onp6Y+RSZtRTnByOfn6/NvAv9fX2M7Q5vz9/NSg
nblwbm5yuW1wk4hwb8mvrqKkuvLz4GJMS+zt8vLh4ODz4L+gn7WBfqqOjYyOrKqsb+Dh8v///wAAAP//
/yH/C05FVFNDQVBFMi4wAwEAAAAh+QQFCgD/ACwAAAAAQAAgAAAI/wD9CRxIsKDBgwgTKlzYpMmzhxAh
NiEw0IaMixgzyqAoUAWIjyBDquDoz4KCkyhTWuDYxFiBly9FuIuHacqZHgJlCAvBk2eJWg8iwXiDQyAI
YBqSJk0QjMGlFVlOCFSQSoBVq+VeVWhkggoTgT0KDBo7iMudIaAIgRJSQCAOQYXiFkqCKAgtU7Q+hBB4
IoFfvzTg/Ai1KpQDDQIjlAPAGICOQDxIUSKVQQDYQWEyh+Hyp5/nXzE0SfGHo9CT00+SnPPcL80LT6P7
IpmNhMYl1m1SWBodAYCR30Z0mGDdisWi0YzCZFqeSY4Qc54NEEJgx9+aJ3Wy1ynzwVT0NA2qe/9BAqE8
BCgO2uDGVp2JkXHwx+3IwIf4tereMh3aL8dQOnWs9WOAONbVQc6BZcQxQisBDugPMOdBISEgHQDImoNM
jLPEhs7g4YGF0RFoDBntWPONGOkc088x/OQQzR+l+BOCEuxAA00UI6TRjyn1jCDNOTECc4MVVtwgQQfh
GNCGPR10002MAhDxxRdETOABEAbwIY8H2mgTozfVkEBCIikaMIQkhiijBxcY+CMMNRdcsM4IphgQBDxx
TKMGNW0KGUAAR7ZhwA9wAOKCCzS0yYQzB2hjJR8G8BAIHvvUoEObzxAzhDn0ONLPEHmgAZMxOdXSQBpd
VNJPEFWwIcirwhj/FQw2bYQCRj8/jKGFBgloAMxUr1wDxAZA9MPDFbeUo2wqAjURDyisOUGBCMZUawwj
OT1AC2uVPFCCMOAKs4ZRDIQSLQMcAKMuMF5MVUER0VawQCr0pvKVP01g8gtrioDTw7//PiOQDZHs61kf
7+Cg8MIdXWJwP3TkcsLEE4MgkAWNUMLaFq5E4LHHCgjkAzoGsBaLCBE908TAfZTs2SnzaCRDR3S4bIAq
HIT00cVbuNyPLFKkdJLIpSjSBYPFiNLQ0j4MXAo6R/ezDCoWyWCRDR2VQkfUyWzi0dcqOL1F1MM8ooBJ
FqQ9EAE+HIFCP2aI4sPcc5NEgA1uG1AK1Tb0/00AR3+r4HY/pXitwuEjVWTB4KU8YoMFKlhgA0kCSTFF
OG60qZDlmGueEOeZL2T5KqEvZPrpqKd+UEMpRzRRRTJrRJJHOotEkklCq8SSSzAVIBNNNuHkj049+QSU
UEQZhZRSvDb1VFRTVXWVAFlt1dW9YZFV1llprdUWaXDJRZddeOnF11+ACUaYYYj5o1hjjkEmGWWW+dMD
ZppxxhpoopFmGmqqYY1rYOMP2dDGNrjRDW98AxzhEMc4yFEOc5wDHQFNpzrX0c52uvOd8PhjPOY5T3rW
0573xGc+9fFMK+7jj/zs5xD9+U+DCLQGAyFIQQy6EIEgJKEJVWiG/sjQhv+W0KEPAXFEJTpRilbUohfF
aEY1ulGOdtSjHwVpSEU6UpKW1KQn+SNKU6rSlbK0pS59KUxjKtOZ0rSmNr0pTnOq053ytKc+3eBPgRpU
oQ6VqCAyylEegJSkKGUpTGmKU54ClaheQqrhmQpVqmKVq2AlK1rZCle64pWvgCUsYhkLWcoqB7Pw9axo
Tcta18rWtjzTrW+Fa1z+AEG5zpWudbXLHwp4V7zmVa975eth/QJYwAZWMNYgbGEM84cKHMaaiFGsYhfL
2MY69rEIhMwfI/PZyVK2Mn/YoGWsgZnMaGYznNWOZz4DWu6IZjSkKW1pTWiaN58WtalV7WpZ2xqDuvZC
NRAcTmxkMxva1CYQtg0ubnSr29rwhgK98c1vgCOA4N5WOMQdjiSPY5zjICc5yvkDdJ5DCEhFd7nSfW4K
pAvpQAICACH5BAUKAP8ALAEAEgA/AA4AAAj/AP8JHPivQDUKU2LMwYSBIEFBbP7BmDMnUkOHAjVo+bci
xZwpFzEK2PPPBIqPIQcOGoQMRat+/bqkHFiokK1mL2POFJggASxsOWVi/AcAwCxx6mAKdRgmjDJF/2A6
+TT0yZNpfaL2mzoUCRIXKbRyxWjEyCwPYqk6zKRMEj2traYMrTMNXhe4cjFCcAFngIGocYeOqxFow99+
gf/JEcNMz78/3P46FPavTBR8av6d6yJ56JhJLv5184sx1T9nePDU+HfA8FAxMRRF+0cIo5l/lKO8eHHh
RSXbAyWkSBEgBRjgpiewYHHg30uHt//lOGaglTqM1QaWGWEKcauhBLuBnumn7jrBGwNNa+NDvjN4mP3A
C8TxLwh8+QTv4xeof79AR/4FiFE4+5EyEIHg+TEFPebh58kUXZjXiXx+rBChQJw4dMJAVGCxwXf4MSJC
LKD4t8Y8p9Di3wkcqBLKfiqgsoAsReBHQBNN+CKOexgRYIMMu+wokAw2/EPkQASoAAIv4vhngQW98CCg
fwTkF91ARU6JZJVadvlblwEiOFRAACH5BAUKAP8ALAIAEQA+AA8AAAj/AP8JHEjw37OCCBMqLAhiIUIF
CwuIQCYQnRmFIUKUsCUQy0WFGhLAmvPP4kIB5ahU/FhwUBh9Ag2IW1joST4DOFEsTIDEUsyZCgHcWoRT
5kA52QSG4SLpZ0E2dQQ+ofbBKUEIEAQioeHA6sBxewQa0ZHBajUhQpRxiZcDgdd/VD9MS3JvRIO3XB24
+OdgxYC3zjJkqPEvwxZ5Bv4ZJSOEG70WORz++1CPFowRzRbecDBgQIcVCpkIDLSB1BZt8hQOMdDPgDqE
pQjWa+VaXWKH/fqpU9evYGwBA1kXRSinxb/cvQtyIXiu0vHkiBJCGZi74N6BS6gnR5gNEyjJAkucqiN1
26GWS07KL7zVKL3DAsTcfJccohMiWuCBBbMT6p8Th6m8EkgR/inUQw/gKAIeDji80wd4J5zACR0KNSRQ
BBG4QtJCTTzjyxDgySDDLjpJBgIIvKCwXUIWKKBALygktoBDKoInUBDbyWBDQir884N6kt3WYosD+dDE
h5L5s+MuisW2UI+8gOdPj70A9Y8FNljQo0CQHIEFgZJ1OUUR4YTpJZgOdfmlQwEBACH5BAUKAP8ALAEA
EQA/AA8AAAj/AP8JHEhQYJOCCBMqLKhiIUILDv8VKCBCHxY/DkOEKJHvH8aFGjQksHTRoQABABZ5LJgN
TbZ/gwahkfQPBcInbNj8K1SIDSJ1cxAi0aLlX4IEWuDURGhkz61/AADsCaTO5sBqQloYylaNmZAYCcvc
ixSnThlb914kpOFgBSAI/2A5SJFQRwYTePbsmJWBRUE9kugRUnSnRQxuhBLCa2CqDyIYL5rx6YcQzoA2
KZSmuJww0AZ1HhppQ7GhFUE5Avv1I3QsYjPVaUxF/Ke6DZjZqoEAUZcQVwzeqh2yUqs6OMFSAyf9A065
oJmBowYWT3hImZAhvBFWG0hu2ocg2f/BqlPowsGP8ArH7cvAAz3CiWj+UHIoQ5AgNpHSRAyp5VKbiAKU
c4sJfDjkjTEiYGJARGsIU0IkBoQTETAcXKKOhA6lskAjEc72z4KzbeOhQLkQ5IVDumwR0TM9jIgDDiP+
c0KMEaTmkBQzxALKbPPMcAots3EwgypFNLcQjrIUuRABihigGogKEYCOk0YuhM50ySy5xXTDLFRKEeFg
6NCXYc5GpphegokmQgEBACH5BAUKAP8ALAEAEgA/AA4AAAj/AP8JHPhvkAiBBQoYm0GQYIkSAkOEEMaw
ocAECQRq0ACsokUAAAQKEJDK40BmmCRVK2jQjcV/b87BK/OvUKESdl7CWgFHCxKMCXJanGUiw55/IAG4
JBgvxrEh8f7lESIExcsX4VDc++fp3gerBKEw+AHmh4N/Yxw4ADtwxz8elHhk+FchQwa21YRwM9CPEAKB
oOhZ/NCMb5oG/+o162LxbL9+bbD9+BcqlMUMXfi2usZDHqkiAw/paUHo3+N+L/+RU3MujenHqaG46Nbm
dT8DL5fU0MbH9stv1hLFwJ1aoBJo6+oRL37DigR7ywmWGkjkywR50S0eOoQrXfZ/XAbWuVFCjtUIvsX/
QYEyqUN6JjuWLBnlIb3AQXm4pccRomaVZvb9g9EYA3xHUDlIXbGBgf888483aAT4jzAEVZKeF/9o8Q+D
EhLkAxoznBEDaumxMcMbcwSoxQxZpCjQCakt8AkVLHD4jyIIdEFJgH280IVr9tExQBet2PjPHNcQKZAU
L/kwx2n22UAHlOmpMAdfJBZnwZWwCaSABV8ShE6W6fmDxUD9mNFhgOhYZIMFKliwZkNqzmmnfQEBACH5
BAUKAP8ALAEAEgA/AA4AAAj/AP8JHPhPhDtNyAT28EewYa0qtgTiYNhwYDBLsASeoFix3KtFVAQy4Sgw
DLI/Q7hhCvPPGMmBtiIF6RLpyb8QLwde+oHtEpJ/GnLeEsijiwmBAkhmy/PPUT8DQ4TEOyOlYZ0q/yr1
69fg3r98GBpCGJPi31ZsDP5lqUpw3BUWQJ5ey/CPCtt/mQ7l4Wa2n7l/3Ia4IVinzr0GfdN06RJk8EAI
EBzY69vm34AfjgWOG5dhQ18+GzbwyHzoEK4cFQeuEUiO3LQRqRtCgTKpw8B+sZcs2act9sBv38TEMFDR
zM2BSqK88D3wxg0JHYg3ND6QyJcJLCoeaNiuxbHUoliTyBMozZT01BBuCOxWmaD68KnGOfv3RRsf8P/Q
cKkWD8H5ijKwkQQbXjH3jxY0aMEANrgJlEBFt+iwRwXXNJjaGXfEEoM6BuaDCDzLhcNcFpfAkYI6jjjg
GxWByMKCOk7IkloTboSzlYH/RLKKAf/FZocTPOJoByVbWZgaOurcaOAWBihpYJJGxrYFlMQtgCOOW1yp
pYEWKNAlQVM4EQ6HOIqpjgGlMBfmmDiuSeY/FthggQoEzdCLGSjg+MmdeRpoJ544/tlnbAEBADs=");?>
<!-- DEBUG ICON END -->