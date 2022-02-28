<?php
ob_start();
$title = _("COVID-19 | Add New Request");
#require_once('../../startup.php');
require_once(APPLICATION_PATH . '/header.php');
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> <?php echo _("Welcome to Admin Page"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Admin"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
            </div>
            <!-- /.box-header -->
            <div class="box-body">

            </div>
        </div>
    </section>
</div>
<?php
require_once(APPLICATION_PATH . '/footer.php');
