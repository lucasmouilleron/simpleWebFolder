<?php

/////////////////////////////////////////////////////////////////////////////
/// INCLUDES
/////////////////////////////////////////////////////////////////////////////
require __DIR__ . "/config.php";
if(file_exists(__DIR__ . "/../_sf_overrides/config.php")) require __DIR__ . "/../_sf_overrides/config.php";
require_once __DIR__ . "/tools.php";

/////////////////////////////////////////////////////////////////////////////
/// SETUP
/////////////////////////////////////////////////////////////////////////////
$rootFolder = realpath(__DIR__ . "/..");
$docRoot = getDocRoot();
$baseURL = getBaseURL($docRoot);
$currentPage = getCurrentPage($rootFolder, $baseURL);

/////////////////////////////////////////////////////////////////////////////
/// ROUTING
/////////////////////////////////////////////////////////////////////////////

// shares management

if($currentPage == "/shares" || startsWith($currentPage, "/create-share=") || startsWith($currentPage, "/remove-share="))
{
    include __DIR__ . "/shares.php";
    return;
}

// share
if (startsWith($currentPage, "/share=")) {
    include __DIR__ . "/share.php";
    return;
}

// tracking
if($currentPage == "/tracking")
{
    include __DIR__ . "/tracking.php";
    return;
}

// default to files
include __DIR__ . "/files.php";

