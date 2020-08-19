<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-02-26
 * Time: 17:52
 */
namespace app\common\helper;

class Pbkdf2
{

    /**
     * PBKDF2 密码哈希
     */
    const PBKDF2_HASH_ALGORITHM ='sha256';   //hash算法
    const PBKDF2_ITERATIONS = 1000;            //迭代次数
    const PBKDF2_SALT_BYTE_SIZE = 24;         //盐值长度
    const PBKDF2_HASH_BYTE_SIZE = 24;         //哈希长度

    const HASH_SECTIONS = 4;                   //哈希段
    const HASH_ALGORITHM_INDEX = 0;           //算法
    const HASH_ITERATION_INDEX = 1;           //迭代
    const HASH_SALT_INDEX = 2;                //盐值
    const HASH_PBKDF2_INDEX = 3;              //PBKDF2

    public static function create_hash($password)
    {
        $salt = base64_encode(random_bytes(self::PBKDF2_SALT_BYTE_SIZE));
        return self::PBKDF2_HASH_ALGORITHM . ":" . self::PBKDF2_ITERATIONS . ":" . $salt . ":" .
            base64_encode(self::pbkdf2(
                self::PBKDF2_HASH_ALGORITHM,
                $password,
                $salt,
                self::PBKDF2_ITERATIONS,
                self::PBKDF2_HASH_BYTE_SIZE,
                true)
            );
    }

    public static function validate_password($password, $correct_hash)
    {
        $params = explode(":", $correct_hash);
        if (count($params) < self::HASH_SECTIONS) {
            return false;
        }
        $pbkdf2 = base64_decode($params[self::HASH_PBKDF2_INDEX]);
        return self::slow_equals(
            $pbkdf2,
            self::pbkdf2(
                $params[self::HASH_ALGORITHM_INDEX],
                $password,
                $params[self::HASH_SALT_INDEX],
                (int) $params[self::HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }

    protected static function slow_equals($a, $b)
    {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b) ; $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }

    protected static function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, hash_algos(), true)) {
            trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
        }
        if ($count <= 0 || $key_length <= 0) {
            trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);
        }
        if (function_exists("hash_pbkdf2")) {
            if (!$raw_output) {
                $key_length = $key_length * 2;
            }
            return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
        }
        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = '';
        for($i = 0; $i <= $block_count; $i++) {
            $last = $salt . pack('N', $i);
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
            for($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }
        if ($raw_output) {
            return substr($output, 0, $key_length);
        } else {
            return bin2hex(substr($output, 0, $key_length));
        }
    }
}