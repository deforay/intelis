<?php
require(APPLICATION_PATH . '/header.php');
?>
<div class="content-wrapper">
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <br>
                <br>
                <br>
                <br>
                <br>
                <br>
                <br>
                <br>
                <br>
                <h1 style="color:red;text-align: center;font-size:8em;font-weight:500;">
                    <span class="fa-solid fa-triangle-exclamation"></span> 404
                </h1>
                <h2 style="color:red;text-align: center;font-weight:500;">
                    <?= _("Sorry! We could not find this page or resource."); ?><br />
                    <small><?= _("Please contact the System Admin for further support."); ?></small>
                </h2>
            </div>
        </div>
    </section>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        $('.sidebar-menu').remove();
    });
</script>
<?php
require(APPLICATION_PATH . "/footer.php");
