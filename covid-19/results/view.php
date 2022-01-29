<?php
$ciphering = "AES-128-CTR";
$iv_length = openssl_cipher_iv_length($ciphering);
$encryption = $_GET['q'];
$options = 0;
$decryption_iv = $systemConfig['tryCrypt'];
$decryption_key = $systemConfig['tryCrypt'];
$decryption = openssl_decrypt(
    $encryption,
    $ciphering,
    $decryption_key,
    $options,
    $decryption_iv
);
$data = explode('&&&', urldecode($decryption));
/* if ($data[1] != "qr")
    include_once(APPLICATION_PATH . '/header.php'); */
$uniqueId = $data[0];
$db = MysqliDb::getInstance();
$db->where("unique_id", $uniqueId);
$res = $db->getOne("form_covid19","covid19_id");
$id = $res['covid19_id'];
?>
<style>
    #the-canvas {
        border: 1px solid black;
        direction: ltr;
        margin-left: 15%;
    }
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script src="//mozilla.github.io/pdf.js/build/pdf.js"></script>


<canvas id="the-canvas"></canvas>


<script type="text/javascript">
    $(document).ready(function() {

        convertSearchResultToPdf(<?php echo ($id); ?>);
    });

    function convertSearchResultToPdf(id) {

        <?php
        $path = '';
        $path = '/covid-19/results/generate-result-pdf.php';
        ?>

        $.post("<?php echo $path; ?>", {
                source: 'print',
                id: id,
                type: "qr",
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    alert('Unable to generate download');
                } else {
                    var url = './../../uploads/' + data;
                    // Loaded via <script> tag, create shortcut to access PDF.js exports.
                    var pdfjsLib = window['pdfjs-dist/build/pdf'];

                    // The workerSrc property shall be specified.
                    pdfjsLib.GlobalWorkerOptions.workerSrc = '//mozilla.github.io/pdf.js/build/pdf.worker.js';
                    // If absolute URL from the remote server is provided, configure the CORS
                    // header on that server.

                    // Asynchronous download of PDF
                    var loadingTask = pdfjsLib.getDocument(url);
                    loadingTask.promise.then(function(pdf) {

                        // Fetch the first page
                        var pageNumber = 1;
                        pdf.getPage(pageNumber).then(function(page) {

                            var scale = 1.5;
                            var viewport = page.getViewport({
                                scale: scale
                            });

                            // Prepare canvas using PDF page dimensions
                            var canvas = document.getElementById('the-canvas');
                            var context = canvas.getContext('2d');
                            canvas.height = viewport.height;
                            canvas.width = viewport.width;

                            // Render PDF page into canvas context
                            var renderContext = {
                                canvasContext: context,
                                viewport: viewport
                            };
                            var renderTask = page.render(renderContext);
                            renderTask.promise.then(function() {});
                        });
                    }, function(reason) {
                        // PDF loading error
                        console.error(reason);
                    });
                }
            });
    }
</script>