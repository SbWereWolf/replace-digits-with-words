<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\Scripting\Config\EnvReader;
use SbWereWolf\Scripting\Convert\NanosecondsConverter;
use SbWereWolf\Scripting\FileSystem\Path;
use SbWereWolf\Substitution\Carrier;
use SbWereWolf\Substitution\WordFabric;

$message = date(DATE_ATOM) . ': Script is starting';
echo $message . PHP_EOL;

$startMoment = hrtime(true);

$pathParts = [__DIR__, 'vendor', 'autoload.php'];
$autoloaderPath = join(DIRECTORY_SEPARATOR, $pathParts);
require_once($autoloaderPath);

$logger = new Logger('import');

$pathComposer = new Path(__DIR__);
$logsPath = $pathComposer->make(
    [
        'logs',
        pathinfo(__FILE__, PATHINFO_FILENAME) . '-' . time() . '.log',
    ]
);

$writeHandler = new StreamHandler($logsPath);
$logger->pushHandler($writeHandler);

$logger->pushProcessor(function ($record) {
    /** @var LogRecord $record */
    echo "{$record->datetime} {$record->message}" . PHP_EOL;

    return $record;
});

$logger->notice($message);

$configPath = $pathComposer->make(['.env']);
(new EnvReader($configPath))->defineConstants();

$db = (new PDO(
    constant('DSN'),
    constant('LOGIN'),
    constant('PASSWORD'),
));


$message = 'Import starting';
$logger->notice($message);

$start = hrtime(true);

$wordsPath = $pathComposer->make(['data', 'words.txt']);

$message = "Reading words from file `$wordsPath`";
$logger->notice($message);

$wordsString = file_get_contents($wordsPath);

$eol = str_replace(
    'CR',
    "\r",
    str_replace(
        'LF',
        "\n",
        constant('END_OF_LINE')
    )
);
$words = explode($eol, $wordsString);

$importer = new Carrier($db, $words);
foreach ($importer->unload() as $message) {
    $logger->notice($message);
}


$finishMoment = hrtime(true);

$totalTime = $finishMoment - $startMoment;
$timeParts = new NanosecondsConverter();
$printout = $timeParts->print($totalTime);

$message = "Import duration is $printout";
$logger->notice($message);

$message = 'Script is finished';
$logger->notice($message);

$logger->close();
