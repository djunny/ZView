<?php
/**
 * User: dj
 * Date: 2020/7/17
 * Time: 下午1:08
 */
include './function.php';

function test_call($value) {
    return $value * 10000;
}

class call
{
    public static function test($value) {
        return $value * 10;
    }
}

view()->bind([
    'name' => 'func_call'
])->show('func_call');
