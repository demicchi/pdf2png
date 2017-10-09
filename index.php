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

    if ($maxheight < 1 || $maxheight > 5000) {
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
        $zip->addFromString('merged.png', $resultimage_string);
        if ($combinepdf)
            $zip->addFromString('merged.pdf', $resultpdf_string);
        $zip->close();
    }

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename="pack.zip"');
    header('Content-Length: ' . filesize($zipfile));
    readfile($zipfile);
    unlink($zipfile);

} else {
    echo <<<HTML
<html>
    <head>
        <title>PDF2PNG</title>
    </head>
    <body>
        <h1>PDF2PNG</h1>
        All PDFs should be with version 1.4 (Acrobat 5.x) or below to combine.<br /><br />
        <form action="/" method="post" enctype="multipart/form-data">
            PDF1: <input name="pdf[]" type="file" /><br />
            PDF2: <input name="pdf[]" type="file" /><br />
            PDF3: <input name="pdf[]" type="file" /><br />
            PDF4: <input name="pdf[]" type="file" /><br />
            PDF5: <input name="pdf[]" type="file" /><br />
            <br />
            <input id="combinepdf_chk" name="combinepdf" type="checkbox" value="true" checked="checked" /><label for="combinepdf_chk">Merge all PDFs into one PDF</label><br />
            <br />
            <input id="reverse_chk" name="reverse" type="checkbox" value="true" checked="checked" /><label for="reverse_chk">Merge in reverse order for PNG (Japanese Newspaper)</label><br />
            Max PNG Height: <input name="maxheight" type="text" size="6" pattern="^[0-9]+$" value="1000" /> (range: 1-5000, 0 means default)<br />
            <input name="getpdf" type="hidden" value="true" />
            <br />
            <input type="submit" value="Generate PNG" />
        </form>
        <hr />
        Ver.20171009-01
    </body>
</html>
HTML;

}