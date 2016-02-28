<?php

use ReactiveNet\ReactiveNet;

require __DIR__ . '/../vendor/autoload.php';

$remote = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');

$obs1 = $remote->channel('test.channel.stuff');
$obs2 = $remote->channel('test.channel.interval');

//Subscribe to just one stream
$obs1->subscribeCallback(function ($x) {
        echo $x, PHP_EOL;
    });

//Combine the latest from two streams
$obs1
    ->combineLatest([$obs2])
    ->subscribeCallback(function ($x) {
        echo $x[0], ':', $x[1], PHP_EOL;
    });
