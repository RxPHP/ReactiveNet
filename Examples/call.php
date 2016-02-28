<?php

use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use ReactiveNet\ReactiveNet;

require __DIR__ . '/../vendor/autoload.php';

$remote    = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');
$scheduler = new EventLoopScheduler(\EventLoop\getLoop());

//Register Call
$remote->register("some.progress.test", function ($args) use ($scheduler) {
    return Observable::interval(1000, $scheduler)->take($args[2]);
})
    ->subscribeCallback(
        function ($x) {
            echo "registered", PHP_EOL;
        });

//Make Call
$remote->call('some.progress.test', [1, 2, 3])
    ->subscribeCallback(
        function ($result) {
            echo $result, PHP_EOL;
        },
        function (Exception $e) {
            echo $e->getMessage();
        },
        function () {
            echo "completed", PHP_EOL;
        }
    );
