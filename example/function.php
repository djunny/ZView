<?php
/**
 * User: dj
 * Date: 2020/7/17
 * Time: 下午1:10
 */

include '../src/ZView.php';
/**
 * normal view
 *
 * @param bool $callback
 *
 * @return \ZV\ZView
 */
function view($callback = FALSE) {
    $zview = new ZV\ZView([
        'tpl_ext'    => '.htm',
        'tpl_prefix' => 'tpl',
        'tmp_path'   => __DIR__ . '/tmp/',
        'force'      => 5,// 刷新频率（1为强制每次检测刷新缓存,大于2以上代表 rand(1,force) 检测刷新）
        'view_path'  => [
            __DIR__ . '/view/',
        ],
    ], $callback ?: function (...$args) use (&$body) {
        echo implode('', $args);
    });
    return $zview;
}

/**
 * swoole view
 *
 * @param $resp
 *
 * @return \ZV\ZView
 */
function swoole_view($resp) {
    return view(function (...$args) use ($resp) {
        foreach ($args as $text) {
            $text && $resp->write($text);
        }
    });
}