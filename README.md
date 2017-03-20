# EzFMDB
EzFMDB (Easy FileMaker DB) is a Warpper Class that provide more friendly coding interface.  
  
Sorry about my broken English, please see the code example for the detailed information.  
  
Require:
1. PHP 5.6+
2. Enable cURL module in PHP
3. Put FM_PHP_API lib into fm_api/ folder
  
---
  
# ★Query parameter
EzFMDB provide a series of parameter when calling select(), update(), delete() method.  
  
Query parameter have 4 parts:
1. WHERE Part, Keyword is **WHERE** or **OMIT**
2. ORDER Part, Keyword is **ORDER BY**
3. LIMIT Part, Keyword is **LIMIT**
4. Escaping，**The last parameter** means whether escape special character or not.[boolean default:true]

LIMIT Part syntax: **LIMIT** skip _[,max]_
- skip : number of record to skip (can be zero)
- max : the maximum record number to get

There are two ways to use WHERE and ORDER Part:
1. **String-type** : juset write a sql-like syntax string
2. **Array-type** : the array-type parameter right after the keyword parameter.
```php
	/* WHERE String-type  : conditions must be connected with "AND" */
	/* Supported operator :  = , < , > , <= , >=                 */
	$result = $db->delete("layout","WHERE name='filemaker' AND `The Version`<13");
	
	/* WHERE Array-type : use this way when field or value contains "AND" */
	/* Please note the difference between "=" and "==" operator */
	$result = $db->delete("layout",
			"WHERE", array( "name"=>"filemaker" , "The Version"=>"<13" )
		);
	
	/* ORDER String-type */
	$result = $db->delete("layout","ORDER BY name ASC, `The Version` DESC");
	
	/* ORDER Array-type :  use this way when field name contains "," */
	$result = $db->delete("layout",
		"ORDER BY", array("name"=>"ASC", "The Version"=>"DESC")
	);
```

##### ☆WHERE Part
- Can be assigned multi-times, EzFMDB will apply all of them with the given order.
- This is just act like "Perform Find[]" in FileMaker Script step.
- There is no "NOT" operator
- "WHERE" will search record from **"All records"** not from **"Found set"**
- "OMIT" will remove record from **"Found set"**
  
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
		"WHERE", array("age"=>">10"),		//found idx=1,4,6
		"WHERE school='NCKU' AND gender=0", //found idx=5,6
		"OMIT name='darkk6'"				//remove 6 from found set (idx=1,4,5,6)
	);
	//Finally, this will find 3 records (idx=1,4,5) and then delete it.
```
---

# Usage

### Creating EzFMDB object
```php
	/** Use UTF-8 encoding when you use Non-English characters **/
	//Login with default user name "admin" and no password
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name");
	//Login with no password account
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name","UserName");
	//Login with specific password account
	$db = new EzFMDB("http://YourHostOrIP","DataBase_Name","UserName","Password");
```

### Configure the object
**setCastResult(_$boolean_)** : Cast type when getting data. [true]
- The official FM API will return all data in string type.
- EzFMDB will cast the data type to their actual type by default.

**setCastTimesToInt(_$boolean_)** : Return unix timestamp when getting "time data".[false]
- Work with : date, time, timestamp , ( in seconds )
- If the fiels is empty , -1 will return.

**setConvertTimesFormat(_$boolean_)** : Change date time format. [false]
- Work with : date, timestamp
- FM will return date type in "mm/dd/yyyy" , set to true if you want "yyyy/mm/dd"
- The same concept in the date part of timestamp type field .

**setDebug(_$param_)** : Set debug enabled or assign logger object
- $param can be true/false or logger object
- Give true/false to enable/disable debug mode.
- Give logger object to change the logger object.
- The logger object must have a public function named "log" with one string parameter.

#### getFileMaker() 
**@return** _FileMaker_  
Get official FileMaker object to access it's feature.
```php
	$fm = $db->getFileMaker();
	$cmd = $fm->newFindAllCommand(...);
	...
```
  
### getDatabases() 
**@return** _array( string )_  
Get all database names that you can access with current account and password.
```php
	$list = $db->getDatabases();
```

### isError(_$object_) 
**@return** _int_  
  
| Return value | Meaning                |  
|:------------:|:-----------------------|  
| 0            | Not an error Object    |  
| 1            | FileMaker_Error Object |  
| 2            | EzFMDB_ERR Object      |  
  
Check given object is FileMaker_Error or EzFMDB_ERR.
```php
	$cmd = $db->delete("Layout");
	if( $db->isError($cmd) ){
		echo "Error occurred";
	}
```

### getLastError() 
**@return** _FileMaker_Error_  
Get last error object.
```php
	$err = $db->getLastError();
```

### getErrInfo(_$obj [,$json]_) 
**@param** $obj FileMaker_Error object to be convert  
**@param** $json return with json string [true]  
**@return** _EzFMDB_ERR_  
Convert FileMaker_Error into EzFMDB_ERR. This method will return array when $json = false or **null** when the given object is not a FileMaker_Error .
```php
	//Assume error 105 occurred
	$res = $db->getFileMaker()->getLayout("NotExistLayout");
	
	$err = $db->getErrInfo($res);
	// return {"ErrCode":105,"ErrMsg":"Layout is missing"}
	
	$err = $db->getErrInfo($res,false);
	// return array( "ErrCode"=>105 , "ErrMsg"=>"Layout is missing" )
```
[FileMaker Error Code Reference](http://help.filemaker.com/app/answers/detail/a_id/10790/~/filemaker-pro-error-code-reference-guide)  
  
### getLayouts()
**@return** _array( string )_  
Get layout name list from this database.
```php
	$list = $db->getLayouts();
```
  
### getScripts() 
**@return** _array( string )_  
Get script name list from this database.
```php
	$list = $db->getScripts();
```
  
### getFields(_$layout_) 
**@return** _array( FieldInfo ) / EzFMDB_ERR_  
Get Field info from this layout. Each field have these info:
- name : Name of field
- repetition : repetition count of this field
- type : field type (text,number,date,container...)
- class : field class (normal,calculation,summary)
- global : is field a global field
- auto_enter : is field set auto-enter data
```php
	$fieldsInfo = $db->getFields("LayoutName");
```
  
### getResult(_$fmResultObj_)
**@return** _array( data ) / NULL_  
Get record [RecData][1] from given FileMaker_Result object.
```php
	$dataSet = $db->getResult($fmResult);
	/*
		$dataSet = array(
			array(			// an array for each record
				"fm_recid" => Record_ID,
				"fieldName1" => record_value1,
				"fieldName2" => "record_value2",
				"fieldWithRepetition" => array(
							"Rep_Data1" , "Rep_Data2"....
					),
				....
			),
			array(....),
			....
		)
	*/
```
[1]: Records returned from EzFMDB are all this format.  
  
### runScript(_$layout, $script [,$param=null]_) 
**@param** $layout The layout name to perform this script  
**@param** $script The script name to be performed  
**@param** $param The parameter pass to script.  
**@return** _TRUE / EzFMDB_ERR_ , TRUE when success.  
Perform a script, note that the value assigned in "Exit Script[]" will not pass to PHP.
```php
	$isSuccess = $db->runScript("LayoutName","ScriptName");
```
  
---
  
### select(_$layout , $fields [, **QueryParameters** ]_) 
**@param** $layout Specific layout name  
**@param** $fields Field name to be select. Can be sql-like sytax string or array  
**@QueryParameters** See **Query Parameters** section  
**@return** _array( RecData ) / EzFMDB_ERR_  
Select specific records (according to QueryParameters ) from the layout.
  
| idx |name  |gender|school|age|The Code|  
|:---:|:----:|:----:|:----:|:-:|:------:|  
|1    |A     |1     |NCKU  |1  |33      |  
|2    |B     |0     |NTU   |9  |445     |  
|3    |C     |1     |FHSH  |8  |3       |  
|4    |D     |1     |MIT   |31 |423     |  
|5    |E     |0     |NCKU  |5  |76      |  
|6    |darkk6|0     |NCKU  |20 |234     |  
  
```php
	//Use sql like string in "fields" parameter
	$r = $db->select("LayoutName" , "name,`The Code`");
	
	//Use array-type in "fields" parameter, use this way when field name contains ","
	$r = $db->select("LayoutName" , array("name","The Code") );

	//Select all fields
	$r = $db->select("LayoutName" , "*" );
	
	//Select fm_recid only
	$r = $db->select("LayoutName" , "" );
	
	//More complex example
	$r = $db->select("LayoutName","*"
		"WHERE", array("age"=>"<10"),		//Find 1,2,3,5 ; Found set [1,2,3,5]
		"WHERE school='NCKU' AND gender=0", //Find 5,6     ; Found set [1,2,3,5,6]
		"OMIT name='darkk6'",				//Omit 6       ; Found set [1,2,3,5]
		"ORDER BY age ASC",					//Sort by "age" ascending  [1,5,3,2]
		"LIMIT 1,2"							//Skip 1 , max 2 [5,3]
	);
```
  
### update(_$layout , $fvPair  [, **QueryParameters** ]_)
**@param** $layout Specific layout name  
**@param** $fvPair sql-like sytax string or array (key:fieldName , value:value).  
**@QueryParameters** See **Query Parameters** section  
**@return** _int / EzFMDB_ERR / array( EzFMDB_ERR )_  
Update all records that match the condition. This method will return affected record count or EzFMDB_ERR when error occurred.  
The $fvPair parameter structure is the same as RecData.
```php
	//update all Records set "name" to "darkk6" and "The Code" to "0"
	//NOTE : sql-like string doesn't support repetition field (will set the first repetition only)
	$r = $db->update("LayoutName" , "name='darkk6' , `The Code`=0");
	
	//Use this way if field name or value contains ","
	$r = $db->update("LayoutName" , 
				array(
						"name"=>"darkk6'",
						//repField[2] will remain, only update repField[1] and repField[3]
						"repField" => array( 0=>"Rep1_Data" , 2=>"Rep3_Data" ),
						"The Code"=>"0"
				)
			);
```
  
### updateByRecID(_$layout, $fvPair, $rec_id [,$doEscape=true_)
**@param** $layout Specific layout name  
**@param** $fvPair See update() parameter info.  
**@param** $rec_id Specific Record ID (fm_recid)  
**@param** $doEscape Whether escape special character ot not.  
**@return** _TRUE / EzFMDB_ERR_  
Update record with specific Rec_ID, return TRUE when success.
```php
	$r = $db->updateByRecID("LayoutName","name='darkk6',`The Code`=0",$fm_recid);
	$r = $db->updateByRecID("LayoutName",array( "name"=>"darkk6" ),$fm_recid);
```
  
### delete(_$layout [, **QueryParameters** ]_)
**@param** $layout Specific layout name  
**@QueryParameters** See **Query Parameters** section  
**@return** _int / EzFMDB_ERR / array( EzFMDB_ERR )_  
Update all records that match the condition. This method will return deleted record count or EzFMDB_ERR when error occurred.
```php
	$r = $db->delete("LayoutName");
	$r = $db->delete("LayoutName","WHERE",array("name"=>"darkk6","The Code"=>"0"));
```
  
### deleteByRecID(_$layout, $rec_id [,$doEscape=true]_)
**@param** $layout Specific layout name  
**@param** $rec_id Specific Record ID (fm_recid)  
**@param** $doEscape Whether escape special character ot not.  
**@return** _TRUE / EzFMDB_ERR_  
Delete record with specific Rec_ID, return TRUE when success.
```php
	$r = $db->deleteByRecID("LayoutName",$fm_recid);
```
  
### insert($layout,$fvPair, $doEscape=true)
**@param** $layout Specific layout name  
**@param** $fvPair Only accept array-type parameter. See update() parameter info.  
**@param** $doEscape Whether escape special character ot not.  
**@return** _TRUE / EzFMDB_ERR_  
Add a new record , return TRUE when success.
```php
	$r = $db->insert("LayoutName",array( "name"=>"darkk6" ));
```