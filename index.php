<?php
//mbrousseau - CPI - Created Nov 2014
//LTI Provider for Campus Store Re-direction
//Very basic LTI connection. Not checking signatures or comparing nonces as redirect content is public.
//Takes a site name via context_id and parses the name in the format CODE#### (ie. ABED4F84)
//Pass the whole thing to apc as there is a bunch of string parsing and api pinging, cache is good for an hour

//Can pass custom parameter fake to manually set the title and flag student view
//Pass fake_student=(true|false) fake_course=(BIOL2P93D01SP2014MAIN)

//Bring in the DB credentials
require_once("info.php");

//Bring in our functions
require_once("functions.php");

// Load up the LTI Support code
require_once 'ims-blti/blti.php';

//Initialize, all secrets as 'secret', do not set session, and do not redirect
$context = new BLTI($lti_auth['secret'], false, false);

//Default HTML
echo '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
echo '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">';
echo '<head><meta http-equiv="content-type" content="text/html;charset=utf-8" />';
echo '<title>LibGuide LTI</title>';

//Grab the css
echo '<link href="css/styles.css" type="text/css" rel="stylesheet" media="all" />';

//Bring in the JS for selected items
echo '<script type="text/javascript" language="javascript" src="js/scripts.js"></script>';   

//Check if APC is enabled on the server
$apcOn = extension_loaded('apc');

//Check the key is correct
if($lti_auth['key'] == $context->info['oauth_consumer_key']){

	//Make sure they have sent a course id
	if(isset($context->info['context_id'])){
		
		//Close the head and open the body
		echo '</head><body>';

		//Grab the title of the course for parsing
		$title = $context->info['context_id'];

		//If the user has passed the custom parameter fake set the title to it
		if(isset($context->info['custom_fake_class'])){
			$title = $context->info['custom_fake_class'];
		}

		//Check if the data is cached in APC
		if (($titleData = apc_fetch('campusStore_'.$title)) && $apcOn == TRUE){}

		//Data not cached start pulling!
		else{
			//Parse out the 8 long code + class
			//Check if there is a dash (signifying multiple courses) and grab the four digit code
			$dashLoc = strrpos($title, "-");

			//If it does have a dash add 1 to start at the next character
			if ($dashLoc !== 0){ $dashLoc++;}

			//Substring out the course name and add a space
			$fourCode = substr($title, 0, 4);
			$eightCode = substr($title, $dashLoc, 8);

			//Append the department code to the array
			$titleData['fourCode'] = trim($fourCode);

			//Append the full course code to the array
			$titleData['eightCode'] = trim($eightCode);

			//Grab the duration from the title (8 characters after the -)
			$duration = substr($title, ($dashLoc+8), 3);

			//Pull out the 0 in the duration as the bookstore doesn't use it in single digit durations
			if($duration[1] == "0"){
				$one = $duration[0];
				$two = $duration[2];
				$duration = $one.$two;
			}

			//Append duration to the array
			$titleData['duration'] = $duration;

			//Need to Pull out the term (11 characters after the -)
			$term = substr($title, ($dashLoc+11), 2);

			//Append term to the array
			$titleData['term'] = $term;

			//Hit the CPI Service API for the Faculty info
			$xml_string = file_get_contents('https://cpi.brocku.ca/services/xml/department/'.strtolower($titleData['fourCode']).'');
			$cpiXML = new SimpleXMLElement($xml_string);

			//Add the faculty to the array and cast to a string becuase upc doesn't like objects
			$titleData['faculty'] = (string)($cpiXML->faculty);

			//Get the short faculty code
			$titleData['shortFaculty'] = facultyShort($titleData['faculty']);

			//Pull the two final digit year out the title
			$year = substr($title, ($dashLoc+15), 2);

			//Append to the array
			$titleData['year'] = $year;

			//Make sure apc is on
			if($apcOn == TRUE){
				//Add to the cache
				apc_add('campusStore_'.$title, $titleData, 3600);
			}
		}
		
		//Check if they're an Instructor and show them the instructor splash page unless they have picked student view
		if($context->info['ext_sakai_role'] == "Instructor" && $context->info['custom_fake_student'] !== "true"){
			
			//Aria navigation definition
			echo '<nav role="navigation" aria-label="Textbook Finder Menu">';
			
			//Start the list of navigation items
			echo '<ul aria-label="Textbook Finder Menu">';

			//Switch to instructor view
			echo '<button aria-label="Instructor View of Tool" value="Instructor View of Tool" id="instructor" class="sak-button sak-button-selected" onclick="selected(\'instructor\', \'student\'); document.getElementById(\'display\').src=\''.$bookwareApi.'/for-faculty--staff\';">Instructor View</button>';
		
			//Switch to student view
			echo '<button aria-label="Student View of Tool" value="Student View of Tool" id="student" class="sak-button" onclick="selected(\'student\', \'instructor\'); document.getElementById(\'display\').src=\''.$bookwareApi.'/lms-search/?course%5b0%5d='.$titleData['shortFaculty'].','.$titleData['term'].$titleData['year'].','.$titleData['duration'].','.$titleData['eightCode'].'\';">Student View</button>';

			//Help button
			echo '<button aria-label="Send Help Email" value="Send Help Email" class="sak-button" onclick="window.top.location=\'mailto:'.$supportEmail.'?Subject=Textbook Finder in Sakai - '.$titleData['eightCode'].'\'">Help</button>';
			
			//Navigation items finish
			echo '</ul>';
			
			//Instructor Mode iframe
			echo '<iframe src="'.$bookwareApi.'/for-faculty--staff" width=100% height=1800 frameborder=0 id=display></iframe>';
			
			//End the nav
			echo '</nav>';
		}
		
		//Or show them the student bookstore page
		else{	
			//Forward them on to the correct campus store page
			header('Location: '.$bookwareApi.'/lms-search/?course%5b0%5d='.$titleData['shortFaculty'].','.$titleData['term'].$titleData['year'].','.$titleData['duration'].','.$titleData['eightCode'].'');
		}

	}
	//No valid connection to LTI could be made. Tell the user.
	else{
		echo 'No valid session. Please refresh the page and try again. If you continue to receive this message please contact <a href="mailto:'.$supportEmail.'?Subject=Campus Store in Sakai" target="_top">'.$supportEmail.'</a>';
	}
}
else{
		echo 'LTI credentials not valid. Please refresh the page and try again. If you continue to receive this message please contact <a href="mailto:'.$supportEmail.'?Subject=Campus Store in Sakai" target="_top">'.$supportEmail.'</a>';
}
//End the HTML
echo '</body></html>';
?>
