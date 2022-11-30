<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
        session_start();
}
$tableName = "support";
try{
	if (isset($_POST['image']) && trim($_POST['image'])!="" && trim($_POST['supportId'])!="") {
		$supportId=base64_decode($_POST['supportId']);
		// File upload folder 
		$uploadDir = WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support"; 
		if (!file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support") && !is_dir(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support")) { 
            mkdir(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support");
        }
		if (!file_exists($uploadDir.DIRECTORY_SEPARATOR.$supportId) && !is_dir($uploadDir.DIRECTORY_SEPARATOR.$supportId)) { 
			mkdir($uploadDir.DIRECTORY_SEPARATOR.$supportId);
		}
		
		$data = $_POST['image'];
		$fileName = uniqid().'.png';
		$uploadPath = $uploadDir.DIRECTORY_SEPARATOR.$supportId.DIRECTORY_SEPARATOR.$fileName;
		
		// remove "data:image/png;base64,"
		$uri =  substr($data,strpos($data,",",1));
		// save to file
		file_put_contents($uploadPath,base64_decode($uri));

		$fData = array(
			'screenshot_file_name' => $fileName
		);
		$db->where('support_id',$supportId);
		$db->update($tableName,$fData);
		//print_r($data);die;
		if (file_exists($uploadPath)){
			// Return response 
			echo "Submitted successfully";
		}else{
			echo "Please contact to admin or try again after sometimes";
		}
	}
}catch(Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());

}