<?php

use Softneta\MedDream\Core\Logging;

class guidoSearch
{
	private $root_dir = '../';
    private $db_host = "https://localhost/api/";

    public function getData($searchCriteria, $date_from, $date_to, $mod, $listMax)
	{
		$root_dir = __DIR__ . '/..';
        include_once("$root_dir/autoload.php");
        $log = new Logging();


        $url = 'https://localhost/api/router/qido-rs/studies?limit='.$listMax.'&includefield=00080020' .
		'&includefield=00080030&includefield=00080050&includefield=00080061&includefield=00080090' .
		'&includefield=00081030&includefield=00100010&includefield=00100020&includefield=00100030' .
		'&includefield=00200010';

        $log->asDump("(dcmsys/jpeg.php) Quido search request:  ", $url);

        // free feids


        for ($i = 0; $i < count($searchCriteria); $i++)
        {
            if($searchCriteria[$i]['name'] == 'patientid')
            {
                $url .= '&PatientID=' . rawurlencode('*' . $searchCriteria[$i]['text'] . '*');
            }
            if($searchCriteria[$i]['name'] == 'patientname')
            {
                $url .= '&PatientName=' . rawurlencode('*' . $searchCriteria[$i]['text'] . '*');
            }
            if($searchCriteria[$i]['name'] == 'accessionnum')
            {
                $url .= '&AccessionNumber=' . rawurlencode('*' . $searchCriteria[$i]['text'] . '*');
            }
            if($searchCriteria[$i]['name'] == 'description')
            {
                $url .= '&StudyDescription=' . rawurlencode('*' . $searchCriteria[$i]['text'] . '*');
            }

        }

        //file_put_contents(dirname(__FILE__)."/test2.txt",  print_r($mod, true) );
        //modality
        $modality = '';
		for ($i = 0; $i < count($mod); $i++)
		{
			if ($mod[$i]['selected'])
			{
				if (strlen($modality))
					$modality .= '\\';
				$modality .= $mod[$i]['name'];
            }
        }
        if (strlen($modality))
          $url .= "&ModalitiesInStudy=$modality";


        //date period
        if (strlen($date_from) || strlen($date_to))
        {
            $url .= '&StudyDate=';
            if (!strlen($date_from))
                $url .= '19011213';		/* minimum of time_t */
            else
                $url .= str_replace('.', '', $date_from);
            $url .= '-';
            if (!strlen($date_to))
                $url .= '20380119';		/* maximum of time_t */
            else
                $url .= str_replace('.', '', $date_to);
        }

        //file_put_contents(dirname(__FILE__)."/url.txt", $url);

        $raw = $this->curl($url);

        $parsed = json_decode($raw, true);

        $result = array('error' => '', 'count' => 0);

        $num_found = 0;
        foreach ($parsed as $entry)
        {
            /* mandatory tags */
            $value = $this->dcmsys_read_tag('0020000D', $entry, false, true);
            $result[$num_found]['uid'] = $value;

            /* optional tags */
            $result[$num_found]['id'] = $this->dcmsys_read_tag('00200010', $entry);
            $result[$num_found]['patientid'] = $this->dcmsys_read_tag('00100020', $entry);
			$result[$num_found]['patientname'] = $this->dcmsys_read_tag('00100010',
				$entry, true);
			$result[$num_found]['patientbirthdate'] = $this->dcmsys_date_from_dicom($this->dcmsys_read_tag('00100030',
					$entry));
            $result[$num_found]['modality'] = $this->dcmsys_read_tag('00080061', $entry);
			$result[$num_found]['description'] = $this->dcmsys_read_tag('00081030',
				$entry);
			$result[$num_found]['date'] = $this->dcmsys_date_from_dicom($this->dcmsys_read_tag('00080020',
					$entry));
			$result[$num_found]['time'] = $this->dcmsys_time_from_dicom($this->dcmsys_read_tag('00080030',
					$entry));
			$result[$num_found]['accessionnum'] = $this->dcmsys_read_tag('00080050',
				$entry);
			$result[$num_found]['referringphysician'] = $this->dcmsys_read_tag('00080090',
				$entry, true);
			$result[$num_found]['sourceae'] = $this->dcmsys_read_tag('00020016', $entry,
				true);

            /* unsupported metadata etc */
            $result[$num_found]['readingphysician'] = '';
            $result[$num_found]['notes'] = 2;			/* won't be supported: database access is needed */
            $result[$num_found]['reviewed'] = '';
            $result[$num_found]['received'] = '';
            $result[$num_found]['datetime'] = $result[$num_found]['date'] . ' ' . $result[$num_found]['time'];

            $num_found++;
        }
        $result['count'] = $num_found;
        $log->asDump("(dcmsys/quidoSearch.php) Quido search result:",  $result);
        return $result;
    }


    private function curl($url)
    {
        $result = array();
        $result['error'] = '';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //can get protected content SSL

        if(isset($_COOKIE['suid']))
		{
            curl_setopt( $ch, CURLOPT_COOKIE, 'suid='.$_COOKIE['suid'] );
        }

        /*else
        {
            $tmpfname =  '../../..'.'/log/cookie_'.$user.'.txt'; //todo padaryti kaip jpeg.php
            curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);   //set cookie to skip site ads
            curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
        }*/

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpcode === 200)
        {

            curl_close($ch);
            return $result;

        }
        else
        {

            $result['error'] = 'http code: '.$httpcode.' url '.$url.' error: '.curl_error($ch);
            curl_close($ch);
            return json_encode($result['error']);
        }
    }


	private function dcmsys_read_tag($key, $arr, $is_PN_VR = false,
								  $substitute_NULL = false, $def_value = '')
    {
        /* first sub-index */
        if (!array_key_exists($key, $arr))
            return $substitute_NULL ? NULL : $def_value;
        $arr1 = $arr[$key];
        if (is_null($arr1))		/* not encountered yet but who knows */
            return $substitute_NULL ? NULL : $def_value;

        /* sub-index 'Value' */
        if (!array_key_exists('Value', $arr1))
            return $substitute_NULL ? NULL : $def_value;
        $arr2 = $arr1['Value'];
        if (is_null($arr2))		/* an often-seen size optimization */
            return $substitute_NULL ? NULL : $def_value;

        /* sub-index 0 */
        if (!array_key_exists(0, $arr2))
            return $substitute_NULL ? NULL : $def_value;
        $arr3 = $arr2[0];

        /* in case of non-PN tags, that's all! */
        if (!$is_PN_VR)
            return $arr3;

        /* PN will additionally have a sub-index 'Alphabetic' (or, at the same level,
           'Phonetic' and 'Ideographic'). Let's combine as much as possible into one
           string.
         */
        if (array_key_exists('Alphabetic', $arr3))
            $value = $arr3['Alphabetic'];
        else
            $value = '';
        if (array_key_exists('Phonetic', $arr3))
            $value .= ' (' . $arr3['Phonetic'] . ')';
        if (array_key_exists('Ideographic', $arr3))
            $value .= ' (' . $arr3['Ideographic'] . ')';
        return trim(str_replace('^', ' ', $value));
    }


    /* add date separators to a string of 8 digits (full DICOM-style date) */
    private function dcmsys_date_from_dicom($str)
    {
        if (strlen($str) == 8)
        {
            $final = preg_replace("/(\d\d\d\d)(\d\d)(\d\d)/", '$1-$2-$3', $str, -1, $num);
            if ($num === 1)			/* excludes NULL which indicates error */
                return $final;
        }
        return $str;
    }

    /* add time separators to a string of 6+ digits (DICOM-style time with
   an optional fractional part which will be left intact)
   */
    private function dcmsys_time_from_dicom($str)
    {
        if (strlen($str) >= 6)
        {
			$final = preg_replace("/(\d\d)(\d\d)(\d\d)(.*)/", '$1:$2:$3$4', $str, -1,
				$num);
            if ($num === 1)			/* excludes NULL which indicates error */
                return $final;
        }
        return $str;
    }


}
