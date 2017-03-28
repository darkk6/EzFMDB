# EzFMDB
EzFMDB (Easy FileMaker DB) 是一個 Warpper Class，提供較為友善的 FileMaker PHP API 存取功能

需求：
1. PHP 5.6+
2. 啟用 PHP 的 cURL 模組
3. 將 FM_PHP_API 放入 fm_api/ 資料夾中(參考裡面的說明)

---

### ★查詢參數 (_Query parameter_)
使用 EzFMDB 進行 select , update , delete 時，都支援使用**查詢參數**，查詢參數為一連串的參數組成。  
查詢參數分為四種內容：
1. WHERE 條件，關鍵字串為 **WHERE** 或 **OMIT**
2. ORDER 條件，關鍵字串為 **ORDER BY**
3. LIMIT 條件，關鍵字串為 **LIMIT**
4. 過濾字元，**最後一個參數** 若為 boolean 則代表是否過濾特殊字元 [true]

LIMIT 條件則限制以字串方式指定，語法： **LIMIT** skip _[,max]_
- skip 為要跳過的資料筆數 (0 代表不跳過)
- max 為最多取出幾筆資料 (不寫代表不限制)

WHERE 與 ORDER 條件有兩種使用方法：
1. **字串型**：直接將條件寫在參數字串內，會自動解析其內容(條件)
2. **陣列型**：接在關鍵字串後的**第一個參數**代表其內容(條件)
```php
	/* WHERE 字串型範例 : 條件間僅能以 AND 做為區隔 */
	/* 支援運算子為 : = , < , > , <= , >=           */
	$result = $db->delete("layout","WHERE name='filemaker' AND `The Version`<13");
	
	/* WHERE 陣列型範例 : 若欄位名稱或內容含有 AND 字樣，請使用陣列型以免解析錯誤 */
	/* 注意運算子 不加等號、單等號、雙等號 的差異 */
	$result = $db->delete("layout",
			"WHERE", array( "name"=>"filemaker" , "The Version"=>"<13" )
		);
	
	/* ORDER 字串型範例 */
	$result = $db->delete("layout","ORDER BY name ASC, `The Version` DESC");
	
	/* ORDER 陣列型範例 : 若欄位名稱含有逗號，請使用陣列型以免解析錯誤 */
	$result = $db->delete("layout",
		"ORDER BY", array("name"=>"ASC", "The Version"=>"DESC")
	);
```

##### ☆WHERE 條件
- 此條件可以出現多次，EzFMDB 會**依照出現的先後順序**進行資料的篩選
- 此條件指定方式與 FileMaker 的 Perform Find 相同，並無 not 運算
- 使用 WHERE 時是 **從所有紀錄中** 取出資料(***並非已找到的資料中篩選***)
- 使用 OMIT 則**從已經找到的資料列中**進行移除。
  
| idx |name  |gender|school|age|  
|:---:|:----:|:----:|:----:|:-:|  
|1    |A     |1     |NCKU  |13 |  
|2    |B     |0     |NTU   |9  |  
|3    |C     |1     |FHSH  |8  |  
|4    |D     |1     |MIT   |31 |  
|5    |E     |0     |NCKU  |5  |  
|6    |darkk6|0     |NCKU  |20 |  
  
```php
	$r = $db->delete("layout",
		"WHERE", array("age"=>">10"),		//找出 idx=1,4,6
		"WHERE school='NCKU' AND gender=0", //找出 idx=5,6
		"OMIT name='darkk6'"				//從已找到的 idx =1,4,5,6 移除 6
	);	//最後結果為找到 idx = 1,4,5 的三筆資料
```
1. 先在所有資料中找出 age > 10 的紀錄
2. 同樣**在所有資料中**找出 school = 'NCKU' 且 gender = 0 的紀錄
3. 在**上述已找到**的資料中，移除 name = 'darkk6' 紀錄
4. 將這些資料刪除 ( idx=1,4,5 三筆 )

---

# Usage

### 建立 EzFMDB 物件
```php
	/**DataBase_Name 若有中文，請用 UTF-8 編碼 **/
	//登入帳號為 admin , 沒登入密碼
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name");
	//沒設定密碼的帳號
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name","UserName");
	//使用特定帳號密碼登入
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name","UserName","Password");
```

### 物件設定
**setCastResult(_$boolean_)** : 傳回資料是否進行型別轉換 [true]
- FM API 傳回資料原本都是字串

**setCastTimesToInt(_$boolean_)** : 若資料格式為時間格式時，是否轉為秒數(int) [false]
- 作用於 date, time, timestamp 這些欄位
- 若欄位原本為空，在啟用此設定時會得到 -1

**setConvertTimesFormat(_$boolean_)** : 是否將時間格式轉換為指定的格式 [false]
- 作用於 date, timestamp 這些欄位
- date FM 格式原本是 mm/dd/yyyy , 轉為 yyyy/mm/dd
- timestamp 日期部分格式亦同

**setDebug(_$param_)** : 設定除錯模式
- $param 可為 true/false 或 logger 物件 , 用以指定除錯輸出或改變 logger 物件
- 傳入的 logger 物件必須要有一個 public function log($string) 方法
  
#### getFileMaker() 
**@return** _FileMaker_  
取得 FileMaker PHP 的原生物件，可以直接存取其功能。
```php
	$fm = $db->getFileMaker();
	$cmd = $fm->newFindAllCommand(...);
	...
```
  
### getDatabases() 
**@return** _array( string )_  
取得該主機上，使用同樣帳號密碼可以存取的 Database 名稱
```php
	$list = $db->getDatabases();
```

### isError(_$object_) 
**@return** _int_  
  
| 傳回值| 意義                 |  
|:-----:|:---------------------|  
| 0     | 並非錯誤物件         |  
| 1     | FileMaker_Error 物件 |  
| 2     | EzFMDB_ERR 物件      |  
  
判斷物件是否為 FileMaker_Error 或 EzFMDB_ERR 的錯誤物件
```php
	$cmd = $db->delete("Layout");
	if( $db->isError($cmd) ){
		echo "發生錯誤";
	}
```
  
### getLastError() 
**@return** _FileMaker_Error_  
取得上一個錯誤物件，若沒有則傳回 null
```php
	$err = $db->getLastError();
```
  
### getErrInfo(_$obj [,$json]_) 
**@param** $obj 要轉換的 FileMaker_Error 物件  
**@param** $json 是否以 json string 形式傳回 [true]  
**@return** _EzFMDB_ERR_  
將 FileMaker_Error 轉為 EzFMDB_ERR，根據 $json 參數，可能傳回 _array_ 或 _string_ 或 **null**
```php
	//假設發生 105 錯誤
	$res = $db->getFileMaker()->getLayout("NotExistLayout");
	
	$err = $db->getErrInfo($res);
	// 傳回 {"ErrCode":105,"ErrMsg":"Layout is missing"}
	
	$err = $db->getErrInfo($res,false);
	// 傳回 array( "ErrCode"=>105 , "ErrMsg"=>"Layout is missing" )
```
[FileMaker Error Code Reference](http://help.filemaker.com/app/answers/detail/a_id/10790/~/filemaker-pro-error-code-reference-guide)  
  
### getLayouts()
**@return** _array( string )_  
取得此資料庫的 layout 列表
```php
	$list = $db->getLayouts();
```

### getScripts() 
**@return** _array( string )_  
取得此資料庫的 script 列表
```php
	$list = $db->getScripts();
```

### getFields(_$layout_) 
**@return** _array( FieldInfo ) / EzFMDB_ERR_  
取得指定 layout 中所有的欄位資訊 FieldInfo 結構如下  
- name : 名稱  
- repetition : repetition 數量  
- type : 欄位型態 (text,number,date,container...)  
- class : 欄位類別 (normal,calculation,summary)  
- global : 是否為 global field  
- auto_enter : 是否有自動輸入的資料
```php
	$fieldsInfo = $db->getFields("LayoutName");
```

### getResult(_$fmResultObj_)
**@return** _array( data ) / NULL_  
傳入 FileMaker_Result 物件，傳回 Record 的[資料陣列(RecData)][1]
```php
	$dataSet = $db->getResult($fmResult);
	/*
		$dataSet = array(
			array(			// 一筆資料一個 array
				"fm_recid" => Record_ID,
				"fieldName1" => record_value1,
				"fieldName2" => "record_value2",
				"fieldWithRepetition" => array(
							"Data1" , "Data2"....
					),
				....
			),
			array(....),
			....
		)
	*/
```
[1]: 所有透過EzFMDB 取出的 Record 資料皆為這種形式  
  
### runScript(_$layout, $script [,$params [, ...] ]_) 
**@param** $layout 要執行 script 的 layout 名稱  
**@param** $script 要執行的 script 名稱  
**@param** $param 執行 Script 的參數，給予多個參數會自動以 \r (¶) 連接所有參數再傳遞給 Script  
**@return** _TRUE / EzFMDB_ERR_ 成功傳回 TRUE 否則傳回 EzFMDB_ERR  
執行 Script ， FM Script 的 Exit Script[] 的傳回資料是無法遞給 PHP 的
```php
	$isSuccess = $db->runScript("LayoutName","ScriptName");
	$isSuccess = $db->runScript("LayoutName","ScriptName","param1");
	$isSuccess = $db->runScript("LayoutName","ScriptName","arg1","arg2","arg3");
	$isSuccess = $db->runScript("LayoutName","ScriptName","data1\rdata2\rdata3");
```
  
---
---
  
### 存取資料時，該 layout 上必須要有相對應的欄位 (包含 Repetition)，否則會傳回 Field Missing  
---
  
### select(_$layout , $fields [, **QueryParameters** ]_) 
**@param** $layout 指定的 layout 名稱  
**@param** $fields 可為字串(逗號分隔)或陣列指定每個欄位名稱。  
**@QueryParameters** 參考 **查詢參數**  
**@return** _array( RecData ) / EzFMDB_ERR_  
對指定 layout 取得資料(根據 QueryParameters 設定)  
  
| idx |name  |gender|school|age|The Code|  
|:---:|:----:|:----:|:----:|:-:|:------:|  
|1    |A     |1     |NCKU  |1  |33      |  
|2    |B     |0     |NTU   |9  |445     |  
|3    |C     |1     |FHSH  |8  |3       |  
|4    |D     |1     |MIT   |31 |423     |  
|5    |E     |0     |NCKU  |5  |76      |  
|6    |darkk6|0     |NCKU  |20 |234     |  
  
```php
	//使用字串選擇欄位(不設任何選取和排序等條件)
	$r = $db->select("LayoutName" , "name,`The Code`");
	
	//使用陣列選擇欄位，若欄位名稱包含逗點，請用陣列方式
	$r = $db->select("LayoutName" , array("name","The Code") );

	//所有欄位
	$r = $db->select("LayoutName" , "*" );
	
	//只選出 fm_recid
	$r = $db->select("LayoutName" , "" );
	
	//組合實例
	$r = $db->select("LayoutName","*",
		"WHERE", array("age"=>"<10"),		//找出 1,2,3,5 ; Found set [1,2,3,5]
		"WHERE school='NCKU' AND gender=0", //找出 5,6     ; Found set [1,2,3,5,6]
		"OMIT name='darkk6'",				//隱藏 6       ; Found set [1,2,3,5]
		"ORDER BY age ASC",					//根據 age 升冪排序 [1,5,3,2]
		"LIMIT 1,2"							//跳過 1 個，只取 2 個 [5,3]
	);
```

### update(_$layout , $fvPair  [, **QueryParameters** ]_)
**@param** $layout 指定的 layout 名稱  
**@param** $fvPair 可為字串或陣列，要寫入的欄位以及其對應的內容  
**@QueryParameters** 參考 **查詢參數**  
**@return** _int / EzFMDB_ERR / array( EzFMDB_ERR )_  
修改指定條件下的所有紀錄，若執行成功，會傳回成功更新的 Record 數量。$fvPair 格式和 RecData 相同，支援 Repetition
```php
	//將所有 Record 的 name 設為 darkk6 , "The Code" 欄位設為 0
	//注意：字串型不支援指定 repetition field (只會寫入第一個)
	$r = $db->update("LayoutName" , "name='darkk6' , `The Code`=0");
	
	//若欄位名稱內含有逗點，請使用陣列
	$r = $db->update("LayoutName" , 
				array(
						"name"=>"darkk6'",
						//repField[2] 不變 , 只更新 repField[1],repField[3]
						"repField" => array( 0=>"Rep1_Data" , 2=>"Rep3_Data" ),
						"The Code"=>"0"
				)
			);
```

### updateByRecID(_$layout, $fvPair, $rec_id [,$doEscape=true_)
**@param** $layout 指定的 layout 名稱  
**@param** $fvPair 參考 update() 的說明  
**@param** $rec_id 內部紀錄 ID , fm_recid  
**@param** $doEscape 是否對內容進行特殊字元跳脫  
**@return** _TRUE / EzFMDB_ERR_  
更新指定 Rec_ID 的紀錄，修改成功傳回 TRUE
```php
	$r = $db->updateByRecID("LayoutName","name='darkk6',`The Code`=0",$fm_recid);
	$r = $db->updateByRecID("LayoutName",array( "name"=>"darkk6" ),$fm_recid);
```

### delete(_$layout [, **QueryParameters** ]_)
**@param** $layout 指定的 layout 名稱  
**@QueryParameters** 參考 **查詢參數**  
**@return** _int / EzFMDB_ERR / array( EzFMDB_ERR )_  
刪除指定條件下的所有紀錄，若執行成功，會傳回成功刪除的 Record 數量
```php
	$r = $db->delete("LayoutName");
	$r = $db->delete("LayoutName","WHERE",array("name"=>"darkk6","The Code"=>"0"));
```

### deleteByRecID(_$layout, $rec_id [,$doEscape=true]_)
**@param** $layout 指定的 layout 名稱  
**@param** $rec_id 內部紀錄 ID , fm_recid  
**@param** $doEscape 是否對內容進行特殊字元跳脫  
**@return** _TRUE / EzFMDB_ERR_  
刪除指定 RecID 紀錄，刪除成功傳回 TRUE
```php
	$r = $db->deleteByRecID("LayoutName",$fm_recid);
```

### insert($layout,$fvPair, $doEscape=true)
**@param** $layout 指定的 layout 名稱  
**@param** $fvPair 僅有陣列型用法，請參考 update() 的說明  
**@param** $doEscape 是否對內容進行特殊字元跳脫  
**@return** _TRUE / EzFMDB_ERR_  
新增成功傳回 TRUE
```php
	$r = $db->insert("LayoutName",array( "name"=>"darkk6" ));
```