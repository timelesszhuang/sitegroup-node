<?php
/**
 *
 * Coding 相关
 * User: timeless
 * Date: 18-3-9
 * Time: 上午9:56
 */

namespace app\common\controller;


class Coding
{
    static function tiriEncode($str, $factor = 0)
    {
        $len = strlen($str);
        if (!$len) {
            return;
        }
        if ($factor === 0) {
            $factor = mt_rand(1, min(255, ceil($len / 3)));
        }
        $c = $factor % 8;

        $slice = str_split($str, $factor);
        for ($i = 0; $i < count($slice); $i++) {
            for ($j = 0; $j < strlen($slice[$i]); $j++) {
                $slice[$i][$j] = chr(ord($slice[$i][$j]) + $c + $i);
            }
        }
        $ret = pack('C', $factor) . implode('', $slice);
        return self::base64URLEncode($ret);
    }

    static function tiriDecode($str)
    {
        if ($str == '') {
            return;
        }
        $str = self::base64URLDecode($str);
        $factor = ord(substr($str, 0, 1));
        $c = $factor % 8;
        $entity = substr($str, 1);
        $slice = str_split($entity, $factor);
        if (!$slice) {
            return false;
        }
        for ($i = 0; $i < count($slice); $i++) {
            for ($j = 0; $j < strlen($slice[$i]); $j++) {
                $slice[$i][$j] = chr(ord($slice[$i][$j]) - $c - $i);
            }
        }
        return implode($slice);
    }

    static function base64URLEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    static function base64URLDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    static function stringXor($str)
    {
        for ($i = 0; $i < strlen($str); ++$i) {
            $str[$i] = chr(ord($str[$i]) ^ 0x7F);
        }
        return $str;
    }
}