<?php


// __destruct / disposal is NOT executed in case of an uncaught exception

class T  {
	public $n;
	public function __destruct() {
		echo "[".$this->n."]disposed\n";
	}
	public function __construct($n) {
		echo "[$n] hi\n";
		$this->n = $n;
	}
}


$f = function($t1, $t2) {
	echo $t1->n . " " . $t2->n . "\n";
};
$f(new T("1"), new T("2"));


$f2=function($t1, $t2) {
	echo $t1->n . " " . $t2->n . "\n";
	throw new Exception("Serious problem here");
};
$f2(new T("3"), new T("4"));

