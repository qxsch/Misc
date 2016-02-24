<?php

require('Daemon.php');

class MyDaemon extends Daemonizeable {
	protected $fd;

	public function onProcessCreate() {
		//$this->fd=fopen(__DIR__."/log.txt", "w");
	}

	public function onProcessDestroy() {
		//fclose($this->fd);
	}

	public function run() {
		while(!$this->mustExit()) {
			echo "Hi\n";
			//fputs($this->fd, "Hi\n");
			$this->log('HI', 'NOTICE');
			sleep(10);
		}
	}
}


try {
	$d = new Daemon(new MyDaemon());
	$d->setLogfile(__DIR__."/daemonlog.txt");
	if(!isset($_SERVER['argv'][1])) {
		throw new InvalidArgumentException('Usage: '.basename($_SERVER['PHP_SELF']).' start|stop|restart|status');
	}
	switch(strtolower($_SERVER['argv'][1])) {
		case 'start': $d->start(); break;
		case 'stop': $d->stop(); break;
		case 'status': echo "Daemon is ".($d->isRunning() ? "running" : "NOT RUNNING" )."\n"; break;
		case 'restart': $d->restart(); break;
	}
}
catch(Exception $e) {
	if(posix_isatty(STDOUT)) {
		echo '['.get_class($e).'] '."\033[31m".$e->getMessage()."\033[0m\n";
	}
	else {
		echo '['.get_class($e).'] '.$e->getMessage()."\n";
	}
	exit(1);
}

