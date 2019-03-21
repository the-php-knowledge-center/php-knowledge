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
    $cmd = "SPX_ENABLED=1 SPX_REPORT=trace SPX_TRACE_FILE=trace.txt php $curFile $profileJSONFILE trace > /dev/null";
    shell_exec($cmd);
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
