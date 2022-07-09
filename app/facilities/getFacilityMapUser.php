<?php
  

$fType = ($_POST['fType']==1)?4:1;
$vlfmQuery="SELECT GROUP_CONCAT(DISTINCT vlfm.user_id SEPARATOR ',') as userId FROM user_facility_map as vlfm join facility_details as fd ON fd.facility_id=vlfm.facility_id where facility_type = ".$fType;
$vlfmResult = $db->rawQuery($vlfmQuery);
$uQuery="SELECT * FROM user_details";
if(isset($vlfmResult[0]['userId']))
{
  $exp = explode(",",$vlfmResult[0]['userId']);
  foreach($exp as $ex){
    $noUserId[] = "'".$ex."'";
  }
  $imp = implode(",",$noUserId);
  $uQuery = $uQuery." where user_id NOT IN(".$imp.")";
}
$uResult = $db->rawQuery($uQuery);
?>
<h4>User Facility Map Details</h4>
<div class="col-xs-5">
    <select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
        <?php
        foreach($uResult as $uName){
            ?>
                <option value="<?php echo $uName['user_id'];?>"><?php echo ucwords($uName['user_name']);?></option>
            <?php
        }
        ?>
    </select>
</div>

<div class="col-xs-2">
    <button type="button" id="search_rightAll" class="btn btn-block"><i class="glyphicon glyphicon-forward"></i></button>
    <button type="button" id="search_rightSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-right"></i></button>
    <button type="button" id="search_leftSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-left"></i></button>
    <button type="button" id="search_leftAll" class="btn btn-block"><i class="glyphicon glyphicon-backward"></i></button>
</div>

<div class="col-xs-5">
    <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple"></select>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
    $('#search').multiselect({
        search: {
            left: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
            right: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
        },
        fireSearch: function(value) {
            return value.length > 3;
        }
    });
    });
</script>