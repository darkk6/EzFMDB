<?php
/*********************************************
 *
 * Interface for FileMaker PHP API - by darkk6
 *
 * 更簡單操作 FileMaker PHP API 的 wrapper class
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.0.0
 *
 * @License GPLv3
 *
**********************************************/
	require_once('util/Util.php');
	require_once('fm_api/FileMaker.php');
	
	define("NOT_ERR",0);
	define("FM_ERR",1);
	define("EZFMDB_ERR",2);
	
	class EzFMDB {
		
	/*** 常數定義 ***/
		const SORTTYPE = array("ASC"=>FILEMAKER_SORT_ASCEND , "DESC"=>FILEMAKER_SORT_DESCEND);
		const LTET = '≤';
		const GTET = '≥';
		const NEQL = '≠';
		const CART = '¶'; 				//carriage return symbol
	
	/*** private members ***/
		private $_fm, 					//存放 FM API 物件
				$_host="",				//是否啟用除錯模式
				$_debug=true,			//是否啟用除錯模式
				$_logger=NULL,			//存放用於除錯的物件
				$_lastError=NULL;		//存放上一個錯誤物件 (FileMaker_Error)
		
		//屬於設定類型的
		private $_noCastResult=false,		//傳回資料強制以 string 呈現 (除了 fm_recid 外)
				$_castTimesToInt=false,		//時間類型(date,time,timestamp) 強制轉為 int
				$_convertTimesFormat=false,	//時間類型(date,time,timestamp) 轉為 yyyy/mm/dd HH:mm:ss 格式(字串)
				$_getContainerWithUrl=false;//取得 container url 時，是否包含前面的網址( false 會由 /fmi/... 開始， true 則為 http(s)://... 開頭)
		
		private $_errorAsString=true;		//傳回錯誤的時候預設為哪種
		
		
	/*=================== @BLOCK Constructor 建構子 ===================*/
		/**
		 *  @param [in] $db_url 		資料庫位置，必須為 http:// 或 https:// 開頭
		 *  @param [in] $db_name 		資料庫名稱 , 若為中文必須使用 UTF-8 編碼
		 *  @param [in] $db_username 	使用者名稱 (預設 "admin")
		 *  @param [in] $db_pswd 		使用者密碼，若沒設定用空字串 (預設 "")
		 */
		public function __construct($db_url,$db_name,$db_username="admin",$db_pswd="") {
			$this->doCheckRequire();
			$this->_fm = new FileMaker( $db_name, $db_url, $db_username, $db_pswd );
			$this->_host = $db_url;
		}
	
	/*=================== @BLOCK public methods for common use ===================*/
		public function setCastResult($val){
			$this->_noCastResult = !boolval($val);
		}
		public function setCastTimesToInt($val){
			$this->_castTimesToInt = boolval($val);
		}
		public function setConvertTimesFormat($val){
			$this->_convertTimesFormat = boolval($val);
		}
		public function setContainerWithURL($val){
			$this->_getContainerWithUrl = boolval($val);
		}
		public function setErrorAsString($val){
			$this->_errorAsString = boolval($val);
		}
		
		
		/**
		 *  @brief 		取得原 FileMaker 物件
		 *  @details 	取得原 FileMaker 物件
		 */
		public function getFileMaker() {
			return $this->_fm;
		}
		
		/**
		 *  @brief 取得 Container 的資料
		 *  
		 *  @param [in] $url 目標 Contianer 的 Data URL
		 *  @return 
		 */
		public function getContainerData($url){
			return $this->_fm->getContainerData($url);
		}
				
		/**
		 *  @brief 取得此 FileMaker Server 中，使用相同的帳號和密碼可以存取的 Database 名字
		 *  @return Databases 名字(array of string) 或 FileMaker_Error
		 *
		 *  @details 可以在建立物件後透過此方法找出其他能 access 的 databases
		 */
		public function getDatabases(){
			return $this->_fm->listDatabases();
		}
		
		/**
		 *  @brief 指定除錯狀態
		 *  
		 *  @param [in] $enableOrLogger Logger 物件或 true/false 代表啟用與否
		 *  
		 *  @details 若參數為 true/false 則只會改變 debug 狀態，若為含有 log method 的物件，則會改變 logger 物件並將 debug 設為 true
		 */
		public function setDebug($enableOrLogger){
			if(is_bool($enableOrLogger)){
				//單純切換啟用狀態 , 若目前的 logger 不是 null 則切換狀態，否則為 false
				$this->_debug = is_null($this->_logger) ? false : $enableOrLogger;
			}else if( is_object($enableOrLogger) && method_exists($enableOrLogger,"log") ){
				//若這是一個物件
				$this->_debug = true;
				$this->_logger = $enableOrLogger;
			}else{
				$this->_debug = false; $this->_logger = NULL;
			}
		}
		
		/**
		 *  @brief 取得上一個發生的錯誤
		 *  
		 *  @return 上一個錯誤物件 FileMaker_Error
		 *  
		 *  @details 取得上一個發生的錯誤
		 */
		public function getLastError(){
			return $this->_lastErrorObj;
		}
		
		/**
		 *  @brief 取得錯誤資訊
		 *  
		 *  @param [in] $errObj 錯誤物件 , 或錯誤物件陣列 FileMaker_Error
		 *  @param [in] $json 	是否編碼為 json string (預設 true)
		 *
		 *  @return
		 * 		代表這個錯誤的 代碼(ErrCode) 以及 訊息(ErrMsg) , 若傳入的並非 FileMaker_Error 或其陣列，將會傳回 NULL
		 * 		若指定 $json=false , 則傳回內容為陣列 , 否則為 json string
		 *
		 */
		public function getErrInfo($errObj , $json=NULL ){
			if( $json===NULL ) $json=$this->_errorAsString;
			
			$errType = $this->isError($errObj);
			if( $errType==EZFMDB_ERR ){
				return is_string($errObj) ? ( $json ? $errObj : json_decode($errObj,true) ) : ( $json ? json_encode($errObj) : $errObj );
			}else if( $errType == FM_ERR ){
				$result = array( "ErrCode"=>$errObj->code , "ErrMsg"=>$errObj->getMessage() );
				return $json ? json_encode($result) : $result;
			}else if( is_array($errObj) ){
				$tmp = array();
				foreach($errObj as $err){
					$errType=$this->isError($err);
					if( $errType == NOT_ERR ) continue;
					else if( $errType==EZFMDB_ERR ){
						if( is_string($err) ) array_push($tmp,json_decode($err,true));
						else array_push($tmp,$err);
					}else if( $errType==FM_ERR ){
						array_push($tmp,array("ErrCode"=>$err->code,"ErrMsg"=>$err->getMessage()));
					}
				}
				return $json ? json_encode($tmp) : $tmp;
			}
			return NULL;
		}
		
		/**
		 *  @brief 檢查傳入物件是否為 Error
		 *  
		 *  @param [in] $obj 要檢驗的物件
		 *  @return 該物件是否為 Error , NOT_ERR:不是 , FM_ERR:為 FileMaker_Error , EZFMDB_ERR:為 getErrInfo 得到的 Error Info
		 *  
		 *  @details 同時可檢驗 FileMaker_Error 以及透過 getErrInfo 取得的結果
		 */
		public function isError($obj){
			if( FileMaker::isError($obj) ) return FM_ERR;
			// 透過 getErrInfo 取得的資料也要能判斷
			if( is_string($obj) ){
				if( strpos($obj,"ErrCode")===FALSE || strpos($obj,"ErrMsg")===FALSE ) return NOT_ERR;
				$tmp = json_decode($obj,true);
				if( is_array($tmp) && count($tmp)==2 ) return EZFMDB_ERR;
				return NOT_ERR;
			}else
				if( is_array($obj) && count($obj)==2 && array_key_exists("ErrMsg",$obj) && array_key_exists("ErrCode",$obj) ) return EZFMDB_ERR;
			return NOT_ERR;
		}
		
		
		public function log($methodName,...$data){
			if(!$this->_debug) return;
			if($this->_logger==null) return;
			
			$msg=json_encode($data);
			
			if(strpos($methodName,"EzFMDB::")===0) $methodName = str_replace("EzFMDB::","",$methodName);
			
			$this->_logger->log( sprintf("[EzFM @ %s] : %s",$methodName,$msg) );
		}
		
	/*=================== @BLOCK public methods to FileMaker(Not databases) ===================*/
		/**
		 *  @brief 取得此 databases 中所有的 layout 名字
		 *  @return 所有 layout 的名字(array of string) 或 FileMaker_Error
		 */
		public function getLayouts(){
			return $this->_fm->listLayouts();
		}

		/**
		 *  @brief 取得此 databases 中所有的 script 名字
		 *  @return 所有 script 的名字(array of string) 或 FileMaker_Error
		 */
		public function getScripts(){
			return $this->_fm->listScripts();
		}
		
		/**
		 *  @brief fmResultToArray 的 public 版本，參考 fmResultToArray 說明
		 */
		public function getResult($fmResult,$fieldArray=NULL,$rec_id_only=false){
			return $this->fmResultToArray($fmResult,$fieldArray,$rec_id_only);
		}
		
		
		public function getFields($layout){
			if( empty($layout) ) throw new Exception('Layout name is required');
			$layoutObj = $this->_fm->getLayout($layout);
			if($this->isError($layoutObj)){
				$this->_lastError = $layoutObj;
				$return = $this->getErrInfo($layoutObj);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			$result = array();
			$fields = $layoutObj->getFields();
			foreach($fields as $f){
				$tmp = array();
				$tmp["name"] = $f->getName();
				$tmp["repetition"] = $f->getRepetitionCount();
				$tmp["type"] = $f->getResult();
				$tmp["class"] = $f->getType();
				$tmp["global"] = $f->isGlobal();
				$tmp["auto_enter"] = $f->isAutoEntered();
				array_push($result,$tmp);
			}
			return $result;
		}
		
		/**
		 *  @brief 執行 Script
		 *  
		 *  @param [in] $layout 要在哪個 layout 執行此 Script
		 *  @param [in] $scriptName 要執行的 Script 名稱
		 *  @param [in] $params 傳遞給 Script 的參數(多個會自動以 \r 串接)
		 *  @return 成功將傳回 true , 否則為 EZFMDB_ERR
		 */
		public function runScript( $layout, $scriptName,...$params) {
			if( empty($layout) ) throw new Exception('Layout name is required');
			if( empty($scriptName) ) throw new Exception('Script name is required');
			
			if( count($params)==0 ) $params=null;
			else if( count($params)==1 ) $params=$params[0];
			else $params=implode("\r",$params);
			
			$cmd = $this->_fm->newPerformScriptCommand( $layout, $scriptName, $params );
			if( $this->isError($cmd) ){
				$this->_lastError = $cmd;
				$return = $this->getErrInfo($cmd);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$res = $cmd->execute();
			if( $this->isError($res) ){
				$this->_lastError = $res;
				$return = $this->getErrInfo($res);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			/* 紀錄 */ $this->log( __METHOD__ ." #".__LINE__ ,$scriptName,$params);
			$this->_lastError = null;
			return TRUE;
		}
	
	/*=================== @BLOCK public methods to FileMaker (存取資料庫) ===================*/
		/**
		 *  @brief 取得指定資料
		 *  
		 *  @param [in] $layout Layout 名稱
		 *  @param [in] $fields 要選擇的欄位名稱，可以使用 string array 或 字串。
		 *  					使用字串時， "*" 代表選擇所有欄位 , 採用逗號分隔，若欄位名稱含有空格，使用 `` 包起來。
		 *  					可以使用空字串 "" 代表只選出 FileMaker 內部用的 record_id (fm_recid)
		 *  					但欄位名稱不可有逗號，若需要，請使用 string array 形式
		 *  					
		 *  @return 查詢後的結果(會多帶一個 fm_recid 欄位) 或 EzFMDB_ERR , 找不到資料會傳回空陣列
		 *  
		 *  @note 	1. 傳回的資料順序不會按照 $fields 所給的順序排序
		 *  		2. 傳回資料會根據設定轉型 , 時間、日期格式若指定要轉為 int 且原內容為空時，會傳回 -1
		 *  
		 *  @details 其餘參數為 WHERE , OMIT , ORDER BY , LIMIT 參考說明文件或 parseQueryRequest() 的說明
		 */
		public function select($layout,$fields){
			if( empty($layout) ) throw new Exception('Layout name is required');
			
			$argc=func_num_args();
			$args=func_get_args();
			
			$fieldArray = NULL;	//若為 NULL 代表選出所有
			$rec_id_only = false;
			if( is_array($fields) ){
				$fieldArray=$fields;
			}else if( is_string($fields)){
				if($fields==""){
					$rec_id_only=true;
				}else if($fields!="*"){
					$tmp = explode(",",$fields);
					if( is_array($tmp) ){
						$fieldArray = array();
						foreach($tmp as $ipt){
							$fielaName = $this->preg_tripSQL($ipt,"`");
							array_push($fieldArray, $fielaName );
							if( $fielaName=="*" ){
								$fieldArray=NULL;
								break;
							}
						}
					}
				}
			}else throw new Exception('Invalid field assign.');
			
			$query = $this->parseQueryRequest(array_slice($args,2));
			$doEscape = $query["ESCAPE"];
			$hasWhere = is_array($query["WHERE"]);
			
			// 由於 FileMaker_Command_CompoundFind 必須要有 FindRequest , 因此沒提供條件時，直接用 FileMaker_Command_FindAll
			$cmd = ( $hasWhere ) ? $this->_fm->newCompoundFindCommand($layout) :
									$this->_fm->newFindAllCommand($layout) ;
			
			if( $this->isError($cmd) ){
				$this->_lastError = $cmd;
				$return = $this->getErrInfo($cmd);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			//指定條件
			if( $hasWhere ){
				foreach ($query["WHERE"] as $priority => $request){
					$findReq = $this->_fm->newFindRequest($layout);	//只要上面 $cmd 沒問題，這邊應該就不會有問題了
					$findReq->setOmit($request["OMIT"]);
					foreach($request["FACTOR"] as $field => $value ){
						//因為 where 條件( $value 的部分 )可能出現 > < , 因此要註記是 findCmd
						$field = ( $doEscape ? $this->fm_escape( $field        ) : $field );
						$value = ( $doEscape ? $this->fm_escape( $value , true ) : $value );
						$findReq->addFindCriterion( $field, $value );
					}
					$cmd->add( $priority+1 , $findReq );
				}
			}
			//指定排序方式
			if( is_array($query["ORDER"]) ){
				$priority = 1;
				foreach($query["ORDER"] as $field => $sortType ){
					$cmd->addSortRule($field, $priority, self::SORTTYPE[$sortType]);
					$priority++;
				}
			}
			//設定 Range
			if( is_array($query["LIMIT"]) ){
				if($query["LIMIT"][1]==-1) $cmd->setRange($query["LIMIT"][0]);
				else $cmd->setRange($query["LIMIT"][0],$query["LIMIT"][1]);
			}
			
			/* 紀錄 query 資料 */ $this->log( __METHOD__ ." #".__LINE__ ,array( "layout"=>$layout , "fields" => $fieldArray ),$query);
			
			$fmResult = $cmd->execute();
			$isError = $this->isError( $fmResult ); //這個不可能出現 EZFMDB_ERR
			if ( $isError == FM_ERR ){
				$this->_lastError = $fmResult;
				if( $fmResult->code ==401 ) return array();//若是指定條件找不到資料，傳回空陣列(但還是要記錄 last error)
				$return = $this->getErrInfo($fmResult);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$this->_lastError = null;
			//將資料配置為陣列
			return $this->fmResultToArray($fmResult,$fieldArray,$rec_id_only);
		}
	
		/**
		 *  @brief 根據條件更新資料
		 *  
		 *  @param [in] $layout 指定的 layout 名稱
		 *  @param [in] $fvPair 要修改的 欄位-資料 內容，可以有兩種形式：
		 *  					1. 字串，採用 field1=value1, field2=value2, ... 的形式，如同 SQL，此方法不支援指定具有 repetition 的欄位(只會更新第一個)
		 *  					2. 陣列，key 為欄位名稱， value 為資料內容 , 若該欄位有 repetition , 則 value 可以是陣列
		 *  							 其 value 陣列的 key 是 repetition number(從 0 開始)，不在裡面的會跳過
		 *  					
		 *  					其餘條件參數和 select 相同
		 *  					
		 *  @return 傳回數字代表成功影響幾筆資料，或完全失敗傳回 EZFMDB_ERR
		 *  		若有成功，中途也有失敗，最後可以透過 getLastError 取得 EZFMDB_ERR array
		 */
		public function update($layout,$fvPair){
			if( empty($layout) ) throw new Exception('Layout name is required');
			
			$argc=func_num_args();
			$args=func_get_args();
			
			if( is_string($fvPair) ){
				$arr = explode(",",$fvPair);
				$fvPair = array();
				if( !is_array($arr) ) throw new Exception('Invalid field-value string assign.');
				foreach($arr as $item){
					$tmp = explode("=",$item);
					$key = $this->preg_tripSQL($tmp[0],"`");
					$val = $this->preg_tripSQL($tmp[1],"'");
					$fvPair[$key]=$val;
				}
			}else if( !is_array($fvPair) ) throw new Exception('Invalid field-value pair assign.');
			
			/* 紀錄 query 資料 */ $this->log( __METHOD__ ." #".__LINE__ ,"Calling internal_select");
			$selectResult = $this->internal_call_select($layout,"",array_slice($args,2));
			
			$updateCount=0;
			$errList=array();
			if($this->isError($selectResult)){
				//如果這裡發生錯誤，代表是 select 發生錯誤，此時 lastError 就已經是原本的錯誤了 , 傳回的錯誤是 EZDBFM_ERR
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$selectResult);
				return $selectResult;
			}else{
				if( count($selectResult)==0 ) return 0;
				
				$lastArg = $args[count($args)-1];
				$doEscape = ( is_bool($lastArg) ? $lastArg : true);
				
				/* 紀錄準備開始 */ $this->log( __METHOD__ ." #".__LINE__ ,array("layout"=>$layout));
				foreach($selectResult as $record){
					//呼叫 updateByRecID 處理
					$res = $this->updateByRecID($layout,$fvPair,$record['fm_recid'],$doEscape);
					if( $res===TRUE ) $updateCount++;
					else array_push($errList,$res);
				}
			}
			
			$this->_lastError = (count($errList)>0 ? $errList : null);
			
			if( $updateCount>0 ) return $updateCount;
			else if(count($errList)>0) return $errList;//若影響列數為 0 ，且有錯誤，則傳回 errList
			return 0;//沒有錯誤也沒有影響列數，傳回 0
			
		}
		
		/**
		 *  @brief 根據 Record_ID 更新資料
		 *  
		 *  @param [in] $layout 指定的 layout 名稱
		 *  @param [in] $fvPair 資料陣列或字串，參考 update
		 *  @param [in] $rec_id 目標 Record_ID
		 *  @param [in] $doEscape 是否進行文字的跳脫
		 *  @return 成功傳回 true , 否則傳回 EZFMDB_ERR
		 *  
		 *  @details update 是透過 select 取得資料後，呼叫此 method 處理每一個 record_id 
		 */
		public function updateByRecID($layout, $fvPair, $rec_id, $doEscape=true){
			if( empty($layout) ) throw new Exception('Layout name is required');
			if( !is_numeric($rec_id) ) throw new Exception('Invalid record ID.');
			
			//同樣的處理 $fvPair 步驟 , 若是透過 update 呼叫 這邊是會略過的
			if( is_string($fvPair) ){
				$arr = explode(",",$fvPair);
				$fvPair = array();
				if( !is_array($arr) ) throw new Exception('Invalid field-value string assign.');
				foreach($arr as $item){
					$tmp = explode("=",$item);
					$key = $this->preg_tripSQL($tmp[0],"`");
					$val = $this->preg_tripSQL($tmp[1],"'");
					$fvPair[$key]=$val;
				}
			}else if( !is_array($fvPair) ) throw new Exception('Invalid field-value pair assign.');
			
			$cmd = $this->_fm->newEditCommand( $layout , $rec_id );
			if( $this->isError($cmd) ){
				$this->_lastError = $cmd;
				$return = $this->getErrInfo($cmd);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$layoutObj = $this->_fm->getLayout($layout);
			foreach($fvPair as $f => $v ){
				
				$f = ( $doEscape ? $this->fm_escape( $f ) : $f );
				
				$fieldObj = $layoutObj->getField($f);	//等一下用來計算 repetition
				if($this->isError($fieldObj)){
					$this->log(__METHOD__ ." #".__LINE__,$this->getErrInfo($fieldObj));
					continue;
				}
				$repCount = $fieldObj->getRepetitionCount();
				$vCount = count($v);
				//若這個 Field 沒有 repetition , 只放入第一個元素
				if( is_array($v) && $vCount>0 && $repCount==1 ) $v=$v[0];
				
				if(is_array($v)){
					for($ridx=0;$ridx<$repCount;$ridx++){
						if( array_key_exists($ridx,$v) ){
							$v[$ridx] = $this->check_DateTimeField($fieldObj,$v[$ridx]);
							$v[$ridx] = ( $doEscape ? $this->fm_escape( $v[$ridx] ) : $v[$ridx] );
							$cmd->setField( $f , $v[$ridx] , $ridx );
						}
					}
				}else{
					$v = $this->check_DateTimeField($fieldObj,$v);
					$v = ( $doEscape ? $this->fm_escape( $v ) : $v );
					$cmd->setField( $f , $v );
				}
				
			}
			$res=$cmd->execute();
			
			if($this->isError($res)) {
				$this->_lastError = $res;
				$return = $this->getErrInfo($res);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$this->_lastError = null;
			/* 紀錄結果 */ $this->log( __METHOD__ ." #".__LINE__ ,array("layout"=>$layout,"recid"=>$rec_id),$fvPair);
			return TRUE;
		}
	
		/**
		 *  @brief 根據條件刪除資料
		 *  
		 *  @param [in] $layout 指定的 layout 名稱
		 *  					其餘條件參數和 select 相同
		 *  					
		 *  					
		 *  @return 傳回數字代表成功刪除幾筆資料，或完全失敗傳回 EZFMDB_ERR
		 *  		若有成功，中途也有失敗，最後可以透過 getLastError 取得 EZFMDB_ERR array
		 */
		public function delete($layout){
			if( empty($layout) ) throw new Exception('Layout name is required');
			
			$argc=func_num_args();
			$args=func_get_args();
			
			$deleteCount=0;
			$errList=array();
			
			/* 紀錄 query 資料 */ $this->log( __METHOD__ ." #".__LINE__ ,"Calling internal_select");
			//直接根據 WHERE 條件尋找要準備刪除的目標
			$selectResult = $this->internal_call_select($layout,"",array_slice($args,1));
			
			if($this->isError($selectResult)){
				//如果這裡發生錯誤，代表是 select 發生錯誤，此時 lastError 就已經是原本的錯誤了 , 傳回的錯誤是 EZDBFM_ERR
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$selectResult);
				return $selectResult;
			}else{
				if( count($selectResult)==0 ) return 0;
				
				$lastArg = $args[count($args)-1];
				$doEscape = ( is_bool($lastArg) ? $lastArg : true);
				
				/* 紀錄準備開始 */ $this->log( __METHOD__ ." #".__LINE__ ,array("layout"=>$layout));
				foreach($selectResult as $record){
					//呼叫 deleteByRecID 處理
					$res = $this->deleteByRecID($layout,$record['fm_recid'],$doEscape);
					if( $res===TRUE ) $deleteCount++;
					else array_push($errList,$res);
				}
			}
			
			$this->_lastError = (count($errList)>0 ? $errList : null);
			
			if( $deleteCount>0 ) return $deleteCount;
			else if(count($errList)>0) return $errList;//若刪除列數為 0 ，且有錯誤，則傳回 errList
			return 0;//沒有錯誤也沒有刪除列數，傳回 0
			
		}
	
		public function deleteByRecID($layout, $rec_id, $doEscape=true){
			if( empty($layout) ) throw new Exception('Layout name is required');
			if( !is_numeric($rec_id) ) throw new Exception('Invalid record ID.');
			
			$cmd = $this->_fm->newDeleteCommand($layout,$rec_id);
			if($this->isError($cmd)){
				$this->_lastError = $cmd;
				$return = $this->getErrInfo($cmd);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$res = $cmd->execute();

			if( $this->isError($res) ){
				$this->_lastError = $res;
				$return = $this->getErrInfo($res);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$this->_lastError = null;
			/* 紀錄結果 */ $this->log( __METHOD__ ." #".__LINE__ ,array("layout"=>$layout,"recid"=>$rec_id));
			return true;
		}
		
		/**
		 *  @brief 在 layout 中新增一筆資料
		 *  
		 *  @param [in] $layout 指定的 layout 名稱
		 *  @param [in] $fvPair 必須為陣列，key:欄位 value:資料 的資料陣列
		 *  					若要指定 repetition 欄位 value 可以是陣列，方式和 update 相同
		 *  @param [in] $doEscape 是否進行字元跳脫
		 *
		 *  @return 成功傳回 true , 否則傳回 EZFMDB_ERR
		 *  
		 *  @details 此方法會先過濾 $fvPair 中的資料，會跳過 container,calculation,summary 等欄位
		 *  		 遇到不存在的欄位或者 repetition 不正確的欄位會自動過濾
		 */
		public function insert($layout,$fvPair, $doEscape=true){
			if( empty($layout) ) throw new Exception('Layout name is required');
			if( !is_array($fvPair) ) throw new Exception('Field-Value pair array is required');
			
			$layoutObj = $this->_fm->getLayout($layout);
			if($this->isError($layoutObj)){
				$this->_lastError = $layoutObj;
				$return = $this->getErrInfo($layoutObj);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			//先取得 Field 資訊 並過濾無效 Field
			$newFvPair=array();
			$notFiels=array();
			foreach($fvPair as $f => $v){
				$f = ( $doEscape ? $this->fm_escape( $f ) : $f );
				
				$fieldObj = $layoutObj->getField($f);
				if($this->isError($fieldObj)){
					array_push($notFiels,$f);
					continue;
				}
				
				//自動跳過不能寫入的欄位
				$typeResult = $fieldObj->getResult();
				$typeType = $fieldObj->getType();
				if( $typeResult=="container" || $typeType=="summary" || $typeType=="calculation"){
					array_push($notFiels,$f);
					continue;
				}
				
				$repCount=$fieldObj->getRepetitionCount();
				
				if( is_array($v) && count($v)>0 && $repCount==1 ) $v=$v[0];
				
				if(is_array($v)){
					for($ridx=0;$ridx<$repCount;$ridx++){
						if( array_key_exists($ridx,$v) ){
							$v[$ridx] = $this->check_DateTimeField($fieldObj,$v[$ridx]);
							$v[$ridx] = ( $doEscape ? $this->fm_escape( $v[$ridx] ) : $v[$ridx] );
						}
					}
				}else{
					$v = $this->check_DateTimeField($fieldObj,$v);
					$v = ( $doEscape ? $this->fm_escape( $v ) : $v );
				}
				$newFvPair[$f]=$v;
			}
			if(count($notFiels)>0){
				/* 紀錄跳過的欄位資訊 */ $this->log( __METHOD__ ." #".__LINE__ ,"Skipped fields",$notFiels);
			}
			/* 紀錄準備新增資訊 */ $this->log( __METHOD__ ." #".__LINE__ ,array("layout"=>$layout,"data"=>$newFvPair));
			$cmd = $this->_fm->newAddCommand($layout,$newFvPair);
			if($this->isError($cmd)){
				$this->_lastError = $cmd;
				$return = $this->getErrInfo($cmd);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			$res = $cmd->execute();
			if($this->isError($res)){
				$this->_lastError = $res;
				$return = $this->getErrInfo($res);
				/* 紀錄錯誤結果 */ $this->log( __METHOD__ ." #".__LINE__ ,$return);
				return $return;
			}
			
			return TRUE;
		}
	
	/*=================== @BLOCK private methods ===================*/
		/**
		 *  @brief 檢查是否可以使用 FileMaker PHP API 前置作業
		 *  @details 檢查是否啟用 cRUL 模組以及有引入 FileMaker PHP API
		 */
		private function doCheckRequire(){
			//檢查是否有啟用 curl
			if( !function_exists( 'curl_init' ) ){
				die( '請啟用 cURL 模組 , Please enable "cURL" php module.' );
			}
			//檢查是否有引入 FileMaker API
			if( !class_exists("FileMaker") ){
				die( '請引入 FileMaker PHP API , Please include FileMaker PHP API.' );
			}
		}
		
		/**
		 *  @brief 跳脫 FileMaker 的特殊字元
		 *  @details 根據不同的 command 移除一些字元
		 */
		private function fm_escape( $input, $findCmd = false ,$editCmd = false ) {
			if (is_array($input)) return array_map( __METHOD__ ." #".__LINE__, $input );

			if ( !empty( $input ) && is_string( $input ) ) {
				$needle  = array(  '\\',  '/',  "\0",  "\n",  "\r",   "'",   '"', "\x1a",     '<',    '>', '%00');
				$replace = array('\\\\', '\/', '\\0', '\\n', '\\r', "\\'", '\\"',  '\\Z', '\<\\/', '\\/>',   '');
				
				if($editCmd){
					$needle[]  =  '*';   $needle[] = '@';
					$replace[] = '\*'; $replace[] = '\@';
				}
				
				//如果是 findCmd , 且開頭出現 < 或 >，則不要取代 < 和 >
				$findFix=null;
				if($findCmd && preg_match("/^[<>]/",$input)){
					$findFix = substr($input,0,1);
					$input = substr($input,1);
				}
				
				$input = str_replace( $needle, $replace, $input );
				if( is_string($findFix) ) $input = $findFix.$input;
			}
			return $input;
		}
		
		/**
		 *  @brief 提供內部快速呼叫 select 的 method
		 */
		private function internal_call_select($layout,$fields,$parameters){
			$newParam = array_merge( array($layout,$fields) , $parameters );
			return call_user_func_array(array($this,'select'),$newParam);
		}
		
		/**
		 *  @brief 根據設定進行資料型態轉換
		 *  
		 *  @param [in] $fmField FileMaker_Field 物件用來判斷型態
		 *  @param [in] $data 要處理的資料
		 *  @return 根據設定傳回要放入陣列的資料(轉型後)
		 */
		private function doDataTypeCast($fmField,$data){
			if($this->_noCastResult) return $data;
			if( !is_a($fmField,'FileMaker_Field') ) return $data;
			
			switch( $fmField->getResult() ){
				case "number": 	return intval($data) == $data ? intval($data) : $data;//避免數值過大的問題
				case "text": 	return is_string($data)?$data:(string)$data;
				case "container": return $this->_getContainerWithUrl ? $this->_fm->getContainerDataURL($data) : $data;
				case "date": case "time": case "timestamp":
					$is_empty=empty($data);
					$time = strtotime($data);
					if($this->_castTimesToInt) return $is_empty ? -1 : $time;
					else if($this->_convertTimesFormat){
						if($fmField->getResult()=="date") return $is_empty ? "" : date("Y/m/d",$time);
						else if($fmField->getResult()=="time") return $is_empty ? "" : date("H:i:s",$time);
						else return $is_empty ? "" : date("Y/m/d H:i:s",$time);
					}else return $data;
			}
			return $data;
		}
		
		/**
		 *  @brief 將 FileMaker_Result 轉為簡易資料陣列
		 *  
		 *  @param [in] $fmResult 要轉換的 FileMaker_Result 物件
		 *  @param [in] $fieldArray 要顯示的欄位 , NULL 代表所有欄位
		 *  @param [in] $rec_id_only 為 true 時，只會傳回 fm_recid
		 *  @return 配置好的陣列，但若 $fmResult 並非 FileMaker_Result 物件時，傳回 NULL
		 *  
		 *  @details 會根據 instance 設定是否對結果進行強制轉型
		 */
		private function fmResultToArray($fmResult,$fieldArray=NULL,$rec_id_only=false){
			if( !is_a($fmResult,"FileMaker_Result") ) return NULL;
			$resultOpt = array();
			$fields = $fmResult->getFields();
            $records = $fmResult->getRecords();
			$layoutObj = $fmResult->getLayout();
            foreach ( $records as $record ) {
				$rec_data=array();
				$rec_data['fm_recid'] = intval($record->getRecordId());
				if(!$rec_id_only){
					foreach ( $fields as $field ) {
						//檢查要取得的欄位是否有限制
						if( is_array($fieldArray) && (!in_array($field,$fieldArray)) ) continue;
						
						$fieldObj = $layoutObj->getField($field);//為了取得是否有 repetition 和其他用途
						$repCount = $fieldObj->getRepetitionCount();
						if( $repCount == 1){
							$rec_data[$field] = $this->doDataTypeCast($fieldObj,$record->getField($field));
						}else{
							$rec_data[$field] = array();
							for($i=0;$i<$repCount;$i++)
								array_push($rec_data[$field], $this->doDataTypeCast($fieldObj,$record->getField($field,$i)) );
						}
					}
				}
				array_push($resultOpt,$rec_data);
            }
			return $resultOpt;
		}
		
		/**
		 *  @brief 解析 WHERE , ORDER BY , LIMIT 使用
		 *  
		 *  @param [in] $args 透過 SELECT , UPDATE , DELETE 等指令去除 layout , fields 等參數後的參數陣列
		 *  @return WHERE , ORDER BY , LIMIT 解析結果的陣列
		 *  
		 *  @details
		 *  	若參數中有 "WHERE" , 後一個參數為 array , 則該 array 為 where 條件指令 (可有多個)
		 *  	WHERE 參數本身可以直接使用基本的 SQL 語法 (當 "WHERE" 後面的參數不是 array 時)
		 *  	如： "WHERE name='ABC' AND `the time`=1" , 會自動解析 
		 *  	※ 注意：分隔只能用 AND , 若欄位或內容含有 AND 字樣，請使用陣列版本
		 *  	
		 *  	若參數中含有 "OMIT" , 運作原理與方式和 "WHERE" 相同，只是透過這個條件找出來的是會被移除
		 * 
		 *  	WHERE 和 OMIT 可以有多個，最後結果會依照順序處理後得到
		 *  	注意：搜尋的時候 FileMaker 是搜尋所有的 Repetition , 因此這邊無法指定 Repetition (如同 Perform Find 運作方式)
		 * 
		 * 		若參數中有 "ORDER BY" , 後一個參數為 array , 則該 array 為 ORDER BY 指令
		 *  	ORDER BY 參數本身也可直接使用基本的 SQL 語法 (當 "ORDER BY" 後面的參數不是 array 時)
		 * 		如： "ORDER BY name ASC , `The Code` DESC" , 會自動解析
		 *  	
		 *  	若參數中有 "LIMIT skip[,max]" (方括號中為選擇性)，代表會限制範圍
		 *  	skip : 過略幾筆資料 (0+)
		 *  	max  : 最多取得幾筆 (-1 代表不限制)
		 *  
		 *  	最後一個參數若為 boolean , 代表是否進行 FileMaker 的字元跳脫，若沒給預設為 true
		 */
		private function parseQueryRequest($args){
			$result = array(
						"WHERE" => NULL,
						"ORDER" => NULL,
						"LIMIT" => NULL,
						"ESCAPE" => TRUE
					);
			if( !is_array($args) || count($args)<=0) return $result;
			
			//找到 WHERE , OMIT , ORDER BY , LIMIT 關鍵字的 index
			$whereIdxList = array();
			$orderIdx = -1;$limitIdx = -1;
			foreach($args as $idx => $arg){
				if( !is_string($arg) ) continue;
				// ORDER , LIMIT 參數都只找第一個
				if( preg_match("/^(WHERE|OMIT)/i",$arg) ){
					array_push($whereIdxList, array( "idx" => $idx , "type"=> preg_match("/^OMIT/i",$arg)?"OMIT":"FIND" ));
				}else if( preg_match("/^ORDER BY/i",$arg) ) $orderIdx = ($orderIdx==-1 ? $idx : $orderIdx);
				else if( preg_match("/^LIMIT/i",$arg) ) $limitIdx = ($limitIdx==-1 ? $idx : $limitIdx);
			}
			//----- 分析 WHERE
				if(count($whereIdxList)>0){
					$result["WHERE"] = array();
					foreach($whereIdxList as $wItem){
						$whereIdx = $wItem["idx"];
						$isOmit = $wItem["type"]=="OMIT" ;
						$findReq = array();
						$findReq["OMIT"]=$isOmit;
						if(is_array($args[$whereIdx+1])){
							//透過 array assign
							$findReq["FACTOR"]=$args[$whereIdx+1];
						}else if( preg_match("/^(WHERE|OMIT) /i",$args[$whereIdx]) ){
							//直接用字串，進行分析
							$str = preg_replace("/(?:WHERE|OMIT) +(.+)/i","$1",$args[$whereIdx]);
							$arr = explode("AND",$str);
							if(is_array($arr)){
								$findReq["FACTOR"]=array();
								foreach($arr as $item){
									$tmp = preg_split("/[<>=]+/",$item);
									$key = $this->preg_tripSQL($tmp[0],"`");
									$val = $this->preg_tripSQL($tmp[1],"'");
									//找出 operator
									$op = preg_replace("/[^<>=]*(<=|>=|=|<|>)[^<>=]*/","$1",$item);
									if( $op=="=" ) $op = "==";
									$findReq["FACTOR"][$key]=$op.$val;
								}
							}
						}
						array_push($result["WHERE"],$findReq);
					}
				}
			//----- 分析 ORDER BY
				if($orderIdx!=-1){
					if(is_array($args[$orderIdx+1])){
						//透過 array assign
						$result["ORDER"]=$args[$orderIdx+1];
					}else if( preg_match("/^ORDER BY /i",$args[$orderIdx]) ){
						//直接用字串，進行分析
						$str = preg_replace("/ORDER BY +(.+)/i","$1",$args[$orderIdx]);
						$arr = explode(",",$str);
						if(is_array($arr)){
							$orderPtn="/.*?(`.+`|\\S+) +(ASC|DESC).*?/i";
							$result["ORDER"]=array();
							foreach($arr as $item){
								if( preg_match($orderPtn,$item) ){
									$key = str_replace("`","",trim(preg_replace($orderPtn,"$1",$item)));//不知道為何偶爾會出現空白
									$val = strtoupper(trim(preg_replace($orderPtn,"$2",$item)));
									$result["ORDER"][$key]="$val";
								}
							}
						}
					}
				}
			//----- 分析 LIMIT
				if($limitIdx!=-1){
					$limitPtn="(\\d+)(?:\\s*,\\s*(\\d+))?\\D*";
					if( preg_match("/^LIMIT +".$limitPtn."/i",$args[$limitIdx]) ){
						$str = preg_replace("/LIMIT +(.+)/i","$1",$args[$limitIdx]);
						//直接用字串，進行分析
						$skip = intval(preg_replace("/$limitPtn/","$1",$str));
						$max = preg_replace("/$limitPtn/","$2",$str);
						$max = strlen($max)==0 ? -1 : intval($max);
						$result["LIMIT"]=array($skip,$max);
					}
				}
			
			$lastArg = $args[count($args)-1];
			if( is_bool($lastArg) ) $result['ESCAPE']=$lastArg;
			
			return $result;
		}
		
		/**
		 *  @brief 解析 SQL 語法用
		 *  
		 *  @param [in] $str 要解析的字串
		 *  @param [in] $ch 邊界字元
		 *  @return 處理後的字串
		 *  
		 *  @details 通常用於處理 `field` 或 'value' 使用 , 使其可以正確取得如：
		 *  			`The Code` = '123 45' 中的 "The Code" 和 "123 45"
		 */
		private function preg_tripSQL($str,$ch){
			$ptn = "/\\s*(?:".$ch."(.+)".$ch."|(\\S+))\\s*/";
			$repalce = "$1";
			if( strpos($str,$ch)===FALSE ) $repalce = "$2";
			return preg_replace($ptn,$repalce,$str);
		}
		
		/*
			處理 date, time 和 timestamp 的格式
		*/
		private function check_DateTimeField($fmFieldObj,$val){
			$type = $fmFieldObj->getResult();
			if( $type!="date" && $type!="time" && $type!="timestamp" ) return $val;
			
			$time = FALSE;
			if( is_int($val) ) $time = $val;
			else if( is_numeric($val) ) $time = intval($val);
			else if( is_string($val) ) $time = strtotime($val);

			if($time===FALSE) return $val;
			
			switch($type){
				case "date":
					return date("m-d-Y",$time);
				case "time":
					return date("H:i:s",$time);
				case "timestamp":
					return date("m-d-Y H:i:s",$time);
			}
			return $val;
		}
	}
?>