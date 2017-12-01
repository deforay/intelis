<?php
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
//global config
$configQuery="SELECT value FROM global_config WHERE name ='vl_form'";
$configResult=$db->query($configQuery);
$country = $configResult[0]['value'];

$rpQuery="SELECT GROUP_CONCAT(DISTINCT rp.sample_id SEPARATOR ',') as sampleId FROM r_package_details_map as rp";
$rpResult = $db->rawQuery($rpQuery);

$query="SELECT vl.sample_code,vl.vl_sample_id FROM vl_request_form as vl where vl.vlsm_country_id = $country";
if(isset($rpResult[0]['sampleId'])){
    $query = $query." AND vl_sample_id NOT IN(".$rpResult[0]['sampleId'].")";
}
$query = $query." ORDER BY vl.request_created_datetime ASC";
$result = $db->rawQuery($query);
?>
<div class="col-md-8">
<div class="form-group">
   <div class="col-md-12">
      <div class="col-md-12">
         <div style="width:60%;margin:0 auto;clear:both;">
          <a href="#" id="select-all-samplecode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a>  <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
          </div><br/><br/>
         <select id="sampleCode" name="sampleCode[]" multiple="multiple" class="search">
            <?php
            foreach($result as $sample){
              ?>
              <option value="<?php echo $sample['vl_sample_id'];?>"><?php  echo ucwords($sample['sample_code']);?></option>
              <?php
             }
            ?>
         </select>
      </div>
   </div>
</div>
</div>
<script>
    $(document).ready(function() {
      $('.search').multiSelect({
        selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
        selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
        afterInit: function(ms){
          var that = this,
              $selectableSearch = that.$selectableUl.prev(),
              $selectionSearch = that.$selectionUl.prev(),
              selectableSearchString = '#'+that.$container.attr('id')+' .ms-elem-selectable:not(.ms-selected)',
              selectionSearchString = '#'+that.$container.attr('id')+' .ms-elem-selection.ms-selected';
      
          that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
          .on('keydown', function(e){
            if (e.which === 40){
              that.$selectableUl.focus();
              return false;
            }
          });
      
          that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
          .on('keydown', function(e){
            if (e.which == 40){
              that.$selectionUl.focus();
              return false;
            }
          });
        },
        afterSelect: function(){
            //button disabled/enabled
	     if(this.qs2.cache().matchedResultsCount == noOfSamples){
		alert("You have selected Maximum no. of sample "+this.qs2.cache().matchedResultsCount);
		$("#packageSubmit").attr("disabled",false);
                $("#packageSubmit").css("pointer-events","auto");
	     }else if(this.qs2.cache().matchedResultsCount <= noOfSamples){
	       $("#packageSubmit").attr("disabled",false);
               $("#packageSubmit").css("pointer-events","auto");
	     }else if(this.qs2.cache().matchedResultsCount > noOfSamples){
               alert("You have already selected Maximum no. of sample "+noOfSamples);
	       $("#packageSubmit").attr("disabled",true);
               $("#packageSubmit").css("pointer-events","none");
	     }
	      this.qs1.cache();
	      this.qs2.cache();
       },
       afterDeselect: function(){
         //button disabled/enabled
          if(this.qs2.cache().matchedResultsCount == 0){
            $("#packageSubmit").attr("disabled",true);
	    $("#packageSubmit").css("pointer-events","none");
          }else if(this.qs2.cache().matchedResultsCount == noOfSamples){
	     alert("You have selected Maximum no. of sample "+this.qs2.cache().matchedResultsCount);
	     $("#packageSubmit").attr("disabled",false);
             $("#packageSubmit").css("pointer-events","auto");
	  }else if(this.qs2.cache().matchedResultsCount <= noOfSamples){
	    $("#packageSubmit").attr("disabled",false);
            $("#packageSubmit").css("pointer-events","auto");
	  }else if(this.qs2.cache().matchedResultsCount > noOfSamples){
	    $("#packageSubmit").attr("disabled",true);
            $("#packageSubmit").css("pointer-events","none");
	  }
	  this.qs1.cache();
	  this.qs2.cache();
       }
      });
      $('#select-all-samplecode').click(function(){
        $('#sampleCode').multiSelect('select_all');
        return false;
      });
      $('#deselect-all-samplecode').click(function(){
        $('#sampleCode').multiSelect('deselect_all');
        $("#packageSubmit").attr("disabled",true);
        $("#packageSubmit").css("pointer-events","none");
        return false;
      });
   });
</script>