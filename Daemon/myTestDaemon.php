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

$options=array('-h' => false, '-v' => false);
$args=array();
$opts=array();
foreach($_SERVER['argv'] as $arg) {
	if(isset($options[$arg])) {
		$opts[$arg] = true;
	}
	else {
		$args[]=$arg;
	}
}

try {
	$d = new Daemon(new MyDaemon());
	$d->setLogfile(__DIR__."/daemonlog.txt");
	if(!isset($args[1])) {
		throw new InvalidArgumentException('Usage: '.basename($_SERVER['PHP_SELF']).' start|stop|restart|status');
	}
	switch(strtolower($args[1])) {
		case 'start': $d->start(); break;
		case 'stop': $d->stop(); break;
		case 'status': echo "Daemon is ".($d->isRunning() ? "running" : "NOT RUNNING" )."\n"; break;
		case 'restart': $d->restart(); break;
		default: throw new InvalidArgumentException('Usage: '.basename($_SERVER['PHP_SELF']).' start|stop|restart|status');
	}
}
catch(Exception $e) {
	if(posix_isatty(STDOUT)) {
		echo '['.get_class($e).'] '."\033[31m".$e->getMessage()."\033[0m\n";
		if(isset($opts['-v'])) {
			echo "\033[35m".$e->getTraceAsString()."\033[0m\n";
		}
	}
	else {
		echo '['.get_class($e).'] '.$e->getMessage()."\n";
		if(isset($opts['-v'])) {
			echo $e->getTraceAsString()."\n";
		}
	}
	exit(1);
}

