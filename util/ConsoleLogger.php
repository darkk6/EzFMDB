<?php
class ConsoleLogger{
	
	public function __construct(){}
	
	/* 會在結尾自動加上 \n */
	public function log($str=""){
		print($str."\n");
	}
}
?>