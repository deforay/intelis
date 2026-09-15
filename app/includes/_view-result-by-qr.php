<?php

/**
 * Shared "view result from the QR code on a printed report" page.
 *
 * Each module's /<module>/results/view.php sets a $qrView array and requires this
 * file; the token decoding, the sample lookup and the PDF rendering are the same
 * for every module.
 *
 * $qrView keys:
 *   table       string  the module's form table.
 *   primaryKey  string  its primary key column, posted to the PDF generator.
 *   pdfPath     string  the module's generate-result-pdf.php URL.
 */

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$invalidRequest = _translate("INVALID REQUEST");
$refuse = static function (string $message): never {
    http_response_code(400);
    echo "<br><br><br><br><br><br><h1 style='text-align:center;font-family:arial;font-size:1.3em;'>$message</h1>";
    exit;
};

$uniqueId = CommonService::uniqueIdFromViewQRCode($_GET['q'] ?? null, $general->getGlobalConfig('key'));
if ($uniqueId === null) {
    $refuse($invalidRequest);
}

$db->where("unique_id", $uniqueId);
$res = $db->getOne($qrView['table'], $qrView['primaryKey']);
if (empty($res)) {
    $refuse($invalidRequest);
}

$id = $res[$qrView['primaryKey']];
?>
<style>
    #the-canvas {
        border: 1px solid black;
        direction: ltr;
        margin-left: 15%;
        margin-top: 50px;
    }
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js" integrity="sha512-dw+7hmxlGiOvY3mCnzrPT5yoUwN/MRjVgYV7HGXqsiXnZeqsw1H9n9lsnnPu4kL2nx2bnrjFcuWK+P3lshekwQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<canvas id="the-canvas"></canvas>

<script type="text/javascript">
    $(document).ready(function() {
        convertSearchResultToPdf(<?= _jsEscape($id); ?>);
    });

    function convertSearchResultToPdf(id) {
        $.post(<?= _jsEscape($qrView['pdfPath']); ?>, {
                source: 'print',
                id: id,
                type: "qr",
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    alert('Unable to generate result PDF');
                } else {
                    var url = '/download.php?f=' + data;
                    // Loaded via <script> tag, create shortcut to access PDF.js exports.
                    var pdfjsLib = window['pdfjs-dist/build/pdf'];

                    // The workerSrc property shall be specified.
                    pdfjsLib.GlobalWorkerOptions.workerSrc = '//cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js';

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
