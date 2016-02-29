function ReactiveNet(url, realm) {
    var self = this;

    this.client = new autobahn.Connection({url: url, realm: realm});
    this.session = new Rx.ReplaySubject(1);

    this.client.onopen = function (session) {
        self.session.onNext(session);
    };

    this.client.open();
}

ReactiveNet.prototype.channel = function (uri, options) {
    return new ChannelSubject(this.session, uri, options);
};

ReactiveNet.prototype.call = function (uri, args, options) {

    options = options || {};
    options.receive_progress = true;
    var disposed = new Rx.Subject();

    return this.session
        .flatMap(function (session) {
            return Rx.Observable.create(
                function (observer) {
                    session.call(uri, args, null, options).then(
                        function (values) {
                            if (values !== null) {
                                observer.onNext(values);
                            }
                            observer.onCompleted();
                        },
                        function (error) {
                            observer.onError(error.error);
                        },
                        function (values) {
                            observer.onNext(values);
                        }
                    );
                }
            )
        })
        .takeUntil(disposed);
};

function ChannelSubject(session, uri, options) {
    var self = this;
    this.uri = uri;
    this.options = options;
    this.session = session;
    this.disposable = Rx.CompositeDisposable();

    var observable = Rx.Observable.create(function (observer) {
        self.session.subscribe(function (session) {
            session.subscribe(self.uri, function (values) {
                    observer.onNext(values[0]);
                }, self.options)
                .then(
                    function ($s) {
                        //@todo add ability to unregister when disposed
                    },
                    function ($error) {
                        observer.onNext(new Error($error));
                    }
                );
        });
    });

    var observer = Rx.Observer.create(
        function (value) {

            self.session
                .filter(function (session) {
                    debugger;
                    return true;
                })
                .subscribe(
                    function (session) {
                        session.publish(self.uri, [value], null, self.options);
                    }
                )
        });

    return Rx.Subject.create(observer, observable);
}
