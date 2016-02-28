<?php

namespace ReactiveNet;

use Ratchet\Wamp\Exception;
use React\Promise\Deferred;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\React\Promise;
use Rx\SchedulerInterface;
use Rx\Subject\ReplaySubject;
use Rx\Subject\Subject;
use Thruway\ClientSession;
use Thruway\Message\ErrorMessage;
use Thruway\Peer\Client;
use Thruway\Transport\PawlTransportProvider;

class ReactiveNet
{
    /** @var Client */
    private $client;

    /** @var Subject */
    private $session;

    /** @var SchedulerInterface */
    private $scheduler;

    /**
     * RxNetClient constructor.
     * @param $uri
     * @param $realm
     * @param SchedulerInterface $scheduler
     * @throws \Exception
     */
    public function __construct($uri, $realm, SchedulerInterface $scheduler = null)
    {
        $this->client    = new Client($realm, \EventLoop\getLoop());
        $this->session   = new ReplaySubject(1);
        $this->scheduler = $scheduler;

        $this->client->addTransportProvider(new PawlTransportProvider($uri));

        $this->client->on('open', function (ClientSession $session) {
            $this->session->onNext($session);
        });

        $this->client->on('error', function ($error) {
            //It might be better to have an error subject that these get emitted on
            //$this->session->onError(new \Exception($error));
        });

        $this->client->on('close', function ($reason) {

            //@todo need some retry logic here
            // $this->session->onCompleted();
        });

        $this->client->start(false);
    }

    public function channel($uri, $options = null)
    {
        return new ChannelSubject($this->session, $uri, $options, $this->scheduler);
    }

    public function register($uri, callable $sourceCallable, $options = null)
    {

        $callable = function ($args, $argsKw, $details) use ($sourceCallable) {
            $deferred = new Deferred();

            /** @var Observable $observable */
            $observable = call_user_func_array($sourceCallable, [$args, $details]);

            $observable->subscribe(new CallbackObserver(
                function ($value) use ($deferred) {
                    $deferred->progress($value);
                },
                function (Exception $exception) use ($deferred) {
                    $deferred->reject($exception->getMessage());
                },
                function () use ($deferred) {
                    $deferred->resolve();
                }
            ));

            return $deferred->promise();
        };

        return $this->session->flatMap(function (ClientSession $session) use ($uri, $callable, $options) {
            return Promise::toObservable($session->register($uri, $callable, $options));
        });

    }

    public function call($uri, array $args = [], array $options = [])
    {
        $options['receive_progress'] = true;

        $disposed = new Subject();

        $fromPromise = function (ClientSession $session) use ($uri, $args, $options, $disposed) {
            return Observable::create(function (ObserverInterface $observer) use ($uri, $args, $options, $session, $disposed) {
                $session->call($uri, $args, null, $options)->then(
                    function ($values) use ($observer) {
                        if ($values[0] !== null) {
                            $observer->onNext($values[0]);
                        }
                        $observer->onCompleted();
                    },
                    function (ErrorMessage $error) use ($observer) {
                        $observer->onError(new Exception($error));
                    },
                    function ($values) use ($observer) {
                        $observer->onNext($values[0]);
                    }
                );

                return new CallbackDisposable(function () use ($disposed) {
                    $disposed->onNext(true);
                });
            });
        };

        return $this->session
            ->flatMap($fromPromise)
            ->takeUntil($disposed);
    }

}
