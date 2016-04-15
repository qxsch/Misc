<?php

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

/**
 * Exception for the Impersonation
 */
class ImpersonationException extends RuntimeException {}
/**
 * Exception for Daemon Errors
 */
class DaemonException extends RuntimeException { }

class Daemon {
	private $logfile=null;
	private $logfd=null;
	private $runAsUserInfo=null;
	protected $processTitleFormat='%basename%: Daemon %class%';
	protected $daemonizeable;
	protected $terminate=false;

	public function __construct(Daemonizeable $daemonizeable) {
		$this->daemonizeable = $daemonizeable;
		$this->daemonizeable->attachDaemon($this);
		pcntl_signal(SIGTERM, array($this, 'receiveSignal'));
		pcntl_signal(SIGUSR1, array($this, 'receiveSignal'));
		pcntl_signal(SIGUSR2, array($this, 'receiveSignal'));
	}

	/**
	 * Gets the user the server should use
	 *
	 * @return null|string  the user, that the server should use or null in case no user has been set
	 */
	public function getRunAsUser() {
		if($this->runAsUserInfo===null) return null;

		return $this->runAsUserInfo['name'];
	}

	/**
	 * Sets the user the server should use
	 *
	 * @param string $user  set the user, that the server should use
	 */
	public function setRunAsUser($user) {
		if($user===null) {
			$this->runAsUserInfo=null;
			return $this;
		}
		$info=posix_getpwnam($user);
		if($info===false || !is_array($info)) {
			throw new \DomainException('Invalid username. The user "'.$user.'" does not exist.');
		}
		$this->runAsUserInfo=$info;

		return $this;
	}

	/**
	 * Returns the process title of the daemon
	 * @return string the process title of the daemon
	 */
	public function getProcessTitleFormat() {
		return $this->processTitleFormat;
	}

	/**
	 * Sets the process title of the daemon
	 *
	 * Listing permitted replacments
	 *   %basename%  The base name of PHPSELF
	 *   %fullname%  The value of PHPSELF
	 *   %class%     The Daemon's Classname
	 *
	 * @param string $string the process title of the daemon
	 */
	public function setProcessTitleFormat($string) {
		$this->processTitleFormat = (string)$string;
		return $this;
	}


	public function setLogfile($path) {
		$this->logfile=(string)$path;
		return $this;
	}
	public function getLogfile($path) {
		return $this->logfile;
	}
	public function log($message, $level='ERROR', $origin=null) {
		if($this->logfd!==null) {
			if($this===$origin) {
				fputs($this->logfd, '['.date('r').']['.strtoupper($level).'] '.trim($message)."\n");
			}
			else {
				if(is_object($origin)) {
					fputs($this->logfd, '['.date('r').']['.strtoupper($level).']['.get_class($origin).'] '.trim($message)."\n");
				}
				else {
					fputs($this->logfd, '['.date('r').']['.strtoupper($level).']['.$origin.'] '.trim($message)."\n");
				}
			}
		}
	}

	protected function findSameProcesses() {
		$pids=array();
		exec('ps axo pid,args 2>&1', $out, $rc);
		if($rc!=0) {
			// Exception
			throw new DaemonException('Failed to lookup processes with return code '.$rc.' and message '.$out);
		}

		$title = $this->getProcessTitleString(
			$this->processTitleFormat,
			array(
				'basename' => basename($_SERVER['PHP_SELF']),
				'fullname' => $_SERVER['PHP_SELF'],
				'class' => get_class($this->daemonizeable)
			)
		);

		$mypid=getmypid();
		if($title=='') {
			// no proc title support
			foreach($out as $line) {
				if(strpos($line, $_SERVER['argv'][0])!==false && strpos($line, 'php')!==false) {
					if(preg_match('/^\s*(\d+)/', $line, $matches)) {
						if($mypid!=$matches[1]) {
							$pids[]=(int)$matches[1];
						}
					}
				}
			}
		}
		else {
			// proc title support
			foreach($out as $line) {
				if(preg_match('/^\s*(\d+)\s+(.*)$/', $line, $matches)) {
					if(trim($matches[2])==$title && $mypid!=$matches[1]) {
						$pids[]=(int)$matches[1];
					}
				}
			}
		}
		return $pids;
	}

	protected function killPids(array $pids, $signal=SIGTERM) {
		foreach($pids as $pid) {
			posix_kill($pid, $signal);
		}
	}

	protected function getProcessTitleString($title, array $replacements = array()) {
		// process title not supported
		if(!(function_exists('cli_set_process_title') || function_exists('setproctitle'))) {
			return;
		}
		$title=trim($title);
		// skip when empty title names or running on MacOS
		if($title == '' || PHP_OS == 'Darwin') {
			return;
		}
		// 1. replace the values
		$title = preg_replace_callback(
			'/\%([a-z0-9]+)\%/i',
			function ($match) use ($replacements) {
				if (isset($replacements[$match[1]])) {
					return $replacements[$match[1]];
				}
				return $match[0];
			},
			$title
		);
		// 2. remove forbidden chars
		$title = preg_replace(
			'/[^a-z0-9-_.: \\\\\\]\\[]/i',
			'',
			$title
		);
		return $title;
	}

	/**
	 * Sets the proccess title
	 *
	 * This function call requires php5.5+ or the proctitle extension!
	 * Empty title strings won't be set.
	 * @param string $title the new process title
	 * @param array $replacements an associative array of replacment values
	 * @return void
	 */
	protected function setProcessTitle($title, array $replacements = array()) {
		$title = $this->getProcessTitleString($title, $replacements);
		// skip when empty title names or running on MacOS
		if($title == '') {
			return;
		}
		// set the title
		if(function_exists('cli_set_process_title')) {
			cli_set_process_title($title); // PHP 5.5+ has a builtin function
		}
		elseif (function_exists('setproctitle')) {
			setproctitle($title); // pecl proctitle extension
		}
	}

	/**
	 * Switch the user
	 */
	protected function switchUser() {
		if($this->runAsUserInfo===null) return null;
		if(
			isset($this->runAsUserInfo['uid']) &&
			isset($this->runAsUserInfo['gid'])
		) {
			if(!(
				posix_setegid($this->runAsUserInfo['gid']) &&
				posix_seteuid($this->runAsUserInfo['uid'])
			)) {
				throw new ImpersonationException('Cannot switch to user "'.$this->runAsUserInfo['name'].'"');
			}
		}
	}

	public function start() {
		$this->terminate=false;
		$pids=$this->getRunningPids();
		if(!empty($pids)) {
			throw new DaemonException('The daemon is already running with pid '.implode(', ', $pids));
		}

		$this->switchUser(); // switch the user if applicable

		$pid=pcntl_fork(); // fork
		if($pid<0) {
			throw new DaemonException('Cannot fork the daemon.');
		}
		elseif($pid==0) {
			// we are the child
			posix_setsid();
			$pid=pcntl_fork(); // fork
			if($pid<0) {
				throw new DaemonException('Cannot fork the daemon to free it.');
			}
			elseif($pid==0) {
				if($this->logfile!==null) {
					if(!($this->logfd=fopen($this->logfile, "a"))) {
						$this->logfd=null;
					}
				}
				$this->setProcessTitle(
					$this->processTitleFormat,
					array(
						'basename' => basename($_SERVER['PHP_SELF']),
						'fullname' => $_SERVER['PHP_SELF'],
						'class' => get_class($this->daemonizeable)
					)
				);
				// We are now completely free and running under init.
				fclose(STDIN);
				fclose(STDOUT);
				fclose(STDERR);
				umask(0);
				chdir('/');

				@ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE);
				$this->log('Starting the daemon '.get_class($this->daemonizeable), 'INFO', $this);
				// let's run the daemonizeable
				try {
					$this->daemonizeable->onProcessCreate();
					try {
						while(!$this->mustExit()) {
							$this->daemonizeable->run();
						}
					}
					catch(Exception $e) {
						$this->log('A '.get_class($e).' exception occurred with message: '.$e->getMessage()."\n".$e->getTraceAsString(), 'ERROR', $this);
					}
					$this->daemonizeable->onProcessDestroy();
				}
				catch(Exception $e) {
					$this->log('A '.get_class($e).' exception occurred with message: '.$e->getMessage()."\n".$e->getTraceAsString(), 'ERROR', $this);
				}

				// process all signals before closing the log file
				$this->processSignals();
				$this->log('Stopping the daemon '.get_class($this->daemonizeable), 'INFO', $this);
				if($this->logfd!==null) {
					fclose($this->logfd);
				}

			}
			exit();
		}
		else {
			// we are the parent
		}
	}
	public function stop() {
		$pids=$this->getRunningPids();
		if(empty($pids)) {
			throw new DaemonException('The daemon is not running');
		}
		$this->killPids($pids);
	}

	public function getRunningPids() {
		return $this->findSameProcesses();
	}
	public function isRunning() {
		$pids=$this->getRunningPids();
		if(empty($pids)) {
			return false;
		}
		else {
			return true;
		}
	}

	public function restart() {
		$this->stop();
		sleep(1);
		$this->start();
	}

	public function mustExit() {
		$this->processSignals();
		return $this->terminate;
	}
	public function processSignals() {
		// process normal signals
		pcntl_signal_dispatch();

		// process ob_start
		$output = @trim(ob_get_clean());
		if($output!='') {
			$this->log('Received the following output: '.$output, 'WARNING', $this);
		}
		@ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE);

	}
	public function receiveSignal($signo) {
		switch($signo) {
			case SIGTERM: $this->terminate=true; break;
		}
		$this->daemonizeable->receiveSignal($signo);
	}
}
