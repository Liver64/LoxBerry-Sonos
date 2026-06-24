<?php
/**
 * Sonos4Lox BinLog support helper.
 * Version: BIN_LOG_SUPPORT_V02_2026_06_15
 *
 * Changes in V02:
 * - Updated legacy bin/binlog.php log contexts to src/Support/BinLog.php.
 */

require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';

class S4L_BinLog
{
    /**
     * Write a throttled support-script log message to sonos.log.
     *
     * @param string $topic Log topic.
     * @param string $message Log message.
     * @return bool
     */
    public static function write($topic, $message)
    {
        global $lbplogdir;

        $checkFile = '/run/shm/s4lox_bin_err';
        $hour = (int)strftime('%H');

        if ($topic === 'Battery check') {
            if ($hour >= 8 && $hour < 21) {
                LBLog::newLog([
                    'name'     => $topic,
                    'addtime'  => 1,
                    'filename' => $lbplogdir . '/sonos.log',
                    'append'   => 1,
                ]);

                LOGSTART($topic);
                LOGINF($message);
                LOGOK('src/Support/BinLog.php: Battery check ended successfully.');
                @file_put_contents($checkFile, '1');
                LOGEND($topic . ' end');
                return true;
            }

            return false;
        }

        if (is_file($checkFile)) {
            return false;
        }

        LBLog::newLog([
            'name'     => $topic,
            'addtime'  => 1,
            'filename' => $lbplogdir . '/sonos.log',
            'append'   => 1,
        ]);

        LOGSTART($topic);
        LOGERR('src/Support/BinLog.php: ' . $message);
        @file_put_contents($checkFile, '1');
        LOGEND($topic . ' end');
        return true;
    }
}
