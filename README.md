ReactiveNet
======

This is a proof-of-concept for using observables over a network.  It uses the [WAMP](http://wamp-proto.org/) protocol ([Thruway](https://github.com/voryx/Thruway)) with websockets, but can work with any transport supported by WAMP.

Goals of this project:

- Provide "Hot Observables" or Subjects that work seamlessly over a network
- Provide "Cold Observables" that work seamlessly over a network
- Model every aspect of the underlying tech with Rx components
- Support any language that has a WAMP client

#Installation

Download the zip file and then:

```
  composer install
```


#Usage

##Channels

This uses Thruway's publish and subscribe under the hood, but exposes it as a single channel (Subject) that can be subscribed to or observed on.

```php

//Client 1
$remote    = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1'); //WAMP router
$scheduler = new EventLoopScheduler(\EventLoop\getLoop());

//Emit every second on the channel 'test.channel.interval'
$intervalObserver = $remote->channel('test.channel.interval');
Observable::interval(1000)
    ->subscribe($intervalObserver, $scheduler);


//Client 2
$remote    = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');
$scheduler = new EventLoopScheduler(\EventLoop\getLoop());

//Subscribe to just one stream
$obs1 = $remote->channel('test.channel.stuff');

$obs1->subscribeCallback(function ($x) {
    echo $x, PHP_EOL;
});

```


##Calls (Cold Observable)

Uses Thruway's RPC with progress to create a cold observable

```php

$remote    = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');
$scheduler = new EventLoopScheduler(\EventLoop\getLoop());

//Client 1 - Register Call
$remote->register("some.progress.test2", function ($args) use ($scheduler) {
    return Observable::interval(1000, $scheduler)->take($args[2]);
})
    ->subscribeCallback(
        function ($x) {
            echo "registered", PHP_EOL;
        });

//Client 2 - Make Call
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



```

Javascript
```JS
    var remote = new ReactiveNet("ws://demo.thruway.ws:9090", 'realm1');

    var obs1 = remote.channel('test.channel.stuff');

    //Subscribe to just one stream
    obs1.subscribe(function (x) {
        console.log(x);
    });

    //Make Call
    remote.call('some.progress.test', [1, 2, 3]).subscribe(
            function (result) {
                console.log(result);
            },
            function (err) {
                console.log('error');
            },
            function () {
                console.log('completed');
            });

```


Todo:

- Write tests
- Handle reconnecting
- Better error handling
- Dispose stuff
- Rename call and register.  

See more in the [examples](Examples)

Similar project [ReactiveSocket](https://github.com/reactivesocket)  



