<?php
//Associated functions for campusStore php

//Take in the long name of the faculty and return the accepted short form
function facultyShort($faculty){
    
    //Spaces are jerks. Make sure we trim any.	
    $faculty = trim($faculty);

    //Different faculty name cases
    switch ($faculty) {
        case "Humanities":
            $short = "HUMAN";
            break;
        case "Education":
            $short = "EDUC";
            break;
        case "Business":
            $short = "BUSINE";
            break;
        case "Applied Health Sciences":
            $short = "HEALTH";
            break;
        case "Mathematics and Science":
            $short = "MTHSCI";
            break;
        case "Social Sciences":
            $short = "SOCSCI";
            break;
	case "Intensive English Language Program":
            $short = "IELP";
            break;
        default:
            $short = "FALSE";
    }

    //Send back the shortform
    return $short;
}

//Takes a lookup url, the duration (D01, D02, etc) and the session (FW, SP, etc) | Returns two digit year (15)
function getDurationDate($lookup, $duration, $session){
	
	//Strip the preceeded D from the duration
	$duration = str_replace("D", "", $duration);

	//The list of things to post
	$postVars = array('dur' => 'UG:'.$duration.':'.$session.':'.'ALL');
	
	//URLize our array for posting
	$data = http_build_query($postVars);

	//Our get url
	$getUrl = trim($lookup.'?'.$data);

	//First time gets the session (and doesn't actually print anything), second time actually gets the page value - crazy.
	echo file_get_contents($getUrl);
	$rawHTML =  file_get_contents($getUrl);

	//Find the 2 to get the year -- This will only work for the next 84 years. Sorry if you're still using this in the year 2100
	$startYear = strpos($rawHTML, "20");
	
	//Pull out the year
	$calYear = substr($rawHTML, $startYear+2, 2);

	return $calYear;	
	
}