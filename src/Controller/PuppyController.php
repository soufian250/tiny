<?php

namespace App\Controller;

use App\Entity\Puppy;
use App\Form\PuppyType;
use App\Repository\PuppyRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tinify\Tinify;
use PhpOffice\PhpSpreadsheet\Reader\Csv as ReaderCsv;
use PhpOffice\PhpSpreadsheet\Reader\Ods as ReaderOds;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;


/**
 * @Route("/tiny")
 */
class PuppyController extends AbstractController
{

    /**
     * @Route("/compress", name="compress", methods={"GET"})
     */
    public function compress()
    {

        $executionStartTime = microtime(true);
        $images_directory = $this->getParameter('images_directory');

        if (is_dir($images_directory)) {
            foreach (scandir($images_directory) as $file) {
                if ($file !== '.' && $file !== '..') {

                    //TODO: Wrap this code in Exception handler
                    \Tinify\setKey("GWdhK6Ng41yphqynJCrdTC1431bnYjXF");
                    $source = \Tinify\fromFile($images_directory.'/'.$file);
                    $source->toFile('CompresedImages/'.$file);

                }
            }
        }

        $executionEndTime = microtime(true);
        $seconds = $executionEndTime - $executionStartTime;

        return new Response("Images compressed successfully in $seconds");

    }

    /**
     * @Route("/getImages", name="get_data_from_spreadsheet", methods={"GET"})
     */
    public function getInfoFromSpreedSheet()
    {

        $filename = $this->getParameter('list_images_excel');

        if (!file_exists($filename)) {
            throw new \Exception('File does not exist');
        }

        $executionStartTime = microtime(true);
        $spreadsheet = $this->readFile($filename);
        $data = $this->createDataFromSpreadsheet($spreadsheet);


        foreach ($data as $cell ){
            foreach ($cell['columnValues'] as $key => $cell2 ){
                $link = $cell2[2];
                $imageName = substr($cell2[2], strrpos($cell2[2], '/') + 1);
                if ($link != null){
                    $contentOrFalseOnFailure   = file_get_contents($link);
                    file_put_contents('downloadedImages/'.$imageName, $contentOrFalseOnFailure);
                }
            }
        }

        $executionEndTime = microtime(true);
        $seconds = $executionEndTime - $executionStartTime;
         return new Response("Images downloaded successfully in $seconds");

    }


    protected function readFile($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'ods':
                $reader = new ReaderOds();
                break;
            case 'xlsx':
                $reader = new ReaderXlsx();
                break;
            case 'csv':
                $reader = new ReaderCsv();
                break;
            default:
                throw new \Exception('Invalid extension');
        }
        $reader->setReadDataOnly(true);
        return $reader->load($filename);
    }

    private function createDataFromSpreadsheet($spreadsheet)
    {

        {
            $data = [];
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $worksheetTitle = $worksheet->getTitle();
                $data[$worksheetTitle] = [
                    'columnNames' => [],
                    'columnValues' => [],
                ];
                foreach ($worksheet->getRowIterator() as $row) {
                    $rowIndex = $row->getRowIndex();
                    if ($rowIndex > 1) {
                        $data[$worksheetTitle]['columnValues'][$rowIndex] = [];
                    }
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false); // Loop over all cells, even if it is not set
                    foreach ($cellIterator as $cell) {
                        if ($rowIndex === 1) {
                            $data[$worksheetTitle]['columnNames'][] = $cell->getCalculatedValue();
                        }
                        if ($rowIndex > 1) {
                            $data[$worksheetTitle]['columnValues'][$rowIndex][] = $cell->getCalculatedValue();
                        }
                    }
                }
            }

            return $data;
        }
    }


}
