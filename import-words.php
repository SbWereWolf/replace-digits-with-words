<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\Scripting\Config\EnvReader;
use SbWereWolf\Scripting\Convert\NanosecondsConverter;
use SbWereWolf\Scripting\FileSystem\Path;
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

$query = $db->prepare(
    '
INSERT INTO translation ( original, symbols, digits, length, number)
VALUES(:original,:symbols,:digits,:length,:number)
'
);

$original = '';
$symbols = '';
$length = 0;
$digits = '';
$number = 0;
$query->bindParam(':original', $original, PDO::PARAM_STR);
$query->bindParam(':symbols', $symbols, PDO::PARAM_STR);
$query->bindParam(':digits', $digits, PDO::PARAM_STR);
$query->bindParam(':length', $length, PDO::PARAM_INT);
$query->bindParam(':number', $number, PDO::PARAM_INT);

$rowsRead = 0;
$rowsInserted = 0;
$db->beginTransaction();
foreach ($words as $word) {
    if ($word !== '') {
        $message = "Prepare `$word`";
        $logger->notice($message);

        $rowsRead++;
        $prepared = (new WordFabric($word))->make();

        $original = $prepared->original();
        $symbols = $prepared->symbols();
        $length = $prepared->length();
        $digits = $prepared->digits();
        $number = $prepared->number();

        $isSuccess = $query->execute();
        if ($isSuccess) {
            $rowsInserted++;
        }

        $message =
            "`{$original}` `{$symbols}` `{$length}`"
            . " `{$digits}` `{$digits}`";
        $logger->notice($message);
    }
}
$db->commit();


$read = number_format($rowsRead, 0, ',', ' ');
$inserted = number_format($rowsInserted, 0, ',', ' ');
$scriptMaxMem =
    round(memory_get_peak_usage(true) / 1024 / 1024, 1);

$message =
    "Rows read `$read`," .
    "Rows inserted `$inserted`," .
    " max mem allocated is `$scriptMaxMem`Mb";
$logger->notice($message);

$finishMoment = hrtime(true);

$totalTime = $finishMoment - $startMoment;
$timeParts = new NanosecondsConverter();
$printout = $timeParts->print($totalTime);

$message = "Import duration is $printout";
$logger->notice($message);

$message = 'Script is finished';
$logger->notice($message);

$logger->close();
