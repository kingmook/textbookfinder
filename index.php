<?php
//mbrousseau - CPI - Created Nov 2014
//LTI Provider for Campus Store Re-direction
//Very basic LTI connection. Not checking signatures or comparing nonces as redirect content is public.
//Takes a site name via context_id and parses the name in the format CODE#### (ie. ABED4F84)
//Ping the registrar's api for duration as campus store uses calendar year instead of brock year
//Pass the whole thing to apc as there is a bunch of string parsing and api pinging, cache is good for an hour

//Can pass custom parameter fake to manually set course and if you are a student or not ie:
//fake_class=FILM-COMM-PCUL3P21D03FW2014MAIN
//fake_student=false

//Bring in the DB credentials
require_once("info.php");

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

//Check if APC is enabled on the server
$apcOn = extension_loaded('apc');

//Make sure they have sent a course id
if (isset($context->info['context_id'])){

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
		$eightCode = $fourCode.substr($title, ($dashLoc+4), 4);

		//Append to the array
		$titleData['eightCode'] = $eightCode;

		//IELP Exception
		//Campus Store currently does not include the duration N in IELP courses. Strip that out
		if (trim($fourCode) === "IELP"){
			$titleData['eightCode'] = str_replace("N", "", $titleData['eightCode']);
		}

		//Grab the duration from the title (8 characters after the -)
		$duration = substr($title, ($dashLoc+8), 3);

		//Pull out the 0 in the duration as the bookstore doesn't use it in single digit durations
		if($duration[1] == "0"){
			$one = $duration[0];
			$two = $duration[2];
			$duration = $one.$two;
		}

		//Append to the array
		$titleData['duration'] = $duration;

		//Need to Pull out the term (11 characters after the -)
		$term = substr($title, ($dashLoc+11), 2);

		//Append to the array
		$titleData['term'] = $term;

		//The campus store's store uses calendar years not Brock years
		//Lets check to make sure its not a calender year 2015 but Brock year 2014 class. Hit the Registrar duration api to show start and end dates
		$regApi = file_get_contents("http://brocku.ca/registrar/guides-and-timetable/common/timetable_info.php?dur=UG:".substr($duration, 1).":".$term.":ALL");
		echo $regApi."<br />";

		//Pull of the first year that starts with 20 - yeah I know this will only work for 86 years. If we're seriously using this in 86 years I apologize to whomever is reading this.
		$yearPos = strpos($regApi, "20");

		//Only grab the year minus the 20
		$year = substr($regApi, $yearPos+2, 2);

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
		
		//Switch to student view
		echo '<li class="sak-button"><a href="https://brock.bookware3000.ca/lms-search/?course%5b0%5d=,'.$titleData['term'].''.$titleData['year'].',,'.$titleData['eightCode'].'">Student View of Textbook Finder</a></li>';
				
		//Switch to student view
		echo '<li class="sak-button"><a href="mailto:'.$supportEmail.'?Subject=Textbook Finder in Sakai" target="_top">Help</a></li>';
		
		//Navigation items finish
		echo '</ul>';
		
		//Instructor Mode iframe
		echo '<iframe src="https://brock.bookware3000.ca/for-faculty--staff" width=100% height=1800 frameborder=0></iframe>';
		
		//End the nav
		echo '</nav>';

	}
	
	//Or show them the student bookstore page
	else{	
		//Forward them on to the correct campus store page
		header('Location: https://brock.bookware3000.ca/lms-search/?course%5b0%5d=,'.$titleData['term'].''.$titleData['year'].',,'.$titleData['eightCode'].'');
	}

}
//No valid connection to LTI could be made. Tell the user.
else{
	echo 'No valid session. Please refresh the page and try again. If you continue to receive this message please contact <a href="mailto:'.$supportEmail.'?Subject=Campus Store in Sakai" target="_top">'.$supportEmail.'</a>';
}
//End the HTML
echo '</body></html>';
?>
