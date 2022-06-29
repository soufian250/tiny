<?php

namespace App\Controller;

use App\Entity\Puppy;
use App\Form\PuppyType;
use App\Repository\PuppyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tinify\Tinify;
use PhpOffice\PhpSpreadsheet\Reader\Csv as ReaderCsv;
use PhpOffice\PhpSpreadsheet\Reader\Ods as ReaderOds;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;


/**
 * @Route("/puppy")
 */
class PuppyController extends AbstractController
{
    /**
     * @Route("/", name="app_puppy_index", methods={"GET"})
     */
    public function index(PuppyRepository $puppyRepository)
    {
        return $this->render('puppy/index.html.twig', [
            'puppies' => $puppyRepository->findAll(),
        ]);
    }


    /**
     * @Route("/import", name="app_puppy_import", methods={"GET"})
     */
    public function import(PuppyRepository $puppyRepository )
    {

        $path = $this->getParameter('brochures_directory');
        $i = 0;
        if (is_dir($path)) {
            $files = array();
            foreach (scandir($path) as $key => $file) {
                $i++;
                if ($file !== '.' && $file !== '..') {
                    \Tinify\setKey("GWdhK6Ng41yphqynJCrdTC1431bnYjXF");
                     $source = \Tinify\fromFile($path.'/'.$file);
                     $source->toFile($file);
                }
            }
        }

        dump('OK');die;

    }

    /**
     * @Route("/filedata", name="get_data_from_spreadsheet", methods={"GET"})
     */
    public function getInfoFromSpreedSheet()
    {

        $filename = $this->getParameter('list_images_excel');
        $downloadedImagesFolder =  $this->getParameter('downloaded_images');
        if (!file_exists($filename)) {
            throw new \Exception('File does not exist');
        }


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



        return $this->render('puppy/data.html.twig', [
            'data' => $data,
        ]);


    }



    /**
     * @Route("/new", name="app_puppy_new", methods={"GET", "POST"})
     */
    public function new(Request $request, PuppyRepository $puppyRepository)
    {
        $puppy = new Puppy();
        $form = $this->createForm(PuppyType::class, $puppy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $puppyRepository->add($puppy, true);

            return $this->redirectToRoute('app_puppy_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('puppy/new.html.twig', [
            'puppy' => $puppy,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_puppy_show", methods={"GET"})
     */
    public function show(Puppy $puppy)
    {
        return $this->render('puppy/show.html.twig', [
            'puppy' => $puppy,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_puppy_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Puppy $puppy, PuppyRepository $puppyRepository)
    {
        $form = $this->createForm(PuppyType::class, $puppy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $puppyRepository->add($puppy, true);

            return $this->redirectToRoute('app_puppy_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('puppy/edit.html.twig', [
            'puppy' => $puppy,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_puppy_delete", methods={"POST"})
     */
    public function delete(Request $request, Puppy $puppy, PuppyRepository $puppyRepository)
    {
        if ($this->isCsrfTokenValid('delete'.$puppy->getId(), $request->request->get('_token'))) {
            $puppyRepository->remove($puppy, true);
        }

        return $this->redirectToRoute('app_puppy_index', [], Response::HTTP_SEE_OTHER);
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
