<?php

namespace PostCSS;

/**
 * Bare port of encoding functions used by Mozilla SourceMap.
 *
 * @link https://github.com/mozilla/source-map/blob/master/lib/base64.js
 * @link https://github.com/mozilla/source-map/blob/master/lib/base64-vlq.js
 */
class Encoding
{
    /**
     * A single base 64 digit can contain 6 bits of data. For the base 64 variable
     * length quantities we use in the source map spec, the first bit is the sign,
     * the next four bits are the actual value, and the 6th bit is the
     * continuation bit. The continuation bit tells us whether there are more
     * digits in this value following this digit.
     *
     *   Continuation
     *   |    Sign
     *   |    |
     *   V    V
     *   101011
     *
     * @var int
     */
    const VLQ_BASE_SHIFT = 5;

    /**
     * Binary: 100000.
     *
     * @var int
     */
    const VLQ_BASE = 32; //1 << static::VLQ_BASE_SHIFT;
    /**
     * binary: 011111.
     *
     * @var int
     */
    const VLQ_BASE_MASK = 31; //static::VLQ_BASE - 1;

    /**
     * binary: 100000.
     *
     * @var int
     */
    const VLQ_CONTINUATION_BIT = 32; //static::VLQ_BASE;

    /**
     * @param int $value
     * @param int $places
     *
     * @return int
     */
    protected static function unsignedRightShift($value, $places)
    {
        if ($value >= 0 || $places < 1) {
            return $value >> $places;
        }
        $value = (int) substr(decbin($value), 0, -1);
        if ($places > 1) {
            $value >>= $places - 1;
        }

        return $value;
    }

    /**
     * Converts from a two-complement value to a value where the sign bit is
     * placed in the least significant bit.  For example, as decimals:
     *   1 becomes 2 (10 binary), -1 becomes 3 (11 binary)
     *   2 becomes 4 (100 binary), -2 becomes 5 (101 binary).
     *
     * @param int $aValue
     *
     * @return int
     */
    protected static function toVLQSigned($aValue)
    {
        return ($aValue < 0) ? ((-$aValue) << 1) + 1 : ($aValue << 1);
    }

    /**
     * Converts to a two-complement value from a value where the sign bit is
     * placed in the least significant bit.  For example, as decimals:
     *   2 (10 binary) becomes 1, 3 (11 binary) becomes -1
     *   4 (100 binary) becomes 2, 5 (101 binary) becomes -2.
     */
    protected static function fromVLQSigned($aValue)
    {
        $isNegative = ($aValue & 1) === 1;
        $shifted = $aValue >> 1;

        return $isNegative ? -$shifted : $shifted;
    }

    const BASE64_INT_TO_CHAR_MAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /**
     * Encode an integer in the range of 0 to 63 to a single base 64 digit.
     *
     * @param int $number
     *
     * @return string
     *
     * @link https://github.com/mozilla/source-map/blob/master/lib/base64.js
     */
    protected static function toBase64($number)
    {
        $s = static::BASE64_INT_TO_CHAR_MAP;
        if (isset($s[$number])) {
            return $s[$number];
        }
        throw new \Exception('Must be between 0 and 63: '.$number);
    }

    protected static function fromBase64($charCode)
    {
        $bigA = 65;     // 'A'
        $bigZ = 90;     // 'Z'

        $littleA = 97;  // 'a'
        $littleZ = 122; // 'z'

        $zero = 48;     // '0'
        $nine = 57;     // '9'

        $plus = 43;     // '+'
        $slash = 47;    // '/'

        $littleOffset = 26;
        $numberOffset = 52;

        // 0 - 25: ABCDEFGHIJKLMNOPQRSTUVWXYZ
        if ($bigA <= $charCode && $charCode <= $bigZ) {
            return $charCode - $bigA;
        }

        // 26 - 51: abcdefghijklmnopqrstuvwxyz
        if ($littleA <= $charCode && $charCode <= $littleZ) {
            return $charCode - $littleA + $littleOffset;
        }

        // 52 - 61: 0123456789
        if ($zero <= $charCode && $charCode <= $nine) {
            return $charCode - $zero + $numberOffset;
        }

        // 62: +
        if ($charCode == $plus) {
            return 62;
        }

        // 63: /
        if ($charCode == $slash) {
            return 63;
        }

        // Invalid base64 digit.
        return -1;
    }

    /**
     * Returns the base 64 VLQ encoded value.
     *
     * @param int $aValue
     *
     * @return string
     */
    public static function toBase64VLQ($aValue)
    {
        $encoded = '';

        $vlq = static::toVLQSigned($aValue);

        do {
            $digit = $vlq & static::VLQ_BASE_MASK;
            $vlq = static::unsignedRightShift($vlq, static::VLQ_BASE_SHIFT);
            if ($vlq > 0) {
                // There are still more digits in this value, so we must make sure the
                // continuation bit is marked.
                $digit |= static::VLQ_CONTINUATION_BIT;
            }
            $encoded .= static::toBase64($digit);
        } while ($vlq > 0);

        return $encoded;
    }

    /**
     * Decodes the next base 64 VLQ value from the given string and returns the
     * value and the rest of the string via the out parameter.
     */
    public static function fromBase64VLQ($aStr, $aIndex)
    {
        $strLen = strlen($aStr);
        $result = 0;
        $shift = 0;
        do {
            if ($aIndex >= $strLen) {
                throw new \Exception('Expected more digits in base 64 VLQ value.');
            }
            $digit = static::fromBase64(ord($aStr[$aIndex++]));
            if ($digit === -1) {
                throw new \Exception('Invalid base64 digit: '.$aStr[$aIndex - 1]);
            }
            $continuation = (bool) ($digit & static::VLQ_CONTINUATION_BIT);
            $digit &= static::VLQ_BASE_MASK;
            $result = $result + ($digit << $shift);
            $shift += static::VLQ_BASE_SHIFT;
        } while ($continuation);

        return [
            'value' => static::fromVLQSigned($result),
            'rest' => $aIndex,
        ];
    }
}
