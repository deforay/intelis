<?php

if(isset($_POST['table']))
{
    $table = $_POST['table'];
    $sql = "SELECT DISTINCT sample_code FROM $table";
    $result = $db->rawQuery($sql);
    echo json_encode($result);
}