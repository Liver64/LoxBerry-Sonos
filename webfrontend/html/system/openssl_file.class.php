<?php

final class OpenSSLFile
{
    /**
        * Encrypt and decrypt files with OpenSSL module.
        *
        * Coded to PHP 7.0
        * based on
        * @license     GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
        * @link        https://github.com/Tinram/OpenSSL-File-Encrypt.git
    */


    /* @const CIPHER encryption algorithm */
    const CIPHER = 'AES-256-CTR';
        /* other strong ciphers: AES-256-CBC, CAMELLIA-256-CBC */

    /* @const KEY_HASH, hash for password hashing and stretching */
    const KEY_HASH = 'SHA512';

    /* @const KEY_STRETCHES, number of key stretch iterations */
    const KEY_STRETCHES = 2 ** 16; /* increase towards paranoia */

    /* @const HMAC_HASH, hash for HMAC */
    const HMAC_HASH = 'SHA512';

    /* @const HMAC_LEN, HMAC_HASH byte length */
    const HMAC_LEN = 64;

    /* @const FILE_EXT, encrypted file extension suffix */
    const FILE_EXT = '.dat';


    /**
        * Encrypt a file.
        *
        * @param   string $sFilename, the file to encrypt
        * @param   string $sPassword, the password
        * @return  string, message
    */

    public static function encrypt(string $sFilename = '', string $sPassword = ''): string
    {
        self::checkArgs(__METHOD__, func_get_args());

        $sPlaintext = file_get_contents($sFilename);

        $sKey = $sPassword;

        for ($i = 0; $i < self::KEY_STRETCHES; $i++)
        {
            $sKey = openssl_digest($sKey, self::KEY_HASH);
        }

        $iIVLen = openssl_cipher_iv_length(self::CIPHER);
        $IV = random_bytes($iIVLen);

        $sCiphertextRaw = openssl_encrypt($sPlaintext, self::CIPHER, $sKey, OPENSSL_RAW_DATA, $IV);

        $HMAC = hash_hmac(self::HMAC_HASH, $sCiphertextRaw, $sKey, $binary=true);
        $sCiphertext = $IV . $HMAC . $sCiphertextRaw;

        $iF = file_put_contents($sFilename . self::FILE_EXT, $sCiphertext);

        if ($iF)
        {
            return PHP_EOL . ' \'' . $sFilename . self::FILE_EXT . '\' saved' . PHP_EOL;
        }
        else
        {
            return PHP_EOL . ' encryption failed' . PHP_EOL;
        }
    }


    /**
        * Decrypt a file.
        *
        * @param   string $sFilename, the file to decrypt
        * @param   string $sPassword, the password
        * @return  string, message
    */

    public static function decrypt(string $sFilename = '', string $sPassword = ''): string
    {
        self::checkArgs(__METHOD__, func_get_args());
		#echo $sFilename;
        $sCiphertext = file_get_contents($sFilename);

        $sKey = $sPassword;

        for ($i = 0; $i < self::KEY_STRETCHES; $i++)
        {
            $sKey = openssl_digest($sKey, self::KEY_HASH);
        }

        $iIVLen = openssl_cipher_iv_length(self::CIPHER);
        $IV = substr($sCiphertext, 0, $iIVLen);
        $HMAC = substr($sCiphertext, $iIVLen, self::HMAC_LEN);

        $sCiphertextRaw = substr($sCiphertext, $iIVLen + self::HMAC_LEN);
        $sDecryptedText = openssl_decrypt($sCiphertextRaw, self::CIPHER, $sKey, OPENSSL_RAW_DATA, $IV);
        $sCalcMAC = hash_hmac(self::HMAC_HASH, $sCiphertextRaw, $sKey, $binary=true);
		
		return $sDecryptedText;
    }


    /**
        * Passed arguments checker.
        *
        * @param   string $sMethodName, name of the method, for error output
        * @param   array $aArgs, method arguments
    */

    private static function checkArgs(string $sMethodName = '', array $aArgs = [])
    {
        if (empty($aArgs[0]))
        {
            die(__CLASS__ . '::' . $sMethodName . '(): $sFilename is missing!' . PHP_EOL);
        }
        else if (empty($aArgs[1]))
        {
            die(__CLASS__ . '::' . $sMethodName . '(): $sPassword is missing!' . PHP_EOL);
        }
    }

}
