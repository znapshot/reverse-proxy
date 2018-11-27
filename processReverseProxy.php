#!/usr/bin/php
<?php
require_once '../common/util.php';
require_once '../common/phpexcel/Classes/PHPExcel.php';
require_once '../common/excelHelper.php';

class cContext {
    public $isVerbose;
    public $logFileName;
    public $outFileName;
    public $cnFileName;
    public $directoryName;

    public $sites;
    public $commonNames;
    public $siteNames;
    public $dates;
 
    public function __construct() {
        $this->isVerbose = false;
        $this->logFileName = null;
        $this->outFileName = null;
        $this->cnFileName = "oaci.csv";
        $this->directoryName = null;
        $this->siteNames = null;
        $this->sites = null;
        $this->commonNames = null;
        $this->dates = array();
    }

    function getSite( $ipAddress, $commonName = null){
        if( $this->sites === null)
            $this->sites = array();
        if( isset( $this->sites[ $ipAddress]))
            $curIpAddress = $this->sites[ $ipAddress];
        else{
            $curIpAddress = new cIpAddress( $ipAddress);
            $this->sites[ $ipAddress] = $curIpAddress;
        }
        $site = $curIpAddress->getSite( $commonName);
        return $site;
    }
}

class cSite {
    public $ipAddress;
    public $techno;
    public $nbErrors;
    public $nbSuccess;
    public $lastStatus;
    public $maxConsecutiveErrors;
    public $nbConsecutiveErrors;
    public $currentCall;
    public $calls;
    public $tls10;
    public $tls11;
    public $tls12;
    public $commonName;
    public $name;

    public function __construct( $ipAddress, $commonName = null) {
        $this->ipAddress = $ipAddress;
        $this->nbErrors = 0;
        $this->nbSuccess = 0;
        $this->lastStatus = 0;
        $this->maxConsecutiveErrors = 0;
        $this->nbConsecutiveErrors = 0;
        $this->currentCall = null;
        $this->tls10 = false;
        $this->tls11 = false;
        $this->tls12 = false;
        $this->calls = null;
        $this->commonName = $commonName;
        $this->name = null;
        $this->techno = null;
    }
}

class cIpAddress {
    public $ipAddress;
    public $lastSite;
    public $sites;

    public function __construct( $ipAddress){
        $this->ipAddress = $ipAddress;
        $this->lastSite = null;
        $this->sites = array();
    }

    public function getSite( $commonName){
        if( $commonName === null){
            if( $this->lastSite === null)
                $this->lastSite = new cSite( $this->ipAddress);
            return $this->lastSite;
        }
        if( isset( $this->sites[ $commonName])) {
            $this->lastSite = $this->sites[ $commonName];
        }
        else {
            if( $this->lastSite === null){
                $this->lastSite = new cSite( $this->ipAddress, $commonName);
            }
            else{
                $lastCommonName = $this->lastSite->commonName;
                if( $lastCommonName === null){
                    $this->lastSite->commonName = $commonName;
                }
                elseif( strcmp( $lastCommonName, $commonName)){
                    $this->lastSite = new cSite( $this->ipAddress, $commonName);
                }
            }
            $this->sites[ $commonName] = $this->lastSite;
        }
        return $this->lastSite;
    }
}

class cSiteTechno{
    public $name;
    public $techno;
}

$gDateSheetHeader = array(
    "IP"     => new cHeader( "A", 15, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "CN"     => new cHeader( "B", 35, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "Site"   => new cHeader( "C", 15, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "Techno" => new cHeader( "D", 15, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "1-0"    => new cHeader( "E", 8, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "1-1"    => new cHeader( "F", 8, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "1-2"    => new cHeader( "G", 8, PHPExcel_Style_NumberFormat::FORMAT_TEXT),
    "# suc"  => new cHeader( "H", 8, PHPExcel_Style_NumberFormat::FORMAT_NUMBER),
    "Freq"   => new cHeader( "I", 8, PHPExcel_Style_NumberFormat::FORMAT_NUMBER),
    "# err"  => new cHeader( "J", 8, PHPExcel_Style_NumberFormat::FORMAT_NUMBER),
    "# cons" => new cHeader( "K", 8, PHPExcel_Style_NumberFormat::FORMAT_NUMBER)
);

function dateSheet( $sheet, $sites, $cnNames)
{
    global $gDateSheetHeader;

    sheetHeader( $sheet, $gDateSheetHeader);

    $siteByName = array();
    foreach( $sites as $ipAddress)
    foreach( $ipAddress->sites as $site) {
        if( !$site->nbSuccess)
            continue;
        if( $cnNames !== null && isset( $cnNames[ $site->commonName])){
            $siteTechno = $cnNames[ $site->commonName];
            $name = $siteTechno->name;
            $site->techno = $siteTechno->techno;
        }
        else{
            echo "[WAR] Common name $site->commonName unknown.\n";
            continue;
        }
        if( !isset( $siteByName[ $name]))
            $siteByName[ $name] = array();
        $siteByName[ $name][] = $site;

    }
    ksort( $siteByName);
    //var_dump( $siteByName);

    $row = 2;
    foreach( $siteByName as $name => $siteNames)
        foreach( $siteNames as $site) {
        // Remove fake connection attempts
        $sheet->setCellValue( $gDateSheetHeader[ "IP"]->column . ($row) , $site->ipAddress);
        $sheet->setCellValue( $gDateSheetHeader[ "CN"]->column . ($row) , $site->commonName);
        $sheet->setCellValue( $gDateSheetHeader[ "Site"]->column . ($row), $name);
        $sheet->setCellValue( $gDateSheetHeader[ "Techno"]->column . ($row), $site->techno);
        if( $site->tls10) {
            $cell = $gDateSheetHeader[ "1-0"]->column . ($row);
            $sheet->setCellValue( $cell, "O");
            $sheet->getStyle( $cell)->applyFromArray( array(
                'alignment' => array(
                        'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_CENTER
                ),
                'fill' => array(
                        'type' => PHPExcel_Style_Fill::FILL_SOLID,
                        'color' => array(
                                    'rgb' => 'FF0000'
                        )
                    )
            ));
        }
        if( $site->tls11) {
            $cell = $gDateSheetHeader[ "1-1"]->column . ($row);
            $sheet->setCellValue( $cell, "O");
            $sheet->getStyle( $cell)->applyFromArray( array(
                'alignment' => array(
                        'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_CENTER
                ),
                'fill' => array(
                        'type' => PHPExcel_Style_Fill::FILL_SOLID,
                        'color' => array(
                                    'rgb' => 'FFA500'
                        )
                    )
            ));
        }
        if( $site->tls12) {
            $cell = $gDateSheetHeader[ "1-2"]->column . ($row);
            $sheet->setCellValue( $cell, "O");
            $sheet->getStyle( $cell)->applyFromArray( array(
                'alignment' => array(
                        'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_CENTER
                ),
                'fill' => array(
                        'type' => PHPExcel_Style_Fill::FILL_SOLID,
                        'color' => array(
                                    'rgb' => '00FF00'
                        )
                    )
            ));
        }
        $sheet->setCellValue( $gDateSheetHeader[ "# suc"]->column . ($row), $site->nbSuccess);
        $sheet->setCellValue( $gDateSheetHeader[ "Freq"]->column . ($row), '=(24*60)/' . $gDateSheetHeader[ "# suc"]->column . ($row));
        if( $site->nbErrors > 0) {
                $sheet->setCellValue( $gDateSheetHeader[ "# err"]->column . ($row), $site->nbErrors);
         }
        if( $site->maxConsecutiveErrors > 1) {
            $sheet->setCellValue( $gDateSheetHeader[ "# cons"]->column . ($row), $site->maxConsecutiveErrors);            
            if( $site->calls === null)
                echo "[WAR] No calls for site $site->ipAddress and consecutive errors : $site->maxConsecutiveErrors.\n";
            else
            foreach( $site->calls as $call){
                if( count( $call) == $site->maxConsecutiveErrors){
                    $col = $gDateSheetHeader[ "# cons"]->column;
                    foreach( $call as $timeStamp) {
                        $col++;
                        $sheet->setCellValue( $col . ($row), $timeStamp);
                    }
                    break;
                }
            }

        }
        ++$row;
    }

}

function createWorkBook( $context) 
{
    if( empty( $context->outFileName)) {
        echo "[WAR] No out file name specified ! (use --o=<outfilename>)\n";
        return;
    }

    echo "[INF] Creation du classeur $context->outFileName.\n";

    $workBook = new PHPExcel;

    foreach( $context->dates as $currentDate => $sites) {
        if( $context->isVerbose) echo "[INF] Begin creation of sheet $currentDate.\n";
        $sheet = new PHPExcel_Worksheet( $workBook, $currentDate );
        $workBook->addSheet( $sheet);
        dateSheet( $sheet, $sites, $context->commonNames);
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        if( $context->isVerbose) echo "[INF] End creation of sheet $currentDate.\n";   
    }

    $workBook->setActiveSheetIndexByName('Worksheet');
    $sheetIndex = $workBook->getActiveSheetIndex();
    $workBook->removeSheetByIndex($sheetIndex);

    $writer = new PHPExcel_Writer_Excel2007( $workBook);
    $writer->save( $context->outFileName);
}


function analyzeLogFile( $context) 
{

    if( ($logFile = fopen( $context->logFileName, 'r')) === false) {
        printf( '[ERR] Impossible d\'ouvrir le fichier [%s].\n', $context->logFileName);
        return;
    }
    if( $context->isVerbose) echo "[INF] Begin processing file " . basename( $context->logFileName). "\n";

    $nbLines = 0;
    $nbErrors = 0;
    $last2008Line = -1;

    while( !feof( $logFile)) {
        ++$nbLines;

        if( $context->isVerbose && !($nbLines % 50000)) echo "[INF] Processing line $nbLines ...\n";
        $line = fgets( $logFile);

        $is2008Line = preg_match( '/AH02008/', $line); // Error line (commbined with 140940F5 )
        $is0944Line = preg_match( '/AH00944/', $line); // Success line
        $is2041Line = preg_match( '/AH02041/', $line); // Line used to get the TLS version
        $is2275Line = preg_match( '/AH02275/', $line); // Line used to get the Certificate CN
        $is140940F5Line = preg_match( '/140940F5/', $line); // TLS Error line
        if( ! $is2008Line && ! $is140940F5Line && ! $is0944Line && !$is2041Line && !$is2275Line)
            continue;

        if( $is2275Line &&
            preg_match( '/^.*\[client ([\d\.]+):\d+\].*depth 0.*CN=SSIM DSI DE LA DGAC - ([^,]+),.*$/', $line, $match)
        ) {
            $ipAddress = $match[ 1];
            $commonName = $match[ 2];
            $site = $context->getSite( $ipAddress, $commonName);
            continue;
        }

        if( $is0944Line &&
            preg_match( '/^\[([^\]]+)\]\s+\[proxy:debug\]\s+\[pid \d+\]\s+proxy_util.c\(2209\):\s+\[client ([^\]]+)\].*$/', $line, $match) 
        ) {
            $timeStamp = $match[ 1];
            $ipAddress = $match[ 2];
            preg_match( '/^([\d\.]+):\d+$/', $ipAddress, $match);
            $ipAddress = $match[ 1];
            $site = $context->getSite( $ipAddress);
            ++$site->nbSuccess;
            if( !$site->lastStatus) {
                if( $site->calls === null)
                    $site->calls = array();
                $site->calls[] = $site->currentCall;
                $site->currentCall = null;
            }
            $site->lastStatus = 1;
            $site->nbConsecutiveErrors = 0;
            continue;
        }

        if( $is2041Line &&
            preg_match( '/^.*\[client ([\d.]+):.*Protocol: ([^,]+),.*$/', $line, $match) 
        ) {
            $ipAddress = $match[ 1];
            $tlsVersion = $match[ 2];
            $site = $context->getSite( $ipAddress);
            if( !strcmp( $tlsVersion, 'TLSv1'))
                $site->tls10 = true;
            elseif( !strcmp( $tlsVersion, 'TLSv1.1'))
                $site->tls11 = true;
            elseif( !strcmp( $tlsVersion, 'TLSv1.2'))
                $site->tls12 = true;
            continue;
        }

        if( $is2008Line)
            $last2008Line = $nbLines;
        if( $is140940F5Line && ($nbLines - $last2008Line) != 1)
            continue;

        ++$nbErrors;
        if( $is2008Line && 
            preg_match( '/^\[([^\]]+)\]\s+\[ssl:info\]\s+\[pid \d+\]\s+\[client ([^\]]+)\].*$/', $line, $match)) 
        {
            $timeStamp = $match[ 1];
            $ipAddress = $match[ 2];
            preg_match( '/^([\d\.]+):\d+$/', $ipAddress, $match);
            $ipAddress = $match[ 1];
            $site = $context->getSite( $ipAddress);
            if( $site->lastStatus) {
                $site->lastStatus = 0;
                $site->nbConsecutiveErrors = 0;
            }
            ++$site->nbConsecutiveErrors;
            if( $site->nbConsecutiveErrors > $site->maxConsecutiveErrors)
                $site->maxConsecutiveErrors = $site->nbConsecutiveErrors;
            preg_match( '/^\w+\s+\w+\s+\d+\s+([\d:]+)\..*$/', $timeStamp, $match);
            if( $site->currentCall === null)
                $site->currentCall = array();
            $site->currentCall[] = $match[ 1];
            ++$site->nbErrors;

        }
    }

    // Case where the last called found in log file was not a sucess
    foreach( $context->sites as $curIpAddress)
        foreach( $curIpAddress->sites as $site){
            if( $site->currentCall !== null) {
                if( $site->calls === null)
                    $site->calls = array();
                $site->calls[] = $site->currentCall;
                $site->currentCall = null;
            }
        }

    if( $context->isVerbose) echo '[INF] End proccessing file ' . basename( $context->logFileName) . "\n";
    //var_dump( $context->sites);
}

function processLogFile( $context)
{
    $logFileName = $context->logFileName;
    if( preg_match('/^erreur_log_bo[^-]*-(\d+)$/', basename($logFileName), $match)){
        $logFileDate = $match[ 1];
        analyzeLogFile( $context);
        ksort($context->sites);
        $context->dates[ $logFileDate] = $context->sites;
        $context->sites = array();
    }
    else
        echo "[WAR] file $logFileName does not seem to be reverse proxy log file\n";

}

function processCSVFile( $fileName, $context)
{
    if( ($csvFile = fopen( $fileName, 'r')) === false) {
        echo "[ERR] Cannot open file $fileName\n";
        return null;
    }
    if( $context->isVerbose) echo "[INF] Loading file " . basename( $fileName). "\n";

    $values = array();

    while( !feof( $csvFile)) {
        $line = fgets( $csvFile);
        if( preg_match( '/^([^;]+);(.*);(.*)$/', $line, $match)){
            $key = $match[ 1];
            $siteTechno = new cSiteTechno();
            $siteTechno->name = $match[ 2];
            $siteTechno->techno = $match[ 3];
            $values[ $key] = $siteTechno;
        }
    }

    fclose( $csvFile);
    return $values;

}

function syntax()
{
    echo "Usage: processReverseProxy.php [--d=<directory>] [--l=<log file>] [-v] [--c=<common name file>] --o=<output file>\n";
    echo "       Either --d or --l must be specfied.\n";
}

function analyzeArguments( $_ARGS, $context) {

    if( array_key_exists( 'h', $_ARGS)) {
        syntax();
        exit;
    }

    if( array_key_exists( 'v', $_ARGS))
        $context->isVerbose = true;

    if( array_key_exists( 'l', $_ARGS))
        $context->logFileName = $_ARGS[ 'l'];

    if( array_key_exists( 'o', $_ARGS))
        $context->outFileName = $_ARGS[ 'o'];

    if( array_key_exists( 'c', $_ARGS))
        $context->cnFileName = $_ARGS[ 'c'];

    if( array_key_exists( 'd', $_ARGS))
        $context->directoryName = $_ARGS[ 'd'];

    if( $context->directoryName === null && $context->logFileName === null){
        syntax();
        exit;
    }

    if( $context->directoryName !== null && $context->logFileName !== null){
        syntax();
        exit;
    }
}

ini_set( 'memory_limit', '1024M');

$_ARGS = arguments( $argv);
$context = new cContext();
analyzeArguments( $_ARGS, $context);

if( !empty( $context->cnFileName))
    $context->commonNames = processCSVFile( $context->cnFileName, $context);

// Process single file
if( !empty( $context->logFileName)) {
    processLogFile( $context);
}
// Process directory
elseif( !empty( $context->directoryName)){
    $files = scandir( $context->directoryName);
    foreach( $files as $curFile) {
        if( preg_match('/^erreur_log_bo[^-]*-(\d+)$/', $curFile)){
            $context->logFileName = $context->directoryName . '/' . $curFile;
            processLogFile( $context);
        }
    }

}
 
createWorkBook( $context);

?>