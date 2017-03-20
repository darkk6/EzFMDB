<?php
/****************************************************/
/**************** Some configuration ****************/
/****************************************************/




/*****************************************/
/**************** Utility ****************/
/*****************************************/

//===== 取得 FM 指定格式的 date/time 格式 ====
	
	//func_get_arg ,  func_num_args()
	//strtotime 支援 yyyy/mm/dd 和 mm/dd/yyyy , dd-mm-yyyy
	function fmDate($time=NULL){
		return date("m-d-Y",inner_getTime($time));
	}
	function fmTime($time=NULL){
		return date("H:i:s",inner_getTime($time));
	}
	function fmTimestamp($time=NULL){
		return date("m-d-Y H:i:s",inner_getTime($time));
	}
	function inner_getTime($time){
		if( is_null($time) ) return time();
		if( is_string($time) ) return strtotime($time);
		if( is_int($time) ) return $time;
	}
?>