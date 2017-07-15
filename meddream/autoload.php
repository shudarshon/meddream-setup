<?php
/*
	Original name: autoload.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Autoloader for some refactored classes
 */

function autoloader($name)
{
	$classFiles = array(
		'MedDreamCmd' => 'MedDreamCmd.php',
		'RawAdam7' => 'RawAdam7.php',
		'RawAdam7Exception' => 'RawAdam7Exception.php',
		'Softneta\MedDream\Core\AuthDB' => 'AuthDB.php',
		'Softneta\MedDream\Core\CharacterSet' => 'CharacterSet.php',
		'Softneta\MedDream\Core\ForeignPath' => 'ForeignPath.php',
		'Softneta\MedDream\Core\Study' => 'Study.php',
		'Softneta\MedDream\Core\System' => 'System.php',
		'Softneta\MedDream\Core\SR' => 'SR.php',
		'Softneta\MedDream\Core\MedReport' => 'MedReport.php',
		'Softneta\MedDream\Core\PresentationStateHandler' => 'PresentationStateHandler.php',
		'Softneta\MedDream\Core\SOP\DicomCommon' => 'sop/DicomCommon.php',
		'Softneta\MedDream\Core\SOP\PresentationState' => 'sop/PresentationState.php',
		'Softneta\MedDream\Core\SOP\SecondaryCapture' => 'sop/SecondaryCapture.php',
		'Softneta\MedDream\Core\Database\DbIface' => 'db/DbIface.php',
		'Softneta\MedDream\Core\Database\DbAbstract' => 'db/DbAbstract.php',
		'Softneta\MedDream\Core\Database\DB' => 'db/DB.php',
		'Softneta\MedDream\Core\Pacs\Loader' => 'pacs/Loader.php',
		'Softneta\MedDream\Core\Pacs\CommonDataImporter' => 'pacs/CommonDataImporter.php',
		'Softneta\MedDream\Core\Pacs\ConfigIface' => 'pacs/ConfigIface.php',
		'Softneta\MedDream\Core\Pacs\ConfigAbstract' => 'pacs/ConfigAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsConfig' => 'pacs/PacsConfig.php',
		'Softneta\MedDream\Core\Pacs\SharedIface' => 'pacs/SharedIface.php',
		'Softneta\MedDream\Core\Pacs\SharedAbstract' => 'pacs/SharedAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsShared' => 'pacs/PacsShared.php',
		'Softneta\MedDream\Core\Pacs\AuthIface' => 'pacs/AuthIface.php',
		'Softneta\MedDream\Core\Pacs\AuthAbstract' => 'pacs/AuthAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsAuth' => 'pacs/PacsAuth.php',
		'Softneta\MedDream\Core\Pacs\SearchIface' => 'pacs/SearchIface.php',
		'Softneta\MedDream\Core\Pacs\SearchAbstract' => 'pacs/SearchAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsSearch' => 'pacs/PacsSearch.php',
		'Softneta\MedDream\Core\Pacs\StructureIface' => 'pacs/StructureIface.php',
		'Softneta\MedDream\Core\Pacs\StructureAbstract' => 'pacs/StructureAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsStructure' => 'pacs/PacsStructure.php',
		'Softneta\MedDream\Core\Pacs\PreloadIface' => 'pacs/PreloadIface.php',
		'Softneta\MedDream\Core\Pacs\PreloadAbstract' => 'pacs/PreloadAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsPreload' => 'pacs/PacsPreload.php',
		'Softneta\MedDream\Core\Pacs\ExportIface' => 'pacs/ExportIface.php',
		'Softneta\MedDream\Core\Pacs\ExportAbstract' => 'pacs/ExportAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsExport' => 'pacs/PacsExport.php',
		'Softneta\MedDream\Core\Pacs\ForwardIface' => 'pacs/ForwardIface.php',
		'Softneta\MedDream\Core\Pacs\ForwardAbstract' => 'pacs/ForwardAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsForward' => 'pacs/PacsForward.php',
		'Softneta\MedDream\Core\Pacs\ReportIface' => 'pacs/ReportIface.php',
		'Softneta\MedDream\Core\Pacs\ReportAbstract' => 'pacs/ReportAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsReport' => 'pacs/PacsReport.php',
		'Softneta\MedDream\Core\Pacs\AnnotationIface' => 'pacs/AnnotationIface.php',
		'Softneta\MedDream\Core\Pacs\AnnotationAbstract' => 'pacs/AnnotationAbstract.php',
		'Softneta\MedDream\Core\Pacs\PacsAnnotation' => 'pacs/PacsAnnotation.php',
		'Softneta\MedDream\Core\Pacs\PACS' => 'pacs/PACS.php',
		'Softneta\MedDream\Core\PacsGateway\PacsGw' => 'pacsgateway/PacsGw.php',
		'Softneta\MedDream\Core\PacsGateway\HttpClient' => 'pacsgateway/HttpClient.php',
		'Softneta\MedDream\Core\Backend' => 'Backend.php',
		'Softneta\MedDream\Core\Audit' => 'Audit.php',
		'Softneta\MedDream\Core\Configurable' => 'Configurable.php',
		'Softneta\MedDream\Core\Configuration' => 'Configuration.php',
		'Softneta\MedDream\Core\Constants' => 'Constants.php',
		'Softneta\MedDream\Core\Translation' => 'Translation.php',
		'Softneta\MedDream\Core\Logging' => 'Logging.php',
		'Softneta\MedDream\Core\RetrieveStudy' => 'RetrieveStudy.php',
		'Softneta\MedDream\Core\DicomTags' => 'DicomTags.php',
		'Softneta\MedDream\Core\DICOM\DicomTagParser' => 'dicom/DicomTagParser.php',
		'Softneta\MedDream\Core\ECG\ECGImage' => 'ecg/ECGImage.php',
		'Softneta\MedDream\Core\ECG\ECGLoader' => 'ecg/ECGLoader.php',
		'Softneta\MedDream\Core\ECG\ECGStudy' => 'ecg/ECGStudy.php',
		'Softneta\MedDream\Core\ECG\ECGWaveform' => 'ecg/ECGWaveform.php',
		'Softneta\MedDream\Core\ECG\ScaledImage' => 'ecg/ScaledImage.php',
		'Softneta\MedDream\Core\Branding' => 'Branding.php',
		'Softneta\MedDream\Core\PathUtils' => 'PathUtils.php',
		'Softneta\MedDream\Core\QueryRetrieve\QR' => 'qr/QR.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrCache' => 'qr/QrCache.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrBasicIface' => 'qr/QrBasicIface.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrNonblockingIface' => 'qr/QrNonblockingIface.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrAbstract' => 'qr/QrAbstract.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrToolkit' => 'qr/QrToolkit.php',
		'Softneta\MedDream\Core\QueryRetrieve\QrToolkitWrapper' => 'qr/QrToolkitWrapper.php',
		'Softneta\MedDream\Core\InstallValidator' => 'InstallValidator.php',
		'Softneta\MedDream\Core\SendToDicomLibrary' => 'SendToDicomLibrary.php',
		'Softneta\MedDream\Core\Jobs' => 'Jobs.php',
		'Softneta\MedDream\Core\HttpUtils' => 'HttpUtils.php',
		'Softneta\MedDream\Core\ReportManager' => 'ReportManager.php',
		'Softneta\MedDream\Core\Branding' => 'Branding.php',
	);

	if (isset($classFiles[$name]))
		require_once(__DIR__ . '/' . $classFiles[$name]);
}

spl_autoload_register('autoloader');
