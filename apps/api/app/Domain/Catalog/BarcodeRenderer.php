<?php

namespace App\Domain\Catalog;

use Dompdf\Dompdf;
use Dompdf\Options;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeRenderer
{
    private const QUIET = 20; // 10 módulos de 2 px de cada lado.

    public function svg(string $code): string
    {
        $xml = new \DOMDocument;
        $xml->loadXML((new BarcodeGeneratorSVG)->getBarcode($code, BarcodeGenerator::TYPE_CODE_128, 2, 64), LIBXML_NONET);
        $root = $xml->documentElement;
        $width = (int) $root->getAttribute('width') + 2 * self::QUIET;
        $height = 84;
        $group = $xml->createElementNS('http://www.w3.org/2000/svg', 'g');
        while ($root->firstChild) {
            $group->appendChild($root->firstChild);
        }
        $group->setAttribute('transform', 'translate('.self::QUIET.' 10)');
        $background = $xml->createElementNS('http://www.w3.org/2000/svg', 'rect');
        foreach (['width' => $width, 'height' => $height, 'fill' => 'white'] as $key => $value) {
            $background->setAttribute($key, (string) $value);
        }
        $root->setAttribute('width', (string) $width);
        $root->setAttribute('height', (string) $height);
        $root->setAttribute('viewBox', '0 0 '.$width.' '.$height);
        $root->setAttribute('aria-label', 'Code128 interno '.$code);
        $root->appendChild($background);
        $root->appendChild($group);

        return $xml->saveXML();
    }

    public function png(string $code): string
    {
        $bars = imagecreatefromstring((new BarcodeGeneratorPNG)->getBarcode($code, BarcodeGenerator::TYPE_CODE_128, 2, 64));
        $image = imagecreatetruecolor(imagesx($bars) + 2 * self::QUIET, 84);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $bars, self::QUIET, 10, 0, 0, imagesx($bars), imagesy($bars));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    public function pdf(ProductBarcode $barcode, CommercialSku $sku): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $name = htmlspecialchars(mb_strimwidth($sku->name, 0, 48, '...'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code = htmlspecialchars($barcode->code, ENT_QUOTES, 'UTF-8');
        $internalSku = htmlspecialchars($sku->sku, ENT_QUOTES, 'UTF-8');
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $widthAtOnePoint = $pdf->getFontMetrics()->getTextWidth('SKU '.$sku->sku, $font, 1);
        $skuFontSize = number_format(min(8, 250 / max(1, $widthAtOnePoint)), 2, '.', '');
        $png = base64_encode($this->png($barcode->code));
        $pdf->loadHtml('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><style>@page{margin:12pt}body{text-align:center;font-size:8pt}h1{font-size:10pt;line-height:13pt;margin:0}.name{font-size:8pt;line-height:10pt;height:20pt;overflow-wrap:break-word}.sku{font-size:'.$skuFontSize.'pt;line-height:12pt;height:12pt;white-space:nowrap}img{width:228pt}p{margin:1pt;line-height:10pt}small{font-size:6pt;line-height:8pt}</style><h1>Etiqueta interna - TESTE</h1><p class="name">'.$name.'</p><p class="sku">SKU '.$internalSku.'</p><img src="data:image/png;base64,'.$png.'" alt="Code128"><p>'.$code.'</p><small>Código interno, não é GTIN. Homologar leitura e impressão antes do uso.</small></html>');
        $pdf->setPaper([0, 0, 283.46, 170.08]); // 100 × 60 mm, somente teste.
        $pdf->render();

        return $pdf->output();
    }
}
