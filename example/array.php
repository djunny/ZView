<?php
/**
 * User: dj
 * Date: 2020/7/17
 * Time: 下午1:08
 */
include './function.php';

view()->bind([
    'list' => range(1, 10),
])->show('array');
