<?php
//David Lippman 2014 for Lumen Learning
//Takes a directory of HTML files from an OpenStax epub file,
//splits them into section html files
//outputs content to code to add to content.opf file

// GPL License

require("phpQuery-onefile.php");
$meta = array();

//Change this section
$dir = 'micro';


function file_save($filename, $html, $title) {
	file_put_contents($filename, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"><title>'.$title.'</title></head><body>'.$html.'</body></html>');
}

$files = glob($dir."/*.html");

$n = 0;
$ids = array();
foreach ($files as $file) {
	$chp = explode('.',basename($file))[0];
	
	$html = file_get_contents($file);
	$html = str_replace(array("\xc2\xa0",'&nbsp;','&#160;')," ", $html);
	
	phpQuery::newDocumentHTML($html);
	
	$intro = pq("div.chapter > div.titlepage")->html().pq("div.chapter > div.introduction")->html();
	file_save($dir.'/'.$chp.'-0.html', $intro, 'Introduction');
	
	$ids[] = $chp.'-0';
	
	$secs = pq("div.chapter > div.section");
	$seccnt = 1;
	foreach ($secs as $sec) {
		//preg_match('/^(\d+\.\d+)/',pq($sec)->attr("title"),$matches);
		//$secid = $matches[1];
		//$oea = '<iframe src="//oea.herokuapp.com/assessments/load?src_url=https://s3-us-west-2.amazonaws.com/qtiquiz/concbio/'.$secid.'.xml&eid=concbio-a9NwZ-'.str_replace('.','-',$secid).'&results_end_point=https://oea.herokuapp.com/api&assessment_id=concbio-a9NwZ-'.str_replace('.','-',$secid).'&confidence_levels=true" frameborder="0" style="border:none;width:100%;height:100%;min-height:400px;"></iframe>';
		$oea = '';
		file_save($dir.'/'.$chp.'-'.$seccnt.'.html', pq($sec)->html().$oea, pq($sec)->attr("title"));
		$ids[] = $chp.'-'.$seccnt;
		$seccnt++;
	}
	
	$sec = pq("div.chapter > div.glossary");
	file_save($dir.'/'.$chp.'-g.html', pq($sec)->html(), 'Glossary');
	$ids[] = $chp.'-g';
	
	$as = pq("div.chapter > div.summary a");
	foreach ($as as $a) {
		pq($a)->replaceWith(pq($a)->contents());
	}
	
	$chpnum = intval(substr($chp,2));
	
	$sec = pq("div.chapter > div.summary, div.chapter > div.section-summary");
	file_save($dir.'/'.$chp.'-s.html', pq($sec)->html(), "Chapter $chpnum Summary");
	$ids[] = $chp.'-s';
	
	$as = pq("div.cnx-eoc a");
	foreach ($as as $a) {
		pq($a)->replaceWith(pq($a)->contents());
	}
	pq("div.solution.labeled div.title")->text("Solution:");
	pq("div.solution.problems-exercises div.title")->text("Solution:");
	$secs = pq(".cnx-eoc.interactive-exercise, .cnx-eoc.free-response, .cnx-eoc.short-answer, .cnx-eoc.section-quiz, .cnx-eoc.conceptual-questions, .cnx-eoc.problems-exercises, .cnx-eoc.self-check-questions, .cnx-eoc.review-questions, .cnx-eoc.critical-thinking");
	$extext = '';
	foreach ($secs as $sec) {
		$extext .= '<div class="cnx-eoc exercises">'.pq($sec)->html().'</div>';
	}
	file_save($dir.'/'.$chp.'-e.html', $extext, "Chapter $chpnum Exercises");
	$ids[] = $chp.'-e';
}

foreach ($ids as $id) {
	echo '&lt;item id="'.$id.'" href="'.$id.'.html"  media-type="application/xhtml+xml"/&gt;<br/>';
}
foreach ($ids as $id) {
	echo '&lt;itemref idref="'.$id.'" /&gt;<br/>';
}

?>
