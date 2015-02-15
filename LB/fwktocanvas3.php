<?php
@set_time_limit(0);
ini_set("max_input_time", "600");
ini_set("max_execution_time", "600");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);
$cid = 1648;
$folder = "management";
//$webroot = 'http://www.savingstudentsmoney.org/FWK/econ/';
$webroot = 'https://textimgs.s3.amazonaws.com/mgmt/';

if (!is_writable(__DIR__)) { die('directory not writable'); }

$curdir = rtrim(dirname(__FILE__), '/\\');

// $image is $_FILES[ <image name> ]
// $imageId is the id used in a database or wherever for this image
// $thumbWidth and $thumbHeight are desired dimensions for the thumbnail

$imgcnt = 0;
function processImage($f,$image, $thumbWidth, $thumbHeight )
{
    global $imgcnt,$folder;
    $imgcnt++;
    $curdir = rtrim(dirname(__FILE__), '/\\');
    $galleryPath = "$curdir/$folder/images/";

    if (strpos($f.$image,'.png')!==false) {
    	    $im = imagecreatefrompng($f.$image);
    } else {
    	    $im = imagecreatefromjpeg($f.$image);
    }
    $size = getimagesize($f.$image);
    $w = $size[ 0 ];
    $h = $size[ 1 ];
   
    // create thumbnail
    $tw = $thumbWidth;
    $th = $thumbHeight;
    
    if ($w<=500) {
    	    return $image;
    }
    $imname = 'sm_'.str_replace(array('.png','.jpg','.jpeg'),'',basename($image));
   
    if ( $w/$h > $tw/$th )
    { // wider
	$tmph = $h*($tw/$w);
	$imT = imagecreatetruecolor( $tw, $tmph );
	imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $tmph, $w, $h ); // resize to width
    }else
    { // taller
      
	//nocrop version
	$tmpw = $w*($th/$h);
	$imT = imagecreatetruecolor( $tmpw, $th );
	imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tmpw, $th, $w, $h ); // resize to width
    }
   
    // save the image
   imagejpeg( $imT, $galleryPath . $imname . '.jpg', 71 ); 
   return 'images/'.$imname . '.jpg';
}


function fileize($str) {
	global $webroot;
	$attr = '<hr />
<div class="smallattr" style="font-size: x-small;">This page is licensed under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0" rel="license">Creative Commons Attribution Non-Commercial Share-Alike License</a> and contains content from a variety of sources published under a variety of open licenses, including:
<ul>
<li>Content created by Anonymous under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0" rel="license">Creative Commons Attribution Non-Commercial Share-Alike License</a></li>
<li>Original content contributed by Lumen Learning</li>
</ul>
<p>If you believe that a portion of this Open Course Framework infringes another\'s copyright, <a href="http://lumenlearning.com/copyright">contact us</a>.</p>
</div>';
	$str = preg_replace('/<a\s+class="glossterm">(.*?)<\/a>/sm','<span class="glossterm">$1</span>',$str);
	
	$str = preg_replace('/<a\s+class="footnote"[^>]*#(.*?)".*<\/a>(.*?)<\/sup>/sm','<a class="footnote" href="#$1">$2</sup></a>',$str);
	$str = preg_replace('/<a[^>]+name="ftn\.(.*?)".*?<\/a>/sm','<a name="ftn.$1"></a>',$str);
	$str = preg_replace('/<a[^>]*catalog\.flatworldknowledge[^>]*>(.*?)<\/a>/sm',' $1 ',$str);
	$str = preg_replace('/<p[^>]*>/sm','<p>',$str);
	$str = preg_replace_callback('/class="([^"]*)"/sm',function($m) {
					$classes = preg_split('/\s+/',trim($m[1]));
					foreach ($classes as $k=>$v) {
						$classes[$k] = 'im_'.$v;
					}
					return 'class="'.implode(' ',$classes).'"';
				},$str);
	
	return $str.$attr;
	
}


$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

//3~sFRGaRwnRO2kPq8AeMMW8FIQbqF40n9RjgjQHlXRfK2eJbWStDqu6TGAiROG4fso
$chapters = array();
$sections = array();
$secind = array();
$images = array();
//for ($k=4;$k<=4;$k++) {
for ($k=7;$k<=20;$k++) {

	if ($k<10) {
		$shortsec = 's0'.$k;
		$source = 'section_0'.$k;
	} else {
		$shortsec = 's'.$k;
		$source = 'section_'.$k;
	}

	foreach (glob($folder .'/'.$shortsec.'-*.html') as $filename) {
		if (preg_match('/'.$shortsec.'-[^\d]/',$filename)) {
			echo $filename;
			$c = file_get_contents($filename);
		}
	}
	
	//remove copyrighted images
	phpQuery::newDocumentHTML($c);
	$fig = pq("div.figure");
	foreach ($fig as $f) {
		$cr = pq($f)->children(".copyright")->html();
		if (strpos($cr, '©')!==false || strpos($cr, '&copy;')!==false) {
			pq($f)->remove();	
		}
	}
	$c = pq("body")->html();
	
	//$c = file_get_contents($folder .'/'.$source.'.html');
	$c = preg_replace('|</div>\s*<div\sid=navbar-bottom.*$|sm','',$c);
	
	//$c = mb_convert_encoding($c,'windows-1252','UTF-8');
	
	//remove copyrighted images
	//$c = preg_replace('/<img[^>]*>\s*<div\sclass="copyright".*?(©|&copy;).*?<\/div>/sm','###DEL###',$c);
	//$c = preg_replace('/<img[^>]*>\s*(<p.*?<\/p>)\s*<div\sclass="copyright".*?(©|&copy;).*?<\/div>/sm','$1',$c);
	
	
	//$c = str_replace('<div class="figure','<div style="width:500px;margin:auto;" class="figure',$c);
	
	
	preg_match_all('/<img[^>]*src="(.*?)"[^>]*>/',$c,$matches,PREG_SET_ORDER);
	$sl = strlen($source);
	foreach ($matches as $m) {
		if (substr($m[1],0,$sl)==$source) {
			$newpath = processImage('./'.$folder.'/',$m[1], 500, 400);
			$images[] = $newpath;
			$c = str_replace($m[0],'<a target="_blank" href="'.$webroot.$m[1].'"><img src="'.$webroot.$newpath.'"/></a>',$c);
		}
	}
	
	preg_match('/<div\s+class="chapter.*?<h1[^>]*>(.*?)<\/h1>/sm',$c,$matches);
	//for ($i=1;$i<count($parts);$i+=2) {
		$chp = $k;
		$chptitle	= htmlentities(str_replace("\n",' ',strip_tags($matches[1])), ENT_XML1);
		$chpfolder = array("name"=>$chptitle, "id"=>$blockcnt, "startdate"=>0, "enddate"=>2000000000, "avail"=>2, "SH"=>"HO", "colors"=>"", "public"=>0, "fixedheight"=>0, "grouplimit"=>array());
		$blockcnt++;
		$chpfolder['items'] = array();
		$insec = false;
		
		$secparts = preg_split('/<div\s+class="section.*?id="[^"]*(ch\d+[^"]*)".*?<h2[^>]*>(.*?)<\/h2>/sm',$c,-1,PREG_SPLIT_DELIM_CAPTURE);
		for ($j=1;$j<count($secparts);$j+=3) {
			$sec = ($j+2)/3;
			$sectitle = htmlentities(str_replace("\n",' ',strip_tags($secparts[$j+1])));
			$seclevel = substr_count($secparts[$j],'_');
			if ($seclevel==1) { //start of section
				if ($insec) {
					$chpfolder['items'][] = $secfolder;
				}
				$insec = false;
				if ($j+3<count($secparts)-1) {
					$nextseclevel = substr_count($secparts[$j+3],'_');
					if ($nextseclevel==2) {
						$secfolder = array("name"=>$sectitle, "id"=>$blockcnt, "startdate"=>0, "enddate"=>2000000000, "avail"=>2, "SH"=>"HO", "colors"=>"", "public"=>0, "fixedheight"=>0, "grouplimit"=>array());
						$blockcnt++;
						$secfolder['items'] = array();
						$insec = true;
					}
				}
			}
			if ($insec) {
				$sectitle = preg_replace('/^\s*\d+\.\d+\s*/','',$sectitle);
			}
			$txt = fileize('<div class="section"><h2>'.$sectitle.'</h2>'.$secparts[$j+2]);
			$txt = addslashes($txt);
			$sectitle = addslashes($sectitle);
			$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$sectitle','','$txt',2)";
			mysql_query($query) or die("Query failed : $query " . mysql_error());
			$linkid= mysql_insert_id();
			$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
			mysql_query($query) or die("Query failed : $query " . mysql_error());
			$itemid= mysql_insert_id();
			if ($insec) {
				$secfolder['items'][] = $itemid;
			} else {
				$chpfolder['items'][] = $itemid;
			}
		}
		if ($insec) {
			$chpfolder['items'][] = $secfolder;
		}
		$items[] = $chpfolder;
	//}
}

$newitems = addslashes(serialize($items));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());
?>
Done
