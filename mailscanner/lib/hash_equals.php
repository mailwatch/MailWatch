<?php
/**
 * A Compatibility library with PHP 5.5's simplified password hashing API.
 *
 * Declared on the global namespace
 *
 * @author Miguel Angel Liebana <mi.liebana@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @copyright 2015 Miguel Angel Liebana
 */

namespace
{
    if (!function_exists('hash_equals')) {
        /**
         * Timing attack safe string comparison
         *
         * Compares two strings using the same time whether they're equal or not.
         * This function should be used to mitigate timing attacks; for instance, when testing crypt() password hashes.
         *
         * As the original function, emits an E_USER_WARNING if any of the parameters is not a string.
         *
         * @param string $knownString The string of known length to compare against
         * @param string $userString  The user-supplied string
         *
         * @return bool Returns TRUE when the two strings are equal, FALSE otherwise.
         */
        function hash_equals($knownString, $userString)
        {
            if (!is_string($knownString)) {
                trigger_error("hash_equals(): Expected knownString to be a string", E_USER_WARNING);
                return false;
            }

            if (!is_string($userString)) {
                trigger_error("hash_equals(): Expected userString to be a string", E_USER_WARNING);
                return false;
            }

            $knownLength = strlen($knownString);
            $userLength  = strlen($userString);
            $testLength  = min($knownLength, $userLength);

            $result = $knownLength - $userLength;

            for ($i = 0; $i < $testLength; $i++) {
                $result |= (ord($knownString[$i]) ^ ord($userString[$i]));
            }

            return 0 === $result;
        }
    }
}