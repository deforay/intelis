<?php
?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<!-- Bootstrap 3.3.6 -->
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
<link href="/assets/css/deforayModal.css" rel="stylesheet" />
<style>
	.content-wrapper {
		padding: 2%;
	}

	.center {
		text-align: center;
	}

	body {
		overflow-x: hidden;
		/*overflow-y: hidden;*/
	}

	td {
		font-size: 13px;
		font-weight: 500;
	}

	th {
		font-size: 15px;
	}
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
<script src="/assets/js/deforayModal.js"></script>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h4 class="pull-left bg-primary" style="width:100%;padding:8px;font-weight:normal;">Support</h4>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<!-- Status message -->
						<div class="statusMsg"></div>
						<form class="form-horizontal" method='post' name='supportForm' id='supportForm' enctype="multipart/form-data" autocomplete="off">
							<div class="form-group">
								<label for="first_name" class="col-xs-2 control-label">Support</label>
								<div class="col-xs-9">
									<textarea rows="6" class="form-control isRequired" name="feedback" id="feedback" title="Please enter the feedback" placeholder="Enter Feedback"></textarea>
									<input type="hidden" class="form-control isRequired" name="feedbackUrl" id="feedbackUrl" value="<?php echo $_GET['fUrl']; ?>">
								</div>
							</div>
							<div class="form-group">
								<label for="last_name" class="col-xs-2 control-label">Upload Image</label>
								<div class="col-xs-9">
									<input type="file" class="form-control" name="supportFile" id="supportFile" title="Please select a file to upload">
									(Upload jpg,jpeg,png format)
								</div>
							</div>

							<div class="form-group">
								<label class="col-xs-2 control-label"></label>
								<div class="col-xs-9">
									<input type="checkbox" name="attach_screenshot" id="attach_screenshot" > Attach current page screenshot
								</div>
							</div>

							<div class="form-group">
								<div class="col-xs-offset-2 col-xs-9">
									<input class="btn btn-primary submitBtn" type="submit" value="Submit">
								</div>
							</div>
						</form>
						
					</div>
					<!-- /.box-body -->
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<div id="dDiv" class="dialog">
	<div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
	<iframe id="dFrame" src="" title="LIS Content" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"><?= _("Unable to load this page or resource"); ?></iframe>
</div>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript">
$(document).ready(function(e){
    // Submit form data via Ajax
    $("#supportForm").on('submit', function(e){
		e.preventDefault();
		flag = deforayValidator.init({
      		formId: 'supportForm'
   		});
		if (flag) {
			$.ajax({
				type: 'POST',
				url: 'addSupportHelper.php',
				data: new FormData(this),
				dataType: 'json',
				contentType: false,
				cache: false,
				processData:false,
				beforeSend: function(){
					$('.submitBtn').attr("disabled","disabled");
					$('#supportForm').css("opacity",".5");
				},
				success: function(response){
					//$('.statusMsg').html('');
					if(response.status == 1){
						if(response.attached=='yes'){
							parent.screenshot(response.supportId,'yes');
						}else{
							parent.screenshot(response.supportId,'');
						}
						//$('#fupForm')[0].reset();
						//$('.statusMsg').html('<p class="alert alert-success">'+response.message+'</p>');
					}else{
						$('.statusMsg').html('<p class="alert alert-danger">'+response.message+'</p>');
					}
					$('#supportForm').css("opacity","");
					$(".submitBtn").removeAttr("disabled");
				}
			});
		}
    });
});
</script>