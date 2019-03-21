<?php
/**
 * a tool to explore and profile a php web application completely in cli
 */
if ($_SERVER["argc"] === 1) {
    print "Usage: php {__FILE__} [profile json file]\n";
    exit();
} else if ($_SERVER["argc"] === 2) {
    $profileJSONFILE = $_SERVER["argv"][1];
    $curFile = __FILE__;
    $cmd = "SPX_ENABLED=1 SPX_REPORT=trace SPX_TRACE_FILE=trace.txt php $curFile $profileJSONFILE trace > /dev/null 2>&1";
    shell_exec($cmd);
    //now try only get the relavant information
    $traceContent = file_get_contents(__DIR__."/trace.txt");
    $lines = explode("\n", trim($traceContent));
    $numOfLines = count($lines);
    $resultLines = [];
    for ($i = 0; $i < $numOfLines; $i++) {
        $lineComps = explode("|", $lines[$i]);
        $shouldAddToResult = true;
        //filter out unrelated information
        if (isset($lineComps[7]) && strpos($lines[$i],$curFile) !== FALSE) {
            $shouldAddToResult = false;
        }
        //modify depth
        if (isset($lineComps[6])) {
            $depth = (int)$lineComps[6];
            if ($depth > 0) {
                $lineComps[6] = $depth - 1;
                $lineComps[6] = str_pad($lineComps[6], 10," ");
            }
            $lines[$i] = implode("|", $lineComps);
        }
        if ($shouldAddToResult) {
            $resultLines[] = $lines[$i]; 
        }
    }
    $resultContent = implode("\n", $resultLines);
    file_put_contents(__DIR__."/trace.txt", $resultContent);
    exit();
} else if ($_SERVER["argc"] === 3 && $_SERVER['argv'][2] === 'trace') {
    $profileJSONFILE = $_SERVER["argv"][1];
    $profileData = json_decode(file_get_contents($profileJSONFILE), true);

    //populate the computed fields
    $profileData["PHP_SELF"] = $profileData["SCRIPT_NAME"];
    $profileData["SCRIPT_FILENAME"] = $profileData["DOCUMENT_ROOT"]."/".$profileData["SCRIPT_NAME"];
    $profileData["PATH_TRANSLATED"] = $profileData["SCRIPT_FILENAME"];

    $serverProtocolType = substr($profileData["SERVER_PROTOCOL"], 0, 5);

    if ($serverProtocolType === "HTTP/") {
        if ($profileData["REQUEST_METHOD"] === "GET") {
            $profileData["QUERY_STRING"] = implode(",", $_GET);
        } else {
            $profileData["QUERY_STRING"] = [];
        }
    }

    $profileData["HTTP_HOST"] = $profileData["REMOTE_ADDR"].":".$profileData["REMOTE_PORT"];

    $phpSpecialVars = ["_GET","_POST","_COOKIE"];

    foreach($phpSpecialVars as $varName) {
        $$varName = $profileData[$varName];
    }

    //now populate the $_SERVER vars
    foreach($profileData as $key => $val) {
        if (!in_array($key, $phpSpecialVars)) {
            $_SERVER[$key] = $val;
        }
    }

    //fix current path
    chdir($profileData["DOCUMENT_ROOT"]);
    $pwd = getcwd();
    $_SERVER['PWD'] = $pwd;

    //now include the entry file
    require_once $profileData["SCRIPT_FILENAME"];
}
