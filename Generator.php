<?php declare(strict_types=1);

namespace Erp\Helper\General\Xls;

use Erp\Filters\DF;
use Erp\Model\Images\ImagesService;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class Generator
{
	/**
	 *
	 * @throws BadRequestException
	 * @throws ReaderException
	 * @throws Exception
	 */
	public static function getPHPSpreadsheet(string $templateDir, string $templateFilename, string $filename, ?XlsHeader $xlsHeader, callable $dataCallback): FileResponse
	{
		$excel = (IOFactory::createReader('Xlsx'))->load($templateDir . $templateFilename.'.template.xlsx');
		$excel->setActiveSheetIndex(0);

		/** @var Worksheet $sheet */
		try {
			$sheet = $excel->getActiveSheet();

			if ($xlsHeader) {
				$excel->getActiveSheet()->setTitle($xlsHeader->getSheetTitle());
				$sheet->mergeCells('B2:B5');
				$sheet->mergeCells('D2:G3');
				$sheet->mergeCells('D4:G4');
				$sheet->setCellValue('C2', $GLOBALS['application']->name);
				$sheet->setCellValue('C3', $GLOBALS['application']->company_street);
				$sheet->setCellValue('C4', $GLOBALS['application']->company_city . ', ' . $GLOBALS['application']->company_zipcode);
				$sheet->setCellValue('D2',  $xlsHeader->getHeaderTitle())->getStyle('D2')->getFont()->setSize(14)->setBold(true);
				$sheet->setCellValue('D4', $xlsHeader->getDateInfo());
				self::setLogoToXls($excel->getActiveSheet(), 'B2', $xlsHeader->getLogoDimensions());
			}

			$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
			$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
			$sheet->getPageSetup()->setFitToPage(TRUE);
		} catch (Exception) {
		}

		$dataCallback($sheet, $excel, $xlsHeader);

		$responseFilename = $filename.'.xlsx';
		$responseFile = TEMP_DIR.'cache/'.$responseFilename;

		IOFactory::createWriter($excel, 'Xlsx')->save($responseFile);

		return new FileResponse($responseFile, $responseFilename);
	}

	public static function alignment(Worksheet $sheet, string $pArea, string $alignment): void
	{
		$sheet->getStyle($pArea)->getAlignment()->setHorizontal($alignment);
	}

	/**
  * @param string $border Border::BORDER_THICK, BORDER_THIN, ....
  * @param string $where outline, allborders
  */
 public static function border(Worksheet $sheet, string $pArea, string $border, string $where = 'outline'): void
	{
		$sheet->getStyle($pArea)->applyFromArray(['borders' => [$where => ['borderStyle' => $border]]]);
	}

	public static function getRangeAtoZZ(): array
	{
		$rangeAtoZZ = range('A', 'Z');
		foreach (range('A', 'Z') as $firstLetter) {
			foreach (range('A', 'Z') as $secondLetter) {
				$rangeAtoZZ[] = $firstLetter.$secondLetter;
			}
		}

		return $rangeAtoZZ;
	}

	/**
	 * @throws Exception
	 */
	public static function setLogoToXls(Worksheet $sheet, string $xlsCoordinate, array $logoDimensions): void
	{
		if ($GLOBALS['application']->image) {
			$objDrawing = new Drawing();
			$objDrawing->setPath(ImagesService::saveThumbnail(
				ImagesService::getThumbnailName($GLOBALS['application']->image, $logoDimensions['width'], $logoDimensions['height'], 'f', 80)));
			$objDrawing->setCoordinates($xlsCoordinate);
			$objDrawing->setOffsetX(2);
			$objDrawing->setOffsetY(2);
			$objDrawing->setWidth($logoDimensions['width']);
			$objDrawing->setHeight($logoDimensions['height']);
			$objDrawing->setWorksheet($sheet);
		}
	}

	/**
	 * @throws Exception
	 */
	public static function addSignature(Worksheet $sheet, XlsHeader $header, string $x, int $y): void
	{
		[$signedAdmin, $filename, $datetime] = $header->getSignature();
		if ($filename) {
			$sheet->setCellValue($x . $y, $signedAdmin->name . ' ' . ($datetime ? $datetime->format(DF::HUMAN_FORMAT) : ''));
			$objDrawing = new Drawing();
			$objDrawing->setPath(FS_DIR.'/signatures/'.$filename);
			$objDrawing->setCoordinates($x . ($y + 1));
			$objDrawing->setOffsetX(2);
			$objDrawing->setOffsetY(2);
			$objDrawing->setWidth(200);
			$objDrawing->setHeight(200);
			$objDrawing->setWorksheet($sheet);
		}
	}

	public static function getLastColumn($columnCount): string
	{
		return self::getRangeAtoZZ()[$columnCount];
	}

	public static function applyCommonStyles($sheet, $firstRow, $columnCount, $entitiesCount, bool $hasTotal): array
	{
		$lastRow = $hasTotal ? $entitiesCount + $firstRow + 1 : $entitiesCount + $firstRow;
		$lastColumn = self::getLastColumn($columnCount);

		$sheet->getStyle('B'.($firstRow+1).':'.$lastColumn.$lastRow)
			->applyFromArray(['borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]]);
		$sheet->getStyle('B'.$firstRow.':'.$lastColumn.$firstRow)
			->applyFromArray(['borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]]]);

		if ($hasTotal) {
			$sheet->getStyle('B'.$lastRow.':'.$lastColumn.$lastRow)
				->applyFromArray(['borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
		}

		$sheet->freezePaneByColumnAndRow(0, $firstRow + 1);

		if($GLOBALS['application']->image) {
			$sheet->getPageSetup()->setPrintArea('B2:'.$lastColumn.$lastRow);
		} else {
			$sheet->getPageSetup()->setPrintArea('B'.$firstRow.':'.$lastColumn.$lastRow);
		}

		return [$lastRow, $lastColumn];
	}
}
