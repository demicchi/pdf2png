<?php
/**
 * Created by IntelliJ IDEA.
 * User: demicchi
 * Date: 2017/10/09
 * Time: 16:16
 */

require_once __DIR__ . '/vendor/autoload.php';

if (@$_POST["getpdf"] === "true") {
    $imagenum = 0;
    $totalwidth = 0;
    $maxheight = intval($_POST["maxheight"]);
    $combinepdf = (@$_POST["combinepdf"] === "true");
    $volnum = $_POST["volnum"];
    $pdfname = ($_POST["pdfname"] == "") ? "merged.pdf" : str_replace('%n', $volnum, $_POST["pdfname"].".pdf");
    $pngname = ($_POST["pngname"] == "") ? "merged.png" : str_replace('%n', $volnum, $_POST["pngname"].".png");
    $zipname = ($_POST["zipname"] == "") ? "pack.zip" : str_replace('%n', $volnum, $_POST["zipname"].".zip");
    $forwin = (@$_POST["forwin"] === "true");

    if ($maxheight < 100 || $maxheight > 5000) {
        $maxheight = 0;
    } else {
        $maxheight = $_POST["maxheight"];
    }

    if ($combinepdf) {
        $pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $pdf->SetImportUse();
    }

    foreach ($_FILES["pdf"]["tmp_name"] as $key => $tmpname) {
        if ($tmpname == "")
            continue;
        $imagick = new Imagick();
        $imagick->setResolution(144,144);
        $imagick->readImage($tmpname);
        $total = $imagick->getImageScene();
        if ($combinepdf) {
            try {
                $pdf->SetSourceFile($tmpname);
            } catch (Exception $e) {
                echo "<html><head></head><body>";
                echo "An error occurred while processing {$_FILES["pdf"]["name"][$key]}<br /><br />\n";
                echo $e->getMessage();
                echo "</body></html>";
                exit();
            }
        }


        for ($i = 0; $i <= $total; $i++) {
            $imagick->setIteratorIndex($i);
            $imagick->setImageFormat("png");
            if ($imagenum == 0 && $maxheight == 0) {
                $maxheight = $imagick->getImageHeight();
            }
            $imagick->resizeImage(0, $maxheight, imagick::FILTER_LANCZOS, 1);
            $totalwidth += $imagick->getImageWidth();
            $images[$imagenum] = imagecreatefromstring($imagick->getImageBlob());
            $imagenum++;

            if ($combinepdf) {
                $pdf_template = $pdf->ImportPage($i + 1);
                $pdf_template_size = $pdf->GetTemplateSize($pdf_template);
                if ($pdf_template_size["w"] > $pdf_template_size["h"]) {
                    $pdfparam = array(
                        "orientation" => "L",
                        "sheet-size" => array($pdf_template_size["h"], $pdf_template_size["w"])
                    );
                } else {
                    $pdfparam = array(
                        "orientation" => "P",
                        "sheet-size" => array($pdf_template_size["w"], $pdf_template_size["h"])
                    );
                }
                $pdf->AddPageByArray($pdfparam);
                $pdf->UseTemplate($pdf_template);
            }
        }
        $imagick->clear();
    }

    $resultimage = imagecreatetruecolor($totalwidth, $maxheight);
    imagefill($resultimage, 0, 0, imagecolorallocate($resultimage, 255,255,255));
    if ($_POST["reverse"] === "true")
        $images = array_reverse($images);
    $dstx = 0;
    foreach ($images as $image) {
        imagecopy($resultimage, $image, $dstx, 0, 0, 0, imagesx($image), $maxheight);
        $dstx += imagesx($image);
        imagedestroy($image);
    }

    ob_start();
    imagepng($resultimage);
    $resultimage_string = ob_get_contents();
    ob_end_clean();
    imagedestroy($resultimage);

    if ($combinepdf)
        $resultpdf_string = $pdf->Output("", \Mpdf\Output\Destination::STRING_RETURN);

    $zipfile = tempnam(sys_get_temp_dir(), "pdf2png");
    $zip = new ZipArchive;
    if ($zip->open($zipfile, ZipArchive::CREATE) === true) {
        $zip->addFromString($forwin ? mb_convert_encoding($pngname, "CP932", "UTF-8") : $pngname, $resultimage_string);
        if ($combinepdf)
            $zip->addFromString($forwin ? mb_convert_encoding($pdfname, "CP932", "UTF-8") : $pdfname, $resultpdf_string);
        $zip->close();
    }

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename="'.$zipname.'"');
    header('Content-Length: ' . filesize($zipfile));
    readfile($zipfile);
    unlink($zipfile);

} else {
    $volnum = (isset($_GET["volnum"])) ? htmlspecialchars($_GET["volnum"]) : "";
    $pdfname = (isset($_GET["pdfname"])) ? htmlspecialchars($_GET["pdfname"]) : "";
    $pngname = (isset($_GET["pngname"])) ? htmlspecialchars($_GET["pngname"]) : "";
    $zipname = (isset($_GET["zipname"])) ? htmlspecialchars($_GET["zipname"]) : "";
    $forwin = (isset($_GET["forwin"])) ? (($_GET["forwin"] === "true") ? "checked=\"checked\"" : "") : "checked=\"checked\"";

    echo <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>PDF2PNG</title>
        <script>
        function getlink() {
        	document.getElementById('link').innerHTML = 
            	"Bookmark Me! <a href=\"/?volnum=" + encodeURIComponent(document.forms.uploadform.volnum.value) + 
            	"&pdfname=" + encodeURIComponent(document.forms.uploadform.pdfname.value) + 
            	"&pngname=" + encodeURIComponent(document.forms.uploadform.pngname.value) + 
            	"&zipname=" + encodeURIComponent(document.forms.uploadform.zipname.value) + 
            	"&forwin=" + (document.forms.uploadform.forwin.checked ? "true" : "false") + 
            	"\">PDF2PNG</a>";
        }
        </script>
    </head>
    <body>
        <h1>PDF2PNG</h1>
        Merge pdfs and export in one PNG and one PDF.<br />
        <br />
        <form name="uploadform" action="/" method="post" enctype="multipart/form-data">
            <h2>Files to Process</h2>
            PDF1: <input name="pdf[]" type="file" /><br />
            PDF2: <input name="pdf[]" type="file" /><br />
            PDF3: <input name="pdf[]" type="file" /><br />
            PDF4: <input name="pdf[]" type="file" /><br />
            PDF5: <input name="pdf[]" type="file" /><br />
            - Up to 1 GB total.
            <br />
            <br />
            <h2>Process Config</h2>
            <input id="combinepdf_chk" name="combinepdf" type="checkbox" value="true" checked="checked" /><label for="combinepdf_chk">Merge all PDFs into one PDF</label><br />
            - All PDFs should be with version 1.4 (Acrobat 5.x) or below to merge into one PDF.<br />
            - PowerPoint exports version 1.5 PDFs but they seem to be compatible to this PDF2PNG.<br />
            <br />
            <input id="reverse_chk" name="reverse" type="checkbox" value="true" checked="checked" /><label for="reverse_chk">Merge in reverse order for PNG (Japanese Newspaper)</label><br />
            Max PNG Height: <input name="maxheight" type="text" size="6" pattern="^[0-9]+$" value="1000" /> (range: 100-5000, 0 means default)<br />
            <input name="getpdf" type="hidden" value="true" />
            <br />
            <br />
            <h2>Filename Config</h2>
            Volume Number (if necessary in filenames): <input name="volnum" size="10" type="text" value="{$volnum}" /><br />
            - %n in the fields below will be replaced by the value Volume Number above. <br />
            PDF Name: <input name="pdfname" size="20" type="text" value="{$pdfname}" />.pdf (default: merged.pdf)<br />
            PNG Name: <input name="pngname" size="20" type="text" value="{$pngname}" />.png (default: merged.png)<br />
            ZIP Name: <input name="zipname" size="20" type="text" value="{$zipname}" />.zip (default: pack.zip)<br />
            Filename Charset for Windows Japanese(CP932): <input id="forwin_chk" name="forwin" type="checkbox" value="true" {$forwin} /><label for="forwin_chk">Yes</label><br />
            <input type="button" value="Get Link to Bookmark with the Filename Parameters Above" onclick="getlink()" /><br />
            <div id="link"></div>
            <br />
            <br />
            <h2>Get Ready?</h2>
            <input type="submit" value="Upload PDFs and Generate PNG and PDF" />
            <br />
            <br />
        </form>
        <hr />
        Ver.20171011-01
    </body>
</html>
HTML;

}