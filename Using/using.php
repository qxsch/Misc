<?php


interface IDisposable {
	public function dispose();
}

class UsingStatement implements IDisposable {
	protected $disposables=array();
	public function __construct($disposables) {
		foreach($disposables as $arg) {
			if($arg instanceof IDisposable) {
				$this->disposables[] = $arg;
			}
		}
	}

	public function __destruct() {
		$this->dispose();
	}

	public function call(Closure $call, $autodispose=true) {
		try {
			call_user_func_array($call, $this->disposables);
		}
		finally {
			if($autodispose===true) {
				$this->dispose();
			}
		}
	}

	public function dispose() {
		foreach(array_reverse($this->disposables) as $obj) {
			$obj->dispose();
		}
		// free pointers to allow garbage collection
		$this->disposables = array();
	}
}

function using() {
	$args = func_get_args();
	$end = end($args);
	if($end instanceof Closure) {
		$end = array_pop($args);
		$c = new UsingStatement($args);
		$c->call($end);
		return $c;
	}
	return new UsingStatement($args);
}





class T implements IDisposable {
	public $n;
	public function dispose() {
		echo "[".$this->n."]disposed\n";
	}
	public function __construct($n) {
		echo "[$n] hi\n";
		$this->n = $n;
	}
}


using(new T("1"), new T("2"))->call(function($t1, $t2) {
	echo $t1->n . " " . $t2->n . "\n";
});

using(new T("3"), new T("4"), function($t1, $t2) {
	echo $t1->n . " " . $t2->n . "\n";
});

using(new T("5"), new T("6"), function($t1, $t2) {
	echo $t1->n . " " . $t2->n . "\n";
	throw new Exception("Serious problem here");
});

