<?php


use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

include './function.php';

$http = new Server("0.0.0.0", 9555);


$http->set(array(
    'daemonize'        => 0,
    'enable_coroutine' => 0,
    'http_compression' => 1,
    'http_gzip_level'  => 6,
));
$http->on('request', function (Request $req, Response $resp) use (&$saved_ip) {
    swoole_view($resp)->bind([
        'name' => 'swoole',
    ])->show('hello');

    $resp->status(200);
    $resp->end();
});
$http->start();
