<?php
/**
 * QXS Daemon
 */
namespace QXS\Daemon;


/**
 * The Daemonizeable class
 */
abstract class Daemonizeable {
	private $daemon;
	public abstract function onProcessCreate();
	public abstract function onProcessDestroy();
	public abstract function run();
	public function receiveSignal($signo)  {
	}
	protected final function processSignals() {
		$this->daemon->processSignals(); 
	}
	protected final function log($message, $level='ERROR') {
		$this->daemon->log($message, $level, $this);
	}
	protected final function mustExit() {
		return $this->daemon->mustExit(); 
	}
	public final function attachDaemon(Daemon $daemon) {
		if($this->daemon===null) {
			$this->daemon = $daemon;
		}
	}
}


