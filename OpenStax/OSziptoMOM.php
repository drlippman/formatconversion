<?php
@set_time_limit(0);
ini_set("max_input_time", "600");
ini_set("max_execution_time", "600");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);

$cid = 4112;
//make sure course has one folder in it
$dir = 'ospsych';

$meta['book'] = 'Psychology';
$meta['org'] = 'OpenStax College';
$meta['license'] = 'CC-BY';
$meta['licenseurl'] = 'http://creativecommons.org/licenses/by/4.0/';
$bookname = 'OpenStax Psychology';

//read in collection file to get module order
phpQuery::newDocumentFileXML($dir.'/collection.xml');

$mods = pq("col|module");
$modlist = array();
foreach ($mods as $mod) {
	$modlist[] =  pq($mod)->attr("document");
}

$query = "SELECT itemorder FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

//process each module
foreach ($modlist as $mod) {
	phpQuery::newDocumentFileHTML($dir.'/'.$mod.'/index.cnxml.html');
	$pagetitle = trim(pq("title")->html());
	//rewrite image urls
	$imgs = pq("img");
	foreach ($imgs as $img) {
		$src = pq($img)->attr("src");
		$src = 'https://textimgs.s3.amazonaws.com/'.$dir.'/'.$mod.'/'.basename($src);
		pq($img)->attr("src",$src);
	}
	
	//grab and process review questions
	$revq = pq("section.review-questions");
	$assesstitle = makeQTI($pagetitle, $revq);
	pq($revq)->html('<h1>Review Questions</h1><p>https://www.openassessment.com/assessments/'."$assesstitle</p>");
	
	$txt = addslashes(pq("body")->html());
	
	$pagetitle = addslashes($pagetitle);
	$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$linkid= mysql_insert_id();
	$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$itemid= mysql_insert_id();
	
	$items[0]['items'][] = $itemid;
}

$newitems = addslashes(serialize($items));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());

$n = 0;
function makeQTI($pagetitle, $revq) {
	global $meta,$n,$bookname,$dir,$assessuniq;
	$lets = array('A','B','C','D','E','F','G');
	$meta['chapter'] = $pagetitle;
	$out = startqti($pagetitle);
	
	$qs = pq($revq)->find("div[data-type=exercise]");
	foreach ($qs as $k=>$q) {
		$n++;
		$prob = pq($q)->find("div[data-type=problem]");
		$lis = pq($prob)->children("ol")->find("li");
		$solntext = array();
		foreach ($lis as $li) {
			$solntext[] = pq($li)->html();
		}
		$lis = pq($prob)->children("ol")->remove();
		$prompt = pq($prob)->html();
		$soln = pq($q)->find("div[data-type=solution]")->text();;
		//get solution letter index
		$corrects = array(array_search($soln, $lets));
		
		$out .= '<item ident="'.$assessuniq.'q'.$n.'" title="Question #'.($k+1).'">
		<itemmetadata>
		  <qtimetadata>
		    <qtimetadatafield>
		      <fieldlabel>question_type</fieldlabel>
		      <fieldentry>'.((count($corrects)>1)?'multiple_answers_question':'multiple_choice_question').'</fieldentry>
		    </qtimetadatafield>
		    <qtimetadatafield>
		      <fieldlabel>points_possible</fieldlabel>
		      <fieldentry>1</fieldentry>
		    </qtimetadatafield>
		   ';
		    $out.= '</qtimetadata>
		</itemmetadata>
		<presentation>
		  <material>
		    <mattext texttype="text/html">'.htmlentities(trim($prompt),ENT_XML1).'</mattext>
		  </material>
		  <response_lid ident="response1" rcardinality="Single">
		    <render_choice>';
		    foreach ($solntext as $k=>$it) {
			    $out .= '<response_label ident="'.$assessuniq.'q'.$n.'o'.$k.'">
			<material>
			  <mattext texttype="text/html">'.htmlentities(trim($it),ENT_XML1).'</mattext>
			</material>
		      </response_label>';
		   }
		   $out .= '
		    </render_choice>
		  </response_lid>
		</presentation>
		<resprocessing>
		  <outcomes>
		    <decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/>
		  </outcomes>
		  <respcondition continue="No">
		    <conditionvar>
		    ';
		    if (count($corrects)==1) {
			    $out .= '<varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$corrects[0].'</varequal>';
		    } else {
			    $out .= '<and>';
			    foreach ($solntext as $k=>$it) {
				    if (in_array($k,$corrects)) {
					    $out .= '<varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$k.'</varequal>';
				    } else {
					    $out .= '<not><varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$k.'</varequal></not>';
				    }
			    }
			    $out .= '</and>';
		    }
		    
		    
		     $out .= '</conditionvar>
		    <setvar action="Set" varname="SCORE">100</setvar>
		  </respcondition>
		</resprocessing>
	      </item>';
		
		
	}
	
	$out .= endqti();
	$cleantitle = preg_replace('/\W/','_',$pagetitle);
	file_put_contents($dir.'/OEA/'.$cleantitle.".xml", $out);
	echo "$cleantitle.xml<br/>";
	return "$cleantitle.xml";
}

function startqti($assessv='') {
	global $meta,$assessn,$dir,$assessuniq,$bookname;
	if ($assessv=='') {
		$assessv = $assessn;
		$assessn++;
	}
	$assessuniq = $dir.'-20150602-'.preg_replace('/\W/','-',$assessv);
	
	$c = '<?xml version="1.0" encoding="UTF-8"?>
<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsglobal.org/xsd/ims_qtiasiv1p2 http://www.imsglobal.org/xsd/ims_qtiasiv1p2p1.xsd">
  <assessment ident="'.$assessuniq.'" title="'.$bookname.': '.$assessv.'">
    <qtimetadata>
      <qtimetadatafield>
        <fieldlabel>cc_maxattempts</fieldlabel>
        <fieldentry>1</fieldentry>
      </qtimetadatafield>';
    if (isset($meta['book'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_publication</fieldlabel>
        <fieldentry>'.$meta['book'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['org'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_organization</fieldlabel>
        <fieldentry>'.$meta['org'].'</fieldentry>
      </qtimetadatafield>';
     } 
     if (isset($meta['author'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_author</fieldlabel>
        <fieldentry>'.$meta['author'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['chapter'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_chapter</fieldlabel>
        <fieldentry>'.$meta['chapter'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['chpn'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_chapter_number</fieldlabel>
        <fieldentry>'.$meta['chpn'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['license'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_license</fieldlabel>
        <fieldentry>'.$meta['license'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['licenseurl'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_license_id</fieldlabel>
        <fieldentry>'.$meta['licenseurl'].'</fieldentry>
      </qtimetadatafield>';
     }
     
    $c .=' </qtimetadata>
    <section ident="root_section">';
    return $c;
}

function endqti() {
	$c =  '</section>
  </assessment>
</questestinterop>';
	return $c;
}


?>
