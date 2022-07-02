<?php

namespace App\Controller;

use App\Entity\Puppy;
use App\Form\ExcelFormatType;
use App\Form\PuppyType;
use App\Repository\PuppyRepository;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tinify\Tinify;
use PhpOffice\PhpSpreadsheet\Reader\Csv as ReaderCsv;
use PhpOffice\PhpSpreadsheet\Reader\Ods as ReaderOds;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;



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
     * @Route("/download", name="download", methods={"GET"})
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

    /**
     * @Route("/export", name="export")
     */
    public function exportAction()
        {

            $format = 'xlsx';
            $filename = 'Browser_characteristics.'.$format;

            $spreadsheet = $this->createSpreadsheet();


            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $writer = new Xlsx($spreadsheet);

            $response = new StreamedResponse();
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache', true);
            $response->headers->addCacheControlDirective('must-revalidate', true);
            $response->setCallback(function() use ($writer) {
                $writer->save('php://output');
            });

            return $response;

        }

    /**
     * @Route("/compresstest", name="export")
     */
    public function testAction()
        {


            $destination_url = $this->getParameter('images_directory');
            $source_img = $this->getParameter('images_directory_source');

            for ($i=1;$i<=10;$i++){
                $quality =$i*10;
                $this->compressImage($source_img, $destination_url.'/democompressed'.$quality.'.png',$quality );
            }


            $error = "Image Compressed successfully quality";

            dump($error);die;


            return new Response("Images downloaded successfully");

        }


    public function createSpreadsheet()
    {


        $spreadsheet = new Spreadsheet();
        // Get active sheet - it is also possible to retrieve a specific sheet
        $sheet = $spreadsheet->getActiveSheet();

        // Set cell name and merge cells


        // Set column names
        $columnNames = [
            'Type',
            'Source',
            'Path',
            'Size(bytes)',
        ];
        $columnLetter = 'A';
        foreach ($columnNames as $columnName) {
            // Allow to access AA column if needed and more
            $sheet->setCellValue($columnLetter.'1', $columnName);
            $columnLetter++;
        }

        // Add data for each column
        $columnValues = [
            ['Google Chrome', 'Google Inc.', 'September 2, 2008', 'C++'],
            ['Firefox', 'Mozilla Foundation', 'September 23, 2002', 'C++, JavaScript, C, HTML, Rust'],
            ['Microsoft Edge', 'Microsoft', 'July 29, 2015', 'C++'],
            ['Safari', 'Apple', 'January 7, 2003', 'C++, Objective-C'],
            ['Opera', 'Opera Software', '1994', 'C++'],
            ['Maxthon', 'Maxthon International Ltd', 'July 23, 2007', 'C++'],
            ['Flock', 'Flock Inc.', '2005', 'C++, XML, XBL, JavaScript'],
        ];

        $i = 2; // Beginning row for active sheet
        foreach ($columnValues as $columnValue) {
            $columnLetter = 'A';
            foreach ($columnValue as $value) {
                $sheet->setCellValue($columnLetter.$i, $value);
                $columnLetter++;
            }
            $i++;
        }

        return $spreadsheet;
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

    private function compressImage( $source, $destination, $quality)
    {
        $info = getimagesize($source);

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($source);

        elseif ($info['mime'] == 'image/gif')
            $image = imagecreatefromgif($source);

        elseif ($info['mime'] == 'image/png')
            $image = imagecreatefrompng($source);

        if(isset($image))
            imagejpeg($image, $destination, $quality);

        return $destination;

    }


}
