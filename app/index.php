<?php 
header("location:/login/login.php");
/*$username="root";
$password="zaq12345";
$host="localhost";
$database="odkdash";

$con=mysqli_connect($host,$username,$password) or die("Server Error");
mysqli_select_db($con,$database) or die("Database error");


$sql = "SELECT COLUMN_NAME 
FROM `INFORMATION_SCHEMA`.`COLUMNS` 
WHERE `TABLE_SCHEMA`='odkdash' 
    AND `TABLE_NAME`='spi_form_v_6'
    AND DATA_TYPE = 'varchar'";

$result = mysqli_query($con, $sql);


$columnArr = mysqli_fetch_all ($result,MYSQLI_ASSOC);
$query = "ALTER TABLE `spi_form_v_6` ";
foreach($columnArr as $col)
{
	$row = $col['COLUMN_NAME'];
	$query .=  "CHANGE `$row` `$row` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, ";
}
echo $query;
*/
?>
