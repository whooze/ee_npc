<?php namespace EENPC;

include_once('colors.php');
$colors = new Colors();
/*
This file holds the communications with the EE server, so that we can keep
only the real bot logic in the ee_npc file...
*/

///DATA HANDLING AND OUTPUT

/**
 * Main Communication
 * @param  string $function       which string to call
 * @param  array  $parameterArray parameters to send
 * @return object                 a JSON object converted to class
 */
function ee($function, $parameterArray = array())
{
    global $baseURL, $username, $aiKey, $serv, $cnum, $APICalls;

    $init = $parameterArray;
    $parameterArray['ai_key'] = $aiKey;
    $parameterArray['username'] = $username;
    $parameterArray['server'] = $serv;
    if ($cnum) {
        $parameterArray['cnum'] = $cnum;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    $send = "api_function=".$function."&api_payload=".json_encode($parameterArray);
    //out($send);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $send);

    // receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $serverOutput = curl_exec($ch);

    curl_close($ch);

    $APICalls++;

    $return = handle_output($serverOutput, $function);
    if ($return === false) {
        out_data($init);
    }

    //out($function);
    return $return;
}

/**
 * Handle the server output
 * @param  JSON   $serverOutput JSON return
 * @param  string $function     function to call
 * @return object               json object -> class
 */
function handle_output($serverOutput, $function)
{
    $parts = explode(':', $serverOutput, 2);
    //This will simply kill the script if EE returns with an error
    //This is to avoid foulups, and to simplify the code checking above
    if ($parts[0] == 'COUNTRY_IS_DEAD') {
        out("Country is Dead!");

        return false;
    } elseif ($parts[0] == 'OWNED') {
        out("Trying to sell more than owned!");

        return false;
    } elseif (expected_result($function) && $parts[0] != expected_result($function)) {
        out("\n\nUnexpected Result for '$function': ".$parts[0]."\n\n");

        return false;
    } elseif (!expected_result($function)) {
        out($parts[0]);
        if (!isset($parts[1])) {
            return;
        }
    }

    $output = json_decode($parts[1]);

    return $output;
}

/**
 * just verifies that these things exist
 * @param  string $input Whatever the game returned
 * @return string        proper result
 */
function expected_result($input)
{
    global $lastFunction;
    $lastFunction = $input;
    //This is simply a list of expected return values for each function
    //This allows us to quickly verify if an error occurred
    switch ($input) {
        case 'server':
            return 'SERVER_INFO';
        case 'create':
            return 'CNUM';
        case 'advisor':
            return 'ADVISOR';
        case 'main':
            return 'MAIN';
        case 'build':
            return 'BUILD';
        case 'explore':
            return 'EXPLORE';
        case 'cash':
            return 'CASH';
        case 'pm_info':
            return 'PM_INFO';
        case 'pm':
            return 'PM';
        case 'tech':
            return 'TECH';
        case 'market':
            return 'MARKET';
        case 'onmarket':
            return 'ONMARKET';
        case 'buy':
            return 'BUY';
        case 'sell':
            return 'SELL';
        case 'govt':
            return 'GOVT';
        case 'rules':
            return 'RULES';
        case 'indy':
            return 'INDY';
    }
}

/**
 * Ouput strings nicely
 * @param  string  $str     The string to format
 * @param  boolean $newline If we shoudl make a new line
 * @return void             echoes, not returns
 */
function out($str, $newline = true)
{
    //This just formats output strings nicely
    echo ($newline ? "\n" : null)."[".date("H:i:s")."] $str";
}

/**
 * Output and format data
 * @param  array,object $data Data to ouput
 * @return void
 */
function out_data($data)
{
    //This function is to output and format some data nicely
    out("DATA:\n".str_replace(",\n", "\n", var_export($data, true)));
}

/**
 * Does count() in some case where it doesn't work right
 * @param  object $data probably a $result object
 * @return int       count of things in $data
 */
function actual_count($data)
{
 //do not ask me why, but count() doesn't work on $result->turns
    $i = 0;
    foreach ($data as $stuff) {
        $i++;
    }

    return $i;
}

/**
 * Exit
 * @param  string $str Final output String
 * @return exit
 */
function done($str = null)
{
    if ($str) {
        out($str);
    }
    out("Exiting\n\n");
    exit;
}
