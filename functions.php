<?php
//Associated functions for campusStore php


//Take in the long name of the faculty and return the accepted short form
function facultyShort($faculty){

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
        default:
            $short = "FALSE";
    }

    //Send back the shortform
    return $short;
}