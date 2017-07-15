<?php
/*
	Original name: SecondaryCapture.php

	Copyright: Softneta, 2014

	Classification: public

	Owner: tb <tomas.burba@softneta.lt>

	Contributors:
		kf <kestutis.freigofas@softneta.lt>
		tb <tomas.burba@softneta.lt>

	Description:
		Server-side creation of a SC SOP Class DICOM file
 */

namespace Softneta\MedDream\Core\SOP;

use Softneta\MedDream\Core\SOP\DicomCommon;

class SecondaryCapture extends DicomCommon
{
	public $xml2dcm = '';
	public $cfgdata = array();
	public $cfgfilename = '';
	public $dcmfilename = '';
	public $filename = '';
	public $transfersyntax = '1.2.840.10008.1.2.4.50';
	public $sopuid = '1.2.840.10008.5.1.4.1.1.7';
	public $rootuid = '1.3.6.1.4.1.44316';
	public $swversion = '';
	public $implementatinversion = 'SC2DCM';
	public $manufacturer = 'Softneta';

	/**
     * convert parset data to dicom
	 *
	 *@param array $data - parsed array
     *return boolean true/false
     */
	function buildDicom($data)
	{
		if (count($data) == 0)
			return false;
			
		$this->data = $data;
		//validate
		$this->validateData();
		//if error in building dicom config
		if ($this->error != '')
		{
			restore_error_handler();
			return false;
		}
		//set extra data	
		$this->data['instancedate'] = date("Ymd");
		$this->data['instancetime'] = date("His");		
		$this->data['contentdate'] = $this->data['studydate'];
		$this->data['contenttime'] = $this->data['studytime'];
			
		if ($this->data['contentdate'] == '')
			$this->data['contentdate'] = $this->data['instancedate'];
		if ($this->data['contenttime'] == '')
			$this->data['contenttime'] = $this->data['instancetime'];
				
		if ($this->data['studyid'] == '')
			$this->data['studyid'] = $this->data['studydate'].$this->data['studytime'];
		//set modules
		$this->setPatientModule();
		$this->setGeneralStudyModule();
		$this->setGeneralSeriesModule();
		$this->setGeneralEquipmentModule();
		$this->setPresentationStateRelationshipModule();//custom - from PR
		$this->setPresentationStateIdentificationModule();//custom - from PR
		$this->setSCEquipmentModule();
		$this->setGeneralImageModule();
		$this->setImagePixelModule();
		$this->setCineModule();
		$this->setMultiframeModule();
		$this->setSCMultiframeImageModule();
		$this->setSOPCommonModule();
		$this->setOtherMetadata();
			
		//if error in building dicom config
		if ($this->error != '')
		{
			restore_error_handler();
			return false;
		}
		
		if (count($this->cfgdata) >0)
		{		
			$this->cfgfilename = str_replace("\\",'/', $this->cfgfilename);
			$this->dcmfilename = str_replace("\\",'/', $this->dcmfilename);
			$this->convertDicom();
		}
		return false;
	}
	/**
     * validate parsed data
	 *
     */
	function validateData()
	{
		$this->validateName('patientname');
		$this->validateName('referringphysician');
		$this->validateStringSize('patientid');
		$this->validateStringSize('studyid',16);
		$this->validateStringSize('accessionnum',16);
		$this->validateStringSize('description');
		$this->validateStringSize('presentationdescription', 64);
		$this->validateStringSize('contentlabel', 16);
		$this->validateStringSize('contentlabelAsDescription', 64);
		
		$this->data['sex'] = $this->validateSex('sex');

		if (!isset($this->data['seriesnumber']))
			$this->data['seriesnumber'] = 1;
		if (!isset($this->data['instancenumber']))
			$this->data['instancenumber'] = 1;

		if ($this->data['birthdate'] != '')
		{
			$date = $this->validateDate($this->data['birthdate']);
			$this->data['birthdate'] = $date;
		}
		if ($this->data['studydate'] != '')
		{
			$date = $this->validateDate($this->data['studydate']);
			$this->data['studydate'] = $date;
		}
		if ($this->data['studytime'] != '')
		{
			$time = $this->validateTime($this->data['studytime']);
			$this->data['studytime'] = $time;
		}	
		if ($this->data['presentationcreationdate'] != '')
		{
			$date = $this->validateDate($this->data['presentationcreationdate']);
			$this->data['presentationcreationdate'] = $date;
		}
		if ($this->data['presentationcreationtime'] != '')
		{
			$time = $this->validateTime($this->data['presentationcreationtime']);
			$this->data['presentationcreationtime'] = $time;
		}
	}
	/**
     * convert to dicom
	 *
     */	
	function convertDicom()
	{
		$data = '';
		if (count($this->cfgdata) > 0)
		{
			ksort($this->cfgdata);
			$data = '<?xml version="1.0" encoding="UTF-8"?>';
			$data .= '<dicom>';
			$data .= $this->formXML($this->cfgdata);
			$data .= $this->setDataXML($this->filename);
			$data .= '</dicom>';
		}
		
		@file_put_contents($this->cfgfilename, $data);
		unset($data);
		
		if (file_exists($this->cfgfilename))
		{
			$comand = '"'.$this->xml2dcm.'" -x "'.$this->cfgfilename.'" -o "'.$this->dcmfilename.'" 2>&1';
			
			$this->error = self::tryExec($comand, $out);
			if (!file_exists($this->dcmfilename))
				$this->setError('failed to create dicom');
				
			if ($this->error != '')
				$this->setError(implode("\n", $out));
			else
			{
				@unlink($this->cfgfilename);
				//@unlink($this->filename);
			}
		}
		else
			$this->setError('Failed to create configuration file');
	}
	/**
     * add image data as xml string
	 *
	 *$param string $file - filename
	 *
	 *return string - xml
     */	
	function setDataXML($file)
	{
		$data = file_get_contents($file);
		$len = strlen($data);
		$data = strtoupper(chunk_split(bin2hex($data),2,"\\"));
		
		return '<attr vr="OB" tag="7FE00010"><item len="0"/><item len="'.$len.'">'.substr($data,0,-1).'</item></attr>';
	}
	/**
     * form xml from tags
	 *
	 *$param array $taglist - tag list
	 *
	 *return string - xml
     */	
	function formXML($taglist)
	{
		$str = '';
		if (count($taglist) >0)
		{
			foreach ($taglist as $key=>$value)
			{
				$prefix = 'attr';
				if (strlen($key) != 8)
				{
					$prefix = 'item';
					$str .= '<'.$prefix;
				}
				else
					$str .= '<'.$prefix.' tag="'.$key.'"';
				
				if (is_array($value))
					$data = $this->formXML($value);
				else
				{
					
					$data = $value;
				}
				if ($data == '')
					$str .='/>';
				else
					$str .= '>'.$data.'</'.$prefix.'>';
			}
		}
		return $str;
	}
	/**
     * form xml from tags
	 *
	 *$param string $tag - tag name
	 *$param string $value - tag list
     */	
	function addTag($tag, $value)
	{
		//$this->cfgdata .= $tag.':'.$value."\r\n"; 
		//handle multiple tags
		$tags = explode('/',$tag);
		$this->cfgdata = $this->addTags($this->cfgdata, $tags, $this->escapeTagalue((string)$value));
	}
	/**
     * recursion to form multiple tags into multi-mention array
	 *
	 *$param string $data - tag array
	 *$param string $tag - tag name
	 *$param string $value - tag list
     */	
	function addTags($data, $tags, $value) 
	{
		$count = count($tags);
		$tag = array_shift($tags);
		if ( $count  >1)
		{
			if (!isset($data[$tag]))
				$data[$tag] = array();
				
			$data[$tag] = $this->addTags($data[$tag], $tags, $value);
		}
		else
			$data[$tag] = $value;
			
		return $data;
	}
	/**
     * set module tags
	 *
     */
	function setPatientModule()
	{
		//Patient's Name 64 chars maximum per component group - 2
		//need to limit len and from wl
		$this->addTag('00100010', $this->data['patientname']);
		//Patient ID 64 chars - 2 and from wl
		$this->addTag('00100020', $this->data['patientid']);
		//Patient's Birth Date - 2 and from wl
		$this->addTag('00100030', $this->data['birthdate']);
		//Patient's Sex Enumerated Values: M = male F =female O = other - 2
		$this->addTag('00100040', $this->data['sex']);
	}
	/**
     * set module tags
	 *
     */
	function setGeneralStudyModule()
	{
		//Study Instance UID -1 and from wl
		$this->addTag('0020000D', $this->data['studyuuid']);
		//Study Date - 2 and from wl
		$this->addTag('00080020', $this->data['studydate']);
		//Study Time - 2 and from wl
		$this->addTag('00080030', $this->data['studytime']);
		//Referring Physician's Name - can be empty - 2 from wl or empty
		$this->addTag('00080090', $this->data['referringphysician']);
		//Study ID - 2 
		$this->addTag('00200010', $this->data['studyid']);
		//Accession Number - 2 
		$this->addTag('00080050', $this->data['accessionnum']);
		//Study Description - 3 - from wl 0032,1060
		if (isset($this->data['description']))
			if ($this->data['description'] !='')
				$this->addTag('00081030', $this->data['description']);
	}
	/**
     * set module tags
	 *
     */
	function setGeneralSeriesModule()
	{
		//Modality -1
		$this->addTag('00080060', $this->data['modality']);
		//Series Instance UID -1
		$this->addTag('0020000E', $this->data['seriesuuid']);
		//Transfer Syntax UID -1 and from wl
		$this->addTag('00020010', $this->transfersyntax);
		//Series Number -2
		$this->addTag('00200011', $this->data['seriesnumber']);
		//Laterality Enumerated Values: R = right L= left for series - 2c
		$this->addTag('00200060', $this->data['laterality']);
		//Series Date - 3
		//$this->addTag('00080021', $this->data['seriesdate']);
		//Series Time -3
		//$this->addTag('00080031', $this->data['seriestime']);
		//Body Part Examined -3
		//$this->addTag('00180015', $this->data['bodypart']);
		//Series Description -3
		$this->addTag('0008103E', $this->data['seriesdescription']);
	}
	/**
     * set module tags
	 *
     */
	function setPresentationStateRelationshipModule()
	{
		if (isset($this->data['referencedseriessequence']))
			for ($i = 0; $i < count($this->data['referencedseriessequence']); $i++)
			{
				//Referenced Series Sequence - 1
				//0008,1115
				//Series Instance UID -1
				$this->addTag('00081115/' . $i . '/0020000E', $this->data['referencedseriessequence'][$i]['seriesuid']);
				//Referenced Instance Sequence -1
				//0008,114A
				if (isset($this->data['referencedseriessequence'][$i]['imagesequence']))
					for ($j = 0; $j < count($this->data['referencedseriessequence'][$i]['imagesequence']); $j++)
					{
						//Referenced SOP Class UID -1
						$this->addTag('00081115/' . $i . '/0008114A/' . $j . '/00081150',
							$this->data['referencedseriessequence'][$i]['imagesequence'][$j]['sopud']);
						//Referenced SOP Instance UID - 1
						$this->addTag('00081115/' . $i . '/0008114A/' . $j . '/00081155',
							$this->data['referencedseriessequence'][$i]['imagesequence'][$j]['instanceuid']);
					}
			}
	}
	/**
     * set module tags
	 *
     */
	function setGeneralEquipmentModule()
	{
		//Manufacturer -2
		$this->addTag('00080070', $this->manufacturer);
		//Software Versions -1
		if ($this->swversion != '')
			$this->addTag('00181020', $this->swversion);
	}
	/**
     * set module tags
	 *
     */
	function setSCEquipmentModule()
	{
		//Conversion Type - 1
		$this->addTag('00080064', 'DI');
	}
	/**
     * set module tags
	 *
     */
	function setGeneralImageModule()
	{
		//Instance Number - 2
		$this->addTag('00200013', $this->data['instancenumber']);
		//Patient Orientation - C.7.6.1.1.1 - 2c - empty
		$this->addTag('00200020', '');
		//Content Time - 1 image creaton date time
		$this->addTag('00080033',$this->data['contenttime']);
		//Content Date -1
		$this->addTag('00080023', $this->data['contentdate']);
	}
	
	/**
     * set module tags
	 *
     */
	function setPresentationStateIdentificationModule()
	{
		//Presentation Creation Date -1
		$this->addTag('00700082', $this->data['presentationcreationdate']);
		//Presentation Creation time -1
		$this->addTag('00700083', $this->data['presentationcreationtime']);
		//Instance Number - 2
		//$this->addTag('00200013', $this->data['instancenumber']);
		//Content Label - 1
		$this->addTag('00700080', strtoupper($this->data['contentlabel']));
		//Content Description - 2 len=64
		$this->addTag('00700081', $this->data['presentationdescription']);
				//Alternate Content DescriptionSequence - 1
		//0070,0087
		//Content Description - 1
		//0070,0081
		$this->addTag('00700087/0/00700081', $this->data['contentlabelAsDescription']);
		//Language Code Sequence - 1
		//0008,0006
		//Defined CID 5000 “Languages”.
		//Code Value 0008,0100 - 1
		$this->addTag('00700087/0/00080006/0/00080100', 'en');
		//Coding Scheme Designator 0008,0102 - 1
		$this->addTag('00700087/0/00080006/0/00080102', 'RFC3066');
		//Code Meaning 0008,0104 - 1
		$this->addTag('00700087/0/00080006/0/00080104', 'English');
		//Content Creator’s Name - 2 len 64
		$this->addTag('00700084', $this->data['presentationcreator']);
	}
	
	/**
     * set module tags
	 *
     */
	function setImagePixelModule()
	{
		//Samples per Pixel -1  1 - grayscale or 3 - color
		$this->addTag('00280002', '3');
		//YBR_PARTIAL_420, YBR_ICT, YBR_RCT C.7.6.3.1.2
		$this->addTag('00280004', 'YBR_FULL_422');
		//Rows -1
		$this->addTag('00280010', $this->data['numrows']);
		//Columns -1
		$this->addTag('00280011', $this->data['numcolumns']);
		//Bits Allocated -1
		$this->addTag('00280100', '8');
		//Bits Stored -1
		$this->addTag('00280101', '8');
		//High Bit -1
		$this->addTag('00280102', '7');
		//Pixel Representation -1
		$this->addTag('00280103', '0');
		//Planar Configuration -1c
		$this->addTag('00280006', '0');
	}
	/**
     * set module tags
	 *
     */
	function setCineModule()
	{
		//FrameTime
		$this->addTag('00181063', '0');
	}
	/**
     * set module tags
	 *
     */
	function setMultiframeModule()
	{
		//Number of Frames - 1
		$this->addTag('00280008', '1');
		//Frame Increment Pointer - 1 
		$this->addTag('00280009', '00181063');
	}
	/**
     * set module tags
	 *
     */
	function setSCMultiframeImageModule()
	{
		//Burned In Annotation - 1
		$this->addTag('00280301', 'NO');
		//Frame Increment Pointer - 1 
		$this->addTag('00280009', '00181063');
	}
	/**
     * set module tags
	 *
     */
	function setSOPCommonModule()
	{	
		//SOP Class UID - 1
		$this->addTag('00080016', $this->sopuid);
		//SOP Instance UID 1
		$this->addTag('00080018', $this->data['sopinstance']);
		//Specific Character Set 1c
		$this->addTag('00080005', $this->encoding);
		//Instance Creation Date -3 dicom was created
		$this->addTag('00080012', $this->data['instancedate']);
		//Instance Creation Time -3
		$this->addTag('00080013', $this->data['instancetime']);
	}
	/**
     * set module tags
	 *
     */
	function setOtherMetadata()
	{
		//Table 7.1-1  PS 3.10-2011 Page 20
		//File Meta Information Group Length - 1
		//0002,0000 - will be added by program
		//File Meta Information Version -1
		//0002,0001 - will be added by program
		$this->addTag('00020001', '00\01');
		//SOP Class UID - 1
		$this->addTag('00020002', $this->sopuid);
		//SOP Instance UID -1
		$this->addTag('00020003', $this->data['sopinstance']);
		//0002,0010 - see setGeneralSeriesModule()
		//Implementation Class UID -1 1.2.40.0.13.1.1+prefix
		$this->addTag('00020012', $this->rootuid);
		//Implementation Version Name -3
		//0002,0013 - will be added by program or not
		$this->addTag('00020013', $this->implementatinversion);
		//Source Application Entity Title -3
		//0002,0016 - will be added by program or not
	}
	/**
     * restore default data
	 *
     */
	function clear()
	{
		$this->cfgdata = array();
		$this->cfgfilename = '';
		$this->dcmfilename = '';
		$this->filename = '';
		$this->error = '';
		$this->data = array();
		$this->transfersyntax = '1.2.840.10008.1.2.4.50';
		$this->sopuid = '1.2.840.10008.5.1.4.1.1.7';
		$this->swversion = '';
		$this->encoding = 'ISO_IR 6';
	}
}
?>
