<?php
/*
	Original name: PresentationState.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ..\PresentationStateHandler.php
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\PresentationStateHandler;


class PresentationState extends PresentationStateHandler
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
			(
			"anotationToDicom" => array(
				"description" => "create PR dicom file",
				"access" => "remote"),
			"deleteFile" => array(
				"description" => "delete temp dicom file",
				"access" => "remote"),
			"getStudyPRList" => array(
				"description" => "",
				"access" => "remote"),
			"getImagePrlist" => array(
				"description" => "",
				"access" => "remote"),
			"annotationToJpg" => array(
				"description" => "",
				"access" => "remote")
			);
	}

	/**
	 * annotation structure to dicom PR
	 *
	 * @param string $instanceuid - image uid
	 * @param array $annotation - sample array(
	 * 						'description'=>"description" ,
	 * 						'title'=>'title' ,
	 * 						'annotations'=>array(
	 * 							array(
	 * 								'type'=> 'TEXT',
	 * 								'points'=>array(
	 * 									//multiple grapgic lines
	 * 									array('0','0','0','0')
	 * 								),
	 * 								'text'=>array(
	 * 									array(
	 * 										 //multiple text labels or boxes
	 * 										'text'=>'description',
	 * 										'textpos'=> array('0','0', '10', '12','12','12'),
	 * 										'textstyle'=>array('align'=>'LEFT')
	 * 									)
	 * 								)
	 * 							)
	 * 						)
	 * 			);
	 * return array
	 */
	public function anotationToDicom($instanceuid, $annotation)
	{
		return parent::anotationToDicom($instanceuid, $annotation);
	}

	/**
	 * returns images ids that has PR
	 * sampele: array('seriesid_that_have_pr'=>array('imageid1_that_have_pr',..))
	 *
	 * @param string $studyuid
	 * @return array
	 */
	public function getStudyPRList($studyuid)
	{
		return parent::getStudyPRList($studyuid);
	}

	/**
	 * delete temporary DICOM file
	 *
	 * @param string $file - file path
	 * return string
	 */
	public function deleteFile($file)
	{
		return parent::deleteFile($file);
	}
	/**
	 * collect annotation structure for image1 about all
	 * PR images that belongs to image1
	 *
	 * @param string $instanceuid - uid or pk
	 * @return string
	 */
	public function getImagePrlist($instanceuidpk)
	{
		return parent::getImagePrlist($instanceuidpk);
	}
	/**
	 * save anotation as jpg
	 * 
	 * @param array $data
	 * @param object $imageData - image bytearray as jpg
	 * @return array
	 */
	public function annotationToJpg($data, $imageData)
	{
		$this->log->asDump('begin ' . __METHOD__);
		$this->log->asDump('Annotation data: ', $data);
		
		if(empty($imageData))
		{
			$this->log->asErr(' missing jpg Image data');
			return array('error'=>'Missing jpg Image data');
		}
		$this->log->asDump("Image data: ". strlen($imageData->data));
		
		$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' .
		DIRECTORY_SEPARATOR . 'pr' . date("YmdHis") . rand(100000, 99999999) . '.tmp';
		@file_put_contents($path, $imageData->data);
		unset($imageData);
		$this->log->asDump('Image saved: '. $path);
		
		if(file_exists($path))
			$data['file'] = $path;
		else
		{
			$this->log->asErr('no image file to save');
			return array('error'=>'No image file to save');
		}
		$handle = new PresentationStateHandler();
		$return = $handle->jpgToDicom($data['instanseUid'], $data);
		$handle->deleteFile($path);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
