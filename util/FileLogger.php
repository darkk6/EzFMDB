<?php
class FileLogger{
	
	private $path , $append , $logCount;
	
	public function __construct($filePath,$append=true){
		//測試 open file
		$mode = $append ? "a" : "w";
		$f = fopen( $filePath , $mode );
		if(!$f) throw new Exception('Invalid file path "'.$filePath.'"');
		fclose($f);
		
		$this->path = realpath($filePath);
		$this->append = $append;
		$this->logCount = 0;
	}
	
	/* 會在結尾自動加上 \n */
	public function log($str=""){
		$f = fopen( $this->path , "a" );
		if(!$f) return;
		fwrite($f,$str.PHP_EOL);
		fclose($f);
		$this->logCount++;
	}
	
	public function getInfo(){
		return json_encode(
					array(
						"Path" => $this->path,
						"Count" => $this->logCount,
						"Mode" => ($this->append?"append":"write")
					)
				);
	}
}
?>