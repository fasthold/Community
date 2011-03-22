<?php

/**
 * Global Debug function
 *
 * @param mixed $var
 * @param string $label
 * @param bool $dump
 */
function debug($var,$label=null,$dump=false) {
	if(is_null($var) || !isset($var) || is_bool($var)) {$dump = true;}
	echo "<pre>$label\n";
	if($dump) {
		if(function_exists('xdebug_var_dump')) {
			xdebug_var_dump($var);
		} elseif(class_exists('Zend_Debug')) {
			Zend_Debug::dump($var, $label);
		} else {
			var_dump($var);
		}
	} else {
		print_r($var);
	}
	echo "\n</pre>\n";
	return ;
}

/**
 * debug() something, but exit() when finish.
 *
 * @param Mixed $var
 * @param string $label
 * @param boolean $dump
 */
function d($var,$label=null,$dump=false) {
	debug($var,$label,$dump);
	die();
}

/**
 * var_dump() something, but exit() when finish.
 *
 * @param Mixed $var
 * @param string $label
 * @param boolean $dump
 */
function v($var,$label=null) {
	d($var,$label,true);
}

function dbProfiler() {
	if(Zend_Registry::isRegistered('Profiler')) {
		$profiler = Zend_Registry::get('Profiler');
		/** @var $profiler Zend_Db_Profiler */
		$p = $profiler->getQueryProfiles();
		if(!empty($p)) {
			echo "<hr>";
			foreach ($p as $query) {
				/* @var $query Zend_Db_Profiler_Query */
				echo '[';
				$elapsed = number_format($query->getElapsedSecs(),4);
				if($elapsed>=0.01) {
					echo "<font color=red>".$elapsed."</font>";
				} else {
					echo $elapsed;
				}
				echo '] <i>';
				echo $query->getQuery()."</i><br/>\n";
			}
		}
	}
	return ;
}
