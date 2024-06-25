<?php
class LogHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {
        // echo"---in---UserHandler---222--";
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
		// print_r($db);
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
    }
	public function insertAPILog($TransPara) {
		
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO igain_api_json_log (" . implode(', ', $key) . ") "
			 . "VALUES ('" . implode("', '", $val) . "')";		
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}
    }
	public function insertLog($TransPara) {
		
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO igain_log_tbl (" . implode(', ', $key) . ") "
			 . "VALUES ('" . implode("', '", $val) . "')";		
			//  echo $sql;

		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}
    }

}

?>
