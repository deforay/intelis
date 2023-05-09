<?php

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$tableName = "support";
try {
	$db->startTransaction();
	if (isset($_POST['feedback']) && trim($_POST['feedback']) != "" && trim($_POST['feedbackUrl']) != "") {
		$data = array(
			'feedback' => $_POST['feedback'],
			'feedback_url' => $_POST['feedbackUrl']
		);
		if (isset($_POST['attach_screenshot']) && $_POST['attach_screenshot']) {
			$data['attach_screenshot'] = 'yes';
			$response['attached'] = 'yes';
		}

		//print_r($data);die;
		$db->insert($tableName, $data);
		$supportId = $db->getInsertId();
		// File upload folder 
		$uploadDir = WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support";
		if (!file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support") && !is_dir(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support")) {
			mkdir(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support");
		}

		if (!empty($_FILES["supportFile"]["name"])) {
			// Allowed file types 
			$allowedExtensions = array('jpg', 'jpeg', 'png',);
			$imageName = $_FILES['supportFile']['name'];
			$_FILES['supportFile']['name'] = preg_replace('/[^A-Za-z0-9.]/', '-', $imageName);
			$_FILES['supportFile']['name'] = str_replace(" ", "-", $_FILES['supportFile']['name']);
			$extension = strtolower(pathinfo($_FILES['supportFile']['name'], PATHINFO_EXTENSION));

			if (isset($_FILES['supportFile']["name"]) && trim($_FILES["supportFile"]["name"]) != "" && in_array($extension, $allowedExtensions)) {
				mkdir($uploadDir . DIRECTORY_SEPARATOR . $supportId);
				$uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $supportId;
				if (move_uploaded_file($_FILES["supportFile"]["tmp_name"], $uploadPath . DIRECTORY_SEPARATOR . $_FILES['supportFile']['name'])) {
					$fData = array(
						'upload_file_name' => $_FILES['supportFile']['name']
					);
					$db->where('support_id', $supportId);
					$db->update($tableName, $fData);
					$db->commit();
					$response['status'] = 1;
					$response['supportId'] = base64_encode($supportId);
					$response['message'] = 'Form data submitted successfully!';
				} else {
					$db->rollback();
					$response['message'] = 'Please try again after some time';
				}
			} else {
				$db->rollback();
				$response['message'] = 'Sorry, only ' . implode('/', $allowedExtensions) . ' files are allowed to upload.';
			}
		} else {
			$response['status'] = 1;
			$response['supportId'] = base64_encode($supportId);
			$response['message'] = 'Form data submitted successfully!';
			$db->commit();
		}

		// Return response 
		echo json_encode($response);
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
