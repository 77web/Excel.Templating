<?php


namespace Excel\Templating\Service;

use Excel\Templating\Service\Util\Sheet as SheetUtil;

class SheetRemover implements Service
{
    /**
     * @param \ZipArchive $output
     * @param array $sheetNamesToDelete
     */
    public function execute(\ZipArchive $output, array $sheetNamesToDelete = null)
    {
        $relIdsToDelete = SheetUtil::convertNamesToRelIds($output, $sheetNamesToDelete);
        $sheetXmlsToDelete = SheetUtil::convertRelIdsToXmls($output, $relIdsToDelete);

        // delete entry for deleted sheet in xl/workbook.xml
        $workbookDom = new \DOMDocument();
        $workbookDom->loadXml($output->getFromName('xl/workbook.xml'));
        $workbookXPath = new \DOMXPath($workbookDom);
        $workbookXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXPath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationshipts');
        foreach ($relIdsToDelete as $relId) {
            $sheetNodeList = $workbookXPath->query('//x:workbook/x:sheets/x:sheet[@r:id="'.$relId.'"]');
            if (1 === $sheetNodeList->length) {
                /** @var \DOMElement $sheetNode */
                $sheetNode = $sheetNodeList->item(0);

                $sheetNode->parentNode->removeChild($sheetNode);
            }
        }
        $output->addFromString('xl/workbook.xml', $workbookDom->saveXML());

        // delete entry for deleted sheet in xl/_rels/workbook.xml.rels
        $relsDom = new \DOMDocument();
        $relsDom->loadXml($output->getFromName('xl/_rels/workbook.xml.rels'));
        $relsXPath = new \DOMXpath($relsDom);
        $relsXPath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relIdsToDelete as $relId) {
            $relNodeList = $relsXPath->query('//r:Relationships/r:Relationship[@Id="'.$relId.'"]');
            if (1 === $relNodeList->length) {
                /** @var \DOMElement $relNode */
                $relNode = $relNodeList->item(0);

                $relNode->parentNode->removeChild($relNode);
            }
        }
        $output->addFromString('xl/_rels/workbook.xml.rels', $relsDom->saveXML());

        // remove entry for deleted sheet in [Content_Types].xml
        $ctypesDom = new \DOMDocument;
        $ctypesDom->loadXml($output->getFromName('[Content_Types].xml'));
        $ctypesXPath = new \DOMXPath($ctypesDom);
        $ctypesXPath->registerNamespace('t', 'http://schemas.openxmlformats.org/package/2006/content-types');
        foreach ($sheetXmlsToDelete as $sheet) {
            $typeNodeList = $ctypesXPath->query('//t:Types/t:Override[contains(@PartName, "'.$sheet.'")]');
            if (1 === $typeNodeList->length) {
                /** @var \DOMElement $typeNode */
                $typeNode = $typeNodeList->item(0);

                $typeNode->parentNode->removeChild($typeNode);
            }
        }
        $output->addFromString('[Content_Types].xml', $ctypesDom->saveXML());

        // delete worksheet xml, worksheet rel xml
        foreach ($sheetXmlsToDelete as $sheetXml) {
            $output->deleteName('xl/worksheets/'.$sheetXml);
            $output->deleteName('xl/worksheets/_rels/'.$sheetXml.'.rels');
        }
    }
} 
