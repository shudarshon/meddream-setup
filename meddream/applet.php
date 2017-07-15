<?php
/*
	Original name: applet.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>

	Description:
		Integrates MedDream into PacsOne web interface. Replaces ..\applet.php
		in a typical PacsOne installation.
 */

function appletExists()
{
	$dir = dirname(__FILE__);
	$dir .= "/meddream";
	return file_exists($dir);
}

function appletViewer(&$uids)
{
	$actions = array();
	$actions['action'] = $_POST['actionvalue'];
	$actions['option'] = $_POST['option'];
	$actions['entry'] = $_POST['entry'];
	for ($i = 0; $i < sizeof($actions['entry']); $i++)
		$actions['entry'][$i] = (string) urldecode((string)$actions['entry'][$i]);
	$_SESSION['actions'] = $actions;

	print "<html>\n";
	print "<body style=\"height:100%; border:0px margin-top:0px; margin-bottom:0px;\" leftmargin=\"0\" topmargin=\"0\" bottommargin=\"0\" bgcolor=\"#ffffff\">\n";
	echo '<table width="100%" height="100%">';
	echo '<tr>';
	echo '<td>';
	require_once 'header.php';
	require_once 'footer.php';
	echo '</td>';
	echo '</tr>';
	echo '<tr height="100%" style="height:100%;">';
	echo '<td height="100%" style="height:100%;">';
	echo '<IFRAME SRC="meddream/index.php?session=pacsone" width="100%" height="100%"></IFRAME>';
	echo '</td>';
	echo '</tr>';
	echo '</table>';
	print "</body>\n";
	print "</html>\n";
}

?>
