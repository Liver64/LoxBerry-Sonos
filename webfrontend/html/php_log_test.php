<?php
echo "<pre>";
echo "error_log=" . ini_get('error_log') . "\n";
echo "log_errors=" . ini_get('log_errors') . "\n";
error_log("S4L PHP LOG TEST " . date('c'));
trigger_error("S4L PHP WARNING TEST", E_USER_WARNING);
echo "done\n";
