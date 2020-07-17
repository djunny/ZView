<?php
/**
 * User: dj
 * Date: 2020/7/17
 * Time: 下午1:08
 */
include '../src/ZView.php';
include './function.php';

view()->bind([
    'name' => 'eval',
])->show('hello');
