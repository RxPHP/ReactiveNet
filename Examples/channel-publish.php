<?php

use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use ReactiveNet\ReactiveNet;

require __DIR__ . '/../vendor/autoload.php';

$remote    = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');
$scheduler = new EventLoopScheduler(\EventLoop\getLoop());

//Emit every second on the channel 'test.channel.interval'
$intervalObserver = $remote->channel('test.channel.interval');
Observable::interval(1000)
    ->subscribe($intervalObserver, $scheduler);

//Emit every second on the channel 'test.channel.stuff'
$stuffObserver = $remote->channel('test.channel.stuff');
Observable::interval(500)
    ->map(function ($i) {
        return "test-" . $i * 3;
    })
    ->subscribe($stuffObserver, $scheduler);
