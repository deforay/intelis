<?php
ob_start();
session_start();
#include_once '../startup.php';
include_once APPLICATION_PATH . '/includes/MysqliDb.php';
include_once(APPLICATION_PATH . '/models/General.php');
include_once APPLICATION_PATH . '/includes/ImageResize.php';
include_once(APPLICATION_PATH . "/vendor/autoload.php");

$general = new General($db);
//#require_once('../startup.php'); 
// include_once(APPLICATION_PATH . '/header.php');
$tableName = "user_details";
$tableName2 = "vl_user_facility_map";
try {
    if (trim($_POST['userName']) != '' && trim($_POST['loginId']) != '' && ($_POST['role']) != '' && ($_POST['password']) != '') {

        if (isset($_FILES['userSignature']['name']) && $_FILES['userSignature']['name'] != "") {
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['userSignature']['name'], PATHINFO_EXTENSION));
            $string = $general->generateRandomString(10) . ".";
            $imageName = "usign-" . $string . $extension;
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
            if (move_uploaded_file($_FILES["userSignature"]["tmp_name"], $signatureImagePath)) {
                $resizeObj = new ImageResize($signatureImagePath);
                $resizeObj->resizeImage(100, 100, 'auto');
                $resizeObj->saveImage($signatureImagePath, 100);
                $data['user_signature'] = $imageName;
            }
        }

        $password = sha1($_POST['password'] . $systemConfig['passwordSalt']);
        $idOne = $general->generateRandomString(8);
        $idTwo = $general->generateRandomString(4);
        $idThree = $general->generateRandomString(4);
        $idFour = $general->generateRandomString(4);
        $idFive = $general->generateRandomString(12);
        $data = array(
            'user_id' => $idOne . "-" . $idTwo . "-" . $idThree . "-" . $idFour . "-" . $idFive,
            //'user_alpnum_id'=>$idOne."-".$idTwo."-".$idThree."-".$idFour."-".$idFive,
            'user_name' => $_POST['userName'],
            'email' => $_POST['email'],
            'login_id' => $_POST['loginId'],
            'phone_number' => $_POST['phoneNo'],
            'password' => $password,
            'role_id' => $_POST['role'],
            'status' => 'active',
        );
        $data['user_signature'] = $imageName;
        $id = $db->insert($tableName, $data);
        if ($id > 0 && trim($_POST['selectedFacility']) != '') {
            if ($id > 0 && trim($_POST['selectedFacility']) != '') {
                $selectedFacility = explode(",", $_POST['selectedFacility']);
                $uniqueFacilityId = array_unique($selectedFacility);
                for ($j = 0; $j <= count($selectedFacility); $j++) {
                    if (isset($uniqueFacilityId[$j])) {
                        $data = array(
                            'facility_id' => $selectedFacility[$j],
                            'user_id' => $data['user_id'],
                        );
                        $db->insert($tableName2, $data);
                    }
                }
            }
        }

        $_SESSION['alertMsg'] = "User details added successfully";
    }
    $userType = $general->getSystemConfig('user_type');
    if(isset($systemConfig['remoteURL']) && $systemConfig['remoteURL'] != "" && $userType == 'vluser'){
        $apiUrl = $systemConfig['remoteURL'] . "/api/user/save-user-profile.php";
        $post = array('post' => json_encode($_POST), 'sign'=> (isset($signatureImagePath) && $signatureImagePath != "")?curl_file_create($signatureImagePath):null, 'x-api-key' => $general->generateRandomString(18));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$apiUrl);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($ch);
        curl_close($ch);

        $deResult = json_decode($result, true);
        // echo "<pre>";print_r($deResult);die;
    }

    //Add event log
    $eventType = 'user-add';
    $action = ucwords($_SESSION['userName']) . ' added user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);

    header("location:users.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
