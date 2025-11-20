<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;


$tableName = "support";

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {
	$db->beginTransaction();
	if (isset($_POST['feedback']) && trim((string) $_POST['feedback']) !== "" && trim((string) $_POST['feedbackUrl']) !== "") {
		$data = ['feedback' => $_POST['feedback'], 'feedback_url' => $_POST['feedbackUrl']];
		if (isset($_POST['attach_screenshot']) && $_POST['attach_screenshot']) {
			$data['attach_screenshot'] = 'yes';
			$response['attached'] = 'yes';
		}

		$db->insert($tableName, $data);
		$supportId = $db->getInsertId();
		// File upload folder
		$uploadDir = realpath(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/support");
		if (!file_exists($uploadDir) && !is_dir($uploadDir)) {
			MiscUtility::makeDirectory($uploadDir);
		}


		if (
			isset($_FILES['supportFile']) && $_FILES['supportFile']['error'] === UPLOAD_ERR_OK
			&& $_FILES['supportFile']['size'] > 0
		) {
			// Allowed file types
			$allowedExtensions = ['jpg', 'jpeg', 'png'];

			$imageName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['supportFile']['name'])));
			$imageName = str_replace(" ", "-", $imageName);
			$extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
			$imageName = $imageName . "." . $extension;


			if (in_array($extension, $allowedExtensions)) {

				MiscUtility::makeDirectory($uploadDir . DIRECTORY_SEPARATOR . $supportId);
				$uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $supportId;
				if (move_uploaded_file($_FILES["supportFile"]["tmp_name"], $uploadPath . DIRECTORY_SEPARATOR . $imageName)) {
					$fData = ['upload_file_name' => $imageName];
					$db->where('support_id', $supportId);
					$db->update($tableName, $fData);
					$db->commitTransaction();
					$response['status'] = 1;
					$response['supportId'] = base64_encode((string) $supportId);
					$response['message'] = 'Form data submitted successfully!';
				} else {
					$db->rollbackTransaction();
					$response['message'] = 'Please try again after some time';
				}
			} else {
				$db->rollbackTransaction();
				$response['message'] = 'Sorry, only ' . implode(', ', $allowedExtensions) . ' files are allowed to upload.';
			}
		} else {
			$response['status'] = 1;
			$response['supportId'] = base64_encode((string) $supportId);
			$response['message'] = 'Form data submitted successfully!';
			$db->commitTransaction();
		}

		// Return response
		echo json_encode($response);
	}
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
