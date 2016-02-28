<?php


namespace ReactiveNet;

use Rx\Disposable\CompositeDisposable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\SchedulerInterface;
use Rx\Subject\Subject;
use Thruway\ClientSession;

class ChannelSubject extends Subject
{
    /** @var Subject */
    private $session;

    /** @var */
    private $uri;

    /** @var null */
    private $options;

    /** @var CompositeDisposable */
    private $disposable;

    /** @var SchedulerInterface */
    private $scheduler;

    /**
     * TopicSubject constructor.
     * @param Subject $session
     * @param $uri
     * @param null $options
     * @param SchedulerInterface $scheduler
     */
    public function __construct(Subject $session, $uri, $options = null, SchedulerInterface $scheduler = null)
    {
        $this->uri        = $uri;
        $this->options    = $options;
        $this->session    = $session;
        $this->disposable = new CompositeDisposable();
        $this->scheduler  = $scheduler;
    }

    public function subscribe(ObserverInterface $observer, $scheduler = null)
    {
        $disposable = new CompositeDisposable([parent::subscribe($observer, $scheduler)]);

        $onNext = function (ClientSession $session) use ($disposable, $observer) {
            $session->subscribe($this->uri, function ($values) use ($observer) {
                $observer->onNext($values[0]); //Only support single value
            }, $this->options)->then(
                function ($s) {
                    //@todo add ability to unregister when disposed
                },
                function ($error) use ($observer) {
                    $observer->onNext(new \Exception($error));
                });
        };

        $callbackObserver = new CallbackObserver(
            $onNext,
            [$observer, 'onError'],
            [$observer, 'onCompleted']
        );

        $subscription = $this->session->subscribe($callbackObserver, $scheduler);

        $disposable->add($subscription);

        return $disposable;
    }

    public function onNext($value)
    {
        parent::onNext($value);

        $callbackObserver = new CallbackObserver(
            function (ClientSession $session) use ($value) {
                $session->publish($this->uri, [$value], null, $this->options);
            }
        );

        $subscription = $this->session
            ->filter(function (ClientSession $session) {
                return $session->getState() === $session::STATE_UP;
            })
            ->subscribe($callbackObserver, $this->scheduler);

        $this->disposable->add($subscription);
    }

    public function dispose()
    {
        $this->isDisposed = true;
        $this->observers  = [];
        $this->disposable->dispose();
    }
}
